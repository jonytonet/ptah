<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Handles CRUD form modal: open, edit, close and save.
 */
trait HasCrudForm
{
    // ── Form modal ─────────────────────────────────────────────────────────────

    /**
     * Resets the create form state — called by the Alpine "New" button.
     * Does NOT set showModal (Alpine handles visibility instantly on the client).
     */
    public function prepareCreate(): void
    {
        $this->formData = [];
        $this->formErrors = [];
        $this->editingId = null;
        $this->sdSearches = [];
        $this->sdResults = [];
        $this->sdLabels = [];
        $this->imageUploads = [];
        $this->formInstanceKey = ($this->formInstanceKey + 1) % 999;
        $this->dispatch('ptah:form-ready');
    }

    /**
     * Legacy alias — kept for backward compatibility.
     * Prefer the Alpine approach: @click="$wire.showModal = true; $wire.prepareCreate()"
     */
    public function openCreate(): void
    {
        $this->prepareCreate();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $record = $modelInstance->newQuery()->find($id);

        if (! $record) {
            return;
        }

        $this->editingId = $id;
        $this->formData = $record->toArray();
        $this->formErrors = [];
        $this->sdSearches = [];
        $this->sdResults = [];
        $this->imageUploads = [];
        $this->formInstanceKey = ($this->formInstanceKey + 1) % 999;

        // Pre-populate searchdropdown labels
        $this->preloadSdLabels($record);

        $this->showModal = true;
        $this->dispatch('ptah:form-ready');
    }

    /**
     * Opens the create modal pre-filled with another record's savable fields
     * ("copy record"). Guarded/audit fields are never copied; saving creates
     * a brand-new row.
     */
    public function duplicateRecord(int $id): void
    {
        if (! $this->authorizeCrudAction('create')) {
            return;
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $record = $modelInstance->newQuery()->find($id);

        if (! $record) {
            return;
        }

        $this->prepareCreate();

        $savable = array_column($this->getFormCols(), 'colsNomeFisico');
        $data = array_intersect_key($record->toArray(), array_flip($savable));

        foreach ($this->guardedFormFields() as $forbidden) {
            unset($data[$forbidden]);
        }

        $this->formData = $data;
        $this->preloadSdLabels($record);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->formData = [];
        $this->formErrors = [];
        $this->imageUploads = [];
    }

    public function save(): void
    {
        if ($this->creating) {
            return;
        }

        // Ptah permission check — fail-closed (anonymous users are denied when a
        // permissionIdentifier is configured and the module is active).
        if (! $this->authorizeCrudAction($this->editingId ? 'update' : 'create')) {
            $this->formErrors['_general'] = trans('ptah::ui.crud_permission_denied');

            return;
        }

        $this->creating = true;
        $this->formErrors = [];

        // Rich validation via FormValidatorService (required, email, min, max, regex, CPF, etc.)
        $formCols = $this->getFormCols();

        // Build a validation copy where pending file-uploads satisfy the required check
        $formDataForValidation = $this->formData;
        foreach ($formCols as $col) {
            if (($col['colsTipo'] ?? '') !== 'image') {
                continue;
            }
            $imgField = $col['colsNomeFisico'] ?? null;
            $imgUpload = $imgField ? ($this->imageUploads[$imgField] ?? null) : null;
            if ($imgField && $imgUpload && is_object($imgUpload) && method_exists($imgUpload, 'store')
                && empty($this->formData[$imgField])) {
                $formDataForValidation[$imgField] = '__upload_pending__';
            }
        }

        $this->formErrors = $this->formValidator->validate($formDataForValidation, $formCols);

        // Validate file size / type for each uploaded image
        $uploadErrors = $this->validateImageUploads($formCols);
        if (! empty($uploadErrors)) {
            $this->formErrors = array_merge($this->formErrors, $uploadErrors);
        }

        if (! empty($this->formErrors)) {
            $this->creating = false;

            return;
        }

        // Build data from only columns with colsGravar == 'S'
        $savableFields = array_column($formCols, 'colsNomeFisico');
        $data = array_intersect_key($this->formData, array_flip($savableFields));

        // Hard guard against mass assignment of sensitive/audit fields, regardless
        // of what the CRUD config marks as savable. These are set by the framework
        // or auditing logic, never by user-submitted form data.
        foreach ($this->guardedFormFields() as $forbidden) {
            unset($data[$forbidden]);
        }

        // Apply mask transforms before persisting (money→float, CPF→digits, etc.)
        $data = $this->applyMaskTransforms($data, $formCols);

        // Process file uploads — stores files and injects paths into $data
        $this->processImageUploads($data, $formCols);

        try {
            $modelInstance = $this->resolveEloquentModel();
            $fillable = $modelInstance->getFillable();
            $userId = Auth::id();

            if ($this->editingId) {
                $record = $modelInstance->newQuery()->findOrFail($this->editingId);
                // Hook: permite mutação dos dados antes de atualizar
                $this->beforeUpdate($data, $record);
                // Execute dynamic lifecycle hook from config
                $this->executeDynamicHook('beforeUpdate', $data, $record);
                // Record who updated
                if ($userId && in_array('updated_by', $fillable, true)) {
                    $data['updated_by'] = $userId;
                }
                $record->update($data);
                // Hook: ação após atualizar (pode retornar redirect)
                $redirect = $this->afterUpdate($record);
                // Execute dynamic lifecycle hook from config
                $this->executeDynamicHook('afterUpdate', $data, $record);
            } else {
                // Hook: permite mutação dos dados antes de criar
                $this->beforeCreate($data);
                // Execute dynamic lifecycle hook from config
                $this->executeDynamicHook('beforeCreate', $data);
                // Record who created
                if ($userId && in_array('created_by', $fillable, true)) {
                    $data['created_by'] = $userId;
                }
                if ($userId && in_array('updated_by', $fillable, true)) {
                    $data['updated_by'] = $userId;
                }
                $record = $modelInstance->newQuery()->create($data);
                // Hook: ação após criar (pode retornar redirect)
                $redirect = $this->afterCreate($record);
                // Execute dynamic lifecycle hook from config
                $this->executeDynamicHook('afterCreate', $data, $record);
            }

            // Invalidate cache
            $this->cacheService->invalidateModel($this->model);

            $this->closeModal();
            $this->dispatch('crud-saved', model: $this->model);
            $this->dispatch('ptah-toast', title: trans('ptah::ui.toast_saved'), color: 'success');

            // Se o hook retornou um RedirectResponse, executa o redirect
            if (isset($redirect) && $redirect instanceof RedirectResponse) {
                redirect($redirect->getTargetUrl());
            }
        } catch (\Throwable $e) {
            $this->formErrors['_general'] = trans('ptah::ui.crud_save_error', ['message' => $e->getMessage()]);
        }

        $this->creating = false;
    }

    /**
     * Saves the record and, on success, immediately reopens a blank create
     * form — for users registering several records in sequence.
     * Only meaningful on create; on edit it behaves exactly like save().
     */
    public function saveAndNew(): void
    {
        $wasEditing = (bool) $this->editingId;

        $this->save();

        if (empty($this->formErrors) && ! $wasEditing) {
            $this->prepareCreate();
            $this->showModal = true;
        }
    }

    // ── Lifecycle Hooks ────────────────────────────────────────────────────────

    /**
     * Chamado antes de inserir um novo registro.
     * Sobrescreva para mutate de $data ou disparar lógica pré-criação.
     *
     * @param  array<string, mixed>  $data  Dados do formulário (por referência)
     */
    protected function beforeCreate(array &$data): void {}

    /**
     * Chamado antes de atualizar um registro existente.
     * Sobrescreva para mutate de $data ou disparar lógica pré-atualização.
     *
     * @param  array<string, mixed>  $data  Dados do formulário (por referência)
     * @param  Model  $record  Registro que será atualizado
     */
    protected function beforeUpdate(array &$data, Model $record): void {}

    /**
     * Chamado após a criação bem-sucedida de um novo registro.
     * Retorne um RedirectResponse para redirecionar o usuário após o save.
     *
     * @param  Model  $record  Registro recém-criado
     * @return RedirectResponse|null
     */
    protected function afterCreate(Model $record): mixed
    {
        return null;
    }

    /**
     * Chamado após a atualização bem-sucedida de um registro existente.
     * Retorne um RedirectResponse para redirecionar o usuário após o save.
     *
     * @param  Model  $record  Registro atualizado
     * @return RedirectResponse|null
     */
    protected function afterUpdate(Model $record): mixed
    {
        return null;
    }

    /**
     * Executa lógica dinâmica definida no CrudConfig (lifecycle hooks).
     * Suporta duas sintaxes:
     * 1. Classe PHP (recomendado): @App\CrudHooks\ProductHooks::beforeCreate ou @ProductHooks::beforeCreate
     * 2. Expressão inline (Symfony ExpressionLanguage): merge(data, {'status': 'pending'})
     *
     * IMPORTANTE: hooks inline NÃO executam PHP arbitrário (sem eval()). São uma
     * única expressão segura avaliada via symfony/expression-language. A expressão
     * recebe as variáveis `data`, `record` e `user`, e — se retornar um array —
     * esse array substitui os dados do formulário. Funções seguras disponíveis:
     * merge(), now(), upper(), lower(), slug(), uuid(). Lógica complexa deve usar
     * hooks por classe.
     *
     * Com tratamento de erro robusto para não quebrar o save().
     *
     * @param  string  $hookName  Nome do hook: 'beforeCreate', 'afterCreate', 'beforeUpdate', 'afterUpdate'
     * @param  array  $data  Dados do formulário (referência mutável)
     * @param  Model|null  $record  Registro (para update hooks)
     */
    protected function executeDynamicHook(string $hookName, array &$data, ?Model $record = null): void
    {
        $hookCode = $this->crudConfig['lifecycleHooks'][$hookName] ?? null;

        if (empty($hookCode) || ! is_string($hookCode)) {
            return;
        }

        try {
            // Detect syntax: @Class::method or @Class@method = class-based, otherwise = inline expression
            if (str_starts_with(trim($hookCode), '@')) {
                $this->executeClassBasedHook($hookCode, $hookName, $data, $record);
            } else {
                $this->executeInlineHook($hookCode, $hookName, $data, $record);
            }

        } catch (\Throwable $e) {
            // Log error with full context but don't break execution
            Log::error(
                "[BaseCrud] Lifecycle hook '{$hookName}' failed for model {$this->model}",
                [
                    'hook' => $hookName,
                    'model' => $this->model,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'code' => $hookCode,
                ]
            );

            // Optionally notify user (commented out to avoid UI clutter)
            // $this->formErrors['_lifecycle_hook'] = "Hook {$hookName} error: " . $e->getMessage();
        }
    }

    /**
     * Executa hook baseado em classe PHP.
     * Sintaxes suportadas:
     * - @App\CrudHooks\ProductHooks::methodName
     * - @App\CrudHooks\ProductHooks@methodName
     * - @ProductHooks::methodName (usa namespace default App\CrudHooks)
     * - @ProductHooks@methodName (usa namespace default App\CrudHooks)
     *
     * @throws \RuntimeException Se classe ou método não existir
     */
    protected function executeClassBasedHook(string $hookCode, string $hookName, array &$data, ?Model $record): void
    {
        // Remove @ prefix and normalize separators
        $hookCode = ltrim(trim($hookCode), '@');
        $hookCode = str_replace('@', '::', $hookCode);

        // Parse class::method or use hookName as method
        if (str_contains($hookCode, '::')) {
            [$className, $methodName] = explode('::', $hookCode, 2);
        } else {
            $className = $hookCode;
            $methodName = $hookName; // Use hook name as method (beforeCreate, afterCreate, etc.)
        }

        // Add default namespace if not fully qualified
        if (! str_starts_with($className, '\\') && ! str_contains($className, '\\')) {
            $className = 'App\\CrudHooks\\'.$className;
        }

        // Validate class existence
        if (! class_exists($className)) {
            throw new \RuntimeException("Hook class not found: {$className}");
        }

        // Validate method existence
        if (! method_exists($className, $methodName)) {
            throw new \RuntimeException("Hook method not found: {$className}::{$methodName}");
        }

        // Instantiate and call method with component context
        $hookInstance = new $className;
        $hookInstance->{$methodName}($data, $record, $this);
    }

    /**
     * Field-level onChange formula (ScriptCase-style calculated fields).
     *
     * When the column that just changed declares `colsOnChange`, the expression
     * is evaluated in the same closed sandbox used by inline lifecycle hooks
     * (variables: `data`, `value`; functions: merge/now/upper/lower/slug/uuid).
     * An array result replaces the form data — e.g. quantity recalculating a
     * total: merge(data, {'total': data['qty'] * data['price']}).
     *
     * Errors are logged and never break the form.
     */
    public function applyFieldOnChange(string $field): void
    {
        $col = $this->findColByField($field);
        $expr = $col['colsOnChange'] ?? null;

        if (empty($expr) || ! is_string($expr)) {
            return;
        }

        try {
            $el = new ExpressionLanguage;
            $this->registerHookFunctions($el);

            $result = $el->evaluate($expr, [
                'data' => $this->formData,
                'value' => $this->formData[$field] ?? null,
            ]);

            if (is_array($result)) {
                $this->formData = $result;
            }
        } catch (\Throwable $e) {
            Log::warning("[BaseCrud] onChange formula failed for field {$field}", [
                'model' => $this->model,
                'expression' => $expr,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Executa hook inline como uma expressão segura (symfony/expression-language).
     *
     * Diferente de eval(), NÃO executa PHP arbitrário: avalia uma única expressão
     * num sandbox que só conhece as variáveis e funções expostas explicitamente.
     * Isso elimina o risco de RCE caso a configuração do CRUD seja adulterada.
     *
     * Variáveis disponíveis: `data` (array do formulário), `record` (model|null),
     * `user` (usuário autenticado|null). Se a expressão retornar um array, ele
     * substitui os dados do formulário. Ex.: `merge(data, {'status': 'pending'})`.
     */
    protected function executeInlineHook(string $hookCode, string $hookName, array &$data, ?Model $record): void
    {
        $expression = new ExpressionLanguage;
        $this->registerHookFunctions($expression);

        $result = $expression->evaluate($hookCode, [
            'data' => $data,
            'record' => $record,
            'user' => Auth::user(),
        ]);

        // Se a expressão retornar um array, ele passa a ser o novo conjunto de dados.
        if (is_array($result)) {
            $data = $result;
        }
    }

    /**
     * Registra as funções seguras disponíveis nas expressões de hook inline.
     * Apenas estas funções podem ser chamadas — qualquer outra resulta em erro,
     * mantendo o sandbox fechado por padrão.
     */
    protected function registerHookFunctions(ExpressionLanguage $el): void
    {
        $el->register(
            'merge',
            fn (...$args) => sprintf('array_merge(%s)', implode(', ', $args)),
            fn (array $values, ...$arrays) => array_merge(...array_map(fn ($a) => (array) $a, $arrays)),
        );
        $el->register('now', fn () => 'now()', fn () => now());
        $el->register('upper', fn ($s) => "mb_strtoupper({$s})", fn (array $v, $s) => mb_strtoupper((string) $s));
        $el->register('lower', fn ($s) => "mb_strtolower({$s})", fn (array $v, $s) => mb_strtolower((string) $s));
        $el->register('slug', fn ($s) => "Str::slug({$s})", fn (array $v, $s) => Str::slug((string) $s));
        $el->register('uuid', fn () => 'Str::uuid()', fn () => (string) Str::uuid());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Centralised, fail-closed authorization for CRUD write actions
     * (create/update/delete/restore). Used by save() and the deletion concern.
     *
     * Rules:
     *  - Permissions module disabled → allowed (feature is off).
     *  - No permissionIdentifier configured → allowed (CRUD opts out of gating).
     *  - Identifier configured but no authenticated user → DENIED (fail-closed).
     *  - Otherwise → delegated to ptah_can().
     */
    protected function authorizeCrudAction(string $action): bool
    {
        if (! config('ptah.modules.permissions')) {
            return true;
        }

        $key = $this->crudConfig['permissions']['permissionIdentifier'] ?? null;

        if (! $key) {
            return true;
        }

        if (! Auth::check()) {
            return false;
        }

        return ptah_can($key, $action);
    }

    /**
     * Fields that must never be set from user-submitted form data, even if the
     * CRUD config marks them as savable. Primary keys, timestamps and audit
     * columns are managed by the framework / auditing layer.
     *
     * @return array<int, string>
     */
    protected function guardedFormFields(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'remember_token',
        ];
    }

    protected function getFormCols(): array
    {
        return array_values(
            array_filter(
                $this->crudConfig['cols'] ?? [],
                fn ($c) => $this->ptahBool($c['colsGravar'] ?? false)
                       && $this->ptahBool($c['colsEditableForm'] ?? true)
            )
        );
    }

    protected function findColByField(string $field): ?array
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsNomeFisico'] ?? null) === $field) {
                return $col;
            }
        }

        return null;
    }

    protected function getCellValue(array $col, mixed $row): mixed
    {
        $field = $col['colsNomeFisico'] ?? '';

        if ($row instanceof Model) {
            return $row->getAttribute($field);
        }

        return $row[$field] ?? null;
    }

    protected function preloadSdLabels(Model $record): void
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsTipo'] ?? '') !== 'searchdropdown') {
                continue;
            }

            $field = $col['colsNomeFisico'] ?? '';
            $rel = $col['colsRelacao'] ?? null;
            $exibe = $col['colsRelacaoExibe'] ?? null;

            if ($rel && $exibe && $record->{$rel}) {
                $this->sdLabels[$field] = $record->{$rel}->{$exibe} ?? '';
            }
        }
    }

    /**
     * Applies mask transforms to form data before persisting to DB.
     * e.g. money_brl → float, CPF/CNPJ → digits only, uppercase, etc.
     */
    protected function applyMaskTransforms(array $data, array $formCols): array
    {
        foreach ($formCols as $col) {
            $field = $col['colsNomeFisico'] ?? null;
            $transform = $col['colsMaskTransform'] ?? null;

            if (! $field || ! $transform || ! array_key_exists($field, $data)) {
                continue;
            }

            $val = $data[$field];

            $data[$field] = match ($transform) {
                // "R$ 1.253,08" → 1253.08  |  "25.5" → 25.5  |  "25,50" → 25.5
                'money_to_float' => (function () use ($val): float {
                    $s = trim((string) $val);
                    $clean = preg_replace('/[^0-9.,]/', '', $s);
                    if ($clean === '') {
                        return 0.0;
                    }
                    $dotPos = strrpos($clean, '.');
                    $commaPos = strrpos($clean, ',');
                    // Both separators present → whichever is last is the decimal
                    if ($dotPos !== false && $commaPos !== false) {
                        if ($commaPos > $dotPos) {
                            // BR format: "1.234,56" → 1234.56
                            return (float) str_replace(['.', ','], ['', '.'], $clean);
                        }

                        // EN format: "1,234.56" → 1234.56
                        return (float) str_replace(',', '', $clean);
                    }
                    // Only comma: "25,50" (decimal) or "1,000" (thousands)
                    if ($commaPos !== false) {
                        $decimals = substr($clean, $commaPos + 1);

                        return strlen($decimals) <= 2
                            ? (float) str_replace(',', '.', $clean)
                            : (float) str_replace(',', '', $clean);
                    }

                    // Only dot or no separator: plain float "25.50" or integer "25550"
                    return (float) $clean;
                })(),
                // "055.465.309-52" → "05546530952"
                'digits_only' => preg_replace('/\D/', '', (string) $val),
                // Uppercase + alphanumeric only (license plate)
                'plate_clean' => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $val)),
                // "01/12/2024" → "2024-12-01"
                'date_br_to_iso' => (function () use ($val): string {
                    $d = \DateTime::createFromFormat('d/m/Y', (string) $val);

                    return $d ? $d->format('Y-m-d') : (string) $val;
                })(),
                // "2024-12-01" → "01/12/2024"
                'date_iso_to_br' => (function () use ($val): string {
                    $d = \DateTime::createFromFormat('Y-m-d', (string) $val);

                    return $d ? $d->format('d/m/Y') : (string) $val;
                })(),
                'uppercase' => mb_strtoupper((string) $val),
                'lowercase' => mb_strtolower((string) $val),
                'trim' => trim((string) $val),
                default => $val,
            };
        }

        return $data;
    }

    // ── Image upload helpers ───────────────────────────────────────────────────

    /**
     * Validates file size and allowed types for every pending image upload.
     * Returns an array of field => error-message pairs (empty = no errors).
     */
    protected function validateImageUploads(array $formCols): array
    {
        $errors = [];

        foreach ($formCols as $col) {
            if (($col['colsTipo'] ?? '') !== 'image') {
                continue;
            }

            $field = $col['colsNomeFisico'] ?? null;
            $upload = $field ? ($this->imageUploads[$field] ?? null) : null;

            if (! $field || ! $upload || ! is_object($upload) || ! method_exists($upload, 'store')) {
                continue;
            }

            // File size in KB
            $maxKb = (int) ($col['colsUploadMaxSize'] ?? 2048);
            if (method_exists($upload, 'getSize') && $upload->getSize() > $maxKb * 1024) {
                $errors[$field] = trans('ptah::ui.image_error_size', ['max' => $maxKb]);

                continue;
            }

            // Allowed extensions
            $allowedRaw = $col['colsUploadAllowedTypes'] ?? null;
            if ($allowedRaw) {
                $allowed = is_array($allowedRaw) ? $allowedRaw : array_map('trim', explode(',', $allowedRaw));
                $ext = method_exists($upload, 'getClientOriginalExtension')
                    ? strtolower($upload->getClientOriginalExtension())
                    : '';
                if ($ext && ! in_array($ext, $allowed, true)) {
                    $errors[$field] = trans('ptah::ui.image_error_type', ['types' => implode(', ', $allowed)]);
                }
            }
        }

        return $errors;
    }

    /**
     * Stores every pending TemporaryUploadedFile and injects the stored path
     * into $data, overwriting any URL value the user may have typed.
     */
    protected function processImageUploads(array &$data, array $formCols): void
    {
        foreach ($formCols as $col) {
            if (($col['colsTipo'] ?? '') !== 'image') {
                continue;
            }

            $field = $col['colsNomeFisico'] ?? null;
            $upload = $field ? ($this->imageUploads[$field] ?? null) : null;

            if (! $field || ! $upload || ! is_object($upload) || ! method_exists($upload, 'store')) {
                continue;
            }

            $path = $this->resolveUploadPath($col);
            $stored = $upload->store($path, 'public');

            if ($stored !== false) {
                $data[$field] = $stored;
                $this->imageUploads[$field] = null;
            }
        }
    }

    /**
     * Derives the storage path for an image column.
     * Priority: colsUploadPath config → auto-derived from model name.
     *
     * Examples (auto-derived):
     *   App\Models\Product             → images/product
     *   App\Models\Product\ProductStock → images/product/product-stock
     */
    protected function resolveUploadPath(array $col): string
    {
        if (! empty($col['colsUploadPath'])) {
            return (string) $col['colsUploadPath'];
        }

        $modelStr = $this->model ?? '';
        // Strip namespace prefixes
        $modelStr = str_replace(['App\\Models\\', 'App/Models/'], '', $modelStr);
        // Split on \ or /
        $segments = preg_split('/[\\\\\/]/', $modelStr) ?: [$modelStr];
        // Convert each segment to kebab-case
        $segments = array_map(
            fn ($s) => Str::kebab($s),
            array_filter($segments)
        );

        return 'images/'.implode('/', $segments);
    }
}

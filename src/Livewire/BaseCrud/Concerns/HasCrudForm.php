<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

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
        $this->formData        = [];
        $this->formErrors      = [];
        $this->editingId       = null;
        $this->sdSearches      = [];
        $this->sdResults       = [];
        $this->sdLabels        = [];
        $this->imageUploads    = [];
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

        $this->editingId       = $id;
        $this->formData        = $record->toArray();
        $this->formErrors      = [];
        $this->sdSearches      = [];
        $this->sdResults       = [];
        $this->imageUploads    = [];
        $this->formInstanceKey = ($this->formInstanceKey + 1) % 999;

        // Pre-populate searchdropdown labels
        $this->preloadSdLabels($record);

        $this->showModal = true;
        $this->dispatch('ptah:form-ready');
    }

    public function closeModal(): void
    {
        $this->showModal    = false;
        $this->editingId    = null;
        $this->formData     = [];
        $this->formErrors   = [];
        $this->imageUploads = [];
    }

    public function save(): void
    {
        if ($this->creating) {
            return;
        }

        // Ptah permission check (only when module is active and permissionIdentifier configured)
        if (config('ptah.modules.permissions') && \Illuminate\Support\Facades\Auth::check()) {
            $key    = $this->crudConfig['permissions']['permissionIdentifier'] ?? null;
            $action = $this->editingId ? 'update' : 'create';
            if ($key && ! ptah_can($key, $action)) {
                $this->formErrors['_general'] = trans('ptah::ui.crud_permission_denied');
                return;
            }
        }

        $this->creating   = true;
        $this->formErrors = [];

        // Rich validation via FormValidatorService (required, email, min, max, regex, CPF, etc.)
        $formCols = $this->getFormCols();

        // Build a validation copy where pending file-uploads satisfy the required check
        $formDataForValidation = $this->formData;
        foreach ($formCols as $col) {
            if (($col['colsTipo'] ?? '') !== 'image') {
                continue;
            }
            $imgField  = $col['colsNomeFisico'] ?? null;
            $imgUpload = $imgField ? ($this->imageUploads[$imgField] ?? null) : null;
            if ($imgField && $imgUpload && is_object($imgUpload) && method_exists($imgUpload, 'store')
                && empty($this->formData[$imgField])) {
                $formDataForValidation[$imgField] = '__upload_pending__';
            }
        }

        $this->formErrors = $this->formValidator->validate($formDataForValidation, $formCols);

        // Validate file size / type for each uploaded image
        $uploadErrors     = $this->validateImageUploads($formCols);
        if (! empty($uploadErrors)) {
            $this->formErrors = array_merge($this->formErrors, $uploadErrors);
        }

        if (! empty($this->formErrors)) {
            $this->creating = false;
            return;
        }

        // Build data from only columns with colsGravar == 'S'
        $savableFields = array_column($formCols, 'colsNomeFisico');
        $data          = array_intersect_key($this->formData, array_flip($savableFields));

        // Apply mask transforms before persisting (money→float, CPF→digits, etc.)
        $data = $this->applyMaskTransforms($data, $formCols);

        // Process file uploads — stores files and injects paths into $data
        $this->processImageUploads($data, $formCols);

        try {
            $modelInstance = $this->resolveEloquentModel();
            $fillable      = $modelInstance->getFillable();
            $userId        = \Illuminate\Support\Facades\Auth::id();

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
            if (isset($redirect) && $redirect instanceof \Illuminate\Http\RedirectResponse) {
                redirect($redirect->getTargetUrl());
            }
        } catch (\Throwable $e) {
            $this->formErrors['_general'] = trans('ptah::ui.crud_save_error', ['message' => $e->getMessage()]);
        }

        $this->creating = false;
    }

    // ── Lifecycle Hooks ────────────────────────────────────────────────────────

    /**
     * Chamado antes de inserir um novo registro.
     * Sobrescreva para mutate de $data ou disparar lógica pré-criação.
     *
     * @param array<string, mixed> $data  Dados do formulário (por referência)
     */
    protected function beforeCreate(array &$data): void {}

    /**
     * Chamado antes de atualizar um registro existente.
     * Sobrescreva para mutate de $data ou disparar lógica pré-atualização.
     *
     * @param array<string, mixed>                          $data   Dados do formulário (por referência)
     * @param \Illuminate\Database\Eloquent\Model $record  Registro que será atualizado
     */
    protected function beforeUpdate(array &$data, \Illuminate\Database\Eloquent\Model $record): void {}

    /**
     * Chamado após a criação bem-sucedida de um novo registro.
     * Retorne um RedirectResponse para redirecionar o usuário após o save.
     *
     * @param  \Illuminate\Database\Eloquent\Model          $record  Registro recém-criado
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function afterCreate(\Illuminate\Database\Eloquent\Model $record): mixed { return null; }

    /**
     * Chamado após a atualização bem-sucedida de um registro existente.
     * Retorne um RedirectResponse para redirecionar o usuário após o save.
     *
     * @param  \Illuminate\Database\Eloquent\Model          $record  Registro atualizado
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function afterUpdate(\Illuminate\Database\Eloquent\Model $record): mixed { return null; }

    /**
     * Executa código PHP dinâmico definido no CrudConfig (lifecycle hooks).
     * Suporta duas sintaxes:
     * 1. Classe PHP: @App\CrudHooks\ProductHooks::beforeCreate ou @ProductHooks::beforeCreate
     * 2. Código inline: Log::info("Criando produto", $data);
     *
     * Com tratamento de erro robusto para não quebrar o save().
     *
     * @param string $hookName Nome do hook: 'beforeCreate', 'afterCreate', 'beforeUpdate', 'afterUpdate'
     * @param array $data Dados do formulário (referência mutável)
     * @param \Illuminate\Database\Eloquent\Model|null $record Registro (para update hooks)
     */
    protected function executeDynamicHook(string $hookName, array &$data, ?\Illuminate\Database\Eloquent\Model $record = null): void
    {
        $hookCode = $this->crudConfig['lifecycleHooks'][$hookName] ?? null;

        if (empty($hookCode) || !is_string($hookCode)) {
            return;
        }

        try {
            // Detect syntax: @Class::method or @Class@method = class-based, otherwise = inline eval
            if (str_starts_with(trim($hookCode), '@')) {
                $this->executeClassBasedHook($hookCode, $hookName, $data, $record);
            } else {
                $this->executeInlineHook($hookCode, $hookName, $data, $record);
            }

        } catch (\Throwable $e) {
            // Log error with full context but don't break execution
            \Illuminate\Support\Facades\Log::error(
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
    protected function executeClassBasedHook(string $hookCode, string $hookName, array &$data, ?\Illuminate\Database\Eloquent\Model $record): void
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
        if (!str_starts_with($className, '\\') && !str_contains($className, '\\')) {
            $className = 'App\\CrudHooks\\' . $className;
        }

        // Validate class existence
        if (!class_exists($className)) {
            throw new \RuntimeException("Hook class not found: {$className}");
        }

        // Validate method existence
        if (!method_exists($className, $methodName)) {
            throw new \RuntimeException("Hook method not found: {$className}::{$methodName}");
        }

        // Instantiate and call method with component context
        $hookInstance = new $className();
        $hookInstance->{$methodName}($data, $record, $this);
    }

    /**
     * Executa hook baseado em código inline via eval().
     * Código roda em closure isolada com variáveis disponíveis.
     */
    protected function executeInlineHook(string $hookCode, string $hookName, array &$data, ?\Illuminate\Database\Eloquent\Model $record): void
    {
        // Create isolated closure with available variables
        $closure = function () use ($hookCode, &$data, $record) {
            // Available variables for hook code:
            // - $data (by reference) - form data array
            // - $record (read-only) - Eloquent model instance (null for beforeCreate)
            // - $this - access to component methods (use with caution)
            eval($hookCode);
        };

        // Execute closure in component context
        $closure->call($this);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    protected function getFormCols(): array
    {
        return array_values(
            array_filter(
                $this->crudConfig['cols'] ?? [],
                fn($c) => $this->ptahBool($c['colsGravar'] ?? false)
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

        if ($row instanceof \Illuminate\Database\Eloquent\Model) {
            return $row->getAttribute($field);
        }

        return $row[$field] ?? null;
    }

    protected function preloadSdLabels(\Illuminate\Database\Eloquent\Model $record): void
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsTipo'] ?? '') !== 'searchdropdown') {
                continue;
            }

            $field = $col['colsNomeFisico'] ?? '';
            $rel   = $col['colsRelacao']      ?? null;
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
            $field     = $col['colsNomeFisico'] ?? null;
            $transform = $col['colsMaskTransform'] ?? null;

            if (! $field || ! $transform || ! array_key_exists($field, $data)) {
                continue;
            }

            $val = $data[$field];

            $data[$field] = match ($transform) {
                // "R$ 1.253,08" → 1253.08  |  "25.5" → 25.5  |  "25,50" → 25.5
                'money_to_float' => (function () use ($val): float {
                    $s     = trim((string) $val);
                    $clean = preg_replace('/[^0-9.,]/', '', $s);
                    if ($clean === '') return 0.0;
                    $dotPos   = strrpos($clean, '.');
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
                'trim'      => trim((string) $val),
                default     => $val,
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

            $field  = $col['colsNomeFisico'] ?? null;
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
                $ext     = method_exists($upload, 'getClientOriginalExtension')
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

            $field  = $col['colsNomeFisico'] ?? null;
            $upload = $field ? ($this->imageUploads[$field] ?? null) : null;

            if (! $field || ! $upload || ! is_object($upload) || ! method_exists($upload, 'store')) {
                continue;
            }

            $path   = $this->resolveUploadPath($col);
            $stored = $upload->store($path, 'public');

            if ($stored !== false) {
                $data[$field]                  = $stored;
                $this->imageUploads[$field]    = null;
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
            fn($s) => \Illuminate\Support\Str::kebab($s),
            array_filter($segments)
        );

        return 'images/' . implode('/', $segments);
    }
}

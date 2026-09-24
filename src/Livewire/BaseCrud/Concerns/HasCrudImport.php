<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Ptah\Services\Crud\CrudImportReader;

/**
 * Spreadsheet import into a BaseCrud screen: upload → map → preview → import.
 *
 * Opt-in per screen (`importConfig.enabled`), like export. Every row goes
 * through what a save from the form goes through — the same form columns,
 * the same FormValidatorService rules, the same mask transforms, the same
 * lifecycle hooks, the same audit stamps, the same tenant and locked-filter
 * scope — so a spreadsheet cannot put into the table anything the screen
 * would refuse.
 *
 * All or nothing: rows are re-validated on import (the preview is never
 * trusted — the mapping is client state) and written in ONE transaction; a
 * single row the database refuses rolls the whole file back and is reported
 * by line. A half-imported file is the worst outcome for the person who has
 * to find which half.
 *
 * The rows themselves never enter Livewire state (a 2,000-row payload on
 * every request): the uploaded file is re-read at each step.
 */
trait HasCrudImport
{
    public bool $showImportModal = false;

    public int $importStep = 1;

    /** @var mixed Livewire TemporaryUploadedFile */
    public $importFile = null;

    /** @var array<int|string, string> header index => field (client-writable: checked on use) */
    public array $importMapping = [];

    /** @var list<string> */
    #[Locked]
    public array $importHeaders = [];

    /** @var array{total?: int, valid?: int, errors?: list<array{line: int, field: string, message: string}>, more_errors?: int} */
    #[Locked]
    public array $importPreview = [];

    /** @var array{created?: int, updated?: int, error?: string} */
    #[Locked]
    public array $importResult = [];

    private const IMPORT_ERRORS_SHOWN = 50;

    public function importEnabled(): bool
    {
        return ! empty($this->crudConfig['importConfig']['enabled'])
            && $this->authorizeCrudAction('create')
            && $this->crudConfigAllows('create')
            && $this->crudConfigAllows('import');
    }

    public function openImport(): void
    {
        if (! $this->importEnabled()) {
            return;
        }

        $this->resetImport();
        $this->showImportModal = true;
    }

    public function closeImport(): void
    {
        $this->showImportModal = false;
        $this->resetImport();
    }

    public function updatedImportFile(): void
    {
        if (! $this->importEnabled()) {
            $this->resetImport();

            return;
        }

        $maxKb = (int) ($this->crudConfig['importConfig']['maxKb'] ?? 10240);

        $this->validate([
            'importFile' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', CrudImportReader::EXTENSIONS)],
        ]);

        try {
            $table = $this->readImportFile();
        } catch (\Throwable $e) {
            $this->addError('importFile', trans('ptah::ui.import_unreadable', ['message' => $e->getMessage()]));

            return;
        }

        if ($table['headers'] === [] || $table['rows'] === []) {
            $this->addError('importFile', trans('ptah::ui.import_empty'));

            return;
        }

        $max = $this->importMaxRows();
        if (count($table['rows']) > $max) {
            $this->addError('importFile', trans('ptah::ui.import_too_many', ['rows' => count($table['rows']), 'max' => $max]));

            return;
        }

        $this->importHeaders = $table['headers'];
        $this->importMapping = CrudImportReader::autoMap($table['headers'], $this->importFormCols());
        $this->importStep = 2;
    }

    public function previewImport(): void
    {
        if (! $this->importEnabled() || $this->importFile === null) {
            return;
        }

        $report = $this->prepareImportRows();

        $this->importPreview = [
            'total' => $report['total'],
            'valid' => count($report['rows']),
            'errors' => array_slice($report['errors'], 0, self::IMPORT_ERRORS_SHOWN),
            'more_errors' => max(0, count($report['errors']) - self::IMPORT_ERRORS_SHOWN),
            'unmapped_required' => $report['unmapped_required'],
        ];
        $this->importStep = 3;
    }

    public function runImport(): void
    {
        if (! $this->importEnabled() || $this->importFile === null) {
            return;
        }

        // A previa nao e confiavel: o mapeamento e estado do cliente.
        $report = $this->prepareImportRows();

        if ($report['errors'] !== [] || $report['unmapped_required'] !== []) {
            $this->previewImport();

            return;
        }

        $model = $this->resolveEloquentModel();
        $formCols = $this->importFormCols();
        $mode = ($this->crudConfig['importConfig']['mode'] ?? 'create') === 'upsert' ? 'upsert' : 'create';
        $key = (string) ($this->crudConfig['importConfig']['key'] ?? '');
        $canUpdate = $this->authorizeCrudAction('update') && $this->crudConfigAllows('update');
        $userId = Auth::id();
        $fillable = $model->getFillable();
        $created = $updated = 0;
        $line = 0;

        try {
            DB::connection($model->getConnectionName())->transaction(function () use ($report, $model, $mode, $key, $canUpdate, $userId, $fillable, &$created, &$updated, &$line) {
                foreach ($report['rows'] as ['line' => $line, 'data' => $data]) {
                    $existing = null;

                    if ($mode === 'upsert' && $key !== '' && array_key_exists($key, $data) && $data[$key] !== null) {
                        $existing = $this->scopedQuery()?->where($model->getTable().'.'.$key, $data[$key])->first();

                        if ($existing && ! $canUpdate) {
                            throw new \RuntimeException(trans('ptah::ui.crud_permission_denied'));
                        }
                    }

                    if ($existing) {
                        $this->beforeUpdate($data, $existing);
                        $this->executeDynamicHook('beforeUpdate', $data, $existing);
                        if ($userId && in_array('updated_by', $fillable, true)) {
                            $data['updated_by'] = $userId;
                        }
                        $existing->update($data);
                        $this->afterUpdate($existing);
                        $this->executeDynamicHook('afterUpdate', $data, $existing);
                        $updated++;

                        continue;
                    }

                    $data = $this->applyImportScope($data, $model);
                    $this->beforeCreate($data);
                    $this->executeDynamicHook('beforeCreate', $data);
                    foreach (['created_by', 'updated_by'] as $stamp) {
                        if ($userId && in_array($stamp, $fillable, true)) {
                            $data[$stamp] = $userId;
                        }
                    }
                    $record = $model->newQuery()->create($data);
                    $this->afterCreate($record);
                    $this->executeDynamicHook('afterCreate', $data, $record);
                    $created++;
                }
            });
        } catch (\Throwable $e) {  // CrudHookAbort de um hook critico incluso
            $this->importResult = ['error' => trans('ptah::ui.import_failed', ['line' => $line, 'message' => $e->getMessage()])];
            $this->importStep = 4;

            return;
        }

        $this->importResult = ['created' => $created, 'updated' => $updated];
        $this->importStep = 4;
        $this->cacheService->invalidateModel($this->model);
        $this->dispatch('ptah-toast', title: trans('ptah::ui.import_done', ['created' => $created, 'updated' => $updated]), color: 'success');
    }

    /**
     * Every row, coerced, transformed and validated like a form submission.
     *
     * @return array{total: int, rows: list<array{line: int, data: array<string, mixed>}>, errors: list<array{line: int, field: string, message: string}>, unmapped_required: list<string>}
     */
    protected function prepareImportRows(): array
    {
        $table = $this->readImportFile();
        $formCols = $this->importFormCols();
        $colsByField = [];
        foreach ($formCols as $col) {
            $colsByField[(string) $col['colsNomeFisico']] = $col;
        }

        // Mapeamento vem do cliente: so campos do formulario, cada um uma vez.
        $mapping = [];
        foreach ($this->importMapping as $index => $field) {
            if (is_string($field) && isset($colsByField[$field]) && ! in_array($field, $mapping, true) && isset($table['headers'][(int) $index])) {
                $mapping[(int) $index] = $field;
            }
        }

        $unmappedRequired = array_values(array_map(
            fn (array $c) => (string) ($c['colsNomeLogico'] ?? $c['colsNomeFisico']),
            array_filter($formCols, fn (array $c) => $this->ptahBool($c['colsRequired'] ?? false) && ! in_array($c['colsNomeFisico'], $mapping, true))
        ));

        $scopeRelated = $this->importRelatedScope();
        $rows = $errors = [];

        foreach ($table['rows'] as $i => $cells) {
            $line = $i + 2; // cabecalho e a linha 1
            $data = [];
            $rowErrors = [];

            foreach ($mapping as $index => $field) {
                [$value, $error] = CrudImportReader::coerce($cells[$index] ?? null, $colsByField[$field], $scopeRelated);
                if ($error !== null) {
                    $rowErrors[$field] = $error;
                }
                $data[$field] = $value;
            }

            // Mascaras so no que veio preenchido: money_to_float de uma celula
            // vazia gravaria 0.
            $filled = array_filter($data, fn ($v) => $v !== null);
            $data = array_merge($data, $this->applyMaskTransforms($filled, $formCols));

            $rowErrors += $this->formValidator->validate($data, array_values(array_filter($formCols, fn ($c) => in_array($c['colsNomeFisico'], $mapping, true) || $this->ptahBool($c['colsRequired'] ?? false))));

            foreach ($this->guardedFormFields() as $forbidden) {
                unset($data[$forbidden]);
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $field => $message) {
                    $errors[] = ['line' => $line, 'field' => (string) $field, 'message' => is_array($message) ? implode(' ', $message) : (string) $message];
                }

                continue;
            }

            $rows[] = ['line' => $line, 'data' => $data];
        }

        return ['total' => count($table['rows']), 'rows' => $rows, 'errors' => $errors, 'unmapped_required' => $unmappedRequired];
    }

    /**
     * The fields a spreadsheet may fill: exactly the form's savable fields.
     *
     * @return list<array<string, mixed>>
     */
    protected function importFormCols(): array
    {
        return array_values(array_filter(
            $this->getFormCols(),
            fn (array $c) => ! empty($c['colsNomeFisico'])
                && ($c['colsTipo'] ?? '') !== 'image'
                && ! in_array($c['colsNomeFisico'], $this->guardedFormFields(), true)
                && ! in_array($c['colsNomeFisico'], $this->deniedColumns, true)
        ));
    }

    /**
     * Company and locked filters on every created row — a spreadsheet row must
     * land where a record created from this screen would.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyImportScope(array $data, Model $model): array
    {
        $companyField = (string) ($this->crudConfig['companyField'] ?? 'company_id');

        if ($this->companyFilter > 0 && Schema::hasColumn($model->getTable(), $companyField)) {
            $data[$companyField] = $this->companyFilter;
        }

        foreach ($this->lockedFilters as $column => $value) {
            $data[(string) $column] = $value;
        }

        return $data;
    }

    /**
     * Relation lookups by name stay inside the active company.
     */
    protected function importRelatedScope(): ?\Closure
    {
        if ($this->companyFilter <= 0) {
            return null;
        }

        $companyField = (string) ($this->crudConfig['companyField'] ?? 'company_id');
        $company = $this->companyFilter;

        return function (Builder $query) use ($companyField, $company): void {
            if (Schema::hasColumn($query->getModel()->getTable(), $companyField)) {
                $query->where($query->getModel()->getTable().'.'.$companyField, $company);
            }
        };
    }

    protected function importMaxRows(): int
    {
        return max(1, (int) ($this->crudConfig['importConfig']['maxRows'] ?? 2000));
    }

    /**
     * @return array{headers: list<string>, rows: list<list<mixed>>}
     */
    protected function readImportFile(): array
    {
        return CrudImportReader::read(
            $this->importFile->getRealPath(),
            (string) $this->importFile->getClientOriginalExtension()
        );
    }

    protected function resetImport(): void
    {
        $this->importStep = 1;
        $this->importFile = null;
        $this->importMapping = [];
        $this->importHeaders = [];
        $this->importPreview = [];
        $this->importResult = [];
        $this->resetErrorBag('importFile');
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

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
        $this->formData   = [];
        $this->formErrors = [];
        $this->editingId  = null;
        $this->sdSearches = [];
        $this->sdResults  = [];
        $this->sdLabels   = [];
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

        $this->editingId  = $id;
        $this->formData   = $record->toArray();
        $this->formErrors = [];
        $this->sdSearches = [];
        $this->sdResults  = [];

        // Pre-populate searchdropdown labels
        $this->preloadSdLabels($record);

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal  = false;
        $this->editingId  = null;
        $this->formData   = [];
        $this->formErrors = [];
    }

    public function save(): void
    {
        if ($this->creating) {
            return;
        }

        $this->creating   = true;
        $this->formErrors = [];

        // Rich validation via FormValidatorService (required, email, min, max, regex, CPF, etc.)
        $formCols = $this->getFormCols();
        $this->formErrors = $this->formValidator->validate($this->formData, $formCols);

        if (! empty($this->formErrors)) {
            $this->creating = false;
            return;
        }

        // Build data from only columns with colsGravar == 'S'
        $savableFields = array_column($formCols, 'colsNomeFisico');
        $data          = array_intersect_key($this->formData, array_flip($savableFields));

        // Apply mask transforms before persisting (money→float, CPF→digits, etc.)
        $data = $this->applyMaskTransforms($data, $formCols);

        try {
            $modelInstance = $this->resolveEloquentModel();
            $fillable      = $modelInstance->getFillable();
            $userId        = \Illuminate\Support\Facades\Auth::id();

            if ($this->editingId) {
                $record = $modelInstance->newQuery()->findOrFail($this->editingId);
                // Record who updated
                if ($userId && in_array('updated_by', $fillable, true)) {
                    $data['updated_by'] = $userId;
                }
                $record->update($data);
            } else {
                // Record who created
                if ($userId && in_array('created_by', $fillable, true)) {
                    $data['created_by'] = $userId;
                }
                if ($userId && in_array('updated_by', $fillable, true)) {
                    $data['updated_by'] = $userId;
                }
                $modelInstance->newQuery()->create($data);
            }

            // Invalidate cache
            $this->cacheService->invalidateModel($this->model);

            $this->closeModal();
            $this->dispatch('crud-saved', model: $this->model);
        } catch (\Throwable $e) {
            $this->formErrors['_general'] = trans('ptah::ui.crud_save_error', [':message' => $e->getMessage()]);
        }

        $this->creating = false;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    protected function getFormCols(): array
    {
        return array_values(
            array_filter(
                $this->crudConfig['cols'] ?? [],
                fn($c) => $this->ptahBool($c['colsGravar'] ?? false)
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
                // "R$ 1.253,08" → 1253.08
                'money_to_float' => (float) str_replace(
                    ['.', ','],
                    ['',  '.'],
                    preg_replace('/[^0-9,]/', '', (string) $val)
                ),
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
}

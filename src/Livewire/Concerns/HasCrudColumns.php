<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

/**
 * Handles column visibility, ordering, and width preferences.
 *
 * formDataColumns structure: [fieldName => bool]   (true = visible, false = hidden)
 */
trait HasCrudColumns
{
    // ── Reorder / resize ───────────────────────────────────────────────────────

    /**
     * Saves the new column order after a user drag-and-drop.
     * Called via $wire.call('reorderColumns', ['field1', 'field2', ...]) from JS.
     */
    public function reorderColumns(array $newOrder): void
    {
        $this->columnOrder = $newOrder;
        $this->savePreferences();
    }

    /**
     * Saves the width of a column resized by the user.
     * Called via $wire.call('saveColumnWidth', 'field', 150) from JS.
     */
    public function saveColumnWidth(string $column, int $width): void
    {
        $this->columnWidths[$column] = max(60, $width);
        $this->savePreferences();
    }

    // ── Column visibility initialisation ──────────────────────────────────────

    /**
     * Initialises the visibility map from the CrudConfig columns.
     * Merges with any already-loaded preferences (preserves user choices).
     */
    protected function initFormDataColumns(): void
    {
        $defaults = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $field = $col['colsNomeFisico'] ?? null;
            if ($field) {
                $defaults[$field] = true; // visible by default
            }
        }

        // Merge with saved preferences (user choices take priority)
        $this->formDataColumns = array_merge($defaults, $this->formDataColumns);
        $this->updateHiddenColumnsCount();
    }

    // ── Visibility toggles ────────────────────────────────────────────────────

    /**
     * Persists the current visibility state after the user changes a toggle.
     * The actual values are already updated by Livewire via wire:model on formDataColumns.
     */
    public function updateColumns(): void
    {
        $this->updateHiddenColumnsCount();
        $this->savePreferences();
    }

    public function showAllColumns(): void
    {
        foreach ($this->formDataColumns as $field => $_) {
            $this->formDataColumns[$field] = true;
        }
        $this->hiddenColumnsCount = 0;
        $this->savePreferences();
    }

    public function hideAllColumns(): void
    {
        foreach ($this->formDataColumns as $field => $_) {
            $this->formDataColumns[$field] = false;
        }
        $this->updateHiddenColumnsCount();
        $this->savePreferences();
    }

    public function resetColumnsToDefault(): void
    {
        foreach ($this->formDataColumns as $field => $_) {
            $this->formDataColumns[$field] = true;
        }
        $this->hiddenColumnsCount = 0;
        $this->savePreferences();
    }

    // ── Counts ────────────────────────────────────────────────────────────────

    public function updateHiddenColumnsCount(): void
    {
        $this->hiddenColumnsCount = (int) count(
            array_filter($this->formDataColumns, fn($v) => ! $v)
        );
    }

    // ── Visible column list ───────────────────────────────────────────────────

    /**
     * Returns the visible column definitions applying formDataColumns visibility
     * and columnOrder saved by the user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVisibleColumns(): array
    {
        $cols = $this->crudConfig['cols'] ?? [];

        // 1. Filter by visibility map
        if (! empty($this->formDataColumns)) {
            $cols = array_values(array_filter($cols, function ($col) {
                $field = $col['colsNomeFisico'] ?? '';
                return $this->formDataColumns[$field] ?? true;
            }));
        }

        // 2. Apply saved column order (action columns always go last)
        if (! empty($this->columnOrder)) {
            $actionCols = array_values(array_filter($cols, fn($c) => ($c['colsTipo'] ?? '') === 'action'));
            $dataCols   = array_values(array_filter($cols, fn($c) => ($c['colsTipo'] ?? '') !== 'action'));

            $colMap = [];
            foreach ($dataCols as $col) {
                $colMap[$col['colsNomeFisico'] ?? ''] = $col;
            }

            $ordered = [];
            foreach ($this->columnOrder as $field) {
                if (isset($colMap[$field])) {
                    $ordered[] = $colMap[$field];
                    unset($colMap[$field]);
                }
            }
            // Append any columns added after the saved order
            $ordered = array_merge($ordered, array_values($colMap));
            $cols    = array_merge($ordered, $actionCols);
        }

        return $cols;
    }
}

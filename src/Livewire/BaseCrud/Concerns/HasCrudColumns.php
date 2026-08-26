<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

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
                $defaults[$field] = $this->ptahBool($col['colsVisibleList'] ?? true);
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

    protected function updateHiddenColumnsCount(): void
    {
        // A denied column (see ColumnPermissionService) never ends up in
        // crudConfig['cols'], but a stale saved preference from before the
        // column was gated can still carry the key — strip it so it neither
        // inflates/deflates the count nor lingers in the column-visibility
        // dropdown. Purely cosmetic: getVisibleColumns() already filters by
        // crudConfig['cols'], so the column itself never renders regardless.
        foreach ($this->deniedColumns as $denied) {
            unset($this->formDataColumns[$denied]);
        }

        $this->hiddenColumnsCount = (int) count(
            array_filter($this->formDataColumns, fn ($v) => ! $v)
        );
    }

    // ── Visible column list ───────────────────────────────────────────────────

    /**
     * Returns the visible column definitions applying formDataColumns visibility
     * and columnOrder saved by the user.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * The visible columns a user can actually sort by, as
     * `[['field' => ..., 'sortBy' => ..., 'label' => ...], ...]`.
     *
     * Single source of truth for BOTH the clickable table headers and the card
     * view's sort select. They used to disagree by construction: the header
     * computed sortability inline in the Blade and the card view offered no
     * sorting at all, so switching to cards silently froze the listing on
     * whatever the table had last chosen — the exact complaint that produced
     * this method.
     *
     * The criteria mirror what the table header has always applied:
     *   - action columns are not data and cannot be ordered;
     *   - a dotted field ('customer.name') is a relation display column whose
     *     ORDER BY is resolved through a JOIN only when colsOrderBy names a
     *     real column, so it is not offered raw;
     *   - colsMetodoCustom is computed in PHP after the query, so the database
     *     has nothing to order by;
     *   - a column denied by column permissions must not even be namable, or
     *     the select becomes an oracle for data the user cannot read.
     *
     * @return array<int, array{field: string, sortBy: string, label: string}>
     */
    public function sortableColumns(): array
    {
        $out = [];

        foreach ($this->getVisibleColumns() as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');

            if ($field === '' || ($col['colsTipo'] ?? '') === 'action') {
                continue;
            }

            if (str_contains($field, '.') || ! empty($col['colsMetodoCustom'])) {
                continue;
            }

            if (in_array($field, $this->deniedColumns, true)) {
                continue;
            }

            $out[] = [
                'field' => $field,
                'sortBy' => (string) ($col['colsOrderBy'] ?? $field),
                'label' => (string) ($col['colsNomeLogico'] ?? $field),
            ];
        }

        return $out;
    }

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
            $actionCols = array_values(array_filter($cols, fn ($c) => ($c['colsTipo'] ?? '') === 'action'));
            $dataCols = array_values(array_filter($cols, fn ($c) => ($c['colsTipo'] ?? '') !== 'action'));

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
            $cols = array_merge($ordered, $actionCols);
        }

        return $cols;
    }
}

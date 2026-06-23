<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Ptah\Support\SqlIdentifier;

/**
 * Handles searchable-dropdown logic: inline modal search &
 * filter-panel dropdown searches.
 */
trait HasCrudSearchDropdown
{
    // ── Inline modal search dropdown ───────────────────────────────────────────

    /**
     * Called in real time as the user types in a searchdropdown field.
     * Populates $this->sdResults[fieldName].
     */
    public function searchDropdown(string $field, string $query): void
    {
        $this->sdSearches[$field] = $query;

        if (strlen($query) < 1) {
            $this->sdResults[$field] = [];

            return;
        }

        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo = $col['colsSDTipo'] ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // Cascading dropdown: blocked until the parent field has a value.
        [$gateClosed, $filterColumn, $filterValue] = $this->sdCascade($col, $this->formData);
        if ($gateClosed) {
            $this->sdResults[$field] = [];

            return;
        }

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $query, $sdLimit,
            false, $filterColumn, $filterValue
        );
    }

    /**
     * Called on focus/click of a field: loads the first items without a filter.
     */
    public function openDropdown(string $field): void
    {
        // Do not reload if results already exist
        if (! empty($this->sdResults[$field])) {
            return;
        }

        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo = $col['colsSDTipo'] ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // Cascading dropdown: blocked until the parent field has a value.
        [$gateClosed, $filterColumn, $filterValue] = $this->sdCascade($col, $this->formData);
        if ($gateClosed) {
            $this->sdResults[$field] = [];

            return;
        }

        // Use the term the user already typed (if any), or load all
        $currentQuery = $this->sdSearches[$field] ?? '';

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $currentQuery, $sdLimit,
            true, $filterColumn, $filterValue
        );
    }

    public function selectDropdownOption(string $field, mixed $value, string $label): void
    {
        $this->formData[$field] = $value;
        $this->sdLabels[$field] = $label;
        $this->sdResults[$field] = [];
        $this->sdSearches[$field] = '';

        // Cascading dropdowns: a new parent value invalidates every descendant.
        $this->resetSdDependents($field);
    }

    /**
     * Livewire hook: fires when any formData entry changes through wire:model
     * (e.g. the parent is a plain select instead of a searchdropdown).
     */
    public function updatedFormData(mixed $value, string $key): void
    {
        $this->resetSdDependents($key);

        // Calculated fields: run this column's onChange formula (HasCrudForm).
        $this->applyFieldOnChange($key);
    }

    // ── Cascading (dependent) dropdown helpers ─────────────────────────────────

    /**
     * Resolves the cascade state for a column with colsSDDependsOn.
     *
     * @param  array  $col  Column config
     * @param  array  $source  Current values: formData (modal) or filters (panel)
     * @return array{0: bool, 1: ?string, 2: mixed} [gateClosed, filterColumn, filterValue]
     */
    protected function sdCascade(array $col, array $source): array
    {
        $dependsOn = $col['colsSDDependsOn'] ?? null;

        if (! $dependsOn) {
            return [false, null, null];
        }

        $parentValue = $source[$dependsOn] ?? null;

        if ($parentValue === null || $parentValue === '') {
            return [true, null, null];
        }

        // Column on the child model used to filter by the parent value.
        // Defaults to the parent field name (city.state_id ← state_id).
        $filterColumn = $col['colsSDFilterColumn'] ?? $dependsOn;

        return [false, $filterColumn, $parentValue];
    }

    /**
     * Recursively clears every searchdropdown that depends on $parentField
     * (value, label, search term and cached results) — modal form scope.
     */
    protected function resetSdDependents(string $parentField): void
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsSDDependsOn'] ?? null) !== $parentField) {
                continue;
            }

            $child = $col['colsNomeFisico'] ?? null;

            if (! $child || ! array_key_exists($child, $this->formData)) {
                // Still clear stale UI state even when no value was set yet.
                if ($child) {
                    unset($this->sdLabels[$child], $this->sdSearches[$child]);
                    $this->sdResults[$child] = [];
                    $this->resetSdDependents($child);
                }

                continue;
            }

            unset($this->formData[$child], $this->sdLabels[$child], $this->sdSearches[$child]);
            $this->sdResults[$child] = [];

            // Grandchildren and deeper levels follow.
            $this->resetSdDependents($child);
        }
    }

    /**
     * Same cascade reset for the filter-panel scope ($filters).
     */
    protected function resetSdFilterDependents(string $parentField): void
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsSDDependsOn'] ?? null) !== $parentField) {
                continue;
            }

            $child = $col['colsNomeFisico'] ?? null;

            if (! $child) {
                continue;
            }

            unset($this->filters[$child], $this->filterOperators[$child], $this->sdFilterLabels[$child]);
            $this->sdResults['filter_'.$child] = [];

            $this->resetSdFilterDependents($child);
        }
    }

    // ── Filter-panel searchable dropdown ──────────────────────────────────────

    public function filterSearchDropdown(string $field, string $query): void
    {
        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo = $col['colsSDTipo'] ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // If the user cleared the text, reset the active filter
        if ($query === '') {
            unset($this->filters[$field], $this->sdFilterLabels[$field]);
            $this->sdResults['filter_'.$field] = [];
            $this->resetSdFilterDependents($field);

            return;
        }

        // Cascading dropdown: panel scope depends on the parent FILTER value.
        [$gateClosed, $filterColumn, $filterValue] = $this->sdCascade($col, $this->filters);
        if ($gateClosed) {
            $this->sdResults['filter_'.$field] = [];

            return;
        }

        $this->sdResults['filter_'.$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $query, $sdLimit,
            false, $filterColumn, $filterValue
        );
    }

    /**
     * Loads the first items for a filter-panel SD on focus.
     */
    public function openFilterDropdown(string $field): void
    {
        if (! empty($this->sdResults['filter_'.$field])) {
            return;
        }

        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo = $col['colsSDTipo'] ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // Cascading dropdown: panel scope depends on the parent FILTER value.
        [$gateClosed, $filterColumn, $filterValue] = $this->sdCascade($col, $this->filters);
        if ($gateClosed) {
            $this->sdResults['filter_'.$field] = [];

            return;
        }

        $this->sdResults['filter_'.$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, '', $sdLimit, true,
            $filterColumn, $filterValue
        );
    }

    /**
     * Confirms the selection of an item in the filter-panel SD.
     * Stores the ID in $filters[$field] and closes the dropdown.
     */
    public function selectFilterDropdownOption(string $field, mixed $value, string $label): void
    {
        $this->filters[$field] = $value;
        // Preserve a user-chosen operator (e.g. "!=" — different from); only
        // default to "=" when none was set. Lets searchdropdown filters do
        // "status different from finalised" via the FK-id != path.
        if (empty($this->filterOperators[$field])) {
            $this->filterOperators[$field] = '=';
        }
        $this->sdFilterLabels[$field] = $label;
        $this->sdResults['filter_'.$field] = [];
        $this->resetSdFilterDependents($field);
        $this->resetPage();
    }

    /**
     * Clears the active selection of a filter-panel SD.
     */
    public function clearFilterDropdownSelection(string $field): void
    {
        unset($this->filters[$field], $this->filterOperators[$field], $this->sdFilterLabels[$field]);
        $this->sdResults['filter_'.$field] = [];
        $this->resetSdFilterDependents($field);
        $this->resetPage();
    }

    // ── Result resolver ────────────────────────────────────────────────────────

    /**
     * Queries the model or service and returns [{value, label}] pairs.
     *
     * @param  string  $tipo  'model' | 'service'
     * @param  string  $sdModel  Model class or "Service\Class\methodName"
     * @param  string  $sdLabel  Display column name
     * @param  string  $sdValue  Value column name (usually 'id')
     * @param  string  $sdOrder  "column ASC|DESC"
     * @param  string  $query  Search term (empty = all when $allowEmpty is true)
     * @param  int  $limit  Max rows to return
     * @param  bool  $allowEmpty  When true, returns results even with an empty query
     * @return array<int, array{value: mixed, label: string}>
     */
    protected function resolveSearchDropdownResults(
        string $tipo,
        string $sdModel,
        string $sdLabel,
        string $sdValue,
        string $sdOrder,
        string $query,
        int $limit = 15,
        bool $allowEmpty = false,
        ?string $filterColumn = null,
        mixed $filterValue = null
    ): array {
        if (! $allowEmpty && strlen($query) < 1) {
            return [];
        }

        // Normalise model path (/ → \)
        $modelClass = str_replace('/', '\\', $sdModel);

        try {
            if ($tipo === 'model') {
                $fullClass = class_exists($modelClass)
                    ? $modelClass
                    : 'App\\Models\\'.$modelClass;

                if (! class_exists($fullClass)) {
                    return [];
                }

                [$orderCol, $orderDir] = array_pad(explode(' ', $sdOrder, 2), 2, 'ASC');

                $q = app($fullClass)
                    ->newQuery()
                    ->orderBy($orderCol, $orderDir)
                    ->limit($limit);

                // Cascading dropdown: restrict the child list to the parent value.
                // Column name is config-driven → guard it like every dynamic identifier.
                if ($filterColumn !== null && SqlIdentifier::isSafe($filterColumn)) {
                    $q->where($filterColumn, $filterValue);
                }

                // Case-insensitive filter via LOWER() for MySQL/SQLite compatibility.
                // Guard the column name against SQL injection before raw interpolation.
                if ($query !== '' && SqlIdentifier::isSafe($sdLabel)) {
                    $q->whereRaw('LOWER('.$sdLabel.') LIKE ?', ['%'.mb_strtolower($query).'%']);
                }

                return $q->get()
                    ->map(fn ($item) => [
                        'value' => $item->{$sdValue},
                        'label' => $item->{$sdLabel},
                    ])
                    ->toArray();
            }

            if ($tipo === 'service') {
                // Calls a static or instance method on a Service class
                if (str_contains($modelClass, '\\')) {
                    $parts = explode('\\', $modelClass);
                    $methodName = array_pop($parts);
                    $class = implode('\\', $parts);

                    $fullClass = class_exists($class)
                        ? $class
                        : 'App\\Services\\'.$class;

                    if (class_exists($fullClass) && method_exists($fullClass, $methodName)) {
                        $result = app($fullClass)->{$methodName}($query);

                        return is_array($result) ? $result : [];
                    }
                }
            }
        } catch (\Throwable) {
            // Fail silently
        }

        return [];
    }
}

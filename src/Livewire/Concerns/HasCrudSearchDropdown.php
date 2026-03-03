<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

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
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $query, $sdLimit
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
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // Use the term the user already typed (if any), or load all
        $currentQuery = $this->sdSearches[$field] ?? '';

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $currentQuery, $sdLimit, true
        );
    }

    public function selectDropdownOption(string $field, mixed $value, string $label): void
    {
        $this->formData[$field]   = $value;
        $this->sdLabels[$field]   = $label;
        $this->sdResults[$field]  = [];
        $this->sdSearches[$field] = '';
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
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // If the user cleared the text, reset the active filter
        if ($query === '') {
            unset($this->filters[$field], $this->sdFilterLabels[$field]);
            $this->sdResults['filter_' . $field] = [];
            return;
        }

        $this->sdResults['filter_' . $field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $query, $sdLimit
        );
    }

    /**
     * Loads the first items for a filter-panel SD on focus.
     */
    public function openFilterDropdown(string $field): void
    {
        if (! empty($this->sdResults['filter_' . $field])) {
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
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        $this->sdResults['filter_' . $field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, '', $sdLimit, true
        );
    }

    /**
     * Confirms the selection of an item in the filter-panel SD.
     * Stores the ID in $filters[$field] and closes the dropdown.
     */
    public function selectFilterDropdownOption(string $field, mixed $value, string $label): void
    {
        $this->filters[$field]              = $value;
        $this->filterOperators[$field]       = '=';
        $this->sdFilterLabels[$field]        = $label;
        $this->sdResults['filter_' . $field] = [];
        $this->resetPage();
    }

    /**
     * Clears the active selection of a filter-panel SD.
     */
    public function clearFilterDropdownSelection(string $field): void
    {
        unset($this->filters[$field], $this->filterOperators[$field], $this->sdFilterLabels[$field]);
        $this->sdResults['filter_' . $field] = [];
        $this->resetPage();
    }

    // ── Result resolver ────────────────────────────────────────────────────────

    /**
     * Queries the model or service and returns [{value, label}] pairs.
     *
     * @param  string $tipo       'model' | 'service'
     * @param  string $sdModel    Model class or "Service\Class\methodName"
     * @param  string $sdLabel    Display column name
     * @param  string $sdValue    Value column name (usually 'id')
     * @param  string $sdOrder    "column ASC|DESC"
     * @param  string $query      Search term (empty = all when $allowEmpty is true)
     * @param  int    $limit      Max rows to return
     * @param  bool   $allowEmpty When true, returns results even with an empty query
     *
     * @return array<int, array{value: mixed, label: string}>
     */
    protected function resolveSearchDropdownResults(
        string $tipo,
        string $sdModel,
        string $sdLabel,
        string $sdValue,
        string $sdOrder,
        string $query,
        int    $limit      = 15,
        bool   $allowEmpty = false
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
                    : 'App\\Models\\' . $modelClass;

                if (! class_exists($fullClass)) {
                    return [];
                }

                [$orderCol, $orderDir] = array_pad(explode(' ', $sdOrder, 2), 2, 'ASC');

                $q = app($fullClass)
                    ->newQuery()
                    ->orderBy($orderCol, $orderDir)
                    ->limit($limit);

                // Case-insensitive filter via LOWER() for MySQL/SQLite compatibility
                if ($query !== '') {
                    $q->whereRaw('LOWER(' . $sdLabel . ') LIKE ?', ['%' . mb_strtolower($query) . '%']);
                }

                return $q->get()
                    ->map(fn($item) => [
                        'value' => $item->{$sdValue},
                        'label' => $item->{$sdLabel},
                    ])
                    ->toArray();
            }

            if ($tipo === 'service') {
                // Calls a static or instance method on a Service class
                if (str_contains($modelClass, '\\')) {
                    $parts      = explode('\\', $modelClass);
                    $methodName = array_pop($parts);
                    $class      = implode('\\', $parts);

                    $fullClass = class_exists($class)
                        ? $class
                        : 'App\\Services\\' . $class;

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

<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Support\SearchDropdownMask;
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

        $settings = $this->sdSettings($col);

        if (! $settings['model']) {
            return;
        }

        // Cascading dropdown: blocked until the parent field has a value.
        [$gateClosed, $filterColumn, $filterValue] = $this->sdCascade($col, $this->formData);
        if ($gateClosed) {
            $this->sdResults[$field] = [];

            return;
        }

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $settings, $query, false, $filterColumn, $filterValue
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

        $settings = $this->sdSettings($col);

        if (! $settings['model']) {
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

        // colsSDInitWithData === false: don't preload the list on focus while
        // there is no search term yet (mirrors SearchDropdown.php's own
        // initWithData semantics). A term already typed still reactivates it.
        if ($settings['initWithData'] === false && $currentQuery === '') {
            $this->sdResults[$field] = [];

            return;
        }

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $settings, $currentQuery, true, $filterColumn, $filterValue
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

        $settings = $this->sdSettings($col);

        if (! $settings['model']) {
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
            $settings, $query, false, $filterColumn, $filterValue
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

        $settings = $this->sdSettings($col);

        if (! $settings['model']) {
            return;
        }

        // Cascading dropdown: panel scope depends on the parent FILTER value.
        [$gateClosed, $filterColumn, $filterValue] = $this->sdCascade($col, $this->filters);
        if ($gateClosed) {
            $this->sdResults['filter_'.$field] = [];

            return;
        }

        // colsSDInitWithData === false: the filter panel has no persisted
        // search term to reactivate on focus (unlike the modal), so this
        // always short-circuits to an empty list until the user types.
        if ($settings['initWithData'] === false) {
            $this->sdResults['filter_'.$field] = [];

            return;
        }

        $this->sdResults['filter_'.$field] = $this->resolveSearchDropdownResults(
            $settings, '', true, $filterColumn, $filterValue
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

    // ── Config resolver ──────────────────────────────────────────────────────

    /**
     * Resolves a normalised settings array for a searchdropdown column,
     * reading the canonical runtime keys first and falling back — in this
     * order — to the visual-editor dialect, then the CLI-wizard dialect, then
     * a hard default. This is the single place the three historical dialects
     * (see class docblock references in HasCrudSearchDropdown callers) are
     * reconciled, so a config authored by any of them behaves identically.
     *
     * colsSDMode is only treated as an alias for colsSDTipo when its value is
     * literally 'model' or 'service' — some configs following an earlier,
     * incorrect doc example set it to 'both', which must never hijack colsSDTipo.
     *
     * @param  array<string, mixed>  $col
     * @return array{
     *     model: ?string,
     *     tipo: string,
     *     value: string,
     *     label: string,
     *     labelTwo: ?string,
     *     labelThree: ?string,
     *     order: string,
     *     limit: int,
     *     initWithData: bool,
     *     placeholder: ?string,
     *     startList: string,
     *     filters: list<array{0: string, 1: string, 2: mixed}>,
     *     arraySearch: list<string>,
     *     masks: array{one: string, two: string, three: string}
     * }
     */
    /*
     * protected, NOT public: the only production callers are this component's
     * own Blade partials, and Livewire binds the compiled view closure to the
     * component instance (ExtendedCompilerEngine::bind), so protected resolves
     * there. Public would make it wire-callable — needless surface on a
     * component that manages real data (review finding).
     */
    protected function sdSettings(array $col): array
    {
        $mode = $col['colsSDMode'] ?? null;
        $tipo = $col['colsSDTipo'] ?? (in_array($mode, ['model', 'service'], true) ? $mode : 'model');

        $label = $col['colsSDLabel'] ?? $col['colsSDLabelField'] ?? $col['colsSdSelectColumn'] ?? 'name';
        $value = $col['colsSDValor'] ?? $col['colsSDValueField'] ?? $col['colsSdValueColumn'] ?? 'id';
        $order = $col['colsSDOrder'] ?? $col['colsSDOrderBy'] ?? $col['colsSdOrderBy'] ?? "{$label} ASC";
        $limit = (int) ($col['colsSDLimit'] ?? $col['colsSdLimit'] ?? 15);

        $model = $col['colsSDModel'] ?? $col['colsSdTable'] ?? null;

        // Service mode composes "Service\Class\methodName" from the split
        // editor fields when colsSDModel was never assembled (e.g. a config
        // authored by the CLI, which stores colsSDService/colsSDServiceMethod
        // separately and never runs the editor's save-time composer).
        if (
            $model === null
            && $tipo === 'service'
            && ! empty($col['colsSDService'])
            && ! empty($col['colsSDServiceMethod'])
        ) {
            $model = rtrim((string) $col['colsSDService'], '\\/').'\\'.$col['colsSDServiceMethod'];
        }

        return [
            'model' => $model,
            'tipo' => $tipo,
            'value' => $value,
            'label' => $label,
            'labelTwo' => $col['colsSDLabelTwo'] ?? null,
            'labelThree' => $col['colsSDLabelThree'] ?? null,
            'order' => $order,
            'limit' => $limit,
            'initWithData' => array_key_exists('colsSDInitWithData', $col)
                ? (bool) $col['colsSDInitWithData']
                : true,
            'placeholder' => $col['colsSDPlaceholder'] ?? null,
            'startList' => $col['colsSDStartList'] ?? 'bottom',
            'filters' => $this->sdNormalizeFilters($col['colsSDFilters'] ?? null),
            'arraySearch' => $this->sdNormalizeArraySearch($col['colsSDArraySearch'] ?? null),
            'masks' => [
                'one' => $col['colsSDMaskOne'] ?? 'defaultMask',
                'two' => $col['colsSDMaskTwo'] ?? 'defaultMask',
                'three' => $col['colsSDMaskThree'] ?? 'defaultMask',
            ],
        ];
    }

    /**
     * Normalises colsSDFilters into a flat list of [column, operator, value]
     * triples, accepting any of the shapes a config may store:
     *   - a JSON string of objects: '[{"field":"active","value":"S","op":"="}]'
     *   - a plain list of triples: [['active', '=', 'S'], ...]
     *   - an associative map: ['active' => 'S', 'status' => 'inactive']
     *
     * Every column is checked against SqlIdentifier::isSafe() and every
     * operator against a fixed allow-list — anything else is silently
     * dropped (config-driven WHERE fragments are a SQL-injection surface if
     * ever tampered with).
     *
     * @return list<array{0: string, 1: string, 2: mixed}>
     */
    protected function sdNormalizeFilters(mixed $raw): array
    {
        if (is_string($raw)) {
            if (trim($raw) === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $triples = [];

        if (! array_is_list($raw)) {
            // Associative map: ['col' => 'val', ...] → op defaults to '='.
            foreach ($raw as $col => $val) {
                $triples[] = [$col, '=', $val];
            }
        } else {
            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (array_key_exists('field', $item)) {
                    $triples[] = [$item['field'] ?? '', $item['op'] ?? '=', $item['value'] ?? null];
                } elseif (array_is_list($item) && count($item) === 3) {
                    $triples[] = [$item[0], $item[1], $item[2]];
                }
            }
        }

        $allowedOps = ['=', '!=', '>', '>=', '<', '<=', 'LIKE'];
        $safe = [];

        foreach ($triples as [$col, $op, $val]) {
            $col = (string) $col;
            $op = strtoupper((string) $op) === 'LIKE' ? 'LIKE' : (string) $op;

            if (! SqlIdentifier::isSafe($col) || ! in_array($op, $allowedOps, true)) {
                continue;
            }

            $safe[] = [$col, $op, $val];
        }

        return $safe;
    }

    /**
     * Normalises colsSDArraySearch (a CSV string or an array) into a flat
     * list of column names, discarding anything SqlIdentifier::isSafe()
     * rejects.
     *
     * @return list<string>
     */
    protected function sdNormalizeArraySearch(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $cols = is_array($raw) ? $raw : explode(',', (string) $raw);

        $safe = [];
        foreach ($cols as $col) {
            $col = trim((string) $col);
            if (SqlIdentifier::isSafe($col)) {
                $safe[] = $col;
            }
        }

        return $safe;
    }

    // ── Result resolver ────────────────────────────────────────────────────────

    /**
     * Queries the model or service and returns [{value, label}] pairs (plus
     * labelTwo/labelThree when configured).
     *
     * @param  array<string, mixed>  $settings  Resolved by sdSettings()
     * @param  string  $query  Search term (empty = all when $allowEmpty is true)
     * @param  bool  $allowEmpty  When true, returns results even with an empty query
     * @return array<int, array{value: mixed, label: string}>
     */
    protected function resolveSearchDropdownResults(
        array $settings,
        string $query,
        bool $allowEmpty = false,
        ?string $filterColumn = null,
        mixed $filterValue = null
    ): array {
        if (! $allowEmpty && strlen($query) < 1) {
            return [];
        }

        $sdModel = (string) $settings['model'];
        $sdLabel = $settings['label'];
        $sdValue = $settings['value'];
        $sdOrder = $settings['order'];
        $tipo = $settings['tipo'];
        $limit = $settings['limit'];
        $arraySearch = $settings['arraySearch'];

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

                // colsSDLabel supports dot-notation for a relation column
                // (e.g. "user.name"). The last segment is the column on the
                // related model; everything before it is the relation path
                // (nested relations such as "a.b.name" are supported too).
                $labelIsRelation = str_contains($sdLabel, '.');
                $relPath = null;
                $relColumn = $sdLabel;

                if ($labelIsRelation) {
                    $lastDot = strrpos($sdLabel, '.');
                    $relPath = substr($sdLabel, 0, $lastDot);
                    $relColumn = substr($sdLabel, $lastDot + 1);
                }

                [$orderCol, $orderDir] = array_pad(explode(' ', $sdOrder, 2), 2, 'ASC');

                // A relation column can't be ordered by directly without a JOIN.
                // Fall back to the value column (default "{label} ASC" would
                // otherwise become "user.name ASC" and break the query). Only
                // applies when the label itself is a relation — a table-qualified
                // column on the base model (e.g. "items.name") is valid and must
                // keep ordering exactly as before.
                if ($labelIsRelation && str_contains($orderCol, '.')) {
                    $orderCol = $sdValue;
                }

                $q = app($fullClass)
                    ->newQuery()
                    ->orderBy($orderCol, $orderDir)
                    ->limit($limit);

                if ($labelIsRelation) {
                    $q->with($relPath);
                }

                // Cascading dropdown: restrict the child list to the parent value.
                // Column name is config-driven → guard it like every dynamic identifier.
                if ($filterColumn !== null && SqlIdentifier::isSafe($filterColumn)) {
                    $q->where($filterColumn, $filterValue);
                }

                // Static filters (colsSDFilters) — already normalised (and
                // column/operator-guarded) by sdNormalizeFilters().
                foreach ($settings['filters'] as [$col, $op, $val]) {
                    $q->where($col, $op, $val);
                }

                // Case-insensitive filter via LOWER() for MySQL/SQLite compatibility.
                // arraySearch columns join the label in an OR'd group so any of
                // them matching the term is enough. Column names are guarded
                // against SQL injection before raw interpolation.
                if ($query !== '') {
                    $like = 'LOWER(%s) LIKE ?';
                    $bind = ['%'.mb_strtolower($query).'%'];

                    if ($labelIsRelation && SqlIdentifier::isSafe($relColumn)) {
                        $q->where(function (Builder $sub) use ($relPath, $relColumn, $like, $bind, $arraySearch) {
                            $sub->orWhereHas($relPath, function (Builder $r) use ($relColumn, $like, $bind) {
                                $r->whereRaw(sprintf($like, $relColumn), $bind);
                            });

                            foreach ($arraySearch as $col) {
                                if (SqlIdentifier::isSafe($col)) {
                                    $sub->orWhereRaw(sprintf($like, $col), $bind);
                                }
                            }
                        });
                    } elseif (! $labelIsRelation && SqlIdentifier::isSafe($sdLabel)) {
                        $q->where(function (Builder $sub) use ($sdLabel, $like, $bind, $arraySearch) {
                            $sub->orWhereRaw(sprintf($like, $sdLabel), $bind);

                            foreach ($arraySearch as $col) {
                                if (SqlIdentifier::isSafe($col)) {
                                    $sub->orWhereRaw(sprintf($like, $col), $bind);
                                }
                            }
                        });
                    }
                }

                return $q->get()
                    ->map(function ($item) use ($settings, $sdValue, $sdLabel) {
                        $row = [
                            'value' => $item->{$sdValue},
                            'label' => $this->sdApplyMask(data_get($item, $sdLabel), $settings['masks']['one']),
                        ];

                        if ($settings['labelTwo'] !== null) {
                            $row['labelTwo'] = $this->sdApplyMask(
                                data_get($item, $settings['labelTwo']),
                                $settings['masks']['two']
                            );
                        }

                        if ($settings['labelThree'] !== null) {
                            $row['labelThree'] = $this->sdApplyMask(
                                data_get($item, $settings['labelThree']),
                                $settings['masks']['three']
                            );
                        }

                        return $row;
                    })
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

    /**
     * Applies a colsSDMask* value to a label, but ONLY when it is a real
     * (non-default) mask — otherwise returns $value untouched, including its
     * original type/null-ness. This keeps a pre-existing config (no
     * colsSDMask* set → 'defaultMask') byte-identical to before masks
     * existed: raw data_get() output, never coerced to a string.
     */
    protected function sdApplyMask(mixed $value, string $mask): mixed
    {
        if ($mask === 'defaultMask') {
            return $value;
        }

        return SearchDropdownMask::format($value, $mask);
    }
}

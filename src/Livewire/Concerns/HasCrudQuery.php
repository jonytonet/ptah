<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ptah\DTO\FilterDTO;
use Ptah\Models\UserPreference;

/**
 * Handles data querying: paginated rows, aggregates (totals), joins,
 * sort-by-relation, model resolution and filter building.
 */
trait HasCrudQuery
{
    // ── Computed properties ────────────────────────────────────────────────────

    /**
     * Returns paginated data applying all active filters.
     * Includes error recovery: clears corrupted preferences and returns an empty list.
     */
    public function getRowsProperty(): LengthAwarePaginator
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return new LengthAwarePaginator([], 0, $this->perPage);
        }

        try {
            /** @var Builder $query */
            $query = $modelInstance->newQuery();

            // JOINs configured via crudConfig['joins']
            $joinedTables = $this->applyJoins($query, $modelInstance);

            // Soft delete
            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelInstance));
            if ($usesSoftDeletes && $this->showTrashed) {
                $query->onlyTrashed();
            }

            // Eager-load relationships visible in the table
            $relations = $this->getVisibleRelations();
            if (! empty($relations)) {
                $query->with($relations);
            }

            // Company filter (multi-tenant) — only applied when the column actually exists
            if ($this->companyFilter > 0) {
                $table        = $modelInstance->getTable();
                $companyField = $this->crudConfig['companyField'] ?? 'company_id';
                if (Schema::hasColumn($table, $companyField)) {
                    $query->where("{$table}.{$companyField}", $this->companyFilter);
                }
            }

            // External whereHas filter (pre-filtered by parent entity)
            if ($this->whereHasFilter !== '') {
                [$col, $op, $val] = array_pad($this->whereHasCondition, 3, null);
                if ($col && $val !== null) {
                    $query->whereHas($this->whereHasFilter, function (Builder $q) use ($col, $op, $val) {
                        $q->where($col, $op ?? '=', $val);
                    });
                }
            }

            // Global search with OR across text fields and relations
            if ($this->search !== '') {
                $searchFilters = $this->filterService->buildGlobalSearchFilters(
                    $this->crudConfig['cols'] ?? [],
                    $this->search
                );

                if (! empty($searchFilters)) {
                    $this->filterService->applyFilters($query, $searchFilters);
                }

                // Save search history
                $this->addToSearchHistory($this->search);
            }

            // Form filters
            $activeFilters = $this->buildActiveFilters();
            if (! empty($activeFilters)) {
                $this->filterService->applyFilters($query, $activeFilters);
            }

            // Date ranges (standard ERP: _start/_end, legacy: _from/_to)
            $drFilters = $this->filterService->processDateRangeFilters($this->dateRanges, $this->dateRangeOperators);
            if (! empty($drFilters)) {
                $this->filterService->applyFilters($query, $drFilters);
            }

            // Quick date filter
            if ($this->quickDateFilter !== '' && $this->quickDateColumn !== '') {
                [$from, $to] = $this->getQuickDateRange($this->quickDateFilter);
                if ($from && $to) {
                    $query->whereBetween($this->quickDateColumn, [$from, $to]);
                }
            }

            // Custom filters
            $customFilterConfig = $this->crudConfig['customFilters'] ?? [];
            $cfFilters          = $this->filterService->processCustomFilters($customFilterConfig, $this->filters);
            if (! empty($cfFilters)) {
                $this->filterService->applyFilters($query, $cfFilters);
            }

            // GROUP BY support (configGroupBy)
            if ($groupBy = $this->crudConfig['groupBy'] ?? null) {
                $mainTable = $modelInstance->getTable();
                $query
                    ->select([
                        \Illuminate\Support\Facades\DB::raw("MIN({$mainTable}.id) as id"),
                        "{$mainTable}.{$groupBy}",
                    ])
                    ->groupBy("{$mainTable}.{$groupBy}")
                    ->orderBy("{$mainTable}.{$groupBy}", $this->direction);

                return $query->paginate($this->perPage);
            }

            // Sorting (supports relation via JOIN)
            $relationInfo = $this->getOrderByRelationInfo($this->sort);

            if ($relationInfo) {
                [$rel, $displayCol, $fk, $relTable] = $relationInfo;
                $mainTable = $modelInstance->getTable();

                // Only add leftJoin if the table was not already joined via applyJoins()
                if (! in_array($relTable, $joinedTables)) {
                    $query->leftJoin(
                        $relTable,
                        "{$mainTable}.{$fk}",
                        '=',
                        "{$relTable}.id"
                    );

                    // If applyJoins did not set a select, define one now to avoid ambiguous columns
                    if (empty($joinedTables)) {
                        $query->select("{$mainTable}.*");
                    }
                }

                $query->orderBy("{$relTable}.{$displayCol}", $this->direction);
            } else {
                $sortCol = $this->resolveSortColumn();
                $query->orderBy($sortCol, $this->direction);
            }

            return $query->paginate($this->perPage);

        } catch (\Exception $e) {
            // Clear potentially corrupted preferences
            $userId = Auth::id();
            if ($userId) {
                UserPreference::remove($userId, 'crud.' . $this->model);
                $this->cacheService->forgetPreferences($userId, $this->model);
            }

            Log::error('Ptah BaseCrud: error loading data', [
                'model' => $this->model,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', trans('ptah::ui.crud_load_error'));

            return new LengthAwarePaginator([], 0, $this->perPage, 1, [
                'path' => request()->url(),
            ]);
        }
    }

    /**
     * Column totals (sums / counts / averages).
     * Each aggregate clones the query to avoid mutual interference.
     */
    public function getTotalizadoresDataProperty(): array
    {
        $totConfig = $this->crudConfig['totalizadores'] ?? [];

        if (empty($totConfig['enabled']) || empty($totConfig['columns'])) {
            return [];
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return [];
        }

        // Build base query with active filters
        $baseQuery     = $modelInstance->newQuery();
        $this->applyJoins($baseQuery, $modelInstance);
        $activeFilters = $this->buildActiveFilters();

        if (! empty($activeFilters)) {
            $this->filterService->applyFilters($baseQuery, $activeFilters);
        }

        $drFilters = $this->filterService->processDateRangeFilters($this->dateRanges, $this->dateRangeOperators);
        if (! empty($drFilters)) {
            $this->filterService->applyFilters($baseQuery, $drFilters);
        }

        $result = [];

        foreach ($totConfig['columns'] as $totCol) {
            $field     = $totCol['field']     ?? null;
            $aggregate = $totCol['aggregate'] ?? 'sum';

            if (! $field) {
                continue;
            }

            // Clone the query for each aggregate (avoids accumulated SELECTs)
            $cloned = clone $baseQuery;

            $result[$field] = match ($aggregate) {
                'sum'   => $cloned->sum($field),
                'count' => $cloned->count($field),
                'avg'   => round((float) $cloned->avg($field), 2),
                'max'   => $cloned->max($field),
                'min'   => $cloned->min($field),
                default => null,
            };
        }

        return $result;
    }

    // ── JOINs ─────────────────────────────────────────────────────────────────

    /**
     * Applies JOINs declared in crudConfig['joins'] to the Query Builder.
     *
     * Each entry supports:
     *   type     — 'left' (default) | 'inner'
     *   table    — table to join
     *   first    — left column  (e.g. "products.supplier_id")
     *   second   — right column (e.g. "suppliers.id")
     *   distinct — bool, applies SELECT DISTINCT
     *   select   — array of { column, alias } for additional SELECT columns
     *
     * Returns the list of tables that were effectively joined, so the
     * sort-by-relation block does not duplicate the same JOIN.
     *
     * @return string[] Names of joined tables
     */
    protected function applyJoins(Builder $query, Model $modelInstance): array
    {
        $joins = $this->crudConfig['joins'] ?? [];

        if (empty($joins)) {
            return [];
        }

        $mainTable    = $modelInstance->getTable();
        $joinedTables = [];
        $selectCols   = ["{$mainTable}.*"];
        $useDistinct  = false;

        foreach ($joins as $join) {
            $table  = trim($join['table']  ?? '');
            $first  = trim($join['first']  ?? '');
            $second = trim($join['second'] ?? '');
            $type   = strtolower($join['type'] ?? 'left');

            if (! $table || ! $first || ! $second) {
                continue;
            }

            // Avoid duplicate JOIN on the same query
            if (in_array($table, $joinedTables)) {
                continue;
            }

            if ($type === 'inner') {
                $query->join($table, $first, '=', $second);
            } else {
                $query->leftJoin($table, $first, '=', $second);
            }

            $joinedTables[] = $table;

            foreach ($join['select'] ?? [] as $sel) {
                $col   = trim($sel['column'] ?? '');
                $alias = trim($sel['alias']  ?? '');
                if ($col && $alias) {
                    $selectCols[] = \Illuminate\Support\Facades\DB::raw("{$col} AS {$alias}");
                }
            }

            if (! empty($join['distinct'])) {
                $useDistinct = true;
            }
        }

        if (! empty($joinedTables)) {
            $query->select($selectCols);

            if ($useDistinct) {
                $query->distinct();
            }
        }

        return $joinedTables;
    }

    // ── Sort by relation ───────────────────────────────────────────────────────

    /**
     * Detects whether the sort column is an FK that can be resolved via JOIN.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     *         [relationName, displayColumn, foreignKey, tableName]
     */
    protected function getOrderByRelationInfo(string $column): ?array
    {
        $col = $this->findColByField($column);

        if (! $col) {
            return null;
        }

        $rel   = $col['colsRelacao']      ?? null;
        $exibe = $col['colsRelacaoExibe'] ?? null;
        $fk    = $col['colsNomeFisico']   ?? null; // e.g. category_id

        if (! $rel || ! $exibe || ! $fk) {
            return null;
        }

        // FK must match the column being sorted
        if ($fk !== $column) {
            return null;
        }

        $tableName = $this->relationToTableName($rel);

        return [$rel, $exibe, $fk, $tableName];
    }

    /**
     * Converts a camelCase relation name to a snake_plural table name.
     * e.g. "businessPartner" → "business_partners"
     */
    protected function relationToTableName(string $relation): string
    {
        // Resolve the relation on the model to get the actual table
        try {
            $modelInstance = $this->resolveEloquentModel();

            if ($modelInstance && method_exists($modelInstance, $relation)) {
                $relInstance = $modelInstance->{$relation}();
                return $relInstance->getRelated()->getTable();
            }
        } catch (\Throwable) {
            // Fallback to automatic conversion
        }

        return \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($relation));
    }

    // ── Active filters ────────────────────────────────────────────────────────

    protected function buildActiveFilters(): array
    {
        $domainFilters = [];

        foreach ($this->filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Skip if it is a custom filter (handled separately)
            $isCustom = false;

            foreach ($this->crudConfig['customFilters'] ?? [] as $cf) {
                if (($cf['field'] ?? null) === $field) {
                    $isCustom = true;
                    break;
                }
            }

            if ($isCustom) {
                continue;
            }

            // Operator defined explicitly, or auto-detected
            $explicitOp = $this->filterOperators[$field] ?? null;

            // Check whether to filter via relation
            $col = $this->findColByField($field);

            if ($col && ! empty($col['colsRelacao']) && ! empty($col['colsRelacaoExibe'])) {
                $domainFilters[] = new FilterDTO(
                    field:    $col['colsNomeFisico'],
                    value:    $value,
                    operator: $explicitOp ?? '=',
                    type:     'text',
                );
            } else {
                // If column has colsSource (configured JOIN), use qualified name for WHERE
                $filterField = ($col && ! empty($col['colsSource'])) ? $col['colsSource'] : $field;
                $autoOp = (is_string($value) && strlen($value) > 1 && FilterDTO::inferType($field, $value) === 'text')
                    ? 'LIKE'
                    : '=';
                $domainFilters[] = new FilterDTO(
                    field:    $filterField,
                    value:    $value,
                    operator: $explicitOp ?? $autoOp,
                    type:     FilterDTO::inferType($field, $value),
                );
            }
        }

        // Advanced search (fields added by user)
        if ($this->advancedSearchActive && ! empty($this->advancedSearchFields)) {
            foreach ($this->advancedSearchFields as $asf) {
                $field    = $asf['field']    ?? null;
                $operator = $asf['operator'] ?? '=';
                $value    = $asf['value']    ?? null;
                $logic    = $asf['logic']    ?? 'AND';

                if (! $field || $value === null || $value === '') {
                    continue;
                }

                $domainFilters[] = new FilterDTO(
                    field:    $field,
                    value:    $value,
                    operator: $operator,
                    type:     FilterDTO::inferType($field, $value),
                    options:  ['logic' => $logic],
                );
            }
        }

        return $domainFilters;
    }

    protected function resolveSortColumn(): string
    {
        $col = $this->findColByField($this->sort);

        // colsSource has priority (JOIN column — use qualified name for ORDER BY)
        if ($col && ! empty($col['colsSource'])) {
            return $col['colsSource'];
        }

        if ($col && ! empty($col['colsOrderBy'])) {
            return $col['colsOrderBy'];
        }

        return $this->sort;
    }

    // ── Model resolution ──────────────────────────────────────────────────────

    protected function resolveEloquentModel(): ?Model
    {
        if ($this->eloquentModel) {
            return $this->eloquentModel;
        }

        $modelName = $this->crudConfig['crud'] ?? $this->model;

        // Convert "Purchase/Order/PurchaseOrders" to namespace
        $class = str_replace('/', '\\', $modelName);

        // Try known prefixes
        $candidates = [
            $class,
            'App\\Models\\' . $class,
            app()->getNamespace() . 'Models\\' . $class,
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                $this->eloquentModel = app($candidate);
                return $this->eloquentModel;
            }
        }

        return null;
    }

    protected function getVisibleRelations(): array
    {
        $relations = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $rel = $col['colsRelacao'] ?? null;
            if ($rel && $rel !== '') {
                $relations[] = $rel;
            }

            // Nested dot notation — eager-load all segments except the last (which is the field)
            // e.g. "address.city.name" → eager-load "address.city"
            // e.g. "supplier.name"     → eager-load "supplier"
            $nested = $col['colsRelacaoNested'] ?? null;
            if ($nested && $nested !== '') {
                $parts = explode('.', $nested);
                if (count($parts) > 1) {
                    $relations[] = implode('.', array_slice($parts, 0, count($parts) - 1));
                }
            }
        }

        return array_unique($relations);
    }

    protected function getSearchableFields(): array
    {
        $fields = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $tipo = $col['colsTipo'] ?? 'text';
            if ($tipo === 'text' && empty($col['colsRelacao'])) {
                $fields[] = $col['colsNomeFisico'];
            }
        }

        return array_unique($fields);
    }
}

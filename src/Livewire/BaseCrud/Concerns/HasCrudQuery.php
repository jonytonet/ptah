<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Ptah\DTO\FilterDTO;
use Ptah\Models\UserPreference;
use Ptah\Services\Crud\FilterService;
use Ptah\Support\SqlIdentifier;

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
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return new LengthAwarePaginator([], 0, $this->perPage);
        }

        try {
            [$query, $joinedTables] = $this->buildBaseQuery($modelInstance);

            // Record the search term in history (side effect kept out of
            // buildBaseQuery so it is not duplicated by totals/export/print).
            if ($this->search !== '') {
                $this->addToSearchHistory($this->search);
            }

            $this->applyGroupingAndSort($query, $modelInstance, $joinedTables);

            return $query->paginate($this->perPage);

        } catch (QueryException $e) {
            // Clear potentially corrupted preferences
            $userId = Auth::id();
            if ($userId) {
                UserPreference::remove($userId, 'crud.'.$this->model);
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
    #[Computed]
    public function totalizadoresData(): array
    {
        $totConfig = $this->crudConfig['totalizadores'] ?? [];

        if (empty($totConfig['enabled']) || empty($totConfig['columns'])) {
            return [];
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return [];
        }

        // Same filtered query as the listing (search, company, locked, whereHas,
        // date ranges, custom filters all included) so the totals always match
        // the rows the user is actually seeing. No grouping/sort needed here.
        [$baseQuery] = $this->buildBaseQuery($modelInstance);

        $result = [];

        foreach ($totConfig['columns'] as $totCol) {
            $field = $totCol['field'] ?? null;
            $aggregate = $totCol['aggregate'] ?? 'sum';

            if (! $field) {
                continue;
            }

            // Clone the query for each aggregate (avoids accumulated SELECTs)
            $cloned = clone $baseQuery;

            $result[$field] = match ($aggregate) {
                'sum' => $cloned->sum($field),
                'count' => $cloned->count($field),
                'avg' => round((float) $cloned->avg($field), 2),
                'max' => $cloned->max($field),
                'min' => $cloned->min($field),
                default => null,
            };
        }

        return $result;
    }

    // ── Shared query building ───────────────────────────────────────────────────

    /**
     * Builds the fully-filtered query (WHERE / JOIN / eager-load / soft-delete /
     * search / form filters / date ranges / quick date / custom filters), WITHOUT
     * grouping, sorting or pagination.
     *
     * This is the single source of truth shared by the listing (rows), totals,
     * export and the print screen — so any filter fix applies everywhere and the
     * numbers never diverge.
     *
     * @return array{0: Builder, 1: string[]} [query, joinedTables]
     */
    protected function buildBaseQuery(Model $modelInstance): array
    {
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
            $table = $modelInstance->getTable();
            $companyField = $this->crudConfig['companyField'] ?? 'company_id';
            if (Schema::hasColumn($table, $companyField)) {
                $query->where("{$table}.{$companyField}", $this->companyFilter);
            }
        }

        // Locked filters (master/detail): enforced on every query, immune to
        // clearFilters. Column names are config-driven → guarded.
        foreach ($this->lockedFilters as $lockedCol => $lockedVal) {
            if (SqlIdentifier::isSafe((string) $lockedCol)) {
                $query->where($lockedCol, $lockedVal);
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
        $cfFilters = $this->filterService->processCustomFilters($customFilterConfig, $this->filters);
        if (! empty($cfFilters)) {
            $this->filterService->applyFilters($query, $cfFilters);
        }

        return [$query, $joinedTables];
    }

    /**
     * Applies grouping (configGroupBy), group-break ordering and the user sort
     * (including sort-by-relation via JOIN) to an already-filtered query.
     * configGroupBy short-circuits the normal sort.
     *
     * @param  string[]  $joinedTables  Tables already joined by buildBaseQuery()
     */
    protected function applyGroupingAndSort(Builder $query, Model $modelInstance, array $joinedTables): void
    {
        // GROUP BY support (configGroupBy) — replaces the normal sort
        if ($groupBy = $this->crudConfig['groupBy'] ?? null) {
            $mainTable = $modelInstance->getTable();
            $query
                ->select([
                    DB::raw("MIN({$mainTable}.id) as id"),
                    "{$mainTable}.{$groupBy}",
                ])
                ->groupBy("{$mainTable}.{$groupBy}")
                ->orderBy("{$mainTable}.{$groupBy}", $this->direction);

            return;
        }

        // Group break (ScriptCase-style "quebra"): rows are kept individual,
        // but the break field becomes the primary sort so the view can emit
        // group headers and per-group subtotals. User sort stays secondary.
        if ($breakField = $this->crudConfig['groupBreak'] ?? null) {
            if (SqlIdentifier::isSafe($breakField)) {
                $query->orderBy($breakField, 'ASC');
            }
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

        $mainTable = $modelInstance->getTable();
        $joinedTables = [];
        $selectCols = ["{$mainTable}.*"];
        $useDistinct = false;

        foreach ($joins as $join) {
            $table = trim($join['table'] ?? '');
            $first = trim($join['first'] ?? '');
            $second = trim($join['second'] ?? '');
            $type = strtolower($join['type'] ?? 'left');

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
                $col = trim($sel['column'] ?? '');
                $alias = trim($sel['alias'] ?? '');
                if ($col && $alias) {
                    $selectCols[] = DB::raw("{$col} AS {$alias}");
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
     *                                                                [relationName, displayColumn, foreignKey, tableName]
     */
    protected function getOrderByRelationInfo(string $column): ?array
    {
        $col = $this->findColByField($column);

        if (! $col) {
            return null;
        }

        $rel = $col['colsRelacao'] ?? null;
        $exibe = $col['colsRelacaoExibe'] ?? null;
        $fk = $col['colsNomeFisico'] ?? null; // e.g. category_id

        if (! $rel || ! $exibe || ! $fk) {
            return null;
        }

        // Nested paths ("a.b") can't be sorted via a single leftJoin — bail out
        // (the column is simply not sortable via relation JOIN; avoids a broken
        // table name / invalid SQL).
        if (str_contains((string) $rel, '.')) {
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

        return Str::snake(Str::plural($relation));
    }

    // ── Active filters ────────────────────────────────────────────────────────

    protected function buildActiveFilters(): array
    {
        $domainFilters = [];

        // Iterate the union of valued filters AND fields that only carry a NULL
        // operator (IS NULL / IS NOT NULL) — those have no value but must apply.
        $fields = array_values(array_unique(array_merge(
            array_keys($this->filters),
            array_keys($this->filterOperators),
        )));

        foreach ($fields as $field) {
            $value = $this->filters[$field] ?? null;

            // Normalise the operator: empty / non-string "select…" becomes null.
            $explicitOp = $this->filterOperators[$field] ?? null;
            if (! is_string($explicitOp) || trim($explicitOp) === '') {
                $explicitOp = null;
            }

            $isNull = $explicitOp !== null && FilterService::isNullOperator($explicitOp);

            // Empty values are skipped UNLESS the operator is IS NULL / IS NOT NULL,
            // which filter by the column itself and need no value.
            if (! $isNull && ($value === null || $value === '')) {
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

            $col = $this->findColByField($field);

            // ── NULL operators: filter by the column, no value, any type ──────
            if ($isNull) {
                $filterField = ($col && ! empty($col['colsSource'])) ? $col['colsSource'] : $field;
                $domainFilters[] = new FilterDTO(
                    field: $filterField,
                    value: null,
                    operator: $explicitOp,
                    type: 'text',
                );

                continue;
            }

            // ── Relationship column (colsRelacao + colsRelacaoExibe) ──────────
            // Numeric value = the FK id → filter the FK directly.
            // Text value     = search the related display column via whereHas.
            if ($col && ! empty($col['colsRelacao']) && ! empty($col['colsRelacaoExibe'])) {
                $relation = (string) $col['colsRelacao'];
                $isNested = str_contains($relation, '.');

                if (is_numeric($value)) {
                    $idOp = in_array($explicitOp, ['=', '!=', '<>'], true) ? $explicitOp : '=';

                    if ($isNested) {
                        // Nested path (a.b): the root FK does NOT point at the final
                        // related model, so match its primary key through whereHas
                        // (Eloquent resolves the dotted path). The related key is the
                        // searchdropdown value field (colsSDValor, default "id").
                        $relatedKey = $col['colsSDValor'] ?? 'id';
                        $domainFilters[] = new FilterDTO(
                            field: $relation,
                            value: $value,
                            operator: $idOp,
                            type: 'relation',
                            options: ['whereHas' => $relation, 'column' => $relatedKey],
                        );
                    } else {
                        // CAVEAT: `fk != id` also excludes rows with a NULL fk
                        // (NULL != x is UNKNOWN in SQL). To include unlinked rows,
                        // the strategy would need ->where(fn => ...->orWhereNull(fk)).
                        $domainFilters[] = new FilterDTO(
                            field: $col['colsNomeFisico'],
                            value: $value,
                            operator: $idOp,
                            type: 'number',
                        );
                    }
                } else {
                    $relOp = in_array(strtoupper((string) $explicitOp), ['=', '!=', 'LIKE', 'NOT LIKE'], true)
                        ? $explicitOp
                        : 'LIKE';

                    // Both single-level and nested ("a.b") paths go through whereHas;
                    // Eloquent's whereHas resolves dotted relation paths natively.
                    $domainFilters[] = new FilterDTO(
                        field: $relation,
                        value: $value,
                        operator: $relOp,
                        type: 'relation',
                        options: [
                            'whereHas' => $relation,
                            'column' => $col['colsRelacaoExibe'],
                        ],
                    );
                }

                continue;
            }

            // ── Plain column ──────────────────────────────────────────────────
            // If column has colsSource (configured JOIN), use qualified name for WHERE
            $filterField = ($col && ! empty($col['colsSource'])) ? $col['colsSource'] : $field;
            $autoOp = (is_string($value) && strlen($value) > 1 && FilterDTO::inferType($field, $value) === 'text')
                ? 'LIKE'
                : '=';
            $domainFilters[] = new FilterDTO(
                field: $filterField,
                value: $value,
                operator: $explicitOp ?? $autoOp,
                type: FilterDTO::inferType($field, $value),
            );
        }

        // Advanced search (fields added by user)
        if ($this->advancedSearchActive && ! empty($this->advancedSearchFields)) {
            foreach ($this->advancedSearchFields as $asf) {
                $field = $asf['field'] ?? null;
                $operator = $asf['operator'] ?? '=';
                $value = $asf['value'] ?? null;
                $logic = $asf['logic'] ?? 'AND';

                if (! $field || $value === null || $value === '') {
                    continue;
                }

                $domainFilters[] = new FilterDTO(
                    field: $field,
                    value: $value,
                    operator: $operator,
                    type: FilterDTO::inferType($field, $value),
                    options: ['logic' => $logic],
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
            'App\\Models\\'.$class,
            app()->getNamespace().'Models\\'.$class,
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

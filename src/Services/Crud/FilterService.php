<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Contracts\FilterStrategyInterface;
use Ptah\DTO\FilterDTO;
use Ptah\Services\Crud\Filters\ArrayFilterStrategy;
use Ptah\Services\Crud\Filters\DateFilterStrategy;
use Ptah\Services\Crud\Filters\NumericFilterStrategy;
use Ptah\Services\Crud\Filters\RelationFilterStrategy;
use Ptah\Services\Crud\Filters\TextFilterStrategy;

/**
 * Filter service for BaseCrud.
 *
 * Uses the Strategy pattern to apply filters to Eloquent's Query Builder
 * in a type-safe manner, without eval(). Supports AND/OR logic per filter.
 *
 * Each FilterDTO may have `options['logic'] = 'OR'` to be grouped
 * in a separate OR block from the normal AND filters.
 */
class FilterService
{
    /** @var array<string, FilterStrategyInterface> */
    protected array $strategies = [];

    public function __construct()
    {
        $this->strategies = [
            'text'      => new TextFilterStrategy(),
            'number'    => new NumericFilterStrategy(),
            'numeric'   => new NumericFilterStrategy(),
            'date'      => new DateFilterStrategy('date'),
            'datetime'  => new DateFilterStrategy('datetime'),
            'timestamp' => new DateFilterStrategy('datetime'),
            'relation'  => new RelationFilterStrategy(),
            'array'     => new ArrayFilterStrategy(),
            'boolean'   => new TextFilterStrategy(),
        ];
    }

    /**
     * Registers a custom strategy (extension via ServiceProvider).
     */
    public function registerStrategy(string $type, FilterStrategyInterface $strategy): void
    {
        $this->strategies[$type] = $strategy;
    }


    /**
     * Applies a collection of FilterDTOs to the Builder.
     *
     * Filters with `options['logic'] = 'OR'` are grouped in an OR block.
     * Others are applied with AND (default).
     *
     * @param Builder     $query
     * @param FilterDTO[] $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $andFilters = [];
        $orFilters  = [];

        foreach ($filters as $filter) {
            if (! ($filter instanceof FilterDTO)) {
                $filter = FilterDTO::fromArray((array) $filter);
            }

            if (! $filter->isValid()) {
                continue;
            }

            $logic = strtoupper($filter->options['logic'] ?? 'AND');

            if ($logic === 'OR') {
                $orFilters[] = $filter;
            } else {
                $andFilters[] = $filter;
            }
        }

        // Apply AND filters normally
        foreach ($andFilters as $filter) {
            $query = $this->applyFilter($query, $filter);
        }

        // Group OR filters into a single WHERE block (... OR ... OR ...)
        if (! empty($orFilters)) {
            $query->where(function (Builder $subQuery) use ($orFilters) {
                foreach ($orFilters as $filter) {
                    $subQuery->orWhere(function (Builder $q) use ($filter) {
                        $this->applyFilter($q, $filter);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Applies a single filter to the Builder using the correct strategy.
     */
    protected function applyFilter(Builder $query, FilterDTO $filter): Builder
    {
        // Explicit relation filter via whereHas
        if (! empty($filter->options['whereHas'])) {
            return $this->strategies['relation']->apply($query, $filter);
        }

        $type     = $filter->type ?? 'text';
        $strategy = $this->strategies[$type] ?? $this->strategies['text'];

        return $strategy->apply($query, $filter);
    }

    // ── Utilities ─────────────────────────────────────────────────────────────────

    /**
     * Processes date range filters from the BaseCrud filter form.
     *
     * Accepts ERP pattern: key `{field}_start` / `{field}_end` in formData.
     * Also accepts the legacy pattern: `{field}_from` / `{field}_to`.
     *
     * @param array $formData  Filter form data (dateRanges)
     * @return FilterDTO[]
     */
    public function processDateRangeFilters(array $formData, array $operators = []): array
    {
        $filters   = [];
        $processed = [];

        foreach ($formData as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // ERP pattern: {field}_start / {field}_end
            if (str_ends_with($key, '_start')) {
                $field = substr($key, 0, -6);
                if (in_array($field, $processed, true)) continue;
                $to          = $formData[$field . '_end'] ?? null;
                $processed[] = $field;

                $opFrom = $operators[$field . '_start'] ?? null;
                $opTo   = $operators[$field . '_end']   ?? null;

                if ($opFrom || $opTo) {
                    // Explicit operators: apply individually
                    if ($value !== null && $value !== '') {
                        $filters[] = new FilterDTO(field: $field, value: $value, operator: $opFrom ?? '>=', type: 'date');
                    }
                    if ($to !== null && $to !== '') {
                        $filters[] = new FilterDTO(field: $field, value: $to, operator: $opTo ?? '<=', type: 'date');
                    }
                } else {
                    $dto = $this->buildDateRangeFilter($field, $value, $to ?: null);
                    if ($dto) $filters[] = $dto;
                }
                continue;
            }

            if (str_ends_with($key, '_end')) {
                $field = substr($key, 0, -4);
                if (in_array($field, $processed, true)) continue;
                $from        = $formData[$field . '_start'] ?? null;
                $processed[] = $field;

                $opFrom = $operators[$field . '_start'] ?? null;
                $opTo   = $operators[$field . '_end']   ?? null;

                if ($opFrom || $opTo) {
                    if ($from !== null && $from !== '') {
                        $filters[] = new FilterDTO(field: $field, value: $from, operator: $opFrom ?? '>=', type: 'date');
                    }
                    if ($value !== null && $value !== '') {
                        $filters[] = new FilterDTO(field: $field, value: $value, operator: $opTo ?? '<=', type: 'date');
                    }
                } else {
                    $dto = $this->buildDateRangeFilter($field, $from ?: null, $value);
                    if ($dto) $filters[] = $dto;
                }
                continue;
            }

            // Legacy pattern: {field}_from / {field}_to
            if (str_ends_with($key, '_from')) {
                $field = substr($key, 0, -5);
                if (in_array($field, $processed, true)) continue;
                $to = $formData[$field . '_to'] ?? null;
                $processed[] = $field;
                $dto = $this->buildDateRangeFilter($field, $value, $to ?: null);
                if ($dto) $filters[] = $dto;
                continue;
            }

            if (str_ends_with($key, '_to')) {
                $field = substr($key, 0, -3);
                if (in_array($field, $processed, true)) continue;
                $from = $formData[$field . '_from'] ?? null;
                $processed[] = $field;
                $dto = $this->buildDateRangeFilter($field, $from ?: null, $value);
                if ($dto) $filters[] = $dto;
            }
        }

        return $filters;
    }

    /**
     * Processes custom filters from CrudConfig.
     * Supports whereHas and `formData['custom'][$field]` pattern.
     *
     * @param array $customFilterConfig  `customFilters` section of CrudConfig
     * @param array $formData            Filter form data
     * @return FilterDTO[]
     */
    public function processCustomFilters(array $customFilterConfig, array $formData): array
    {
        $filters = [];

        foreach ($customFilterConfig as $cfgFilter) {
            $fieldKey = $cfgFilter['field'] ?? null;

            if (! $fieldKey) {
                continue;
            }

            // Supports $formData[$field] and $formData['custom'][$field]
            $value = $formData[$fieldKey]
                ?? $formData['custom'][$cfgFilter['colRelation'] ?? $fieldKey]
                ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $whereHas    = $cfgFilter['whereHas']    ?? null;
            $colRelation = $cfgFilter['colRelation'] ?? $cfgFilter['field_relation'] ?? '';
            $operator    = $cfgFilter['operator']    ?? $cfgFilter['defaultOperator'] ?? '=';
            $type        = $cfgFilter['type']        ?? $cfgFilter['colsFilterType']  ?? ($whereHas ? 'relation' : 'text');
            $logic       = $cfgFilter['logic']       ?? 'AND';
            $aggregate   = $cfgFilter['aggregate']   ?? null;

            $options = ['logic' => $logic];

            if ($whereHas) {
                $options['whereHas']    = $whereHas;
                $options['column']      = $colRelation;
                $options['colRelation'] = $colRelation;
                if ($aggregate) {
                    $options['aggregate']      = $aggregate;
                    $options['aggregateColumn'] = $colRelation;
                }
            }

            // If operator is IN and value is a CSV string
            if (strtoupper($operator) === 'IN' && is_string($value)) {
                $value = array_map('trim', explode(',', $value));
                $type  = 'array';
            }

            $filters[] = new FilterDTO(
                field:    $whereHas ? $colRelation : $fieldKey,
                value:    $value,
                operator: $operator,
                type:     $type,
                options:  $options,
            );
        }

        return $filters;
    }

    /**
     * Builds global search filters with OR across all visible fields,
     * including relationships (whereHas with LIKE).
     *
     * Returns FilterDTOs with logic='OR' ready for `applyFilters()`.
     *
     * @param array  $cols  CrudConfig columns
     * @param string $term  Search term
     * @return FilterDTO[]
     */
    public function buildGlobalSearchFilters(array $cols, string $term): array
    {
        $filters = [];

        foreach ($cols as $col) {
            $tipo  = $col['colsTipo']        ?? 'text';
            // colsSource takes priority for JOIN columns (aliases don't work in WHERE)
            $field = $col['colsSource'] ?? $col['colsNomeFisico'] ?? '';
            $rel   = $col['colsRelacao']     ?? null;
            $exibe = $col['colsRelacaoExibe']?? null;

            if (! $field) {
                continue;
            }

            if ($rel && $exibe) {
                // Search inside the relation via whereHas with OR
                $filters[] = new FilterDTO(
                    field:    $field,
                    value:    $term,
                    operator: 'LIKE',
                    type:     'relation',
                    options:  [
                        'logic'    => 'OR',
                        'whereHas' => $rel,
                        'column'   => $exibe,
                    ],
                );
                continue;
            }

            // Search in direct text and select fields
            if (in_array($tipo, ['text', 'select'], true)) {
                $filters[] = new FilterDTO(
                    field:    $field,
                    value:    $term,
                    operator: 'LIKE',
                    type:     'text',
                    options:  ['logic' => 'OR'],
                );
            }
        }

        return $filters;
    }

    /**
     * Creates a date range FilterDTO from optional from/to values.
     */
    protected function buildDateRangeFilter(string $field, ?string $from, ?string $to): ?FilterDTO
    {
        if ($from && $to) {
            return new FilterDTO(
                field:    $field,
                value:    [$from, $to],
                operator: 'BETWEEN',
                type:     'date',
            );
        }

        if ($from) {
            return new FilterDTO(
                field:    $field,
                value:    $from,
                operator: '>=',
                type:     'date',
            );
        }

        if ($to) {
            return new FilterDTO(
                field:    $field,
                value:    $to,
                operator: '<=',
                type:     'date',
            );
        }

        return null;
    }
}


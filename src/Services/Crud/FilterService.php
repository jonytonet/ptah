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
 * Serviço de filtros para o BaseCrud.
 *
 * Usa o padrão Strategy para aplicar filtros ao Query Builder do Eloquent
 * de forma type-safe, sem eval(). Suporta lógica AND/OR por filtro.
 *
 * Cada FilterDTO pode ter `options['logic'] = 'OR'` para ser agrupado
 * em um bloco OR separado dos filtros AND normais.
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
     * Registra uma estratégia customizada (extensão via ServiceProvider).
     */
    public function registerStrategy(string $type, FilterStrategyInterface $strategy): void
    {
        $this->strategies[$type] = $strategy;
    }


    /**
     * Aplica uma coleção de FilterDTOs ao Builder.
     *
     * Filtros com `options['logic'] = 'OR'` são agrupados em um bloco OR.
     * Os demais são aplicados com AND (padrão).
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

        // Aplica AND filters normalmente
        foreach ($andFilters as $filter) {
            $query = $this->applyFilter($query, $filter);
        }

        // Agrupa OR filters em um único bloco WHERE (... OR ... OR ...)
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
     * Aplica um único filtro ao Builder usando a estratégia correta.
     */
    protected function applyFilter(Builder $query, FilterDTO $filter): Builder
    {
        // Filtro de relação explícito via whereHas
        if (! empty($filter->options['whereHas'])) {
            return $this->strategies['relation']->apply($query, $filter);
        }

        $type     = $filter->type ?? 'text';
        $strategy = $this->strategies[$type] ?? $this->strategies['text'];

        return $strategy->apply($query, $filter);
    }

    // ── Utilitários ────────────────────────────────────────────────────────

    /**
     * Processa filtros de date range do formulário do BaseCrud.
     *
     * Aceita o padrão ERP: chave `{field}_start` / `{field}_end` nos formData.
     * Também aceita o padrão legado: `{field}_from` / `{field}_to`.
     *
     * @param array $formData  Dados do formulário de filtros (dateRanges)
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

            // Padrão ERP: {field}_start / {field}_end
            if (str_ends_with($key, '_start')) {
                $field = substr($key, 0, -6);
                if (in_array($field, $processed, true)) continue;
                $to          = $formData[$field . '_end'] ?? null;
                $processed[] = $field;

                $opFrom = $operators[$field . '_start'] ?? null;
                $opTo   = $operators[$field . '_end']   ?? null;

                if ($opFrom || $opTo) {
                    // Operadores explícitos: aplica individualmente
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

            // Padrão legado: {field}_from / {field}_to
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
     * Processa filtros customizados do CrudConfig.
     * Suporta whereHas e padrão `formData['custom'][$field]`.
     *
     * @param array $customFilterConfig  Seção `customFilters` do CrudConfig
     * @param array $formData            Dados do formulário de filtros
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

            // Suporta $formData[$field] e $formData['custom'][$field]
            $value = $formData[$fieldKey]
                ?? $formData['custom'][$cfgFilter['colRelation'] ?? $fieldKey]
                ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $whereHas    = $cfgFilter['whereHas']    ?? null;
            $colRelation = $cfgFilter['colRelation'] ?? '';
            $operator    = $cfgFilter['operator']    ?? '=';
            $type        = $cfgFilter['type']        ?? ($whereHas ? 'relation' : 'text');
            $logic       = $cfgFilter['logic']       ?? 'AND';

            $options = ['logic' => $logic];

            if ($whereHas) {
                $options['whereHas']    = $whereHas;
                $options['column']      = $colRelation;
                $options['colRelation'] = $colRelation;
            }

            // Se operador for IN e valor for string CSV
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
     * Constrói filtros de busca global com OR em todos os campos visíveis,
     * incluindo relacionamentos (whereHas com LIKE).
     *
     * Retorna FilterDTOs com logic='OR' prontos para `applyFilters()`.
     *
     * @param array  $cols  Colunas do CrudConfig
     * @param string $term  Termo buscado
     * @return FilterDTO[]
     */
    public function buildGlobalSearchFilters(array $cols, string $term): array
    {
        $filters = [];

        foreach ($cols as $col) {
            $tipo  = $col['colsTipo']        ?? 'text';
            $field = $col['colsNomeFisico']  ?? '';
            $rel   = $col['colsRelacao']     ?? null;
            $exibe = $col['colsRelacaoExibe']?? null;

            if (! $field) {
                continue;
            }

            if ($rel && $exibe) {
                // Busca dentro da relação via whereHas com OR
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

            // Busca em campos texto e select diretos
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
     * Cria um FilterDTO de date range a partir de from/to opcionais.
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


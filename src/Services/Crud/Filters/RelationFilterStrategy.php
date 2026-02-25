<?php

declare(strict_types=1);

namespace Ptah\Services\Crud\Filters;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Contracts\FilterStrategyInterface;
use Ptah\DTO\FilterDTO;

/**
 * Estratégia de filtro para campos de relacionamento Eloquent.
 *
 * Suporta whereHas com busca textual, numérica e agregados (SUM/COUNT/AVG/MAX/MIN).
 *
 * Configuração via FilterDTO::options:
 *   'whereHas'       => 'supplier'                    (nome da relação)
 *   'column'         => 'name'                        (coluna dentro da relação)
 *   'aggregate'      => 'count'                       (opcional — 'sum'|'count'|'avg'|'max'|'min')
 *   'aggregateColumn'=> 'amount'                      (coluna para o agregado)
 */
class RelationFilterStrategy implements FilterStrategyInterface
{
    protected const ALLOWED_AGGREGATES = ['sum', 'count', 'avg', 'max', 'min'];
    protected const ALLOWED_OPERATORS  = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    public function normalize(FilterDTO $filter): ?FilterDTO
    {
        $value = $filter->value;

        if ($value === null || $value === '') {
            return null;
        }

        // Se value é array de options (colunas select), tenta extrair valor real
        if (is_array($value) && isset($value[0]['value'])) {
            return null; // Estrutura inválida — ignorar
        }

        return $filter;
    }

    public function apply(Builder $query, FilterDTO $filter): Builder
    {
        $normalized = $this->normalize($filter);

        if ($normalized === null) {
            return $query;
        }

        $options   = $normalized->options;
        $whereHas  = $options['whereHas'] ?? null;
        $column    = $options['column'] ?? $options['colRelation'] ?? null;
        $operator  = $normalized->operator;
        $value     = $normalized->value;
        $aggregate = strtolower($options['aggregate'] ?? '');
        $aggColumn = $options['aggregateColumn'] ?? $column;

        // Validação do operador
        if (! in_array(strtoupper($operator), self::ALLOWED_OPERATORS, true)) {
            $operator = '=';
        }

        if (! $whereHas) {
            // Filtro direto na coluna FK
            return $query->where($normalized->field, $operator, $value);
        }

        // Filtro com agregado (COUNT > 0, SUM >= 100, etc.)
        if ($aggregate && in_array($aggregate, self::ALLOWED_AGGREGATES, true)) {
            return $this->applyAggregateFilter($query, $whereHas, $aggColumn, $aggregate, $operator, $value);
        }

        // whereHas padrão
        return $query->whereHas($whereHas, function (Builder $q) use ($column, $operator, $value) {
            if (! $column) {
                return;
            }

            // Garante que value seja scalar
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }

            $op = strtoupper($operator);

            match (true) {
                $op === 'LIKE'     => $q->where($column, 'LIKE', '%' . $value . '%'),
                $op === 'NOT LIKE' => $q->where($column, 'NOT LIKE', '%' . $value . '%'),
                $op === 'IN'       => $q->whereIn($column, (array) $value),
                $op === 'NOT IN'   => $q->whereNotIn($column, (array) $value),
                default            => $q->where($column, $operator, $value),
            };
        });
    }

    protected function applyAggregateFilter(
        Builder $query,
        string  $relation,
        ?string $column,
        string  $aggregate,
        string  $operator,
        mixed   $value
    ): Builder {
        return $query->whereHas($relation, function (Builder $q) use ($column, $aggregate, $operator, $value) {
            if (! $column) {
                return;
            }

            $q->selectRaw("1")
              ->groupBy($q->getModel()->getKeyName())
              ->havingRaw(
                  strtoupper($aggregate) . '(' . $column . ') ' . $operator . ' ?',
                  [(float) $value]
              );
        });
    }
}

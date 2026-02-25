<?php

declare(strict_types=1);

namespace Ptah\Services\Crud\Filters;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Contracts\FilterStrategyInterface;
use Ptah\DTO\FilterDTO;

/**
 * Estratégia de filtro para campos numéricos.
 *
 * Operadores suportados: =, !=, >, <, >=, <=, BETWEEN, IN, NOT IN
 */
class NumericFilterStrategy implements FilterStrategyInterface
{
    public function normalize(FilterDTO $filter): ?FilterDTO
    {
        $value = $filter->value;

        if ($value === null || $value === '') {
            return null;
        }

        // Array com 2 itens vira BETWEEN automaticamente
        if (is_array($value) && count($value) === 2) {
            [$from, $to] = $value;
            if ($from === '' && $to === '') {
                return null;
            }
        }

        return $filter;
    }

    public function apply(Builder $query, FilterDTO $filter): Builder
    {
        $normalized = $this->normalize($filter);

        if ($normalized === null) {
            return $query;
        }

        $operator = strtoupper($normalized->operator);
        $field    = $normalized->field;
        $value    = $normalized->value;

        // BETWEEN automático se valor for array [from, to]
        if (is_array($value) && count($value) === 2) {
            [$from, $to] = $value;

            if ($from !== '' && $to !== '') {
                return $query->whereBetween($field, [(float) $from, (float) $to]);
            }

            if ($from !== '') {
                return $query->where($field, '>=', (float) $from);
            }

            if ($to !== '') {
                return $query->where($field, '<=', (float) $to);
            }

            return $query;
        }

        return match (true) {
            $operator === 'IN' && is_array($value)     => $query->whereIn($field, $value),
            $operator === 'NOT IN' && is_array($value) => $query->whereNotIn($field, $value),
            $operator === 'BETWEEN' && is_string($value) => $this->applyBetweenString($query, $field, $value),
            $operator === 'IS NULL'                    => $query->whereNull($field),
            $operator === 'IS NOT NULL'                => $query->whereNotNull($field),
            default                                    => $query->where($field, $normalized->operator, (float) $value),
        };
    }

    protected function applyBetweenString(Builder $query, string $field, string $value): Builder
    {
        $parts = array_map('trim', explode(',', $value));

        if (count($parts) === 2) {
            return $query->whereBetween($field, [(float) $parts[0], (float) $parts[1]]);
        }

        return $query->where($field, '=', (float) $value);
    }
}

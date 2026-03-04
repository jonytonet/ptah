<?php

declare(strict_types=1);

namespace Ptah\Services\Crud\Filters;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Contracts\FilterStrategyInterface;
use Ptah\DTO\FilterDTO;

/**
 * Filter strategy for text fields.
 *
 * Supported operators: LIKE (default), =, !=, IN, NOT IN
 */
class TextFilterStrategy implements FilterStrategyInterface
{
    public function normalize(FilterDTO $filter): ?FilterDTO
    {
        $value = $filter->value;

        if ($value === null || $value === '') {
            return null;
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

        return match (true) {
            $operator === 'IN' && is_array($value)     => $query->whereIn($field, $value),
            $operator === 'NOT IN' && is_array($value) => $query->whereNotIn($field, $value),
            $operator === 'LIKE'                       => $query->whereRaw('LOWER(' . $field . ') LIKE ?', ['%' . mb_strtolower($value) . '%']),
            $operator === 'LIKE_START'                 => $query->whereRaw('LOWER(' . $field . ') LIKE ?', [mb_strtolower($value) . '%']),
            $operator === 'LIKE_END'                   => $query->whereRaw('LOWER(' . $field . ') LIKE ?', ['%' . mb_strtolower($value)]),
            $operator === 'NOT LIKE'                   => $query->whereRaw('LOWER(' . $field . ') NOT LIKE ?', ['%' . mb_strtolower($value) . '%']),
            $operator === 'IS NULL'                    => $query->whereNull($field),
            $operator === 'IS NOT NULL'                => $query->whereNotNull($field),
            default                                    => $query->where($field, $normalized->operator, $value),
        };
    }
}

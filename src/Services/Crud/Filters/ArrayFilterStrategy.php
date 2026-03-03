<?php

declare(strict_types=1);

namespace Ptah\Services\Crud\Filters;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Contracts\FilterStrategyInterface;
use Ptah\DTO\FilterDTO;

/**
 * Filter strategy for array fields (whereIn / whereNotIn).
 *
 * Accepts direct arrays or comma-separated strings.
 */
class ArrayFilterStrategy implements FilterStrategyInterface
{
    public function normalize(FilterDTO $filter): ?FilterDTO
    {
        $value = $filter->value;

        if ($value === null || $value === '') {
            return null;
        }

        // Normalise CSV string into array
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
            $value = array_filter($value, fn($v) => $v !== '');

            if (empty($value)) {
                return null;
            }

            // Returns a new DTO with the corrected value
            return new FilterDTO(
                field:    $filter->field,
                value:    array_values($value),
                operator: $filter->operator,
                type:     $filter->type,
                options:  $filter->options,
            );
        }

        if (is_array($value) && empty($value)) {
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

        $field    = $normalized->field;
        $value    = is_array($normalized->value) ? $normalized->value : [$normalized->value];
        $operator = strtoupper($normalized->operator);

        return match (true) {
            $operator === 'NOT IN' => $query->whereNotIn($field, $value),
            default                => $query->whereIn($field, $value),
        };
    }
}

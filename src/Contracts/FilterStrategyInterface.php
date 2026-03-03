<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Ptah\DTO\FilterDTO;

/**
 * Contract for filter strategies used by FilterService.
 *
 * Each strategy is responsible for applying a specific type
 * of filter to the Eloquent Query Builder.
 */
interface FilterStrategyInterface
{
    /**
     * Applies the filter to the Builder.
     *
     * @param Builder   $query  Eloquent Query Builder
     * @param FilterDTO $filter DTO with field, value, operator, type, options
     * @return Builder
     */
    public function apply(Builder $query, FilterDTO $filter): Builder;

    /**
     * Validates and normalises the DTO before applying.
     * Returns null if the filter should be skipped.
     */
    public function normalize(FilterDTO $filter): ?FilterDTO;
}

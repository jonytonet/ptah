<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Ptah\DTO\FilterDTO;

/**
 * Contrato para estratégias de filtro do FilterService.
 *
 * Cada estratégia é responsável por aplicar um tipo específico
 * de filtro ao Query Builder do Eloquent.
 */
interface FilterStrategyInterface
{
    /**
     * Aplica o filtro ao Builder.
     *
     * @param Builder   $query  Query Builder do Eloquent
     * @param FilterDTO $filter DTO com field, value, operator, type, options
     * @return Builder
     */
    public function apply(Builder $query, FilterDTO $filter): Builder;

    /**
     * Valida e normaliza o DTO antes de aplicar.
     * Retorna null se o filtro deve ser ignorado.
     */
    public function normalize(FilterDTO $filter): ?FilterDTO;
}

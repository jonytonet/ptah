<?php

declare(strict_types=1);

namespace Ptah\Services\Crud\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Ptah\Contracts\FilterStrategyInterface;
use Ptah\DTO\FilterDTO;

/**
 * Estratégia de filtro para campos de data e datetime.
 *
 * Operadores suportados: =, !=, >, <, >=, <=, BETWEEN
 * Normaliza datas via Carbon para garantir startOfDay / endOfDay em BETWEEN.
 */
class DateFilterStrategy implements FilterStrategyInterface
{
    /** @param string $type 'date' ou 'datetime' */
    public function __construct(protected string $type = 'date') {}

    public function normalize(FilterDTO $filter): ?FilterDTO
    {
        $value = $filter->value;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            [$from, $to] = array_pad($value, 2, null);
            if (! $from && ! $to) {
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

        // Array [from, to] ⟹ BETWEEN
        if (is_array($value)) {
            [$from, $to] = array_pad($value, 2, null);
            return $this->applyRange($query, $field, $from, $to);
        }

        // String "data1,data2" ⟹ BETWEEN
        if ($operator === 'BETWEEN' && is_string($value) && str_contains($value, ',')) {
            $parts = array_map('trim', explode(',', $value, 2));
            return $this->applyRange($query, $field, $parts[0] ?? null, $parts[1] ?? null);
        }

        // Campo inteiro {field}_start / {field}_end já resolvido no FilterService
        if ($operator === 'BETWEEN' && is_string($value)) {
            return $query->whereDate($field, '=', $value);
        }

        // IS NULL / IS NOT NULL
        if ($operator === 'IS NULL') {
            return $query->whereNull($field);
        }

        if ($operator === 'IS NOT NULL') {
            return $query->whereNotNull($field);
        }

        // Comparações simples
        if ($this->type === 'datetime') {
            try {
                $dt = Carbon::parse($value);
                return $query->where($field, $normalized->operator, $dt->toDateTimeString());
            } catch (\Throwable) {
                return $query->whereDate($field, $normalized->operator, $value);
            }
        }

        return $query->whereDate($field, $normalized->operator, $value);
    }

    protected function applyRange(Builder $query, string $field, ?string $from, ?string $to): Builder
    {
        if ($from && $to) {
            try {
                $start = Carbon::parse($from)->startOfDay();
                $end   = Carbon::parse($to)->endOfDay();
                return $query->whereBetween($field, [$start->toDateTimeString(), $end->toDateTimeString()]);
            } catch (\Throwable) {
                return $query->whereBetween($field, [$from, $to]);
            }
        }

        if ($from) {
            try {
                return $query->where($field, '>=', Carbon::parse($from)->startOfDay()->toDateTimeString());
            } catch (\Throwable) {
                return $query->where($field, '>=', $from);
            }
        }

        if ($to) {
            try {
                return $query->where($field, '<=', Carbon::parse($to)->endOfDay()->toDateTimeString());
            } catch (\Throwable) {
                return $query->where($field, '<=', $to);
            }
        }

        return $query;
    }
}

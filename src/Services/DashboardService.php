<?php

declare(strict_types=1);

namespace Ptah\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Ptah\Support\SafeUrl;
use Ptah\Support\SqlIdentifier;

/**
 * Computes the widgets declared in config/ptah-dashboard.php.
 *
 * The widget definitions are code (reviewed, versioned), but every name in
 * them still reaches SQL, so each column goes through SqlIdentifier and each
 * operator through an allowlist — a typo fails as a widget error, not as SQL.
 * A widget that fails renders its error instead of taking the page down.
 *
 * Scope: the model's global scopes apply (it is `Model::query()`), the active
 * company when the table has the company column, and `permission` hides the
 * widget from whoever cannot `read` that page object.
 */
final class DashboardService
{
    private const OPERATORS = ['=', '!=', '<>', '>', '>=', '<', '<=', 'like', 'in', 'not in', 'null', 'not null'];

    /**
     * @return list<array<string, mixed>>
     */
    public function visibleWidgets(): array
    {
        $out = [];

        foreach ((array) config('ptah-dashboard.widgets', []) as $i => $widget) {
            if (! is_array($widget) || ! $this->canSee($widget)) {
                continue;
            }
            $out[] = $this->compute($widget, (int) $i);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array<string, mixed>
     */
    public function compute(array $w, int $index = 0): array
    {
        $base = [
            'type' => (string) ($w['type'] ?? 'stat'),
            'label' => (string) ($w['label'] ?? ''),
            'color' => in_array($w['color'] ?? null, ['primary', 'success', 'danger', 'warn'], true) ? $w['color'] : 'primary',
            'link' => is_string($w['link'] ?? null) && SafeUrl::isSafe($w['link']) ? $w['link'] : null,
            'error' => null,
        ];

        try {
            $company = function_exists('ptah_company_id') ? ptah_company_id() : 0;
            // Por usuario tambem: um escopo global do host pode depender de quem
            // esta logado (o vendedor que so ve os proprios pedidos).
            $key = 'ptah.dashboard.'.$index.'.'.md5((string) json_encode($w)).'.'.$company.'.'.(string) auth()->id();
            $seconds = max(0, (int) ($w['cache'] ?? 60));

            $data = $seconds > 0
                ? Cache::remember($key, $seconds, fn () => $this->data($w))
                : $this->data($w);

            return $base + $data;
        } catch (\Throwable $e) {
            // array_merge, nao `+`: `$base` ja tem 'error' => null e o `+` o manteria.
            return array_merge($base, ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array<string, mixed>
     */
    private function data(array $w): array
    {
        return match ($w['type'] ?? 'stat') {
            'stat' => $this->stat($w),
            'trend' => $this->trend($w),
            'latest' => $this->latest($w),
            default => throw new \InvalidArgumentException('Unknown widget type "'.($w['type'] ?? '').'" (stat, trend, latest).'),
        };
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array{value: string, raw: float|int}
     */
    private function stat(array $w): array
    {
        $query = $this->query($w);
        $this->period($query, $w, (string) ($w['period'] ?? 'all'));

        $aggregate = (string) ($w['aggregate'] ?? 'count');
        $raw = match ($aggregate) {
            'count' => $query->count(),
            'sum', 'avg' => (float) $query->{$aggregate}($this->column($query, (string) ($w['field'] ?? ''))),
            default => throw new \InvalidArgumentException("Unknown aggregate \"{$aggregate}\" (count, sum, avg)."),
        };

        return ['raw' => $raw, 'value' => $this->format($raw, (string) ($w['format'] ?? 'number'))];
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array{points: list<array{date: string, count: int}>, total: int, max: int}
     */
    private function trend(array $w): array
    {
        $days = max(2, min(366, (int) ($w['days'] ?? 30)));
        $from = CarbonImmutable::today()->subDays($days - 1);
        $query = $this->query($w);
        $dateCol = $this->column($query, (string) ($w['date_field'] ?? 'created_at'));

        $counts = [];
        foreach ((clone $query)->where($dateCol, '>=', $from)->pluck($dateCol) as $value) {
            try {
                $day = CarbonImmutable::parse((string) $value)->format('Y-m-d');
                $counts[$day] = ($counts[$day] ?? 0) + 1;
            } catch (\Throwable) {
                continue;
            }
        }

        $points = [];
        for ($d = $from; $d <= CarbonImmutable::today(); $d = $d->addDay()) {
            $points[] = ['date' => $d->format('Y-m-d'), 'count' => $counts[$d->format('Y-m-d')] ?? 0];
        }

        $values = array_column($points, 'count');

        return ['points' => $points, 'total' => array_sum($values), 'max' => max(1, ...$values)];
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function latest(array $w): array
    {
        $query = $this->query($w);
        $model = $query->getModel();
        $hidden = $model->getHidden();
        $columns = array_values(array_filter(
            array_map('strval', (array) ($w['columns'] ?? [])),
            fn (string $c) => SqlIdentifier::isSafe($c) && ! in_array($c, $hidden, true)
        ));

        if ($columns === []) {
            throw new \InvalidArgumentException('A latest widget needs "columns".');
        }

        $default = Schema::hasColumn($model->getTable(), 'created_at') ? 'created_at' : $model->getKeyName();
        $orderBy = $this->column($query, (string) ($w['date_field'] ?? $default));
        $limit = max(1, min(50, (int) ($w['limit'] ?? 5)));

        $rows = $query->orderByDesc($orderBy)->limit($limit)->get()
            ->map(fn (Model $r) => array_map(fn (string $c) => is_scalar($r->getAttribute($c)) ? (string) $r->getAttribute($c) : '', $columns))
            ->all();

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function query(array $w): Builder
    {
        $class = (string) ($w['model'] ?? '');

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new \InvalidArgumentException("Model \"{$class}\" does not exist.");
        }

        /** @var Builder $query */
        $query = $class::query();
        $table = $query->getModel()->getTable();

        $companyField = (string) ($w['company_field'] ?? 'company_id');
        $company = function_exists('ptah_company_id') ? ptah_company_id() : 0;
        if ($company > 0 && SqlIdentifier::isSafe($companyField) && Schema::hasColumn($table, $companyField)) {
            $query->where($table.'.'.$companyField, $company);
        }

        foreach ((array) ($w['where'] ?? []) as $cond) {
            [$col, $op, $value] = array_pad(array_values((array) $cond), 3, null);
            $op = strtolower((string) $op);

            if (! in_array($op, self::OPERATORS, true)) {
                throw new \InvalidArgumentException("Operator \"{$op}\" is not allowed in a widget.");
            }

            $col = $this->column($query, (string) $col);
            match ($op) {
                'in' => $query->whereIn($col, (array) $value),
                'not in' => $query->whereNotIn($col, (array) $value),
                'null' => $query->whereNull($col),
                'not null' => $query->whereNotNull($col),
                default => $query->where($col, $op, $value),
            };
        }

        return $query;
    }

    private function period(Builder $query, array $w, string $period): void
    {
        if ($period === 'all') {
            return;
        }

        $today = CarbonImmutable::today();
        $from = match ($period) {
            'today' => $today,
            'week' => $today->startOfWeek(),
            'month' => $today->startOfMonth(),
            'year' => $today->startOfYear(),
            default => throw new \InvalidArgumentException("Unknown period \"{$period}\" (today, week, month, year, all)."),
        };

        $query->where($this->column($query, (string) ($w['date_field'] ?? 'created_at')), '>=', $from);
    }

    private function column(Builder $query, string $column): string
    {
        if (! SqlIdentifier::isSafe($column)) {
            throw new \InvalidArgumentException("\"{$column}\" is not a valid column name.");
        }

        return str_contains($column, '.') ? $column : $query->getModel()->getTable().'.'.$column;
    }

    private function format(float|int $value, string $format): string
    {
        return match ($format) {
            'money' => 'R$ '.number_format((float) $value, 2, ',', '.'),
            'percent' => number_format((float) $value, 1, ',', '.').'%',
            default => number_format((float) $value, is_float($value) && floor($value) != $value ? 2 : 0, ',', '.'),
        };
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function canSee(array $w): bool
    {
        $key = $w['permission'] ?? null;

        if (! is_string($key) || $key === '' || ! config('ptah.modules.permissions')) {
            return true;
        }

        return function_exists('ptah_can') && ptah_can($key, 'read');
    }
}

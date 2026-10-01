<?php

declare(strict_types=1);

namespace Ptah\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

    /** The group /dashboard shows — and the one a flat `widgets` list is. */
    public const DEFAULT_GROUP = 'dashboard';

    /**
     * @return list<array<string, mixed>>
     */
    public function visibleWidgets(?string $group = null): array
    {
        $out = [];

        foreach ($this->widgetsOf($group ?? self::DEFAULT_GROUP) as $i => $widget) {
            if (! is_array($widget) || ! $this->canSee($widget)) {
                continue;
            }
            $out[] = $this->compute($widget, (int) $i);
        }

        return $out;
    }

    public function hasWidgets(?string $group = null): bool
    {
        return $this->widgetsOf($group ?? self::DEFAULT_GROUP) !== [];
    }

    /**
     * The widgets of one group. `widgets` may mix both forms: the numbered
     * entries (the flat list it always was) are the default group, and a
     * named entry holding a list is a group of its own —
     * `'financeiro' => [...]`, placed with
     * `@include('ptah::dashboard.widgets', ['group' => 'financeiro'])`.
     *
     * @return list<mixed>
     */
    private function widgetsOf(string $group): array
    {
        $all = (array) config('ptah-dashboard.widgets', []);
        $out = [];

        foreach ($all as $key => $entry) {
            if (is_int($key) && $group === self::DEFAULT_GROUP) {
                $out[] = $entry;
            } elseif ($key === $group && is_array($entry) && array_is_list($entry)) {
                array_push($out, ...$entry);
            }
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
            // A mensagem crua (SQL, tabela, conexao de uma QueryException) so
            // em debug; em producao vai para o log (1.41.11).
            Log::warning('[ptah] dashboard widget failed', ['widget' => $w['title'] ?? $index, 'error' => $e->getMessage()]);

            // A validacao da definicao (InvalidArgumentException) e texto do
            // proprio pacote, sem SQL, e ajuda quem configura: continua.
            $safe = $e instanceof \InvalidArgumentException || config('app.debug');

            return array_merge($base, ['error' => $safe ? $e->getMessage() : trans('ptah::ui.dashboard_widget_error')]);
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
            'breakdown' => $this->breakdown($w),
            default => throw new \InvalidArgumentException('Unknown widget type "'.($w['type'] ?? '').'" (stat, trend, breakdown, latest).'),
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
     * Bars over a window of `days`, one per day, week or month (`group`), of
     * the count — or the sum/avg of `field` (`aggregate`): revenue per day,
     * receivables per month.
     *
     * Grouped by DAY in the database (at most 366 rows come back, not every
     * row of the window — 1.43.0), then folded into weeks/months here, which
     * keeps the SQL the same on every driver. An average is folded as
     * sum / count, never as an average of averages.
     *
     * @param  array<string, mixed>  $w
     * @return array<string, mixed>
     */
    private function trend(array $w): array
    {
        $group = (string) ($w['group'] ?? 'day');
        $days = max(2, min(366, (int) ($w['days'] ?? 30)));
        $today = CarbonImmutable::today();

        // `days` e a janela; agrupado, ela vira o numero de semanas/meses
        // mais proximo (365 dias por mes = 12 barras), do inicio do periodo.
        [$from, $step, $bucket, $label] = match ($group) {
            'day' => [$today->subDays($days - 1), fn ($d) => $d->addDay(), fn ($d) => $d, 'd/m'],
            'week' => [$today->startOfWeek()->subWeeks(max(2, (int) round($days / 7)) - 1), fn ($d) => $d->addWeek(), fn ($d) => $d->startOfWeek(), 'd/m'],
            'month' => [$today->startOfMonth()->subMonthsNoOverflow(max(2, (int) round($days / 30.4375)) - 1), fn ($d) => $d->addMonthNoOverflow(), fn ($d) => $d->startOfMonth(), 'm/Y'],
            default => throw new \InvalidArgumentException("Unknown group \"{$group}\" (day, week, month)."),
        };

        $query = $this->query($w);
        $dateCol = $this->column($query, (string) ($w['date_field'] ?? 'created_at'));
        [$aggregate, $field] = $this->aggregateOf($query, $w);

        $base = (clone $query)->where($dateCol, '>=', $from)->where($dateCol, '<=', $today->endOfDay())->toBase();
        $grammar = $base->getGrammar();
        $day = $query->getModel()->getConnection()->getDriverName() === 'sqlsrv'
            ? 'CAST('.$grammar->wrap($dateCol).' AS date)'
            : 'DATE('.$grammar->wrap($dateCol).')';
        $base->columns = null;
        $base->reorder()->selectRaw($day.' as ptah_day, COUNT(*) as ptah_n'.($field ? ', SUM('.$grammar->wrap($field).') as ptah_s' : ''))
            ->groupByRaw($day);

        $n = [];
        $s = [];
        foreach ($base->get() as $row) {
            try {
                $key = $bucket(CarbonImmutable::parse((string) $row->ptah_day))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
            $n[$key] = ($n[$key] ?? 0) + (int) $row->ptah_n;
            $s[$key] = ($s[$key] ?? 0) + (float) ($row->ptah_s ?? 0);
        }

        $format = (string) ($w['format'] ?? 'number');
        $points = [];
        for ($d = $from; $d <= $today; $d = $step($d)) {
            $key = $d->format('Y-m-d');
            $value = $this->fold($aggregate, $n[$key] ?? 0, $s[$key] ?? 0.0);
            $points[] = ['date' => $key, 'label' => $d->format($label), 'count' => $n[$key] ?? 0, 'value' => $value, 'display' => $this->format($value, $format)];
        }

        $total = $this->fold($aggregate, array_sum($n), (float) array_sum($s));
        $values = array_column($points, 'value');

        return ['points' => $points, 'total' => $total, 'display_total' => $this->format($total, $format), 'max' => max(1, ...$values), 'group' => $group];
    }

    /**
     * Totals per category (`group_by` a column, or `relation.column` of a
     * belongsTo): sales of the month per payment method. The top `limit`, and
     * the rest as one "Others" bar so the bars still add up to the total.
     *
     * @param  array<string, mixed>  $w
     * @return array<string, mixed>
     */
    private function breakdown(array $w): array
    {
        $query = $this->query($w);
        $this->period($query, $w, (string) ($w['period'] ?? 'all'));
        [$aggregate, $field] = $this->aggregateOf($query, $w);
        $groupCol = $this->groupColumn($query, (string) ($w['group_by'] ?? ''));
        $limit = max(1, min(20, (int) ($w['limit'] ?? 5)));
        $format = (string) ($w['format'] ?? 'number');

        $base = (clone $query)->toBase();
        $grammar = $base->getGrammar();
        $sum = $field ? 'SUM('.$grammar->wrap($field).')' : '0';
        $order = match ($aggregate) {
            'sum' => $sum,
            'avg' => 'AVG('.$grammar->wrap((string) $field).')',
            default => 'COUNT(*)',
        };
        $base->columns = null;
        $rows = $base->reorder()->selectRaw($grammar->wrap($groupCol).' as ptah_g, COUNT(*) as ptah_n, '.$sum.' as ptah_s')
            ->groupBy($groupCol)->orderByRaw($order.' desc')->limit($limit)->get();

        $all = (clone $query)->toBase();
        $all->columns = null;
        $totals = $all->reorder()->selectRaw('COUNT(*) as ptah_n, '.$sum.' as ptah_s')->first();
        $totalN = (int) ($totals->ptah_n ?? 0);
        $totalS = (float) ($totals->ptah_s ?? 0);

        $labels = (array) ($w['labels'] ?? []);
        $items = [];
        $topN = 0;
        $topS = 0.0;
        foreach ($rows as $row) {
            $raw = $row->ptah_g;
            $value = $this->fold($aggregate, (int) $row->ptah_n, (float) $row->ptah_s);
            $items[] = [
                'label' => $raw === null || $raw === '' ? trans('ptah::ui.dashboard_no_value') : (string) ($labels[(string) $raw] ?? $raw),
                'value' => $value,
                'display' => $this->format($value, $format),
            ];
            $topN += (int) $row->ptah_n;
            $topS += (float) $row->ptah_s;
        }

        if ($totalN > $topN) {
            $value = $this->fold($aggregate, $totalN - $topN, $totalS - $topS);
            $items[] = ['label' => trans('ptah::ui.dashboard_others'), 'value' => $value, 'display' => $this->format($value, $format), 'others' => true];
        }

        $total = $this->fold($aggregate, $totalN, $totalS);

        return ['items' => $items, 'total' => $total, 'display_total' => $this->format($total, $format), 'max' => max(1, ...(array_column($items, 'value') ?: [0]))];
    }

    /**
     * `aggregate` (count|sum|avg) and the qualified `field` it needs.
     *
     * @param  array<string, mixed>  $w
     * @return array{0: string, 1: ?string}
     */
    private function aggregateOf(Builder $query, array $w): array
    {
        $aggregate = (string) ($w['aggregate'] ?? 'count');

        return match ($aggregate) {
            'count' => ['count', null],
            'sum', 'avg' => [$aggregate, $this->column($query, (string) ($w['field'] ?? ''))],
            default => throw new \InvalidArgumentException("Unknown aggregate \"{$aggregate}\" (count, sum, avg)."),
        };
    }

    private function fold(string $aggregate, int $count, float $sum): float|int
    {
        return match ($aggregate) {
            'sum' => round($sum, 2),
            'avg' => $count > 0 ? round($sum / $count, 2) : 0,
            default => $count,
        };
    }

    /**
     * A column of the model, or `relation.column` of one of its belongsTo
     * relations (joined) — never a `$hidden` attribute.
     */
    private function groupColumn(Builder $query, string $groupBy): string
    {
        $model = $query->getModel();

        if ($groupBy === '') {
            throw new \InvalidArgumentException('A breakdown widget needs "group_by".');
        }

        if (str_contains($groupBy, '.')) {
            [$name, $column] = explode('.', $groupBy, 2);

            // So um metodo do proprio model: nunca save/delete/... do Model.
            if (method_exists($model, $name) && ! method_exists(Model::class, $name)) {
                $relation = $model->{$name}();

                if (! $relation instanceof BelongsTo) {
                    throw new \InvalidArgumentException("\"{$name}\" is not a belongsTo relation (breakdown groups by a belongsTo).");
                }

                $related = $relation->getRelated();
                if (! SqlIdentifier::isSafe($column) || str_contains($column, '.') || in_array($column, $related->getHidden(), true)) {
                    throw new \InvalidArgumentException("\"{$column}\" cannot be grouped by.");
                }

                $query->leftJoin($related->getTable().' as ptah_bd', 'ptah_bd.'.$relation->getOwnerKeyName(), '=', $relation->getQualifiedForeignKeyName());

                return 'ptah_bd.'.$column;
            }
        }

        $qualified = $this->column($query, $groupBy);
        if (in_array(str_contains($groupBy, '.') ? substr($groupBy, strrpos($groupBy, '.') + 1) : $groupBy, $model->getHidden(), true)) {
            throw new \InvalidArgumentException("\"{$groupBy}\" cannot be grouped by.");
        }

        return $qualified;
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
        [$from, $to] = match ($period) {
            'today' => [$today, $today->endOfDay()],
            'week' => [$today->startOfWeek(), $today->endOfWeek()],
            'month' => [$today->startOfMonth(), $today->endOfMonth()],
            'year' => [$today->startOfYear(), $today->endOfYear()],
            default => throw new \InvalidArgumentException("Unknown period \"{$period}\" (today, week, month, year, all)."),
        };

        // Fechado nos dois lados: com uma data futura (a data agendada), "no
        // mes" somava tambem os meses seguintes.
        $column = $this->column($query, (string) ($w['date_field'] ?? 'created_at'));
        $query->where($column, '>=', $from)->where($column, '<=', $to);
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

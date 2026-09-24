<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ptah\Livewire\BaseCrud\BaseCrud;

/**
 * "Why does this screen show nothing?" — answered layer by layer.
 *
 * A BaseCrud listing narrows the table in a fixed order: the model's global
 * scopes (soft deletes, a host's tenant scope), the company filter, locked
 * filters and the whereHas pre-filter, the global search, the column
 * filters, the date ranges, the quick date filter. Most of that state is
 * restored silently from the user's saved preferences, so an empty screen
 * usually has a cause the user cannot see and the agent cannot guess.
 *
 * This mounts the REAL component as the user — same mount(), same
 * preferences, same config — and counts the rows after each layer by
 * switching the component's own state back on step by step, with the
 * component's own buildBaseQuery(). Nothing is re-implemented, so the answer
 * cannot drift from what the screen does. It also runs the final query the
 * way rows() does, because rows() swallows a QueryException into an empty
 * page (and clears the user's preferences).
 *
 * Read-only: counts and one SELECT. The one side effect of mount() — loading
 * preferences — is a read.
 */
final class WhyEmptyInspector
{
    /**
     * @param  array<string, mixed>  $mountParams  extra mount() params (whereHasFilter, lockedFilters…)
     * @return array{model: string, table: string|null, layers: list<array{layer: string, rows: int|null, note: string}>, final: int|null, sql: string|null, error: string|null, can_read: bool, state: array<string, mixed>}
     */
    public static function inspect(string $model, string $route = '', array $mountParams = []): array
    {
        $previous = app('request');
        app()->instance('request', Request::create('/'.ltrim($route, '/')));

        try {
            /** @var BaseCrud $crud */
            $crud = app('livewire')->new(BaseCrud::class);
            app()->call([$crud, 'boot']);
            app()->call([$crud, 'mount'], ['model' => $model] + $mountParams);
        } finally {
            app()->instance('request', $previous);
        }

        $call = fn (string $method, mixed ...$args) => \Closure::bind(fn () => $this->{$method}(...$args), $crud, BaseCrud::class)();

        $eloquent = $call('resolveEloquentModel');
        $out = [
            'model' => $model,
            'table' => $eloquent?->getTable(),
            'layers' => [],
            'final' => null,
            'sql' => null,
            'error' => null,
            'can_read' => (bool) $call('authorizeCrudAction', 'read'),
            'state' => self::state($crud),
        ];

        if ($crud->crudConfig === []) {
            $out['error'] = "no crud_config for \"{$model}\"".($route !== '' ? " (route {$route}, nor a global one)" : '');

            return $out;
        }

        if (! $eloquent instanceof Model) {
            $out['error'] = 'the config\'s model does not resolve to an Eloquent class';

            return $out;
        }

        $table = $eloquent->getTable();

        try {
            $out['layers'][] = ['layer' => "table {$table}", 'rows' => DB::connection($eloquent->getConnectionName())->table($table)->count(), 'note' => 'every row, no scope'];
        } catch (\Throwable $e) {
            $out['error'] = 'table: '.self::oneLine($e->getMessage());

            return $out;
        }

        $scopes = array_map('class_basename', array_keys($eloquent->getGlobalScopes()));
        $out['layers'][] = ['layer' => 'model global scopes', 'rows' => self::safeCount(fn () => $eloquent->newQuery()), 'note' => $scopes === [] ? 'none' : implode(', ', $scopes)];

        // Liga o estado do componente de volta, camada por camada, na mesma
        // ordem em que buildBaseQuery() aplica.
        $saved = self::state($crud);
        self::clear($crud);

        $steps = [
            'showTrashed' => ['trash view', fn () => $crud->showTrashed = $saved['showTrashed']],
            'companyFilter' => ['company filter', fn () => $crud->companyFilter = $saved['companyFilter']],
            'search' => ['global search', fn () => $crud->search = $saved['search']],
            'filters' => ['column filters', fn () => $crud->filters = $saved['filters']],
            'dateRanges' => ['date ranges', fn () => $crud->dateRanges = $saved['dateRanges']],
            'quickDateFilter' => ['quick date filter', fn () => $crud->quickDateFilter = $saved['quickDateFilter']],
        ];

        $build = fn (): Builder => $call('buildBaseQuery', $eloquent)[0];

        $out['layers'][] = ['layer' => 'screen base (locked, whereHas, custom)', 'rows' => self::safeCount($build), 'note' => self::baseNote($crud, $eloquent)];

        foreach ($steps as $key => [$label, $restore]) {
            $restore();

            if (self::isEmptyState($saved[$key])) {
                continue;
            }

            $out['layers'][] = ['layer' => $label, 'rows' => self::safeCount($build), 'note' => self::describe($key, $saved[$key], $crud, $eloquent)];
        }

        try {
            $query = $build();
            $out['final'] = (clone $query)->count();
            $out['sql'] = self::sql($query);
            // O que rows() faz — um erro aqui vira tela vazia la.
            (clone $query)->limit(1)->get();
        } catch (\Throwable $e) {
            $out['error'] = 'the listing query fails — BaseCrud shows an empty page and clears the user\'s preferences: '.self::oneLine($e->getMessage());
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function state(BaseCrud $crud): array
    {
        return [
            'showTrashed' => $crud->showTrashed,
            'companyFilter' => $crud->companyFilter,
            'search' => $crud->search,
            'filters' => array_filter($crud->filters, fn ($v) => ! self::isEmptyState($v)),
            'dateRanges' => array_filter($crud->dateRanges, fn ($v) => ! self::isEmptyState($v)),
            'quickDateFilter' => $crud->quickDateFilter,
            'lockedFilters' => $crud->lockedFilters,
        ];
    }

    private static function clear(BaseCrud $crud): void
    {
        $crud->showTrashed = false;
        $crud->companyFilter = 0;
        $crud->search = '';
        $crud->filters = [];
        $crud->dateRanges = [];
        $crud->quickDateFilter = '';
    }

    private static function isEmptyState(mixed $v): bool
    {
        return $v === '' || $v === null || $v === [] || $v === false || $v === 0;
    }

    private static function describe(string $key, mixed $value, BaseCrud $crud, Model $model): string
    {
        return match ($key) {
            'showTrashed' => 'only deleted records are listed (trash view saved in preferences)',
            'companyFilter' => ($crud->crudConfig['companyField'] ?? 'company_id')." = {$value} (the user's active company)",
            'search' => "\"{$value}\" (saved search)",
            'filters' => self::pairs($value).' (saved or URL filters)',
            'dateRanges' => self::pairs($value),
            'quickDateFilter' => "{$value} on ".$crud->quickDateColumn,
            default => '',
        };
    }

    private static function baseNote(BaseCrud $crud, Model $model): string
    {
        $parts = [];

        if ($crud->lockedFilters !== []) {
            $parts[] = 'locked '.self::pairs($crud->lockedFilters);
        }
        if ($crud->whereHasFilter !== '') {
            $parts[] = "whereHas {$crud->whereHasFilter}";
        }
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $parts[] = 'soft deletes hidden';
        }

        return $parts === [] ? 'nothing extra' : implode('; ', $parts);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function pairs(array $values): string
    {
        return implode(', ', array_map(
            fn ($k, $v) => $k.'='.(is_scalar($v) ? var_export($v, true) : json_encode($v)),
            array_keys($values),
            $values
        ));
    }

    private static function safeCount(callable $query): ?int
    {
        try {
            return (int) $query()->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function sql(Builder $query): string
    {
        $bindings = array_map(
            fn ($b) => is_numeric($b) ? (string) $b : "'".str_replace("'", "''", (string) $b)."'",
            $query->getBindings()
        );

        return self::oneLine(Str::replaceArray('?', $bindings, $query->toSql()));
    }

    private static function oneLine(string $s): string
    {
        $s = trim((string) preg_replace('/\s+/', ' ', $s));

        return mb_strlen($s) > 400 ? mb_substr($s, 0, 400).'…' : $s;
    }
}

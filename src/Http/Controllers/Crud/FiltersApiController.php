<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Crud;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;
use Ptah\Support\Scalar;

/**
 * The filter panel of the BaseCrud for the React and Vue stacks (1.59.0).
 *
 * The listing itself already takes the panel's state (filters, operators,
 * date ranges and their operators, quick date — CrudRuntime::applyState());
 * this controller serves what the panel needs around it:
 *  - meta: how to draw each filter (_filter-panel.blade.php decides it from
 *    the raw config, which never leaves the server);
 *  - url: the link filters (`?f[...]`) as the engine accepted them — the
 *    banner of _url-filters-banner.blade.php;
 *  - by-cell: "filter by this value" (filterByCell(), the cell button);
 *  - labels: the chosen label of a searchdropdown filter, kept in the same
 *    preferences the Livewire screen keeps it in (sdFilterLabels).
 *
 * Every answer is built from named keys; the screen's permission to read is
 * checked by the resolver, the rest by the engine's own methods.
 */
class FiltersApiController extends Controller
{
    /** The periods of the quick date buttons, in the panel's order (getQuickDateRange()). */
    public const QUICK_PERIODS = ['today', 'yesterday', 'last7', 'last30', 'week', 'month', 'lastMonth', 'quarter', 'year'];

    /** The operator selects of _filter-panel.blade.php, per kind of filter. */
    public const OPERATORS = [
        'text' => ['LIKE', '=', '!=', 'LIKE_START', 'LIKE_END', 'IS NULL', 'IS NOT NULL'],
        'number' => ['=', '!=', '>', '>=', '<', '<=', 'IS NULL', 'IS NOT NULL'],
        'searchdropdown' => ['=', '!=', 'IS NULL', 'IS NOT NULL'],
    ];

    /** The "from" and "to" operator selects of a date filter. */
    public const DATE_OPERATORS = ['start' => ['>=', '>', '='], 'end' => ['<=', '<', '=']];

    /**
     * What an advanced search row may compare with, per kind of field. Every
     * one is in FiltersRuntime::FILTERS_ADVANCED_OPERATORS, which the listing
     * enforces.
     */
    public const ADVANCED_OPERATORS = [
        'text' => ['LIKE', 'NOT LIKE', '=', '!=', 'LIKE_START', 'LIKE_END'],
        'number' => ['=', '!=', '>', '>=', '<', '<='],
        'date' => ['=', '!=', '>', '>=', '<', '<='],
    ];

    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    /** How to draw the panel for this screen and user. */
    public function meta(Request $request, string $screen): JsonResponse
    {
        // restore: the labels of the searchdropdown filters live in the
        // user's preferences, with the rest of the filter state.
        $crud = $this->resolver->resolve($request, $screen, restore: true);
        $schema = $crud->schema();

        $filters = $this->filterDefinitions($crud, $schema);
        $columns = array_values(array_filter($filters, fn (array $f) => ! $f['custom']));
        $dropdowns = array_column(array_filter($columns, fn (array $f) => $f['kind'] === 'searchdropdown'), 'field');

        $labels = [];
        foreach ($crud->sdFilterLabels as $field => $label) {
            if (! in_array($field, $dropdowns, true)) {
                continue;
            }
            if (is_scalar($label) && (string) $label !== '') {
                $labels[(string) $field] = (string) $label;
            } elseif (is_array($label) && $label !== []) {
                // A multiple filter (1.63.0): value => label.
                $labels[(string) $field] = (object) array_map('strval', array_filter($label, 'is_scalar'));
            }
        }

        return response()->json([
            'filters' => $filters,
            'sentinels' => ['empty' => CrudRuntime::FILTER_EMPTY, 'filled' => CrudRuntime::FILTER_FILLED],
            'quick_dates' => [
                // The panel offers the shortcuts only on a screen with a date filter.
                'enabled' => (bool) ($schema['features']['quickDates'] ?? false)
                    && array_filter($columns, fn (array $f) => $f['kind'] === 'date') !== [],
                'column' => $crud->quickDateColumn,
                'periods' => self::QUICK_PERIODS,
            ],
            'advanced' => [
                'enabled' => (bool) ($schema['features']['advancedSearch'] ?? false),
                'fields' => array_map(fn (array $f) => [
                    'field' => $f['field'],
                    'label' => $f['label'],
                    'kind' => match ($f['type']) {
                        'number' => 'number',
                        'date', 'datetime' => 'date',
                        default => 'text',
                    },
                ], $columns),
                'operators' => self::ADVANCED_OPERATORS,
            ],
            // The columns whose cells offer "filter by this value".
            'cell_fields' => array_column($columns, 'field'),
            'labels' => (object) $labels,
        ]);
    }

    /** The link filters (`?f[...]`) the engine accepted, for the banner. */
    public function url(Request $request, string $screen): JsonResponse
    {
        // mount() already read `?f` from this request (captureUrlFilters()).
        $crud = $this->resolver->resolve($request, $screen);
        $labels = $this->columnLabels($crud);

        $data = [];
        foreach ($crud->urlFilters as $field => $spec) {
            $items = is_array($spec['val'] ?? null) ? $spec['val'] : [$spec['val'] ?? ''];
            $data[] = [
                'field' => (string) $field,
                'label' => $labels[(string) $field] ?? (string) $field,
                'op' => (string) ($spec['op'] ?? '='),
                'value' => implode(', ', array_map('strval', array_filter($items, 'is_scalar'))),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * "Filter by this value": the engine reads the value from the RECORD (not
     * the formatted cell) and sets the filter of that column. The record is
     * looked up through the listing's own query — with the request's state,
     * the screen's locks, scope and company — so a row outside it changes
     * nothing (422).
     */
    public function byCell(Request $request, string $screen): JsonResponse
    {
        $field = $request->input('field');
        $row = $request->input('row');

        if (! is_string($field) || $field === '' || ! (is_int($row) || (is_string($row) && $row !== ''))) {
            return response()->json(['ok' => false, 'errors' => ['field' => 'required', 'row' => 'required']], 422);
        }

        $crud = $this->resolver->resolve($request, $screen);
        $state = $request->input('state', []);
        $crud->applyState(is_array($state) ? $state : []);

        // The field's own filter is what is being replaced: without it here,
        // its presence (applied or not) could not tell whether the engine
        // accepted the cell.
        unset($crud->filters[$field], $crud->filterOperators[$field], $crud->dateRanges[$field.'_start'], $crud->dateRanges[$field.'_end']);

        $crud->filterByCell($row, $field);

        $applied = array_key_exists($field, $crud->filters)
            || array_key_exists($field, $crud->filterOperators)
            || array_key_exists($field.'_start', $crud->dateRanges);

        if (! $applied) {
            return response()->json(['ok' => false, 'state' => null], 422);
        }

        $now = $crud->state();

        return response()->json([
            'ok' => true,
            'state' => [
                'filters' => $now['filters'],
                'operators' => $now['operators'],
                'date_ranges' => $now['date_ranges'],
                'date_operators' => $now['date_operators'],
            ],
        ]);
    }

    /**
     * Keeps the label chosen in a searchdropdown filter, so the panel shows
     * it when the screen is reopened (sdFilterLabels, as on Livewire).
     * `replace` sets the whole map (the panel was cleared); otherwise only the
     * fields sent change, null forgets one. `state` is the listing state the
     * label belongs to — saved with it, so this write and the listing's own
     * do not overwrite each other with an older state.
     */
    public function labels(Request $request, string $screen): JsonResponse
    {
        $labels = $request->input('labels', []);
        if (! is_array($labels)) {
            return response()->json(['ok' => false, 'errors' => ['labels' => 'invalid']], 422);
        }

        $crud = $this->resolver->resolve($request, $screen, restore: true);
        $definitions = array_filter(
            $this->filterDefinitions($crud, $crud->schema()),
            fn (array $f) => ! $f['custom'] && $f['kind'] === 'searchdropdown'
        );
        $dropdowns = array_column($definitions, 'field');
        $multiple = array_column(array_filter($definitions, fn (array $f) => ($f['multiple'] ?? false) === true), 'field');

        $errors = [];
        foreach ($labels as $field => $label) {
            if (! is_string($field) || ! in_array($field, $dropdowns, true)) {
                $errors[(string) $field] = 'unknown';
            } elseif (is_array($label)) {
                // A multiple filter (1.63.0): value => label, bounded. A filter
                // of one value keeps one text.
                if (! in_array($field, $multiple, true) || count($label) > CrudRuntime::FILTER_LIST_MAX) {
                    $errors[$field] = 'invalid';
                } else {
                    foreach ($label as $v => $l) {
                        if (! is_scalar($l) || mb_strlen((string) $l) > 255 || mb_strlen((string) $v) > 255) {
                            $errors[$field] = 'invalid';
                            break;
                        }
                    }
                }
            } elseif ($label !== null && (! is_scalar($label) || mb_strlen((string) $label) > 255)) {
                $errors[$field] = 'invalid';
            }
        }

        if ($errors !== []) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }

        $current = $request->boolean('replace') ? [] : $crud->sdFilterLabels;
        foreach ($labels as $field => $label) {
            if (is_array($label)) {
                if ($label === []) {
                    unset($current[$field]);
                } else {
                    $current[$field] = array_map('strval', $label);
                }
            } elseif ($label === null || (string) $label === '') {
                unset($current[$field]);
            } else {
                $current[$field] = (string) $label;
            }
        }
        $crud->sdFilterLabels = $current;

        $state = $request->input('state');
        $crud->remember();
        is_array($state) ? $crud->applyState($state) : $crud->savePreferences();

        return response()->json(['ok' => true, 'labels' => (object) array_intersect_key($current, array_flip($dropdowns))]);
    }

    /**
     * The filters of the panel, in its order: the filterable columns, then
     * the custom filters — the same two loops as _filter-panel.blade.php.
     * Only columns the schema exposes as filters (it already drops the
     * model's `$hidden` attributes).
     *
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    private function filterDefinitions(CrudRuntime $crud, array $schema): array
    {
        $exposed = array_column((array) ($schema['filters'] ?? []), 'field');
        $out = [];
        $hidden = [];

        foreach ($crud->crudConfig['cols'] ?? [] as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            $type = (string) ($col['colsTipo'] ?? 'text');

            if ($field === '' || $type === 'action' || ! in_array($col['colsIsFilterable'] ?? false, [true, 'S', 1, '1'], true)) {
                continue;
            }
            if (! in_array($field, $exposed, true)) {
                $hidden[] = $field;

                continue;
            }

            $options = self::options($col['colsSelect'] ?? null);
            $kind = match (true) {
                $type === 'date' => 'date',
                $type === 'select' && $options !== null => 'select',
                $type === 'searchdropdown' => 'searchdropdown',
                $type === 'number' => 'number',
                default => 'text',
            };
            $dropdown = $kind === 'searchdropdown';

            $out[] = [
                'field' => $field,
                'label' => (string) ($col['colsNomeLogico'] ?? $field),
                'type' => $type,
                'kind' => $kind,
                'custom' => false,
                'operators' => self::OPERATORS[$kind] ?? [],
                'date_operators' => $kind === 'date' ? self::DATE_OPERATORS : null,
                'options' => $kind === 'select' ? $options : null,
                'depends_on' => $dropdown && is_string($col['colsSDDependsOn'] ?? null) && $col['colsSDDependsOn'] !== '' ? $col['colsSDDependsOn'] : null,
                'placeholder' => $dropdown && is_string($col['colsSDPlaceholder'] ?? null) && $col['colsSDPlaceholder'] !== '' ? $col['colsSDPlaceholder'] : null,
                'start_list' => $dropdown ? (($col['colsSDStartList'] ?? 'bottom') === 'top' ? 'top' : 'bottom') : null,
                // Money kept in cents (1.62.0): typed in reais, so a text input
                // that takes "2.160,00" — the server converts.
                'money_cents' => ($col['colsRenderer'] ?? '') === 'money' && filter_var($col['colsRendererCents'] ?? false, FILTER_VALIDATE_BOOLEAN),
                // More than one value at once (colsFilterMultiple, 1.63.0): select
                // and searchdropdown filters keep a list, read as IN / NOT IN.
                'multiple' => $crud->filterAllowsMultiple($col),
            ];
        }

        foreach ($crud->crudConfig['customFilters'] ?? [] as $cf) {
            $field = is_array($cf) ? (string) ($cf['field'] ?? '') : '';
            if ($field === '' || in_array($field, $hidden, true)) {
                continue;
            }

            // A custom `searchdropdown` is drawn as the text filter since
            // 1.41.5 (see the panel); a date is ONE date, not a range.
            $type = (string) ($cf['colsFilterType'] ?? 'text');
            $options = self::options($cf['colsSelect'] ?? null);
            $kind = match (true) {
                $type === 'date' => 'date',
                $type === 'number' => 'number',
                $type === 'select' && $options !== null => 'select',
                default => 'text',
            };

            $out[] = [
                'field' => $field,
                'label' => (string) ($cf['label'] ?? $field),
                'type' => $type,
                'kind' => $kind,
                'custom' => true,
                'operators' => [],
                'date_operators' => null,
                'options' => $kind === 'select' ? $options : null,
                'depends_on' => null,
                'placeholder' => null,
                'start_list' => null,
                'money_cents' => false,
                'multiple' => false,
            ];
        }

        return $out;
    }

    /** @return array<string, string> field => label of the configured columns */
    private function columnLabels(CrudRuntime $crud): array
    {
        $labels = [];
        foreach ($crud->crudConfig['cols'] ?? [] as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            if ($field !== '') {
                $labels[$field] = (string) ($col['colsNomeLogico'] ?? $field);
            }
        }

        return $labels;
    }

    /**
     * `colsSelect` (label => value) as an ordered list — the shape of the
     * schema's options.
     *
     * @return list<array{label: string, value: string}>|null
     */
    private static function options(mixed $select): ?array
    {
        if (! is_array($select) || $select === []) {
            return null;
        }

        $out = [];
        foreach ($select as $label => $value) {
            $out[] = ['label' => (string) $label, 'value' => Scalar::text($value)];
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Carbon\Carbon;
use Ptah\DTO\FilterDTO;

/**
 * Handles all filter-related functionality: sort, search, date filters,
 * advanced search, search history, named filters and filter badge summary.
 */
trait HasCrudFilters
{
    /**
     * Operators accepted from the URL query string (?f[field][op]=...). Anything
     * outside this allowlist falls back to '=' — matches what the filter
     * strategies (src/Services/Crud/Filters/*Strategy.php) actually support.
     */
    private const URL_FILTER_OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN'];
    // ── Table sorting ──────────────────────────────────────────────────────────

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->sort = $column;
            $this->direction = 'ASC';
        }

        $this->resetPage();
        $this->savePreferences();
    }

    // ── Watchers ───────────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function updatedFilters(): void
    {
        // Touching the filter panel discards any active URL filters — the
        // panel takes over from that point on (URL filters are not persisted).
        if (! empty($this->urlFilters)) {
            $this->urlFilters = [];
        }
        $this->resetPage();
        $this->buildTextFilter();
        $this->savePreferences();
    }

    public function updatedFilterOperators(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function updatedDateRanges(): void
    {
        if (! empty($this->urlFilters)) {
            $this->urlFilters = [];
        }
        $this->resetPage();
        $this->buildTextFilter();
        $this->savePreferences();
    }

    public function updatedDateRangeOperators(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    // ── Filter panel ───────────────────────────────────────────────────────────

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearFilters(): void
    {
        if (! empty($this->urlFilters)) {
            $this->urlFilters = [];
        }
        $this->filters = [];
        $this->filterOperators = [];
        $this->dateRanges = [];
        $this->dateRangeOperators = [];
        $this->sdSearches = [];
        $this->sdResults = [];
        $this->sdLabels = [];
        $this->sdFilterLabels = [];
        $this->quickDateFilter = '';
        $this->textFilter = [];
        $this->advancedSearchActive = false;
        $this->search = '';
        $this->resetPage();
        $this->savePreferences();
    }

    public function setViewDensity(string $density): void
    {
        $allowed = ['compact', 'comfortable', 'spacious'];
        if (! in_array($density, $allowed, true)) {
            return;
        }
        $this->viewDensity = $density;
        $this->savePreferences();
    }

    /**
     * Switches between the table and the card (mosaic) listing.
     */
    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['table', 'cards'], true)) {
            return;
        }
        $this->viewMode = $mode;
        $this->savePreferences();
    }

    // ── Active filter badge summary ────────────────────────────────────────────

    /**
     * Builds the badge array for active filter display.
     * Called whenever filters / dateRanges / quickDateFilter changes.
     */
    public function buildTextFilter(): void
    {
        $badges = [];
        $cols = $this->crudConfig['cols'] ?? [];

        // Map field → label
        $labelMap = [];
        foreach ($cols as $col) {
            $labelMap[$col['colsNomeFisico'] ?? ''] = $col['colsNomeLogico'] ?? $col['colsNomeFisico'] ?? '';
        }

        foreach ($this->filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $label = $labelMap[$field] ?? $field;
            $badges[] = ['label' => $label, 'field' => $field, 'value' => $value];
        }

        foreach ($this->dateRanges as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Determine base field (_start/_end/_from/_to)
            $field = preg_replace('/_(start|end|from|to)$/', '', $key);
            $label = $labelMap[$field] ?? $field;
            $suffix = str_ends_with($key, '_start') || str_ends_with($key, '_from')
                ? trans('ptah::ui.date_range_from_label')
                : trans('ptah::ui.date_range_to_label');
            $badges[] = ['label' => "{$label} {$suffix}", 'field' => $key, 'value' => $value];
        }

        if ($this->quickDateFilter !== '') {
            $labels = [
                'today' => trans('ptah::ui.date_today'),
                'yesterday' => trans('ptah::ui.date_yesterday'),
                'last7' => trans('ptah::ui.date_last7'),
                'last30' => trans('ptah::ui.date_last30'),
                'week' => trans('ptah::ui.date_week'),
                'month' => trans('ptah::ui.date_month'),
                'lastMonth' => trans('ptah::ui.date_last_month'),
                'quarter' => trans('ptah::ui.date_quarter'),
                'year' => trans('ptah::ui.date_year'),
            ];
            $badges[] = [
                'label' => trans('ptah::ui.filter_period_label'),
                'field' => 'quickDate',
                'value' => $labels[$this->quickDateFilter] ?? $this->quickDateFilter,
            ];
        }

        $this->textFilter = $badges;
    }

    public function removeTextFilterBadge(string $field): void
    {
        // Check if it is a date range key
        if (preg_match('/_(start|end|from|to)$/', $field)) {
            unset($this->dateRanges[$field]);
        } elseif ($field === 'quickDate') {
            $this->quickDateFilter = '';
        } else {
            unset($this->filters[$field]);
        }

        $this->buildTextFilter();
        $this->resetPage();
    }

    // ── Quick date filter ──────────────────────────────────────────────────────

    public function applyQuickDateFilter(string $period): void
    {
        if (! empty($this->urlFilters)) {
            $this->urlFilters = [];
        }
        $this->quickDateFilter = ($this->quickDateFilter === $period) ? '' : $period;
        $this->resetPage();
        $this->buildTextFilter();
        $this->savePreferences();
    }

    public function updatedQuickDateFilter(): void
    {
        $this->resetPage();
        $this->buildTextFilter();
    }

    /**
     * Returns [from, to] date strings for the selected period.
     *
     * @return array{0: string, 1: string}
     */
    protected function getQuickDateRange(string $period): array
    {
        $now = Carbon::now();
        $copy = $now->copy();

        return match ($period) {
            'today' => [$now->startOfDay()->toDateTimeString(),             $copy->endOfDay()->toDateTimeString()],
            'yesterday' => [$now->subDay()->startOfDay()->toDateTimeString(),   $now->copy()->endOfDay()->toDateTimeString()],
            'last7' => [$now->subDays(7)->startOfDay()->toDateTimeString(), $copy->endOfDay()->toDateTimeString()],
            'last30' => [$now->subDays(30)->startOfDay()->toDateTimeString(), $copy->endOfDay()->toDateTimeString()],
            'week' => [$now->startOfWeek()->toDateTimeString(),            $copy->endOfWeek()->toDateTimeString()],
            'month' => [$now->startOfMonth()->toDateTimeString(),           $copy->endOfMonth()->toDateTimeString()],
            'lastMonth' => [$now->subMonth()->startOfMonth()->toDateTimeString(), $now->copy()->endOfMonth()->toDateTimeString()],
            'quarter' => [$now->startOfQuarter()->toDateTimeString(),         $copy->endOfQuarter()->toDateTimeString()],
            'year' => [$now->startOfYear()->toDateTimeString(),            $copy->endOfYear()->toDateTimeString()],
            default => ['', ''],
        };
    }

    // ── Advanced search ────────────────────────────────────────────────────────

    public function toggleAdvancedSearch(): void
    {
        $this->advancedSearchActive = ! $this->advancedSearchActive;

        if (! $this->advancedSearchActive) {
            $this->advancedSearchFields = [];
        }

        $this->savePreferences();
    }

    public function addAdvancedSearchField(string $field, string $operator, mixed $value, string $logic = 'AND'): void
    {
        $this->advancedSearchFields[] = compact('field', 'operator', 'value', 'logic');
        $this->resetPage();
        $this->buildTextFilter();
    }

    public function removeAdvancedSearchField(int $index): void
    {
        array_splice($this->advancedSearchFields, $index, 1);
        $this->resetPage();
        $this->buildTextFilter();
    }

    // ── Search history ─────────────────────────────────────────────────────────

    protected function addToSearchHistory(string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        // Remove duplicates and put at beginning
        $this->searchHistory = array_values(array_filter(
            $this->searchHistory,
            fn ($t) => $t !== $term
        ));

        array_unshift($this->searchHistory, $term);

        // Limit to 10 entries
        $this->searchHistory = array_slice($this->searchHistory, 0, 10);
    }

    public function clearSearchHistory(): void
    {
        $this->searchHistory = [];
        $this->savePreferences();
    }

    // ── Named filters ──────────────────────────────────────────────────────────

    public function saveNamedFilter(string $name): void
    {
        if ($name === '') {
            return;
        }

        $this->savedFilters[$name] = array_filter($this->filters);
        $this->savingFilterName = null;
        $this->savePreferences();
    }

    public function loadNamedFilter(string $name): void
    {
        if (isset($this->savedFilters[$name])) {
            $this->filters = $this->savedFilters[$name];
            $this->resetPage();
        }
    }

    public function deleteNamedFilter(string $name): void
    {
        unset($this->savedFilters[$name]);
        $this->savePreferences();
    }

    // ── URL filters (?f[field]=value) ──────────────────────────────────────────

    /**
     * Reads `?f[...]` from the current request and normalises it into
     * `$this->urlFilters` — a `[field => ['op' => string, 'val' => mixed]]` map.
     *
     * Supported query formats:
     *   - `f[field]=value`                      → operator `=`
     *   - `f[field][op]=LIKE&f[field][val]=x`    → explicit operator
     *   - `f[field][]=1&f[field][]=2`            → list, operator `IN`
     *
     * Fields not present in `allowedFilterFields()` are silently ignored, and
     * operators outside the allowlist fall back to `=`. Never persisted (not
     * part of savePreferences()) and always overridden the moment the user
     * touches the filter panel (see updatedFilters()/clearFilters()/etc.).
     */
    protected function captureUrlFilters(): void
    {
        $raw = request()->query('f', []);

        if (! is_array($raw) || empty($raw)) {
            $this->urlFilters = [];

            return;
        }

        $allowed = $this->allowedFilterFields();
        $captured = [];

        foreach ($raw as $field => $spec) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                continue;
            }

            if (is_array($spec) && array_key_exists('val', $spec)) {
                // Explicit operator: f[field][op]=...&f[field][val]=...
                $op = is_string($spec['op'] ?? null) ? strtoupper(trim($spec['op'])) : '=';
                $val = $spec['val'];

                // val must be a scalar or a flat list of scalars. A smuggled
                // nested structure (e.g. ?f[field][val][][sub]=1) would reach
                // whereIn()/whereBetween() or the banner's implode() as a
                // non-scalar and blow up the request — discard the whole
                // filter for this field instead.
                if (is_array($val) && array_filter($val, fn ($v) => ! is_scalar($v)) !== []) {
                    continue;
                }
            } elseif (is_array($spec)) {
                // Plain list: f[field][]=1&f[field][]=2 → IN. Non-scalar items
                // (e.g. a smuggled ?f[field][][sub]=1) are dropped individually;
                // the rest of the list is still usable.
                $op = 'IN';
                $val = array_values(array_filter($spec, 'is_scalar'));
            } else {
                $op = '=';
                $val = $spec;
            }

            if (! in_array($op, self::URL_FILTER_OPERATORS, true)) {
                $op = '=';
            }

            // BETWEEN as a CSV string ("10,50") — same convention already
            // accepted by FilterService::processDateRangeFilters()/strategies.
            if ($op === 'BETWEEN' && is_string($val) && str_contains($val, ',')) {
                $val = array_map('trim', explode(',', $val, 2));
            }

            if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                continue;
            }

            $captured[$field] = ['op' => $op, 'val' => $val];
        }

        $this->urlFilters = $captured;
    }

    /**
     * Whitelist of fields that may be driven by URL filters: CRUD columns
     * flagged colsIsFilterable (same notion of "filterable" the panel itself
     * uses — see _filter-panel.blade.php / CrudConfig::filterableCols()) plus
     * custom filter fields (field / colRelation), which are always filterable
     * by definition.
     *
     * @return string[]
     */
    protected function allowedFilterFields(): array
    {
        $fields = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (! empty($col['colsNomeFisico']) && $this->ptahBool($col['colsIsFilterable'] ?? false)) {
                $fields[] = $col['colsNomeFisico'];
            }
        }

        foreach ($this->crudConfig['customFilters'] ?? [] as $cf) {
            if (! empty($cf['field'])) {
                $fields[] = $cf['field'];
            }
            if (! empty($cf['colRelation'])) {
                $fields[] = $cf['colRelation'];
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * Converts the captured `$this->urlFilters` into FilterDTO[], ready for
     * FilterService::applyFilters() — same DTO/service used by the normal
     * filter panel flow (buildActiveFilters()).
     *
     * @return FilterDTO[]
     */
    protected function buildUrlFilterDtos(): array
    {
        $dtos = [];

        foreach ($this->urlFilters as $field => $spec) {
            $op = $spec['op'];
            $val = $spec['val'];
            $sample = is_array($val) ? ($val[0] ?? '') : $val;

            // IN / NOT IN always go through the array strategy. Everything
            // else is resolved from the real column config.
            $type = in_array($op, ['IN', 'NOT IN'], true)
                ? 'array'
                : $this->resolveUrlFilterType((string) $field, $sample);

            // BETWEEN only has a real implementation in Numeric/DateFilterStrategy
            // (both explicitly whereBetween() an array value). Every other type
            // (text, boolean, …) falls through to TextFilterStrategy's default
            // `$query->where($field, $operator, $value)` — and 'BETWEEN' is NOT
            // one of Laravel's recognised where() operators, so the query
            // builder silently swaps it: `where($field, '=', 'BETWEEN')`. That
            // is a silent wrong-result bug (0 rows, no exception), not merely an
            // unsupported operator — discard the filter instead of risking it.
            if ($op === 'BETWEEN' && ! in_array($type, ['number', 'date', 'datetime'], true)) {
                continue;
            }

            $dtos[] = new FilterDTO(field: (string) $field, value: $val, operator: $op, type: $type);
        }

        return $dtos;
    }

    /**
     * Resolves the FilterDTO type for a URL-filtered field from the REAL
     * column config — same source of truth buildActiveFilters() uses via
     * findColByField() — so the type is never guessed from the field's name.
     *
     * Relation columns (colsRelacao + colsRelacaoExibe — the same pair
     * buildActiveFilters() checks, regardless of colsTipo: `searchdropdown`,
     * `select`, or any future relation widget) are always resolved to
     * 'number': the URL passes the FK id, which is filtered directly on the
     * raw numeric column — never a whereHas() text search. This also covers
     * the "_id" naming heuristic bug: a PLAIN numeric "_id" column
     * (colsTipo=number, no colsRelacao) is not a relation and correctly stays
     * 'number' via the colsTipo branch below.
     *
     * Falls back to FilterDTO::inferType()'s naming heuristic only when the
     * field has no column config at all (e.g. a customFilters-only field),
     * sampling the first element for BETWEEN's [from, to] pairs so it is not
     * mistaken for an IN list.
     *
     * colsTipo values found in the package (docs/Configuration.md,
     * CrudConfigGenerator): text, textarea, number, date, datetime, select,
     * searchdropdown, boolean, file, image (+ 'action', never filterable).
     * Only number/date/datetime map to a type-specific strategy; everything
     * else (textarea, select, file, image — genuinely textual/opaque data,
     * not a relation) falls back to 'text', which buildUrlFilterDtos() then
     * guards against an incompatible BETWEEN.
     */
    protected function resolveUrlFilterType(string $field, mixed $sample): string
    {
        $col = $this->findColByField($field);

        if (! $col) {
            return FilterDTO::inferType($field, $sample);
        }

        if (! empty($col['colsRelacao']) && ! empty($col['colsRelacaoExibe'])) {
            return 'number';
        }

        return match ($col['colsTipo'] ?? 'text') {
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'datetime',
            'boolean' => 'boolean',
            default => 'text',
        };
    }

    /**
     * Discards the active URL filters (banner "Clear" button). Preferences
     * are untouched since URL filters were never written to them.
     */
    public function clearUrlFilters(): void
    {
        $this->urlFilters = [];
        $this->resetPage();
    }
}

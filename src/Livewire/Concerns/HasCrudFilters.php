<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

use Carbon\Carbon;

/**
 * Handles all filter-related functionality: sort, search, date filters,
 * advanced search, search history, named filters and filter badge summary.
 */
trait HasCrudFilters
{
    // ── Table sorting ──────────────────────────────────────────────────────────

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->sort      = $column;
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
        $this->filters              = [];
        $this->filterOperators      = [];
        $this->dateRanges           = [];
        $this->dateRangeOperators   = [];
        $this->sdSearches           = [];
        $this->sdResults            = [];
        $this->sdLabels             = [];
        $this->sdFilterLabels       = [];
        $this->quickDateFilter      = '';
        $this->textFilter           = [];
        $this->advancedSearchActive = false;
        $this->search               = '';
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

    // ── Active filter badge summary ────────────────────────────────────────────

    /**
     * Builds the badge array for active filter display.
     * Called whenever filters / dateRanges / quickDateFilter changes.
     */
    public function buildTextFilter(): void
    {
        $badges = [];
        $cols   = $this->crudConfig['cols'] ?? [];

        // Map field → label
        $labelMap = [];
        foreach ($cols as $col) {
            $labelMap[$col['colsNomeFisico'] ?? ''] = $col['colsNomeLogico'] ?? $col['colsNomeFisico'] ?? '';
        }

        foreach ($this->filters as $field => $value) {
            if ($value === null || $value === '') continue;
            $label    = $labelMap[$field] ?? $field;
            $badges[] = ['label' => $label, 'field' => $field, 'value' => $value];
        }

        foreach ($this->dateRanges as $key => $value) {
            if ($value === null || $value === '') continue;

            // Determine base field (_start/_end/_from/_to)
            $field  = preg_replace('/_(start|end|from|to)$/', '', $key);
            $label  = $labelMap[$field] ?? $field;
            $suffix = str_ends_with($key, '_start') || str_ends_with($key, '_from')
                ? trans('ptah::ui.date_range_from_label')
                : trans('ptah::ui.date_range_to_label');
            $badges[] = ['label' => "{$label} {$suffix}", 'field' => $key, 'value' => $value];
        }

        if ($this->quickDateFilter !== '') {
            $labels = [
                'today'     => trans('ptah::ui.date_today'),
                'yesterday' => trans('ptah::ui.date_yesterday'),
                'last7'     => trans('ptah::ui.date_last7'),
                'last30'    => trans('ptah::ui.date_last30'),
                'week'      => trans('ptah::ui.date_week'),
                'month'     => trans('ptah::ui.date_month'),
                'lastMonth' => trans('ptah::ui.date_last_month'),
                'quarter'   => trans('ptah::ui.date_quarter'),
                'year'      => trans('ptah::ui.date_year'),
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
        $now  = Carbon::now();
        $copy = $now->copy();

        return match ($period) {
            'today'     => [$now->startOfDay()->toDateTimeString(),             $copy->endOfDay()->toDateTimeString()],
            'yesterday' => [$now->subDay()->startOfDay()->toDateTimeString(),   $now->copy()->endOfDay()->toDateTimeString()],
            'last7'     => [$now->subDays(7)->startOfDay()->toDateTimeString(), $copy->endOfDay()->toDateTimeString()],
            'last30'    => [$now->subDays(30)->startOfDay()->toDateTimeString(),$copy->endOfDay()->toDateTimeString()],
            'week'      => [$now->startOfWeek()->toDateTimeString(),            $copy->endOfWeek()->toDateTimeString()],
            'month'     => [$now->startOfMonth()->toDateTimeString(),           $copy->endOfMonth()->toDateTimeString()],
            'lastMonth' => [$now->subMonth()->startOfMonth()->toDateTimeString(),$now->copy()->endOfMonth()->toDateTimeString()],
            'quarter'   => [$now->startOfQuarter()->toDateTimeString(),         $copy->endOfQuarter()->toDateTimeString()],
            'year'      => [$now->startOfYear()->toDateTimeString(),            $copy->endOfYear()->toDateTimeString()],
            default     => ['', ''],
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
            fn($t) => $t !== $term
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
        $this->savingFilterName    = null;
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
}

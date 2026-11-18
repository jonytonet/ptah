<?php

declare(strict_types=1);

namespace Ptah\Crud\Concerns;

/**
 * The part of the filter panel that must travel inside the listing request
 * itself (1.59.0): the advanced search rows, the link filters (`?f[...]`) and
 * the search history. Used by CrudRuntime — `applyStateNow()` calls
 * `applyFiltersState()` and `state()` adds `filtersState()`.
 *
 * Nothing here filters by itself: each value goes through the engine's own
 * method (addAdvancedSearchField(), captureUrlFilters(), addToSearchHistory())
 * and the query still runs buildActiveFilters()/buildUrlFilterDtos(), which
 * re-check every field against the screen. The checks below only stop what
 * can never be a row of the panel before it reaches the engine.
 */
trait FiltersRuntime
{
    /**
     * Operators an advanced search row may carry — what the strategies of
     * FilterService implement for a single value. Kept in step with
     * FiltersApiController::ADVANCED_OPERATORS (what the panel offers).
     */
    private const FILTERS_ADVANCED_OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'LIKE_START', 'LIKE_END'];

    /** More rows than any person builds by hand: the rest is dropped. */
    private const FILTERS_ADVANCED_MAX_ROWS = 20;

    /**
     * Applies `advanced`, `f` and records the search — after the rest of the
     * listing state, because updatedFilters() discards the link filters and
     * the request may carry them on purpose.
     *
     * @param  array<string, mixed>  $input
     */
    protected function applyFiltersState(array $input): void
    {
        if (array_key_exists('advanced', $input)) {
            $this->applyAdvancedRows(is_array($input['advanced']) ? $input['advanced'] : []);
        }

        if (array_key_exists('f', $input)) {
            $this->applyLinkFilters(is_array($input['f']) ? $input['f'] : []);
        }

        // The Livewire screen records the term when it renders the listing
        // (rows()); here the preferences are written before the listing runs,
        // so the term is recorded now or it never reaches the history.
        if (isset($input['search']) && $this->search !== '') {
            $this->addToSearchHistory($this->search);
        }
    }

    /**
     * What the client mirrors: the advanced rows in effect and the link
     * filters as the engine understood them (op/val already normalised).
     *
     * @return array<string, mixed>
     */
    protected function filtersState(): array
    {
        $rows = [];
        if ($this->advancedSearchActive) {
            foreach ($this->advancedSearchFields as $row) {
                if (is_array($row)) {
                    $rows[] = [
                        'field' => (string) ($row['field'] ?? ''),
                        'operator' => (string) ($row['operator'] ?? '='),
                        'value' => is_scalar($row['value'] ?? null) ? (string) $row['value'] : '',
                        'logic' => ($row['logic'] ?? 'AND') === 'OR' ? 'OR' : 'AND',
                    ];
                }
            }
        }

        return [
            'advanced' => $rows,
            'f' => (object) $this->urlFilters,
        ];
    }

    /** @param  array<array-key, mixed>  $rows */
    private function applyAdvancedRows(array $rows): void
    {
        $this->advancedSearchFields = [];

        foreach (array_slice(array_values($rows), 0, self::FILTERS_ADVANCED_MAX_ROWS) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = $row['field'] ?? null;
            $operator = is_string($row['operator'] ?? null) ? strtoupper(trim($row['operator'])) : '=';
            $value = $row['value'] ?? null;
            $logic = is_string($row['logic'] ?? null) && strtoupper($row['logic']) === 'OR' ? 'OR' : 'AND';

            // A forged operator never reaches a strategy (they pass unknown
            // ones to where() as given); a field outside the screen or denied
            // to this user is dropped here and again by buildActiveFilters().
            if (! is_string($field) || ! in_array($operator, self::FILTERS_ADVANCED_OPERATORS, true)
                || ! is_scalar($value) || trim((string) $value) === ''
                || in_array($field, $this->deniedColumns, true) || ! $this->fieldIsFilterable($field)) {
                continue;
            }

            $this->addAdvancedSearchField($field, $operator, (string) $value, $logic);
        }

        // Active when there is something to apply — the toggle itself is the
        // client's (an open panel with no row filters nothing on Livewire too).
        $this->advancedSearchActive = $this->advancedSearchFields !== [];
    }

    /**
     * The link filters a request carries, read by the engine's own
     * captureUrlFilters() (field allowlist, operator allowlist, value shape).
     * It reads the request's query string, so `f` is put there for the call —
     * a POST (bulk, export) carries the state in its body.
     *
     * @param  array<array-key, mixed>  $filters
     */
    private function applyLinkFilters(array $filters): void
    {
        $query = request()->query;
        $had = $query->has('f');
        $previous = $had ? $query->all()['f'] : null;

        $query->set('f', $filters);

        try {
            $this->captureUrlFilters();
        } finally {
            $had ? $query->set('f', $previous) : $query->remove('f');
        }
    }
}

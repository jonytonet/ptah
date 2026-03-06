<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Support\Facades\Auth;
use Ptah\Models\UserPreference;

/**
 * Handles user preference persistence (V2): column order, filters, view settings.
 */
trait HasCrudPreferences
{
    // ── Save / load ────────────────────────────────────────────────────────────

    public function savePreferences(): void
    {
        $prefs = [
            '_version'      => '2.1.0',
            '_lastModified' => now()->toIso8601String(),
            'company'       => $this->companyFilter ?: ptah_company_id(),
            'table'         => [
                'orderBy'     => $this->sort,
                'direction'   => $this->direction,
                'perPage'     => $this->perPage,
                'columns'     => $this->columnOrder,
                'currentPage' => 1,
            ],
            'filters'       => [
                'lastUsed'           => array_filter($this->filters),
                'operators'          => $this->filterOperators,
                'dateRanges'         => array_filter($this->dateRanges),
                'dateRangeOperators' => $this->dateRangeOperators,
                'saved'              => $this->savedFilters,
                'customFilter'       => [],
                'quickDate'          => $this->quickDateFilter,
                'quickDateColumn'    => $this->quickDateColumn,
                'search'             => $this->search,
                'sdLabels'           => $this->sdLabels,
                'sdFilterLabels'     => $this->sdFilterLabels,
            ],
            'columns'       => $this->formDataColumns,
            'columnWidths'  => $this->columnWidths,
            'columnOrder'   => $this->columnOrder,
            'viewMode'      => $this->viewMode,
            'viewDensity'   => $this->viewDensity,
            'searchHistory' => array_slice($this->searchHistory, 0, 20),
            'advancedSearch'=> [
                'active' => $this->advancedSearchActive,
                'fields' => $this->advancedSearchFields,
            ],
            'ui'     => null,
            'export' => null,
        ];

        $userId = Auth::id();

        if ($userId) {
            UserPreference::set(
                userId: $userId,
                key:    'crud.' . $this->model,
                value:  $prefs,
                group:  'crud',
            );
            $this->cacheService->forgetPreferences($userId, $this->model);
        } else {
            // Fallback: persist to session when no authenticated user
            session(['ptah.crud.' . $this->model => $prefs]);
        }
    }

    protected function loadPreferences(): void
    {
        $userId = Auth::id();

        if ($userId) {
            $prefs = UserPreference::get($userId, 'crud.' . $this->model, null);
        } else {
            // Fallback: load from session when no authenticated user
            $prefs = session('ptah.crud.' . $this->model, null);
        }

        if (! $prefs || ! is_array($prefs)) {
            $this->applyDefaultUiPreferences();
            return;
        }

        // Table
        $table = $prefs['table'] ?? [];
        $this->sort      = $table['orderBy']  ?? 'id';
        $this->direction = $table['direction'] ?? 'DESC';
        $this->perPage   = (int) ($table['perPage'] ?? config('ptah.crud.per_page', 25));

        // Columns
        $this->columnOrder     = $prefs['columnOrder'] ?? [];
        $this->columnWidths    = $prefs['columnWidths'] ?? [];
        $this->formDataColumns = $prefs['columns'] ?? $this->formDataColumns;
        $this->viewMode        = $prefs['viewMode']    ?? 'table';
        $this->viewDensity     = $prefs['viewDensity'] ?? 'comfortable';

        // Filters
        $filterPrefs              = $prefs['filters'] ?? [];
        $this->filters            = $filterPrefs['lastUsed']            ?? [];
        $this->filterOperators    = $filterPrefs['operators']           ?? [];
        $this->dateRanges         = $filterPrefs['dateRanges']          ?? [];
        $this->dateRangeOperators = $filterPrefs['dateRangeOperators']  ?? [];
        $this->savedFilters       = $filterPrefs['saved']               ?? [];
        $this->quickDateFilter    = $filterPrefs['quickDate']           ?? '';
        $this->quickDateColumn    = $filterPrefs['quickDateColumn']     ?? ($this->crudConfig['quickDateColumn'] ?? 'created_at');
        $this->search             = $filterPrefs['search']              ?? '';
        $this->sdLabels           = $filterPrefs['sdLabels']            ?? [];
        $this->sdFilterLabels     = $filterPrefs['sdFilterLabels']      ?? [];

        // Advanced search
        $advPrefs                   = $prefs['advancedSearch'] ?? [];
        $this->advancedSearchActive = (bool) ($advPrefs['active'] ?? false);
        $this->advancedSearchFields = $advPrefs['fields'] ?? [];

        // Search history
        $this->searchHistory = $prefs['searchHistory'] ?? [];

        // Rebuild active filter summary text
        $this->buildTextFilter();

        // Recalculate hidden columns count
        $this->updateHiddenColumnsCount();
    }

    protected function applyDefaultUiPreferences(): void
    {
        $ui = $this->crudConfig['uiPreferences'] ?? [];
        $this->viewDensity = ! empty($ui['compactMode']) ? 'compact' : 'comfortable';
        $this->perPage     = (int) ($ui['perPage'] ?? config('ptah.crud.per_page', 25));
    }
}

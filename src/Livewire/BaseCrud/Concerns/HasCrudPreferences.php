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
    /**
     * Schema version of the persisted preference blob. Bumped to 2.2.0 when
     * `viewMode` gained the 'auto' state — see the migration in
     * loadPreferences().
     */
    private const PREFS_VERSION = '2.2.0';

    // ── Save / load ────────────────────────────────────────────────────────────

    public function savePreferences(): void
    {
        $prefs = [
            '_version' => self::PREFS_VERSION,
            '_lastModified' => now()->toIso8601String(),
            'company' => $this->companyFilter ?: ptah_company_id(),
            'table' => [
                'orderBy' => $this->sort,
                'direction' => $this->direction,
                'perPage' => $this->perPage,
                'columns' => $this->columnOrder,
                'currentPage' => 1,
            ],
            'filters' => [
                'lastUsed' => array_filter($this->filters),
                'operators' => $this->filterOperators,
                'dateRanges' => array_filter($this->dateRanges),
                'dateRangeOperators' => $this->dateRangeOperators,
                'saved' => $this->savedFilters,
                'customFilter' => [],
                'quickDate' => $this->quickDateFilter,
                'quickDateColumn' => $this->quickDateColumn,
                'search' => $this->search,
                'sdLabels' => $this->sdLabels,
                'sdFilterLabels' => $this->sdFilterLabels,
            ],
            'columns' => $this->formDataColumns,
            'columnWidths' => $this->columnWidths,
            'columnOrder' => $this->columnOrder,
            'viewMode' => $this->viewMode,
            'viewDensity' => $this->viewDensity,
            'searchHistory' => array_slice($this->searchHistory, 0, 20),
            'advancedSearch' => [
                'active' => $this->advancedSearchActive,
                'fields' => $this->advancedSearchFields,
            ],
            'ui' => null,
            'export' => null,
        ];

        $userId = Auth::id();

        if ($userId) {
            UserPreference::set(
                userId: $userId,
                key: 'crud.'.$this->model,
                value: $prefs,
                group: 'crud',
            );
            $this->cacheService->forgetPreferences($userId, $this->model);
        } else {
            // Fallback: persist to session when no authenticated user
            session(['ptah.crud.'.$this->model => $prefs]);
        }
    }

    protected function loadPreferences(): void
    {
        $userId = Auth::id();

        if ($userId) {
            $prefs = UserPreference::get($userId, 'crud.'.$this->model, null);
        } else {
            // Fallback: load from session when no authenticated user
            $prefs = session('ptah.crud.'.$this->model, null);
        }

        if (! $prefs || ! is_array($prefs)) {
            $this->applyDefaultUiPreferences();

            return;
        }

        // Table
        $table = $prefs['table'] ?? [];
        $this->sort = $table['orderBy'] ?? 'id';
        $this->direction = $table['direction'] ?? 'DESC';
        $this->perPage = (int) ($table['perPage'] ?? config('ptah.crud.per_page', 25));

        // Columns
        $this->columnOrder = $prefs['columnOrder'] ?? [];
        $this->columnWidths = $prefs['columnWidths'] ?? [];
        $this->formDataColumns = $prefs['columns'] ?? $this->formDataColumns;
        // Legacy migration, same reasoning as viewDensity's below: before 'auto'
        // existed, EVERY screen persisted 'table' because it was the default —
        // it was never a choice. Reading those rows as a deliberate pin would
        // mean the responsive card layout never reaches a single existing
        // installation, which is exactly the users who would benefit from it.
        //
        // The version marker is what makes this precise rather than a guess: a
        // blob written by 2.2.0 or later stores what the user actually picked,
        // so a 'table' pin chosen AFTER this release is respected and left
        // alone. Only pre-2.2.0 blobs are reinterpreted.
        $storedVersion = (string) ($prefs['_version'] ?? '0');
        $storedViewMode = $prefs['viewMode'] ?? 'table';

        $this->viewMode = (version_compare($storedVersion, '2.2.0', '<') && $storedViewMode === 'table')
            ? 'auto'
            : $storedViewMode;
        // Migracao de legado: antes do eixo global de aparencia (v1.18), toda tela
        // persistia 'comfortable' explicito por ser o default do dropdown — nao era
        // uma escolha. Mapear para 'global' devolve essas telas ao controle do perfil;
        // compact/spacious persistidos eram escolhas deliberadas e ficam pinados.
        $saved = $prefs['viewDensity'] ?? 'global';
        $this->viewDensity = $saved === 'comfortable' ? 'global' : $saved;

        // Filters
        $filterPrefs = $prefs['filters'] ?? [];
        $this->filters = $filterPrefs['lastUsed'] ?? [];
        $this->filterOperators = $filterPrefs['operators'] ?? [];
        $this->dateRanges = $filterPrefs['dateRanges'] ?? [];
        $this->dateRangeOperators = $filterPrefs['dateRangeOperators'] ?? [];
        $this->savedFilters = $filterPrefs['saved'] ?? [];
        $this->quickDateFilter = $filterPrefs['quickDate'] ?? '';
        $this->quickDateColumn = $filterPrefs['quickDateColumn'] ?? ($this->crudConfig['quickDateColumn'] ?? 'created_at');
        $this->search = $filterPrefs['search'] ?? '';
        $this->sdLabels = $filterPrefs['sdLabels'] ?? [];
        $this->sdFilterLabels = $filterPrefs['sdFilterLabels'] ?? [];

        // Advanced search
        $advPrefs = $prefs['advancedSearch'] ?? [];
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
        $this->viewDensity = ! empty($ui['compactMode']) ? 'compact' : 'global';
        $this->perPage = (int) ($ui['perPage'] ?? config('ptah.crud.per_page', 25));
    }
}

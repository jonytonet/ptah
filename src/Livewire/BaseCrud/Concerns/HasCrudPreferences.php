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
     * `viewMode` gained the 'auto' state, and to 2.3.0 (1.50.0) when a saved
     * 'comfortable' density started being kept as the user's choice — see the
     * migrations in loadPreferences().
     */
    private const PREFS_VERSION = '2.3.0';

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
                // The page the user is on (1.56.0) — it was always 1.
                'currentPage' => max(1, (int) $this->getPage()),
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
                key: $this->preferencesKey(),
                value: $prefs,
                group: 'crud',
            );
            $this->cacheService->forgetPreferences($userId, $this->model);
        } else {
            // Fallback: persist to session when no authenticated user
            session(['ptah.'.$this->preferencesKey() => $prefs]);
        }
    }

    protected function loadPreferences(): void
    {
        $userId = Auth::id();

        // Per company first (1.56.0); a company with none yet starts from the
        // screen's preferences saved before preferences were per company.
        $keys = array_values(array_unique([$this->preferencesKey(), 'crud.'.$this->model]));
        $prefs = null;

        foreach ($keys as $key) {
            $prefs = $userId
                ? UserPreference::get($userId, $key, null)
                // Fallback: load from session when no authenticated user
                : session('ptah.'.$key, null);

            if (is_array($prefs) && $prefs !== []) {
                break;
            }
        }

        if (! $prefs || ! is_array($prefs)) {
            $this->applyDefaultUiPreferences();

            return;
        }

        // Table
        $table = $prefs['table'] ?? [];
        [$defaultSort, $defaultDirection] = $this->defaultSort();
        $this->sort = $table['orderBy'] ?? $defaultSort;
        $this->direction = $table['direction'] ?? $defaultDirection;
        $this->perPage = (int) ($table['perPage'] ?? config('ptah.crud.per_page', 25));

        // The page the user left (1.56.0). Set directly — setPage() would save
        // the preferences again while loading them. A ?page= in the URL wins.
        $savedPage = (int) ($table['currentPage'] ?? 1);
        if ($savedPage > 1 && ! request()->query->has('page')) {
            $this->paginators['page'] = $savedPage;
        }

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
        //
        // Only pre-2.3.0 blobs, though (1.50.0): the mapping ran on EVERY load,
        // so picking "Comfortable" for a screen never stuck — the next load
        // turned it back into the profile's density.
        $saved = $prefs['viewDensity'] ?? 'global';
        $this->viewDensity = ($saved === 'comfortable' && version_compare($storedVersion, '2.3.0', '<')) ? 'global' : $saved;

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

    /**
     * Where this screen's preferences are kept: per user and screen, and —
     * with the company module on — per company too (1.56.0). Switching
     * company used to carry the page, filters and search of the other
     * company along; now each company keeps its own, and coming back to
     * company 1 reopens page 20 with company 1's filters.
     */
    protected function preferencesKey(): string
    {
        $key = 'crud.'.$this->model;
        $company = (int) $this->companyFilter;

        return config('ptah.modules.company') && $company > 0 ? $key.'@company:'.$company : $key;
    }

    /**
     * Livewire's pagination hook: the page the user moves to is remembered.
     */
    public function updatedPaginators(mixed $page, string $pageName): void
    {
        if ($pageName === 'page') {
            $this->savePreferences();
        }
    }

    protected function applyDefaultUiPreferences(): void
    {
        $ui = $this->crudConfig['uiPreferences'] ?? [];
        $this->viewDensity = ! empty($ui['compactMode']) ? 'compact' : 'global';
        $this->perPage = (int) ($ui['perPage'] ?? config('ptah.crud.per_page', 25));
        [$this->sort, $this->direction] = $this->defaultSort();
    }

    /**
     * The screen's default order: `uiPreferences.defaultSort` and
     * `defaultDirection` (1.55.0 — it was always `id DESC`). A column the user
     * may not sort by — denied, or not one of the screen's — falls back to the
     * key, by the same allowlist that guards a sort sent by the browser.
     *
     * @return array{0: string, 1: 'ASC'|'DESC'}
     */
    protected function defaultSort(): array
    {
        $ui = $this->crudConfig['uiPreferences'] ?? [];
        $column = (string) ($ui['defaultSort'] ?? 'id');
        $direction = strtoupper((string) ($ui['defaultDirection'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if ($column === '' || ($column !== 'id' && ! $this->sortColumnIsAllowed($column))) {
            $column = 'id';
        }

        return [$column, $direction];
    }
}

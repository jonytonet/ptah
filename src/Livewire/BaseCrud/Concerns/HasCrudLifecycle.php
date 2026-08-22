<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Livewire\Attributes\On;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Services\Permission\ColumnPermissionService;
use Ptah\Support\StyleRule;

/**
 * Handles Livewire lifecycle hooks: boot, mount and config reload.
 */
trait HasCrudLifecycle
{
    // ── Lifecycle ──────────────────────────────────────────────────────────────

    /**
     * Returns the current URL path without leading slash.
     * Used as the screen-specific config key, e.g. 'categories'.
     */
    private function resolveCurrentRoute(): string
    {
        return ltrim(request()->path(), '/');
    }

    public function boot(
        CrudConfigService $configService,
        FilterService $filterService,
        CacheService $cacheService,
        FormValidatorService $formValidator,
        ColumnPermissionService $columnPermissionService,
    ): void {
        $this->configService = $configService;
        $this->filterService = $filterService;
        $this->cacheService = $cacheService;
        $this->formValidator = $formValidator;
        $this->columnPermissionService = $columnPermissionService;

        // Reload crudConfig on every request to guarantee fresh data from DB
        if ($this->model) {
            $route = $this->configRoute ?: $this->resolveCurrentRoute();
            $config = $this->configService->find($this->model, $route);
            $this->crudConfig = $this->applyColumnPermissions($config?->config ?? []);
        }
    }

    // ── Column permissions ───────────────────────────────────────────────────

    /**
     * Filters $config['cols'] by the per-column `colsPermission` gate (see
     * ColumnPermissionService) and re-intersects every OTHER part of the
     * config that can derive its own field list straight from crudConfig
     * instead of the already-filtered cols — totalizadores, contitionStyles,
     * customFilters, groupBy/groupBreak. Public columns (the overwhelming
     * majority when the permissions module is off, or a column simply has no
     * `colsPermission` tag) are entirely unaffected.
     *
     * Called from the three places that assign `$this->crudConfig` (boot(),
     * mount(), reloadCrudConfig()) — this wave is ATOMIC with the
     * re-intersections in HasCrudQuery/HasCrudForm/HasCrudColumns/
     * HasCrudRenderers; splitting them would leave a denied column's data
     * reachable through sort/filter/openEdit/renderLink even though it no
     * longer appears in `cols`.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function applyColumnPermissions(array $config): array
    {
        $result = $this->columnPermissionService->apply(
            $config['cols'] ?? [],
            null,
            $this->companyFilter > 0 ? $this->companyFilter : null,
        );

        $config['cols'] = $result['cols'];
        $this->deniedColumns = $result['denied'];

        if ($this->deniedColumns === []) {
            return $config;
        }

        // totalizadores.columns — feeds HasCrudQuery::totalizadoresData(), the
        // _table footer and the per-group subtotal partial.
        if (! empty($config['totalizadores']['columns'])) {
            $config['totalizadores']['columns'] = array_values(array_filter(
                $config['totalizadores']['columns'],
                fn (array $totCol) => ! in_array($totCol['field'] ?? null, $this->deniedColumns, true)
            ));
        }

        // contitionStyles — drop rules whose normalized field is denied,
        // BEFORE StyleRule::normalize() runs again at render time
        // (HasCrudRenderers::getRowStyle()). Reuses normalize() itself so the
        // field is extracted with the exact same key precedence (field /
        // colsNomeFisico / styleField) — no duplicated logic to drift out of
        // sync. Rules that don't normalise at all (invalid condition/missing
        // style) are left untouched; getRowStyle() already discards those.
        foreach (['contitionStyles', 'conditionStyles'] as $stylesKey) {
            if (empty($config[$stylesKey])) {
                continue;
            }

            $config[$stylesKey] = array_values(array_filter(
                $config[$stylesKey],
                function (array $rule) {
                    $normalized = StyleRule::normalize($rule);

                    return $normalized === null || ! in_array($normalized['field'], $this->deniedColumns, true);
                }
            ));
        }

        // customFilters — drop entries reading a denied column, whether
        // directly (field) or through a relation (colRelation).
        if (! empty($config['customFilters'])) {
            $config['customFilters'] = array_values(array_filter(
                $config['customFilters'],
                fn (array $cf) => ! in_array($cf['field'] ?? null, $this->deniedColumns, true)
                    && ! in_array($cf['colRelation'] ?? null, $this->deniedColumns, true)
            ));
        }

        // groupBy / groupBreak referencing a denied column fall back to the
        // normal (ungrouped) flow — both codepaths already treat null as
        // "not configured".
        if (in_array($config['groupBy'] ?? null, $this->deniedColumns, true)) {
            $config['groupBy'] = null;
        }
        if (in_array($config['groupBreak'] ?? null, $this->deniedColumns, true)) {
            $config['groupBreak'] = null;
        }

        return $config;
    }

    public function mount(
        string $model,
        array $initialFilter = [],
        string $whereHasFilter = '',
        array $whereHasCondition = [],
        int $companyFilter = 0,
        array $lockedFilters = [],
    ): void {
        $this->model = $model;
        $this->lockedFilters = $lockedFilters;
        $this->configRoute = $this->resolveCurrentRoute();
        $this->whereHasFilter = $whereHasFilter;
        $this->whereHasCondition = $whereHasCondition;
        $this->companyFilter = $companyFilter ?: ptah_company_id();

        // Load the configuration (screen-specific, with fallback to global)
        $config = $this->configService->find($model, $this->configRoute);

        if (! $config) {
            $this->crudConfig = [];

            return;
        }

        $this->crudConfig = $this->applyColumnPermissions($config->config);

        // Resolve Eloquent model
        $this->resolveEloquentModel();

        // Initialise default date column for quick date filter
        $this->quickDateColumn = $this->crudConfig['quickDateColumn'] ?? 'created_at';

        // Initialise column visibility
        $this->initFormDataColumns();

        // Load user preferences
        $this->loadPreferences();

        // Capture ?f[...] URL filters (override preferences while active, never persisted)
        $this->captureUrlFilters();

        // Count deleted records
        $this->updateTrashedCount();

        // Apply initial filters
        if (! empty($initialFilter)) {
            foreach ($initialFilter as $filterItem) {
                if (is_array($filterItem) && count($filterItem) >= 3) {
                    [$field, , $value] = $filterItem;
                    $this->filters[$field] = $value;
                }
            }
        }
    }

    // ── Config reload (event from CrudConfig modal) ─────────────────────────

    #[On('ptah:crud-config-updated')]
    public function reloadCrudConfig(): void
    {
        // Invalidate cache to force re-read from DB
        $this->configService->forget($this->model, $this->configRoute);

        // Reload the updated config (screen-specific with fallback)
        $config = $this->configService->find($this->model, $this->configRoute);

        if ($config) {
            $this->crudConfig = $this->applyColumnPermissions($config->config);
        }

        // Refresh column visibility to reflect changes
        $this->initFormDataColumns();
    }
}

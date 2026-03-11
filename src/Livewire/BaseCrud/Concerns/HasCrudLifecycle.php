<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Livewire\Attributes\On;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;

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
        CrudConfigService    $configService,
        FilterService        $filterService,
        CacheService         $cacheService,
        FormValidatorService $formValidator,
    ): void {
        $this->configService = $configService;
        $this->filterService = $filterService;
        $this->cacheService  = $cacheService;
        $this->formValidator = $formValidator;

        // Reload crudConfig on every request to guarantee fresh data from DB
        if ($this->model) {
            $route  = $this->configRoute ?: $this->resolveCurrentRoute();
            $config = $this->configService->find($this->model, $route);
            $this->crudConfig = $config?->config ?? [];
        }
    }

    public function mount(
        string $model,
        array  $initialFilter        = [],
        string $whereHasFilter       = '',
        array  $whereHasCondition    = [],
        int    $companyFilter        = 0,
    ): void {
        $this->model             = $model;
        $this->configRoute       = $this->resolveCurrentRoute();
        $this->whereHasFilter    = $whereHasFilter;
        $this->whereHasCondition = $whereHasCondition;
        $this->companyFilter     = $companyFilter ?: ptah_company_id();

        // Load the configuration (screen-specific, with fallback to global)
        $config = $this->configService->find($model, $this->configRoute);

        if (! $config) {
            $this->crudConfig = [];
            return;
        }

        $this->crudConfig = $config->config;

        // Resolve Eloquent model
        $this->resolveEloquentModel();

        // Initialise default date column for quick date filter
        $this->quickDateColumn = $this->crudConfig['quickDateColumn'] ?? 'created_at';

        // Initialise column visibility
        $this->initFormDataColumns();

        // Load user preferences
        $this->loadPreferences();

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
            $this->crudConfig = $config->config;
        }

        // Refresh column visibility to reflect changes
        $this->initFormDataColumns();
    }
}

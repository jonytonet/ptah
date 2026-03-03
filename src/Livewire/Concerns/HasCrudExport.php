<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Handles synchronous and asynchronous data export.
 */
trait HasCrudExport
{
    // ── Export ─────────────────────────────────────────────────────────────────

    public function export(string $format = 'excel'): void
    {
        $exportConfig = $this->crudConfig['exportConfig'] ?? [];

        if (empty($exportConfig['enabled'])) {
            return;
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $query         = $modelInstance->newQuery();
        $this->applyJoins($query, $modelInstance);
        $activeFilters = $this->buildActiveFilters();

        if (! empty($activeFilters)) {
            $this->filterService->applyFilters($query, $activeFilters);
        }

        $count = $query->count();
        $async = $count > (int) ($exportConfig['asyncThreshold'] ?? 1000);

        if ($async) {
            $this->dispatchExportJob($format, $exportConfig);
            $this->exportStatus = trans('ptah::ui.export_processing');
        } else {
            // Basic synchronous export via download
            $this->dispatch('ptah:export-sync', [
                'model'   => $this->model,
                'format'  => $format,
                'filters' => $this->filters,
            ]);
            $this->exportStatus = '';
        }

        $this->showExportMenu = false;
    }

    public function bulkExport(string $format = 'excel'): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $this->dispatch('ptah:bulk-export', [
            'model'  => $this->model,
            'ids'    => $this->selectedRows,
            'format' => $format,
        ]);
    }

    protected function dispatchExportJob(string $format, array $exportConfig): void
    {
        // Dispatch via queue if the job class exists
        $jobClass = 'Ptah\\Jobs\\BaseCrudExportJob';

        if (class_exists($jobClass)) {
            dispatch(new $jobClass(
                model:   $this->model,
                filters: $this->filters,
                format:  $format,
                userId:  Auth::id(),
                config:  $exportConfig,
            ));
        }
    }
}

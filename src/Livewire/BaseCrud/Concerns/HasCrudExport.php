<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

        $query = $modelInstance->newQuery();
        $this->applyJoins($query, $modelInstance);
        $activeFilters = $this->buildActiveFilters();

        if (! empty($activeFilters)) {
            $this->filterService->applyFilters($query, $activeFilters);
        }

        $count = $query->count();
        $async = $count > (int) ($exportConfig['asyncThreshold'] ?? 1000);

        // Pegar apenas colunas visíveis para exportação
        $visibleColumns = $this->getVisibleColumnsForExport();

        if ($async) {
            $this->dispatchExportJob($format, $exportConfig);
            $this->exportStatus = trans('ptah::ui.export_processing');
        } else {
            // Basic synchronous export via download
            $this->dispatch('ptah:export-sync', [
                'model' => $this->model,
                'format' => $format,
                'filters' => $this->filters,
                'columns' => $visibleColumns,
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

        // Pegar apenas colunas visíveis para exportação
        $visibleColumns = $this->getVisibleColumnsForExport();

        $this->dispatch('ptah:bulk-export', [
            'model' => $this->model,
            'ids' => $this->selectedRows,
            'format' => $format,
            'columns' => $visibleColumns,
        ]);
    }

    protected function dispatchExportJob(string $format, array $exportConfig): void
    {
        // Dispatch via queue if the job class exists
        $jobClass = 'Ptah\\Jobs\\BaseCrudExportJob';

        if (class_exists($jobClass)) {
            dispatch(new $jobClass(
                model: $this->model,
                filters: $this->filters,
                format: $format,
                userId: Auth::id(),
                config: $exportConfig,
            ));
        }
    }

    // ── Print screen ─────────────────────────────────────────────────────────

    /**
     * Builds a print-ready snapshot of the CURRENT filtered listing (all rows up
     * to exportConfig.maxRows, no pagination), renders every cell with the same
     * formatCell() the table uses, computes totals, caches the payload under a
     * short-lived token and tells the browser to open the print window.
     *
     * The query is built by the shared buildBaseQuery()/applyGroupingAndSort(),
     * so the printout reflects exactly the same filters/search/company scope as
     * the listing — the controller only displays the cached payload.
     */
    public function printView(): void
    {
        $exportConfig = $this->crudConfig['exportConfig'] ?? [];

        // Same gate as export — the print button lives in the export menu.
        if (empty($exportConfig['enabled'])) {
            return;
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        [$query, $joinedTables] = $this->buildBaseQuery($modelInstance);
        $this->applyGroupingAndSort($query, $modelInstance, $joinedTables);

        $maxRows = (int) ($exportConfig['maxRows'] ?? 5000);

        // Fetch one extra row to detect truncation without a separate count()
        // (count() is unreliable with GROUP BY).
        $records = $query->limit($maxRows + 1)->get();
        $truncated = $records->count() > $maxRows;
        if ($truncated) {
            $records = $records->take($maxRows);
        }

        // Visible, non-action columns (same set the table shows).
        $columns = [];
        foreach ($this->getVisibleColumns() as $col) {
            if (($col['colsTipo'] ?? '') === 'action') {
                continue;
            }
            $columns[] = $col;
        }

        // Totals keyed by physical column name (respect the active filters).
        // Called as a method (not the computed property) to recompute against the
        // current filter state without relying on Livewire's per-request cache.
        $totals = $this->totalizadoresData();

        // Pre-render rows: each cell as the same HTML the listing produces.
        $renderedRows = [];
        foreach ($records as $row) {
            $cells = [];
            foreach ($columns as $col) {
                $cells[] = $this->formatCell($col, $row);
            }
            $renderedRows[] = [
                'cells' => $cells,
                'style' => $this->getRowStyle($row),
            ];
        }

        // Column descriptors + formatted total under each column.
        $columnDescriptors = [];
        foreach ($columns as $col) {
            $field = $col['colsNomeFisico'] ?? '';
            $hasTotal = array_key_exists($field, $totals) && $totals[$field] !== null;
            $columnDescriptors[] = [
                'label' => $col['colsNomeLogico'] ?? $field,
                'field' => $field,
                'align' => $col['colsAlign'] ?? 'text-start',
                'total' => $hasTotal ? $this->formatTotalForColumn($col, $totals[$field]) : null,
            ];
        }

        $payload = [
            'version' => 1,
            'userId' => Auth::id(),
            'model' => $this->model,
            'title' => $this->crudConfig['displayName']
                ?? $this->crudConfig['crud']
                ?? class_basename(str_replace('/', '\\', $this->model)),
            'columns' => $columnDescriptors,
            'rows' => $renderedRows,
            'filters' => $this->buildPrintFilterSummary(),
            'totalRecords' => $records->count(),
            'truncated' => $truncated,
            'maxRows' => $maxRows,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ];

        $token = (string) Str::uuid();
        Cache::put('ptah:print:'.$token, $payload, now()->addMinutes(10));

        $this->showExportMenu = false;

        // The browser opens the print route in a new tab (see base-crud view).
        $this->dispatch('ptah:open-print', url: route('ptah.print', ['token' => $token]));
    }

    /**
     * Human-readable summary of the active filters, for the print header.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function buildPrintFilterSummary(): array
    {
        $summary = [];

        if ($this->search !== '') {
            $summary[] = ['label' => trans('ptah::ui.search_placeholder'), 'value' => $this->search];
        }

        // textFilter already holds the active filter badges ([{label, value}]).
        foreach ($this->textFilter as $badge) {
            $summary[] = [
                'label' => (string) ($badge['label'] ?? ''),
                'value' => (string) ($badge['value'] ?? ''),
            ];
        }

        return $summary;
    }

    /**
     * Formats a totalizador value the same way the listing footer does:
     * currency renderers/helpers → "R$ 1.234,56", numeric otherwise.
     */
    protected function formatTotalForColumn(array $col, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $isCurrency = ($col['colsHelper'] ?? '') === 'currencyFormat'
            || ($col['colsRenderer'] ?? '') === 'money';

        if ($isCurrency) {
            return trans('ptah::ui.currency_prefix')
                .number_format(
                    (float) $value,
                    2,
                    trans('ptah::ui.number_dec_point'),
                    trans('ptah::ui.number_thousands'),
                );
        }

        if (is_numeric($value)) {
            $decimals = ((float) $value == (int) $value) ? 0 : 2;

            return number_format(
                (float) $value,
                $decimals,
                trans('ptah::ui.number_dec_point'),
                trans('ptah::ui.number_thousands'),
            );
        }

        return (string) $value;
    }

    /**
     * Retorna apenas as colunas visíveis (não-action) para exportação
     */
    protected function getVisibleColumnsForExport(): array
    {
        $visibleCols = $this->getVisibleColumns();

        $exportColumns = [];

        foreach ($visibleCols as $col) {
            $tipo = $col['colsTipo'] ?? '';

            // Ignorar colunas de ação
            if ($tipo === 'action') {
                continue;
            }

            $exportColumns[] = [
                'field' => $col['colsNomeFisico'] ?? '',
                'label' => $col['colsNomeLogico'] ?? '',
                'type' => $tipo,
            ];
        }

        return $exportColumns;
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ptah\Jobs\GenerateCrudExportJob;
use Ptah\Models\Export;

/**
 * Handles synchronous and asynchronous data export.
 */
trait HasCrudExport
{
    // ── Export ─────────────────────────────────────────────────────────────────

    /**
     * Exports the CURRENT filtered listing. Like the print screen, the component
     * builds the query via the shared buildBaseQuery()/applyGroupingAndSort() — so
     * the export honours the exact same search / filters / company scope as the
     * table — collects the ordered ids up to exportConfig.maxRows, and hands a
     * short-lived, user-scoped token to the download controller (which resolves the
     * model server-side and generates the file). The client never names a model or
     * reapplies filters, which is what closed the old ?model=User hole and the
     * "export ignores filters" drift.
     */
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

        [$query, $joinedTables] = $this->buildBaseQuery($modelInstance);
        $this->applyGroupingAndSort($query, $modelInstance, $joinedTables);

        $maxRows = (int) ($exportConfig['maxRows'] ?? 5000);
        $pk = $modelInstance->getKeyName();

        // pluck on the RESULT collection (not the query) so a group-by/join SELECT
        // is not overwritten; the maxRows cap bounds the fetch (same as print).
        $ids = $query->limit($maxRows)->get()->pluck($pk)->all();

        $this->dispatchExportDownload($ids, $format);
        $this->exportStatus = '';
        $this->showExportMenu = false;
    }

    /**
     * Exports only the selected rows (bulk export) through the same token flow.
     */
    public function bulkExport(string $format = 'excel'): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $this->dispatchExportDownload($this->selectedRows, $format);
    }

    /**
     * Caches a user-scoped export payload (model + ordered ids + visible columns
     * + sort) under a one-time token and tells the browser to open the download.
     *
     * @param  array<int, int|string>  $ids
     */
    protected function dispatchExportDownload(array $ids, string $format): void
    {
        $payload = [
            'version' => 1,
            'userId' => Auth::id(),
            'model' => $this->model,
            'ids' => array_values($ids),
            'columns' => $this->getVisibleColumnsForExport(),
            'order' => $this->sort,
            'direction' => $this->direction,
            'format' => $format,
        ];

        $token = (string) Str::uuid();
        Cache::put('ptah:export:'.$token, $payload, now()->addMinutes(10));

        $this->showExportMenu = false;
        $this->dispatch('ptah:export-download', url: route('ptah.export.download', ['token' => $token]));
    }

    // ── Async export (Fase 3 — "grande volume") ──────────────────────────────

    /**
     * Queues the CURRENT filtered listing for background generation instead of
     * building the file inline. The component still resolves the filtered/
     * sorted ids via buildBaseQuery()/applyGroupingAndSort() — the same source
     * of truth as the synchronous export — and only hands them, already
     * resolved, to GenerateCrudExportJob; the job never rebuilds the query.
     *
     * Opt-in per CRUD (exportConfig.asyncExport.enabled, default false) and
     * degrades to the synchronous export when there is no real queue backend
     * or the ptah_exports table has not been migrated yet, so a misconfigured
     * install never leaves the user with a request that is queued but never
     * processed.
     */
    public function queueExport(string $format = 'excel'): void
    {
        $exportConfig = $this->crudConfig['exportConfig'] ?? [];

        if (empty($exportConfig['enabled']) || empty($exportConfig['asyncExport']['enabled'])) {
            return;
        }

        if (config('queue.default') === 'sync' || ! Schema::hasTable('ptah_exports')) {
            $this->export($format);
            $this->dispatch('ptah-toast', title: trans('ptah::ui.export_degraded_toast'), color: 'warn');

            return;
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        [$query, $joinedTables] = $this->buildBaseQuery($modelInstance);
        $this->applyGroupingAndSort($query, $modelInstance, $joinedTables);

        $pk = $modelInstance->getKeyName();

        // PDF stays bounded by exportConfig.maxRows (same cap as the synchronous
        // print/export); Excel honours ptah.export.async_max_rows (0 = no limit,
        // since the job streams the workbook via FromQuery instead of loading
        // everything into memory at once).
        $maxRows = $format === 'pdf'
            ? (int) ($exportConfig['maxRows'] ?? 5000)
            : (int) config('ptah.export.async_max_rows', 0);

        if ($maxRows > 0) {
            $query->limit($maxRows);
        }

        // Select just the PK — no need to hydrate full Eloquent models (with
        // every visible column, casts, mutators…) just to collect ids. This is
        // exactly the "large volume" case the sync export's ->get() would
        // strain on. Safe with group-by/joins too: Eloquent's pluck() only
        // overrides the SELECT when the query has none yet — an existing
        // custom select (groupBy's aliased id, a join's extra columns) is left
        // alone and its already-selected pk column is simply extracted.
        $ids = $query->pluck($pk)->all();

        // The model that ACTUALLY produced these ids — resolved server-side via
        // buildBaseQuery()/resolveEloquentModel(), never the raw, client-
        // writable $this->model. Storing $this->model directly here would let
        // a forged request record ids from one model while naming a different
        // one for the job to query — a cross-model data leak. get_class() also
        // gives GenerateCrudExportJob a concrete, directly loadable FQCN with
        // no namespace-guessing needed.
        $resolvedModelClass = get_class($modelInstance);

        $exportRecord = Export::create([
            'user_id' => Auth::id(),
            'company_id' => $this->companyFilter,
            'model' => $resolvedModelClass,
            'route' => request()->path(),
            'format' => $format,
            'status' => 'queued',
            'payload' => $this->buildExportPayload($ids, $format, $resolvedModelClass),
        ]);

        GenerateCrudExportJob::dispatch($exportRecord->id);

        $this->showExportMenu = false;
        $this->dispatch('ptah-toast', title: trans('ptah::ui.export_queued_toast'), color: 'success');
    }

    /**
     * Builds the snapshot GenerateCrudExportJob consumes to generate the file
     * from an already-filtered/sorted set of ids. Same shape as
     * dispatchExportDownload()'s payload, plus route/companyId — the job has
     * no live component/session to fall back on, so both travel explicitly.
     *
     * $modelClass MUST be the resolved model (get_class($this->resolveEloquentModel()))
     * that produced $ids — never the raw $this->model — see queueExport().
     *
     * @param  array<int, int|string>  $ids
     * @return array{version: int, userId: int|null, model: string, route: string, companyId: int|null, ids: array<int, int|string>, columns: array<int, array{field: string, label: string, type: string}>, order: string, direction: string, format: string}
     */
    public function buildExportPayload(array $ids, string $format, string $modelClass): array
    {
        return [
            'version' => 1,
            'userId' => Auth::id(),
            'model' => $modelClass,
            'route' => request()->path(),
            'companyId' => $this->companyFilter ?: null,
            'ids' => array_values($ids),
            'columns' => $this->getVisibleColumnsForExport(),
            'order' => $this->sort,
            'direction' => $this->direction,
            'format' => $format,
        ];
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

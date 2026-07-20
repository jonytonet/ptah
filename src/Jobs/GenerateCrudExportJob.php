<?php

declare(strict_types=1);

namespace Ptah\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Ptah\Exports\CrudExport;
use Ptah\Models\Export;
use Ptah\Services\Export\ExportAuthorizer;

/**
 * Generates the file for a queued BaseCrud export (Fase 3 — "grande volume").
 *
 * HasCrudExport::queueExport() already resolved the filtered/sorted ids via
 * the SAME buildBaseQuery()/applyGroupingAndSort() the listing uses and
 * stored them, together with the RESOLVED model FQCN (never a client-writable
 * raw value — see queueExport()), in Export::$payload. This job NEVER
 * rebuilds the listing query; it only re-fetches those exact ids. Before
 * touching the query or writing anything, it re-runs the same allowlist +
 * permission gate the synchronous download() enforces (ExportAuthorizer) — a
 * stale/forged/tampered Export row must never reach file generation.
 */
class GenerateCrudExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * At most 2 attempts — a failed export (bad write, disk error) is rarely
     * transient enough to benefit from more retries, and letting large-file
     * jobs retry indefinitely would defeat the point of "grande volume".
     */
    public int $tries = 2;

    /**
     * Generous ceiling for large exports. Requires the worker's queue driver
     * to support timeouts (e.g. the pcntl extension) — harmless otherwise.
     */
    public int $timeout = 600;

    public function __construct(public int $exportId) {}

    public function backoff(): int
    {
        return 30;
    }

    public function handle(): void
    {
        $export = Export::find($this->exportId);

        if (! $export || ($export->expires_at && $export->expires_at->isPast())) {
            return;
        }

        $payload = $export->payload;
        $modelClass = (string) ($payload['model'] ?? '');

        // The payload always stores the RESOLVED FQCN (queueExport()), so no
        // namespace-guessing is needed here — just confirm it is still a real,
        // loadable Eloquent model (code can change between queueing and the
        // worker picking the job up).
        if ($modelClass === '' || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            $this->markFailed($export, "Ptah export: model [{$modelClass}] could not be resolved.");

            return;
        }

        // Defence in depth: same allowlist + ptah_can('read') gate as
        // ExportController::download() — never generate a file for a model
        // that is not (or no longer) an authorised Ptah CRUD.
        $reason = (new ExportAuthorizer)->reasonDenied($modelClass);

        if ($reason !== null) {
            $this->markFailed($export, $reason);

            return;
        }

        $export->status = 'processing';
        $export->save();

        $modelInstance = new $modelClass;
        $pk = $modelInstance->getKeyName();
        $ids = $payload['ids'] ?? [];
        $columns = $payload['columns'] ?? [];
        $format = (string) ($payload['format'] ?? 'excel');

        $query = $modelClass::query()->whereIn($pk, $ids);

        // Same primary-sort re-apply as ExportController::download() — a
        // relation sort degrades to whatever order whereIn() returns.
        $order = (string) ($payload['order'] ?? $pk);
        $direction = strtoupper((string) ($payload['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if (Schema::hasColumn($modelInstance->getTable(), $order)) {
            $query->orderBy($order, $direction);
        }

        $rows = $query->count();

        $disk = (string) config('ptah.export.disk', 'local');
        $basePath = trim((string) config('ptah.export.path', 'ptah-exports'), '/');
        $fileName = Str::slug(class_basename($modelClass)).'-'.now()->format('Y-m-d-His');
        $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
        $path = $basePath.'/'.$fileName.'.'.$extension;

        if ($format === 'pdf') {
            Pdf::loadView('ptah::exports.pdf', [
                'data' => $query->get(),
                'columns' => $columns,
                'modelName' => class_basename($modelClass),
                'date' => now()->format('d/m/Y H:i:s'),
                'totalizers' => [],
            ])->setPaper('a4', 'portrait')->save($path, $disk);
        } else {
            Excel::store(new CrudExport($query, $columns), $path, $disk);
        }

        $export->status = 'done';
        $export->file_disk = $disk;
        $export->file_path = $path;
        $export->rows = $rows;
        $export->expires_at = now()->addHours((int) config('ptah.export.ttl_hours', 48));
        $export->save();
    }

    /**
     * Called by the queue worker once the job has exhausted its retries.
     */
    public function failed(\Throwable $e): void
    {
        $export = Export::find($this->exportId);

        if ($export) {
            $this->markFailed($export, $e->getMessage());
        }
    }

    private function markFailed(Export $export, string $message): void
    {
        $export->status = 'failed';
        $export->error = $message;
        $export->save();
    }
}

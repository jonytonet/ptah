<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Ptah\Models\Export;

/**
 * ExportPruneCommand — deletes stale queued exports (Fase 3 — "grande
 * volume"): the generated file (if any) on its disk, then the `ptah_exports`
 * row. Meant to run on a schedule (e.g. daily) via the host app's console
 * kernel/schedule.
 *
 * Prunes TWO kinds of rows:
 *  1. Finished ('done') exports whose expires_at has passed — the normal case.
 *  2. Orphans: 'queued'/'processing'/'failed' rows that never reached 'done'
 *     (so expires_at was never set — a crashed worker, a job that silently
 *     never ran, a permanently failed export nobody ever looked at) and are
 *     older than the SAME ttl_hours window (config('ptah.export.ttl_hours')),
 *     measured from created_at. Without this, those rows would sit in the
 *     table forever.
 *
 * Uso:
 *   php artisan ptah:export-prune
 */
class ExportPruneCommand extends Command
{
    protected $signature = 'ptah:export-prune';

    protected $description = 'Delete stale Ptah async export files and records (expired + orphaned)';

    public function handle(): int
    {
        $ttlHours = (int) config('ptah.export.ttl_hours', 48);

        $expired = Export::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $orphans = Export::query()
            ->whereIn('status', ['queued', 'processing', 'failed'])
            ->where('created_at', '<', now()->subHours($ttlHours))
            ->get();

        $toPrune = $expired->merge($orphans)->unique('id');

        if ($toPrune->isEmpty()) {
            $this->components->info('No stale exports to prune.');

            return self::SUCCESS;
        }

        $deletedFiles = 0;

        foreach ($toPrune as $export) {
            if ($export->file_disk && $export->file_path) {
                Storage::disk($export->file_disk)->delete($export->file_path);
                $deletedFiles++;
            }

            $export->delete();
        }

        $this->components->info(sprintf(
            'Pruned %d stale export(s) (%d file(s) removed).',
            $toPrune->count(),
            $deletedFiles
        ));

        return self::SUCCESS;
    }
}

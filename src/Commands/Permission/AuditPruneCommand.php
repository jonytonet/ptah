<?php

declare(strict_types=1);

namespace Ptah\Commands\Permission;

use Illuminate\Console\Command;
use Ptah\Models\PermissionAudit;

/**
 * AuditPruneCommand — deletes `ptah_permission_audits` rows older than a
 * retention window (pattern mirrors `ExportPruneCommand`). Meant to run on a
 * schedule (e.g. daily/weekly) via the host app's console kernel/schedule —
 * `audit=true` with no pruning otherwise grows this table forever.
 *
 * ⚠ DESTRUTIVO — this command permanently deletes rows (no SoftDeletes on
 * `PermissionAudit`, by design: it's an immutable log). See docs/Permissions.md.
 *
 * Uso:
 *   php artisan ptah:audit-prune                # uses config('ptah.permissions.audit_retention_days', 90)
 *   php artisan ptah:audit-prune --days=30
 *   php artisan ptah:audit-prune --dry-run       # count only, no deletion
 *   php artisan ptah:audit-prune --chunk=500     # delete window size (portable across drivers, incl. sqlite)
 */
class AuditPruneCommand extends Command
{
    protected $signature = 'ptah:audit-prune
        {--days= : Retention window in days. Defaults to config(ptah.permissions.audit_retention_days, 90)}
        {--chunk=1000 : Number of rows deleted per batch}
        {--dry-run : Only count the rows that would be deleted, without deleting them}';

    protected $description = 'Delete ptah_permission_audits rows older than the retention window (DESTRUCTIVE)';

    public function handle(): int
    {
        // Inline `?? 90` fallback — a config/ptah.php published before this
        // key existed doesn't have `audit_retention_days` at all, so relying
        // solely on the config default (config('...', 90)) would still work,
        // but the option itself takes precedence and this makes that explicit.
        $daysOption = $this->option('days');
        $days = $daysOption !== null
            ? (int) $daysOption
            : (int) config('ptah.permissions.audit_retention_days', 90);

        if ($days < 1) {
            $this->components->error("--days deve ser >= 1 (recebido: {$days}) — recusado para não apagar a tabela inteira.");

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = fn () => PermissionAudit::query()->where('created_at', '<', $cutoff);

        if ($dryRun) {
            $count = $query()->count();
            $this->components->info("Dry-run: {$count} registro(s) de auditoria anteriores a {$days} dia(s) seriam removidos (nada foi apagado).");

            return self::SUCCESS;
        }

        $deleted = 0;

        // Portable across every driver Ptah supports (including sqlite, which
        // has no fast TRUNCATE-with-condition): pull a page of ids ordered by
        // PK, delete exactly those ids, repeat until a page comes back
        // smaller than the chunk size. PermissionAudit has neither
        // SoftDeletes nor HasAuditFields, so a query-builder delete is
        // semantically identical to a model delete here — no events to lose.
        while (true) {
            $ids = $query()->orderBy('id')->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            PermissionAudit::query()->whereKey($ids)->delete();
            $deleted += $ids->count();

            if ($ids->count() < $chunk) {
                break;
            }
        }

        if ($deleted === 0) {
            $this->components->info("Nenhum registro de auditoria anterior a {$days} dia(s) — nada a remover.");

            return self::SUCCESS;
        }

        $this->components->info("Removido(s) {$deleted} registro(s) de auditoria anteriores a {$days} dia(s).");

        return self::SUCCESS;
    }
}

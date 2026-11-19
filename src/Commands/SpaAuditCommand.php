<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Support\SpaAudit;

/**
 * `ptah:spa-audit` — before turning `PTAH_SPA_NAVIGATION` on: what in THIS
 * project's views and scripts assumes a document load.
 *
 * Reads resources/views and resources/js (plus any --path) and prints one
 * line per place to look at, with file:line and what to do. Read-only. It
 * reads text: a finding is a place to check, not a proven defect.
 *
 * Uso:
 *   php artisan ptah:spa-audit
 *   php artisan ptah:spa-audit --path=app/View --path=public/js
 *   php artisan ptah:spa-audit --json
 *   php artisan ptah:spa-audit --strict        # exit 1 when anything is listed (CI)
 */
class SpaAuditCommand extends Command
{
    protected $signature = 'ptah:spa-audit
        {--path=* : Extra directories to read (relative to the project root)}
        {--json : Machine-readable output}
        {--strict : Exit 1 when there is anything listed}';

    protected $description = 'Before turning PTAH_SPA_NAVIGATION on: list what in this project assumes a document load (DOMContentLoaded, wire:navigate, global listeners…)';

    public function handle(): int
    {
        $dirs = [resource_path('views'), resource_path('js')];
        foreach ((array) $this->option('path') as $extra) {
            if (is_string($extra) && trim($extra) !== '') {
                $dirs[] = base_path(trim($extra, '/\\'));
            }
        }

        $findings = SpaAudit::scan($dirs, base_path());
        $exit = $this->option('strict') && $findings !== [] ? self::FAILURE : self::SUCCESS;

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'enabled' => (bool) config('ptah.forge.spa_navigation', false),
                'findings' => $findings,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $exit;
        }

        if ($findings === []) {
            $this->components->info('Nothing in resources/views or resources/js assumes a document load.');

            return self::SUCCESS;
        }

        $rule = null;
        foreach ($findings as $f) {
            if ($f['rule'] !== $rule) {
                $rule = $f['rule'];
                $this->newLine();
                $this->line("<fg=yellow>⚠</> [{$rule}] {$f['message']}");
                $this->line("    → {$f['action']}");
            }
            $this->line("    {$f['file']}:{$f['line']}  <fg=gray>{$f['code']}</>");
        }

        $this->newLine();
        $this->line(count($findings).' place(s) to look at. Each is a line to check, not a proven defect. See docs/Configuration.md (single-page navigation).');

        return $exit;
    }
}

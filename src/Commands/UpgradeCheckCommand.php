<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Support\PtahVersion;
use Ptah\Support\UpgradeInspector;

/**
 * `ptah:upgrade-check` — after `composer update`, what THIS project must do.
 *
 * Reads the host's published config, views and stubs, the preferences FK and
 * the pending migrations, and prints one line per thing to act on, with the
 * action. Read-only. See UpgradeInspector for each check.
 *
 * Uso:
 *   php artisan ptah:upgrade-check
 *   php artisan ptah:upgrade-check --json
 *   php artisan ptah:upgrade-check --strict      # exit 1 on any warning (CI)
 */
class UpgradeCheckCommand extends Command
{
    protected $signature = 'ptah:upgrade-check
        {--json : Machine-readable output}
        {--strict : Exit 1 when there is anything to act on}';

    protected $description = 'After updating ptah: list what this project needs to do (config keys, published views/stubs, FK, migrations)';

    public function handle(): int
    {
        $findings = UpgradeInspector::inspect(dirname(__DIR__, 2));
        $warnings = count(array_filter($findings, fn ($f) => $f['level'] === 'warn'));
        $exit = $this->option('strict') && $warnings > 0 ? self::FAILURE : self::SUCCESS;

        if ($this->option('json')) {
            $this->line((string) json_encode(['version' => PtahVersion::current(), 'findings' => $findings], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $exit;
        }

        if ($findings === []) {
            $this->components->info('ptah '.PtahVersion::current().': nothing to do in this project.');

            return self::SUCCESS;
        }

        foreach ($findings as $f) {
            $this->line(($f['level'] === 'warn' ? '<fg=yellow>⚠</>' : '<fg=gray>·</>')." [{$f['check']}] {$f['message']}");
            $this->line("    → {$f['action']}");
        }

        $this->newLine();
        $this->line("{$warnings} to act on, ".(count($findings) - $warnings).' informational.');

        return $exit;
    }
}

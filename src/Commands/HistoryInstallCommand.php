<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * `ptah:history:install` — writes the record-history migration into the app.
 *
 * The package's own migrations are auto-discovered (loadMigrationsFrom), so a
 * new one would run on the host's next `php artisan migrate` without anyone
 * asking — SchemaIsFrozenTest forbids exactly that. Record history is opt-in,
 * so its table is too: this copies the migration into database/migrations,
 * where the host reviews it, commits it and runs it when it decides to.
 *
 * Idempotent: an existing *_create_ptah_record_history_table.php is left alone.
 */
class HistoryInstallCommand extends Command
{
    protected $signature = 'ptah:history:install';

    protected $description = 'Write the migration for record history (Ptah\Traits\RecordsHistory) into database/migrations';

    public function handle(Filesystem $files): int
    {
        $existing = glob(database_path('migrations/*_create_ptah_record_history_table.php')) ?: [];

        if ($existing !== []) {
            $this->components->info('Already installed: '.basename($existing[0]));
        } else {
            $path = database_path('migrations/'.date('Y_m_d_His').'_create_ptah_record_history_table.php');
            $files->ensureDirectoryExists(dirname($path));
            $files->copy(__DIR__.'/../Stubs/migration.record-history.stub', $path);
            $this->components->info('Written: database/migrations/'.basename($path));
        }

        $this->line('Next:');
        $this->line('  1. php artisan migrate');
        $this->line('  2. use \\Ptah\\Traits\\RecordsHistory; in each model whose changes you want recorded');
        $this->line('  The edit modal of its BaseCrud screen then shows a History button.');

        return self::SUCCESS;
    }
}

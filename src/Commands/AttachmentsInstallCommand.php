<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * `ptah:attachments:install` — writes the attachments migration into the app.
 * Same reason as ptah:history:install: package migrations are auto-discovered,
 * so an opt-in table is written into database/migrations for the host to run.
 */
class AttachmentsInstallCommand extends Command
{
    protected $signature = 'ptah:attachments:install';

    protected $description = 'Write the migration for record attachments (Ptah\Traits\HasAttachments) into database/migrations';

    public function handle(Filesystem $files): int
    {
        $existing = glob(database_path('migrations/*_create_ptah_attachments_table.php')) ?: [];

        if ($existing !== []) {
            $this->components->info('Already installed: '.basename($existing[0]));
        } else {
            $path = database_path('migrations/'.date('Y_m_d_His').'_create_ptah_attachments_table.php');
            $files->ensureDirectoryExists(dirname($path));
            $files->copy(__DIR__.'/../Stubs/migration.attachments.stub', $path);
            $this->components->info('Written: database/migrations/'.basename($path));
        }

        $this->line('Next:');
        $this->line('  1. php artisan migrate');
        $this->line('  2. use \\Ptah\\Traits\\HasAttachments; in each model that takes files');
        $this->line('  Files go to the disk in PTAH_ATTACHMENTS_DISK (default: local, private).');

        return self::SUCCESS;
    }
}

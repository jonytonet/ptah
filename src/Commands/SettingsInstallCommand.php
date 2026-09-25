<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * `ptah:settings:install` — writes the settings migration into the app and
 * publishes config/ptah-settings.php (where the settings are declared).
 */
class SettingsInstallCommand extends Command
{
    protected $signature = 'ptah:settings:install';

    protected $description = 'Write the migration for system settings (ptah_setting(), /ptah-settings) and publish config/ptah-settings.php';

    public function handle(Filesystem $files): int
    {
        $existing = glob(database_path('migrations/*_create_ptah_settings_table.php')) ?: [];

        if ($existing !== []) {
            $this->components->info('Already installed: '.basename($existing[0]));
        } else {
            $path = database_path('migrations/'.date('Y_m_d_His').'_create_ptah_settings_table.php');
            $files->ensureDirectoryExists(dirname($path));
            $files->copy(__DIR__.'/../Stubs/migration.settings.stub', $path);
            $this->components->info('Written: database/migrations/'.basename($path));
        }

        $config = config_path('ptah-settings.php');
        if (! is_file($config)) {
            $files->ensureDirectoryExists(dirname($config));
            $files->copy(__DIR__.'/../../config/ptah-settings.php', $config);
            $this->components->info('Written: config/ptah-settings.php');
        }

        $this->line('Next:');
        $this->line('  1. php artisan migrate');
        $this->line('  2. declare your settings in config/ptah-settings.php (definitions)');
        $this->line('  3. edit them at /ptah-settings; read them with ptah_setting(\'key\')');

        return self::SUCCESS;
    }
}

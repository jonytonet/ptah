<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

/**
 * Ptah package installation command.
 *
 * Usage: php artisan ptah:install
 */
class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ptah:install
                            {--force    : Overwrite existing files}
                            {--skip-npm : Skip npm install and npm run build}
                            {--demo     : Install demo data (companies, departments, roles and menu)}
                            {--boost    : Install Laravel Boost for AI agent integration (Copilot, Claude, Cursor)}';

    /**
     * @var string
     */
    protected $description = 'Installs the Ptah package: publishes config, stubs and runs migrations.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Runs the installation command.
     */
    public function handle(): int
    {
        $this->components->info('Installing Ptah...');

        $this->publishConfig();
        $this->publishStubs();
        $this->publishMigrations();
        $this->publishLang();
        $this->publishMenuRegistry();
        $this->updateAppCss();
        $this->runMigrations();
        $this->createStorageLink();
        $this->seedDefaultAdmin();
        $this->seedDemoData();
        $this->installBoost();
        $this->installNodeDependencies();

        $this->newLine();
        $this->components->info('Ptah installed successfully!');
        $this->newLine();
        $adminEmail = config('ptah.permissions.admin_email', 'admin@admin.com');
        $this->line('  <fg=blue>Next steps:</>');
        $this->line('  1. Review the <fg=yellow>config/ptah.php</> file');
        $this->line('  2. Add <fg=yellow>HasUserPreferences</> to your User model:');
        $this->line('     <fg=gray>use Ptah\\Traits\\HasUserPreferences;</>');
        $this->line('  3. Enable required modules:');
        $this->line('     <fg=green>php artisan ptah:module auth</>  <fg=gray>(login, 2FA, profile)</>');
        $this->line('     <fg=green>php artisan ptah:module menu</>  <fg=gray>(dynamic sidebar)</>');
        $this->line('     <fg=green>php artisan ptah:module company</>  <fg=gray>(multi-company)</>');
        $this->line('     <fg=green>php artisan ptah:module permissions</>  <fg=gray>(RBAC)</>');
        $this->line('  4. Sign in with: <fg=yellow>' . $adminEmail . '</>');
        $this->line('  5. Scaffold entities with: <fg=green>php artisan ptah:forge {Entity}</>');
        $this->line('  6. For AI agent integration:');
        $this->line('     <fg=green>php artisan ptah:install --boost</>');
        $this->line('  7. For Docker environment (Dockerfile, Nginx, docker-compose):');
        $this->line('     <fg=green>php artisan vendor:publish --tag=ptah-docker</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Publishes the configuration file.
     */
    protected function publishConfig(): void
    {
        $this->components->task('Publishing configuration', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-config',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Publishes the stubs.
     */
    protected function publishStubs(): void
    {
        $this->components->task('Publishing stubs', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-stubs',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Publishes migrations.
     */
    protected function publishMigrations(): void
    {
        $this->components->task('Publishing migrations', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-migrations',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Publishes language files.
     */
    protected function publishLang(): void
    {
        $this->components->task('Publishing translations', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-lang',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Publishes MenuRegistry.php for auto-menu system.
     */
    protected function publishMenuRegistry(): void
    {
        $this->components->task('Publishing MenuRegistry', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-menu-registry',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Injects Ptah design tokens and source paths into the project's app.css.
     * Required for Tailwind CSS v4 to compile Forge classes correctly.
     */
    protected function updateAppCss(): void
    {
        $this->components->task('Configuring Tailwind CSS (app.css)', function () {
            $appCss = resource_path('css/app.css');

            if (! file_exists($appCss)) {
                $this->components->warn('resources/css/app.css not found — configure Tailwind tokens manually.');
                return;
            }

            $content = file_get_contents($appCss);

            // Add @source for Ptah package views (if not already present)
            $ptahSource = "@source '../../vendor/jonytonet/ptah/resources/views/**/*.blade.php';";
            if (! str_contains($content, 'vendor/jonytonet/ptah')) {
                // Insert after the last existing @source directive
                if (preg_match('/(@source[^\n]+\n)(?!@source)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $content   = substr_replace($content, $ptahSource . "\n", $insertPos, 0);
                } else {
                    // Insert after @import 'tailwindcss'
                    $content = str_replace("@import 'tailwindcss';", "@import 'tailwindcss';\n{$ptahSource}", $content);
                }
            }

            // Add @custom-variant dark for class-based dark mode (Tailwind v4 requires this)
            // Without it, dark: utilities respond to prefers-color-scheme (OS), not the .dark class.
            $darkVariant = "@custom-variant dark (&:where(.dark, .dark *));";
            if (! str_contains($content, '@custom-variant dark')) {
                // Insert after @source block or after @import if no @source
                if (preg_match('/(@source[^\n]+\n)(?!@source)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $content   = substr_replace($content, $darkVariant . "\n", $insertPos, 0);
                } else {
                    $content = str_replace("@import 'tailwindcss';", "@import 'tailwindcss';\n{$darkVariant}", $content);
                }
                file_put_contents($appCss, $content);
            }

            // Add Forge design tokens inside @theme (if not already present)
            if (str_contains($content, '--color-primary')) {
                return; // already configured
            }

            $themeTokens = <<<'CSS'

    /* ── Ptah Forge design tokens ── */
    --color-primary:       #5b21b6;
    --color-primary-light: #ede9fe;
    --color-primary-dark:  #4c1d95;
    --color-success:       #10b981;
    --color-success-light: #d1fae5;
    --color-success-dark:  #059669;
    --color-danger:        #ef4444;
    --color-danger-light:  #fee2e2;
    --color-danger-dark:   #dc2626;
    --color-warn:          #f59e0b;
    --color-warn-light:    #fef3c7;
    --color-warn-dark:     #d97706;
    --color-dark:          #1e293b;
    --color-dark-light:    #f1f5f9;
    --color-dark-dark:     #0f172a;
CSS;

            // Insere os tokens dentro do @theme existente ou cria um novo
            if (str_contains($content, '@theme {')) {
                $content = str_replace('@theme {', '@theme {' . $themeTokens, $content);
            } else {
                $content .= "\n@theme {{$themeTokens}\n}\n";
            }

            file_put_contents($appCss, $content);
        });
    }

    /**
     * Runs migrations.
     *
     * Idempotent: if the main Ptah tables already exist
     * (database was migrated previously), skips without prompting.
     */
    protected function runMigrations(): void
    {
        // Detect re-execution (--boost, --force etc.) without re-creation
        if (Schema::hasTable('ptah_companies') && Schema::hasTable('users')) {
            $this->components->info('Migrations already run — skipping.');
            return;
        }

        if ($this->confirm('Run migrations now?', true)) {
            $this->components->task('Running migrations', function () {
                $this->call('migrate');
            });
        }
    }

    /**
     * Creates the public/storage symlink → storage/app/public.
     * Required for uploads (profile photos, logos, etc.)
     */
    protected function createStorageLink(): void
    {
        $this->components->task('Creating public/storage link', function () {
            if (file_exists(public_path('storage'))) {
                $this->components->warn('Link public/storage already exists, skipping.');
                return;
            }
            $this->call('storage:link');
        });
    }

    /**
     * Creates the default company and admin user from config/.env values.
     * Only runs if Ptah migrations have already been executed.
     */
    protected function seedDefaultAdmin(): void
    {
        if (! Schema::hasTable('ptah_companies')) {
            $this->components->warn('Ptah tables not found — run `php artisan migrate` and then `php artisan db:seed --class=\\Ptah\\Seeders\\DefaultAdminSeeder`.');
            return;
        }

        $this->components->task('Creating default company and admin user', function () {
            $this->call('db:seed', ['--class' => \Ptah\Seeders\DefaultAdminSeeder::class]);
        });
    }

    /**
     * Seeds demo data when --demo is passed.
     */
    protected function seedDemoData(): void
    {
        if (! $this->option('demo')) {
            return;
        }

        if (! Schema::hasTable('ptah_companies')) {
            $this->components->warn('Ptah tables not found — run `php artisan migrate` before running the demo seeder.');
            return;
        }

        $this->components->task('Installing demo data', function () {
            $this->call('db:seed', ['--class' => \Ptah\Seeders\PtahDemoSeeder::class]);
        });

        $this->components->info('Demo data installed — sign in to explore it.');
    }

    /**
     * Installs Laravel Boost when --boost is passed.
     *
     * Runs:
     *   1. composer require laravel/boost --dev
     *   2. php artisan boost:install
     */
    protected function installBoost(): void
    {
        if (! $this->option('boost')) {
            return;
        }

        $this->newLine();
        $this->components->info('Installing Laravel Boost for AI agent integration...');

        $this->components->task(
            'Installing laravel/boost via Composer',
            function () {
                $composer = $this->findComposer();
                $this->runProcess([$composer, 'require', 'laravel/boost', '--dev'], base_path());
            }
        );

        // Verifica se o pacote foi de fato instalado (independente do exit code no Windows)
        if (! file_exists(base_path('vendor/laravel/boost'))) {
            $this->components->warn(
                'Could not install laravel/boost automatically. '.PHP_EOL.
                'Run manually: <fg=green>composer require laravel/boost --dev</>'
            );
            return;
        }

        $this->components->task('Configuring Boost (boost:install)', function () {
            // Reload providers so BoostServiceProvider becomes available
            $this->call('package:discover', ['--ansi' => true]);

            // Check the command is registered before calling it
            if (! $this->getApplication()->has('boost:install')) {
                $this->components->warn(
                    'The boost:install command is not available in this session.'.PHP_EOL.
                    'Run manually: <fg=green>php artisan boost:install</>'
                );
                return;
            }

            try {
                $this->call('boost:install');
            } catch (\Throwable $e) {
                $this->components->warn(
                    'Failed to run boost:install: '.$e->getMessage().PHP_EOL.
                    'Run manually: <fg=green>php artisan boost:install</>'
                );
            }
        });

        $this->components->info(
            'Laravel Boost installed! Ptah guidelines will be automatically loaded by AI agents.'
        );
    }

    /**
     * Detects the Composer executable available on the system.
     */
    protected function findComposer(): string
    {
        $composerPath = base_path('composer.phar');

        if (file_exists($composerPath)) {
            return implode(' ', [PHP_BINARY, $composerPath]);
        }

        return 'composer';
    }

    /**
     * Runs npm install and npm run build if package.json exists.
     * Can be skipped with --skip-npm.
     */
    protected function installNodeDependencies(): void
    {
        if ($this->option('skip-npm')) {
            return;
        }

        if (! file_exists(base_path('package.json'))) {
            return;
        }

        // Detect npm or yarn
        $npm = $this->findNodePackageManager();

        if (! $npm) {
            $this->components->warn('npm/yarn not found — install dependencies manually: npm install && npm run build');
            return;
        }

        $this->components->task('Installing Node dependencies (npm install)', function () use ($npm) {
            $exitCode = $this->runProcess([$npm, 'install'], base_path());
            return $exitCode === 0;
        });

        $this->components->task('Building assets (npm run build)', function () use ($npm) {
            $exitCode = $this->runProcess([$npm, 'run', 'build'], base_path());
            return $exitCode === 0;
        });
    }

    /**
     * Runs an external process and streams its output in real time.
     */
    protected function runProcess(array $command, string $cwd): int
    {
        $process = new \Symfony\Component\Process\Process($command, $cwd);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            $this->getOutput()->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }

    /**
     * Detects the available Node package manager.
     */
    protected function findNodePackageManager(): ?string
    {
        foreach (['npm', 'yarn'] as $manager) {
            $check = new \Symfony\Component\Process\Process(
                PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'where', $manager] : ['which', $manager]
            );
            $check->run();
            if ($check->isSuccessful()) {
                return $manager;
            }
        }
        return null;
    }
}

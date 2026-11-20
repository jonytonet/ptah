<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Ptah\Seeders\DefaultAdminSeeder;
use Ptah\Seeders\PtahDemoSeeder;
use Ptah\Support\BoostPackages;
use Ptah\Support\CssFramework;
use Ptah\Support\ViteConfigPatcher;
use Symfony\Component\Process\Process;

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
                            {--stack=   : What draws the screens: livewire (default), react or vue}
                            {--css=     : CSS framework of the host: tailwind (default) or bootstrap}
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
        $stack = $this->resolveStack();
        $css = $this->resolveCss();

        if ($stack === null || $css === null) {
            return self::FAILURE;
        }

        $this->components->info("Installing Ptah ({$stack} stack)...");

        $this->publishConfig();
        $this->publishStubs();
        $this->publishMigrations();
        $this->publishLang();
        $this->publishMenuRegistry();
        $this->configureCss($css, $stack);
        $this->installStack($stack);
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
        $this->line('  2. Optional: add <fg=yellow>HasUserPreferences</> to your IDENTITY model (one only) for the $user->getPreference()/setPreference() API');
        $this->line('     <fg=gray>use Ptah\\Traits\\HasUserPreferences;</> (BaseCrud persists preferences without it)');
        $this->line('  3. Enable required modules:');
        $this->line('     <fg=green>php artisan ptah:module auth</>  <fg=gray>(login, 2FA, profile)</>');
        $this->line('     <fg=green>php artisan ptah:module menu</>  <fg=gray>(dynamic sidebar)</>');
        $this->line('     <fg=green>php artisan ptah:module company</>  <fg=gray>(multi-company)</>');
        $this->line('     <fg=green>php artisan ptah:module permissions</>  <fg=gray>(RBAC)</>');
        $this->line('  4. Sign in with: <fg=yellow>'.$adminEmail.'</>');
        $this->line('  5. Scaffold entities with: <fg=green>php artisan ptah:forge {Entity}</>');
        if ($stack !== 'livewire') {
            $this->line("     <fg=gray>({$stack} stack: each entity gets a page, a line in routes/ptah-screens.php and the same back end)</>");
        }
        if ($css === CssFramework::BOOTSTRAP) {
            $this->line('     <fg=gray>(Bootstrap: in a layout of your own, put the x-ptah-assets component in the head, before your CSS — docs/Css.md)</>');
        }
        $this->line('  6. For AI agent integration:');
        $this->line('     <fg=green>php artisan ptah:install --boost</>');
        $this->line('  7. For Docker environment (Dockerfile, Nginx, docker-compose):');
        $this->line('     <fg=green>php artisan vendor:publish --tag=ptah-docker</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * The stack to install: --stack, or the one already configured (re-running
     * the installer keeps it), or livewire.
     */
    protected function resolveStack(): ?string
    {
        $stack = strtolower((string) ($this->option('stack') ?: config('ptah.stack', 'livewire')));

        if (! in_array($stack, ['livewire', 'react', 'vue'], true)) {
            $this->components->error("Unknown stack '{$stack}'. Use livewire, react or vue.");

            return null;
        }

        return $stack;
    }

    /**
     * The host's CSS framework: --css, or the one already configured (re-running
     * the installer keeps it), or tailwind.
     */
    protected function resolveCss(): ?string
    {
        $css = strtolower((string) ($this->option('css') ?: CssFramework::current()));

        if (! in_array($css, CssFramework::FRAMEWORKS, true)) {
            $this->components->error("Unknown CSS framework '{$css}'. Use tailwind or bootstrap.");

            return null;
        }

        return $css;
    }

    /**
     * Tailwind (default): wires the host's app.css, as always. Bootstrap: leaves
     * app.css, vite.config and package.json alone — the host keeps no Tailwind —
     * and publishes ptah's pre-compiled stylesheet instead (docs/Css.md).
     */
    protected function configureCss(string $css, string $stack): void
    {
        if ($this->option('css')) {
            $this->components->task("Setting the CSS framework to {$css} (.env)", function () use ($css) {
                $this->setEnv('PTAH_CSS', $css);
                config(['ptah.css.framework' => $css]);
            });
        }

        if ($css !== CssFramework::BOOTSTRAP) {
            $this->updateAppCss();

            return;
        }

        $this->components->task('Publishing ptah.css (public/vendor/ptah)', function () {
            $this->callSilent('vendor:publish', ['--tag' => 'ptah-assets', '--force' => true]);
        });

        // A host view compiled before the install has no scope marker on its
        // <x-forge-*> tags (CssFramework::markHostComponents runs at compile time).
        $this->components->task('Clearing compiled views', function () {
            $this->callSilent('view:clear');
        });

        if ($stack !== 'livewire') {
            $this->components->warn("--css=bootstrap styles the Livewire screens; the {$stack} screens keep their own stylesheet (resources/js/ptah-ui.css).");
        }
    }

    /**
     * The React or Vue stack: turns the API on, writes the front-end entry and
     * the screens file, prepares vite.config and adds the npm packages. The
     * Livewire stack needs none of it.
     */
    protected function installStack(string $stack): void
    {
        if ($stack === 'livewire') {
            return;
        }

        $this->components->task("Enabling the {$stack} stack (.env)", function () use ($stack) {
            $this->setEnv('PTAH_STACK', $stack);
            $this->setEnv('PTAH_CRUD_API', 'true');
            config(['ptah.stack' => $stack, 'ptah.crud_api.enabled' => true]);
        });

        $this->components->task('Publishing the front-end entry', function () use ($stack) {
            $entry = base_path($stack === 'vue' ? 'resources/js/ptah-app.js' : 'resources/js/ptah-app.jsx');

            if (! $this->files->exists($entry) || $this->option('force')) {
                $this->files->ensureDirectoryExists(dirname($entry));
                $this->files->copy(__DIR__."/../Stubs/stack/ptah-app.{$stack}.stub", $entry);
            }

            $screens = base_path('routes/ptah-screens.php');
            if (! $this->files->exists($screens)) {
                $this->files->ensureDirectoryExists(dirname($screens));
                $this->files->copy(__DIR__.'/../Stubs/stack/ptah-screens.stub', $screens);
            }
        });

        $this->patchViteConfig($stack);
        $this->addStackPackages($stack);
    }

    protected function patchViteConfig(string $stack): void
    {
        $entry = $stack === 'vue' ? 'resources/js/ptah-app.js' : 'resources/js/ptah-app.jsx';
        $path = collect(['vite.config.js', 'vite.config.ts', 'vite.config.mjs'])
            ->map(fn (string $name) => base_path($name))
            ->first(fn (string $candidate) => $this->files->exists($candidate));

        if ($path === null) {
            $this->components->warn('vite.config.js not found — add the '.$stack.' plugin, the entry and the @ptah alias by hand (docs/Stacks.md).');

            return;
        }

        $result = ViteConfigPatcher::patch($this->files->get($path), $stack, $entry);
        $this->files->put($path, $result['content']);

        $this->components->task('Configuring '.basename($path), fn () => $result['missing'] === []);

        foreach ($result['missing'] as $line) {
            $this->components->warn('Add to '.basename($path)." by hand: {$line}");
        }
    }

    /**
     * Adds the stack's packages to package.json — `npm install` (below) fetches
     * them. Only what is missing; a version the app already pins is kept.
     */
    protected function addStackPackages(string $stack): void
    {
        $path = base_path('package.json');

        if (! $this->files->exists($path)) {
            $this->components->warn('package.json not found — install the '.$stack.' packages by hand (docs/Stacks.md).');

            return;
        }

        $package = json_decode($this->files->get($path), true);

        if (! is_array($package)) {
            return;
        }

        $wanted = $stack === 'vue'
            ? ['vue' => '^3.5.0', '@vitejs/plugin-vue' => '^5.2.0 || ^6.0.0', 'typescript' => '^5.7.0']
            : ['react' => '^19.0.0', 'react-dom' => '^19.0.0', '@vitejs/plugin-react' => '^4.3.0 || ^5.0.0', 'typescript' => '^5.7.0'];

        $present = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);
        $added = array_diff_key($wanted, $present);

        if ($added === []) {
            return;
        }

        $package['devDependencies'] = array_merge($package['devDependencies'] ?? [], $added);
        ksort($package['devDependencies']);

        $this->components->task('Adding '.implode(', ', array_keys($added)).' to package.json', function () use ($path, $package) {
            $this->files->put($path, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        });
    }

    /** Sets (or replaces) a key in .env. */
    protected function setEnv(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! $this->files->exists($path)) {
            return;
        }

        $content = $this->files->get($path);
        $line = "{$key}={$value}";

        $content = preg_match("/^{$key}=.*$/m", $content)
            ? (string) preg_replace("/^{$key}=.*$/m", $line, $content)
            : rtrim($content)."\n".$line."\n";

        $this->files->put($path, $content);
    }

    /**
     * Publishes the configuration file.
     */
    protected function publishConfig(): void
    {
        $this->components->task('Publishing configuration', function () {
            $this->call('vendor:publish', [
                '--tag' => 'ptah-config',
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
                '--tag' => 'ptah-stubs',
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
                '--tag' => 'ptah-migrations',
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
                '--tag' => 'ptah-lang',
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
                '--tag' => 'ptah-menu-registry',
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

            // Import Ptah's compiled component styles (.ptah-c-* BaseCrud classes and the
            // neutral --ptah-* tokens). Without this, Tailwind never sees those classes and
            // a fresh install ships a completely unstyled BaseCrud UI. It uses
            // ptah-components.css (not forge.css) on purpose: forge.css re-declares
            // `@import "tailwindcss"`, which would double-import Tailwind in the host.
            // Checked before the "already configured" early return below so that hosts
            // which ran an older version of this command (missing only this import) also
            // get it fixed by simply re-running `php artisan ptah:install`.
            $componentsImport = "@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';";
            if (! str_contains($content, 'ptah-components.css')) {
                $content = str_replace(
                    "@import 'tailwindcss';",
                    "@import 'tailwindcss';\n{$componentsImport}",
                    $content
                );
            }

            // Add @source for Ptah package views (if not already present)
            // Guard narrowed to the views path (not just 'vendor/jonytonet/ptah'): the
            // components import above also contains that prefix, which would otherwise
            // make this check always true and this @source line would never be added.
            $ptahSource = "@source '../../vendor/jonytonet/ptah/resources/views/**/*.blade.php';";
            if (! str_contains($content, 'vendor/jonytonet/ptah/resources/views')) {
                // Insert after the last existing @source directive
                if (preg_match('/(@source[^\n]+\n)(?!@source)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $content = substr_replace($content, $ptahSource."\n", $insertPos, 0);
                } else {
                    // Insert after @import 'tailwindcss'
                    $content = str_replace("@import 'tailwindcss';", "@import 'tailwindcss';\n{$ptahSource}", $content);
                }
            }

            // Add @custom-variant dark for class-based dark mode (Tailwind v4 requires this)
            // Without it, dark: utilities respond to prefers-color-scheme (OS), not the .dark class.
            $darkVariant = '@custom-variant dark (&:where(.dark, .dark *));';
            if (! str_contains($content, '@custom-variant dark')) {
                // Insert after @source block or after @import if no @source
                if (preg_match('/(@source[^\n]+\n)(?!@source)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $content = substr_replace($content, $darkVariant."\n", $insertPos, 0);
                } else {
                    $content = str_replace("@import 'tailwindcss';", "@import 'tailwindcss';\n{$darkVariant}", $content);
                }
                file_put_contents($appCss, $content);
            }

            // Add Forge design tokens inside @theme (if not already present)
            if (str_contains($content, '--color-primary')) {
                // Persist even here: on a re-run against an app already fully configured
                // token-wise, this is the only remaining write that can flush the
                // components import added above (e.g. a host installed before this fix).
                file_put_contents($appCss, $content);

                return; // already configured
            }

            $themeTokens = <<<'CSS'

    /* ── Ptah Forge design tokens ──
       Estes literais sao o PONTO DE PARTIDA do app.css. Em runtime o
       `partials.theme-colors` injeta um :root depois do @vite, com os valores de
       `ptah.colors` — entao o que vale na tela e o config, nao o que esta aqui.

       Mesmo assim eles precisam bater com os defaults do config, e por muito
       tempo o primary nao batia: aqui #1e40af (azul) contra #5b21b6 (violeta) em
       config/ptah.php e em core.md. Nao era bug funcional, porque o :root do
       config vence por ordem — era pior: um valor inalcancavel gravado no
       app.css de todo projeto novo, e quem abrisse aquele arquivo concluiria que
       a cor da marca e azul. InstallStubColorParityTest agora casa os dois. */
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
                $content = str_replace('@theme {', '@theme {'.$themeTokens, $content);
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

            if (! file_exists(public_path('storage'))) {
                $this->components->warn(
                    'public/storage was NOT created (filesystem without symlink/junction support — network share, mounted volume).'.PHP_EOL.
                    'Uploads will 404 until this is fixed. Options:'.PHP_EOL.
                    '  1) run the project from a local disk and re-run: php artisan storage:link'.PHP_EOL.
                    "  2) or point the 'public' disk at public_path('storage') in config/filesystems.php and create that directory."
                );
            }
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
            $this->call('db:seed', ['--class' => DefaultAdminSeeder::class]);
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
            $this->call('db:seed', ['--class' => PtahDemoSeeder::class]);
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

            // 1.53.0: without interaction boost:install keeps only the
            // packages already in boost.json — none on a first install — so
            // ptah's guidelines never landed. List ptah first.
            if (! BoostPackages::includePtah()) {
                $this->components->warn('boost.json is not valid JSON — add "jonytonet/ptah" to its "packages" by hand.');
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

        // Boost installs a third-party package's guidelines/skills only when the
        // package is listed in boost.json (done above). Publishing the skills
        // into .claude/skills too keeps them usable whatever Boost decides.
        $this->publishSkills();

        $this->components->info(
            'Laravel Boost installed. Ptah skills published to .claude/skills for AI agents.'
        );
    }

    /**
     * Copies the Ptah agent skills into the app's .claude/skills directory.
     * Idempotent — safe to re-run; pass --force to overwrite on upgrades.
     */
    protected function publishSkills(): void
    {
        $this->call('vendor:publish', array_filter([
            '--tag' => 'ptah-skills',
            '--force' => $this->option('force') ?: null,
        ]));
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
        $process = new Process($command, $cwd);
        $process->setTimeout((int) config('ptah.process_timeout', 300));
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
            $check = new Process(
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

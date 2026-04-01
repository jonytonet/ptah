<?php

declare(strict_types=1);

namespace Ptah\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Enables optional Ptah modules: auth, menu, company, permissions.
 *
 * Usage:
 *   php artisan ptah:module auth
 *   php artisan ptah:module menu
 *   php artisan ptah:module company
 *   php artisan ptah:module permissions
 *   php artisan ptah:module --list
 *   php artisan ptah:module --force
 */
class ModuleCommand extends Command
{
    protected $signature = 'ptah:module
                            {module? : Module name to enable (auth, menu, company, permissions)}
                            {--list  : List available modules and their states}
                            {--force : Overwrite existing files}';

    protected $description = 'Enables optional Ptah modules (auth, menu, company, permissions).';

    /** Available modules: name => env key */
    protected array $modules = [
        'auth'        => 'PTAH_MODULE_AUTH',
        'menu'        => 'PTAH_MODULE_MENU',
        'company'     => 'PTAH_MODULE_COMPANY',
        'permissions' => 'PTAH_MODULE_PERMISSIONS',
        'api'         => 'PTAH_MODULE_API',
        'ai_agent'    => 'PTAH_MODULE_AI_AGENT',
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listModules();
        }

        $module = $this->argument('module')
            ?? $this->choice('Which module would you like to enable?', array_keys($this->modules));

        if (!array_key_exists($module, $this->modules)) {
            $this->components->error("Module '{$module}' not found. Available modules: " . implode(', ', array_keys($this->modules)));
            return self::FAILURE;
        }

        $this->components->info("Enabling module: {$module}");

        match ($module) {
            'auth'        => $this->activateAuth(),
            'menu'        => $this->activateMenu(),
            'company'     => $this->activateCompany(),
            'permissions' => $this->activatePermissions(),
            'api'         => $this->activateApi(),
            'ai_agent'    => $this->activateAiAgent(),
        };

        $this->setEnvValue($this->modules[$module], 'true');

        $this->newLine();
        $this->components->info("Module '{$module}' enabled successfully!");
        $this->showNextSteps($module);

        return self::SUCCESS;
    }

    protected function activateAuth(): void
    {
        $this->components->task('Publishing 2FA migration', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-auth',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Running migrations', function () {
            $this->call('migrate');
        });
    }

    protected function activateMenu(): void
    {
        $this->components->task('Publishing menu migration', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-menu',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Running migrations', function () {
            $this->call('migrate');
        });
    }

    protected function activateCompany(): void
    {
        $this->components->task('Publishing company migrations', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-company',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Running migrations', function () {
            $this->call('migrate');
        });

        $this->components->task('Seeding default company', function () {
            $this->call('db:seed', ['--class' => 'Ptah\\Seeders\\DefaultCompanySeeder']);
        });
    }

    protected function activatePermissions(): void
    {
        // Ensure the company module is also enabled
        if (!config('ptah.modules.company')) {
            $this->components->warn('Enabling dependency: company module');
            $this->activateCompany();
            $this->setEnvValue('PTAH_MODULE_COMPANY', 'true');
        }

        $this->components->task('Publishing permissions migrations', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-permissions',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Running migrations', function () {
            $this->call('migrate');
        });

        $this->components->task('Seeding default admin', function () {
            $this->call('db:seed', ['--class' => 'Ptah\\Seeders\\DefaultAdminSeeder']);
        });

        $email    = config('ptah.permissions.admin_email', 'admin@admin.com');
        $password = config('ptah.permissions.admin_password') ?: 'admin@123';

        $this->newLine();
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->line('  ║  <fg=green>Admin created successfully!</>             ║');
        $this->line("  ║  <fg=yellow>E-mail   :</>  {$email}        ");
        $this->line("  ║  <fg=yellow>Password :</>  {$password}");
        $this->line('  ║  <fg=red>⚠ Change your password on first login!</>  ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function activateAiAgent(): void
    {
        $this->components->task('Installing prism-php/prism', function () {
            $process = new \Symfony\Component\Process\Process(
                ['composer', 'require', 'prism-php/prism'],
                base_path()
            );
            $process->setTimeout(300);
            $process->run();

            return $process->isSuccessful();
        });

        $this->components->task('Publishing AI Agent migrations', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-ai-agent',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Running migrations', function () {
            $this->call('migrate');
        });
    }

    protected function activateApi(): void
    {
        $this->components->task('Instalando darkaonline/l5-swagger', function () {
            $process = new \Symfony\Component\Process\Process(
                ['composer', 'require', 'darkaonline/l5-swagger'],
                base_path()
            );
            $process->setTimeout(300);
            $process->run();

            return $process->isSuccessful();
        });

        $this->components->task('Publishing API base classes', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-api',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Publishing L5-Swagger config', function () {
            if (class_exists(\L5Swagger\L5SwaggerServiceProvider::class)) {
                $this->call('vendor:publish', [
                    '--provider' => 'L5Swagger\\L5SwaggerServiceProvider',
                    '--force'    => false,
                ]);
            }
        });

        // Replace placeholders in the published SwaggerInfo.php
        $swaggerInfoPath = app_path('Http/Controllers/API/SwaggerInfo.php');

        if (file_exists($swaggerInfoPath)) {
            $content = file_get_contents($swaggerInfoPath);
            $content = str_replace('{{ APP_NAME }}',           config('app.name', 'App'),                     $content);
            $content = str_replace('{{ APP_URL }}',            rtrim(config('app.url', 'http://localhost'), '/'), $content);
            $content = str_replace('{{ APP_CONTACT_EMAIL }}',  env('MAIL_FROM_ADDRESS', 'contact@example.com'), $content);
            file_put_contents($swaggerInfoPath, $content);
        }
    }

    protected function listModules(): int
    {
        $this->newLine();
        $this->line('  <fg=blue>Available Ptah modules:</>');
        $this->newLine();

        $rows = [];
        foreach ($this->modules as $name => $envKey) {
            $active = config("ptah.modules.{$name}") ? '<fg=green>✔ active</>' : '<fg=red>✘ inactive</>';
            $rows[] = [$name, $envKey, $active];
        }

        $this->table(['Module', '.env Variable', 'Status'], $rows);
        $this->newLine();
        $this->line('  To enable: <fg=green>php artisan ptah:module {module}</>');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        if (Str::contains($contents, $key . '=')) {
            file_put_contents(
                $envPath,
                preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents)
            );
        } else {
            file_put_contents($envPath, $contents . PHP_EOL . "{$key}={$value}" . PHP_EOL);
        }
    }

    protected function showNextSteps(string $module): void
    {
        $this->line('  <fg=blue>Next steps:</>');

        if ($module === 'auth') {
            $this->line('  1. Make sure your User model uses <fg=yellow>HasUserPreferences</>');
            $this->line('  2. Configure <fg=yellow>config/ptah.php</> (auth section)');
            $this->line('  3. Add the authentication middleware to desired routes');
            $this->line('  4. For TOTP 2FA install: <fg=green>composer require pragmarx/google2fa-laravel bacon/bacon-qr-code</>');
        }

        if ($module === 'menu') {
            $this->line('  1. Set <fg=yellow>PTAH_MENU_DRIVER=database</> in .env (default: config)');
            $this->line('  2. Manage menu items at <fg=green>/ptah-menu</> (requires the auth module)');
        }

        if ($module === 'company') {
            $this->line('  1. Visit <fg=green>/ptah-companies</> to manage companies');
            $this->line('  2. Configure <fg=yellow>ptah.company</> in config/ptah.php to customise behaviour');
            $this->line('  3. Use <fg=yellow>CompanyService::getCurrentCompanyId()</> in your queries');
        }

        if ($module === 'api') {
            $this->line('  1. Visit <fg=green>/api/documentation</> to see the Swagger UI');
            $this->line('  2. Regenerate docs: <fg=green>php artisan l5-swagger:generate</>  ');
            $this->line('  3. Scaffold entities with: <fg=green>php artisan ptah:forge Catalog/Product --api</>');
            $this->line('  4. ⚠  NEVER use response()->json() — use <fg=yellow>BaseResponse::</>  ');
            $this->line('  5. Configure the scan path in <fg=yellow>config/l5-swagger.php</> if needed');
        }

        if ($module === 'permissions') {
            $this->line('  1. Visit <fg=green>/ptah-pages</> and register the system pages and objects');
            $this->line('  2. In <fg=green>/ptah-roles</> create roles and configure permissions per object');
            $this->line('  3. In <fg=green>/ptah-users-acl</> assign roles to users');
            $this->line('  4. Use <fg=yellow>@ptahCan(\'key\', \'action\')</> in Blade views');
            $this->line('  5. Use <fg=yellow>Route::middleware(\'ptah.can:resource,action\')</> on routes');
            $this->line('  6. For audit logging set <fg=yellow>PTAH_PERMISSION_AUDIT=true</> in .env');
        }

        if ($module === 'ai_agent') {
            $this->line('  1. Visit <fg=green>/ptah-ai/models</> to configure your AI provider');
            $this->line('  2. Choose a provider: OpenAI, Anthropic, Google Gemini, or Ollama');
            $this->line('  3. Enter your API key and select a model (e.g. <fg=yellow>gpt-4o</>)');
            $this->line('  4. Set the config as default — the chat widget activates automatically');
            $this->line('  5. Optionally set <fg=yellow>PTAH_AI_SYSTEM_PROMPT</> in .env');
            $this->line('  6. Register custom tools in <fg=yellow>config/ptah.php</> under <fg=yellow>ai_agent.tools</>');
            $this->line('  7. See full docs: <fg=green>vendor/jonytonet/ptah/docs/AiAgent.md</>');
        }

        $this->newLine();
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Ativa módulos opcionais do Ptah: auth, menu, company, permissions.
 *
 * Uso:
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
                            {module? : Nome do módulo a ativar (auth, menu, company, permissions)}
                            {--list  : Lista os módulos disponíveis e seus estados}
                            {--force : Sobrescrever arquivos existentes}';

    protected $description = 'Ativa módulos opcionais do Ptah (auth, menu, company, permissions).';

    /** Módulos disponíveis: nome => env key */
    protected array $modules = [
        'auth'        => 'PTAH_MODULE_AUTH',
        'menu'        => 'PTAH_MODULE_MENU',
        'company'     => 'PTAH_MODULE_COMPANY',
        'permissions' => 'PTAH_MODULE_PERMISSIONS',
        'api'         => 'PTAH_MODULE_API',
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listModules();
        }

        $module = $this->argument('module')
            ?? $this->choice('Qual módulo deseja ativar?', array_keys($this->modules));

        if (!array_key_exists($module, $this->modules)) {
            $this->components->error("Módulo '{$module}' não encontrado. Módulos disponíveis: " . implode(', ', array_keys($this->modules)));
            return self::FAILURE;
        }

        $this->components->info("Ativando módulo: {$module}");

        match ($module) {
            'auth'        => $this->activateAuth(),
            'menu'        => $this->activateMenu(),
            'company'     => $this->activateCompany(),
            'permissions' => $this->activatePermissions(),
            'api'         => $this->activateApi(),
        };

        $this->setEnvValue($this->modules[$module], 'true');

        $this->newLine();
        $this->components->info("Módulo '{$module}' ativado com sucesso!");
        $this->showNextSteps($module);

        return self::SUCCESS;
    }

    protected function activateAuth(): void
    {
        $this->components->task('Publicando migration 2FA', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-auth',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Executando migrations', function () {
            $this->call('migrate');
        });
    }

    protected function activateMenu(): void
    {
        $this->components->task('Publicando migration de menus', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-menu',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Executando migrations', function () {
            $this->call('migrate');
        });
    }

    protected function activateCompany(): void
    {
        $this->components->task('Publicando migrations de empresas', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-company',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Executando migrations', function () {
            $this->call('migrate');
        });

        $this->components->task('Semeando empresa padrão', function () {
            $this->call('db:seed', ['--class' => 'Ptah\\Seeders\\DefaultCompanySeeder']);
        });
    }

    protected function activatePermissions(): void
    {
        // Garantir que company também está ativo
        if (!config('ptah.modules.company')) {
            $this->components->warn('Ativando dependência: módulo company');
            $this->activateCompany();
            $this->setEnvValue('PTAH_MODULE_COMPANY', 'true');
        }

        $this->components->task('Publicando migrations de permissões', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-permissions',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Executando migrations', function () {
            $this->call('migrate');
        });

        $this->components->task('Semeando admin padrão', function () {
            $this->call('db:seed', ['--class' => 'Ptah\\Seeders\\DefaultAdminSeeder']);
        });

        $email    = config('ptah.permissions.admin_email', 'admin@admin.com');
        $password = config('ptah.permissions.admin_password', 'admin@123');

        $this->newLine();
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->line('  ║  <fg=green>Admin criado com sucesso!</>                ║');
        $this->line("  ║  <fg=yellow>E-mail  :</>  {$email}        ");
        $this->line("  ║  <fg=yellow>Senha   :</>  {$password}");
        $this->line('  ║  <fg=red>⚠ Troque a senha no primeiro acesso!</>   ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->newLine();
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

        $this->components->task('Publicando classes base de API', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-api',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Publicando config L5-Swagger', function () {
            if (class_exists(\L5Swagger\L5SwaggerServiceProvider::class)) {
                $this->call('vendor:publish', [
                    '--provider' => 'L5Swagger\\L5SwaggerServiceProvider',
                    '--force'    => false,
                ]);
            }
        });

        // Substitui placeholders no SwaggerInfo.php publicado
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
        $this->line('  <fg=blue>Módulos disponíveis no Ptah:</>');
        $this->newLine();

        $rows = [];
        foreach ($this->modules as $name => $envKey) {
            $active = config("ptah.modules.{$name}") ? '<fg=green>✔ ativo</>' : '<fg=red>✘ inativo</>';
            $rows[] = [$name, $envKey, $active];
        }

        $this->table(['Módulo', 'Variável .env', 'Estado'], $rows);
        $this->newLine();
        $this->line('  Para ativar: <fg=green>php artisan ptah:module {módulo}</>');
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
        $this->line('  <fg=blue>Próximos passos:</>');

        if ($module === 'auth') {
            $this->line('  1. Certifique-se de que o model User usa <fg=yellow>HasUserPreferences</>');
            $this->line('  2. Configure o <fg=yellow>config/ptah.php</> (seção auth)');
            $this->line('  3. Adicione o middleware de autenticação às rotas desejadas');
            $this->line('  4. Para 2FA TOTP, instale: <fg=green>composer require pragmarx/google2fa-laravel bacon/bacon-qr-code</>');
        }

        if ($module === 'menu') {
            $this->line('  1. Defina <fg=yellow>PTAH_MENU_DRIVER=database</> no .env (padrão: config)');
            $this->line('  2. Gerencie os itens em <fg=green>/ptah-menu</> (requer módulo auth ativo)');
        }

        if ($module === 'company') {
            $this->line('  1. Acesse <fg=green>/ptah-companies</> para gerenciar empresas');
            $this->line('  2. Configure <fg=yellow>ptah.company</> no config/ptah.php para personalizar');
            $this->line('  3. Use <fg=yellow>CompanyService::getCurrentCompanyId()</> nas suas queries');
        }

        if ($module === 'api') {
            $this->line('  1. Acesse <fg=green>/api/documentation</> para ver o Swagger UI');
            $this->line('  2. Para gerar/atualizar docs: <fg=green>php artisan l5-swagger:generate</>  ');
            $this->line('  3. Gere entidades com: <fg=green>php artisan ptah:forge Catalog/Product --api</>');
            $this->line('  4. ⚠  NUNCA use response()->json() — use <fg=yellow>BaseResponse::</>  ');
            $this->line('  5. Configure o scan path em <fg=yellow>config/l5-swagger.php</> se necessário');
        }

        if ($module === 'permissions') {
            $this->line('  1. Acesse <fg=green>/ptah-pages</> e cadastre as páginas e objetos do sistema');
            $this->line('  2. Em <fg=green>/ptah-roles</> crie roles e configure permissões por objeto');
            $this->line('  3. Em <fg=green>/ptah-users-acl</> atribua roles aos usuários');
            $this->line('  4. Use <fg=yellow>@ptahCan(\'chave\', \'action\')</> nas views Blade');
            $this->line('  5. Use <fg=yellow>Route::middleware(\'ptah.can:recurso,action\')</> nas rotas');
            $this->line('  6. Para auditoria: defina <fg=yellow>PTAH_PERMISSION_AUDIT=true</> no .env');
        }

        $this->newLine();
    }
}

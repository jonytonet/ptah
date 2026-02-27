<?php

declare(strict_types=1);

namespace Ptah\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Ativa módulos opcionais do Ptah (auth, menu).
 *
 * Uso:
 *   php artisan ptah:module auth
 *   php artisan ptah:module menu
 *   php artisan ptah:module --list
 */
class ModuleCommand extends Command
{
    protected $signature = 'ptah:module
                            {module? : Nome do módulo a ativar (auth, menu)}
                            {--list  : Lista os módulos disponíveis e seus estados}
                            {--force : Sobrescrever arquivos existentes}';

    protected $description = 'Ativa módulos opcionais do Ptah (auth, menu).';

    /** Módulos disponíveis: nome => env key */
    protected array $modules = [
        'auth' => 'PTAH_MODULE_AUTH',
        'menu' => 'PTAH_MODULE_MENU',
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
            'auth' => $this->activateAuth(),
            'menu' => $this->activateMenu(),
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

        $this->newLine();
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

/**
 * Comando de instalação do pacote Ptah.
 *
 * Uso: php artisan ptah:install
 */
class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ptah:install
                            {--force    : Sobrescrever arquivos existentes}
                            {--skip-npm : Pular npm install e npm run build}
                            {--demo     : Instalar dados de demonstração (empresas, departamentos, roles e menu)}
                            {--boost    : Instalar Laravel Boost para integração com agentes de IA (Copilot, Claude, Cursor)}';

    /**
     * @var string
     */
    protected $description = 'Instala o pacote Ptah: publica configuração, stubs e executa migrations.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Executa o comando de instalação.
     */
    public function handle(): int
    {
        $this->components->info('Instalando Ptah...');

        $this->publishConfig();
        $this->publishStubs();
        $this->publishMigrations();
        $this->updateAppCss();
        $this->runMigrations();
        $this->createStorageLink();
        $this->seedDefaultAdmin();
        $this->seedDemoData();
        $this->installBoost();
        $this->installNodeDependencies();

        $this->newLine();
        $this->components->info('Ptah instalado com sucesso!');
        $this->newLine();
        $adminEmail = config('ptah.permissions.admin_email', 'admin@admin.com');
        $this->line('  <fg=blue>Próximos passos:</>');
        $this->line('  1. Revise o arquivo <fg=yellow>config/ptah.php</>');
        $this->line('  2. Adicione <fg=yellow>HasUserPreferences</> ao seu model User:');
        $this->line('     <fg=gray>use Ptah\\Traits\\HasUserPreferences;</>');
        $this->line('  3. Ative os módulos necessários:');
        $this->line('     <fg=green>php artisan ptah:module auth</>  <fg=gray>(login, 2FA, perfil)</>');
        $this->line('     <fg=green>php artisan ptah:module menu</>  <fg=gray>(sidebar dinâmica)</>');
        $this->line('     <fg=green>php artisan ptah:module company</>  <fg=gray>(multi-empresa)</>');
        $this->line('     <fg=green>php artisan ptah:module permissions</>  <fg=gray>(RBAC)</>');
        $this->line('  4. Acesse o sistema com: <fg=yellow>' . $adminEmail . '</>');
        $this->line('  5. Gere entidades com: <fg=green>php artisan ptah:forge {Entity}</>');
        $this->line('  6. Para integração com agentes de IA:');
        $this->line('     <fg=green>php artisan ptah:install --boost</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Publica o arquivo de configuração.
     */
    protected function publishConfig(): void
    {
        $this->components->task('Publicando configuração', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-config',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Publica os stubs.
     */
    protected function publishStubs(): void
    {
        $this->components->task('Publicando stubs', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-stubs',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Publica as migrations.
     */
    protected function publishMigrations(): void
    {
        $this->components->task('Publicando migrations', function () {
            $this->call('vendor:publish', [
                '--tag'   => 'ptah-migrations',
                '--force' => $this->option('force'),
            ]);
        });
    }

    /**
     * Injeta os design tokens e source paths do Ptah no app.css do projeto.
     * Necessário para que o Tailwind CSS v4 compile as classes do Forge corretamente.
     */
    protected function updateAppCss(): void
    {
        $this->components->task('Configurando Tailwind CSS (app.css)', function () {
            $appCss = resource_path('css/app.css');

            if (! file_exists($appCss)) {
                $this->components->warn('resources/css/app.css não encontrado — configure manualmente os tokens Tailwind.');
                return;
            }

            $content = file_get_contents($appCss);

            // Adiciona @source para as views do pacote Ptah (se ainda não existe)
            $ptahSource = "@source '../../vendor/jonytonet/ptah/resources/views/**/*.blade.php';";
            if (! str_contains($content, 'vendor/jonytonet/ptah')) {
                // Insere após a última diretiva @source existente
                if (preg_match('/(@source[^\n]+\n)(?!@source)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $content   = substr_replace($content, $ptahSource . "\n", $insertPos, 0);
                } else {
                    // Insere após @import 'tailwindcss'
                    $content = str_replace("@import 'tailwindcss';", "@import 'tailwindcss';\n{$ptahSource}", $content);
                }
                file_put_contents($appCss, $content);
            }

            // Adiciona tokens de design Forge no @theme (se ainda não existe)
            if (str_contains($content, '--color-primary')) {
                return; // já configurado
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
     * Executa as migrations.
     */
    protected function runMigrations(): void
    {
        if ($this->confirm('Deseja executar as migrations agora?', true)) {
            $this->components->task('Executando migrations', function () {
                $this->call('migrate');
            });
        }
    }

    /**
     * Cria o symlink public/storage → storage/app/public.
     * Necessário para uploads (fotos de perfil, logos etc.)
     */
    protected function createStorageLink(): void
    {
        $this->components->task('Criando link public/storage', function () {
            if (file_exists(public_path('storage'))) {
                $this->components->warn('Link public/storage já existe, pulando.');
                return;
            }
            $this->call('storage:link');
        });
    }

    /**
     * Cria a empresa padrão e o usuário admin a partir das configs/.env.
     * Só executa se as migrations do ptah já foram rodadas.
     */
    protected function seedDefaultAdmin(): void
    {
        if (! Schema::hasTable('ptah_companies')) {
            $this->components->warn('Tabelas do ptah não encontradas — rode `php artisan migrate` e depois `php artisan db:seed --class=\\Ptah\\Seeders\\DefaultAdminSeeder`.');
            return;
        }

        $this->components->task('Criando empresa padrão e usuário admin', function () {
            $this->call('db:seed', ['--class' => \Ptah\Seeders\DefaultAdminSeeder::class]);
        });
    }

    /**
     * Instala dados de demonstração quando --demo é passado.
     */
    protected function seedDemoData(): void
    {
        if (! $this->option('demo')) {
            return;
        }

        if (! Schema::hasTable('ptah_companies')) {
            $this->components->warn('Tabelas do ptah não encontradas — execute `php artisan migrate` antes de rodar o seeder de demo.');
            return;
        }

        $this->components->task('Instalando dados de demonstração', function () {
            $this->call('db:seed', ['--class' => \Ptah\Seeders\PtahDemoSeeder::class]);
        });

        $this->components->info('Dados demo instalados — acesse o sistema para visualizá-los.');
    }

    /**
     * Instala o Laravel Boost quando --boost é passado.
     *
     * Executa:
     *   1. composer require laravel/boost --dev
     *   2. php artisan boost:install
     */
    protected function installBoost(): void
    {
        if (! $this->option('boost')) {
            return;
        }

        $this->newLine();
        $this->components->info('Instalando Laravel Boost para integração com agentes de IA...');

        $this->components->task(
            'Instalando laravel/boost via Composer',
            function () {
                $composer = $this->findComposer();
                $this->runProcess([$composer, 'require', 'laravel/boost', '--dev'], base_path());
            }
        );

        // Verifica se o pacote foi de fato instalado (independente do exit code no Windows)
        if (! file_exists(base_path('vendor/laravel/boost'))) {
            $this->components->warn(
                'Não foi possível instalar laravel/boost automaticamente. '.PHP_EOL.
                'Execute manualmente: <fg=green>composer require laravel/boost --dev</>'
            );
            return;
        }

        $this->components->task('Configurando Boost (boost:install)', function () {
            // Recarrega os providers para que o BoostServiceProvider esteja disponível
            $this->call('package:discover', ['--ansi' => true]);
            $this->call('boost:install');
        });

        $this->components->info(
            'Laravel Boost instalado! Os guidelines do Ptah serão carregados automaticamente pelos agentes de IA.'
        );
    }

    /**
     * Detecta o executável do Composer disponível no sistema.
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
     * Executa npm install e npm run build se package.json existir.
     * Pode ser ignorado com --skip-npm.
     */
    protected function installNodeDependencies(): void
    {
        if ($this->option('skip-npm')) {
            return;
        }

        if (! file_exists(base_path('package.json'))) {
            return;
        }

        // Detecta npm ou yarn
        $npm = $this->findNodePackageManager();

        if (! $npm) {
            $this->components->warn('npm/yarn não encontrado — instale as dependências manualmente: npm install && npm run build');
            return;
        }

        $this->components->task('Instalando dependências Node (npm install)', function () use ($npm) {
            $exitCode = $this->runProcess([$npm, 'install'], base_path());
            return $exitCode === 0;
        });

        $this->components->task('Compilando assets (npm run build)', function () use ($npm) {
            $exitCode = $this->runProcess([$npm, 'run', 'build'], base_path());
            return $exitCode === 0;
        });
    }

    /**
     * Executa um processo externo e exibe a saída em tempo real.
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
     * Detecta o gerenciador de pacotes Node disponível.
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

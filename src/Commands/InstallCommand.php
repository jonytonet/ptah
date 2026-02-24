<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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
                            {--force : Sobrescrever arquivos existentes}';

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
        $this->runMigrations();

        $this->newLine();
        $this->components->info('Ptah instalado com sucesso!');
        $this->newLine();
        $this->line('  <fg=blue>Próximos passos:</>');
        $this->line('  1. Revise o arquivo <fg=yellow>config/ptah.php</>');
        $this->line('  2. Adicione <fg=yellow>HasUserPreferences</> ao seu model User:');
        $this->line('     <fg=gray>use Ptah\\Traits\\HasUserPreferences;</>');
        $this->line('  3. Comece a gerar entidades com:');
        $this->line('     <fg=green>php artisan ptah:make {Entity}</>');
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
}

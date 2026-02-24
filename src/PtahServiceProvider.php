<?php

declare(strict_types=1);

namespace Ptah;

use Illuminate\Support\ServiceProvider;
use Ptah\Commands\InstallCommand;
use Ptah\Commands\MakeApiCommand;
use Ptah\Commands\MakeDocsCommand;
use Ptah\Commands\MakeEntityCommand;

class PtahServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ptah.php',
            'ptah'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();
        $this->loadMigrations();
    }

    /**
     * Registra os comandos Artisan do pacote.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                MakeEntityCommand::class,
                MakeApiCommand::class,
                MakeDocsCommand::class,
            ]);
        }
    }

    /**
     * Registra os arquivos publicáveis via vendor:publish.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publicar stubs
            $this->publishes([
                __DIR__ . '/Stubs' => base_path('stubs/ptah'),
            ], 'ptah-stubs');

            // Publicar configuração
            $this->publishes([
                __DIR__ . '/../config/ptah.php' => config_path('ptah.php'),
            ], 'ptah-config');

            // Publicar migrations
            $this->publishes([
                __DIR__ . '/Migrations' => database_path('migrations'),
            ], 'ptah-migrations');
        }
    }

    /**
     * Carrega as migrations do pacote.
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
    }
}

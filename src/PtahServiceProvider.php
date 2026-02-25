<?php

declare(strict_types=1);

namespace Ptah;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ptah\Commands\InstallCommand;
use Ptah\Commands\MakeApiCommand;
use Ptah\Commands\MakeDocsCommand;
use Ptah\Commands\MakeEntityCommand;
use Ptah\Commands\ScaffoldCommand;
use Ptah\Livewire\BaseCrud;
use Ptah\Livewire\CrudConfig;
use Ptah\Livewire\SearchDropdown;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Support\SchemaInspector;

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

        $this->app->singleton(SchemaInspector::class);
        $this->app->singleton(CacheService::class);
        $this->app->singleton(CrudConfigService::class);
        $this->app->singleton(FilterService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerViews();
        $this->registerRoutes();
        $this->loadMigrations();
        $this->registerLivewire();
    }

    /**
     * Registra os comandos Artisan do pacote.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ScaffoldCommand::class,      // ptah:forge
                MakeEntityCommand::class,    // ptah:make (legado)
                MakeApiCommand::class,
                MakeDocsCommand::class,
            ]);
        }
    }

    /**
     * Registra as views e componentes Blade do Ptah Forge.
     */
    protected function registerViews(): void
    {
        $viewsPath = __DIR__ . '/../resources/views';

        // Carrega as views com namespace 'ptah'
        $this->loadViewsFrom($viewsPath, 'ptah');

        // Registra os componentes Blade forge-* sem prefixo adicional:
        // forge-button.blade.php  → <x-forge-button>
        // forge-breadcrumb.blade.php → <x-forge-breadcrumb>
        Blade::anonymousComponentPath($viewsPath . '/components');
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

            // Publicar views/componentes Forge (permite customização local)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/ptah'),
            ], 'ptah-views');

            // Publicar assets CSS do Forge
            $this->publishes([
                __DIR__ . '/../resources/css' => resource_path('css/vendor/ptah'),
            ], 'ptah-assets');
        }
    }

    /**
     * Carrega as migrations do pacote.
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
    }

    /**
     * Registra os componentes Livewire do Ptah.
     */
    protected function registerLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('ptah::base-crud', BaseCrud::class);
            Livewire::component('ptah::search-dropdown', SearchDropdown::class);
        }
    }

    /**
     * Registra rotas internas do pacote (demo Forge).
     * A rota /ptah-forge-demo só é registrada fora de produção.
     */
    protected function registerRoutes(): void
    {
        if (app()->environment(['local', 'development', 'staging'])) {
            /** @var \Illuminate\Routing\Router $router */
            $router = $this->app->make('router');

            $router->get('/ptah-forge-demo', fn() => view('ptah::forge-demo'))
                   ->name('ptah.forge.demo');
        }
    }
}

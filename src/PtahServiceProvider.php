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
use Ptah\Commands\Modules\ModuleCommand;
use Ptah\Commands\ScaffoldCommand;
use Ptah\Livewire\BaseCrud;
use Ptah\Livewire\CrudConfig;
use Ptah\Livewire\SearchDropdown;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\ResetPasswordPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Services\Auth\SessionService;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Services\Menu\MenuService;
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
        $this->app->singleton(FormValidatorService::class);
        $this->app->singleton(MenuService::class);
        $this->app->singleton(TwoFactorService::class);
        $this->app->singleton(SessionService::class);
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
                ModuleCommand::class,        // ptah:module
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

            // Publicar módulo auth (migrations + views)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php'
                    => database_path('migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php'),
            ], 'ptah-auth');

            // Publicar módulo menu (migration)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_03_000000_create_menus_table.php'
                    => database_path('migrations/2024_01_03_000000_create_menus_table.php'),
            ], 'ptah-menu');
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
            Livewire::component('ptah::base-crud',       BaseCrud::class);
            Livewire::component('ptah::search-dropdown', SearchDropdown::class);
            Livewire::component('ptah::crud-config',     CrudConfig::class);

            if (config('ptah.modules.auth')) {
                Livewire::component('ptah::auth.login',              LoginPage::class);
                Livewire::component('ptah::auth.forgot-password',    ForgotPasswordPage::class);
                Livewire::component('ptah::auth.reset-password',     ResetPasswordPage::class);
                Livewire::component('ptah::auth.two-factor',         TwoFactorChallengePage::class);
                Livewire::component('ptah::auth.profile',            ProfilePage::class);
            }
        }
    }

    /**
     * Registra rotas internas do pacote.
     */
    protected function registerRoutes(): void
    {
        if (app()->environment(['local', 'development', 'staging'])) {
            /** @var \Illuminate\Routing\Router $router */
            $router = $this->app->make('router');

            $router->get('/ptah-forge-demo', fn () => view('ptah::forge-demo'))
                   ->name('ptah.forge.demo');
        }

        if (config('ptah.modules.auth')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ptah-auth.php');
        }
    }
}

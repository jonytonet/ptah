<?php

declare(strict_types=1);

namespace Ptah;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ptah\Commands\InstallCommand;
use Ptah\Commands\MakeApiCommand;
use Ptah\Commands\MakeDocsCommand;
use Ptah\Commands\MakeEntityCommand;
use Ptah\Commands\Modules\ModuleCommand;
use Ptah\Commands\ScaffoldCommand;
use Ptah\Contracts\CompanyServiceContract;
use Ptah\Contracts\PermissionServiceContract;
use Ptah\Http\Middleware\PtahPermission;
use Ptah\Livewire\BaseCrud;
use Ptah\Livewire\CrudConfig;
use Ptah\Livewire\SearchDropdown;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\ResetPasswordPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Livewire\Permission\AuditList;
use Ptah\Livewire\Permission\DepartmentList;
use Ptah\Livewire\Permission\PageList;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Services\Auth\SessionService;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Company\CompanyService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Services\Menu\MenuService;
use Ptah\Services\Permission\PermissionService;
use Ptah\Services\Permission\RoleService;
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

        // Módulo company
        $this->app->singleton(CompanyService::class);
        $this->app->bind(CompanyServiceContract::class, CompanyService::class);

        // Módulo permissions
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(RoleService::class);
        $this->app->bind(PermissionServiceContract::class, PermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerViews();
        $this->registerBladeDirectives();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->loadMigrations();
        $this->registerLivewire();

        // Informa o middleware Authenticate do Laravel onde redirecionar
        // usuários não autenticados quando o módulo auth do Ptah está ativo.
        if (config('ptah.modules.auth')) {
            Authenticate::redirectUsing(function ($request) {
                if (! $request->expectsJson()) {
                    return route('ptah.auth.login');
                }
            });
        }
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
     * Registra diretivas Blade do Ptah.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::if('ptahCan', function (string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool {
            return ptah_can($objectKey, $action, $user, $companyId);
        });

        Blade::if('ptahMaster', function (mixed $user = null): bool {
            return ptah_is_master($user);
        });
    }

    /**
     * Registra alias de middleware do pacote.
     */
    protected function registerMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('ptah.can', PtahPermission::class);
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

            // Publicar módulo company (migrations)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_04_000000_create_ptah_companies_table.php'
                    => database_path('migrations/2024_01_04_000000_create_ptah_companies_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000001_create_ptah_departments_table.php'
                    => database_path('migrations/2024_01_04_000001_create_ptah_departments_table.php'),
            ], 'ptah-company');

            // Publicar módulo permissions (migrations)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_04_000002_create_ptah_roles_table.php'
                    => database_path('migrations/2024_01_04_000002_create_ptah_roles_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000003_create_ptah_pages_table.php'
                    => database_path('migrations/2024_01_04_000003_create_ptah_pages_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000004_create_ptah_page_objects_table.php'
                    => database_path('migrations/2024_01_04_000004_create_ptah_page_objects_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000005_create_ptah_role_permissions_table.php'
                    => database_path('migrations/2024_01_04_000005_create_ptah_role_permissions_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000006_create_ptah_user_roles_table.php'
                    => database_path('migrations/2024_01_04_000006_create_ptah_user_roles_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000007_create_ptah_permission_audits_table.php'
                    => database_path('migrations/2024_01_04_000007_create_ptah_permission_audits_table.php'),
            ], 'ptah-permissions');
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

            if (config('ptah.modules.menu')) {
                Livewire::component('ptah::menu.list', MenuList::class);
            }

            if (config('ptah.modules.company')) {
                Livewire::component('ptah::company.list', CompanyList::class);
            }

            if (config('ptah.modules.permissions')) {
                Livewire::component('ptah::permission.department-list', DepartmentList::class);
                Livewire::component('ptah::permission.role-list',       RoleList::class);
                Livewire::component('ptah::permission.page-list',       PageList::class);
                Livewire::component('ptah::permission.user-list',       UserPermissionList::class);
                Livewire::component('ptah::permission.audit-list',      AuditList::class);
                Livewire::component('ptah::permission.guide',             PermissionGuide::class);
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

        if (config('ptah.modules.menu')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ptah-menu.php');
        }

        if (config('ptah.modules.company')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ptah-company.php');
        }

        if (config('ptah.modules.permissions')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ptah-permissions.php');
        }
    }
}

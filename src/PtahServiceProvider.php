<?php

declare(strict_types=1);

namespace Ptah;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ptah\Commands\ConfigCommand;
use Ptah\Commands\InstallCommand;
use Ptah\Commands\MenuSyncCommand;
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
use Ptah\Livewire\Company\CompanySwitcher;
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

        // SchemaInspector is only needed during Artisan code-generation commands.
        // Binding it as a singleton in every HTTP request wastes memory.
        if ($this->app->runningInConsole()) {
            $this->app->singleton(SchemaInspector::class);
        }
        $this->app->singleton(CacheService::class);
        $this->app->singleton(CrudConfigService::class);
        $this->app->singleton(FilterService::class);
        $this->app->singleton(FormValidatorService::class);
        $this->app->singleton(MenuService::class);
        $this->app->singleton(TwoFactorService::class);
        $this->app->singleton(SessionService::class);

        // Company module
        $this->app->singleton(CompanyService::class);
        $this->app->bind(CompanyServiceContract::class, CompanyService::class);

        // Permissions module
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(RoleService::class);
        $this->app->bind(PermissionServiceContract::class, PermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only override the host application's locale when explicitly requested.
        // Changing the locale globally in a ServiceProvider would silently
        // break Carbon formatting, validation messages and every package that
        // reads App::getLocale() in the host project.
        if (config('ptah.force_locale', false)) {
            $this->app->setLocale(config('ptah.locale', 'en'));
        }

        // Loads package translations with namespace 'ptah'
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ptah');

        $this->registerCommands();
        $this->registerPublishing();
        $this->registerViews();
        $this->registerBladeDirectives();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->loadMigrations();
        $this->registerLivewire();

        // Informs Laravel's Authenticate middleware where to redirect
        // unauthenticated users when the Ptah auth module is active.
        if (config('ptah.modules.auth')) {
            Authenticate::redirectUsing(function ($request) {
                if (! $request->expectsJson()) {
                    return route('ptah.auth.login');
                }
            });
        }
    }

    /**
     * Registers the package's Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ScaffoldCommand::class,      // ptah:forge
                MenuSyncCommand::class,      // ptah:menu-sync
                ModuleCommand::class,        // ptah:module
                ConfigCommand::class,        // ptah:config
            ]);
        }
    }

    /**
     * Registers Ptah Blade directives.
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
     * Registers the package middleware alias.
     */
    protected function registerMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('ptah.can', PtahPermission::class);
    }

    /**
     * Registers Ptah Forge views and Blade components.
     */
    protected function registerViews(): void
    {
        $viewsPath = __DIR__ . '/../resources/views';

        // Loads views with namespace 'ptah'
        $this->loadViewsFrom($viewsPath, 'ptah');

        // Registers anonymous Blade components forge-* without additional prefix:
        // forge-button.blade.php  → <x-forge-button>
        // forge-breadcrumb.blade.php → <x-forge-breadcrumb>
        Blade::anonymousComponentPath($viewsPath . '/components');
    }

    /**
     * Registers publishable files via vendor:publish.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish stubs
            $this->publishes([
                __DIR__ . '/Stubs' => base_path('stubs/ptah'),
            ], 'ptah-stubs');

            // Publish configuration
            $this->publishes([
                __DIR__ . '/../config/ptah.php' => config_path('ptah.php'),
            ], 'ptah-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/Migrations' => database_path('migrations'),
            ], 'ptah-migrations');

            // Publish Forge views/components (allows local customisation)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/ptah'),
            ], 'ptah-views');

            // Publish Forge CSS assets
            $this->publishes([
                __DIR__ . '/../resources/css' => resource_path('css/vendor/ptah'),
            ], 'ptah-assets');

            // Publish translations (allows per-project customisation)
            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/ptah'),
            ], 'ptah-lang');

            // Publish auth module (migrations + views)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php'
                    => database_path('migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php'),
            ], 'ptah-auth');

            // Publish menu module (migration)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_03_000000_create_menus_table.php'
                    => database_path('migrations/2024_01_03_000000_create_menus_table.php'),
            ], 'ptah-menu');

            // Publish company module (migrations)
            $this->publishes([
                __DIR__ . '/Migrations/2024_01_04_000000_create_ptah_companies_table.php'
                    => database_path('migrations/2024_01_04_000000_create_ptah_companies_table.php'),
                __DIR__ . '/Migrations/2024_01_04_000001_create_ptah_departments_table.php'
                    => database_path('migrations/2024_01_04_000001_create_ptah_departments_table.php'),
            ], 'ptah-company');

            // Publish permissions module (migrations)
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

            // Publish API module (BaseResponse, BaseApiController, SwaggerInfo)
            $this->publishes([
                __DIR__ . '/Stubs/base-response.stub'       => app_path('Responses/BaseResponse.php'),
                __DIR__ . '/Stubs/base-api-controller.stub' => app_path('Http/Controllers/API/BaseApiController.php'),
                __DIR__ . '/Stubs/swagger-info.stub'        => app_path('Http/Controllers/API/SwaggerInfo.php'),
            ], 'ptah-api');

            // Publish MenuRegistry (auto-menu system)
            $this->publishes([
                __DIR__ . '/../stubs/seeders/MenuRegistry.stub.php' => database_path('seeders/MenuRegistry.php'),
            ], 'ptah-menu-registry');
        }
    }

    /**
     * Loads Ptah migrations conditionally by enabled module.
     *
     * When NO modules are enabled (pure code-generator usage), no migrations
     * are registered so the host project is not polluted with unexpected tables.
     * When at least one module is active, all Ptah migrations are loaded because
     * the tables are inter-dependent (roles reference companies, etc.).
     */
    protected function loadMigrations(): void
    {
        $modulesEnabled = array_filter(config('ptah.modules', []));

        if (! empty($modulesEnabled)) {
            $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        }
    }

    /**
     * Registers Ptah Livewire components.
     */
    protected function registerLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            // NOTE: do not use '::' in aliases (reserved for Blade vendors in Livewire 4)
            Livewire::component('ptah-base-crud',       BaseCrud::class);
            Livewire::component('ptah-search-dropdown', SearchDropdown::class);
            Livewire::component('ptah-crud-config',     CrudConfig::class);

            if (config('ptah.modules.auth')) {
                Livewire::component('ptah-auth-login',              LoginPage::class);
                Livewire::component('ptah-auth-forgot-password',    ForgotPasswordPage::class);
                Livewire::component('ptah-auth-reset-password',     ResetPasswordPage::class);
                Livewire::component('ptah-auth-two-factor',         TwoFactorChallengePage::class);
                Livewire::component('ptah-auth-profile',            ProfilePage::class);
            }

            if (config('ptah.modules.menu')) {
                Livewire::component('ptah-menu-list', MenuList::class);
            }

            if (config('ptah.modules.company')) {
                Livewire::component('ptah-company-list',     CompanyList::class);
                Livewire::component('ptah-company-switcher', CompanySwitcher::class);
            }

            if (config('ptah.modules.permissions')) {
                Livewire::component('ptah-permission-department-list', DepartmentList::class);
                Livewire::component('ptah-permission-role-list',       RoleList::class);
                Livewire::component('ptah-permission-page-list',       PageList::class);
                Livewire::component('ptah-permission-user-list',       UserPermissionList::class);
                Livewire::component('ptah-permission-audit-list',      AuditList::class);
                Livewire::component('ptah-permission-guide',           PermissionGuide::class);
            }
        }
    }

    /**
     * Registra rotas internas do pacote.
     */
    protected function registerRoutes(): void
    {
        if (app()->environment(['local', 'development'])) {
            /** @var \Illuminate\Routing\Router $router */
            $router = $this->app->make('router');

            $router->get('/ptah-forge-demo', fn () => view('ptah::forge-demo'))
                   ->name('ptah.forge.demo');
        }

        // Rotas principais do Ptah (exportação, etc.)
        $this->loadRoutesFrom(__DIR__ . '/../routes/ptah.php');

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

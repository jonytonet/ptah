<?php

declare(strict_types=1);

namespace Ptah;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Tool;
use Livewire\Livewire;
use Ptah\Commands\AttachmentsInstallCommand;
use Ptah\Commands\BlueprintCommand;
use Ptah\Commands\CheckCommand;
use Ptah\Commands\Config\ConfigDoctorCommand;
use Ptah\Commands\Config\ConfigExportAllCommand;
use Ptah\Commands\Config\ConfigImportAllCommand;
use Ptah\Commands\Config\ConfigRelabelCommand;
use Ptah\Commands\ConfigCommand;
use Ptah\Commands\DocsCommand;
use Ptah\Commands\ExportPruneCommand;
use Ptah\Commands\FieldCommand;
use Ptah\Commands\HistoryInstallCommand;
use Ptah\Commands\InstallCommand;
use Ptah\Commands\LastErrorCommand;
use Ptah\Commands\MakeHooksCommand;
use Ptah\Commands\MapCommand;
use Ptah\Commands\MenuSyncCommand;
use Ptah\Commands\Modules\ModuleCommand;
use Ptah\Commands\Permission\AuditPruneCommand;
use Ptah\Commands\Permission\PermissionSyncCommand;
use Ptah\Commands\Permission\PermissionWhyCommand;
use Ptah\Commands\PreferencesRealignCommand;
use Ptah\Commands\ScaffoldCommand;
use Ptah\Commands\ScreenCommand;
use Ptah\Commands\SettingsInstallCommand;
use Ptah\Commands\UpgradeCheckCommand;
use Ptah\Commands\WhyEmptyCommand;
use Ptah\Contracts\CompanyServiceContract;
use Ptah\Contracts\PermissionServiceContract;
use Ptah\Events\PtahNotificationCreated;
use Ptah\Http\Middleware\PtahMaster;
use Ptah\Http\Middleware\PtahPermission;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\ResetPasswordPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Livewire\Company\CompanySwitcher;
use Ptah\Livewire\Exports\ExportsPanel;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Livewire\Notification\NotificationBell;
use Ptah\Livewire\Permission\AuditList;
use Ptah\Livewire\Permission\DepartmentList;
use Ptah\Livewire\Permission\PageList;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\Livewire\Settings\SettingsPage;
use Ptah\Mcp\PtahMcpTools;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Services\Auth\SessionService;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Company\CompanyService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Services\Menu\MenuService;
use Ptah\Services\Notification\CrudNotificationDispatcher;
use Ptah\Services\Notification\NotificationService;
use Ptah\Services\Permission\ColumnPermissionService;
use Ptah\Services\Permission\PermissionService;
use Ptah\Services\Permission\RoleService;
use Ptah\Services\SettingsService;
use Ptah\Support\AI\ToolSchemaNormalizer;
use Ptah\Support\SchemaInspector;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PtahServiceProvider extends ServiceProvider
{
    /**
     * Exceptions the framework renders ITSELF, which the themed 500 must not take.
     *
     * The handler's `render()` special-cases these AFTER running the render
     * callbacks:
     *
     *     $e = $this->prepareException($e);
     *     if ($response = $this->renderViaCallbacks($request, $e)) { return … }
     *     return match (true) {
     *         $e instanceof HttpResponseException   => $e->getResponse(),
     *         $e instanceof AuthenticationException => $this->unauthenticated(…),
     *         $e instanceof ValidationException     => $this->convertValidation…(…),
     *         default => $this->renderExceptionResponse($request, $e),
     *     };
     *
     * So a callback typed `Throwable` sees them first, and answering 500 there
     * replaces a login redirect, a validation redirect, or a response the
     * exception is carrying. Both of the first two are in Laravel's
     * `dontReport` list, so that 500 also went out with nothing in the log.
     *
     * `HttpException` is here for the arms `prepareException()` has ALREADY
     * converted by this point — `AuthorizationException`,
     * `ModelNotFoundException`, `TokenMismatchException` and friends all become
     * one before any callback runs, which is why they were never affected.
     *
     * `ExceptionRenderableScopeTest` reads the framework's own match arms and
     * fails if this list stops covering them — the answer to "a hand-written
     * list goes stale as Laravel evolves".
     */
    public const FRAMEWORK_RENDERED_EXCEPTIONS = [
        HttpException::class,
        HttpResponseException::class,
        AuthenticationException::class,
        ValidationException::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/ptah.php',
            'ptah'
        );

        // Arquivo SEPARADO, e nao uma chave dentro de ptah.php, por dois
        // motivos que se reforcam.
        //
        // `mergeConfigFrom` e RASO: um host que publicou config/ptah.php passa a
        // ser dono do array inteiro, entao uma chave aninhada nova nunca chega
        // ate ele — armadilha em que este pacote ja caiu duas vezes. Com um
        // arquivo proprio, `ptah-masks` ou existe no host ou vem daqui, sem
        // meio-termo.
        //
        // E as mascaras sao a coisa do pacote que mais muda por fora dele:
        // formato de documento muda por lei. Um arquivo dedicado e menor para
        // rever num diff que um ptah.php de seiscentas linhas.
        $this->mergeConfigFrom(
            __DIR__.'/../config/ptah-masks.php',
            'ptah-masks'
        );

        // Arquivo proprio pelo mesmo motivo do ptah-masks: e o host que declara
        // as definicoes, e um merge raso de ptah.php nunca as traria.
        $this->mergeConfigFrom(
            __DIR__.'/../config/ptah-settings.php',
            'ptah-settings'
        );

        // SchemaInspector is only needed during Artisan code-generation commands.
        // Binding it as a singleton in every HTTP request wastes memory.
        if ($this->app->runningInConsole()) {
            $this->app->singleton(SchemaInspector::class);
        }
        $this->app->singleton(CacheService::class);
        $this->app->singleton(CrudConfigService::class);
        $this->app->singleton(SettingsService::class);
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
        $this->app->singleton(ColumnPermissionService::class);
        $this->app->bind(PermissionServiceContract::class, PermissionService::class);

        // Notifications — no gate here (a singleton binding is cheap); the
        // gate lives inside NotificationService::tableExists(), consulted on
        // every read/write.
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(CrudNotificationDispatcher::class);

        // AI Agent module
        if (config('ptah.modules.ai_agent')) {
            // Nomes de classe, nao instancias — a montagem vive em
            // AiToolRegistry::fromConfig(). Este closure roda quando o
            // AiChatService e resolvido, e o AiChatService e resolvido no boot()
            // do widget de chat, que vive no layout autenticado, ou seja em toda
            // tela do sistema. Instanciar as tools aqui fazia cada page-load
            // pagar por todas elas (26, no ERP que reportou isso) e, pior, punha
            // o construtor de codigo do host dentro do render da pagina: uma
            // tool com ciclo de DI derrubava o sistema inteiro com 500, nao so o
            // chat.
            $this->app->singleton(AiToolRegistry::class, static fn (): AiToolRegistry => AiToolRegistry::fromConfig());
            $this->app->singleton(AiProviderConfigService::class);
            $this->app->singleton(AiChatService::class);
        }
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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ptah');

        $this->registerCommands();
        $this->registerPublishing();
        $this->registerViews();
        $this->registerBladeDirectives();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->loadMigrations();
        $this->registerLivewire();
        $this->registerPermissionCacheInvalidation();
        $this->registerNotificationBroadcastChannel();
        $this->registerBoostTools();

        // Makes tool payloads valid JSON Schema on the way out. A no-argument
        // tool serialises its empty parameter list as `"properties": []`, which
        // strict providers (x.ai, OpenAI's structured mode, most self-hosted
        // OpenAI-compatible servers) reject — and both of ptah's built-in tools
        // take no arguments, so the package failed on its own tools. Gated on
        // the module so a host that never uses the AI agent registers nothing.
        // See the class for why the trigger is the malformed shape rather than
        // the destination host.
        if (config('ptah.modules.ai_agent') && config('ptah.ai_agent.normalize_tool_schema', true)) {
            ToolSchemaNormalizer::register();
        }

        // Render a friendly 403 page when the host app has no custom one.
        // Only activates when the permissions module is enabled; falls back to
        // Laravel's default handler otherwise.
        $exceptionHandler = $this->app->make(ExceptionHandler::class);

        if (method_exists($exceptionHandler, 'renderable')) {
            // Themed error pages for the statuses a user can actually land on.
            //
            // 403 stays gated behind the permissions module (it is that
            // module's own denial screen); the rest are useful to every host
            // and are gated by `ptah.errors.enabled` instead.
            //
            // Three conditions guard EVERY page, and each one matters:
            //   - the host's own `resources/views/errors/{code}.blade.php`
            //     always wins, because Laravel's convention is that a view
            //     there is the last word;
            //   - JSON requests fall through, or an API would answer HTML;
            //   - 500 additionally falls through while APP_DEBUG is on, so a
            //     developer keeps the stack trace instead of a pretty page
            //     that hides it.
            $renderable = function (HttpException $e, Request $request) {
                $status = $e->getStatusCode();

                if ($status === 403 && ! config('ptah.modules.permissions')) {
                    return null;
                }

                if ($status !== 403 && ! config('ptah.errors.enabled', true)) {
                    return null;
                }

                if (! in_array($status, [403, 404, 405, 419, 429, 503], true)) {
                    return null;
                }

                if ($request->expectsJson()
                    || file_exists(resource_path("views/errors/{$status}.blade.php"))) {
                    return null;
                }

                return response()->view("ptah::errors.{$status}", ['exception' => $e], $status);
            };

            $exceptionHandler->renderable($renderable);

            // The reference shown on the 500 page is stamped into the log
            // record for the SAME exception, and that ordering is the whole
            // point: Laravel runs report() before render(), so an id minted
            // where it is displayed would already be too late to appear in any
            // log line. `buildContextUsing` runs inside report(), so the id is
            // written to the log first and merely read back at render time.
            //
            // A reference the support team cannot grep is worse than none at
            // all — it promises a correlation that does not exist — so the
            // view prints the line only when an id was actually logged.
            //
            // The callback is additive (Laravel keeps a list of them), so a
            // host that registers its own context builder is not displaced.
            if (method_exists($exceptionHandler, 'buildContextUsing')) {
                $exceptionHandler->buildContextUsing(function (Throwable $e): array {
                    Context::add('ptahErrorId', $id = bin2hex(random_bytes(6)));

                    return ['ptahErrorId' => $id];
                });
            }

            // 500 is registered apart because it is NOT an HttpException: it is
            // whatever blew up.
            $exceptionHandler->renderable(function (Throwable $e, Request $request) {
                // Com APP_DEBUG ligado o trace vale mais que a pagina bonita, e
                // esconde-lo seria hostil com quem esta depurando. Mas a
                // consequencia era que ninguem conseguia VER a propria 500 em
                // desenvolvimento sem desligar o debug e com ele um monte de
                // outros comportamentos — e um comportamento deliberado que
                // parece defeito, sem nada em lugar nenhum dizendo o porque.
                // `ptah.errors.themed_500_in_debug` e a saida explicita.
                $hiddenByDebug = config('app.debug')
                    && ! config('ptah.errors.themed_500_in_debug', false);

                // A guarda por tipo era so `HttpException`, e o tipo do
                // parametro e `Throwable` — entao TODA excecao passava por aqui.
                // Uma AuthenticationException virava 500 no lugar do redirect
                // para o login, e uma ValidationException virava 500 no lugar do
                // "credenciais invalidas" de volta no formulario. So com
                // APP_DEBUG desligado, e as duas estao no dontReport do Laravel:
                // 500 em producao, sem uma linha de log.
                foreach (self::FRAMEWORK_RENDERED_EXCEPTIONS as $handledByLaravel) {
                    if ($e instanceof $handledByLaravel) {
                        return null;
                    }
                }

                if (! config('ptah.errors.enabled', true)
                    || $hiddenByDebug
                    || $request->expectsJson()
                    || file_exists(resource_path('views/errors/500.blade.php'))) {
                    return null;
                }

                return response()->view('ptah::errors.500', [
                    'exception' => $e,
                    // Read back, never minted here: null when the exception was
                    // not reported (a `dontReport` class, say), and the view
                    // then omits the reference line rather than showing an id
                    // that appears in no log. Deliberately not the exception
                    // message, which can leak a query, a path or a credential
                    // to whoever triggered the error.
                    'errorId' => Context::get('ptahErrorId'),
                ], 500);
            });
        }

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
                ConfigDoctorCommand::class,     // ptah:config:doctor
                ConfigExportAllCommand::class,  // ptah:config:export-all
                ConfigImportAllCommand::class,  // ptah:config:import-all
                ConfigRelabelCommand::class,    // ptah:config:relabel
                MakeHooksCommand::class,      // ptah:hooks
                PreferencesRealignCommand::class,
                DocsCommand::class,           // ptah:docs
                LastErrorCommand::class,      // ptah:last-error
                CheckCommand::class,          // ptah:check
                MapCommand::class,            // ptah:map
                FieldCommand::class,          // ptah:field
                BlueprintCommand::class,      // ptah:blueprint
                UpgradeCheckCommand::class,   // ptah:upgrade-check
                ScreenCommand::class,         // ptah:screen
                WhyEmptyCommand::class,       // ptah:why-empty
                HistoryInstallCommand::class, // ptah:history:install
                AttachmentsInstallCommand::class, // ptah:attachments:install
                SettingsInstallCommand::class, // ptah:settings:install
                PermissionSyncCommand::class, // ptah:permission:sync
                PermissionWhyCommand::class,  // ptah:permission:why
                AuditPruneCommand::class,     // ptah:audit-prune
                ExportPruneCommand::class,    // ptah:export-prune
            ]);
        }
    }

    /**
     * Invalidates the permission cache the instant a role definition, a role's
     * object bindings, a page/object's availability, or a user's role assignments
     * change — closing the window where a revoked permission (or a deactivated
     * page/object) stayed effective until the TTL expired.
     *
     *  - Role / RolePermission change → affects many users → global generation bump.
     *  - PageObject / PtahPage change → affects many users → global generation bump.
     *  - UserRole change              → affects one user   → that user's bump.
     */
    protected function registerPermissionCacheInvalidation(): void
    {
        if (! config('ptah.modules.permissions')) {
            return;
        }

        $service = fn () => $this->app->make(PermissionService::class);

        foreach (['saved', 'deleted', 'restored'] as $event) {
            Role::{$event}(fn () => $service()->bumpGlobalVersion());
            RolePermission::{$event}(fn () => $service()->bumpGlobalVersion());
            UserRole::{$event}(
                fn (UserRole $ur) => $service()->clearCache((int) $ur->user_id)
            );
        }

        // PageObject / PtahPage are hard-deleted (no SoftDeletes trait — see their
        // migrations), so only 'saved' and 'deleted' exist; registering 'restored'
        // on them would throw (no such static method on a non-SoftDeletes model).
        foreach (['saved', 'deleted'] as $event) {
            PageObject::{$event}(fn () => $service()->bumpGlobalVersion());
            PtahPage::{$event}(fn () => $service()->bumpGlobalVersion());
        }
    }

    /**
     * Registers the private `ptah.notifications.{userId}` channel used by
     * {@see PtahNotificationCreated} — ONLY when
     * `ptah.notifications.broadcast` is enabled, which is what lets a host
     * WITHOUT Reverb/Echo installed skip this call entirely. This also means
     * the host does NOT need to touch its own routes/channels.php.
     *
     * The try/catch is NOT optional: Broadcast::channel() resolves the
     * default broadcast connection via BroadcastManager::__call() ->
     * driver() the first time any channel is registered, so if the host's
     * BROADCAST_CONNECTION points at a driver whose SDK is not installed
     * this call throws — without the catch it would break the boot of
     * EVERY request, not just realtime ones.
     */
    protected function registerNotificationBroadcastChannel(): void
    {
        if (! config('ptah.notifications.broadcast')) {
            return;
        }

        try {
            Broadcast::channel('ptah.notifications.{userId}', function ($user, $userId) {
                return (int) $user->getAuthIdentifier() === (int) $userId;
            });
        } catch (Throwable $e) {
            Log::warning('[Ptah] Failed to register the notifications broadcast channel', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registers Ptah Blade directives.
     *
     * @ptahCan's $objectKey may be a QUALIFIED key (`page::obj_key` /
     * `page::section::obj_key`, see `PermissionService::KEY_QUALIFIER`) — it
     * passes through unchanged to ptah_can()/check().
     */
    protected function registerBladeDirectives(): void
    {
        Blade::if('ptahCan', function (string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool {
            return ptah_can($objectKey, $action, $user, $companyId);
        });

        Blade::if('ptahMaster', function (mixed $user = null): bool {
            return ptah_is_master($user);
        });

        Blade::if('ptahCanManageConfig', function (mixed $user = null): bool {
            return ptah_can_manage_config($user);
        });
    }

    /**
     * Registers the package middleware alias.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('ptah.can', PtahPermission::class);
        $router->aliasMiddleware('ptah.master', PtahMaster::class);
    }

    /**
     * Registers Ptah Forge views and Blade components.
     */
    /**
     * Offers the ptah agent tools to Laravel Boost's MCP server.
     *
     * Boost adds every class listed in `boost.mcp.tools.include` to the tools
     * it exposes; appending ptah's there means an agent connected to Boost
     * gets `ptah-map`, `ptah-screen`, `ptah-check`, `ptah-why-empty`… with no
     * setup in the host. Done in boot(), after every provider registered its
     * config, and only when laravel/mcp's base class exists — the tool classes
     * extend it. `PTAH_MCP_TOOLS=false` turns it off.
     */
    protected function registerBoostTools(): void
    {
        if (! config('ptah.mcp_tools', true) || ! class_exists(Tool::class)) {
            return;
        }

        config(['boost.mcp.tools.include' => array_values(array_unique(array_merge(
            (array) config('boost.mcp.tools.include', []),
            PtahMcpTools::CLASSES,
        )))]);
    }

    protected function registerViews(): void
    {
        $packagePath = __DIR__.'/../resources/views';
        $vendorPath = resource_path('views/vendor/ptah/components');

        // Loads views with namespace 'ptah'
        $this->loadViewsFrom($packagePath, 'ptah');

        // Registers anonymous Blade components forge-* without additional prefix.
        // Published path (resources/views/vendor/ptah/components/) takes precedence
        // over the package path so that vendor:publish overrides work correctly.
        if (is_dir($vendorPath)) {
            Blade::anonymousComponentPath($vendorPath);
        }

        Blade::anonymousComponentPath($packagePath.'/components');
    }

    /**
     * Registers publishable files via vendor:publish.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish stubs
            $this->publishes([
                __DIR__.'/Stubs' => base_path('stubs/ptah'),
            ], 'ptah-stubs');

            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/ptah.php' => config_path('ptah.php'),
                __DIR__.'/../config/ptah-masks.php' => config_path('ptah-masks.php'),
                __DIR__.'/../config/ptah-settings.php' => config_path('ptah-settings.php'),
            ], 'ptah-config');

            // E sozinho, para quem so quer as mascaras sem republicar o ptah.php
            // inteiro por cima do que ja ajustou.
            $this->publishes([
                __DIR__.'/../config/ptah-masks.php' => config_path('ptah-masks.php'),
            ], 'ptah-masks');

            // Publish migrations
            $this->publishes([
                __DIR__.'/Migrations' => database_path('migrations'),
            ], 'ptah-migrations');

            // Publish Forge views/components (allows local customisation).
            //
            // ⚠ Publishing a view = you OWN it: Laravel's view finder then prefers
            // your copy in resources/views/vendor/ptah/ over the package's, and
            // `composer update` will NEVER refresh it. Publish ONLY the view you
            // intend to edit. Prefer the granular tags below over `ptah-views`
            // (which copies all 60+ views and freezes the whole UI).
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ptah'),
            ], 'ptah-views');

            // Granular view tags — publish just the area you customise.
            $this->publishes([
                __DIR__.'/../resources/views/components' => resource_path('views/vendor/ptah/components'),
            ], 'ptah-views-components');

            $this->publishes([
                __DIR__.'/../resources/views/livewire/base-crud' => resource_path('views/vendor/ptah/livewire/base-crud'),
            ], 'ptah-views-base-crud');

            $this->publishes([
                __DIR__.'/../resources/views/livewire/auth' => resource_path('views/vendor/ptah/livewire/auth'),
                __DIR__.'/../resources/views/layouts/forge-auth.blade.php' => resource_path('views/vendor/ptah/layouts/forge-auth.blade.php'),
            ], 'ptah-views-auth');

            $this->publishes([
                __DIR__.'/../resources/views/livewire/ai' => resource_path('views/vendor/ptah/livewire/ai'),
            ], 'ptah-views-ai');

            // Publish Forge CSS assets
            $this->publishes([
                __DIR__.'/../resources/css' => resource_path('css/vendor/ptah'),
            ], 'ptah-assets');

            // Publish translations (allows per-project customisation).
            // ⚠ This copies the WHOLE file (1400+ keys) → those keys freeze to the
            // current wording. Prefer `ptah-lang-overrides` unless you are doing a
            // full re-translation / adding a new language.
            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/ptah'),
            ], 'ptah-lang');

            // Minimal, non-freezing translation override. Publishes a starter file
            // where you list ONLY the keys you change — Laravel merges the rest (and
            // any future keys) from the package (array_replace_recursive).
            $this->publishes([
                __DIR__.'/Stubs/lang-override.stub' => lang_path('vendor/ptah/pt_BR/ui.php'),
            ], 'ptah-lang-overrides');

            // Publish auth module (migrations + views)
            $this->publishes([
                __DIR__.'/Migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php' => database_path('migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php'),
            ], 'ptah-auth');

            // Publish menu module (migration)
            $this->publishes([
                __DIR__.'/Migrations/2024_01_03_000000_create_menus_table.php' => database_path('migrations/2024_01_03_000000_create_menus_table.php'),
            ], 'ptah-menu');

            // Publish company module (migrations)
            $this->publishes([
                __DIR__.'/Migrations/2024_01_04_000000_create_ptah_companies_table.php' => database_path('migrations/2024_01_04_000000_create_ptah_companies_table.php'),
                __DIR__.'/Migrations/2024_01_04_000001_create_ptah_departments_table.php' => database_path('migrations/2024_01_04_000001_create_ptah_departments_table.php'),
            ], 'ptah-company');

            // Publish AI Agent module (migrations)
            $this->publishes([
                __DIR__.'/Migrations/2026_03_23_000001_create_ptah_ai_model_configs_table.php' => database_path('migrations/2026_03_23_000001_create_ptah_ai_model_configs_table.php'),
                __DIR__.'/Migrations/2026_03_23_000002_create_ptah_ai_conversations_table.php' => database_path('migrations/2026_03_23_000002_create_ptah_ai_conversations_table.php'),
                __DIR__.'/Migrations/ai/2026_03_31_000003_add_user_to_ptah_ai_conversations_table.php' => database_path('migrations/2026_03_31_000003_add_user_to_ptah_ai_conversations_table.php'),
            ], 'ptah-ai-agent');

            // Publish notifications module (migration) — opt-in schema, deliberately
            // NOT under src/Migrations/loadMigrations() (see the migration's own
            // docblock and tests/Unit/Support/OptInSchemaIsFrozenTest.php).
            $this->publishes([
                __DIR__.'/../database/migrations/2026_08_23_000000_create_ptah_notifications_table.php' => database_path('migrations/2026_08_23_000000_create_ptah_notifications_table.php'),
            ], 'ptah-notifications');

            // Publish permissions module (migrations)
            $this->publishes([
                __DIR__.'/Migrations/2024_01_04_000002_create_ptah_roles_table.php' => database_path('migrations/2024_01_04_000002_create_ptah_roles_table.php'),
                __DIR__.'/Migrations/2024_01_04_000003_create_ptah_pages_table.php' => database_path('migrations/2024_01_04_000003_create_ptah_pages_table.php'),
                __DIR__.'/Migrations/2024_01_04_000004_create_ptah_page_objects_table.php' => database_path('migrations/2024_01_04_000004_create_ptah_page_objects_table.php'),
                __DIR__.'/Migrations/2024_01_04_000005_create_ptah_role_permissions_table.php' => database_path('migrations/2024_01_04_000005_create_ptah_role_permissions_table.php'),
                __DIR__.'/Migrations/2024_01_04_000006_create_ptah_user_roles_table.php' => database_path('migrations/2024_01_04_000006_create_ptah_user_roles_table.php'),
                __DIR__.'/Migrations/2024_01_04_000007_create_ptah_permission_audits_table.php' => database_path('migrations/2024_01_04_000007_create_ptah_permission_audits_table.php'),
            ], 'ptah-permissions');

            // Publish API module (BaseResponse, BaseApiController, SwaggerInfo)
            $this->publishes([
                __DIR__.'/Stubs/base-response.stub' => app_path('Responses/BaseResponse.php'),
                __DIR__.'/Stubs/base-api-controller.stub' => app_path('Http/Controllers/API/BaseApiController.php'),
                __DIR__.'/Stubs/swagger-info.stub' => app_path('Http/Controllers/API/SwaggerInfo.php'),
            ], 'ptah-api');

            // Publish MenuRegistry (auto-menu system)
            $this->publishes([
                __DIR__.'/../stubs/seeders/MenuRegistry.stub.php' => database_path('seeders/MenuRegistry.php'),
            ], 'ptah-menu-registry');

            // Publish 403 error view (allows per-project customisation)
            $this->publishes([
                __DIR__.'/../resources/views/errors/layout.blade.php' => resource_path('views/errors/layout.blade.php'),
                __DIR__.'/../resources/views/errors/403.blade.php' => resource_path('views/errors/403.blade.php'),
                __DIR__.'/../resources/views/errors/404.blade.php' => resource_path('views/errors/404.blade.php'),
                __DIR__.'/../resources/views/errors/405.blade.php' => resource_path('views/errors/405.blade.php'),
                __DIR__.'/../resources/views/errors/419.blade.php' => resource_path('views/errors/419.blade.php'),
                __DIR__.'/../resources/views/errors/429.blade.php' => resource_path('views/errors/429.blade.php'),
                __DIR__.'/../resources/views/errors/500.blade.php' => resource_path('views/errors/500.blade.php'),
                __DIR__.'/../resources/views/errors/503.blade.php' => resource_path('views/errors/503.blade.php'),
            ], 'ptah-errors');

            // Publish agent skills into the app's .claude/skills so Claude Code
            // (and any tool that reads .claude/skills) discovers them. The package
            // is the single source of truth; re-run vendor:publish --tag=ptah-skills
            // --force after upgrading ptah to refresh them.
            $this->publishes([
                __DIR__.'/../resources/boost/skills' => base_path('.claude/skills'),
            ], 'ptah-skills');

            // Publish Docker base environment (Dockerfile, Nginx, php.ini, docker-compose, .env.docker)
            $this->publishes([
                __DIR__.'/../stubs/docker/docker-compose.yml' => base_path('docker-compose.yml'),
                __DIR__.'/../stubs/docker/.env.docker' => base_path('.env.docker'),
                __DIR__.'/../stubs/docker/.dockerignore' => base_path('.dockerignore'),
                __DIR__.'/../stubs/docker/docker' => base_path('docker'),
            ], 'ptah-docker');
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

        if (empty($modulesEnabled)) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        // AI-specific alter-migrations only when the ai_agent module is enabled
        if (! empty($modulesEnabled['ai_agent'])) {
            $this->loadMigrationsFrom(__DIR__.'/Migrations/ai');
        }
    }

    /**
     * Registers Ptah Livewire components.
     */
    protected function registerLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            // NOTE: do not use '::' in aliases (reserved for Blade vendors in Livewire 4)
            Livewire::component('ptah-base-crud', BaseCrud::class);
            Livewire::component('ptah-search-dropdown', SearchDropdown::class);
            Livewire::component('ptah-crud-config', CrudConfig::class);
            Livewire::component('ptah-settings', SettingsPage::class);
            Livewire::component('ptah-exports-panel', ExportsPanel::class);

            if (config('ptah.modules.auth')) {
                Livewire::component('ptah-auth-login', LoginPage::class);
                Livewire::component('ptah-auth-forgot-password', ForgotPasswordPage::class);
                Livewire::component('ptah-auth-reset-password', ResetPasswordPage::class);
                Livewire::component('ptah-auth-two-factor', TwoFactorChallengePage::class);
                Livewire::component('ptah-auth-profile', ProfilePage::class);
            }

            if (config('ptah.modules.menu')) {
                Livewire::component('ptah-menu-list', MenuList::class);
            }

            if (config('ptah.modules.company')) {
                Livewire::component('ptah-company-list', CompanyList::class);
                Livewire::component('ptah-company-switcher', CompanySwitcher::class);
            }

            if (config('ptah.modules.permissions')) {
                Livewire::component('ptah-permission-department-list', DepartmentList::class);
                Livewire::component('ptah-permission-role-list', RoleList::class);
                Livewire::component('ptah-permission-page-list', PageList::class);
                Livewire::component('ptah-permission-user-list', UserPermissionList::class);
                Livewire::component('ptah-permission-audit-list', AuditList::class);
                Livewire::component('ptah-permission-guide', PermissionGuide::class);

                // Reinforcement, not the primary defense: each ACL list component's
                // boot() already calls RequiresMasterAccess::assertMasterAccess() on
                // every request. This makes the `ptah.master` route middleware itself
                // reapply to the AJAX requests those components send after the initial
                // mount, which Livewire otherwise skips (see PersistentMiddleware).
                Livewire::addPersistentMiddleware(PtahMaster::class);
            }

            if (config('ptah.modules.ai_agent')) {
                Livewire::component('ptah-ai-model-config-list', AiModelConfigList::class);
                Livewire::component('ptah-ai-chat-widget', AiChatWidget::class);
            }

            // Notifications — 'ptah.notifications.enabled', NOT 'ptah.modules.*'
            // (see the config's own docblock for why it is a top-level key).
            if (config('ptah.notifications.enabled')) {
                Livewire::component('ptah-notification-bell', NotificationBell::class);
            }
        }
    }

    /**
     * Registers the package's internal routes.
     */
    protected function registerRoutes(): void
    {
        if (app()->environment(['local', 'development'])) {
            /** @var Router $router */
            $router = $this->app->make('router');

            $router->get('/ptah-forge-demo', fn () => view('ptah::forge-demo'))
                ->name('ptah.forge.demo');
        }

        // Main Ptah routes (export, etc.)
        $this->loadRoutesFrom(__DIR__.'/../routes/ptah.php');

        // /ptah-settings (the screen explains how to install when the table is missing)
        $this->loadRoutesFrom(__DIR__.'/../routes/ptah-settings.php');

        if (config('ptah.modules.auth')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ptah-auth.php');
        }

        if (config('ptah.modules.menu')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ptah-menu.php');
        }

        if (config('ptah.modules.company')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ptah-company.php');
        }

        if (config('ptah.modules.permissions')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ptah-permissions.php');
        }

        if (config('ptah.modules.ai_agent')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ptah-ai.php');
        }
    }
}

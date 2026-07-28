<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Appends routes to routes/web.php or routes/api.php.
 *
 * Web: Route::resource(...)
 * API: Route::apiResource(...) inside Route::prefix('{ptah.api.prefix}/v1')
 *      ->middleware(ptah.api.middleware). routes/api.php is REQUIRED (created
 *      by `php artisan install:api`) — there is no fallback to web.php, since
 *      that group carries CSRF/session middleware instead of API auth and
 *      would either 419 on write requests or silently ship an open endpoint.
 */
class RouteGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        // This method is only called when not in combined mode.
        // In combined mode, ScaffoldCommand calls generateWebRoute/generateApiRoute directly.
        return $context->withViews
            ? $this->appendWebRoute($context)
            : $this->appendApiRoute($context);
    }

    /** Exposed for ScaffoldCommand to use in combined mode (web + api). */
    public function generateWebRoute(EntityContext $context): GeneratorResult
    {
        return $this->appendWebRoute($context);
    }

    /** Exposed for ScaffoldCommand to use in combined mode (web + api). */
    public function generateApiRoute(EntityContext $context): GeneratorResult
    {
        return $this->appendApiRoute($context);
    }

    protected function label(): string
    {
        return 'Routes';
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function appendWebRoute(EntityContext $context): GeneratorResult
    {
        $routesPath = base_path('routes/web.php');
        $label = 'Routes [web.php]';

        if (! $this->files->exists($routesPath)) {
            return GeneratorResult::error($label, $routesPath, 'routes/web.php not found.');
        }

        $controllerFQN = $context->subNs($context->rootNamespace.'Http\\Controllers')."\\{$context->entity}Controller";

        // With the auth module active, gate the screen behind login so anonymous
        // visitors are redirected to /login instead of hitting the permission 403.
        $middleware = config('ptah.modules.auth') ? "->middleware('auth')" : '';
        $routeEntry = "\nRoute::get('{$context->entityLower}', [\\{$controllerFQN}::class, 'index'])"
            ."{$middleware}->name('{$context->entityLower}.index');";

        return $this->appendToRouteFile($routesPath, $routeEntry, $context->entityLower, $label);
    }

    private function appendApiRoute(EntityContext $context): GeneratorResult
    {
        $routesPath = base_path('routes/api.php');
        $label = 'Routes [api.php]';

        // No fallback to web.php: that group carries the CSRF/session
        // middleware instead of API auth, which either 419s on write requests
        // or — worse — ships the apiResource (including DELETE) unauthenticated.
        if (! $this->files->exists($routesPath)) {
            return GeneratorResult::error(
                $label,
                $routesPath,
                'routes/api.php not found — run "php artisan install:api" first (it also installs Sanctum, '.
                'required by the default api middleware). API routes were NOT generated to avoid creating '.
                'unauthenticated endpoints.'
            );
        }

        $controllerFQN = $context->subNs($context->rootNamespace.'Http\\Controllers\\API')."\\{$context->entity}ApiController";

        $prefix = trim((string) config('ptah.api.prefix', 'api'), '/');
        $prefix = $prefix === '' ? 'v1' : "{$prefix}/v1";

        // Never generate an open route: an empty/misconfigured value falls
        // back to the safe default instead of an unauthenticated apiResource.
        $middleware = (array) config('ptah.api.middleware', ['api', 'auth:sanctum']);
        if ($middleware === []) {
            $middleware = ['api', 'auth:sanctum'];
        }

        $middlewareList = implode(', ', array_map(
            fn (string $m) => "'".addslashes($m)."'",
            $middleware
        ));

        $routeEntry = "\nRoute::prefix('{$prefix}')\n    ->middleware([{$middlewareList}])\n    ->group(function () {\n        Route::apiResource('{$context->entityPlural}', \\{$controllerFQN}::class);\n    });";

        // routes/api.php only exists after `php artisan install:api`, which
        // also installs Sanctum — but a project may still remove/rename the
        // guard afterwards. Warn inline (instead of skipping generation) so
        // the route stays protected while the misconfiguration is visible in
        // the diff — there is no warning channel in GeneratorResult today.
        if (in_array('auth:sanctum', $middleware, true) && ! config('auth.guards.sanctum')) {
            $routeEntry = "\n// WARNING: middleware 'auth:sanctum' is used below but the \"sanctum\" guard is not".
                "\n// configured (config('auth.guards.sanctum') is empty). Run \"php artisan install:api\" to fix".
                "\n// it — until then every request to this route will fail at runtime.".$routeEntry;
        }

        return $this->appendToRouteFile($routesPath, $routeEntry, $context->entityPlural, $label);
    }

    private function appendToRouteFile(
        string $routesPath,
        string $routeEntry,
        string $routeKey,
        string $label
    ): GeneratorResult {
        $content = $this->files->get($routesPath);

        if (str_contains($content, "'{$routeKey}'")) {
            return GeneratorResult::skipped($label, $routesPath);
        }

        $this->files->append($routesPath, $routeEntry);

        return GeneratorResult::done($label, $routesPath);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Generators\GeneratorResult;
use Ptah\Generators\Contracts\GeneratorInterface;
use Ptah\Support\EntityContext;

/**
 * Appends routes to routes/web.php or routes/api.php.
 *
 * Web: Route::resource(...)
 * API: Route::apiResource(...) inside Route::prefix('v1')
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
        $label      = 'Routes [web.php]';

        if (! $this->files->exists($routesPath)) {
            return GeneratorResult::error($label, $routesPath, 'routes/web.php not found.');
        }

        $controllerFQN = $context->subNs($context->rootNamespace . "Http\\Controllers") . "\\{$context->entity}Controller";
        $routeEntry    = "\nRoute::get('{$context->entityLower}', [\\{$controllerFQN}::class, 'index'])->name('{$context->entityLower}.index');";

        return $this->appendToRouteFile($routesPath, $routeEntry, $context->entityLower, $label);
    }

    private function appendApiRoute(EntityContext $context): GeneratorResult
    {
        $routesPath = base_path('routes/api.php');
        $label      = 'Routes [api.php]';

        if (! $this->files->exists($routesPath)) {
            // Try web.php as fallback
            $routesPath = base_path('routes/web.php');
            if (! $this->files->exists($routesPath)) {
                return GeneratorResult::error($label, $routesPath, 'routes/api.php not found.');
            }
        }

        $controllerFQN = $context->subNs($context->rootNamespace . "Http\\Controllers\\API") . "\\{$context->entity}ApiController";
        $routeEntry    = "\nRoute::prefix('v1')->group(function () {\n    Route::apiResource('{$context->entityPlural}', \\{$controllerFQN}::class);\n});";

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

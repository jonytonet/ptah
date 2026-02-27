<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Generators\GeneratorResult;
use Ptah\Generators\Contracts\GeneratorInterface;
use Ptah\Support\EntityContext;

/**
 * Adiciona rotas ao arquivo routes/web.php ou routes/api.php.
 *
 * Web: Route::resource(...)
 * API: Route::apiResource(...) dentro de prefix('api')
 */
class RouteGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        return $context->withViews
            ? $this->appendWebRoute($context)
            : $this->appendApiRoute($context);
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
            return GeneratorResult::error($label, $routesPath, 'routes/web.php não encontrado.');
        }

        $controllerFQN = $context->rootNamespace . "Http\\Controllers\\{$context->entity}Controller";
        $routeEntry    = "\nRoute::get('{$context->entityLower}', [\\{$controllerFQN}::class, 'index'])->name('{$context->entityLower}.index');";

        return $this->appendToRouteFile($routesPath, $routeEntry, $context->entityLower, $label);
    }

    private function appendApiRoute(EntityContext $context): GeneratorResult
    {
        $routesPath = base_path('routes/api.php');
        $label      = 'Routes [api.php]';

        if (! $this->files->exists($routesPath)) {
            // Tenta web.php como fallback
            $routesPath = base_path('routes/web.php');
            if (! $this->files->exists($routesPath)) {
                return GeneratorResult::error($label, $routesPath, 'routes/api.php não encontrado.');
            }
        }

        $controllerFQN = $context->rootNamespace . "Http\\Controllers\\Api\\{$context->entity}ApiController";
        $routeEntry    = "\nRoute::apiResource('{$context->entityPlural}', \\{$controllerFQN}::class);";

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

<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Support\Str;
use Ptah\Support\EntityContext;

/**
 * Gera o Controller API da entidade.
 *
 * Stub: controller.api.stub
 * Só é executado quando --api está ativo.
 *
 * Namespace gerado: App\Http\Controllers\API\{Folder}\{Entity}ApiController
 * Requests gerados: App\Http\Requests\API\{Folder}\{Create|Update}{Entity}ApiRequest
 */
class ControllerApiGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $dir  = $context->subPath(config('ptah.paths.controllers') . '/API');
        $path = "{$dir}/{$context->entity}ApiController.php";

        $ns         = $context->subNs($context->rootNamespace . 'Http\\Controllers\\API');
        $requestNs  = $context->subNs($context->rootNamespace . 'Http\\Requests\\API');
        $serviceFqn = $context->subNs($context->rootNamespace . 'Services') . '\\' . $context->entity . 'Service';
        $resourceFqn = $context->subNs($context->rootNamespace . 'Http\\Resources') . '\\' . $context->entity . 'Resource';

        // Swagger: tag = top-level folder (ex: 'Catalog') ou nome da entidade
        $swaggerTag  = $this->resolveSwaggerTag($context);
        // Swagger: path = entity plural em kebab-case (ex: 'products', 'animal-breeds')
        $swaggerPath = Str::kebab($context->entityPlural);

        return $this->writeFile(
            path: $path,
            stub: 'controller.api',
            replacements: [
                'namespace'          => $ns,
                'service_fqn'        => $serviceFqn,
                'request_create_fqn' => $requestNs . '\\Create' . $context->entity . 'ApiRequest',
                'request_update_fqn' => $requestNs . '\\Update' . $context->entity . 'ApiRequest',
                'resource_fqn'       => $resourceFqn,
                'entity'             => $context->entity,
                'entity_lower'       => $context->entityLower,
                'entities'           => $context->entityPlural,
                'swagger_tag'        => $swaggerTag,
                'swagger_path'       => $swaggerPath,
            ],
            force: $context->force,
            labelOverride: "Controller API [{$context->entity}ApiController]",
        );
    }

    public function shouldRun(EntityContext $context): bool
    {
        return ! $context->withViews; // só roda no modo --api
    }

    protected function label(): string
    {
        return 'Controller (API)';
    }

    /**
     * Extrai o top-level folder do subFolder para usar como tag Swagger.
     * Ex: subFolder='Catalog/Product' → 'Catalog'
     *     subFolder='Catalog'         → 'Catalog'
     *     subFolder=''               → nome da entidade
     */
    private function resolveSwaggerTag(EntityContext $context): string
    {
        if (empty($context->subFolder)) {
            return $context->entity;
        }

        $parts = explode('/', $context->subFolder);

        return $parts[0];
    }
}

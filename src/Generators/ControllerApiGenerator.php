<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera o Controller API da entidade.
 *
 * Stub: controller.api.stub
 * Placeholders: namespace, entity, entity_lower, entities, rootNamespace
 *
 * Só é executado quando --api está ativo.
 */
class ControllerApiGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $dir  = $context->subPath(config('ptah.paths.controllers') . '/Api');
        $path = "{$dir}/{$context->entity}ApiController.php";
        $ns          = $context->subNs($context->rootNamespace . 'Http\\Controllers\\Api');
        $requestNs   = $context->subNs($context->rootNamespace . 'Http\\Requests');
        $serviceFqn  = $context->subNs($context->rootNamespace . 'Services') . '\\' . $context->entity . 'Service';
        $resourceFqn = $context->subNs($context->rootNamespace . 'Http\\Resources') . '\\' . $context->entity . 'Resource';

        return $this->writeFile(
            path: $path,
            stub: 'controller.api',
            replacements: [
                'namespace'          => $ns,
                'service_fqn'        => $serviceFqn,
                'request_store_fqn'  => $requestNs . '\\Store' . $context->entity . 'Request',
                'request_update_fqn' => $requestNs . '\\Update' . $context->entity . 'Request',
                'resource_fqn'       => $resourceFqn,
                'entity'             => $context->entity,
                'entity_lower'       => $context->entityLower,
                'entities'           => $context->entityPlural,
                'rootNamespace'      => $context->rootNamespace,
            ],
            force: $context->force,
            labelOverride: "Controller [{$context->entity}ApiController]",
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
}

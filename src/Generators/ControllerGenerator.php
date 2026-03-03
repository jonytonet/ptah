<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the Web Controller for the entity.
 *
 * Stub: controller.stub
 * Placeholders: namespace, entity, entity_lower, entities, rootNamespace
 *
 * Not executed when --api is active (use ControllerApiGenerator instead).
 */
class ControllerGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = $context->subPath(config('ptah.paths.controllers')) . "/{$context->entity}Controller.php";
        $ns         = $context->subNs($context->rootNamespace . 'Http\\Controllers');
        $serviceFqn = $context->subNs($context->rootNamespace . 'Services') . '\\' . $context->entity . 'Service';

        return $this->writeFile(
            path: $path,
            stub: 'controller',
            replacements: [
                'namespace'     => $ns,
                'service_fqn'   => $serviceFqn,
                'entity'        => $context->entity,
                'entity_lower'  => $context->entityLower,
                'entities'      => $context->entityPlural,
                'rootNamespace' => $context->rootNamespace,
            ],
            force: $context->force,
            labelOverride: "Controller [{$context->entity}Controller]",
        );
    }

    public function shouldRun(EntityContext $context): bool
    {
        return $context->withViews;
    }

    protected function label(): string
    {
        return 'Controller';
    }
}

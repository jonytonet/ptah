<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the API Resource for the entity.
 *
 * Stub: resource.stub
 * Placeholders: namespace, entity, resource_fields
 */
class ResourceGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = $context->subPath(config('ptah.paths.resources')) . "/{$context->entity}Resource.php";
        $ns   = $context->subNs($context->rootNamespace . 'Http\\Resources');

        return $this->writeFile(
            path: $path,
            stub: 'resource',
            replacements: [
                'namespace'       => $ns,
                'entity'          => $context->entity,
                'resource_fields' => $context->resourceFields(),
            ],
            force: $context->force,
            labelOverride: "Resource [{$context->entity}Resource]",
        );
    }

    protected function label(): string
    {
        return 'Resource';
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera o API Resource da entidade.
 *
 * Stub: resource.stub
 * Placeholders: namespace, entity, resource_fields
 */
class ResourceGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.resources') . "/{$context->entity}Resource.php";

        return $this->writeFile(
            path: $path,
            stub: 'resource',
            replacements: [
                'namespace'       => $context->rootNamespace . 'Http\\Resources',
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

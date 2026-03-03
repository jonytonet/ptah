<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the DTO (Data Transfer Object) for the entity.
 *
 * Stub: dto.stub
 * Placeholders: namespace, entity, dto_properties, dto_from_array
 */
class DtoGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = $context->subPath(config('ptah.paths.dtos')) . "/{$context->entity}DTO.php";
        $ns   = $context->subNs($context->rootNamespace . 'DTOs');

        return $this->writeFile(
            path: $path,
            stub: 'dto',
            replacements: [
                'namespace'      => $ns,
                'entity'         => $context->entity,
                'dto_properties' => $context->dtoProperties(),
                'dto_from_array' => $context->dtoFromArray(),
            ],
            force: $context->force,
            labelOverride: "DTO [{$context->entity}DTO]",
        );
    }

    protected function label(): string
    {
        return 'DTO';
    }
}

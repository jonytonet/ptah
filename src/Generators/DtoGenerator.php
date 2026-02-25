<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera o DTO (Data Transfer Object) da entidade.
 *
 * Stub: dto.stub
 * Placeholders: namespace, entity, dto_properties, dto_from_array
 */
class DtoGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.dtos') . "/{$context->entity}DTO.php";

        return $this->writeFile(
            path: $path,
            stub: 'dto',
            replacements: [
                'namespace'        => $context->rootNamespace . 'DTOs',
                'entity'           => $context->entity,
                'dto_properties'   => $context->dtoProperties(),
                'dto_from_array'   => $context->dtoFromArray(),
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

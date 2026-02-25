<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera o Service da entidade.
 *
 * Stub: service.stub
 * Placeholders: namespace, entity, rootNamespace
 */
class ServiceGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.services') . "/{$context->entity}Service.php";

        return $this->writeFile(
            path: $path,
            stub: 'service',
            replacements: [
                'namespace'     => $context->rootNamespace . 'Services',
                'entity'        => $context->entity,
                'rootNamespace' => $context->rootNamespace,
            ],
            force: $context->force,
            labelOverride: "Service [{$context->entity}Service]",
        );
    }

    protected function label(): string
    {
        return 'Service';
    }
}

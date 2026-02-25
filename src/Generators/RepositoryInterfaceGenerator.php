<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera a Interface do Repositório.
 *
 * Stub: repository.interface.stub
 * Placeholders: namespace, entity
 */
class RepositoryInterfaceGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $dir  = config('ptah.paths.repositories') . '/Contracts';
        $path = "{$dir}/{$context->entity}RepositoryInterface.php";

        return $this->writeFile(
            path: $path,
            stub: 'repository.interface',
            replacements: [
                'namespace' => $context->rootNamespace . 'Repositories\\Contracts',
                'entity'    => $context->entity,
            ],
            force: $context->force,
            labelOverride: "Interface [{$context->entity}RepositoryInterface]",
        );
    }

    protected function label(): string
    {
        return 'RepositoryInterface';
    }
}

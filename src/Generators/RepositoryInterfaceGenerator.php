<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the Repository Interface.
 *
 * Stub: repository.interface.stub
 * Placeholders: namespace, entity
 */
class RepositoryInterfaceGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $dir  = $context->subPath(config('ptah.paths.repositories') . '/Contracts');
        $path = "{$dir}/{$context->entity}RepositoryInterface.php";
        $ns   = $context->subNs($context->rootNamespace . 'Repositories\\Contracts');

        return $this->writeFile(
            path: $path,
            stub: 'repository.interface',
            replacements: [
                'namespace' => $ns,
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

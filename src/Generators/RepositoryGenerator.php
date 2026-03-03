<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the concrete Repository implementation.
 *
 * Stub: repository.stub
 * Placeholders: namespace, entity, rootNamespace
 */
class RepositoryGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path         = $context->subPath(config('ptah.paths.repositories')) . "/{$context->entity}Repository.php";
        $ns           = $context->subNs($context->rootNamespace . 'Repositories');
        $interfaceFqn = $context->subNs($context->rootNamespace . 'Repositories\\Contracts') . '\\' . $context->entity . 'RepositoryInterface';

        return $this->writeFile(
            path: $path,
            stub: 'repository',
            replacements: [
                'namespace'     => $ns,
                'entity'        => $context->entity,
                'rootNamespace' => $context->rootNamespace,
                'model_fqn'     => $context->modelFqn,
                'interface_fqn' => $interfaceFqn,
            ],
            force: $context->force,
            labelOverride: "Repository [{$context->entity}Repository]",
        );
    }

    protected function label(): string
    {
        return 'Repository';
    }
}

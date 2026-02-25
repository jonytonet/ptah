<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera a implementação concreta do Repositório.
 *
 * Stub: repository.stub
 * Placeholders: namespace, entity, rootNamespace
 */
class RepositoryGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.repositories') . "/{$context->entity}Repository.php";

        return $this->writeFile(
            path: $path,
            stub: 'repository',
            replacements: [
                'namespace'     => $context->rootNamespace . 'Repositories',
                'entity'        => $context->entity,
                'rootNamespace' => $context->rootNamespace,
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

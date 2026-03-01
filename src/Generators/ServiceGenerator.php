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
        $path         = $context->subPath(config('ptah.paths.services')) . "/{$context->entity}Service.php";
        $ns           = $context->subNs($context->rootNamespace . 'Services');
        $interfaceFqn = $context->subNs($context->rootNamespace . 'Repositories\\Contracts') . '\\' . $context->entity . 'RepositoryInterface';

        return $this->writeFile(
            path: $path,
            stub: 'service',
            replacements: [
                'namespace'     => $ns,
                'interface_fqn' => $interfaceFqn,
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

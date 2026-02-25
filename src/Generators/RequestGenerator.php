<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera os FormRequests de Store e Update da entidade.
 *
 * Stubs: request.store.stub, request.update.stub
 * Placeholders: namespace, entity, rules
 */
class RequestGenerator extends AbstractGenerator
{
    /**
     * Gera StoreRequest e UpdateRequest em sequência.
     * O GeneratorResult retornado representa apenas o último (Update).
     * O ScaffoldCommand deve chamar generateStore() e generateUpdate() separadamente.
     */
    public function generate(EntityContext $context): GeneratorResult
    {
        // Gera os dois; neste método, retorna o do Update como representativo.
        $this->generateStore($context);
        return $this->generateUpdate($context);
    }

    public function generateStore(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.requests') . "/Store{$context->entity}Request.php";

        return $this->writeFile(
            path: $path,
            stub: 'request.store',
            replacements: [
                'namespace' => $context->rootNamespace . 'Http\\Requests',
                'entity'    => $context->entity,
                'rules'     => $context->validationRulesStore(),
            ],
            force: $context->force,
            labelOverride: "Request [Store{$context->entity}]",
        );
    }

    public function generateUpdate(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.requests') . "/Update{$context->entity}Request.php";

        return $this->writeFile(
            path: $path,
            stub: 'request.update',
            replacements: [
                'namespace' => $context->rootNamespace . 'Http\\Requests',
                'entity'    => $context->entity,
                'rules'     => $context->validationRulesUpdate(),
            ],
            force: $context->force,
            labelOverride: "Request [Update{$context->entity}]",
        );
    }

    protected function label(): string
    {
        return 'Request';
    }
}

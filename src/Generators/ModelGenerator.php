<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera o Model Eloquent da entidade.
 *
 * Stub: model.stub
 * Placeholders: namespace, entity, table, fillable, casts,
 *               soft_deletes_use, soft_deletes_trait
 */
class ModelGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $path = config('ptah.paths.models') . "/{$context->entity}.php";

        $softDeletesUse   = '';
        $softDeletesTrait = '';

        if ($context->withSoftDeletes) {
            $softDeletesUse   = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $softDeletesTrait = "    use SoftDeletes;\n";
        }

        return $this->writeFile(
            path: $path,
            stub: 'model',
            replacements: [
                'namespace'          => $context->rootNamespace . 'Models',
                'entity'             => $context->entity,
                'table'              => $context->table,
                'fillable'           => $context->fillableList(),
                'casts'              => $context->castsList(),
                'soft_deletes_use'   => $softDeletesUse,
                'soft_deletes_trait' => $softDeletesTrait,
            ],
            force: $context->force,
            labelOverride: "Model [{$context->entity}]",
        );
    }

    protected function label(): string
    {
        return 'Model';
    }
}

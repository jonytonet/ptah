<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera apenas a view index (BaseCrud gerencia create/edit/show via modal Livewire).
 *
 * Stubs: view.index.stub
 * Placeholders: entity, entity_lower, entities
 *
 * Só é executado quando --api NÃO está ativo.
 */
class ViewGenerator extends AbstractGenerator
{
    /**
     * Gera a view index e retorna o resultado.
     */
    public function generate(EntityContext $context): GeneratorResult
    {
        return $this->generateView($context, 'index');
    }

    public function generateView(EntityContext $context, string $view): GeneratorResult
    {
        $viewDir = config('ptah.paths.views') . "/{$context->entityLower}";
        $path    = "{$viewDir}/{$view}.blade.php";
        $label   = "View [{$context->entityLower}/{$view}]";

        if ($this->files->exists($path) && ! $context->force) {
            return GeneratorResult::skipped($label, $path);
        }

        $directory = dirname($path);
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        try {
            $content = $this->resolveStub("view.{$view}");
            $content = $this->replaceVars($content, [
                'entity'       => $context->entity,
                'entity_lower' => $context->entityLower,
                'entities'     => $context->entityPlural,
            ]);

            $this->files->put($path, $content);

            return GeneratorResult::done($label, $path);
        } catch (\Throwable $e) {
            return GeneratorResult::error($label, $path, $e->getMessage());
        }
    }

    public function shouldRun(EntityContext $context): bool
    {
        return $context->withViews;
    }

    protected function label(): string
    {
        return 'Views';
    }
}

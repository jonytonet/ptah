<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\CssFramework;
use Ptah\Support\EntityContext;
use Ptah\Support\LabelHumanizer;

/**
 * Generates only the index view (BaseCrud manages create/edit/show via Livewire modals).
 *
 * Stubs: view.index.stub
 * Placeholders: entity, entity_lower, entities, entity_title
 *
 * Skipped only with --api-only (`--api` generates the screen too).
 */
class ViewGenerator extends AbstractGenerator
{
    /**
     * Generates the index view and returns the result.
     */
    public function generate(EntityContext $context): GeneratorResult
    {
        return $this->generateView($context, 'index');
    }

    public function generateView(EntityContext $context, string $view): GeneratorResult
    {
        $viewDir = config('ptah.paths.views')."/{$context->entityLower}";
        $path = "{$viewDir}/{$view}.blade.php";
        $label = "View [{$context->entityLower}/{$view}]";

        if ($this->files->exists($path) && ! $context->force) {
            return GeneratorResult::skipped($label, $path);
        }

        $directory = dirname($path);
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        try {
            // The React/Vue stack (1.59.0): the page is a shell the front end
            // mounts on, and the screen is registered for the API.
            $app = $view === 'index' && self::usesAppStack();
            $content = $this->resolveStub($app ? 'view.index.app' : "view.{$view}");
            $content = $this->replaceVars($content, [
                'entity' => $context->entity,
                'entity_lower' => $context->entityLower,
                'entities' => $context->entityPlural,
                'entity_title' => LabelHumanizer::make($context->entityLower),
                'crud_identifier' => $context->subFolder
                    ? $context->subFolder.'/'.$context->entity
                    : $context->entity,
            ]);

            if (! $app && CssFramework::isBootstrap()) {
                $content = self::scopePageHeader($content);
            }

            $this->files->put($path, $content);

            if ($app) {
                $this->registerScreen($context);
            }

            return GeneratorResult::done($label, $path);
        } catch (\Throwable $e) {
            return GeneratorResult::error($label, $path, $e->getMessage());
        }
    }

    public function shouldRun(EntityContext $context): bool
    {
        return $context->withViews;
    }

    /**
     * Bootstrap mode (ptah.css.framework): the page header the stub writes is
     * Tailwind markup sitting in the layout's slot, which is host territory
     * there — the scope marker puts it back under ptah's stylesheet. A
     * published stub without that header is left as written.
     */
    public static function scopePageHeader(string $content): string
    {
        $header = '<div class="flex items-center justify-between mb-6">';
        $position = strpos($content, $header);

        if ($position === false) {
            return $content;
        }

        return substr_replace($content, '<div '.CssFramework::SCOPE_ATTRIBUTE.' class="flex items-center justify-between mb-6">', $position, strlen($header));
    }

    public static function usesAppStack(): bool
    {
        return in_array(config('ptah.stack', 'livewire'), ['react', 'vue'], true);
    }

    /**
     * Adds the entity to routes/ptah-screens.php — the screens the headless
     * API serves. The key is the page's path, so the screen config of that
     * path is the one loaded, exactly as the Livewire screen picks it.
     */
    private function registerScreen(EntityContext $context): void
    {
        $path = base_path('routes/ptah-screens.php');

        if (! $this->files->exists($path)) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $this->files->get(__DIR__.'/../Stubs/stack/ptah-screens.stub'));
        }

        $identifier = $context->subFolder ? $context->subFolder.'/'.$context->entity : $context->entity;
        $line = "CrudScreen::register('{$context->entityLower}', '{$identifier}');";
        $content = $this->files->get($path);

        if (! str_contains($content, "CrudScreen::register('{$context->entityLower}'")) {
            $this->files->put($path, rtrim($content)."\n".$line."\n");
        }
    }

    protected function label(): string
    {
        return 'Views';
    }
}

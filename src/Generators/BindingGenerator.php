<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Automatically injects the Interface→Concrete binding into AppServiceProvider.
 *
 * Behaviour:
 *  - Locates app/Providers/AppServiceProvider.php
 *  - Adds `use` imports if they do not already exist
 *  - Inserts `$this->app->bind(...)` inside register()
 *  - Idempotent: skips if the binding already exists
 */
class BindingGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $providerPath = app_path('Providers/AppServiceProvider.php');
        $label        = 'Binding [AppServiceProvider]';

        if (! $this->files->exists($providerPath)) {
            return GeneratorResult::error($label, $providerPath, 'AppServiceProvider.php not found.');
        }

        $content    = $this->files->get($providerPath);
        $entity     = $context->entity;
        $ns         = rtrim($context->rootNamespace, '\\');
        $interface  = $context->subNs("{$ns}\\Repositories\\Contracts") . "\\{$entity}RepositoryInterface";
        $repository = $context->subNs("{$ns}\\Repositories") . "\\{$entity}Repository";

        // Idempotent — already bound?
        if (str_contains($content, "{$entity}RepositoryInterface::class")) {
            return GeneratorResult::skipped($label, $providerPath);
        }

        // 1. Add imports
        $useInterface  = "use {$interface};";
        $useRepository = "use {$repository};";

        if (! str_contains($content, $useInterface)) {
            $content = $this->addUseImport($content, $useInterface);
        }
        if (! str_contains($content, $useRepository)) {
            $content = $this->addUseImport($content, $useRepository);
        }

        // 2. Inject bind() inside register()
        $binding = "\$this->app->bind({$entity}RepositoryInterface::class, {$entity}Repository::class);";
        $content = $this->injectIntoRegister($content, $binding);

        $this->files->put($providerPath, $content);

        return GeneratorResult::done($label, $providerPath);
    }

    protected function label(): string
    {
        return 'Binding [AppServiceProvider]';
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Inserts a `use` statement after the last existing `use` (or after namespace).
     */
    private function addUseImport(string $content, string $useStatement): string
    {
        // Search all existing `use` statements
        if (preg_match_all('/^use\s+[^;]+;/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $last      = end($matches[0]);
            $insertPos = $last[1] + strlen($last[0]);

            return substr($content, 0, $insertPos) . "\n" . $useStatement . substr($content, $insertPos);
        }

        // No `use` found — insert after namespace declaration
        if (preg_match('/^namespace\s+[^;]+;/m', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertPos = $m[0][1] + strlen($m[0][0]);

            return substr($content, 0, $insertPos) . "\n\n" . $useStatement . substr($content, $insertPos);
        }

        return $content;
    }

    /**
     * Injects a line into the body of register(), replacing the placeholder
     * comment or appending before the closing brace.
     */
    private function injectIntoRegister(string $content, string $binding): string
    {
        // Find the opening of register()
        $pattern = '/(public\s+function\s+register\s*\(\s*\)\s*(?::\s*void\s*)?\{)/';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            // No register() found — return unchanged
            return $content;
        }

        $openPos = $matches[0][1] + strlen($matches[0][0]);

        // Find the matching closing brace
        $depth  = 1;
        $pos    = $openPos;
        $length = strlen($content);

        while ($pos < $length && $depth > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
            $pos++;
        }

        $closePos = $pos - 1; // position of '}'
        $inner    = substr($content, $openPos, $closePos - $openPos);

        // Remove the `//` placeholder but preserve real content
        $cleanedInner = preg_replace('/^\s*\/\/\s*$/m', '', $inner);
        $cleanedInner = rtrim($cleanedInner ?? $inner);

        $newInner = $cleanedInner !== ''
            ? $cleanedInner . "\n        " . $binding . "\n    "
            : "\n        " . $binding . "\n    ";

        return substr($content, 0, $openPos) . $newInner . substr($content, $closePos);
    }
}

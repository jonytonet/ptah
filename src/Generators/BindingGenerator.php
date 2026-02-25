<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Injeta automaticamente o binding Interface→Concrete no AppServiceProvider.
 *
 * Comportamento:
 *  - Encontra app/Providers/AppServiceProvider.php
 *  - Adiciona os `use` imports se ainda não existirem
 *  - Insere `$this->app->bind(...)` dentro de register()
 *  - Idempotente: pula se o binding já existir
 */
class BindingGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $providerPath = app_path('Providers/AppServiceProvider.php');
        $label        = 'Binding [AppServiceProvider]';

        if (! $this->files->exists($providerPath)) {
            return GeneratorResult::error($label, $providerPath, 'AppServiceProvider.php não encontrado.');
        }

        $content    = $this->files->get($providerPath);
        $entity     = $context->entity;
        $ns         = rtrim($context->rootNamespace, '\\');
        $interface  = "{$ns}\\Repositories\\Contracts\\{$entity}RepositoryInterface";
        $repository = "{$ns}\\Repositories\\{$entity}Repository";

        // Idempotente — já vinculado?
        if (str_contains($content, "{$entity}RepositoryInterface::class")) {
            return GeneratorResult::skipped($label, $providerPath);
        }

        // 1. Adiciona imports
        $useInterface  = "use {$interface};";
        $useRepository = "use {$repository};";

        if (! str_contains($content, $useInterface)) {
            $content = $this->addUseImport($content, $useInterface);
        }
        if (! str_contains($content, $useRepository)) {
            $content = $this->addUseImport($content, $useRepository);
        }

        // 2. Injeta o bind() dentro de register()
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
     * Insere um `use` statement após o último `use` existente (ou após namespace).
     */
    private function addUseImport(string $content, string $useStatement): string
    {
        // Procura todos os `use` statements existentes
        if (preg_match_all('/^use\s+[^;]+;/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $last      = end($matches[0]);
            $insertPos = $last[1] + strlen($last[0]);

            return substr($content, 0, $insertPos) . "\n" . $useStatement . substr($content, $insertPos);
        }

        // Sem `use` — insere após a declaração de namespace
        if (preg_match('/^namespace\s+[^;]+;/m', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertPos = $m[0][1] + strlen($m[0][0]);

            return substr($content, 0, $insertPos) . "\n\n" . $useStatement . substr($content, $insertPos);
        }

        return $content;
    }

    /**
     * Injeta uma linha dentro do corpo de register(), substituindo o comentário
     * placeholder ou anexando antes do fechamento da função.
     */
    private function injectIntoRegister(string $content, string $binding): string
    {
        // Encontra a abertura de register()
        $pattern = '/(public\s+function\s+register\s*\(\s*\)\s*(?::\s*void\s*)?\{)/';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Sem register() — não altera
            return $content;
        }

        $openPos = $matches[0][1] + strlen($matches[0][0]);

        // Encontra a chave de fechamento correspondente
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

        $closePos = $pos - 1; // posição do '}'
        $inner    = substr($content, $openPos, $closePos - $openPos);

        // Remove o placeholder `//` mas preserva conteúdo real
        $cleanedInner = preg_replace('/^\s*\/\/\s*$/m', '', $inner);
        $cleanedInner = rtrim($cleanedInner ?? $inner);

        $newInner = $cleanedInner !== ''
            ? $cleanedInner . "\n        " . $binding . "\n    "
            : "\n        " . $binding . "\n    ";

        return substr($content, 0, $openPos) . $newInner . substr($content, $closePos);
    }
}

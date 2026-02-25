<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Generators\Contracts\GeneratorInterface;
use Ptah\Support\EntityContext;

/**
 * Classe base para todos os geradores.
 *
 * Fornece:
 *  - writeFile()    → escreve o arquivo com verificação de sobrescrita
 *  - resolveStub()  → prioriza stubs publicados em stubs/ptah/ sobre os do pacote
 *  - replaceVars()  → substitui {{ variavel }} no conteúdo do stub
 *  - shouldRun()    → padrão: sempre roda (sobrescreva quando necessário)
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    public function __construct(protected Filesystem $files) {}

    /**
     * Por padrão, todos os geradores são executados.
     * Sobrescreva para implementar condições (ex: só roda sem --api).
     */
    public function shouldRun(EntityContext $context): bool
    {
        return true;
    }

    // ── Helpers protegidos ─────────────────────────────────────────────────

    /**
     * Cria o diretório e escreve o arquivo gerado a partir de um stub.
     *
     * @param array<string, string> $replacements
     */
    protected function writeFile(
        string $path,
        string $stub,
        array  $replacements,
        bool   $force,
        ?string $labelOverride = null,
    ): GeneratorResult {
        $label = $labelOverride ?? $this->label();

        if ($this->files->exists($path) && ! $force) {
            return GeneratorResult::skipped($label, $path);
        }

        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        try {
            $content = $this->resolveStub($stub);
            $content = $this->replaceVars($content, $replacements);
            $this->files->put($path, $content);

            return GeneratorResult::done($label, $path);
        } catch (\Throwable $e) {
            return GeneratorResult::error($label, $path, $e->getMessage());
        }
    }

    /**
     * Retorna o label exibido no terminal.
     * Cada subclasse deve definir seu label.
     */
    abstract protected function label(): string;

    /**
     * Resolve o conteúdo de um stub.
     * Prioriza stubs publicados em stubs/ptah/ (customizados pelo usuário).
     */
    protected function resolveStub(string $stub): string
    {
        $published = base_path("stubs/ptah/{$stub}.stub");

        if ($this->files->exists($published)) {
            return $this->files->get($published);
        }

        $package = __DIR__ . "/../Stubs/{$stub}.stub";

        if (! $this->files->exists($package)) {
            throw new \RuntimeException("Stub [{$stub}.stub] não encontrado.");
        }

        return $this->files->get($package);
    }

    /**
     * Substitui todos os placeholders {{ variavel }} no conteúdo do stub.
     *
     * @param array<string, string> $replacements
     */
    protected function replaceVars(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }
}

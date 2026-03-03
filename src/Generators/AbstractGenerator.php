<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Generators\Contracts\GeneratorInterface;
use Ptah\Support\EntityContext;

/**
 * Base class for all generators.
 *
 * Provides:
 *  - writeFile()    → writes the file with overwrite check
 *  - resolveStub()  → prefers stubs published in stubs/ptah/ over package stubs
 *  - replaceVars()  → replaces {{ variable }} placeholders in stub content
 *  - shouldRun()    → default: always runs (override when conditions are needed)
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    public function __construct(protected Filesystem $files) {}

    /**
     * All generators run by default.
     * Override to add conditions (e.g. only run without --api).
     */
    public function shouldRun(EntityContext $context): bool
    {
        return true;
    }

    // ── Helpers protegidos ─────────────────────────────────────────────────

    /**
     * Creates the directory and writes the file generated from a stub.
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
     * Returns the label displayed in the terminal.
     * Each subclass must define its own label.
     */
    abstract protected function label(): string;

    /**
     * Resolves the content of a stub file.
     * Prefers stubs published in stubs/ptah/ (customised by the user).
     */
    protected function resolveStub(string $stub): string
    {
        $published = base_path("stubs/ptah/{$stub}.stub");

        if ($this->files->exists($published)) {
            return $this->files->get($published);
        }

        $package = __DIR__ . "/../Stubs/{$stub}.stub";

        if (! $this->files->exists($package)) {
            throw new \RuntimeException("Stub [{$stub}.stub] not found.");
        }

        return $this->files->get($package);
    }

    /**
     * Replaces all {{ variable }} placeholders in stub content.
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

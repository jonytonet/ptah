<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Blade only evaluates a `:attr="..."` bound attribute when the tag is a Blade
 * component (`<x-...>`). On a plain HTML tag (`<button>`, `<input>`, `<nav>`, ...)
 * the colon is emitted literally into the DOM, so `:title="__('...')"` becomes a
 * plain `:title` attribute holding the raw string `__('ptah::ui.foo')` — and every
 * page in this package wraps its content in a root `x-data`, so Alpine picks up
 * that stray colon-prefixed attribute and evaluates `__('ptah::ui.foo')` as a JS
 * expression, throwing a silent `ReferenceError` in the console with no visible
 * failure in the rendered HTML and no PHP error.
 *
 * The fix is always the same: drop the leading colon and wrap the translation in
 * `{{ }}` — `title="{{ __('...') }}"` — which is the pattern already used across
 * this package's icon-only buttons (see `_table.blade.php`).
 *
 * This guard walks every Blade view and fails if it finds a bound `:title`,
 * `:placeholder` or `:aria-label` whose value carries a bare `__(` ANYWHERE —
 * not just at the start: `:title="$row->is_active ? __('a') : __('b')"` is the
 * same bug wearing a ternary. Values where the translation is compiled
 * server-side before Alpine ever sees it (`{{ __() }}` or `@js(__())`) are
 * legitimate and exempt.
 */
class BladeBoundAttributeOnPlainTagTest extends TestCase
{
    /**
     * Captures the whole bound attribute: name in group 1, raw value in group 2.
     * The value must contain a bare `__(`; `{{` and `@js(` mark server-side
     * compilation and exempt the occurrence (asserted per match, not here, so a
     * mixed value like `"@js(x) + __(y)"` still trips the guard via the bare part).
     */
    private const BOUND_ATTRIBUTE_PATTERN = '/:(title|placeholder|aria-label)="([^"]*__\([^"]*)"/';

    private static function isServerCompiled(string $value): bool
    {
        // Strip the server-compiled wrappers; if no bare `__(` survives, Alpine
        // only ever receives an already-evaluated string.
        $stripped = preg_replace('/\{\{.*?\}\}|@js\(.*\)/s', '', $value) ?? $value;

        return ! str_contains($stripped, '__(');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function bladeViewProvider(): array
    {
        $root = dirname(__DIR__, 3).'/resources/views';
        $files = [];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                continue;
            }

            // Only views that actually carry one of these bound attributes — otherwise
            // most of the suite reports as risky for performing no assertions, which
            // buries the signal this test exists to give.
            if (preg_match(self::BOUND_ATTRIBUTE_PATTERN, $source) !== 1) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = [$file->getPathname()];
        }

        if ($files === []) {
            throw new RuntimeException('BladeBoundAttributeOnPlainTagTest: nenhuma view Blade encontrada.');
        }

        ksort($files);

        return $files;
    }

    #[Test]
    #[DataProvider('bladeViewProvider')]
    public function bound_translation_attributes_only_appear_on_blade_components(string $path): void
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException('BladeBoundAttributeOnPlainTagTest: falha ao ler '.$path);
        }

        preg_match_all(self::BOUND_ATTRIBUTE_PATTERN, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            [, $pos] = $match[0];
            $attribute = $match[1][0];
            $value = $match[2][0];

            if (self::isServerCompiled($value)) {
                continue;
            }

            $tagName = self::enclosingTagName($source, $pos);

            if ($tagName === null) {
                continue;
            }

            $this->assertTrue(
                str_starts_with($tagName, 'x-'),
                sprintf(
                    "%s (offset %d): o atributo bindado ':%s' com '__(...)' esta na tag <%s>, que nao e um componente Blade.\n".
                    "Blade so avalia \":attr\" em tags <x-...>; em tag HTML pura o \":\" vaza para o DOM e o\n".
                    "Alpine (x-data raiz da pagina) tenta avaliar '__(...)' como JS, lancando ReferenceError\n".
                    'silencioso. Troque por %s="{{ ...__(...)... }}" (sem os dois pontos).',
                    basename($path),
                    $pos,
                    $attribute,
                    $tagName,
                    $attribute
                )
            );
        }

        // A view can carry the pattern with every occurrence legitimately
        // server-compiled (@js/{{ }}) — the sweep itself is the assertion then.
        $this->addToAssertionCount(1);
    }

    /**
     * Walks backwards from $offset to the nearest unclosed `<`, then reads the tag
     * name that follows it. Returns null if no enclosing tag can be found (should
     * not happen for well-formed Blade views).
     */
    private static function enclosingTagName(string $source, int $offset): ?string
    {
        $ltPos = strrpos(substr($source, 0, $offset), '<');

        if ($ltPos === false) {
            return null;
        }

        $rest = substr($source, $ltPos + 1);
        $match = [];

        if (preg_match('/^([a-zA-Z0-9\-:.]+)/', $rest, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}

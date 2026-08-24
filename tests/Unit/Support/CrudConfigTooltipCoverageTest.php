<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards the CrudConfig editor's field-tooltip coverage: every real
 * <input>/<select>/<textarea> element in crud-config.blade.php must carry an
 * explanatory `title` attribute, and that attribute must always be a
 * server-compiled `{{ __(...) }}` call — never a bound `:title="..."`, which
 * on a plain HTML tag leaks the raw `__(...)` string to Alpine (see
 * BladeBoundAttributeOnPlainTagTest).
 *
 * Blade/HTML comments are stripped before scanning — a guard that reads its
 * own explanatory prose (a `{{-- ... <input ... --}}` comment mentioning a
 * form element) is the single most recurring false-failure shape in this
 * repository's history.
 */
class CrudConfigTooltipCoverageTest extends TestCase
{
    private const VIEW_RELATIVE = 'resources/views/livewire/base-crud/crud-config.blade.php';

    /**
     * Scans from the opening `<tag` up to the first UNQUOTED `>` that closes
     * the start tag — quote-aware, so PHP/Blade expressions inside attribute
     * values (which routinely contain `->`, `=>`, or even a literal `>` in a
     * comparison operator) never terminate the match early. This only
     * captures the opening tag itself, not any nested content, which is all
     * that is needed to inspect its attributes.
     *
     * @return list<string>
     */
    private static function extractFormElementTags(string $source): array
    {
        $tags = [];
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            if ($source[$i] !== '<' || ! preg_match('/\G<(input|select|textarea)\b/i', $source, $tagMatch, 0, $i)) {
                $i++;

                continue;
            }

            $start = $i;
            $j = $i + strlen($tagMatch[0]);
            $quote = null;

            while ($j < $length) {
                $char = $source[$j];

                if ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }

                $j++;
            }

            $tags[] = substr($source, $start, $j - $start + 1);
            $i = $j + 1;
        }

        return $tags;
    }

    private static function readBlade(): string
    {
        $path = dirname(__DIR__, 3).'/'.self::VIEW_RELATIVE;
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException('CrudConfigTooltipCoverageTest: falha ao ler '.$path);
        }

        // Strip Blade comments and HTML comments BEFORE scanning — a guard
        // that trips on its own explanatory prose is the most recurring
        // false-failure shape in this repository (5 reincidences).
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;
        $source = preg_replace('/<!--.*?-->/s', '', $source) ?? $source;

        return $source;
    }

    #[Test]
    public function every_real_form_element_carries_a_title_attribute(): void
    {
        $tags = self::extractFormElementTags(self::readBlade());

        $this->assertNotEmpty($tags, 'CrudConfigTooltipCoverageTest: nenhum <input>/<select>/<textarea> encontrado — o parser quebrou.');

        $withoutTitle = array_values(array_filter(
            $tags,
            static fn (string $tag): bool => preg_match('/\btitle\s*=/i', $tag) !== 1
        ));

        $this->assertSame(
            [],
            $withoutTitle,
            "Elementos sem atributo title (mostrando os primeiros 200 chars de cada):\n".
            implode("\n---\n", array_map(static fn (string $t) => substr($t, 0, 200), $withoutTitle))
        );
    }

    #[Test]
    public function no_form_element_uses_the_forbidden_bound_title_syntax(): void
    {
        $tags = self::extractFormElementTags(self::readBlade());

        $boundTitle = array_values(array_filter(
            $tags,
            static fn (string $tag): bool => preg_match('/:title\s*=/i', $tag) === 1
        ));

        $this->assertSame(
            [],
            $boundTitle,
            "Elementos usando :title= (proibido em tag HTML pura — ver BladeBoundAttributeOnPlainTagTest):\n".
            implode("\n---\n", array_map(static fn (string $t) => substr($t, 0, 200), $boundTitle))
        );
    }

    #[Test]
    public function every_title_attribute_is_server_compiled_via_double_braces(): void
    {
        $tags = self::extractFormElementTags(self::readBlade());

        $badTitles = [];

        foreach ($tags as $tag) {
            if (preg_match('/\btitle="([^"]*)"/i', $tag, $m) !== 1) {
                continue;
            }

            if (! str_contains($m[1], '{{') || ! str_contains($m[1], '}}')) {
                $badTitles[] = $tag;
            }
        }

        $this->assertSame(
            [],
            $badTitles,
            "Elementos com title= que nao usa a forma {{ __(...) }}:\n".
            implode("\n---\n", array_map(static fn (string $t) => substr($t, 0, 200), $badTitles))
        );
    }
}

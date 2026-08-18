<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The dashboard layout carries a multi-line Alpine component in a plain
 * `x-data="{ ... }"` attribute. That attribute is delimited by double quotes, so
 * the FIRST double quote inside it closes the attribute and the browser renders
 * the remainder of the script as visible text — the whole Alpine object dumped
 * across the page, with the app dead underneath.
 *
 * This happened, with three separate causes in one edit: the word "mode" inside a
 * JS comment, a `meta[name="csrf-token"]` selector, and an example preset selector
 * `[data-ptah-dark="..."]` in another comment. The suite was green throughout —
 * nothing renders this layout and asserts on the markup, and PHP/CSS linters have
 * no opinion about quoting inside an HTML attribute value.
 *
 * So the invariant is pinned directly: no double quote may appear inside any
 * inline Alpine attribute in these views. Use single quotes in code, and put prose
 * in a Blade comment outside the element.
 */
class LayoutXDataQuotingTest extends TestCase
{
    /** Attributes whose value is JS/expression and is written with double-quote delimiters. */
    private const ALPINE_ATTRIBUTES = ['x-data', 'x-init', 'x-effect'];

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

            // Only views that actually carry one of these attributes — otherwise most
            // of the suite reports as risky for performing no assertions, which buries
            // the signal this test exists to give.
            $carries = false;

            foreach (self::ALPINE_ATTRIBUTES as $attribute) {
                if (str_contains($source, $attribute.'="')) {
                    $carries = true;
                    break;
                }
            }

            if (! $carries) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = [$file->getPathname()];
        }

        if ($files === []) {
            throw new RuntimeException('LayoutXDataQuotingTest: nenhuma view Blade encontrada.');
        }

        ksort($files);

        return $files;
    }

    #[Test]
    #[DataProvider('bladeViewProvider')]
    public function inline_alpine_attributes_contain_no_double_quotes(string $path): void
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException('LayoutXDataQuotingTest: falha ao ler '.$path);
        }

        foreach (self::ALPINE_ATTRIBUTES as $attribute) {
            foreach (self::extractDoubleQuotedValues($source, $attribute) as $offset => $value) {
                $this->assertStringNotContainsString(
                    '"',
                    $value,
                    sprintf(
                        "%s: o atributo %s=\"...\" (offset %d) contem uma aspa dupla.\n".
                        "A primeira aspa interna FECHA o atributo e o browser despeja o resto do script\n".
                        "como texto visivel na pagina — a aplicacao morre sem erro no PHP e sem falha na suite.\n".
                        "Use aspas simples no codigo e mova prosa para um comentario Blade fora do elemento.\n".
                        'Trecho: %s',
                        basename($path),
                        $attribute,
                        $offset,
                        mb_substr($value, 0, 160)
                    )
                );
            }
        }
    }

    /**
     * Returns every `attr="..."` value in $source, keyed by byte offset. Walks the
     * raw text rather than parsing HTML: the point is to see the attribute exactly
     * as the browser's tokenizer will, terminating on the first double quote.
     *
     * @return array<int, string>
     */
    private static function extractDoubleQuotedValues(string $source, string $attribute): array
    {
        $values = [];
        $needle = $attribute.'="';
        $offset = 0;

        while (($pos = strpos($source, $needle, $offset)) !== false) {
            $start = $pos + strlen($needle);
            // Where the browser would end the attribute: the next double quote.
            $end = strpos($source, '"', $start);

            if ($end === false) {
                $values[$pos] = substr($source, $start);
                break;
            }

            // Everything up to the closing brace of the intended value, so the test
            // sees the author's INTENT and can report the stray quote inside it.
            // The intended end is the matching `}` of the opening `{`, when present.
            $intendedEnd = self::intendedEnd($source, $start);
            $values[$pos] = substr($source, $start, max($end, $intendedEnd) - $start);
            $offset = $end + 1;
        }

        return $values;
    }

    /** Finds the position just past the brace-balanced expression starting at $start, or $start. */
    private static function intendedEnd(string $source, int $start): int
    {
        if (($source[$start] ?? '') !== '{') {
            return $start;
        }

        $depth = 0;
        $len = strlen($source);

        for ($i = $start; $i < $len; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }

        return $start;
    }
}

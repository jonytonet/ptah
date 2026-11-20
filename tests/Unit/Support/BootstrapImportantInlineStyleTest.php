<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Bootstrap mode (docs/Css.md): ptah.css neutralises Bootstrap's !important
 * utilities (`border`, `rounded`, `p-4`, `bg-white`…) on ptah markup with
 * `prop: revert-layer !important`. An !important declaration beats a `style`
 * attribute — so an element that carries such a class AND sets the same
 * property inline loses its inline value in Bootstrap mode only (the widget
 * border colour, the 3px row-style stripe of a card). In a Tailwind host the
 * inline style wins.
 *
 * The rule for package views: the value goes through a utility class (with a
 * custom property for what is dynamic: `border-(--x)` + `style="--x: …"`),
 * never through `style` next to such a class. The list of classes is read from
 * resources/dist/ptah.css itself, so a new Bootstrap family is covered with no
 * edit here.
 */
class BootstrapImportantInlineStyleTest extends TestCase
{
    private const LONGHANDS = [
        'border' => ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style', 'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'],
        'border-width' => ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width'],
        'border-style' => ['border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style'],
        'border-color' => ['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'],
        'border-top' => ['border-top-width', 'border-top-style', 'border-top-color'],
        'border-right' => ['border-right-width', 'border-right-style', 'border-right-color'],
        'border-bottom' => ['border-bottom-width', 'border-bottom-style', 'border-bottom-color'],
        'border-left' => ['border-left-width', 'border-left-style', 'border-left-color'],
        'border-radius' => ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius'],
        'margin' => ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'],
        'padding' => ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
        'background' => ['background-color', 'background-image'],
        'gap' => ['row-gap', 'column-gap'],
    ];

    /** @return list<string> */
    private static function longhands(string $prop): array
    {
        return self::LONGHANDS[$prop] ?? [$prop];
    }

    /**
     * Class name => properties ptah.css reverts with !important.
     *
     * @return array<string, list<string>>
     */
    private static function importantClasses(): array
    {
        $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/dist/ptah.css');
        $map = [];

        preg_match_all('/^  ((?:[^{}\n])+?) \{\n((?:    [^\n]*\n)+?)  \}/m', $css, $rules, PREG_SET_ORDER);
        foreach ($rules as [, $selector, $body]) {
            if (! str_contains($body, 'revert-layer !important')) {
                continue;
            }
            preg_match_all('/^    ([a-z-]+): revert-layer !important;/m', $body, $props);
            foreach (preg_split('/,(?![^(]*\))/', $selector) ?: [] as $one) {
                if (preg_match('/^\s*\.((?:\\\\.|[\w-])+):where\(:scope,:scope \*\)\s*$/', $one, $m) === 1) {
                    $class = (string) preg_replace('/\\\\(.)/', '$1', $m[1]);
                    $map[$class] = array_values(array_unique(array_merge($map[$class] ?? [], $props[1])));
                }
            }
        }

        return $map;
    }

    /** @return list<string> the opening tags of a Blade file */
    private static function tags(string $blade): array
    {
        $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
        $tags = [];
        $len = strlen($blade);

        for ($i = 0; $i < $len; $i++) {
            if ($blade[$i] !== '<' || (! ctype_alpha($blade[$i + 1] ?? '') && substr($blade, $i + 1, 2) !== '{{')) {
                continue;
            }
            $quote = false;
            $braces = 0;
            for ($j = $i + 1; $j < $len; $j++) {
                if (substr($blade, $j, 2) === '{{') {
                    $braces++;
                    $j++;
                } elseif (substr($blade, $j, 2) === '}}' && $braces > 0) {
                    $braces--;
                    $j++;
                } elseif ($blade[$j] === '"' && $braces === 0) {
                    $quote = ! $quote;
                } elseif ($blade[$j] === '>' && ! $quote && $braces === 0) {
                    break;
                }
            }
            $tags[] = substr($blade, $i, $j - $i + 1);
            $i = $j;
        }

        return $tags;
    }

    #[Test]
    public function no_package_view_sets_inline_a_property_a_bootstrap_important_class_of_the_same_element_reverts(): void
    {
        $important = self::importantClasses();
        $this->assertArrayHasKey('border', $important, 'The reverted-class list could not be read from ptah.css.');
        $this->assertArrayHasKey('p-4', $important);

        $root = dirname(__DIR__, 3).'/resources/views';
        $offenders = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (self::tags((string) file_get_contents($file->getPathname())) as $tag) {
                if (preg_match('/(?<![:\w.-])class="([^"]*)"/', $tag, $class) !== 1) {
                    continue;
                }
                // Alpine's :style sets the same inline style at runtime (the
                // sidebar's width): its keys count as properties.
                if (preg_match('/(?<![\w.-])(?::|x-bind:)style="([^"]*)"/', $tag, $bound) === 1) {
                    preg_match_all('/([a-zA-Z-]+)\s*:/', $bound[1], $keys);
                    $style = [1 => implode(';', array_map(fn (string $k): string => strtolower((string) preg_replace('/[A-Z]/', '-$0', $k)).':x', $keys[1]))];
                } elseif (preg_match('/(?<![:\w.-])style="([^"]*)"/', $tag, $style) !== 1) {
                    continue;
                }
                $classes = preg_split('/\s+/', (string) preg_replace('/\{\{.*?\}\}|@\w+(\([^)]*\))?/s', ' ', $class[1])) ?: [];
                // A style the SCREEN'S AUTHOR wrote (a conditional row style,
                // its preview in the config editor) can set anything: such an
                // element carries no reverted class at all (use the
                // arbitrary-value form, `p-[calc(var(--spacing)*4)]`).
                $opaque = preg_match('/\$rowStyle|\[\'style\'\]/', $style[1]) === 1;
                preg_match_all('/(?:^|;)\s*([a-z-]+)\s*:/', (string) preg_replace('/\{\{.*?\}\}/s', 'x', $style[1]), $props);
                $inline = array_merge(...array_map(self::longhands(...), $props[1] ?: ['-']));

                foreach ($classes as $name) {
                    foreach ($important[$name] ?? [] as $reverted) {
                        if ($opaque || array_intersect(self::longhands($reverted), $inline) !== []) {
                            $offenders[] = str_replace($root, 'resources/views', $file->getPathname())." — class {$name} vs style \"{$style[1]}\"";
                            break 2;
                        }
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'Inline style that Bootstrap mode overrides — move it to a utility (+ custom property).');
    }
}

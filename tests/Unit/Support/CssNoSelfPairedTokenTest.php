<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards against a cheap, invisible-to-per-pair-contrast-tests mistake: a
 * `.ptah-c-*` rule declaring `color` and `background-color` with the EXACT
 * SAME `var(--ptah-*)` token — painting an element's ink and its own fill
 * with the same value, which is either 1.00:1 (an element with no border/
 * shadow becomes invisible) or, worse, silently fine ONLY because the
 * element in question never actually renders text on that fill (in which
 * case the `color` declaration is simply dead weight nobody will notice
 * until it IS reused on a text node).
 *
 * Found for real in `.ptah-c-guide_conn` (permission-guide's diagram
 * connector): `color`/`background-color` both `var(--ptah-line-field)`,
 * because the class served two different jobs (a filled line <div> AND an
 * arrow-glyph text node) with one declaration each — the glyph's ink and its
 * own background were therefore identical, 1.00:1 in all 6 tone presets.
 * `AppearancePresetContrastTest`'s pair helpers could not have caught this:
 * they prove a token against ANOTHER selector/token (or an ambient
 * background), never a rule against ITS OWN color/background-color pair.
 *
 * Pure text/regex, no app boot — same idiom as `HardcodedPaletteCeilingTest`.
 */
class CssNoSelfPairedTokenTest extends TestCase
{
    #[Test]
    public function no_ptah_c_rule_declares_color_and_background_color_with_the_same_token(): void
    {
        $css = self::css();
        $offenders = [];

        foreach (self::rules($css) as $selector => $body) {
            if (! str_contains($selector, '.ptah-c-')) {
                continue;
            }

            // Negative lookbehind excludes `background-color`/`border-color` from
            // matching a bare `color:` — those are different properties whose
            // names merely contain the substring "color".
            if (! preg_match('/(?<!background-)(?<!border-)(?<!outline-)\bcolor:\s*(var\([^)]+\))\s*;/', $body, $fg)) {
                continue;
            }

            if (! preg_match('/\bbackground-color:\s*(var\([^)]+\))\s*;/', $body, $bg)) {
                continue;
            }

            if (trim($fg[1]) === trim($bg[1])) {
                $offenders[] = sprintf('%s: color e background-color usam o MESMO token %s', $selector, trim($fg[1]));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Regra(s) .ptah-c-* com color/background-color no MESMO token var(--ptah-*):\n".
            implode("\n", $offenders).
            "\nSe o elemento renderiza texto, isso e 1.00:1 (tinta invisivel sobre o proprio fundo) — ".
            'divida a regra em duas classes ou remova a declaracao que nao se aplica.'
        );
    }

    /**
     * @return array<string, string> selector (normalised) => declaration body
     *
     * If the same selector text appears more than once in the file, the LAST
     * occurrence wins — matching normal CSS cascade behaviour for two rules
     * of equal specificity, so this checks the declaration that actually
     * takes effect, not an earlier one a later rule already overrides.
     */
    private static function rules(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        $rules = [];

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER)) {
            throw new RuntimeException('CssNoSelfPairedTokenTest: nenhuma regra encontrada em ptah-components.css.');
        }

        foreach ($matches as $rule) {
            $selector = trim(preg_replace('/\s+/', ' ', $rule[1]) ?? $rule[1]);

            if ($selector === '') {
                continue;
            }

            $rules[$selector] = $rule[2];
        }

        return $rules;
    }

    private static function css(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('CssNoSelfPairedTokenTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}

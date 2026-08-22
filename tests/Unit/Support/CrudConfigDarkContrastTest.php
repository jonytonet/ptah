<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Numeric proof for the "CRUD Config editor — dark contrast follow-up
 * (Onda 5)" block in resources/css/ptah-components.css. CrudConfigThemeParityTest
 * already proves each selector below carries a token-driven rule in the right
 * scope(s); this file proves the resolved ink/background PAIR those rules
 * actually produce clears WCAG AA — same self-contained relative-luminance
 * math as AlertContrastTest / ModuleScreenSemanticChipContrastTest (kept
 * duplicated on purpose: pure math + literals read straight from this file
 * and forge.css, no app boot needed).
 *
 * Every "before" value documented below was measured against the CURRENT
 * (pre-fix) markup/CSS combination, i.e. what a user actually saw in
 * .ptah-dark before this pass — not a hypothetical.
 */
class CrudConfigDarkContrastTest extends TestCase
{
    private const AA_TEXT = 4.5;

    private const AA_UI = 3.0;

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function relativeLuminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function contrastRatio(string $hex1, string $hex2): float
    {
        $l1 = self::relativeLuminance(self::hexToRgb($hex1));
        $l2 = self::relativeLuminance(self::hexToRgb($hex2));
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** Replicates `color-mix(in srgb, $hex $pct%, #ffffff)` (the "-lite" idiom). */
    private static function mixWithWhite(string $hex, float $pct): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r * $pct + 255 * (1 - $pct)),
            (int) round($g * $pct + 255 * (1 - $pct)),
            (int) round($b * $pct + 255 * (1 - $pct))
        );
    }

    private static function css(): string
    {
        static $css = null;

        return $css ??= file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
    }

    private static function forgeCss(): string
    {
        static $forge = null;

        return $forge ??= file_get_contents(dirname(__DIR__, 3).'/resources/css/forge.css');
    }

    private static function forgeToken(string $name): string
    {
        if (! preg_match('/--'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})/', self::forgeCss(), $m)) {
            throw new RuntimeException("CrudConfigDarkContrastTest: could not read --{$name} from forge.css.");
        }

        return strtolower($m[1]);
    }

    private static function darkToken(string $name): string
    {
        if (! preg_match('/\.ptah-dark\s*\{([^}]*)\}/s', self::css(), $block)) {
            throw new RuntimeException('CrudConfigDarkContrastTest: could not locate the bare .ptah-dark token block.');
        }

        if (! preg_match('/--'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})/', $block[1], $m)) {
            throw new RuntimeException("CrudConfigDarkContrastTest: could not read --{$name} from the .ptah-dark block.");
        }

        return strtolower($m[1]);
    }

    /**
     * 1) hover:bg-slate-50/100 + the already-tokenized (light-in-dark) ink —
     * BEFORE used the literal Tailwind hover background (unaffected by
     * .ptah-dark); AFTER routes through --ptah-surface-hover/--ptah-control-ghost.
     * 2) hover:text-slate-500/600 — BEFORE used the literal Tailwind ink on
     * the (already dark) ambient ptah-cfg-content surface; AFTER routes
     * through --ptah-text-muted/--ptah-text-secondary.
     *
     * @return array<string, array{0: string, 1: string, 2: float, 3: string}>
     */
    public static function hoverPairProvider(): array
    {
        $textDark = self::darkToken('ptah-text'); // == --ptah-text-secondary dark, same value today
        $surfaceHoverDark = self::darkToken('ptah-surface-hover');
        $controlGhostDark = self::darkToken('ptah-control-ghost');
        $mutedDark = self::darkToken('ptah-text-muted');
        $secondaryDark = self::darkToken('ptah-text-secondary');
        $surfaceDark = self::darkToken('ptah-surface');

        return [
            'hover:bg-slate-50 (AFTER: surface-hover) + text-slate-700 ink' => [$textDark, $surfaceHoverDark, self::AA_TEXT, 'BEFORE (literal #f8fafc bg): 1.42:1'],
            'hover:bg-slate-100 (AFTER: control-ghost) + text-slate-600 ink' => [$textDark, $controlGhostDark, self::AA_TEXT, 'BEFORE (literal #f1f5f9 bg): 1.36:1'],
            'hover:text-slate-500 (AFTER: text-muted) on ambient surface' => [$mutedDark, $surfaceDark, self::AA_UI, 'BEFORE (literal #64748b ink): 3.07:1'],
            'hover:text-slate-600 (AFTER: text-secondary) on ambient surface' => [$secondaryDark, $surfaceDark, self::AA_TEXT, 'BEFORE (literal #475569 ink): 1.93:1'],
        ];
    }

    #[Test]
    #[DataProvider('hoverPairProvider')]
    public function hover_pair_clears_its_wcag_floor_in_dark(string $fg, string $bg, float $minRatio, string $before): void
    {
        $ratio = self::contrastRatio($fg, $bg);

        $this->assertGreaterThanOrEqual(
            $minRatio,
            $ratio,
            sprintf('%s vs %s = %.2f:1, below the %.1f:1 floor (%s).', $fg, $bg, $ratio, $minRatio, $before)
        );
    }

    /**
     * .cfg-ink-warn: light keeps the literal text-amber-700 (#b45309, already
     * fine on a white ambient); dark routes to --ptah-warn-lite. BEFORE
     * (bare text-amber-700 on the tokenized dark ambient surface): 2.91:1.
     */
    #[Test]
    public function cfg_ink_warn_clears_aa_text_in_both_scopes(): void
    {
        $lightInk = '#b45309';
        $lightRatio = self::contrastRatio($lightInk, '#ffffff');
        $this->assertGreaterThanOrEqual(self::AA_TEXT, $lightRatio, sprintf('Light .cfg-ink-warn only reaches %.2f:1.', $lightRatio));

        $warn = self::forgeToken('color-warn');
        $warnLite = self::mixWithWhite($warn, 0.55);
        $surfaceDark = self::darkToken('ptah-surface');
        $darkRatio = self::contrastRatio($warnLite, $surfaceDark);

        $this->assertGreaterThanOrEqual(
            self::AA_TEXT,
            $darkRatio,
            sprintf('Dark .cfg-ink-warn (%s on %s) only reaches %.2f:1 (BEFORE, bare text-amber-700: 2.91:1).', $warnLite, $surfaceDark, $darkRatio)
        );
    }

    /**
     * The two literal-tinted callout boxes (bg-sky-50, bg-indigo-50) keep the
     * SAME background in both themes — the invariant "-on-tint" tokens must
     * resolve to values that still clear AA against that literal background,
     * unlike the file-wide dark override they replace (BEFORE: 1.39-2.41:1,
     * see ptah-components.css's own comment for the three measurements).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function calloutPairProvider(): array
    {
        return [
            'sky-50 callout, secondary-on-tint ink' => ['#f0f9ff', 'ptah-text-secondary-on-tint'],
            'indigo-50 callout, secondary-on-tint ink' => ['#eef2ff', 'ptah-text-secondary-on-tint'],
        ];
    }

    #[Test]
    #[DataProvider('calloutPairProvider')]
    public function callout_on_tint_ink_clears_aa_text_against_its_literal_background(string $bg, string $tokenName): void
    {
        if (! preg_match('/--'.preg_quote($tokenName, '/').':\s*(#[0-9a-fA-F]{6})/', self::css(), $m)) {
            throw new RuntimeException("CrudConfigDarkContrastTest: could not read --{$tokenName} from ptah-components.css.");
        }

        $ink = strtolower($m[1]);
        $ratio = self::contrastRatio($ink, $bg);

        $this->assertGreaterThanOrEqual(
            self::AA_TEXT,
            $ratio,
            sprintf('%s vs %s = %.2f:1, below the 4.5:1 floor.', $ink, $bg, $ratio)
        );
    }
}

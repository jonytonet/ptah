<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Onda A UX-ACL, FIX 1 — contrast proof for the semantic status/audit/badge
 * chips added to `.ptah-module-table` in resources/css/ptah-components.css
 * (status ativo/inativo, MASTER/DEFAULT, audit action, menu "Grupo" badge).
 *
 * ModuleScreenThemeParityTest already proves each chip selector carries a
 * `var(--ptah-*)`-driven rule in BOTH scopes; this test proves the resolved
 * INK/BACKGROUND pair those rules produce actually clears WCAG AA (4.5:1 for
 * small chip text) — the thing a "the selector exists" check cannot see.
 *
 * Same self-contained relative-luminance math as ContrastGuardTest (kept
 * duplicated rather than shared: this file is pure math + literal hex pulled
 * from the default `--color-success/-danger/-warn` fallbacks and the CSS's
 * own documented mix percentages, no app boot needed).
 */
class ModuleScreenSemanticChipContrastTest extends TestCase
{
    private const AA_TEXT = 4.5;

    /** Default fallback hexes from forge.css / ptah-components.css `--color-*`. */
    private const SUCCESS = '#10b981';

    private const DANGER = '#ef4444';

    private const WARN = '#f59e0b';

    /** `.ptah-module-table`'s own tbody background: var(--ptah-canvas). */
    private const CANVAS_LIGHT = '#ffffff';

    private const CANVAS_DARK = '#0f172a';

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

    /** Replicates `color-mix(in srgb, $hex $pct%, $onto)` (both opaque hex). */
    private static function mix(string $hex, float $pct, string $onto): string
    {
        $c = self::hexToRgb($hex);
        $o = self::hexToRgb($onto);
        $mixed = [
            (int) round($c[0] * $pct + $o[0] * (1 - $pct)),
            (int) round($c[1] * $pct + $o[1] * (1 - $pct)),
            (int) round($c[2] * $pct + $o[2] * (1 - $pct)),
        ];

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    /** Replicates `color-mix(in srgb, $fgHex $pct%, black)` (--ptah-*-strong idiom). */
    private static function mixWithBlack(string $hex, float $pct): string
    {
        return self::mix($hex, $pct, '#000000');
    }

    /** Replicates `color-mix(in srgb, $fgHex $pct%, white)` (--ptah-*-lite idiom). */
    private static function mixWithWhite(string $hex, float $pct): string
    {
        return self::mix($hex, $pct, '#ffffff');
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: float, 3: string, 4: float}>
     */
    public static function semanticChipProvider(): array
    {
        return [
            // label => [color, strongPct(-> ink light), softPct(-> bg light), litePct(-> ink dark helper), softPctDark]
            'success (status ativo / audit create / audit granted)' => [self::SUCCESS, 0.60, 0.16, 0.55, 0.24],
            'danger (status inativo / audit delete / audit denied)' => [self::DANGER, 0.75, 0.14, 0.55, 0.24],
            'warn (MASTER / DEFAULT / audit update)' => [self::WARN, 0.35, 0.16, 0.55, 0.24],
        ];
    }

    #[Test]
    #[DataProvider('semanticChipProvider')]
    public function semantic_chip_ink_clears_aa_text_contrast_on_its_own_soft_background(
        string $color,
        float $strongPct,
        float $softPct,
        float $litePct,
        float $softDarkPct
    ): void {
        // Light: --ptah-*-strong ink (color-mix($color, strongPct%, black)) on
        // --ptah-*-soft bg (color-mix($color, softPct%, --ptah-surface light = #fff)).
        $strongInk = self::mixWithBlack($color, $strongPct);
        $softBgLight = self::mix($color, $softPct, self::CANVAS_LIGHT);
        $lightRatio = self::contrastRatio($strongInk, $softBgLight);

        $this->assertGreaterThanOrEqual(
            self::AA_TEXT,
            $lightRatio,
            sprintf('Light chip ink %s on bg %s only reaches %.2f:1 (need >= 4.5:1).', $strongInk, $softBgLight, $lightRatio)
        );

        // Dark: --ptah-*-lite ink (color-mix($color, litePct%, white)) on the
        // --ptah-*-soft dark override (color-mix($color, softDarkPct%, transparent),
        // composited over .ptah-module-table's own dark canvas #0f172a).
        $liteInk = self::mixWithWhite($color, $litePct);
        $softBgDark = self::mix($color, $softDarkPct, self::CANVAS_DARK);
        $darkRatio = self::contrastRatio($liteInk, $softBgDark);

        $this->assertGreaterThanOrEqual(
            self::AA_TEXT,
            $darkRatio,
            sprintf('Dark chip ink %s on bg %s only reaches %.2f:1 (need >= 4.5:1).', $liteInk, $softBgDark, $darkRatio)
        );
    }

    /**
     * menu-list "Grupo" badge — the one chip family with no success/danger/warn/
     * primary semantic to borrow (see the Fase-2-style literal exception comment
     * next to `.ptah-module-table .bg-purple-100`/`.text-purple-700` in
     * ptah-components.css). Light is byte-identical to Tailwind's own
     * purple-100/700 (unchanged, only dark was ever missing); dark is the fresh
     * literal pair introduced by this pass.
     */
    #[Test]
    public function purple_group_badge_clears_aa_text_contrast_in_both_scopes(): void
    {
        $lightRatio = self::contrastRatio('#7e22ce', '#f3e8ff');
        $this->assertGreaterThanOrEqual(self::AA_TEXT, $lightRatio, sprintf('Light purple badge only reaches %.2f:1.', $lightRatio));

        $darkBg = self::mix('#a855f7', 0.25, self::CANVAS_DARK);
        $darkRatio = self::contrastRatio('#e9d5ff', $darkBg);
        $this->assertGreaterThanOrEqual(self::AA_TEXT, $darkRatio, sprintf('Dark purple badge (bg %s) only reaches %.2f:1.', $darkBg, $darkRatio));
    }

    /**
     * role-list "text-slate-300" object-type badge inside the bind modal
     * (FIX 1/FIX 3) — the one genuinely-broken-in-BOTH-scopes literal this
     * audit found (1.48:1 on white), not just a missing dark: pair. Now reads
     * from --ptah-text-faint, same token role-list's own pagination summary
     * (.ptah-c-pag) and page-list's slug caption already use.
     */
    #[Test]
    public function bind_modal_object_type_text_no_longer_uses_the_illegible_slate_300_literal(): void
    {
        $view = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/permission/role-list.blade.php');
        $this->assertIsString($view);
        $this->assertStringNotContainsString('text-slate-300', $view, 'role-list.blade.php: text-slate-300 (1.48:1 on white) should no longer be used for the bind-modal obj_type caption.');
        $this->assertStringContainsString('ptah-c-mod_obj_type', $view);
    }

    /**
     * `.ptah-c-mod_master_row` (role-list) / `.ptah-c-mod_denied_row` (audit-list)
     * are deliberately absent from ModuleScreenThemeParityTest's
     * tokenDrivenSelectorProvider (see the NOTE there): they mix the semantic
     * color straight against `transparent`, with no var(--ptah-*) for that
     * mechanism to key on. Covered here instead — both scopes must declare a
     * background using the row's own semantic color, at a lighter mix than the
     * chip tokens above (a row tint, not another chip).
     */
    #[Test]
    public function master_and_denied_row_highlights_declare_both_scopes(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
        $this->assertIsString($css);

        foreach (['ptah-c-mod_master_row' => 'color-warn', 'ptah-c-mod_denied_row' => 'color-danger'] as $class => $expectedColorVar) {
            $this->assertMatchesRegularExpression(
                '/\.'.$class.'\s*\{[^}]*background-color:\s*color-mix\(in srgb, var\(--'.$expectedColorVar.'/',
                $css,
                sprintf('.%s: light rule missing or not derived from var(--%s).', $class, $expectedColorVar)
            );
            $this->assertMatchesRegularExpression(
                '/\.ptah-dark \.'.$class.'\s*\{[^}]*background-color:\s*color-mix\(in srgb, var\(--'.$expectedColorVar.'/',
                $css,
                sprintf('.ptah-dark .%s: dark rule missing or not derived from var(--%s).', $class, $expectedColorVar)
            );
        }
    }
}

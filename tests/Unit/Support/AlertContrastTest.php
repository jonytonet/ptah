<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Contrast guard for the forge-alert (success/warn/danger) title and body text —
 * see FIX 1 of the Onda 3 accessibility audit.
 *
 * Before this fix, forge-alert.blade.php painted title/text with the raw
 * `text-{color}[-dark]` utilities against the alert's own `bg-{color}-light`
 * background, which failed AA in every light-mode alert:
 *   success text 2.24:1 / title 3.32:1
 *   danger  text 3.08:1 / title 3.95:1
 *   warn    text 1.93:1 / title 2.86:1
 * (all below the 4.5:1 floor for text). Dark mode already passed (measured
 * below) because `dark:bg-slate-800/60` is dark enough for the existing
 * neutral Tailwind grays to clear 10:1+, so this guard pins those values too
 * instead of assuming they stayed correct.
 */
class AlertContrastTest extends TestCase
{
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

    /** Replicates CSS `color-mix(in srgb, $hex $pct%, #000000)` (direct sRGB channel mix, no gamma). */
    private static function mixWithBlack(string $hex, float $pct): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r * $pct),
            (int) round($g * $pct),
            (int) round($b * $pct)
        );
    }

    /** Flattens a translucent foreground hex (0-1 alpha) over an opaque hex background. */
    private static function compositeHex(string $fgHex, float $alpha, string $bgHex): string
    {
        $fg = self::hexToRgb($fgHex);
        $bg = self::hexToRgb($bgHex);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($fg[0] * $alpha + $bg[0] * (1 - $alpha)),
            (int) round($fg[1] * $alpha + $bg[1] * (1 - $alpha)),
            (int) round($fg[2] * $alpha + $bg[2] * (1 - $alpha))
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

    private static function alertBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/components/forge-alert.blade.php');
    }

    private static function forgeToken(string $name): string
    {
        if (! preg_match('/--'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})/', self::forgeCss(), $m)) {
            throw new RuntimeException("AlertContrastTest: could not read --{$name} from forge.css.");
        }

        return strtolower($m[1]);
    }

    private static function mixPercent(string $token): float
    {
        if (! preg_match(
            '/--'.preg_quote($token, '/').':\s*color-mix\(in srgb, var\(--color-[a-z]+[^)]*\) (\d+)%, #000000\)/',
            self::css(),
            $m
        )) {
            throw new RuntimeException("AlertContrastTest: could not read the color-mix percentage for --{$token}.");
        }

        return ((int) $m[1]) / 100;
    }

    /**
     * forge-alert.blade.php must apply the new tokenised classes to
     * success/warn/danger only — primary/dark already pass AA and are out of
     * scope for this fix (see the class docblock for the measured ratios).
     */
    #[Test]
    public function blade_applies_the_alert_title_and_text_classes_to_success_warn_and_danger_only(): void
    {
        if (! preg_match("/'success' => \[(.*?)\],/s", self::alertBlade(), $success)) {
            throw new RuntimeException('AlertContrastTest: could not locate the success color-map entry.');
        }
        if (! preg_match("/'danger'\s*=> \[(.*?)\],/s", self::alertBlade(), $danger)) {
            throw new RuntimeException('AlertContrastTest: could not locate the danger color-map entry.');
        }
        if (! preg_match("/'warn'\s*=> \[(.*?)\],/s", self::alertBlade(), $warn)) {
            throw new RuntimeException('AlertContrastTest: could not locate the warn color-map entry.');
        }
        if (! preg_match("/'primary' => \[(.*?)\],/s", self::alertBlade(), $primary)) {
            throw new RuntimeException('AlertContrastTest: could not locate the primary color-map entry.');
        }

        foreach (['success' => $success, 'danger' => $danger, 'warn' => $warn] as $label => $m) {
            $this->assertStringContainsString('ptah-c-alert_title', $m[1], "forge-alert '{$label}' title no longer uses .ptah-c-alert_title.");
            $this->assertStringContainsString('ptah-c-alert_text', $m[1], "forge-alert '{$label}' text no longer uses .ptah-c-alert_text.");
        }

        $this->assertStringNotContainsString('ptah-c-alert_title', $primary[1], 'forge-alert primary should stay on its own (already-passing) utilities.');
        $this->assertStringNotContainsString('ptah-c-alert_text', $primary[1], 'forge-alert primary should stay on its own (already-passing) utilities.');
    }

    public static function contrastPairsProvider(): array
    {
        $css = self::css();
        $successLight = self::forgeToken('color-success-light');
        $dangerLight = self::forgeToken('color-danger-light');
        $warnLight = self::forgeToken('color-warn-light');

        $successStrong = self::mixWithBlack(self::forgeToken('color-success'), self::mixPercent('ptah-success-strong'));
        $warnStrong = self::mixWithBlack(self::forgeToken('color-warn'), self::mixPercent('ptah-warn-strong'));
        $dangerStrong = self::mixWithBlack(self::forgeToken('color-danger'), self::mixPercent('ptah-danger-strong'));

        // dark:bg-slate-800/60 composited over the layout's dark canvas — same
        // idiom ContrastGuardTest uses for the search-input placeholder background.
        $darkAlertBg = self::compositeHex('#1e293b', 0.6, '#0f172a');

        if (! preg_match('/\.ptah-dark \.ptah-alert-success \.ptah-c-alert_title\s*\{\s*color:\s*(#[0-9a-fA-F]{6})/', $css, $sT)
            || ! preg_match('/\.ptah-dark \.ptah-alert-success \.ptah-c-alert_text\s*\{\s*color:\s*(#[0-9a-fA-F]{6})/', $css, $sX)
            || ! preg_match('/\.ptah-dark \.ptah-alert-danger \.ptah-c-alert_title\s*\{\s*color:\s*(#[0-9a-fA-F]{6})/', $css, $dT)
            || ! preg_match('/\.ptah-dark \.ptah-alert-danger \.ptah-c-alert_text\s*\{\s*color:\s*(#[0-9a-fA-F]{6})/', $css, $dX)
            || ! preg_match('/\.ptah-dark \.ptah-alert-warn \.ptah-c-alert_title\s*\{\s*color:\s*(#[0-9a-fA-F]{6})/', $css, $wT)
            || ! preg_match('/\.ptah-dark \.ptah-alert-warn \.ptah-c-alert_text\s*\{\s*color:\s*(#[0-9a-fA-F]{6})/', $css, $wX)
        ) {
            throw new RuntimeException('AlertContrastTest: could not read one of the .ptah-dark .ptah-alert-* declarations.');
        }

        return [
            'success title (light) vs bg-success-light' => [$successStrong, $successLight, 4.5],
            'success text  (light) vs bg-success-light' => [$successStrong, $successLight, 4.5],
            'danger  title (light) vs bg-danger-light' => [$dangerStrong, $dangerLight, 4.5],
            'danger  text  (light) vs bg-danger-light' => [$dangerStrong, $dangerLight, 4.5],
            'warn    title (light) vs bg-warn-light' => [$warnStrong, $warnLight, 4.5],
            'warn    text  (light) vs bg-warn-light' => [$warnStrong, $warnLight, 4.5],
            'success title (dark) vs composite dark alert bg' => [strtolower($sT[1]), $darkAlertBg, 4.5],
            'success text  (dark) vs composite dark alert bg' => [strtolower($sX[1]), $darkAlertBg, 4.5],
            'danger  title (dark) vs composite dark alert bg' => [strtolower($dT[1]), $darkAlertBg, 4.5],
            'danger  text  (dark) vs composite dark alert bg' => [strtolower($dX[1]), $darkAlertBg, 4.5],
            'warn    title (dark) vs composite dark alert bg' => [strtolower($wT[1]), $darkAlertBg, 4.5],
            'warn    text  (dark) vs composite dark alert bg' => [strtolower($wX[1]), $darkAlertBg, 4.5],
        ];
    }

    #[Test]
    #[DataProvider('contrastPairsProvider')]
    public function pair_passes_its_wcag_floor(string $fg, string $bg, float $minRatio): void
    {
        $ratio = self::contrastRatio($fg, $bg);

        $this->assertGreaterThanOrEqual(
            $minRatio,
            $ratio,
            sprintf('%s vs %s = %.2f:1, below the %.1f:1 floor.', $fg, $bg, $ratio, $minRatio)
        );
    }
}

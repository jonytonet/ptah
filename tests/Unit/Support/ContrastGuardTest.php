<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Regression guard for the WCAG 2.x AA contrast fixes applied to
 * resources/css/ptah-components.css and resources/views/components/forge-button.blade.php
 * (Fase 0 — round 2 of the contrast audit). Pure math + file reads, no app boot needed.
 *
 * Colors are extracted straight from the source files via regex whenever possible,
 * so a future revert of the fixed value breaks this suite instead of silently
 * shipping a failing pair again.
 */
class ContrastGuardTest extends TestCase
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

    /** WCAG 2.x contrast ratio between two sRGB hex colors (order-independent). */
    private static function contrastRatio(string $hex1, string $hex2): float
    {
        $l1 = self::relativeLuminance(self::hexToRgb($hex1));
        $l2 = self::relativeLuminance(self::hexToRgb($hex2));
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** Flattens a translucent foreground hex (0-1 alpha) over an opaque hex background. */
    private static function compositeHex(string $fgHex, float $alpha, string $bgHex): string
    {
        $fg = self::hexToRgb($fgHex);
        $bg = self::hexToRgb($bgHex);

        $mixed = [
            (int) round($fg[0] * $alpha + $bg[0] * (1 - $alpha)),
            (int) round($fg[1] * $alpha + $bg[1] * (1 - $alpha)),
            (int) round($fg[2] * $alpha + $bg[2] * (1 - $alpha)),
        ];

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    private static function css(): string
    {
        static $css = null;

        return $css ??= file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
    }

    private static function buttonBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/components/forge-button.blade.php');
    }

    private static function forgeCss(): string
    {
        static $forge = null;

        return $forge ??= file_get_contents(dirname(__DIR__, 3).'/resources/css/forge.css');
    }

    private static function baseCrudBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/base-crud.blade.php');
    }

    private static function modalFormBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/partials/_modal-form.blade.php');
    }

    /** Extracts the 'success' or 'danger' color-map block body from forge-button.blade.php. */
    private static function extractColorMapBlock(string $blade, string $color): string
    {
        if (! preg_match("/'{$color}' => \[(.*?)\n {8}\],/s", $blade, $m)) {
            throw new RuntimeException("ContrastGuardTest: could not locate the '{$color}' color map entry in forge-button.blade.php");
        }

        return $m[1];
    }

    /** Extracts the first `color: #hex` (or `background-color: #hex`) captured by $pattern, or fails loudly. */
    private static function extractHex(string $subject, string $pattern, string $where): string
    {
        if (! preg_match($pattern, $subject, $m)) {
            throw new RuntimeException("ContrastGuardTest: could not locate expected color declaration for [{$where}]. Pattern: {$pattern}");
        }

        return strtolower($m[1]);
    }

    public static function contrastPairsProvider(): array
    {
        $css = self::css();
        $forge = self::forgeCss();
        $blade = self::buttonBlade();

        // --- 1. .ptah-c-modal_sub — modal subtitle text vs modal header bg ---
        $modalSubLight = self::extractHex($css, '/\.ptah-c-modal_sub\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'modal_sub light');
        $modalSubDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-modal_sub\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'modal_sub dark');

        // --- 2. .ptah-c-search::placeholder — search input placeholder text ---
        $placeholderLight = self::extractHex($css, '/\.ptah-c-search::placeholder\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'search placeholder light');
        $placeholderDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-search::placeholder\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'search placeholder dark');
        $placeholderBgLight = self::compositeHex('#f8fafc', 0.6, '#ffffff'); // .ptah-c-search bg over white page
        $placeholderBgDark = self::compositeHex('#1e293b', 0.6, '#0f172a'); // .ptah-dark .ptah-c-search bg over dark toolbar

        // --- 3. .ptah-c-sort_idle — non-active sort arrow icon vs thead bg ---
        $sortIdleLight = self::extractHex($css, '/\.ptah-c-sort_idle\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'sort_idle light');
        $sortIdleDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-sort_idle\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'sort_idle dark');

        // --- 4. .ptah-c-fp_muted (text), .ptah-c-fp_chevron / .ptah-c-search_x (icons) ---
        $fpMutedLight = self::extractHex($css, '/\.ptah-c-fp_muted\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'fp_muted light');
        $fpMutedDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-fp_muted\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'fp_muted dark');
        $fpChevronLight = self::extractHex($css, '/\.ptah-c-fp_chevron\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'fp_chevron light');
        $searchXLight = self::extractHex($css, '/\.ptah-c-search_x\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/', 'search_x light');

        // --- 5. .ptah-c-clear_btn — icon-only clear-filters button ---
        // (?<!-) avoids matching the rule's own border-color/background-color.
        $clearBtnLight = self::extractHex($css, '/\.ptah-c-clear_btn\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'clear_btn light');

        // --- 6. .ptah-c-btn_col_on — "columns hidden" toolbar button (amber) ---
        $btnColOnLight = self::extractHex($css, '/\.ptah-c-btn_col_on\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'btn_col_on light');

        // --- 7. .ptah-c-saved_filter_del — delete saved filter button (now danger/red family) ---
        $savedDelLight = self::extractHex($css, '/\.ptah-c-saved_filter_del\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'saved_filter_del light');
        $savedDelLightBg = self::extractHex($css, '/\.ptah-c-saved_filter_del\s*\{\s*background-color:\s*(#[0-9a-fA-F]{6})/', 'saved_filter_del light bg');
        $savedDelDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-saved_filter_del\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'saved_filter_del dark');
        $savedDelDarkBg = self::compositeHex('#dc2626', 0.15, '#1e293b'); // rgba(220,38,38,.15) over dark card

        // --- 8. .ptah-c-btn_trash_on — "showing trashed" toolbar button (red) ---
        $btnTrashOnLight = self::extractHex($css, '/\.ptah-c-btn_trash_on\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'btn_trash_on light');

        // --- 9-11. forge-button solid variants (success / danger / warn) — full bg/hover/relief scales ---
        $colorDark = self::extractHex($forge, '/--color-dark:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-dark');
        $colorWarn = self::extractHex($forge, '/--color-warn:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-warn');
        $colorWarnDark = self::extractHex($forge, '/--color-warn-dark:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-warn-dark');
        $colorDangerDark = self::extractHex($forge, '/--color-danger-dark:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-danger-dark');

        $successBlock = self::extractColorMapBlock($blade, 'success');
        $successBg = self::extractHex($successBlock, "/'bg'\s*=>\s*'bg-\[(#[0-9a-fA-F]{6})\]'/", "forge-button 'success' bg");
        $successHover = self::extractHex($successBlock, "/'hover'\s*=>\s*'hover:bg-\[(#[0-9a-fA-F]{6})\]'/", "forge-button 'success' hover");
        $successRelief = self::extractHex($successBlock, "/'relief'\s*=>\s*'bg-\[(#[0-9a-fA-F]{6})\]'/", "forge-button 'success' relief");

        $dangerBlock = self::extractColorMapBlock($blade, 'danger');
        if (! str_contains($dangerBlock, "'bg'        => 'bg-danger-dark'")) {
            throw new RuntimeException("ContrastGuardTest: forge-button 'danger' bg no longer reuses 'bg-danger-dark' (AA regression).");
        }
        $dangerHover = self::extractHex($dangerBlock, "/'hover'\s*=>\s*'hover:bg-\[(#[0-9a-fA-F]{6})\]'/", "forge-button 'danger' hover");
        $dangerRelief = self::extractHex($dangerBlock, "/'relief'\s*=>\s*'bg-\[(#[0-9a-fA-F]{6})\]'/", "forge-button 'danger' relief");

        $warnBlock = self::extractColorMapBlock($blade, 'warn');
        if (! str_contains($warnBlock, "'textSolid' => 'text-dark'")) {
            throw new RuntimeException("ContrastGuardTest: forge-button 'warn' textSolid is no longer 'text-dark' (AA regression).");
        }
        if (! str_contains($warnBlock, "'relief'    => 'bg-warn-dark'")) {
            throw new RuntimeException("ContrastGuardTest: forge-button 'warn' relief no longer reuses 'bg-warn-dark' (a 3rd amber tier would drop below AA with dark text).");
        }

        // The relief variant must render its own family's text color (textSolid) instead
        // of a hardcoded white — this is what makes warn's relief readable.
        if (! preg_match('/\$variantClass = "\{\$c\[\'relief\'\]\} \{\$c\[\'textSolid\'\]\}";/', $blade)) {
            throw new RuntimeException("ContrastGuardTest: forge-button relief branch no longer uses \$c['textSolid'] for its text color (AA regression — reintroduces hardcoded white-on-amber).");
        }

        // --- 12. .ptah-c-fp_cancel_btn — text button ("Cancelar" in the save-filter form) ---
        $fpCancelLight = self::extractHex($css, '/\.ptah-c-fp_cancel_btn\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'fp_cancel_btn light');
        $fpCancelDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-fp_cancel_btn\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6})/', 'fp_cancel_btn dark');

        // --- 13. Toast notifications (base-crud.blade.php) — white/dark text on solid bg ---
        $baseCrud = self::baseCrudBlade();
        $toastSuccessBg = self::extractHex($baseCrud, "/'bg-\[(#[0-9a-fA-F]{6})\] text-white':\s*t\.color === 'success'/", 'toast success bg');
        if (! preg_match("/'bg-danger-dark text-white':\s*t\.color === 'danger'/", $baseCrud)) {
            throw new RuntimeException("ContrastGuardTest: base-crud.blade.php danger toast no longer reuses 'bg-danger-dark' (AA regression).");
        }
        if (! preg_match("/'bg-warn text-dark':\s*t\.color === 'warn'/", $baseCrud)) {
            throw new RuntimeException('ContrastGuardTest: base-crud.blade.php warn toast no longer uses bg-warn/text-dark.');
        }

        // --- 14. Bulk-delete confirm button (base-crud.blade.php) & discard-changes button (_modal-form.blade.php) ---
        if (! preg_match('/text-white bg-danger-dark hover:opacity-90/', $baseCrud)) {
            throw new RuntimeException('ContrastGuardTest: base-crud.blade.php bulk-delete confirm button no longer uses bg-danger-dark (AA regression).');
        }
        $modalForm = self::modalFormBlade();
        if (! preg_match('/text-white bg-danger-dark hover:opacity-90/', $modalForm)) {
            throw new RuntimeException('ContrastGuardTest: _modal-form.blade.php discard-changes button no longer uses bg-danger-dark (AA regression).');
        }

        return [
            '1. modal_sub (light) vs modal header bg' => [$modalSubLight, '#ffffff', 4.5],
            '1. modal_sub (dark) vs modal header dark bg' => [$modalSubDark, '#1e293b', 4.5],
            '2. search placeholder (light) vs composite search bg' => [$placeholderLight, $placeholderBgLight, 4.5],
            '2. search placeholder (dark) vs composite dark search bg' => [$placeholderDark, $placeholderBgDark, 4.5],
            '3. sort_idle (light) vs thead bg — icon' => [$sortIdleLight, '#f8fafc', 3.0],
            '3. sort_idle (dark) vs thead dark bg — icon' => [$sortIdleDark, '#1e293b', 3.0],
            '4. fp_muted (light) — text' => [$fpMutedLight, '#ffffff', 4.5],
            '4. fp_muted (dark) — text' => [$fpMutedDark, '#0f172a', 4.5],
            '4. fp_chevron (light) — icon' => [$fpChevronLight, '#ffffff', 3.0],
            '4. search_x (light) — icon' => [$searchXLight, '#ffffff', 3.0],
            '5. clear_btn (light) — icon' => [$clearBtnLight, '#ffffff', 3.0],
            '6. btn_col_on (light) vs amber-50 bg — text' => [$btnColOnLight, '#fffbeb', 4.5],
            '7. saved_filter_del (light) vs its own bg — text' => [$savedDelLight, $savedDelLightBg, 4.5],
            '7. saved_filter_del (dark) vs composite dark bg — text' => [$savedDelDark, $savedDelDarkBg, 4.5],
            '8. btn_trash_on (light) vs red-50 bg — text' => [$btnTrashOnLight, '#fef2f2', 4.5],
            '9. forge-button warn bg (rest) vs dark text' => [$colorDark, $colorWarn, 4.5],
            '9. forge-button warn hover/relief (bg-warn-dark) vs dark text' => [$colorDark, $colorWarnDark, 4.5],
            '10. forge-button success bg (rest) vs white text' => ['#ffffff', $successBg, 4.5],
            '10. forge-button success hover vs white text' => ['#ffffff', $successHover, 4.5],
            '10. forge-button success relief vs white text' => ['#ffffff', $successRelief, 4.5],
            '11. forge-button danger bg (rest, --color-danger-dark) vs white text' => ['#ffffff', $colorDangerDark, 4.5],
            '11. forge-button danger hover vs white text' => ['#ffffff', $dangerHover, 4.5],
            '11. forge-button danger relief vs white text' => ['#ffffff', $dangerRelief, 4.5],
            '12. fp_cancel_btn (light) — text' => [$fpCancelLight, '#ffffff', 4.5],
            '12. fp_cancel_btn (dark) — text' => [$fpCancelDark, '#0f172a', 4.5],
            '13. toast success vs white text' => ['#ffffff', $toastSuccessBg, 4.5],
            '13. toast danger (bg-danger-dark) vs white text' => ['#ffffff', $colorDangerDark, 4.5],
            '14. bulk-delete/discard buttons (bg-danger-dark) vs white text' => ['#ffffff', $colorDangerDark, 4.5],
        ];
    }

    /**
     * The success solid-button scale (bg -> hover -> relief) must darken
     * monotonically now that all three tiers use arbitrary hex values —
     * a lighter hover than the resting bg silently reverts to the old,
     * visually-inverted bug even if each tier still passes AA on its own.
     */
    #[Test]
    public function success_button_scale_darkens_monotonically(): void
    {
        $block = self::extractColorMapBlock(self::buttonBlade(), 'success');
        $bg = self::extractHex($block, "/'bg'\s*=>\s*'bg-\[(#[0-9a-fA-F]{6})\]'/", 'success bg');
        $hover = self::extractHex($block, "/'hover'\s*=>\s*'hover:bg-\[(#[0-9a-fA-F]{6})\]'/", 'success hover');
        $relief = self::extractHex($block, "/'relief'\s*=>\s*'bg-\[(#[0-9a-fA-F]{6})\]'/", 'success relief');

        $lumBg = self::relativeLuminance(self::hexToRgb($bg));
        $lumHover = self::relativeLuminance(self::hexToRgb($hover));
        $lumRelief = self::relativeLuminance(self::hexToRgb($relief));

        $this->assertLessThan($lumBg, $lumHover, "success hover ({$hover}) must be darker than bg ({$bg}).");
        $this->assertLessThan($lumHover, $lumRelief, "success relief ({$relief}) must be darker than hover ({$hover}).");
    }

    /**
     * Same monotonic-darkening guard for the danger scale (bg -> hover -> relief).
     */
    #[Test]
    public function danger_button_scale_darkens_monotonically(): void
    {
        $block = self::extractColorMapBlock(self::buttonBlade(), 'danger');
        $hover = self::extractHex($block, "/'hover'\s*=>\s*'hover:bg-\[(#[0-9a-fA-F]{6})\]'/", 'danger hover');
        $relief = self::extractHex($block, "/'relief'\s*=>\s*'bg-\[(#[0-9a-fA-F]{6})\]'/", 'danger relief');
        // bg is the theme token danger-dark, not an arbitrary value in this block.
        $bg = self::extractHex(self::forgeCss(), '/--color-danger-dark:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-danger-dark');

        $lumBg = self::relativeLuminance(self::hexToRgb($bg));
        $lumHover = self::relativeLuminance(self::hexToRgb($hover));
        $lumRelief = self::relativeLuminance(self::hexToRgb($relief));

        $this->assertLessThan($lumBg, $lumHover, "danger hover ({$hover}) must be darker than bg ({$bg}).");
        $this->assertLessThan($lumHover, $lumRelief, "danger relief ({$relief}) must be darker than hover ({$hover}).");
    }

    #[Test]
    #[DataProvider('contrastPairsProvider')]
    public function meets_the_minimum_wcag_aa_contrast_ratio(string $foreground, string $background, float $minimum): void
    {
        $ratio = self::contrastRatio($foreground, $background);

        $this->assertGreaterThanOrEqual(
            $minimum,
            $ratio,
            sprintf(
                'Contrast %s vs %s = %.2f:1, below the required %.1f:1.',
                $foreground,
                $background,
                $ratio,
                $minimum
            )
        );
    }
}

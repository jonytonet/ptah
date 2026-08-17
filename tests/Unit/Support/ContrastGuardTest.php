<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Tests\Support\CssTokenResolver;
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

    /**
     * Replicates CSS `color-mix(in srgb, $hex $pct%, white)`: per the CSS Color 4
     * spec, `in srgb` interpolates directly in the (non-linearized) sRGB channel
     * space — i.e. result = hex * pct + 255 * (1 - pct) per channel, no gamma
     * correction. This is exactly what ptah-components.css's --ptah-*-lite tokens
     * use for the dark-mode "lite" text/border tint.
     */
    private static function mixWithWhite(string $hex, float $pct): string
    {
        $c = self::hexToRgb($hex);
        $mixed = [
            (int) round($c[0] * $pct + 255 * (1 - $pct)),
            (int) round($c[1] * $pct + 255 * (1 - $pct)),
            (int) round($c[2] * $pct + 255 * (1 - $pct)),
        ];

        return sprintf('#%02x%02x%02x', ...$mixed);
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

    private static function ptahConfig(): string
    {
        static $config = null;

        return $config ??= file_get_contents(dirname(__DIR__, 3).'/config/ptah.php');
    }

    /**
     * The primary brand default as a host WITHOUT PTAH_COLOR_PRIMARY receives it —
     * deliberately read from config/ptah.php, not forge.css's --color-primary
     * (#1e40af), which is a design-system default never used at runtime unless a
     * host imports forge.css directly (nothing in this repo does).
     */
    private static function configPrimaryDefault(): string
    {
        if (! preg_match(
            "/'primary'\s*=>\s*env\('PTAH_COLOR_PRIMARY',\s*'(#[0-9a-fA-F]{6})'\)/",
            self::ptahConfig(),
            $m
        )) {
            throw new RuntimeException('ContrastGuardTest: could not locate the default primary color in config/ptah.php.');
        }

        return strtolower($m[1]);
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

    private static function forgeTabBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/components/forge-tab.blade.php');
    }

    private static function forgeTabsBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/components/forge-tabs.blade.php');
    }

    /** Extracts the 'success' or 'danger' color-map block body from forge-button.blade.php. */
    private static function extractColorMapBlock(string $blade, string $color): string
    {
        if (! preg_match("/'{$color}' => \[(.*?)\n {8}\],/s", $blade, $m)) {
            throw new RuntimeException("ContrastGuardTest: could not locate the '{$color}' color map entry in forge-button.blade.php");
        }

        return $m[1];
    }

    /**
     * The Fase 1 restyle tokenized most neutrals in ptah-components.css, so a
     * declaration this test cares about may now read `var(--ptah-some-token)`
     * instead of a literal hex. A single shared CssTokenResolver (built once,
     * lazily, against the real file) resolves it down to the hex it renders
     * as — in the scope implied by $where ("... dark" -> dark, else light) —
     * so every existing assertion below keeps checking the ACTUAL rendered
     * color, not the token indirection.
     */
    private static function cssTokenResolver(): CssTokenResolver
    {
        static $resolver = null;

        return $resolver ??= new CssTokenResolver(self::css());
    }

    /** Extracts the first `color: #hex` (or `background-color: #hex`) captured by $pattern, or fails loudly. */
    private static function extractHex(string $subject, string $pattern, string $where): string
    {
        if (! preg_match($pattern, $subject, $m)) {
            throw new RuntimeException("ContrastGuardTest: could not locate expected color declaration for [{$where}]. Pattern: {$pattern}");
        }

        $value = $m[1];

        if (str_starts_with($value, 'var(')) {
            $scope = str_contains(strtolower($where), 'dark') ? 'dark' : 'light';
            $value = self::cssTokenResolver()->resolve($value, $scope);
        }

        return strtolower($value);
    }

    public static function contrastPairsProvider(): array
    {
        $css = self::css();
        $forge = self::forgeCss();
        $blade = self::buttonBlade();

        // --- 1. .ptah-c-modal_sub — modal subtitle text vs modal header bg ---
        $modalSubLight = self::extractHex($css, '/\.ptah-c-modal_sub\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'modal_sub light');
        $modalSubDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-modal_sub\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'modal_sub dark');

        // --- 2. .ptah-c-search::placeholder — search input placeholder text ---
        $placeholderLight = self::extractHex($css, '/\.ptah-c-search::placeholder\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'search placeholder light');
        $placeholderDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-search::placeholder\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'search placeholder dark');
        $placeholderBgLight = self::compositeHex('#f8fafc', 0.6, '#ffffff'); // .ptah-c-search bg over white page
        $placeholderBgDark = self::compositeHex('#1e293b', 0.6, '#0f172a'); // .ptah-dark .ptah-c-search bg over dark toolbar

        // --- 3. .ptah-c-sort_idle — non-active sort arrow icon vs thead bg ---
        $sortIdleLight = self::extractHex($css, '/\.ptah-c-sort_idle\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'sort_idle light');
        $sortIdleDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-sort_idle\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'sort_idle dark');

        // --- 4. .ptah-c-fp_muted (text), .ptah-c-fp_chevron / .ptah-c-search_x (icons) ---
        $fpMutedLight = self::extractHex($css, '/\.ptah-c-fp_muted\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'fp_muted light');
        $fpMutedDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-fp_muted\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'fp_muted dark');
        $fpChevronLight = self::extractHex($css, '/\.ptah-c-fp_chevron\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'fp_chevron light');
        $searchXLight = self::extractHex($css, '/\.ptah-c-search_x\s*\{[^}]*color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'search_x light');

        // --- 5. .ptah-c-clear_btn — icon-only clear-filters button ---
        // (?<!-) avoids matching the rule's own border-color/background-color.
        $clearBtnLight = self::extractHex($css, '/\.ptah-c-clear_btn\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'clear_btn light');

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
        $fpCancelLight = self::extractHex($css, '/\.ptah-c-fp_cancel_btn\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'fp_cancel_btn light');
        $fpCancelDark = self::extractHex($css, '/\.ptah-dark \.ptah-c-fp_cancel_btn\s*\{[^}]*(?<!-)color:\s*(#[0-9a-fA-F]{6}|var\(--ptah-[a-z0-9-]+\))/', 'fp_cancel_btn dark');

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

        // --- 15. forge-tab.blade.php (slot mode) inactive tab — dark idle/hover text ---
        // Tailwind's fixed slate palette (slate-400/slate-200), not brand-overridable —
        // hardcoded here (with a presence guard) because the source references the
        // utility class name, not a raw hex, so there's nothing to regex a hex out of.
        $tabBlade = self::forgeTabBlade();
        if (! str_contains($tabBlade, 'dark:text-slate-400') || ! str_contains($tabBlade, 'dark:hover:text-slate-200')) {
            throw new RuntimeException('ContrastGuardTest: forge-tab.blade.php inactive tab lost its dark: idle/hover text classes (AA regression / re-diverges from array mode).');
        }
        $tabsBlade = self::forgeTabsBlade();
        if (! str_contains($tabsBlade, 'dark:text-slate-400') || ! str_contains($tabsBlade, 'dark:hover:text-slate-200')) {
            throw new RuntimeException('ContrastGuardTest: forge-tabs.blade.php (array mode) inactive tab lost its dark: idle/hover text classes.');
        }
        $tabIdleDark = '#94a3b8'; // Tailwind slate-400
        $tabHoverDark = '#e2e8f0'; // Tailwind slate-200

        // --- 16-17. Company switcher active tab / hover text — layout <style> dismantling (Passo 4) ---
        // The layout's forge-dashboard-layout.blade.php no longer hardcodes the switcher's
        // colors; .ptah-switcher-tab--active and its hover state now derive from
        // --ptah-primary (config/ptah.php's default, since that is what a host WITHOUT
        // PTAH_COLOR_PRIMARY renders), not the frozen navy #1e40af.
        $configPrimary = self::configPrimaryDefault();
        $switcherActiveText = '#ffffff'; // --ptah-text-on-accent, invariant across scope
        $switcherHoverTextLight = self::compositeHex($configPrimary, 0.85, '#000000'); // --ptah-primary-strong
        $switcherHoverBgLight = self::mixWithWhite($configPrimary, 0.22); // color-mix(primary 22%, --ptah-surface light)

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
            '15. forge-tab (slot) inactive idle (dark, slate-400) vs card surface' => [$tabIdleDark, '#1e293b', 4.5],
            '15. forge-tab (slot) inactive hover (dark, slate-200) vs card surface' => [$tabHoverDark, '#1e293b', 4.5],
            '16. switcher active tab (--ptah-text-on-accent) vs --ptah-primary (config default)' => [$switcherActiveText, $configPrimary, 4.5],
            '17. switcher hover text (--ptah-primary-strong) vs hover bg (22% mix, light)' => [$switcherHoverTextLight, $switcherHoverBgLight, 4.5],
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

    /**
     * forge-tab.blade.php (slot mode) and forge-tabs.blade.php (array mode) each keep
     * their own $activeClass map — the 13th failure existed because only forge-tab's
     * copy was ever fixed for dark mode. Pin them byte-for-byte equal so a future
     * edit to one can't silently re-diverge from the other.
     */
    #[Test]
    public function tab_active_classes_are_identical_between_slot_and_array_modes(): void
    {
        if (! preg_match('/\$activeClass = \[(.*?)\];/s', self::forgeTabBlade(), $slot)) {
            throw new RuntimeException('ContrastGuardTest: could not find $activeClass in forge-tab.blade.php (slot mode).');
        }
        if (! preg_match('/\$activeClass = \[(.*?)\];/s', self::forgeTabsBlade(), $array)) {
            throw new RuntimeException('ContrastGuardTest: could not find $activeClass in forge-tabs.blade.php (array mode).');
        }

        $normalize = static fn (string $s): string => preg_replace('/\s+/', ' ', trim($s));

        $this->assertSame(
            $normalize($slot[1]),
            $normalize($array[1]),
            'forge-tab.blade.php (slot mode) and forge-tabs.blade.php (array mode) must map '.
            'tab colors to the identical CSS classes, or they render differently in dark mode.'
        );
    }

    /**
     * --ptah-primary-lite is the ink on a --ptah-primary-soft-d pill in four shipped
     * components: .ptah-c-dd_item_sel (selected dropdown row), .ptah-c-btn_on (active
     * toggle), .ptah-c-active_badge and .ptah-c-saved_filter_btn. Both sides of that
     * pair are derived from --color-primary, so neither appears as a hex anywhere and
     * no amount of reading the CSS reveals the contrast — it has to be computed.
     *
     * It was failing. At the original 55% mix the pair measured 4.47:1 against the
     * config/ptah.php default primary, below the 4.5:1 floor for text. It survived the
     * Fase 0 contrast sweep because forge.css defaults to a different primary that
     * lands at 4.55:1 — the demo app passed while a stock install did not, which is
     * exactly the asymmetry this test now removes by checking BOTH defaults.
     *
     * Percentages are read from the CSS rather than hardcoded, so tuning either token
     * re-runs the measurement instead of silently invalidating it.
     */
    #[Test]
    public function primary_lite_ink_on_a_primary_soft_pill_passes_aa_for_both_default_primaries(): void
    {
        $css = self::css();

        if (! preg_match('/--ptah-primary-lite:\s*color-mix\(in srgb, var\(--ptah-primary\) (\d+)%, #ffffff\);/', $css, $inkMatch)) {
            throw new RuntimeException('ContrastGuardTest: could not find the --ptah-primary-lite color-mix() declaration.');
        }

        if (! preg_match('/--ptah-primary-soft-d:\s*color-mix\(in srgb, var\(--ptah-primary\) (\d+)%, transparent\);/', $css, $pillMatch)) {
            throw new RuntimeException('ContrastGuardTest: could not find the --ptah-primary-soft-d color-mix() declaration.');
        }

        $inkPct = ((int) $inkMatch[1]) / 100;
        $pillAlpha = ((int) $pillMatch[1]) / 100;

        // The pill is translucent, so it composites over whichever dark ground the
        // component sits on. --ptah-surface is the shallower (lighter) of the two and
        // therefore the worst case for light ink; --ptah-canvas is checked too because
        // .ptah-c-dd_item_sel renders inside a dropdown over the page ground.
        $grounds = [
            '--ptah-surface' => '#1e293b',
            '--ptah-canvas' => '#0f172a',
        ];

        // Both defaults, because they differ and the difference is what hid this bug:
        // a host that never sets PTAH_COLOR_PRIMARY renders config/ptah.php's value,
        // while forge.css's value is what the demo app and the docs show.
        $primaries = [
            'config/ptah.php default' => self::configPrimaryDefault(),
            'forge.css default' => self::extractHex(
                self::forgeCss(),
                '/--color-primary:\s*(#[0-9a-fA-F]{6})/',
                'forge.css --color-primary'
            ),
        ];

        foreach ($primaries as $origin => $primary) {
            $ink = self::mixWithWhite($primary, $inkPct);

            foreach ($grounds as $groundName => $ground) {
                $pill = self::compositeHex($primary, $pillAlpha, $ground);
                $ratio = self::contrastRatio($ink, $pill);

                $this->assertGreaterThanOrEqual(
                    4.5,
                    $ratio,
                    sprintf(
                        'primary-lite ink on a primary-soft-d pill (%s, primary %s over %s): '.
                        'color-mix(%s %d%%, white) = %s on %d%% of %s over %s = %s -> %.2f:1, below 4.5:1. '.
                        'Affects .ptah-c-dd_item_sel, .ptah-c-btn_on, .ptah-c-active_badge and '.
                        '.ptah-c-saved_filter_btn. Raise the white share in --ptah-primary-lite '.
                        '(contrast is monotonic in that direction) rather than patching call sites.',
                        $origin,
                        $primary,
                        $groundName,
                        $primary,
                        $inkMatch[1],
                        $ink,
                        $pillMatch[1],
                        $primary,
                        $ground,
                        $pill,
                        $ratio
                    )
                );
            }
        }
    }

    /**
     * The active-tab dark-mode tint (--ptah-{color}-lite) is a derived value, not a
     * literal hex in the CSS — recompute the color-mix() formula in PHP against the
     * real theme defaults (forge.css) and assert on the *resulting* color, so a
     * revert to the raw --color-{success,danger,warn} (or --ptah-primary) token, or
     * a change in the mix percentage, breaks this test.
     */
    #[Test]
    public function tab_active_dark_tint_follows_the_color_mix_formula_and_passes_aa(): void
    {
        $css = self::css();
        $forge = self::forgeCss();
        $surface = '#1e293b'; // dark "card" surface used across .ptah-dark rules (modal_card, sticky_cell, tab surface)

        $colors = [
            'primary' => [
                'base' => self::extractHex($forge, '/--color-primary:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-primary'),
                'mixPattern' => '/--ptah-primary-lite:\s*color-mix\(in srgb, var\(--ptah-primary\) (\d+)%, #ffffff\);/',
                'liteVar' => '--ptah-primary-lite',
                'lightLine' => '.ptah-c-tab_active_primary            { color: var(--ptah-primary); border-color: var(--ptah-primary); }',
                'darkLine' => '.ptah-dark .ptah-c-tab_active_primary { color: var(--ptah-primary-lite); border-color: var(--ptah-primary-lite); }',
            ],
            'success' => [
                'base' => self::extractHex($forge, '/--color-success:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-success'),
                'mixPattern' => '/--ptah-success-lite:\s*color-mix\(in srgb, var\(--color-success, #[0-9a-fA-F]{6}\) (\d+)%, #ffffff\);/',
                'liteVar' => '--ptah-success-lite',
                'lightLine' => '.ptah-c-tab_active_success            { color: var(--color-success); border-color: var(--color-success); }',
                'darkLine' => '.ptah-dark .ptah-c-tab_active_success { color: var(--ptah-success-lite); border-color: var(--ptah-success-lite); }',
            ],
            'danger' => [
                'base' => self::extractHex($forge, '/--color-danger:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-danger'),
                'mixPattern' => '/--ptah-danger-lite:\s*color-mix\(in srgb, var\(--color-danger, #[0-9a-fA-F]{6}\) (\d+)%, #ffffff\);/',
                'liteVar' => '--ptah-danger-lite',
                'lightLine' => '.ptah-c-tab_active_danger            { color: var(--color-danger); border-color: var(--color-danger); }',
                'darkLine' => '.ptah-dark .ptah-c-tab_active_danger { color: var(--ptah-danger-lite); border-color: var(--ptah-danger-lite); }',
            ],
            'warn' => [
                'base' => self::extractHex($forge, '/--color-warn:\s*(#[0-9a-fA-F]{6})/', 'forge.css --color-warn'),
                'mixPattern' => '/--ptah-warn-lite:\s*color-mix\(in srgb, var\(--color-warn, #[0-9a-fA-F]{6}\) (\d+)%, #ffffff\);/',
                'liteVar' => '--ptah-warn-lite',
                'lightLine' => '.ptah-c-tab_active_warn            { color: var(--color-warn); border-color: var(--color-warn); }',
                'darkLine' => '.ptah-dark .ptah-c-tab_active_warn { color: var(--ptah-warn-lite); border-color: var(--ptah-warn-lite); }',
            ],
        ];

        foreach ($colors as $color => $cfg) {
            if (! str_contains($css, $cfg['lightLine'])) {
                throw new RuntimeException("ContrastGuardTest: .ptah-c-tab_active_{$color} no longer sets color+border-color from the raw {$color} token in light mode.");
            }
            if (! str_contains($css, $cfg['darkLine'])) {
                throw new RuntimeException("ContrastGuardTest: .ptah-dark .ptah-c-tab_active_{$color} no longer uses {$cfg['liteVar']} for both color and border-color (AA regression).");
            }
            if (! preg_match($cfg['mixPattern'], $css, $m)) {
                throw new RuntimeException("ContrastGuardTest: could not find the {$cfg['liteVar']} color-mix() declaration in ptah-components.css.");
            }

            $pct = ((int) $m[1]) / 100;
            $lite = self::mixWithWhite($cfg['base'], $pct);
            $ratio = self::contrastRatio($lite, $surface);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf(
                    'tab active (%s) dark tint: color-mix(%s %d%%, white) = %s vs surface %s = %.2f:1, below 4.5:1 (text).',
                    $color,
                    $cfg['base'],
                    $m[1],
                    $lite,
                    $surface,
                    $ratio
                )
            );
            $this->assertGreaterThanOrEqual(
                3.0,
                $ratio,
                sprintf(
                    'tab active (%s) dark tint %s vs surface %s = %.2f:1, below 3.0:1 (bottom-border indicator).',
                    $color,
                    $lite,
                    $surface,
                    $ratio
                )
            );
        }
    }

    /**
     * Guards against a silent removal of the global color-scheme declarations added to
     * ptah-components.css (Fase 1 follow-up). Without these, native controls (date
     * picker, <select> popup, scrollbars, autofill) render with the OS light scheme on
     * every screen except the BaseCrud filter panel (which has its own narrower,
     * more specific rule in _scripts.blade.php and is unaffected either way).
     */
    #[Test]
    public function root_and_ptah_dark_declare_the_matching_color_scheme(): void
    {
        $css = self::css();

        if (! preg_match('/:root\s*\{([^}]*)\}/s', $css, $rootBlock)) {
            throw new RuntimeException('ContrastGuardTest: could not locate the :root block in ptah-components.css.');
        }
        if (! preg_match('/\.ptah-dark\s*\{([^}]*)\}/s', $css, $darkBlock)) {
            throw new RuntimeException('ContrastGuardTest: could not locate the .ptah-dark block in ptah-components.css.');
        }

        $this->assertMatchesRegularExpression(
            '/color-scheme:\s*light\s*;/',
            $rootBlock[1],
            'ContrastGuardTest: :root no longer declares "color-scheme: light;" — native controls '.
            '(date picker, select, scrollbars, autofill) will render with the wrong OS scheme.'
        );
        $this->assertMatchesRegularExpression(
            '/color-scheme:\s*dark\s*;/',
            $darkBlock[1],
            'ContrastGuardTest: .ptah-dark no longer declares "color-scheme: dark;" — native controls '.
            'will keep the light OS scheme on every screen when dark mode is active.'
        );
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

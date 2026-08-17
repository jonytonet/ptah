<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Whitelist + defaults for the appearance presets exposed in the "Aparência"
 * tab of /profile (four independent axes: light tone, dark tone, accent
 * color, text color). Single source of truth shared by:
 *   - Ptah\Livewire\Auth\ProfilePage — validates input before persisting to
 *     UserPreference (key "theme", group "appearance").
 *   - resources/views/components/forge-dashboard-layout.blade.php — renders
 *     the 4 `data-ptah-*` attributes on `<html>` from the user's saved
 *     preference (server-side, so there is no flash for authenticated users).
 *   - tests/Unit/Support/AppearancePresetContrastTest.php — proves every
 *     whitelisted value has a matching CSS block in
 *     resources/css/ptah-components.css and vice-versa.
 *
 * Never write an un-whitelisted value into a `data-ptah-*` attribute: a value
 * with no matching CSS block leaves every `var(--ptah-*)` that depends on it
 * invalid at computed-value time — the UI is not degraded, it is destroyed.
 */
final class AppearancePresets
{
    /** @var list<string> */
    public const LIGHT = ['puro', 'papel', 'nevoa'];

    /** @var list<string> */
    public const DARK = ['carvao', 'grafite', 'meianoite'];

    /** @var list<string> */
    public const ACCENT = ['azul', 'violeta', 'ciano', 'verde', 'teal', 'ambar', 'vermelho', 'rosa', 'cinza'];

    /** @var list<string> */
    public const TEXT = ['suave', 'neutra', 'forte'];

    /** @var list<string> */
    public const MODE = ['light', 'dark'];

    public const DEFAULT_LIGHT = 'puro';

    public const DEFAULT_DARK = 'grafite';

    public const DEFAULT_ACCENT = 'azul';

    public const DEFAULT_TEXT = 'neutra';

    /**
     * Display swatch for each accent option (matches `--color-primary` in the
     * `html[data-ptah-accent="..."]` CSS blocks 1:1 — see
     * AppearancePresetContrastTest::every_accent_hex_matches_its_css_block).
     * Used ONLY to paint the little color dot next to each option label in
     * the profile UI; the actual theming always flows through --color-primary
     * / --ptah-primary, never this constant directly.
     *
     * @var array<string, string>
     */
    public const ACCENT_HEX = [
        'azul' => '#1d4ed8',
        'violeta' => '#6d28d9',
        'ciano' => '#0e7490',
        'verde' => '#047857',
        'teal' => '#0f766e',
        'ambar' => '#92400e',
        'vermelho' => '#b91c1c',
        'rosa' => '#be185d',
        'cinza' => '#475569',
    ];

    /**
     * Sanitizes a raw "theme" preference value (whatever came out of
     * UserPreference::get(), which cannot be trusted — it round-trips through
     * a `json` cast and nothing stops a stale/tampered row) against the
     * whitelist for each axis, falling back to the default whenever the
     * stored value is missing or unknown.
     *
     * `mode` has no default: unlike the other axes, an unset/invalid mode
     * means "no server opinion" (null), which is exactly what lets the
     * existing localStorage-only dark/light toggle keep working for a user
     * who never touched the Aparência tab.
     *
     * @return array{mode: string|null, light: string, dark: string, accent: string, text: string}
     */
    public static function sanitize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        return [
            'mode' => in_array($raw['mode'] ?? null, self::MODE, true) ? $raw['mode'] : null,
            'light' => in_array($raw['light'] ?? null, self::LIGHT, true) ? $raw['light'] : self::DEFAULT_LIGHT,
            'dark' => in_array($raw['dark'] ?? null, self::DARK, true) ? $raw['dark'] : self::DEFAULT_DARK,
            'accent' => in_array($raw['accent'] ?? null, self::ACCENT, true) ? $raw['accent'] : self::DEFAULT_ACCENT,
            'text' => in_array($raw['text'] ?? null, self::TEXT, true) ? $raw['text'] : self::DEFAULT_TEXT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function whitelistFor(string $axis): array
    {
        return match ($axis) {
            'light' => self::LIGHT,
            'dark' => self::DARK,
            'accent' => self::ACCENT,
            'text' => self::TEXT,
            default => [],
        };
    }
}

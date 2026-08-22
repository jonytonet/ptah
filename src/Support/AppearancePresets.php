<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Cookie;

/**
 * Whitelist + defaults for the appearance presets exposed in the "Aparência"
 * tab of /profile (six independent axes: light tone, dark tone, accent
 * color, text color, density and font size). Single source of truth shared by:
 *   - Ptah\Livewire\Auth\ProfilePage — validates input before persisting to
 *     UserPreference (key "theme", group "appearance").
 *   - resources/views/components/forge-dashboard-layout.blade.php — renders
 *     the 6 `data-ptah-*` attributes on `<html>` from the user's saved
 *     preference (server-side, so there is no flash for authenticated users),
 *     falling back to the `ptah_appearance` cookie for a visitor or a user
 *     who never saved a preference. The database is always the source of
 *     truth for an authenticated user who has one.
 *   - resources/views/layouts/forge-auth.blade.php — same 4 attributes, but
 *     the cookie is the ONLY source: there is no authenticated user yet.
 *   - tests/Unit/Support/AppearancePresetContrastTest.php — proves every
 *     whitelisted value has a matching CSS block in
 *     resources/css/ptah-components.css and vice-versa.
 *
 * Never write an un-whitelisted value into a `data-ptah-*` attribute: a value
 * with no matching CSS block leaves every `var(--ptah-*)` that depends on it
 * invalid at computed-value time — the UI is not degraded, it is destroyed.
 * That is exactly why decodeCookie() below never skips sanitize(): the cookie
 * is client-controlled and must be treated as untrusted input, same as any
 * other request value.
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

    /**
     * Global density axis (Onda B) — reuses the exact 3-recipe scale that
     * already existed per-screen inside BaseCrud (viewDensity: compact |
     * comfortable | spacious, see HasCrudFilters::setViewDensity()), but as a
     * whole-app choice rendered on `data-ptah-density` in <html>. The CSS
     * recipes (--ptah-control-h/px/fs/row-py) now live in :root, scoped by
     * `html[data-ptah-density="..."]` — see resources/css/ptah-components.css.
     * A screen's own toolbar dropdown still wins locally when it picks
     * "compact"/"spacious" explicitly (`.ptah-base-crud[data-density="..."]`);
     * its default "comfortable" has no local override, so it inherits this
     * global choice instead.
     *
     * @var list<string>
     */
    public const DENSITY = ['compacta', 'confortavel', 'espacosa'];

    /**
     * Global font-size axis (Onda B): scales the whole app's rem-based type
     * via `html[data-ptah-fontsize="..."] { font-size: ...% }`. "normal" has
     * no CSS rule at all (100%, i.e. the browser default) — see the same
     * "no server opinion" idiom `mode` uses, except this axis DOES have a
     * default (unlike mode).
     *
     * @var list<string>
     */
    public const FONTSIZE = ['pequena', 'normal', 'grande'];

    /** @var list<string> */
    public const MODE = ['light', 'dark'];

    public const DEFAULT_LIGHT = 'puro';

    public const DEFAULT_DARK = 'grafite';

    public const DEFAULT_ACCENT = 'azul';

    public const DEFAULT_TEXT = 'neutra';

    public const DEFAULT_DENSITY = 'confortavel';

    public const DEFAULT_FONTSIZE = 'normal';

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
     * @return array{mode: string|null, light: string, dark: string, accent: string, text: string, density: string, fontsize: string}
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
            'density' => in_array($raw['density'] ?? null, self::DENSITY, true) ? $raw['density'] : self::DEFAULT_DENSITY,
            'fontsize' => in_array($raw['fontsize'] ?? null, self::FONTSIZE, true) ? $raw['fontsize'] : self::DEFAULT_FONTSIZE,
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
            'density' => self::DENSITY,
            'fontsize' => self::FONTSIZE,
            default => [],
        };
    }

    /** Name of the cookie that mirrors the "theme" UserPreference for screens with no authenticated user. */
    public const COOKIE = 'ptah_appearance';

    /**
     * Decodes the raw `ptah_appearance` cookie value (whatever
     * `request()->cookie(self::COOKIE)` returns) into an array shape that
     * sanitize() understands, or null when the cookie is missing / not valid
     * JSON / not a JSON object.
     *
     * Deliberately does NOT sanitize: this only turns the cookie's transport
     * format (a JSON string) into PHP data. Every caller MUST still pass the
     * result through sanitize() before it reaches a `data-ptah-*` attribute —
     * the cookie is fully client-controlled (a browser dev tools edit, or a
     * shared-machine leftover) and cannot be trusted any more than the
     * `theme` UserPreference already isn't.
     */
    public static function decodeCookie(?string $raw): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Queues the `ptah_appearance` cookie (1 year, httpOnly, SameSite=Lax) with
     * an ALREADY-sanitized appearance array. httpOnly because only the server
     * ever writes this cookie — the auth screens just read it back to render
     * `data-ptah-*` (and run the anti-flash boot script) before first paint,
     * with zero client-side round trip.
     *
     * Called from the 3 places that persist "theme" to the database, so the
     * cookie never drifts from it: Ptah\Livewire\Auth\ProfilePage (the 4
     * preset axes), the `ptah.appearance.theme-mode` route (the navbar
     * toggle), and login completion (LoginPage / TwoFactorChallengePage),
     * which seeds/refreshes the cookie from whatever is already in the
     * database for a user who saved a preference long ago and will never
     * touch the Aparência tab again.
     *
     * @param  array{mode: string|null, light: string, dark: string, accent: string, text: string, density: string, fontsize: string}  $sanitized
     */
    public static function queueCookie(array $sanitized): void
    {
        Cookie::queue(
            self::COOKIE,
            (string) json_encode($sanitized),
            60 * 24 * 365,
            path: '/',
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\AppearancePresets;

/**
 * The `ptah_appearance` cookie (Ptah\Support\AppearancePresets::COOKIE) is fully
 * client-controlled: a browser dev-tools edit, a stale value from a previous
 * package version, or a shared-machine leftover. `decodeCookie()` only turns the
 * transport format (a JSON string) into PHP data — it is `sanitize()`, called on
 * every read site (forge-auth.blade.php, forge-dashboard-layout.blade.php), that
 * is the actual security boundary: anything outside the whitelist must fall back
 * to the documented defaults before it ever reaches a `data-ptah-*` attribute.
 *
 * A value that skips sanitize() is not just a robustness concern: it is a
 * potential HTML-attribute injection (a raw string like `" onload=alert(1)`
 * breaking out of the attribute), and even a merely-unknown preset name leaves
 * every `var(--ptah-*)` that depends on it invalid at computed-value time,
 * which destroys the UI instead of degrading it (see AppearancePresets' own
 * class doc comment).
 */
class AppearanceCookieSanitizeTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null}>
     */
    public static function garbageCookieProvider(): array
    {
        return [
            'missing cookie (null)' => [null],
            'empty string' => [''],
            'not JSON at all — attribute-injection payload' => ['" onload=alert(1)'],
            'not JSON at all — script tag' => ['<script>alert(1)</script>'],
            'JSON scalar, not an object' => ['"papel"'],
            'JSON array, not an object' => ['["papel","carvao"]'],
            'valid JSON object, every axis unknown' => [json_encode([
                'light' => 'javascript:alert(1)',
                'dark' => '../../etc/passwd',
                'accent' => 'nonexistent-preset',
                'text' => '<img src=x onerror=alert(1)>',
                'mode' => 'sepia',
            ])],
        ];
    }

    #[Test]
    #[DataProvider('garbageCookieProvider')]
    public function garbage_cookie_values_never_survive_past_sanitize(?string $raw): void
    {
        $sanitized = AppearancePresets::sanitize(AppearancePresets::decodeCookie($raw));

        $this->assertSame(AppearancePresets::DEFAULT_LIGHT, $sanitized['light']);
        $this->assertSame(AppearancePresets::DEFAULT_DARK, $sanitized['dark']);
        $this->assertSame(AppearancePresets::DEFAULT_ACCENT, $sanitized['accent']);
        $this->assertSame(AppearancePresets::DEFAULT_TEXT, $sanitized['text']);
        $this->assertNull($sanitized['mode']);

        // Never lets an un-whitelisted string reach the caller under any key.
        foreach ($sanitized as $value) {
            if ($value !== null) {
                $this->assertStringNotContainsString('"', $value);
                $this->assertStringNotContainsString('<', $value);
                $this->assertStringNotContainsString('alert(', $value);
            }
        }
    }

    #[Test]
    public function a_well_formed_cookie_round_trips_through_decode_and_sanitize(): void
    {
        $raw = json_encode([
            'mode' => 'dark',
            'light' => 'papel',
            'dark' => 'carvao',
            'accent' => 'ciano',
            'text' => 'forte',
        ]);

        $sanitized = AppearancePresets::sanitize(AppearancePresets::decodeCookie($raw));

        $this->assertSame([
            'mode' => 'dark',
            'light' => 'papel',
            'dark' => 'carvao',
            'accent' => 'ciano',
            'text' => 'forte',
        ], $sanitized);
    }

    #[Test]
    public function partially_valid_cookie_keeps_the_good_axes_and_defaults_the_rest(): void
    {
        $raw = json_encode([
            'light' => 'papel',
            'accent' => 'not-a-real-accent',
        ]);

        $sanitized = AppearancePresets::sanitize(AppearancePresets::decodeCookie($raw));

        $this->assertSame('papel', $sanitized['light']);
        $this->assertSame(AppearancePresets::DEFAULT_ACCENT, $sanitized['accent']);
        $this->assertSame(AppearancePresets::DEFAULT_DARK, $sanitized['dark']);
        $this->assertSame(AppearancePresets::DEFAULT_TEXT, $sanitized['text']);
    }
}

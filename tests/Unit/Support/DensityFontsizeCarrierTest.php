<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Onda B: the two new global appearance axes (densidade, tamanho de fonte)
 * follow the exact same rendering contract as the 4 pre-existing ones (tom
 * claro/escuro, accent, cor da fonte) — both `<html>` carriers
 * (resources/views/components/forge-dashboard-layout.blade.php for an
 * authenticated user, resources/views/layouts/forge-auth.blade.php for the
 * screens with none) must render `data-ptah-density` / `data-ptah-fontsize`
 * from the SAME already-sanitized `$ptahAppearance` array the other 4
 * attributes come from — never a separate, unsanitized source.
 *
 * Pure text-matching over the Blade source, same idiom as ThemeCarrierTest —
 * no app boot needed.
 */
class DensityFontsizeCarrierTest extends TestCase
{
    private static function dashboardLayoutBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(
            dirname(__DIR__, 3).'/resources/views/components/forge-dashboard-layout.blade.php'
        );
    }

    private static function authLayoutBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(
            dirname(__DIR__, 3).'/resources/views/layouts/forge-auth.blade.php'
        );
    }

    #[Test]
    public function the_dashboard_layout_renders_density_and_fontsize_from_the_sanitized_appearance_array(): void
    {
        $blade = self::dashboardLayoutBlade();

        $this->assertStringContainsString(
            'data-ptah-density="{{ $ptahAppearance[\'density\'] }}"',
            $blade,
            'forge-dashboard-layout.blade.php nao renderiza data-ptah-density a partir de $ptahAppearance '.
            '(o array ja sanitizado por AppearancePresets::sanitize()).'
        );

        $this->assertStringContainsString(
            'data-ptah-fontsize="{{ $ptahAppearance[\'fontsize\'] }}"',
            $blade,
            'forge-dashboard-layout.blade.php nao renderiza data-ptah-fontsize a partir de $ptahAppearance '.
            '(o array ja sanitizado por AppearancePresets::sanitize()).'
        );
    }

    #[Test]
    public function the_auth_layout_renders_density_and_fontsize_from_the_sanitized_appearance_array(): void
    {
        $blade = self::authLayoutBlade();

        $this->assertStringContainsString(
            'data-ptah-density="{{ $ptahAppearance[\'density\'] }}"',
            $blade,
            'forge-auth.blade.php nao renderiza data-ptah-density a partir de $ptahAppearance '.
            '(o array ja sanitizado por AppearancePresets::sanitize()).'
        );

        $this->assertStringContainsString(
            'data-ptah-fontsize="{{ $ptahAppearance[\'fontsize\'] }}"',
            $blade,
            'forge-auth.blade.php nao renderiza data-ptah-fontsize a partir de $ptahAppearance '.
            '(o array ja sanitizado por AppearancePresets::sanitize()).'
        );
    }
}

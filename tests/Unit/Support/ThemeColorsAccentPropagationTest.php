<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards resources/views/partials/theme-colors.blade.php against silently
 * breaking the "Cor de destaque" axis of the /profile Aparência tab.
 *
 * `html[data-ptah-accent="..."]` (ptah-components.css) only ever overrides
 * `--color-primary`. Everything else — `--ptah-primary` and every tint
 * derived from it (-ring/-soft/-softer/-border/-strong/-lite/-soft-d), plus
 * `--color-primary-light`/`--color-primary-dark` from THIS partial — must
 * reference `--color-primary` via var() so they follow the accent choice
 * instead of staying pinned to whatever host color was configured at page
 * load. A literal here (or in ptah-components.css's --ptah-primary
 * declaration) is invisible to the accent attribute and produces a UI that
 * is half-themed: bg-primary follows the pick, its own light/dark tints
 * (and every --ptah-primary-* derivative) do not.
 *
 * Pure text-matching over the source, no app boot needed (same idiom as
 * ContrastGuardTest / ThemeCarrierTest).
 */
class ThemeColorsAccentPropagationTest extends TestCase
{
    private static function partial(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(
            dirname(__DIR__, 3).'/resources/views/partials/theme-colors.blade.php'
        );
    }

    private static function componentsCss(): string
    {
        static $css = null;

        return $css ??= file_get_contents(
            dirname(__DIR__, 3).'/resources/css/ptah-components.css'
        );
    }

    #[Test]
    public function the_partial_no_longer_pins_ptah_primary_to_a_literal(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/--ptah-primary:\s*\{\{/',
            self::partial(),
            'theme-colors.blade.php voltou a fixar --ptah-primary em um literal do host. '.
            'ptah-components.css ja declara --ptah-primary: var(--color-primary, #5b21b6) em :root — '.
            'fixa-lo aqui impede que html[data-ptah-accent="..."] (especificidade maior) alcance os '.
            'tints derivados de --ptah-primary.'
        );
    }

    #[Test]
    public function the_light_and_dark_tints_derive_from_var_color_primary(): void
    {
        $partial = self::partial();

        $this->assertMatchesRegularExpression(
            '/--color-primary-light:\s*color-mix\(in srgb,\s*var\(--color-primary\)\s*14%,\s*#ffffff\);/',
            $partial,
            '--color-primary-light deixou de referenciar var(--color-primary) — um accent preset nao '.
            'alcancaria mais este tint.'
        );

        $this->assertMatchesRegularExpression(
            '/--color-primary-dark:\s*color-mix\(in srgb,\s*var\(--color-primary\)\s*82%,\s*#000000\);/',
            $partial,
            '--color-primary-dark deixou de referenciar var(--color-primary) — um accent preset nao '.
            'alcancaria mais este tint.'
        );
    }

    #[Test]
    public function ptah_components_css_still_derives_ptah_primary_from_color_primary(): void
    {
        $this->assertMatchesRegularExpression(
            '/--ptah-primary:\s*var\(--color-primary,\s*#[0-9a-fA-F]{6}\);/',
            self::componentsCss(),
            'ptah-components.css deixou de declarar --ptah-primary: var(--color-primary, ...) em :root — '.
            'sem isso, html[data-ptah-accent="..."] (que so sobrescreve --color-primary) nao alcanca '.
            '--ptah-primary nem nenhum dos seus tints derivados.'
        );
    }
}

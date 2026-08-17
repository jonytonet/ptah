<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards the 4 large chrome surfaces (page background, card, sidebar, navbar)
 * against regressing to the state shipped in 17061a9: only the ".ptah-c-*"
 * pill classes consumed --ptah-* tokens, so switching the light tone in
 * /profile repainted nothing but buttons — the page, sidebar, navbar and
 * card stayed hardcoded white regardless of the chosen tone.
 *
 * Two failure modes are covered:
 *
 *  1. A Blade component reintroducing a literal `bg-white` / `border-gray-100`
 *     utility for these surfaces. The LIGHT side of these 4 surfaces was never
 *     tokenized before this guard existed — that is exactly the gap it pins.
 *  2. resources/css/ptah-components.css missing a light OR dark rule, on the
 *     EXACT bare selector (light) or `.ptah-dark <selector>` (dark), that
 *     declares at least one `var(--ptah-*)` value — i.e. a rule that actually
 *     reacts to the user's chosen tone, not just any rule with that selector.
 */
class ThemeSurfaceLightDarkParityTest extends TestCase
{
    #[Test]
    public function forge_card_default_variant_has_no_hardcoded_white(): void
    {
        $blade = self::read(self::componentPath('forge-card.blade.php'));

        $this->assertMatchesRegularExpression(
            "/'default'\s*=>\s*''/",
            $blade,
            'forge-card.blade.php: a variante default deve delegar a cor a .ptah-card-default '.
            '(ptah-components.css), nao carregar bg-white/border-gray-100 fixos no $typeMap.'
        );
        $this->assertStringNotContainsString('bg-white', $blade);
    }

    #[Test]
    public function forge_sidebar_has_no_hardcoded_white(): void
    {
        $blade = self::read(self::componentPath('forge-sidebar.blade.php'));

        // Scoped to the <aside class="ptah-sidebar ..."> attribute itself, not the
        // whole file — forge-sidebar has other elements (logo wrapper, footer) whose
        // own hardcoded colors are out of scope for this guard.
        $this->assertMatchesRegularExpression('/class="ptah-sidebar\s/', $blade);
        $this->assertSame(1, preg_match('/class="ptah-sidebar([^"]*)"/', $blade, $m), 'forge-sidebar.blade.php: atributo class do <aside> nao encontrado.');
        $this->assertStringNotContainsString('bg-white', $m[1]);
        $this->assertStringNotContainsString('border-gray-100', $m[1]);
    }

    #[Test]
    public function forge_navbar_has_no_hardcoded_white(): void
    {
        $blade = self::read(self::componentPath('forge-navbar.blade.php'));

        // Scoped to the <nav>'s own 'class' => '...' merge value — forge-navbar also
        // renders dropdown menus (.ptah-admin-dropdown, .ptah-user-dropdown) that
        // legitimately keep their own bg-white and are out of scope for this guard.
        $this->assertSame(1, preg_match("/'class'\s*=>\s*'ptah-navbar([^']*)'/", $blade, $m), "forge-navbar.blade.php: merge de 'class' do <nav> nao encontrado.");
        $this->assertStringNotContainsString('bg-white', $m[1]);
        $this->assertStringNotContainsString('border-gray-100', $m[1]);
    }

    /** @return array<string, array{0: string}> */
    public static function surfaceProvider(): array
    {
        return [
            'main (fundo da pagina)' => ['main'],
            'card (variante default)' => ['.ptah-card-default'],
            'sidebar' => ['.ptah-sidebar'],
            'navbar' => ['.ptah-navbar'],
        ];
    }

    #[Test]
    #[DataProvider('surfaceProvider')]
    public function surface_has_a_token_driven_rule_in_both_scopes(string $selector): void
    {
        $css = self::read(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
        $rules = self::rulesFor($css, $selector);

        $this->assertNotSame(
            '',
            $rules['light'],
            sprintf(
                'ptah-components.css: falta uma regra CLARA "%s { ... }" que declare var(--ptah-*). '.
                'Sem ela, o tom claro escolhido em /profile nao alcanca essa superficie.',
                $selector
            )
        );
        $this->assertNotSame(
            '',
            $rules['dark'],
            sprintf(
                'ptah-components.css: falta uma regra ESCURA ".ptah-dark %s { ... }" que declare var(--ptah-*).',
                $selector
            )
        );
    }

    /**
     * Splits the stylesheet into rules the same way CssDeclarationExtractor does
     * (selector-list before `{`, body inside), then keeps only the declaration
     * body of the rule whose selector is EXACTLY $selector (light) or exactly
     * ".ptah-dark $selector" (dark) — and only if that body uses a --ptah-*
     * token, not just any declaration.
     *
     * @return array{light: string, dark: string}
     */
    private static function rulesFor(string $css, string $selector): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        $light = '';
        $dark = '';

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER)) {
            throw new RuntimeException('ThemeSurfaceLightDarkParityTest: nenhuma regra encontrada em ptah-components.css.');
        }

        foreach ($matches as $rule) {
            $body = trim($rule[2]);

            if ($body === '' || ! str_contains($body, 'var(--ptah-')) {
                continue;
            }

            foreach (explode(',', $rule[1]) as $rawSelector) {
                $normalized = trim(preg_replace('/\s+/', ' ', $rawSelector) ?? $rawSelector);

                if ($normalized === $selector) {
                    $light .= $body;
                } elseif ($normalized === '.ptah-dark '.$selector) {
                    $dark .= $body;
                }
            }
        }

        return ['light' => $light, 'dark' => $dark];
    }

    private static function componentPath(string $file): string
    {
        return dirname(__DIR__, 3).'/resources/views/components/'.$file;
    }

    private static function read(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('ThemeSurfaceLightDarkParityTest: falha ao ler '.$path);
        }

        return $content;
    }
}

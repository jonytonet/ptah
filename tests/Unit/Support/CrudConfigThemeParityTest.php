<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards Onda 4 Parte A of the v1.15.2 audit follow-up: crud-config.blade.php
 * (the CRUD Config editor modal) and its sibling partials/_scripts.blade.php
 * were light-mode-only — 51 `bg-white`, ~250 `text-slate-*`/`border-slate-*`
 * utilities baked into the markup, zero `dark:`, and each carried its own
 * inline <style> block with hex literals (including an indigo focus ring that
 * ignored the host's --color-primary).
 *
 * Same two failure modes as ModuleScreenThemeParityTest, applied to this pair
 * of views instead of the module-screen family:
 *
 *  1. Either view growing a new inline <style> block — the classes/utilities
 *     it would define belong in resources/css/ptah-components.css, tokenized,
 *     not back in the Blade file where the user's theme can never reach them.
 *  2. ptah-components.css missing a light OR dark rule, on the exact bare
 *     selector (light) or `.ptah-dark <selector>` (dark), that declares at
 *     least one var(--ptah-*) value.
 */
class CrudConfigThemeParityTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function styleFreeViewProvider(): array
    {
        return [
            'crud-config' => ['livewire/base-crud/crud-config.blade.php'],
            'base-crud-scripts-partial' => ['livewire/base-crud/partials/_scripts.blade.php'],
        ];
    }

    #[Test]
    #[DataProvider('styleFreeViewProvider')]
    public function view_carries_no_inline_style_block(string $relativePath): void
    {
        $blade = self::read(self::viewPath($relativePath));

        $this->assertStringNotContainsString(
            '<style',
            $blade,
            $relativePath.': voltou a carregar um bloco <style> inline. As regras devem viver em '.
            'resources/css/ptah-components.css, tokenizadas com var(--ptah-*), senao o tema '.
            'escolhido pelo usuario nunca alcanca essa tela.'
        );
    }

    #[Test]
    public function crud_config_root_and_content_wrappers_carry_the_scoping_classes(): void
    {
        $blade = self::read(self::viewPath('livewire/base-crud/crud-config.blade.php'));

        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bptah-cfg\b[^"]*"/',
            $blade,
            'crud-config.blade.php: nenhum elemento carrega a classe .ptah-cfg (wrapper raiz do modal, '.
            'usado por resources/css/ptah-components.css para escopar bg-white/border-slate-*).'
        );
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bptah-cfg-content\b[^"]*"/',
            $blade,
            'crud-config.blade.php: nenhum elemento carrega a classe .ptah-cfg-content (o painel de '.
            'conteudo, distinto da nav rail sempre-escura, usado para escopar text-slate-*).'
        );
    }

    /** @return array<string, array{0: string}> */
    public static function tokenDrivenSelectorProvider(): array
    {
        return [
            '.ptah-cfg .cfg-label' => ['.ptah-cfg .cfg-label'],
            '.ptah-cfg .cfg-input' => ['.ptah-cfg .cfg-input'],
            '.ptah-cfg .cfg-input-sm' => ['.ptah-cfg .cfg-input-sm'],
            '.ptah-cfg .tag' => ['.ptah-cfg .tag'],
            '.ptah-cfg .bg-white' => ['.ptah-cfg .bg-white'],
            '.ptah-cfg .border-slate-100' => ['.ptah-cfg .border-slate-100'],
            '.ptah-cfg .border-slate-200' => ['.ptah-cfg .border-slate-200'],
            '.ptah-cfg .border-slate-300' => ['.ptah-cfg .border-slate-300'],
            '.ptah-cfg-content .bg-slate-50' => ['.ptah-cfg-content .bg-slate-50'],
            '.ptah-cfg-content .bg-slate-100' => ['.ptah-cfg-content .bg-slate-100'],
            '.ptah-cfg-content .bg-slate-200' => ['.ptah-cfg-content .bg-slate-200'],
            '.ptah-cfg .tag:hover' => ['.ptah-cfg .tag:hover'],
            '.ptah-cfg-content .text-slate-300' => ['.ptah-cfg-content .text-slate-300'],
            '.ptah-cfg-content .text-slate-400' => ['.ptah-cfg-content .text-slate-400'],
            '.ptah-cfg-content .text-slate-500' => ['.ptah-cfg-content .text-slate-500'],
            '.ptah-cfg-content .text-slate-600' => ['.ptah-cfg-content .text-slate-600'],
            '.ptah-cfg-content .text-slate-700' => ['.ptah-cfg-content .text-slate-700'],
            '.ptah-cfg-content .text-slate-800' => ['.ptah-cfg-content .text-slate-800'],
        ];
    }

    #[Test]
    #[DataProvider('tokenDrivenSelectorProvider')]
    public function selector_has_a_token_driven_rule_in_both_scopes(string $selector): void
    {
        $css = self::read(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
        $rules = self::rulesFor($css, $selector);

        $this->assertNotSame(
            '',
            $rules['light'],
            sprintf(
                'ptah-components.css: falta uma regra CLARA "%s { ... }" que declare var(--ptah-*). '.
                'Sem ela, o tom claro escolhido em /profile nao alcanca o editor CrudConfig.',
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
     * @return array{light: string, dark: string}
     */
    private static function rulesFor(string $css, string $selector): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        $light = '';
        $dark = '';

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER)) {
            throw new RuntimeException('CrudConfigThemeParityTest: nenhuma regra encontrada em ptah-components.css.');
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

    private static function viewPath(string $relative): string
    {
        return dirname(__DIR__, 3).'/resources/views/'.$relative;
    }

    private static function read(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('CrudConfigThemeParityTest: falha ao ler '.$path);
        }

        return $content;
    }
}

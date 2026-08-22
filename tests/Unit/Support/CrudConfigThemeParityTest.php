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
            // Onda 5 (dark contrast follow-up) — see ptah-components.css's own
            // "CRUD Config editor — dark contrast follow-up" comment block.
            '.ptah-cfg .cfg-input::placeholder' => ['.ptah-cfg .cfg-input::placeholder'],
            '.ptah-cfg .cfg-input-sm::placeholder' => ['.ptah-cfg .cfg-input-sm::placeholder'],
            '.ptah-cfg-content .hover\:bg-slate-50:hover' => ['.ptah-cfg-content .hover\:bg-slate-50:hover'],
            '.ptah-cfg-content .hover\:bg-slate-100:hover' => ['.ptah-cfg-content .hover\:bg-slate-100:hover'],
            '.ptah-cfg-content .hover\:text-slate-500:hover' => ['.ptah-cfg-content .hover\:text-slate-500:hover'],
            '.ptah-cfg-content .hover\:text-slate-600:hover' => ['.ptah-cfg-content .hover\:text-slate-600:hover'],
        ];
    }

    /**
     * `.cfg-ink-warn` is deliberately absent from tokenDrivenSelectorProvider
     * above: its LIGHT rule is an intentional literal (`#b45309`, the exact
     * `text-amber-700` hex it replaces — same "keep the original value" idiom
     * as .cfg-label's own `/* #6B7280, exact match *\/` comment), not a
     * var(--ptah-*) — same documented-literal-exception shape as
     * ModuleScreenSemanticChipContrastTest's purple group badge. Only the
     * DARK rule needs to resolve through a token (it reuses the existing
     * --ptah-warn-lite); see CrudConfigDarkContrastTest for the numeric proof
     * both scopes clear AA.
     */
    #[Test]
    public function cfg_ink_warn_keeps_the_original_light_literal_and_tokenizes_dark(): void
    {
        $css = self::read(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        $this->assertMatchesRegularExpression(
            '/\.ptah-cfg-content \.cfg-ink-warn\s*\{[^}]*color:\s*#b45309/',
            $css,
            'ptah-components.css: falta ".ptah-cfg-content .cfg-ink-warn { color: #b45309 }" (light, literal exato de text-amber-700).'
        );
        $this->assertMatchesRegularExpression(
            '/\.ptah-dark \.ptah-cfg-content \.cfg-ink-warn\s*\{[^}]*color:\s*var\(--ptah-warn-lite\)/',
            $css,
            'ptah-components.css: falta ".ptah-dark .ptah-cfg-content .cfg-ink-warn { color: var(--ptah-warn-lite) }".'
        );
    }

    /**
     * The two literal-tinted callout boxes (bg-sky-50 SQL-source guide,
     * bg-indigo-50 cascading-dropdown hint) keep the SAME background in
     * both themes, so their nested text-slate-* ink only needs a DARK
     * override (restoring the light-mode value) — no light-scope
     * counterpart to require, unlike tokenDrivenSelectorProvider's entries.
     * See ptah-components.css's own comment (point 4) for why the file-wide
     * dark override would be wrong here.
     */
    #[Test]
    public function literal_callout_boxes_restore_the_light_ink_for_nested_slate_text_in_dark(): void
    {
        $css = self::read(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        $this->assertStringContainsString(
            '--ptah-text-secondary-on-tint:',
            $css,
            'ptah-components.css: falta o token invariante --ptah-text-secondary-on-tint.'
        );

        $expectations = [
            '.ptah-dark .ptah-cfg-content .bg-sky-50 .text-slate-400' => '--ptah-text-secondary-on-tint',
            '.ptah-dark .ptah-cfg-content .bg-sky-50 .text-slate-500' => '--ptah-text-secondary-on-tint',
            '.ptah-dark .ptah-cfg-content .bg-indigo-50 .text-slate-500' => '--ptah-text-secondary-on-tint',
            '.ptah-dark .ptah-cfg-content .bg-sky-50 .text-slate-600' => '--ptah-text-secondary-on-tint',
        ];

        foreach ($expectations as $selector => $expectedVar) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($selector, '/').'\s*(,[^{]*)?\{[^}]*color:\s*var\('.preg_quote($expectedVar, '/').'\)/',
                $css,
                sprintf('ptah-components.css: falta a regra "%s { color: var(%s) }".', $selector, $expectedVar)
            );
        }
    }

    /**
     * The sky guide box must stay a SOLID (non-translucent) literal
     * background — the alpha modifier (`/60`) was the root cause of its
     * nested text drifting toward a lighter composite under .ptah-dark's
     * dark ambient surface (see ptah-components.css point 4).
     */
    #[Test]
    public function sql_source_guide_box_is_no_longer_translucent(): void
    {
        $blade = self::read(self::viewPath('livewire/base-crud/crud-config.blade.php'));

        $this->assertStringNotContainsString(
            'bg-sky-50/60',
            $blade,
            'crud-config.blade.php: o guia de fonte SQL voltou a usar bg-sky-50/60 (translucido) — '.
            'isso faz o fundo do card derivar em tom mais claro sob .ptah-dark, quebrando o contraste '.
            'do texto tokenizado aninhado nele.'
        );
        $this->assertStringContainsString('bg-sky-50', $blade);
    }

    /**
     * The two "bare on the tokenized ambient surface" text-amber-700 call
     * sites (permission badge icon, permission select hint) must use
     * .cfg-ink-warn instead — see ptah-components.css point 3.
     */
    #[Test]
    public function permission_badge_and_hint_no_longer_use_the_bare_amber_utility(): void
    {
        $blade = self::read(self::viewPath('livewire/base-crud/crud-config.blade.php'));

        // Only these two call sites sit bare on the tokenized ambient surface
        // (2.91:1 in .ptah-dark) — every remaining text-amber-700 in the file
        // is self-contained inside its OWN literal bg-amber-* box (badge/tag/
        // notice), unaffected by theme, and intentionally left untouched.
        $this->assertStringNotContainsString(
            'class="inline-block w-3 h-3 ml-1 text-amber-700"',
            $blade,
            'crud-config.blade.php: o icone do badge de permissao de coluna voltou a usar text-amber-700 bare.'
        );
        $this->assertStringNotContainsString(
            'class="text-[11px] text-amber-700 mt-1"',
            $blade,
            'crud-config.blade.php: o hint do select de permissao voltou a usar text-amber-700 bare.'
        );
        $this->assertSame(
            2,
            substr_count($blade, 'cfg-ink-warn'),
            'crud-config.blade.php: esperava exatamente 2 usos de .cfg-ink-warn (icone do badge + hint do select).'
        );
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

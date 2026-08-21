<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\AppearancePresets;
use RuntimeException;

/**
 * Onda B — guards the 2 new global appearance axes (densidade, tamanho de
 * fonte) added to resources/css/ptah-components.css.
 *
 * Density reuses, byte-for-byte, the 3-recipe scale that already existed
 * per-screen inside BaseCrud (`.ptah-base-crud[data-density="compact"|
 * "spacious"]`, plus :root for the "comfortable" default) — this is the one
 * guard that proves the GLOBAL axis (`html[data-ptah-density="..."]`) never
 * drifts from those local numbers. If someone tunes one recipe and forgets
 * the other, a BaseCrud screen and the rest of the app would silently render
 * two different "densities" under the same label.
 *
 * Pure math + file reads, no app boot needed — same idiom as
 * AppearancePresetContrastTest.
 */
class DensityFontsizeCssParityTest extends TestCase
{
    private const DENSITY_TOKENS = ['--ptah-control-h', '--ptah-control-px', '--ptah-control-fs', '--ptah-row-py'];

    private static function css(): string
    {
        static $css = null;

        if ($css === null) {
            $read = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

            if ($read === false) {
                throw new RuntimeException('DensityFontsizeCssParityTest: falha ao ler ptah-components.css.');
            }

            // Comments MUST be stripped before any regex parsing below: unlike
            // AppearancePresetContrastTest::block() (which only ever looks for
            // `--ptah-*` tokens, never plain prose), this also matches bare
            // property names like `font-size`. A comment containing "Word:"
            // followed by prose with no semicolon reads as a fake declaration
            // whose greedy value capture swallows everything up to the NEXT
            // real `;` — including the real token right after it. Same idiom
            // as CssTokenResolver::stripComments().
            $css = preg_replace('#/\*.*?\*/#s', '', $read) ?? $read;
        }

        return $css;
    }

    /**
     * Unlike AppearancePresetContrastTest::block() (which only ever needs the
     * `--ptah-*` custom properties), this also has to read plain properties —
     * `font-size` on the html[data-ptah-fontsize="..."] blocks is not a custom
     * property — so the declaration regex matches ANY property name.
     *
     * @return array<string, string> property/token => value
     */
    private static function block(string $selectorPattern, string $label): array
    {
        $pattern = '/'.$selectorPattern.'\s*\{([^}]*)\}/';

        if (! preg_match($pattern, self::css(), $m)) {
            throw new RuntimeException("DensityFontsizeCssParityTest: bloco CSS nao encontrado para [{$label}] (pattern: {$pattern}).");
        }

        $tokens = [];

        if (preg_match_all('/([a-z0-9-]+)\s*:\s*([^;]+);/i', $m[1], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tokens[$match[1]] = trim($match[2]);
            }
        }

        return $tokens;
    }

    /** @return array<string, string> the 4 density-only tokens declared in :root */
    private static function rootDensityTokens(): array
    {
        $all = self::block(':root', 'root');

        return array_intersect_key($all, array_flip(self::DENSITY_TOKENS));
    }

    // ── Whitelist <-> bloco CSS ──────────────────────────────────────────────

    #[Test]
    public function every_whitelisted_density_has_a_html_block_and_vice_versa(): void
    {
        $css = self::css();
        $cssSlugs = [];

        foreach (['compacta', 'confortavel', 'espacosa'] as $slug) {
            if (preg_match('/html\[data-ptah-density="'.$slug.'"\]\s*\{/', $css)) {
                $cssSlugs[] = $slug;
            }
        }

        sort($cssSlugs);
        $whitelist = AppearancePresets::DENSITY;
        sort($whitelist);

        $this->assertSame($whitelist, $cssSlugs);
    }

    /**
     * "normal" is deliberately the ONLY fontsize value with no CSS block (100%,
     * i.e. no rule needed) — same "no server opinion" idiom `mode` uses, see
     * AppearancePresets::FONTSIZE's docblock. Every OTHER whitelisted value must
     * have exactly one block, and no block may exist for a value outside the
     * whitelist.
     */
    #[Test]
    public function every_non_default_fontsize_has_a_html_block_and_normal_has_none(): void
    {
        $css = self::css();

        foreach (AppearancePresets::FONTSIZE as $slug) {
            $hasBlock = (bool) preg_match('/html\[data-ptah-fontsize="'.$slug.'"\]\s*\{/', $css);

            if ($slug === AppearancePresets::DEFAULT_FONTSIZE) {
                $this->assertFalse(
                    $hasBlock,
                    'html[data-ptah-fontsize="'.$slug.'"] tem um bloco CSS, mas e o default — deveria '.
                    'renderizar 100% (nenhuma regra), nao um valor literal redundante.'
                );
            } else {
                $this->assertTrue($hasBlock, 'Falta o bloco html[data-ptah-fontsize="'.$slug.'"].');
            }
        }

        $this->assertSame(
            0,
            preg_match('/html\[data-ptah-fontsize="(?!'.preg_quote(AppearancePresets::DEFAULT_FONTSIZE, '/').'|'.
                implode('|', array_map(
                    static fn (string $s) => preg_quote($s, '/'),
                    array_diff(AppearancePresets::FONTSIZE, [AppearancePresets::DEFAULT_FONTSIZE])
                )).')"\]/', $css),
            'Existe um bloco html[data-ptah-fontsize="..."] para um valor fora da whitelist.'
        );
    }

    // ── Paridade: receita global (html[data-ptah-density]) === receita local
    //    (.ptah-base-crud[data-density]) / :root ────────────────────────────

    /** @return array<string, string> so os 4 tokens compartilhados com o BaseCrud */
    private static function sharedTokens(array $block): array
    {
        return array_intersect_key($block, array_flip(self::DENSITY_TOKENS));
    }

    #[Test]
    public function global_compacta_matches_the_base_crud_compact_recipe(): void
    {
        $this->assertSame(
            self::block('\.ptah-base-crud\[data-density="compact"\]', 'BaseCrud compact'),
            self::sharedTokens(self::block('html\[data-ptah-density="compacta"\]', 'global compacta'))
        );
    }

    #[Test]
    public function global_espacosa_matches_the_base_crud_spacious_recipe(): void
    {
        $this->assertSame(
            self::block('\.ptah-base-crud\[data-density="spacious"\]', 'BaseCrud spacious'),
            self::sharedTokens(self::block('html\[data-ptah-density="espacosa"\]', 'global espacosa'))
        );
    }

    #[Test]
    public function global_confortavel_matches_the_root_default_recipe(): void
    {
        $this->assertSame(
            self::rootDensityTokens(),
            self::sharedTokens(self::block('html\[data-ptah-density="confortavel"\]', 'global confortavel'))
        );
    }

    #[Test]
    public function the_three_html_density_recipes_are_pairwise_distinct(): void
    {
        $compacta = self::block('html\[data-ptah-density="compacta"\]', 'compacta');
        $confortavel = self::block('html\[data-ptah-density="confortavel"\]', 'confortavel');
        $espacosa = self::block('html\[data-ptah-density="espacosa"\]', 'espacosa');

        $this->assertNotSame($compacta, $confortavel);
        $this->assertNotSame($confortavel, $espacosa);
        $this->assertNotSame($compacta, $espacosa);
    }

    // ── Tamanho de fonte: percentuais exatos do plano ────────────────────────

    #[Test]
    public function fontsize_percentages_match_the_spec(): void
    {
        $pequena = self::block('html\[data-ptah-fontsize="pequena"\]', 'pequena');
        $grande = self::block('html\[data-ptah-fontsize="grande"\]', 'grande');

        $this->assertSame('87.5%', $pequena['font-size'] ?? null);
        $this->assertSame('112.5%', $grande['font-size'] ?? null);
    }

    // ── Nenhum bloco destes 2 eixos referencia var() — só literais ───────────

    #[Test]
    public function css_density_and_fontsize_blocks_never_reference_a_var(): void
    {
        $selectors = [
            'html[data-ptah-density="compacta"]',
            'html[data-ptah-density="confortavel"]',
            'html[data-ptah-density="espacosa"]',
            'html[data-ptah-fontsize="pequena"]',
            'html[data-ptah-fontsize="grande"]',
        ];

        foreach ($selectors as $selector) {
            $pattern = '/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/';

            if (! preg_match($pattern, self::css(), $m)) {
                throw new RuntimeException("DensityFontsizeCssParityTest: bloco nao encontrado para o seletor [{$selector}].");
            }

            $this->assertStringNotContainsStringIgnoringCase(
                'var(',
                $m[1],
                "O bloco do seletor [{$selector}] referencia var(...) — presets de aparencia devem conter so literais."
            );
        }
    }

    /**
     * Os tokens POR-FAMILIA (--ptah-field-fs/-py, --ptah-bar-py) existem porque
     * compartilhar --ptah-control-* encolheu todo input/botao/toolbar fora do
     * BaseCrud (achado de revisao, Onda B): o valor confortavel de cada familia
     * E o valor pre-densidade daquela familia. Este teste pina (a) presenca nas
     * 3 receitas globais e (b) confortavel identico ao :root — regressao zero
     * por construcao.
     */
    #[Test]
    public function per_family_tokens_exist_in_every_global_recipe_and_confortavel_equals_root(): void
    {
        $family = ['--ptah-field-fs', '--ptah-field-py', '--ptah-bar-py'];
        $root = array_intersect_key(self::block(':root', 'root'), array_flip($family));

        $this->assertSame(
            ['--ptah-field-fs' => '.875rem', '--ptah-field-py' => '.625rem', '--ptah-bar-py' => '.75rem'],
            $root,
            'Os valores confortaveis da familia em :root sao o contrato de "regressao zero" — mudou um, mudou o render default de todo consumidor.'
        );

        foreach (['compacta', 'confortavel', 'espacosa'] as $slug) {
            $block = array_intersect_key(
                self::block('html\[data-ptah-density="'.$slug.'"\]', "global {$slug}"),
                array_flip($family)
            );
            $this->assertSame($family, array_keys($block), "Receita global [{$slug}] sem os 3 tokens por-familia.");
        }

        $confortavel = array_intersect_key(
            self::block('html\[data-ptah-density="confortavel"\]', 'global confortavel'),
            array_flip($family)
        );
        $this->assertSame($root, $confortavel, 'confortavel deve ser identico ao :root (regressao zero).');
    }
}

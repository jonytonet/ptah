<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Tests\Support\AssertsGoldenCssFixture;
use Ptah\Tests\Support\CssDeclarationExtractor;
use Ptah\Tests\Support\CssTokenResolver;
use RuntimeException;

/**
 * Golden-master guard for resources/css/ptah-components.css. It reads every
 * declaration in the file, resolves any --ptah-* custom property down to its
 * final rendered value (per scope), and compares the whole map against a
 * byte-for-byte fixture captured BEFORE the neutrals were tokenized.
 *
 * If this test fails outside of a deliberate, reviewed change, a token
 * substitution altered what actually renders.
 *
 * Sibling guard: LayoutStyleBaselineTest, over the inline <style> block in
 * forge-dashboard-layout. When a rule MOVES from that block into this file,
 * both fixtures change in the same commit — one loses the site, the other
 * gains it, and the values on both sides must match.
 *
 * To (re)capture: run once with PTAH_REGEN_CSS_BASELINE=1 (writes the fixture
 * and marks itself skipped with a warning — it never regenerates silently).
 */
class NeutralTokenBaselineTest extends TestCase
{
    use AssertsGoldenCssFixture;

    private const FIXTURE_PATH = __DIR__.'/../../Fixtures/css-neutral-baseline.json';

    #[Test]
    public function resolved_neutral_declarations_match_the_captured_baseline(): void
    {
        $css = self::css();

        $extractor = new CssDeclarationExtractor(
            new CssTokenResolver($css),
            stripTokenBlocks: true,
        );

        $this->assertMatchesGoldenFixture(
            self::FIXTURE_PATH,
            $extractor->extract($css),
            'NeutralTokenBaselineTest'
        );
    }

    /**
     * CssTokenResolver merges the `.ptah-base-crud` token block into BOTH scopes,
     * because the density recipe it holds varies by density rather than by theme.
     * That merge takes precedence over `:root`, so a token declared in both places
     * would resolve to the component value everywhere in the test's view — while in
     * a browser it would only apply inside a BaseCrud. Every assertion about that
     * token outside the CRUD would then be measuring a colour that never renders.
     *
     * The two blocks must therefore stay disjoint. They are today (35 tokens vs 4);
     * this keeps them that way.
     */
    #[Test]
    public function the_root_and_base_crud_token_blocks_declare_disjoint_names(): void
    {
        $css = self::css();

        $this->assertSame(1, preg_match('/:root\s*\{([^}]*)\}/', $css, $root));
        $this->assertSame(
            1,
            preg_match('/\.ptah-base-crud\s*\{([^}]*)\}/', $css, $crud),
            'Bloco de tokens `.ptah-base-crud { ... }` (a receita de densidade) nao encontrado.'
        );

        preg_match_all('/(--ptah-[a-z0-9-]+)\s*:/', $root[1], $rootNames);
        preg_match_all('/(--ptah-[a-z0-9-]+)\s*:/', $crud[1], $crudNames);

        $collisions = array_values(array_intersect($rootNames[1], $crudNames[1]));

        $this->assertSame(
            [],
            $collisions,
            'Token declarado em `:root` E em `.ptah-base-crud`: '.implode(', ', $collisions)."\n".
            'O resolver de teste mescla o bloco do componente sobre o :root nos dois escopos, '.
            'entao a partir daqui ele resolveria esse token para o valor do componente em todo '.
            'lugar — inclusive fora do BaseCrud, onde no browser o valor do :root e que vale. '.
            'Renomeie o token do componente.'
        );
    }

    /**
     * CssTokenResolver::parseTokens() reads the FIRST bare `.ptah-dark { ... }`
     * block in the file as the dark token map. The layout migration is about to
     * add a second one — the rule that paints the page background — and if that
     * rule lands above the token block, the resolver silently reads a two-property
     * map and every one of the 24 tokens becomes undefined.
     *
     * That failure is loud in the suite but catastrophic in a browser: an
     * undefined var() is invalid at computed-value time, so the declaration is
     * dropped and the property falls back to inherited/unset. The UI is not
     * degraded, it is destroyed. Cheaper to pin the order here than to debug it
     * from a screenshot.
     */
    #[Test]
    public function the_first_bare_ptah_dark_block_is_the_token_block(): void
    {
        $css = self::css();

        $this->assertSame(
            1,
            preg_match('/\.ptah-dark\s*\{([^}]*)\}/', $css, $m),
            'Nenhum bloco `.ptah-dark { ... }` nu encontrado em ptah-components.css.'
        );

        $this->assertStringContainsString(
            '--ptah-canvas',
            $m[1],
            'O primeiro bloco `.ptah-dark { ... }` nu de ptah-components.css deixou de ser o bloco de tokens. '.
            'CssTokenResolver le exatamente esse bloco como mapa dark, entao qualquer outra regra '.
            '`.ptah-dark { ... }` (por exemplo a que pinta o fundo da pagina) precisa vir DEPOIS dele — '.
            'senao os 24 tokens ficam indefinidos e cada var(--ptah-*) se torna declaracao invalida no browser.'
        );
    }

    private static function css(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('NeutralTokenBaselineTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}

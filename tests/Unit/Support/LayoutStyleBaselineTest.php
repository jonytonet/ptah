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
 * Golden-master guard for the inline <style> block in
 * resources/views/components/forge-dashboard-layout.blade.php.
 *
 * Why this exists: that block retrofits dark mode onto the chrome (sidebar,
 * navbar, dropdowns, tables) with ~125 rules and no tokens at all, so the
 * user-selectable theme cannot reach any of it. Migrating those rules onto the
 * --ptah-* neutral tokens means rewriting a few hundred color literals whose
 * only spec is "what it looks like today" — exactly the situation a golden
 * fixture is for. Without it, "zero visual change" would be a claim; with it,
 * it is a measurement.
 *
 * Two things this guard does NOT cover, on purpose:
 *
 *  1. Which rule WINS. This block is unlayered CSS, so it beats Tailwind
 *     utilities (which live in @layer utilities) regardless of specificity.
 *     21 of its rules exploit that to repaint utility classes like
 *     .text-gray-400 from a distance; the fixture records what those rules
 *     declare, not the cascade outcome on any given element. Moving one of
 *     those into a layered stylesheet keeps this test green while changing the
 *     rendering — so utility-targeting rules must be migrated together with
 *     the view that uses them, not on the strength of a green fixture.
 *  2. Anything outside the <style> element (Tailwind classes in the markup).
 *
 * Regenerate with PTAH_REGEN_CSS_BASELINE=1 (also regenerates the sibling
 * ptah-components.css baseline — when a rule moves between the two files, both
 * fixtures must change in the same reviewed commit).
 */
class LayoutStyleBaselineTest extends TestCase
{
    use AssertsGoldenCssFixture;

    private const FIXTURE_PATH = __DIR__.'/../../Fixtures/css-layout-baseline.json';

    #[Test]
    public function resolved_layout_style_declarations_match_the_captured_baseline(): void
    {
        $extractor = new CssDeclarationExtractor(
            // Tokens come from the stylesheet that DEFINES them, so a rule
            // migrated to `var(--ptah-surface)` resolves to the same literal it
            // is replacing — that equality is the whole point of the exercise.
            new CssTokenResolver(self::componentsCss()),
            // The layout's bare `.ptah-dark { background-color: ...; color: ... }`
            // is a real rule that paints the page, not a token block: keep it.
            stripTokenBlocks: false,
        );

        $css = CssDeclarationExtractor::styleBlockFromBlade(self::layoutBlade());

        $this->assertMatchesGoldenFixture(
            self::FIXTURE_PATH,
            $extractor->extract($css),
            'LayoutStyleBaselineTest'
        );
    }

    /**
     * The block is being dismantled rule by rule. This pins the direction of
     * travel: it may only ever shrink. A commit that ADDS hardcoded color to
     * the layout — the habit that produced the block in the first place — fails
     * here even if it happens to keep every existing site untouched.
     */
    #[Test]
    public function the_inline_style_block_never_grows(): void
    {
        $css = CssDeclarationExtractor::styleBlockFromBlade(self::layoutBlade());

        $colorLiterals = preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css);
        $rules = preg_match_all('/\{/', $css);

        $this->assertLessThanOrEqual(
            93,
            $colorLiterals,
            'O bloco <style> do layout ganhou literais de cor. Cor nova de chrome vai em '.
            'resources/css/ptah-components.css usando os tokens --ptah-*, senao o tema '.
            'escolhido pelo usuario nunca alcanca esse elemento.'
        );
        $this->assertLessThanOrEqual(
            81,
            $rules,
            'O bloco <style> do layout ganhou regras. Ele esta sendo desmontado, nao estendido.'
        );
    }

    private static function layoutBlade(): string
    {
        $path = dirname(__DIR__, 3).'/resources/views/components/forge-dashboard-layout.blade.php';
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('LayoutStyleBaselineTest: falha ao ler '.$path);
        }

        return $content;
    }

    private static function componentsCss(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('LayoutStyleBaselineTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}

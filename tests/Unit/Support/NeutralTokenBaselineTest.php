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

    private static function css(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('NeutralTokenBaselineTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}

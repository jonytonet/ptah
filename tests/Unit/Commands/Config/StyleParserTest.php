<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\StyleParser;
use Ptah\Tests\TestCase;

/**
 * Covers the StyleParser — the `field:condition:value:style` DSL behind
 * `ptah:config ... --style=...`. Pure parsing, no DB.
 */
class StyleParserTest extends TestCase
{
    private StyleParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StyleParser;
    }

    #[Test]
    public function parses_the_four_fixed_segments(): void
    {
        $c = $this->parser->parse('status:==:cancelled:background:#FEE2E2;color:#991B1B;');

        $this->assertSame('status', $c['field']);
        $this->assertSame('==', $c['condition']);
        $this->assertSame('cancelled', $c['value']);
        $this->assertSame('background:#FEE2E2;color:#991B1B;', $c['style']);
    }

    #[Test]
    public function stops_splitting_after_the_fourth_segment_so_the_style_keeps_its_own_colons(): void
    {
        // explode(..., 4) means every ':' from the 4th segment onward belongs
        // to "style" verbatim — this is what lets a CSS declaration list
        // (which is inherently colon-separated) survive intact.
        $c = $this->parser->parse('amount:>:1000:color:red;font-weight:bold;');

        $this->assertSame('amount', $c['field']);
        $this->assertSame('>', $c['condition']);
        $this->assertSame('1000', $c['value']);
        $this->assertSame('color:red;font-weight:bold;', $c['style']);
    }

    #[Test]
    public function throws_when_fewer_than_four_segments_are_given(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Style syntax requires: field:condition:value:style');

        $this->parser->parse('status:==:cancelled');
    }

    #[Test]
    public function accepts_an_empty_style_segment(): void
    {
        // 4 segments exist (the 4th is just empty) — no exception expected.
        $c = $this->parser->parse('status:==:cancelled:');

        $this->assertSame('', $c['style']);
    }
}

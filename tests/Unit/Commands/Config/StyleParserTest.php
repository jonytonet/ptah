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
    public function rejects_an_empty_style_segment(): void
    {
        // DEVIATION from the original expectation (this used to accept an
        // empty style silently): StyleRule::normalize() treats an empty style
        // as unusable — HasCrudRenderers::getRowStyle() would never apply it
        // anyway — so the single canonical normaliser now rejects it here too,
        // consistently with every other consumer (CrudRenderersTest, doctor).
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('status:==:cancelled:');
    }

    #[Test]
    public function canonicalises_the_eq_alias_to_the_php_style_operator(): void
    {
        $c = $this->parser->parse('status:eq:cancelled:color:red;');

        $this->assertSame('==', $c['condition']);
    }

    #[Test]
    public function like_operator_is_rejected_with_the_valid_conditions_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valid conditions: ==, !=, >, <, >=, <=');

        $this->parser->parse('status:LIKE:cancelled:color:red;');
    }

    #[Test]
    public function the_style_marker_lets_the_value_keep_its_own_colons(): void
    {
        // The mirror of stops_splitting_after_the_fourth_segment: that test
        // proves the STYLE keeps its colons, and this one proves the VALUE can
        // too — which the positional form alone cannot express, since the style
        // is colon-rich by nature and takes everything after the third colon.
        $rule = $this->parser->parse('start_at:==:12:30:style=background:#eee;');

        $this->assertSame('start_at', $rule['field']);
        $this->assertSame('==', $rule['condition']);
        $this->assertSame('12:30', $rule['value']);
        $this->assertSame('background:#eee;', $rule['style']);
    }

    #[Test]
    public function without_the_marker_the_positional_form_is_unchanged(): void
    {
        // Backwards compatibility is the point: every --style= call written
        // before the marker existed must parse byte-for-byte as it did. That
        // includes the known-bad case (a colon in the value), which stays
        // mis-parsed on purpose rather than being "fixed" by a heuristic that
        // could mis-parse a legitimate style string instead. Documented in
        // docs/KnownLimitations.md.
        $rule = $this->parser->parse('start_at:==:12:30:background:#eee;');

        $this->assertSame('12', $rule['value']);
        $this->assertSame('30:background:#eee;', $rule['style']);
    }

    #[Test]
    public function the_marker_form_still_requires_field_condition_and_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('start_at:style=background:#eee;');
    }

    #[Test]
    public function only_the_first_style_marker_ends_the_value(): void
    {
        // A style that itself contains the literal 'style=' (a CSS custom
        // property, say) must not re-split the definition.
        $rule = $this->parser->parse('kind:==:a:b:style=--style=x;color:red;');

        $this->assertSame('a:b', $rule['value']);
        $this->assertSame('--style=x;color:red;', $rule['style']);
    }
}

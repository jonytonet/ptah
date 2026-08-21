<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\GeneralParser;
use Ptah\Tests\TestCase;

/**
 * Covers the GeneralParser — the `key=value` DSL behind repeated
 * `ptah:config ... --set="key=value"` options. Pure parsing, no DB.
 */
class GeneralParserTest extends TestCase
{
    private GeneralParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GeneralParser;
    }

    #[Test]
    public function parses_multiple_key_value_settings(): void
    {
        $c = $this->parser->parse(['displayName=Products', 'cacheEnabled=true']);

        $this->assertSame('Products', $c['displayName']);
        $this->assertTrue($c['cacheEnabled']);
    }

    #[Test]
    public function casts_the_literal_strings_true_and_false_to_booleans(): void
    {
        $c = $this->parser->parse(['a=true', 'b=false']);

        $this->assertTrue($c['a']);
        $this->assertFalse($c['b']);
    }

    #[Test]
    public function casts_numeric_strings_to_int_or_float(): void
    {
        $c = $this->parser->parse(['perPage=25', 'ratio=1.5']);

        $this->assertSame(25, $c['perPage']);
        $this->assertIsInt($c['perPage']);
        $this->assertSame(1.5, $c['ratio']);
        $this->assertIsFloat($c['ratio']);
    }

    #[Test]
    public function leaves_non_numeric_non_boolean_strings_untouched(): void
    {
        $c = $this->parser->parse(['displayName=Products']);

        $this->assertSame('Products', $c['displayName']);
        $this->assertIsString($c['displayName']);
    }

    #[Test]
    public function silently_skips_settings_without_an_equals_sign(): void
    {
        $c = $this->parser->parse(['malformed_no_equals', 'valid=1']);

        $this->assertArrayNotHasKey('malformed_no_equals', $c);
        $this->assertSame(1, $c['valid']);
    }

    #[Test]
    public function splits_only_on_the_first_equals_sign(): void
    {
        // The value itself may legitimately contain '=' (e.g. a query string).
        $c = $this->parser->parse(['url=https://example.com?a=1&b=2']);

        $this->assertSame('https://example.com?a=1&b=2', $c['url']);
    }

    #[Test]
    public function returns_an_empty_array_for_an_empty_settings_list(): void
    {
        $this->assertSame([], $this->parser->parse([]));
    }
}

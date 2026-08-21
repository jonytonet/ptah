<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\FilterParser;
use Ptah\Tests\TestCase;

/**
 * Covers the FilterParser — the `field:type:key=value` DSL behind
 * `ptah:config ... --filter=...`. Pure parsing, no DB.
 */
class FilterParserTest extends TestCase
{
    private FilterParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FilterParser;
    }

    #[Test]
    public function parses_field_and_derives_a_titleised_label_by_default(): void
    {
        $c = $this->parser->parse('supplier_name:text');

        $this->assertSame('supplier_name', $c['field']);
        $this->assertSame('Supplier Name', $c['label']);
        $this->assertSame('text', $c['colsFilterType']);
        $this->assertSame('=', $c['defaultOperator']);
        $this->assertSame('', $c['whereHas']);
    }

    #[Test]
    public function type_defaults_to_text_when_only_the_field_is_given(): void
    {
        $c = $this->parser->parse('status');

        $this->assertSame('status', $c['field']);
        $this->assertSame('text', $c['colsFilterType']);
    }

    #[Test]
    public function applies_key_value_options_including_where_has_and_operator(): void
    {
        $c = $this->parser->parse('supplier_name:text:label=Fornecedor:whereHas=supplier:field=name:operator=LIKE');

        $this->assertSame('Fornecedor', $c['label']);
        $this->assertSame('supplier', $c['whereHas']);
        $this->assertSame('name', $c['field']); // 'field=' overwrites the original definition field
        $this->assertSame('LIKE', $c['operator']);
    }

    #[Test]
    public function routes_the_options_key_to_the_options_config_slot(): void
    {
        $c = $this->parser->parse('status:select:options=active:Active,inactive:Inactive');

        $this->assertSame('active:Active,inactive:Inactive', $c['options']);
    }

    /**
     * BUG FIX (Onda 4 Parte B): before tokenize() was added, a plain
     * explode(':', $definition) silently truncated the options value at its
     * first ':' — "options=active:Active,inactive:Inactive" resolved to just
     * "active", dropping "Active,inactive:Inactive" with no warning or error.
     * ColumnParser already solves this identical case (same DSL, same
     * `options=value1:label1,value2:label2` syntax) with the same algorithm;
     * this pins the fix in place for FilterParser too.
     */
    #[Test]
    public function preserves_every_colon_inside_the_options_value_not_just_the_first(): void
    {
        $c = $this->parser->parse('status:select:options=active:Active,inactive:Inactive,pending:Pending');

        $this->assertSame('active:Active,inactive:Inactive,pending:Pending', $c['options']);
    }

    #[Test]
    public function ignores_option_tokens_without_an_equals_sign(): void
    {
        $c = $this->parser->parse('status:select:not_a_kv_pair');

        $this->assertArrayNotHasKey('not_a_kv_pair', $c);
        $this->assertSame('select', $c['colsFilterType']);
    }
}

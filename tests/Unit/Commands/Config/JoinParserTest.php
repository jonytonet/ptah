<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\JoinParser;
use Ptah\Tests\TestCase;

/**
 * Covers the JoinParser — the `type:table:first=second:option` DSL behind
 * `ptah:config ... --join=...`. Pure parsing, no DB.
 */
class JoinParserTest extends TestCase
{
    private JoinParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new JoinParser;
    }

    #[Test]
    public function parses_type_table_and_the_on_condition(): void
    {
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id');

        $this->assertSame('left', $c['type']);
        $this->assertSame('suppliers', $c['table']);
        $this->assertSame('products.supplier_id', $c['first']);
        $this->assertSame('suppliers.id', $c['second']);
        $this->assertFalse($c['distinct']);
        $this->assertSame('', $c['selectRaw']);
    }

    #[Test]
    public function marks_distinct_when_the_bare_modifier_is_present(): void
    {
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id:distinct');

        $this->assertTrue($c['distinct']);
    }

    #[Test]
    public function captures_a_select_raw_column_list_after_select_equals(): void
    {
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id:select=suppliers.name');

        $this->assertSame('suppliers.name', $c['selectRaw']);
    }

    #[Test]
    public function keeps_the_first_on_condition_when_a_second_equals_sign_pair_appears(): void
    {
        // Only the FIRST "a=b" segment (that isn't "select=...") is treated as
        // the ON condition; a later one is parsed but does not overwrite it.
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id:another.a=another.b');

        $this->assertSame('products.supplier_id', $c['first']);
        $this->assertSame('suppliers.id', $c['second']);
    }

    #[Test]
    public function throws_when_fewer_than_three_segments_are_given(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JOIN syntax requires at least: type:table:first=second');

        $this->parser->parse('left:suppliers');
    }

    #[Test]
    public function select_keeps_aliases_and_multiple_columns_from_the_docblocks_own_example(): void
    {
        $r = (new JoinParser)->parse(
            'left:suppliers:products.supplier_id=suppliers.id:distinct:select=suppliers.name:supplier_name,suppliers.cnpj:supplier_cnpj'
        );

        $this->assertSame('suppliers.name:supplier_name,suppliers.cnpj:supplier_cnpj', $r['selectRaw']);
        $this->assertTrue($r['distinct']);
        $this->assertSame('products.supplier_id', $r['first']);
        $this->assertSame('suppliers.id', $r['second']);
    }

    #[Test]
    public function distinct_after_the_on_pair_stays_a_flag_not_a_value_continuation(): void
    {
        $r = (new JoinParser)->parse(
            'inner:categories:products.category_id=categories.id:distinct'
        );

        $this->assertTrue($r['distinct']);
        $this->assertSame('categories.id', $r['second']);
    }
}

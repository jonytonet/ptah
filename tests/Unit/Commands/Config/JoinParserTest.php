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
        $this->assertSame([], $c['select']);
    }

    #[Test]
    public function marks_distinct_when_the_bare_modifier_is_present(): void
    {
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id:distinct');

        $this->assertTrue($c['distinct']);
    }

    #[Test]
    public function select_becomes_the_column_alias_list_the_runtime_reads(): void
    {
        // 1.50.0: it was stored as a `selectRaw` string nothing read.
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id:select=suppliers.name');

        $this->assertSame([['column' => 'suppliers.name', 'alias' => 'name']], $c['select']);
        $this->assertArrayNotHasKey('selectRaw', $c);
    }

    #[Test]
    public function select_accepts_as_for_the_alias(): void
    {
        $c = $this->parser->parse('left:suppliers:products.supplier_id=suppliers.id:select=suppliers.name as supplier_name');

        $this->assertSame([['column' => 'suppliers.name', 'alias' => 'supplier_name']], $c['select']);
    }

    #[Test]
    public function distinct_equals_true_turns_distinct_on(): void
    {
        $this->assertTrue($this->parser->parse('left:s:p.s_id=s.id:distinct=true')['distinct']);
        $this->assertFalse($this->parser->parse('left:s:p.s_id=s.id:distinct=false')['distinct']);
    }

    #[Test]
    public function a_where_option_is_refused_not_dropped(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JOIN option "where" is not supported');

        $this->parser->parse('left:s:p.s_id=s.id:where=s.active=1');
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

        $this->assertSame([
            ['column' => 'suppliers.name', 'alias' => 'supplier_name'],
            ['column' => 'suppliers.cnpj', 'alias' => 'supplier_cnpj'],
        ], $r['select']);
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

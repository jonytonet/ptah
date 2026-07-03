<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Tests\TestCase;

/**
 * Covers the ColumnParser — the `field:type:modifier:key=value` DSL that the
 * ptah:config declarative mode (and the scaffold skill) rely on. Pure parsing,
 * no DB.
 */
class ColumnParserTest extends TestCase
{
    private ColumnParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ColumnParser;
    }

    #[Test]
    public function parses_field_and_type_with_sensible_defaults(): void
    {
        $c = $this->parser->parse('name:text');

        $this->assertSame('name', $c['colsNomeFisico']);
        $this->assertSame('text', $c['colsTipo']);
        $this->assertSame('Name', $c['colsNomeLogico']); // titleised from field
        $this->assertTrue($c['colsGravar']);
        $this->assertFalse($c['colsRequired']);
    }

    #[Test]
    public function type_defaults_to_text_when_omitted(): void
    {
        $c = $this->parser->parse('sku');

        $this->assertSame('sku', $c['colsNomeFisico']);
        $this->assertSame('text', $c['colsTipo']);
    }

    #[Test]
    public function maps_label_and_renderer_shortcuts_to_full_keys(): void
    {
        $c = $this->parser->parse('price:money:label=Preço:renderer=money:currency=BRL');

        $this->assertSame('money', $c['colsTipo']);
        $this->assertSame('Preço', $c['colsNomeLogico']);      // label → colsNomeLogico
        $this->assertSame('money', $c['colsRenderer']);        // renderer → colsRenderer
        $this->assertSame('BRL', $c['colsRendererCurrency']);  // currency → colsRendererCurrency
    }

    #[Test]
    public function applies_boolean_modifiers(): void
    {
        $required = $this->parser->parse('name:text:required');
        $this->assertTrue($required['colsRequired']);

        $readonly = $this->parser->parse('code:text:readonly');
        $this->assertFalse($readonly['colsGravar']);

        $hidden = $this->parser->parse('secret:text:hidden');
        $this->assertFalse($hidden['colsVisibleList']);

        $sortable = $this->parser->parse('name:text:sortable');
        $this->assertSame('name', $sortable['colsOrderBy']);
    }

    #[Test]
    public function maps_relation_shortcuts(): void
    {
        $c = $this->parser->parse('category_id:relation:relation=category:relation_display=name');

        $this->assertSame('relation', $c['colsTipo']);
        $this->assertSame('category', $c['colsRelacao']);
        $this->assertSame('name', $c['colsRelacaoExibe']);
    }

    #[Test]
    public function parses_badges_into_value_color_label_triples(): void
    {
        $c = $this->parser->parse('status:badge:badges=active|green|Ativo,inactive|gray|Inativo');

        $this->assertSame([
            ['value' => 'active', 'color' => 'green', 'label' => 'Ativo'],
            ['value' => 'inactive', 'color' => 'gray', 'label' => 'Inativo'],
        ], $c['colsRendererBadges']);
    }

    #[Test]
    public function tokenizer_preserves_colons_inside_option_values(): void
    {
        // The value side of options=… contains ':' which must NOT be split as tokens.
        $c = $this->parser->parse('status:select:options=active:Active,inactive:Inactive');

        $this->assertSame('select', $c['colsTipo']);
        $this->assertSame('active:Active,inactive:Inactive', $c['colsSelect']);
    }

    #[Test]
    public function casts_numeric_and_boolean_option_values(): void
    {
        $c = $this->parser->parse('qty:numeric:decimals=2:link_new_tab=true');

        $this->assertSame(2, $c['colsRendererDecimals']);   // numeric → int
        $this->assertTrue($c['colsRendererLinkNewTab']);    // 'true' → bool
    }
}

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

        // This used to assert the raw string, which was the observable at the
        // time only because parseOptions() returned it untouched — a shape the
        // views cannot render (collect() on a scalar yields one option labelled
        // '0'). It now asserts the normalised label => value map, which proves
        // the same thing more strongly: every colon-separated pair arrived
        // intact, so nothing was truncated at the first ':'.
        $this->assertSame(['Active' => 'active', 'Inactive' => 'inactive'], $c['colsSelect']);
    }

    #[Test]
    public function casts_numeric_and_boolean_option_values(): void
    {
        $c = $this->parser->parse('qty:numeric:decimals=2:link_new_tab=true');

        $this->assertSame(2, $c['colsRendererDecimals']);   // numeric → int
        $this->assertTrue($c['colsRendererLinkNewTab']);    // 'true' → bool
    }

    #[Test]
    public function maps_permission_shortcut_to_colspermission(): void
    {
        $c = $this->parser->parse('cost:number:permission=viewCost');

        $this->assertSame('viewCost', $c['colsPermission']);
    }

    #[Test]
    public function permission_shortcut_preserves_the_qualified_key_separator(): void
    {
        // The value side of permission=… contains '::' (page-qualified key,
        // see PermissionService::KEY_QUALIFIER) which the tokenizer must NOT
        // split — it only special-cases the single ':' that separates DSL
        // tokens, re-joining anything without '=' back into the open buffer.
        $c = $this->parser->parse('cost:number:permission=page::viewCost');

        $this->assertSame('page::viewCost', $c['colsPermission']);
    }

    #[Test]
    public function a_bare_modifier_after_an_option_is_a_modifier_and_not_part_of_its_value(): void
    {
        // `price:number:label=Price:renderer=money:sortable` — ptah-development
        // /SKILL.md:430, written that way for several releases. The tokenizer
        // treats a fragment without '=' as the continuation of the open value
        // (which is what makes `options=open:Aberto` work), so `renderer`
        // became the string "money:sortable": an invalid renderer, and the
        // whole column was refused by ConfigSchemaValidator. The list of
        // modifiers is closed, so a fragment that IS one closes the value.
        $c = $this->parser->parse('price:number:label=Price:renderer=money:sortable');

        $this->assertSame('money', $c['colsRenderer'], 'O modificador foi engolido pelo valor do renderer.');
        $this->assertSame('price', $c['colsOrderBy'] ?? null, 'E o `sortable` tem de ter sido aplicado.');
        $this->assertSame('Price', $c['colsNomeLogico']);
    }

    #[Test]
    public function a_value_that_really_contains_a_colon_is_still_kept_whole(): void
    {
        // A contrapartida, e o motivo de o buffer existir: nenhum fragmento
        // destes e um modificador, entao todos continuam entrando no valor.
        $c = $this->parser->parse('status:select:options=open:Aberto,closed:Fechado:required');

        $this->assertSame(['Aberto' => 'open', 'Fechado' => 'closed'], $c['colsSelect']);
        $this->assertTrue($c['colsRequired'], 'O `required` no fim tambem e um modificador.');
    }

    #[Test]
    public function badges_alone_imply_the_select_options(): void
    {
        // `select` + `badges=` sem `options=` era recusado pelo validador, que
        // exige colsSelect — e e assim que a documentacao escreve o exemplo.
        // As entradas de badge ja sao pares valor/rotulo.
        $c = $this->parser->parse('is_active:select:renderer=badge:badges=1|success|Ativo,0|danger|Inativo');

        $this->assertSame(['Ativo' => '1', 'Inativo' => '0'], $c['colsSelect']);
    }
}

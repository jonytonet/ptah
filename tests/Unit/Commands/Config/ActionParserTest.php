<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ActionParser;
use Ptah\Tests\TestCase;

/**
 * Covers the ActionParser — the `name:type:value:key=value` DSL behind
 * `ptah:config ... --action=...`. Pure parsing, no DB.
 */
class ActionParserTest extends TestCase
{
    private ActionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ActionParser;
    }

    #[Test]
    public function parses_name_type_and_value_with_defaults(): void
    {
        $c = $this->parser->parse('approve:livewire:approve(%id%)');

        $this->assertSame('approve', $c['colsNomeLogico']);
        $this->assertSame('action', $c['colsTipo']);
        $this->assertSame('livewire', $c['actionType']);
        $this->assertSame('approve(%id%)', $c['actionValue']);
        $this->assertSame('', $c['actionIcon']);
        $this->assertSame('primary', $c['actionColor']);
        $this->assertSame('', $c['actionPermission']);
    }

    #[Test]
    public function maps_key_value_options_onto_studly_action_keys(): void
    {
        $c = $this->parser->parse('approve:livewire:approve(%id%):icon=bx-check:color=success:permission=admin');

        $this->assertSame('bx-check', $c['actionIcon']);
        $this->assertSame('success', $c['actionColor']);
        $this->assertSame('admin', $c['actionPermission']);
    }

    #[Test]
    public function preserves_colons_inside_the_value_when_it_precedes_the_first_option(): void
    {
        // The value itself contains ':' (a URL) — must survive intact because
        // option collection only stops at the FIRST "key=value" looking token.
        $c = $this->parser->parse('open:link:https://example.com/path:icon=bx-link');

        $this->assertSame('link', $c['actionType']);
        $this->assertSame('https://example.com/path', $c['actionValue']);
        $this->assertSame('bx-link', $c['actionIcon']);
    }

    #[Test]
    public function throws_when_fewer_than_three_segments_are_given(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action syntax requires at least: name:type:value');

        $this->parser->parse('approve:livewire');
    }

    #[Test]
    public function ignores_option_tokens_without_an_equals_sign(): void
    {
        $c = $this->parser->parse('approve:livewire:approve(%id%):not_a_kv_pair:icon=bx-check');

        $this->assertSame('bx-check', $c['actionIcon']);
        $this->assertArrayNotHasKey('actionNot_a_kv_pair', $c);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Laudo 1.51.0: forge-stat-card and forge-empty carried fixed palette classes
 * (border-gray-200, text-slate-400), forge-button could not be a link, and
 * forge-table could not hold a component inside a cell.
 */
class ForgeLaudo151Test extends TestCase
{
    private const FIXED_PALETTE = '/\b(?:border|text|bg)-(?:gray|slate)-\d{2,3}\b/';

    #[Test]
    public function stat_card_carries_no_fixed_palette_class(): void
    {
        $html = (string) $this->blade(
            '<x-forge-stat-card title="Pedidos" value="12" trend="+5%" trendLabel="vs. ontem" color="dark" />'
        );

        $this->assertDoesNotMatchRegularExpression(self::FIXED_PALETTE, $html);
        $this->assertStringNotContainsString('text-dark', $html);
        $this->assertStringContainsString('ptah-c-stat_value', $html);
        $this->assertStringContainsString('vs. ontem', $html);
    }

    #[Test]
    public function empty_state_default_icon_carries_no_fixed_palette_class(): void
    {
        $html = (string) $this->blade('<x-forge-empty title="Nada aqui" />');

        $this->assertDoesNotMatchRegularExpression(self::FIXED_PALETTE, $html);
        $this->assertStringContainsString('ptah-c-empty_icon', $html);
    }

    #[Test]
    public function button_with_href_renders_a_link_with_the_same_look(): void
    {
        $html = (string) $this->blade(
            '<x-forge-button href="/pedidos" color="success" size="sm" wire:navigate>Pedidos</x-forge-button>'
        );

        $this->assertMatchesRegularExpression('/^\s*<a\b/', $html);
        $this->assertStringContainsString('href="/pedidos"', $html);
        $this->assertStringContainsString('wire:navigate', $html);
        $this->assertStringContainsString('ptah-btn ptah-btn-success ptah-btn-size-sm', $html);
        $this->assertStringNotContainsString('type="button"', $html);
        $this->assertStringNotContainsString('<button', $html);
        $this->assertMatchesRegularExpression('/<\/a>\s*$/', $html);
    }

    #[Test]
    public function a_disabled_link_button_cannot_be_followed(): void
    {
        $html = (string) $this->blade('<x-forge-button href="/pedidos" disabled>Pedidos</x-forge-button>');

        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
    }

    #[Test]
    public function button_without_href_is_still_a_button(): void
    {
        $html = (string) $this->blade('<x-forge-button wire:click="save">Salvar</x-forge-button>');

        $this->assertMatchesRegularExpression('/^\s*<button\b/', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringNotContainsString('href=', $html);
    }

    #[Test]
    public function table_slot_mode_renders_components_inside_cells(): void
    {
        $html = (string) $this->blade(
            '<x-forge-table :headers="$headers">'
            .'@foreach ($items as $item)'
            .'<tr><td>{{ $item }}</td><td><x-forge-button :href="\'/itens/\'.$item" size="sm">Abrir</x-forge-button></td></tr>'
            .'@endforeach'
            .'</x-forge-table>',
            [
                'headers' => [['key' => 'name', 'label' => 'Nome'], ['key' => 'acoes', 'label' => 'Ações']],
                'items' => ['alfa', 'beta'],
            ]
        );

        $this->assertStringContainsString('href="/itens/alfa"', $html);
        $this->assertStringContainsString('href="/itens/beta"', $html);
        $this->assertStringContainsString('ptah-c-ftbl_body', $html);
        $this->assertStringContainsString('>Nome</th>', $html);
        // Server rows: no Alpine copy of the data, no client-side templates.
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('x-for', $html);
        $this->assertDoesNotMatchRegularExpression(self::FIXED_PALETTE, $html);
    }

    #[Test]
    public function table_slot_mode_without_rows_shows_the_empty_message(): void
    {
        $html = (string) $this->blade(
            '<x-forge-table :headers="$headers" emptyMessage="Sem itens">'
            ."\n@foreach (\$items as \$item)<tr><td>{{ \$item }}</td></tr>@endforeach\n"
            .'</x-forge-table>',
            ['headers' => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B']], 'items' => []]
        );

        $this->assertStringContainsString('Sem itens', $html);
        $this->assertStringContainsString('colspan="2"', $html);
        $this->assertStringNotContainsString('x-data', $html);
    }

    #[Test]
    public function table_rows_mode_is_unchanged(): void
    {
        $html = (string) $this->blade(
            '<x-forge-table :headers="$headers" :rows="$rows" searchable />',
            ['headers' => [['key' => 'name', 'label' => 'Nome']], 'rows' => [['name' => 'alfa']]]
        );

        $this->assertStringContainsString('x-data', $html);
        $this->assertStringContainsString('filteredRows', $html);
        $this->assertStringContainsString('type="search"', $html);
    }
}

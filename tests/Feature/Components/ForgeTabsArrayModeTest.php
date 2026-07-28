<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the array-mode (Alpine) normalization of <x-forge-tabs>.
 *
 * Before this fix, array-mode assumed every tab item had an `id` key. Items
 * keyed by `key` (as used by resources/views/forge-demo.blade.php) or with
 * no identifier at all threw an undefined array key error on every touch
 * point (`$tabs[0]['id']`, `array_column($tabs, 'id')`, `$tab['id']` in the
 * `@foreach` loops). This test locks the normalization contract: `id` wins,
 * `key` is accepted as an alias, and a missing identifier falls back to
 * "tab-{index}" — without ever throwing.
 */
class ForgeTabsArrayModeTest extends TestCase
{
    #[Test]
    public function id_identifier_renders_the_expected_tab_id(): void
    {
        $html = (string) $this->blade(
            '<x-forge-tabs :tabs="$tabs" />',
            ['tabs' => [
                ['id' => 'a', 'label' => 'Aba A', 'slot' => 'conteudo-a'],
            ]]
        );

        $this->assertStringContainsString('id="tab-a"', $html);
        $this->assertStringContainsString('id="panel-a"', $html);
    }

    #[Test]
    public function key_identifier_renders_without_exception_and_produces_tab_info_id(): void
    {
        $html = (string) $this->blade(
            '<x-forge-tabs :tabs="$tabs" />',
            ['tabs' => [
                ['key' => 'info', 'label' => 'Informações'],
            ]]
        );

        $this->assertStringContainsString('id="tab-info"', $html);
    }

    #[Test]
    public function item_without_id_or_key_falls_back_to_positional_id_without_exception(): void
    {
        $html = (string) $this->blade(
            '<x-forge-tabs :tabs="$tabs" />',
            ['tabs' => [
                ['label' => 'Sem identificador'],
            ]]
        );

        // Normalized identifier is "tab-0"; the button element prefixes it again
        // (id="tab-{id}"), so the rendered attribute is "tab-tab-0".
        $this->assertStringContainsString('id="tab-tab-0"', $html);
        $this->assertStringContainsString("activeTab: 'tab-0'", $html);
    }

    #[Test]
    public function default_tab_works_with_key_identified_items(): void
    {
        $html = (string) $this->blade(
            '<x-forge-tabs :tabs="$tabs" default-tab="history" />',
            ['tabs' => [
                ['key' => 'info',    'label' => 'Informações'],
                ['key' => 'history', 'label' => 'Histórico'],
            ]]
        );

        $this->assertStringContainsString("activeTab: 'history'", $html);
    }

    #[Test]
    public function slot_content_is_injected_into_its_panel(): void
    {
        $html = (string) $this->blade(
            '<x-forge-tabs :tabs="$tabs" />',
            ['tabs' => [
                ['id' => 'info', 'label' => 'Informações', 'slot' => '<p>Conteúdo da aba Informações</p>'],
            ]]
        );

        $this->assertStringContainsString('Conteúdo da aba Informações', $html);
    }

    #[Test]
    public function slot_mode_livewire_still_renders_correctly(): void
    {
        $html = (string) $this->blade(<<<'BLADE'
            <x-forge-tabs>
                <x-slot name="tabs">
                    <x-forge-tab key="foo" :active="true">Foo</x-forge-tab>
                    <x-forge-tab key="bar" :active="false">Bar</x-forge-tab>
                </x-slot>
                <p>Painel ativo</p>
            </x-forge-tabs>
            BLADE);

        $this->assertStringContainsString('Foo', $html);
        $this->assertStringContainsString('Bar', $html);
        $this->assertStringContainsString('Painel ativo', $html);
        // Array-mode markup (x-data with tabIds/activeTab) must NOT leak into slot mode.
        $this->assertStringNotContainsString('tabIds:', $html);
    }
}

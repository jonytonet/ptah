<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Models\Menu;
use Ptah\Support\MenuResolver;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

/**
 * The sidebar draws every level of the menu (1.60.1, yelll).
 *
 * Menu::nestItems() builds up to ptah.menu.max_depth levels, and the jump box
 * and find_menu walked all of them, but forge-sidebar drew only two: a child
 * with children became a link to `#` and its children vanished, so the search
 * showed "Fiscal > Cadastro > Ncms" — a path that did not exist on screen.
 * With two levels the markup is byte-for-byte what it was (checked when the
 * change was made), so the existing sidebar tests guard that side.
 */
class ForgeSidebarMultiLevelTest extends TestCase
{
    use ActsAsPtahUser;

    /** @return list<array<string, mixed>> */
    private function tree(): array
    {
        return [
            ['type' => 'menuGroup', 'label' => 'Fiscal', 'icon' => 'bx bx-receipt', 'children' => [
                ['type' => 'menuGroup', 'label' => 'Cadastro', 'children' => [
                    ['label' => 'Ncms', 'url' => '/fiscal/ncms'],
                    ['type' => 'menuGroup', 'label' => 'Tabelas', 'children' => [
                        ['label' => 'Aliquotas de um nome bem comprido', 'url' => '/fiscal/aliquotas'],
                    ]],
                ]],
                ['label' => 'Notas', 'url' => '/fiscal/notas'],
            ]],
            ['type' => 'menuGroup', 'label' => 'Compras', 'children' => [
                ['type' => 'menuGroup', 'label' => 'Cadastro', 'children' => [
                    ['label' => 'Fornecedores', 'url' => '/compras/fornecedores'],
                ]],
            ]],
        ];
    }

    private function render(string $page, bool $spa = true): string
    {
        config(['ptah.forge.spa_navigation' => $spa]);
        $this->get($page);

        return (string) $this->blade('<x-forge-sidebar appName="Test" :items="$items" />', ['items' => $this->tree()]);
    }

    #[Test]
    public function every_level_is_drawn_and_a_subgroup_is_a_toggle_not_a_link_to_nowhere(): void
    {
        $html = $this->render('/elsewhere');

        foreach (['Ncms', 'Tabelas', 'Aliquotas de um nome bem comprido', 'Fornecedores'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('href="/fiscal/aliquotas"', $html);
        $this->assertStringNotContainsString('href="#"', $html, 'A subgroup must not be drawn as a link to "#".');
        // Three subgroups (Fiscal/Cadastro, Fiscal/Cadastro/Tabelas, Compras/Cadastro), each a button.
        $this->assertSame(5, substr_count($html, 'aria-haspopup="true"'));
    }

    #[Test]
    public function the_whole_path_to_a_deep_page_comes_open_and_the_page_is_current(): void
    {
        $html = $this->render('/fiscal/aliquotas');

        $this->assertMatchesRegularExpression('/x-data="\{ open: true[^"]*"\s*data-ptah-group="Fiscal"/', $html);
        $this->assertMatchesRegularExpression('/x-data="\{ open: true[^"]*"\s*data-ptah-group="Fiscal\/Cadastro"/', $html);
        $this->assertMatchesRegularExpression('/x-data="\{ open: true[^"]*"\s*data-ptah-group="Fiscal\/Cadastro\/Tabelas"/', $html);
        $this->assertMatchesRegularExpression('/x-data="\{ open: false[^"]*"\s*data-ptah-group="Compras\/Cadastro"/', $html);
        $this->assertMatchesRegularExpression('/href="\/fiscal\/aliquotas"[^>]*aria-current="page"/', $html);
    }

    #[Test]
    public function a_label_repeated_in_two_branches_keeps_two_open_states(): void
    {
        $html = $this->render('/elsewhere');

        $this->assertStringContainsString('data-ptah-group="Fiscal/Cadastro"', $html);
        $this->assertStringContainsString('data-ptah-group="Compras/Cadastro"', $html);
        $this->assertStringNotContainsString('data-ptah-group="Cadastro"', $html);
    }

    #[Test]
    public function from_the_third_level_a_label_truncates_and_keeps_its_full_text_in_title(): void
    {
        $html = $this->render('/elsewhere', spa: false);

        $this->assertMatchesRegularExpression('/class="min-w-0 truncate text-sm" title="Aliquotas de um nome bem comprido"/', $html);
        // The second level stays as it was.
        $this->assertMatchesRegularExpression('/<span class="whitespace-nowrap text-sm">Notas<\/span>/', $html);
        $this->assertStringNotContainsString('data-ptah-group', $html, 'Without SPA navigation there is no state key.');
    }

    #[Test]
    public function is_active_branch_looks_at_every_level_below(): void
    {
        $this->get('/fiscal/aliquotas');
        [$fiscal, $compras] = $this->tree();

        $this->assertTrue(MenuResolver::isActiveBranch($fiscal));
        $this->assertTrue(MenuResolver::isActiveBranch($fiscal['children'][0]));
        $this->assertFalse(MenuResolver::isActiveBranch($compras));
        $this->assertFalse(MenuResolver::isActiveBranch(['label' => 'Leaf', 'url' => '/fiscal/aliquotas']), 'A link has no branch below it.');
    }

    #[Test]
    public function the_menu_admin_offers_parents_by_path_and_never_a_cycle(): void
    {
        $this->actAsMaster();
        $make = fn (string $text, ?int $parent) => Menu::create([
            'text' => $text, 'icon' => 'bx bx-circle', 'type' => 'menuGroup', 'target' => '_self',
            'parent_id' => $parent, 'link_order' => 0, 'is_active' => true,
        ]);
        $fiscal = $make('Fiscal', null);
        $cadastro = $make('Cadastro', $fiscal->id);
        $tabelas = $make('Tabelas', $cadastro->id);
        $compras = $make('Compras', null);
        $make('Cadastro', $compras->id);

        $component = Livewire::test(MenuList::class)->call('edit', $cadastro->id);
        $labels = collect($component->instance()->groups)->pluck('text')->all();

        $this->assertContains('Fiscal', $labels);
        $this->assertContains('Compras > Cadastro', $labels);
        $this->assertNotContains('Fiscal > Cadastro', $labels, 'The item itself is not a parent option.');
        $this->assertNotContains('Fiscal > Cadastro > Tabelas', $labels, 'Neither is anything below it.');

        // The request can still carry it: the rule refuses.
        $component->set('parent_id', $tabelas->id)->call('save')->assertHasErrors(['parent_id']);
        $this->assertSame($fiscal->id, (int) $cadastro->fresh()->parent_id);
    }
}

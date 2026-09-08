<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Foundation\Auth\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\AiToolInterface;
use Ptah\Contracts\AiToolSchemaInterface;
use Ptah\Models\Menu;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Services\AI\Tools\FindMenuTool;
use Ptah\Services\Menu\MenuService;
use Ptah\Support\MenuResolver;
use Ptah\Tests\TestCase;

/**
 * The tool that lets the assistant answer "where is X?".
 *
 * The point of these tests is not that the tool returns rows. It is that the
 * rows are the SAME menu the sidebar draws, and that a miss is reported as a
 * miss. An assistant that invents a navigation path is worse than one that
 * declines: the person follows the invented path, finds nothing, and stops
 * trusting the answers that were right.
 */
class FindMenuToolTest extends TestCase
{
    /**
     * Seeds the `menus` table, because that is the only source that carries a
     * TREE. `ptah.forge.sidebar_items` is a flat list with no children, so a
     * nested menu — the whole point of a trail — cannot be expressed there. This
     * is also what the reporting host runs.
     */
    private function seedMenu(): void
    {
        $compras = Menu::create([
            'text' => 'Compras', 'url' => null, 'icon' => 'bx bx-cart',
            'type' => 'menuGroup', 'target' => '_self', 'link_order' => 1, 'is_active' => true,
        ]);

        Menu::create([
            'text' => 'Cotação', 'url' => '/compras/cotacao', 'icon' => 'bx bx-dollar',
            'type' => 'menuLink', 'target' => '_self', 'parent_id' => $compras->id,
            'link_order' => 1, 'is_active' => true,
        ]);

        Menu::create([
            'text' => 'Divergências Cotação', 'url' => '/compras/divergencias', 'icon' => 'bx bx-git-compare',
            'type' => 'menuLink', 'target' => '_self', 'parent_id' => $compras->id,
            'link_order' => 2, 'is_active' => true,
        ]);

        // Desligado no admin: a sidebar nao desenha, a tool nao pode oferecer.
        Menu::create([
            'text' => 'Pedido Desativado', 'url' => '/compras/pedido', 'icon' => 'bx bx-x',
            'type' => 'menuLink', 'target' => '_self', 'parent_id' => $compras->id,
            'link_order' => 3, 'is_active' => false,
        ]);

        $cadastro = Menu::create([
            'text' => 'Cadastro Geral', 'url' => null, 'icon' => 'bx bx-folder',
            'type' => 'menuGroup', 'target' => '_self', 'link_order' => 2, 'is_active' => true,
        ]);

        $localidades = Menu::create([
            'text' => 'Localidades', 'url' => null, 'icon' => 'bx bx-map',
            'type' => 'menuGroup', 'target' => '_self', 'parent_id' => $cadastro->id,
            'link_order' => 1, 'is_active' => true,
        ]);

        Menu::create([
            'text' => 'Cidades', 'url' => '/localidades/cidades', 'icon' => 'bx bx-building',
            'type' => 'menuLink', 'target' => '_self', 'parent_id' => $localidades->id,
            'link_order' => 1, 'is_active' => true,
        ]);

        app(MenuService::class)->clearCache();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ptah.modules.menu' => true,
            'ptah.menu.driver' => 'database',
            'ptah.forge.sidebar_items' => [],
        ]);

        $this->seedMenu();
    }

    private function tool(): FindMenuTool
    {
        return new FindMenuTool;
    }

    private function loginUser(): void
    {
        $user = new class extends User
        {
            protected $table = 'users';
        };

        $user->forceFill(['id' => 1, 'name' => 'Ana', 'email' => 'ana@example.test'])->syncOriginal();

        $this->be($user);
    }

    // ── O contrato ────────────────────────────────────────────────────────

    #[Test]
    public function it_can_be_described_without_being_built(): void
    {
        // It carries AiToolSchemaInterface not for its own sake — it has no
        // dependencies — but because a built-in that skipped its own interface
        // would leave that path untested by the package's use of it.
        $this->assertTrue(is_subclass_of(FindMenuTool::class, AiToolSchemaInterface::class));
        $this->assertTrue(is_subclass_of(FindMenuTool::class, AiToolInterface::class));

        $schema = FindMenuTool::toolSchema();

        $this->assertSame('find_menu', $schema['name']);
        $this->assertNotSame('', trim($schema['description']));
        $this->assertArrayHasKey('query', $schema['parameters']['properties']);
    }

    #[Test]
    public function the_static_schema_agrees_with_the_instance_methods(): void
    {
        // The registry does not reconcile them — doing so would mean building
        // the tool, which is the cost the interface exists to avoid — so
        // disagreement would mean the model is told one thing and calls another.
        $tool = $this->tool();
        $schema = FindMenuTool::toolSchema();

        $this->assertSame($schema['name'], $tool->name());
        $this->assertSame($schema['description'], $tool->description());
        $this->assertSame($schema['parameters'], $tool->parameters());
    }

    #[Test]
    public function it_is_registered_as_a_built_in(): void
    {
        $this->assertContains(
            FindMenuTool::class,
            AiToolRegistry::fromConfig()->pendingClasses()
        );
    }

    #[Test]
    public function it_reaches_the_model_as_a_prism_tool(): void
    {
        $names = array_map(
            fn ($t) => $t->name(),
            AiToolRegistry::fromConfig()->getPrismTools()
        );

        $this->assertContains('find_menu', $names);
    }

    // ── O que ele responde ────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string}>
     */
    public static function cotacaoQueryProvider(): array
    {
        return [
            'exato' => ['Cotação'],
            'sem acento' => ['cotacao'],
            'caixa alta' => ['COTACAO'],
            'duas palavras em qualquer ordem' => ['cotacao compras'],
            'ordem invertida' => ['compras cotacao'],
        ];
    }

    #[Test]
    #[DataProvider('cotacaoQueryProvider')]
    public function it_finds_the_screen_however_the_question_was_typed(string $query): void
    {
        // Nobody types accents when they are in a hurry, and nobody types the
        // trail in the order the menu happens to nest it.
        $this->loginUser();

        $result = $this->tool()->execute(['query' => $query]);

        $found = collect($result['results'] ?? [])->pluck('path')->all();

        $this->assertNotEmpty($found, "Nada encontrado para `{$query}`.");
        $this->assertContains('Compras > Cotação', $found);
    }

    #[Test]
    public function the_path_reads_in_the_order_someone_has_to_click(): void
    {
        // Root to leaf. The jump box shows leaf-first because you typed the
        // leaf; an instruction is the other way round.
        $this->loginUser();

        $result = $this->tool()->execute(['query' => 'cidades']);

        $this->assertSame(
            'Cadastro Geral > Localidades > Cidades',
            $result['results'][0]['path'] ?? null
        );
        $this->assertSame('/localidades/cidades', $result['results'][0]['url'] ?? null);
    }

    #[Test]
    public function a_miss_is_reported_as_a_miss(): void
    {
        // The instruction is the point: a model handed an empty list tends to
        // fill it in.
        $this->loginUser();

        $result = $this->tool()->execute(['query' => 'nao existe essa tela']);

        $this->assertSame(0, $result['found']);
        $this->assertArrayHasKey('note', $result);
        $this->assertStringContainsString('guessing', $result['note']);
        $this->assertArrayNotHasKey('results', $result);
    }

    #[Test]
    public function an_inactive_entry_is_never_offered(): void
    {
        // The sidebar does not render it either, and sending someone to a
        // screen an administrator switched off is a wrong answer.
        $this->loginUser();

        $result = $this->tool()->execute(['query' => 'pedido']);

        $this->assertSame(0, $result['found']);
    }

    #[Test]
    public function a_group_is_not_a_destination(): void
    {
        // A menuGroup has no url in ptah, so it is not somewhere to be sent —
        // it only appears inside the trail.
        $this->loginUser();

        $result = $this->tool()->execute(['query' => 'compras']);

        foreach ($result['results'] ?? [] as $row) {
            $this->assertNotSame('Compras', $row['screen']);
            $this->assertNotSame('', $row['url']);
        }
    }

    #[Test]
    public function no_query_lists_the_menu(): void
    {
        $this->loginUser();

        $result = $this->tool()->execute([]);

        $this->assertGreaterThan(0, $result['found']);
        $this->assertNotEmpty($result['results']);
    }

    #[Test]
    public function a_long_menu_is_capped_and_says_so(): void
    {
        // A menu can hold hundreds of entries; returning all of them is tokens
        // spent on a list nobody asked for. Silent truncation would let the
        // model conclude a screen does not exist.
        $items = [];

        for ($i = 1; $i <= 200; $i++) {
            $items[] = ['label' => "Tela {$i}", 'url' => "/t{$i}", 'match' => "t{$i}"];
        }

        // Driver de config aqui: 200 telas planas nao precisam de arvore, e
        // semear 200 linhas no banco so tornaria o teste lento. O setUp deixou
        // o driver em `database`, entao trocar e parte do arranjo, nao detalhe.
        config([
            'ptah.modules.menu' => false,
            'ptah.menu.driver' => 'config',
            'ptah.forge.sidebar_items' => $items,
        ]);
        $this->loginUser();

        $result = $this->tool()->execute([]);

        $this->assertTrue($result['truncated'] ?? false);
        $this->assertSame(200, $result['total'] ?? null);
        $this->assertLessThan(200, $result['found']);
        $this->assertArrayHasKey('note', $result);
    }

    #[Test]
    public function a_search_returns_an_answer_not_a_list(): void
    {
        $items = [];

        for ($i = 1; $i <= 200; $i++) {
            $items[] = ['label' => "Cotação {$i}", 'url' => "/c{$i}", 'match' => "c{$i}"];
        }

        config([
            'ptah.modules.menu' => false,
            'ptah.menu.driver' => 'config',
            'ptah.forge.sidebar_items' => $items,
        ]);
        $this->loginUser();

        $result = $this->tool()->execute(['query' => 'cotacao']);

        $this->assertLessThanOrEqual(8, $result['found']);
    }

    // ── Segurança ─────────────────────────────────────────────────────────

    #[Test]
    public function a_guest_is_told_nothing_about_the_menu(): void
    {
        // The chat can be opened to guests, and a guest never sees the sidebar.
        // The whole internal structure of the application is not something to
        // hand an anonymous session just because the chat is reachable.
        config(['ptah.ai_agent.allow_guests' => true]);

        $result = $this->tool()->execute(['query' => 'cotacao']);

        $this->assertFalse($result['available']);
        $this->assertArrayNotHasKey('results', $result);
    }

    // ── A fonte única ─────────────────────────────────────────────────────

    #[Test]
    public function it_reads_the_same_menu_the_sidebar_draws(): void
    {
        $this->loginUser();

        $result = $this->tool()->execute([]);

        $expected = collect(MenuResolver::flatLinks())->pluck('url')->sort()->values()->all();
        $actual = collect($result['results'])->pluck('url')->sort()->values()->all();

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_follows_the_database_driver_when_that_is_what_the_host_uses(): void
    {
        // The resolver's priority chain, exercised through the tool: a host on
        // the database driver must not get the config menu.
        config([
            'ptah.modules.menu' => true,
            'ptah.menu.driver' => 'database',
            'ptah.forge.sidebar_items' => [['label' => 'Do config', 'url' => '/config-only']],
        ]);

        Menu::create([
            'text' => 'Do banco',
            'url' => '/do-banco',
            'icon' => 'bx bx-data',
            'type' => 'menuLink',
            'target' => '_self',
            'link_order' => 1,
            'is_active' => true,
        ]);

        app(MenuService::class)->clearCache();
        $this->loginUser();

        $urls = collect($this->tool()->execute([])['results'])->pluck('url')->all();

        $this->assertContains('/do-banco', $urls);
        $this->assertNotContains('/config-only', $urls);
    }

    #[Test]
    public function the_tool_cannot_be_pointed_at_another_menu(): void
    {
        // `execute()` accepts only `query`. Anything else is ignored, so a
        // model cannot be talked into describing a menu that is not this
        // application's.
        $this->loginUser();

        $result = $this->tool()->execute([
            'query' => '',
            'items' => [['label' => 'Injetado', 'url' => '/injetado']],
        ]);

        $urls = collect($result['results'])->pluck('url')->all();

        $this->assertNotContains('/injetado', $urls);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\MenuResolver;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * The sidebar's jump box: type part of a screen's name, go straight to it.
 *
 * Asked for after a menu grew past the point where scanning it is faster than
 * typing. Two decisions in it are worth pinning, because both diverge from what
 * was requested and the reasons are not obvious from the code:
 *
 * **It is at the TOP.** The reference implementation the request came from puts
 * it in a page footer, but this sidebar's bottom already belongs to Sign out —
 * a rare, careful action that a frequent one must not sit on top of. And the
 * whole point is a menu long enough to scroll, so a control inside the
 * scrolling area would disappear exactly when it is needed. A control that
 * filters a list belongs before the list.
 *
 * **It filters in the browser.** The request named the package's
 * `SearchDropdown`, which queries Eloquent per keystroke — for data that is
 * already on this page, because the sidebar just rendered it. A round trip per
 * keystroke to search the browser's own memory is slower and costs more with
 * nothing in return.
 */
class SidebarMenuJumpTest extends TestCase
{
    /**
     * A menu with a group, so the trail has something in it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function menu(int $links = 20): array
    {
        $children = [];

        for ($i = 1; $i < $links; $i++) {
            $children[] = [
                'label' => "Tela {$i}",
                'text' => "Tela {$i}",
                'url' => "/tela-{$i}",
                'icon' => 'bx bx-circle',
                'type' => 'menuLink',
                'target' => '_self',
                'is_active' => true,
                'children' => [],
            ];
        }

        $children[] = [
            'label' => 'Cotação',
            'text' => 'Cotação',
            'url' => '/compras/cotacao',
            'icon' => 'bx bx-dollar',
            'type' => 'menuLink',
            'target' => '_self',
            'is_active' => true,
            'children' => [],
        ];

        return [[
            'label' => 'Compras',
            'text' => 'Compras',
            'url' => null,
            'icon' => 'bx bx-cart',
            'type' => 'menuGroup',
            'target' => '_self',
            'is_active' => true,
            'children' => $children,
        ]];
    }

    private function render(?array $items = null): string
    {
        $html = (string) view('ptah::components.forge-sidebar', [
            'items' => $items ?? $this->menu(),
            'attributes' => new ComponentAttributeBag,
        ])->render();

        if (! str_contains($html, 'ptah-sidebar')) {
            throw new RuntimeException('A sidebar nao renderizou — nada abaixo teria alvo.');
        }

        return $html;
    }

    #[Test]
    public function the_box_sits_above_the_nav_not_below_it(): void
    {
        // The position decision, pinned by ORDER in the document rather than by
        // a class name — a class could be moved without anything noticing.
        $html = $this->render();

        $jump = strpos($html, 'ptah-sidebar-jump');
        $nav = strpos($html, '<nav');
        $footer = strpos($html, 'ptah-sidebar-footer');

        $this->assertIsInt($jump, 'O atalho nao renderizou.');
        $this->assertIsInt($nav);
        $this->assertIsInt($footer);

        $this->assertLessThan($nav, $jump, 'O atalho tem de vir ANTES da navegacao.');
        $this->assertLessThan($footer, $jump, 'E muito antes do Sair, que e acao rara e cuidadosa.');
    }

    /**
     * A FLAT menu, so nesting is not what decides the case.
     *
     * @return array<int, array<string, mixed>>
     */
    private function flatMenu(int $links): array
    {
        $out = [];

        for ($i = 1; $i <= $links; $i++) {
            $out[] = [
                'label' => "Tela {$i}",
                'text' => "Tela {$i}",
                'url' => "/tela-{$i}",
                'icon' => 'bx bx-circle',
                'type' => 'menuLink',
                'target' => '_self',
                'is_active' => true,
                'children' => [],
            ];
        }

        return $out;
    }

    #[Test]
    public function a_short_flat_menu_does_not_get_it(): void
    {
        // Five links you can see all at once: the field would be decoration.
        config(['ptah.forge.sidebar_jump_min_items' => 8]);

        $this->assertStringNotContainsString('ptah-sidebar-jump', $this->render($this->flatMenu(5)));
    }

    #[Test]
    public function a_nested_menu_gets_it_regardless_of_how_few_links_there_are(): void
    {
        // The correction that matters, and the reason a threshold alone was
        // wrong: a screen inside a closed group is not visible to someone
        // scanning the menu, however few there are. That is exactly where typing
        // wins, and it does not depend on a count.
        //
        // The first version keyed only on a count of 12, which was arbitrary AND
        // silent: a test app with 8 links never showed the field, shrinking the
        // window did nothing, and nothing said why.
        config(['ptah.forge.sidebar_jump_min_items' => 99]);

        $this->assertStringContainsString('ptah-sidebar-jump', $this->render($this->menu(links: 4)));
    }

    #[Test]
    public function a_long_flat_menu_gets_it_too(): void
    {
        // The other reason, on its own: no nesting, but too many to scan.
        config(['ptah.forge.sidebar_jump_min_items' => 8]);

        $this->assertStringContainsString('ptah-sidebar-jump', $this->render($this->flatMenu(20)));
    }

    #[Test]
    public function a_trivial_menu_never_gets_it(): void
    {
        // The floor. Two links, nested or not, is not a menu you search.
        config(['ptah.forge.sidebar_jump_min_items' => 1]);

        $this->assertStringNotContainsString('ptah-sidebar-jump', $this->render([
            ['label' => 'Um', 'url' => '/um', 'type' => 'menuLink', 'is_active' => true, 'children' => []],
            ['label' => 'Dois', 'url' => '/dois', 'type' => 'menuLink', 'is_active' => true, 'children' => []],
        ]));
    }

    #[Test]
    public function it_appears_on_a_real_menu(): void
    {
        config(['ptah.forge.sidebar_jump_min_items' => 8]);

        $this->assertStringContainsString('ptah-sidebar-jump', $this->render($this->menu(links: 20)));
    }

    #[Test]
    public function the_threshold_is_configurable(): void
    {
        // Exercised on a FLAT menu: with nesting the threshold is irrelevant,
        // so an earlier version of this test could not actually see it move.
        config(['ptah.forge.sidebar_jump_min_items' => 50]);
        $this->assertStringNotContainsString('ptah-sidebar-jump', $this->render($this->flatMenu(20)));

        config(['ptah.forge.sidebar_jump_min_items' => 4]);
        $this->assertStringContainsString('ptah-sidebar-jump', $this->render($this->flatMenu(20)));
    }

    #[Test]
    public function the_feature_can_be_turned_off_entirely(): void
    {
        config(['ptah.forge.sidebar_jump' => false]);

        $this->assertStringNotContainsString('ptah-sidebar-jump', $this->render($this->menu(links: 20)));
    }

    #[Test]
    public function no_request_is_made_per_keystroke(): void
    {
        // The other divergence: the links are embedded, so filtering is local.
        $html = $this->render();

        $this->assertStringContainsString('items:', $html);
        $this->assertStringContainsString('/compras/cotacao', $html, 'Os links precisam estar embutidos.');
        $this->assertStringNotContainsString('wire:model', $this->jumpBlock($html));
        $this->assertStringNotContainsString('ptah-search-dropdown', $html);
    }

    /**
     * The jump block alone, so an assertion cannot match the rest of the bar.
     */
    private function jumpBlock(string $html): string
    {
        $start = strpos($html, 'ptah-sidebar-jump');
        $end = strpos($html, '<nav');

        $this->assertIsInt($start);
        $this->assertIsInt($end);

        return substr($html, $start, $end - $start);
    }

    #[Test]
    public function the_trail_is_shown_leaf_first(): void
    {
        // You typed the leaf, so the leaf is what the eye is looking for; the
        // ancestors are context behind it. This matches the reference system the
        // request came from.
        $html = $this->render();

        $this->assertStringContainsString('it.breadcrumb.slice(0, -1).reverse()', $html);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function keyboardProvider(): array
    {
        return [
            'seta para baixo' => ['@keydown.arrow-down.prevent="move(1)"'],
            'seta para cima' => ['@keydown.arrow-up.prevent="move(-1)"'],
            'enter abre' => ['@keydown.enter.prevent="go(results[hi] || results[0])"'],
            'esc limpa' => ['@keydown.escape.stop="reset()"'],
        ];
    }

    #[Test]
    #[DataProvider('keyboardProvider')]
    public function it_is_operable_from_the_keyboard(string $binding): void
    {
        // A jump box you have to reach for the mouse to use is slower than
        // scrolling the menu, which is the thing it replaces.
        $this->assertStringContainsString($binding, $this->render());
    }

    #[Test]
    public function enter_with_nothing_highlighted_opens_the_first_result(): void
    {
        // Type, press Enter — the common case must not require an arrow key
        // first.
        $this->assertStringContainsString('results[hi] || results[0]', $this->render());
    }

    #[Test]
    public function the_collapsed_sidebar_gets_a_magnifier_that_expands_and_focuses(): void
    {
        // Icon-only has no room for an input, and an input that is there but
        // invisible is worse than an icon that says what it does.
        $html = $this->jumpBlock($this->render());

        $this->assertStringContainsString('x-show="iconOnly()"', $html);
        $this->assertStringContainsString('bx bx-search', $html);
        $this->assertStringContainsString("localStorage.setItem('ptah_sidebar_collapsed', 'false')", $html);
        $this->assertStringContainsString('$refs.jump.focus()', $html);
    }

    #[Test]
    public function the_results_float_over_the_nav(): void
    {
        // Pushing the menu down while typing would make the whole bar move on
        // every keystroke.
        $html = $this->jumpBlock($this->render());

        $this->assertMatchesRegularExpression(
            '/ptah-sidebar-jump-results[^"]*absolute/',
            $html
        );
    }

    #[Test]
    public function an_empty_result_says_so(): void
    {
        // A list that does not open cannot be told apart from one that broke.
        $html = $this->jumpBlock($this->render());

        $this->assertStringContainsString('ptah-sidebar-jump-empty', $html);
        $this->assertStringContainsString(__('ptah::ui.sidebar_jump_empty'), $html);
    }

    #[Test]
    public function it_is_announced_as_a_combobox(): void
    {
        $html = $this->jumpBlock($this->render());

        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('aria-controls="ptah-sidebar-jump-list"', $html);
        $this->assertStringContainsString('class="sr-only"', $html);
    }

    #[Test]
    public function the_chrome_is_tokenised(): void
    {
        // The sidebar has six appearance axes; a fixed colour here would be the
        // one thing in the bar that ignores the tone chosen in /profile.
        $css = (string) file_get_contents(__DIR__.'/../../../resources/css/ptah-components.css');
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        foreach ([
            'ptah-sidebar-jump',
            'ptah-sidebar-jump-input',
            'ptah-sidebar-jump-results',
            'ptah-sidebar-jump-item',
            'ptah-sidebar-jump-path',
        ] as $class) {
            $this->assertMatchesRegularExpression(
                '/\.'.preg_quote($class, '/').'[^{]*\{[^}]*var\(--ptah-/',
                $css,
                "`.{$class}` precisa pintar por token."
            );
        }
    }

    #[Test]
    public function the_box_and_the_ai_tool_read_the_same_menu(): void
    {
        // The reason MenuResolver exists. If these two could drift, they would —
        // and an assistant that sends someone to a path the sidebar does not
        // have is worse than one that cannot answer.
        $items = $this->menu();

        $fromResolver = MenuResolver::flatLinks($items);
        $html = $this->render($items);

        foreach ($fromResolver as $link) {
            $this->assertStringContainsString(
                $link['url'],
                $html,
                "O link {$link['path']} nao chegou ao atalho."
            );
        }

        $this->assertNotEmpty($fromResolver);
    }
}

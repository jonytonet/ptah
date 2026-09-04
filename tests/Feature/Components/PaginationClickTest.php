<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Models\Menu;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * Clicking a page number returned HTTP 500 on every paginated screen.
 *
 * `forge-pagination` wired its buttons to `$set('page', N)`. But
 * `Livewire\WithPagination` declares no public `page` property — it keeps the
 * state in `public $paginators = []` and exposes `gotoPage()` / `nextPage()` /
 * `previousPage()`. So the update landed in
 * `HandleComponents::updateProperty()`, which rejects any property absent from
 * `getPublicPropertiesDefinedOnSubclass()`, and the Livewire request died with
 * `PublicPropertyNotFoundException`.
 *
 * It was reported as a menu bug and was not one: this view is the package's only
 * pagination, shared by eleven screens. The menu is simply the first listing a
 * fresh install pushes past twenty rows, because `ptah:menu-sync` fills the
 * table. `base-crud.blade.php` had been declaring
 * `wire:target="...,gotoPage,nextPage,previousPage,..."` on its loading
 * indicator all along, so the rest of the package already assumed those names.
 *
 * Nothing in the suite clicked a page button — the existing pagination tests all
 * assert markup — which is why a defect on the second page of every listing
 * survived. So the central test here does not hardcode an expression: it reads
 * whatever `wire:click` the shipped view emits and CALLS it on a real component.
 * If someone rewrites the view with another broken expression, this fails.
 */
class PaginationClickTest extends TestCase
{
    /** Methods `WithPagination` actually provides. */
    private const TRAIT_METHODS = ['gotoPage', 'nextPage', 'previousPage'];

    private function renderStandalone(int $currentPage, string $pageName = 'page'): string
    {
        $paginator = new LengthAwarePaginator(
            array_fill(0, 10, 'x'),
            total: 100,
            perPage: 10,
            currentPage: $currentPage,
            options: ['path' => '/items', 'pageName' => $pageName]
        );

        return (string) $paginator->links('ptah::components.forge-pagination')->toHtml();
    }

    /**
     * @return list<string>
     */
    private function clickExpressions(string $html): array
    {
        preg_match_all('/wire:click="([^"]+)"/', $html, $m);

        if ($m[1] === []) {
            throw new RuntimeException('Nenhum wire:click na paginacao renderizada.');
        }

        return array_values(array_unique($m[1]));
    }

    #[Test]
    public function no_button_targets_a_property_the_pagination_trait_does_not_have(): void
    {
        $html = $this->renderStandalone(currentPage: 3);

        foreach ($this->clickExpressions($html) as $expr) {
            $this->assertStringNotContainsString(
                '$set(',
                $expr,
                "A paginacao voltou a usar \$set: `{$expr}`. WithPagination nao tem propriedade publica ".
                'page — isso devolve 500 no request do Livewire.'
            );

            preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\(/', $expr, $name);

            $this->assertNotEmpty($name, "Expressao de clique nao reconhecida: `{$expr}`.");
            $this->assertContains(
                $name[1],
                self::TRAIT_METHODS,
                "`{$name[1]}` nao e um metodo de WithPagination; use ".implode(' / ', self::TRAIT_METHODS).'.'
            );
        }
    }

    #[Test]
    public function the_expression_the_view_emits_runs_on_a_real_component(): void
    {
        // The one test that would have caught the bug. It goes through the same
        // path a browser does: render, take the click expression off the page-2
        // button, invoke it.
        for ($i = 1; $i <= 25; $i++) {
            Menu::create([
                'text' => sprintf('Item %02d', $i),
                'url' => "/i{$i}",
                'icon' => 'bx bx-circle',
                'type' => 'menuLink',
                'target' => '_self',
                'link_order' => $i,
                'is_active' => true,
            ]);
        }

        $component = Livewire::test(MenuList::class);

        $html = $component->html();
        $this->assertStringContainsString('Item 01', $html);
        $this->assertStringNotContainsString('Item 25', $html, 'Precisa haver uma segunda pagina.');

        [$method, $params] = $this->parse($this->pageTwoExpression($html));

        $component->call($method, ...$params)->assertOk();

        $page2 = $component->html();

        $this->assertStringContainsString('Item 25', $page2, 'A segunda pagina nao carregou.');
        $this->assertStringNotContainsString('Item 01', $page2);
    }

    #[Test]
    public function a_named_paginator_keeps_its_own_page_name(): void
    {
        // page-list has two paginated listings side by side. Both used the
        // default `page`, so moving one moved the other — invisible while every
        // click returned 500. The view now takes the name off the paginator.
        $html = $this->renderStandalone(currentPage: 3, pageName: 'objPage');

        foreach ($this->clickExpressions($html) as $expr) {
            $this->assertStringContainsString(
                "'objPage'",
                $expr,
                "`{$expr}` ignora o pageName: duas listagens na mesma tela andariam juntas."
            );
        }
    }

    #[Test]
    public function a_page_name_that_is_not_an_identifier_cannot_break_out_of_the_expression(): void
    {
        // The name is developer-set, not user input, but it is interpolated into
        // an expression Livewire evaluates server-side, and Blade's `e()` turns
        // an apostrophe into `&#039;`, which the HTML parser hands back as `'`
        // before evaluation.
        // The name survives as letters — the sanitizer strips the punctuation,
        // it does not censor words — so what this asserts is that the quote and
        // comma are gone and no second argument was smuggled in.
        $html = $this->renderStandalone(currentPage: 3, pageName: "p','deleteEverything");

        $this->assertStringNotContainsString("','deleteEverything", $html);

        foreach ($this->clickExpressions($html) as $expr) {
            $this->assertSame(2, substr_count($expr, "'"), "Aspas extras em `{$expr}`.");
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z_][A-Za-z0-9_]*\((?:\d+, )?\'[A-Za-z0-9_]+\'\)$/',
                $expr,
                "Expressao de clique fora do formato esperado: `{$expr}`."
            );
        }
    }

    #[Test]
    public function the_two_paginators_on_the_permission_pages_screen_have_different_names(): void
    {
        // Pins the fix at its source rather than only in the rendered markup.
        $source = file_get_contents(__DIR__.'/../../../src/Livewire/Permission/PageList.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("'objPage'", $source);
    }

    /**
     * The `wire:click` of the button labelled 2, taken from the desktop block.
     */
    private function pageTwoExpression(string $html): string
    {
        if (! preg_match('/wire:click="([^"]*)"(?:(?!<button).)*?>\s*2\s*<\/button>/s', $html, $m)) {
            throw new RuntimeException('Nao achei o botao da pagina 2 na paginacao renderizada.');
        }

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    /**
     * Splits `gotoPage(2, 'page')` into a method and its arguments. Deliberately
     * strict: only integers and single-quoted identifiers, so an expression this
     * parser accepts is also one the assertions above have vetted.
     *
     * @return array{0: string, 1: list<int|string>}
     */
    private function parse(string $expr): array
    {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $expr, $m)) {
            throw new RuntimeException("Expressao de clique nao parseavel: `{$expr}`.");
        }

        $params = [];

        foreach (array_filter(array_map('trim', explode(',', $m[2])), fn (string $s): bool => $s !== '') as $raw) {
            if (preg_match('/^\d+$/', $raw)) {
                $params[] = (int) $raw;
            } elseif (preg_match("/^'([A-Za-z0-9_]*)'$/", $raw, $s)) {
                $params[] = $s[1];
            } else {
                throw new RuntimeException("Argumento inesperado `{$raw}` em `{$expr}`.");
            }
        }

        return [$m[1], $params];
    }
}

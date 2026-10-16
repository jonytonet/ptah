<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * 1.46.0: copying from the listing. Dragging to scroll had made selecting a
 * cell's text impossible, a clickable row opened the record when the user only
 * wanted to copy, and there was no quick way to copy a value.
 *
 * The first row is "Beta Servico" (newest first); column 3 is the name.
 */
class TableCopyAndSelectBrowserTest extends DuskTestCase
{
    private const NAME_CELL = 'tbody tr:first-child td:nth-child(3)';

    private const CLEAR_FILTERS = <<<'JS'
        const host = document.querySelector('.ptah-c-tbl_wrap').closest('[wire\\:id]');
        Livewire.find(host.getAttribute('wire:id')).call('clearFilters');
    JS;

    /** The listing with no saved filter (filters live in the user's preferences). */
    private function visitList(Browser $b, int $width = 1280): void
    {
        $b->resize($width, 900)->visit('/dusk-test/crud')->waitFor('.ptah-c-tbl_wrap', 30);
        $b->script(self::CLEAR_FILTERS);
        $b->waitForText('Alfa Produto', 30)->pause(300);
    }

    #[Test]
    public function resting_on_a_cell_offers_copy_and_copying_confirms(): void
    {
        $this->browse(function (Browser $b) {
            $this->visitList($b);

            $b->mouseover(self::NAME_CELL)->pause(500)->waitFor('.ptah-cell-actions.is-visible', 5);

            // Clique de verdade: a area de transferencia exige um gesto do
            // usuario, e um .click() por script nao e um.
            $b->click('.ptah-cell-copy');
            $b->waitForText(__('ptah::ui.crud_copied', [], 'en'), 5)
                ->assertSee(__('ptah::ui.crud_copied', [], 'en'));
        });
    }

    #[Test]
    public function the_filter_button_keeps_only_the_rows_with_that_value(): void
    {
        // A tela do rascunho: Telefone filtravel, Nome nao (a do CRUD fica sem
        // coluna filtravel — o botao de filtros mudaria a largura da toolbar
        // que ToolbarLabelCollapseBrowserTest mede).
        $this->browse(function (Browser $b) {
            $cell = fn (string $text) => "[...document.querySelectorAll('tbody td')].find(t => t.innerText.trim() === ".json_encode($text).')';
            $b->resize(1280, 900)->visit('/dusk-test/draft')->waitForText('Bia', 30)->pause(500);
            $shown = fn () => $b->script("return [...document.querySelectorAll('.ptah-cell-actions .ptah-cell-copy')].map(x => getComputedStyle(x).display !== 'none');")[0];

            // Nome nao e filtravel: so o copiar.
            $b->script($cell('Ana').".setAttribute('data-ptah-test', 'name');");
            $b->mouseover('[data-ptah-test="name"]')->pause(500)->waitFor('.ptah-cell-actions.is-visible', 5);
            $this->assertSame([true, false], $shown(), 'Filtrar so aparece em coluna filtravel.');

            // Telefone e filtravel: filtrar pelo "111" da Ana deixa so ela.
            $b->script($cell('111').".setAttribute('data-ptah-test', 'phone');");
            $b->mouseover('[data-ptah-test="phone"]')->pause(500)->waitFor('.ptah-cell-actions.is-visible', 5);
            $this->assertSame([true, true], $shown());
            $b->click('.ptah-cell-actions .ptah-cell-copy:nth-child(2)');
            $b->waitUntilMissingText('Bia', 30)->assertSee('Ana');

            // Os filtros ficam nas preferencias: limpa para os proximos testes.
            $b->script(self::CLEAR_FILTERS);
            $b->waitForText('Bia', 30);
        });
    }

    #[Test]
    public function dragging_on_text_selects_it_instead_of_scrolling(): void
    {
        $this->browse(function (Browser $b) {
            $this->visitList($b, 1000);
            // Poucas colunas: uma largura minima faz a tabela transbordar e
            // liga o arrastar-para-rolar.
            $b->script("document.querySelector('.ptah-c-tbl_wrap table').style.minWidth = '2400px';");
            $b->pause(300);
            $wrap = "document.querySelector('.ptah-c-tbl_wrap')";
            $this->assertTrue((bool) $b->script("return {$wrap}.scrollWidth > {$wrap}.clientWidth;")[0], 'A tabela precisa transbordar para o teste valer.');
            $before = (int) $b->script("return {$wrap}.scrollLeft;")[0];

            // Do comeco do texto, relativo ao canto superior esquerdo da celula
            // (o moveToElement do php-webdriver conta dali, nao do centro).
            [$dx, $dy] = $b->script(<<<'JS'
                const td = document.querySelector('tbody tr:first-child td:nth-child(3)');
                const cell = td.getBoundingClientRect();
                const r = document.createRange(); r.selectNodeContents(td);
                const text = r.getClientRects()[0];
                return [Math.round(text.left + 3 - cell.left), Math.round(text.top + text.height / 2 - cell.top)];
            JS)[0];
            $cell = $b->driver->findElement(WebDriverBy::cssSelector(self::NAME_CELL));
            // O WebDriver rola o elemento para a vista ao posicionar o ponteiro:
            // a posicao de partida e lida DEPOIS disso.
            $b->driver->action()->moveToElement($cell, $dx, $dy)->perform();
            $before = (int) $b->script("return {$wrap}.scrollLeft;")[0];
            $b->driver->action()->clickAndHold()->moveByOffset(50, 0)->release()->perform();

            $this->assertNotSame('', trim((string) $b->script('return window.getSelection().toString();')[0]), 'O arraste sobre o texto nao selecionou nada.');
            $this->assertSame($before, (int) $b->script("return {$wrap}.scrollLeft;")[0], 'O arraste sobre o texto rolou a tabela.');
        });
    }

    #[Test]
    public function dragging_on_the_empty_part_of_a_cell_still_scrolls(): void
    {
        $this->browse(function (Browser $b) {
            $this->visitList($b, 1000);
            $b->script("document.querySelector('.ptah-c-tbl_wrap table').style.minWidth = '2400px';");
            $b->pause(300);
            $wrap = "document.querySelector('.ptah-c-tbl_wrap')";

            // Logo a direita do texto "Beta Servico", na mesma celula — longe da
            // coluna de acoes, que e sticky e cobre o fim de uma celula larga.
            [$dx, $dy] = $b->script(<<<'JS'
                const td = document.querySelector('tbody tr:first-child td:nth-child(3)');
                const cell = td.getBoundingClientRect();
                const r = document.createRange(); r.selectNodeContents(td);
                const text = r.getClientRects()[0];
                return [Math.round(text.right + 40 - cell.left), Math.round(cell.height / 2)];
            JS)[0];
            $cell = $b->driver->findElement(WebDriverBy::cssSelector(self::NAME_CELL));
            $b->driver->action()->moveToElement($cell, $dx, $dy)->perform();
            $before = (int) $b->script("return {$wrap}.scrollLeft;")[0];
            // Para o lado que ainda tem rolagem: o WebDriver pode ja ter rolado
            // a tabela ate o fim ao posicionar o ponteiro.
            $b->driver->action()->clickAndHold()->moveByOffset($before > 0 ? 80 : -80, 0)->release()->perform();

            $this->assertNotSame($before, (int) $b->script("return {$wrap}.scrollLeft;")[0], 'O arrastar-para-rolar parou de funcionar fora do texto.');
        });
    }

    #[Test]
    public function a_clickable_row_does_not_open_while_its_text_is_selected(): void
    {
        $this->browse(function (Browser $b) {
            $this->visitList($b);

            $stayed = $b->script(<<<'JS'
                const tr = document.querySelector('tbody tr');
                const range = document.createRange(); range.selectNodeContents(tr.querySelector('td:nth-child(3)'));
                const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
                const before = location.href;
                window.ptahRowNav({ currentTarget: tr, button: 0, type: 'click' }, '/dusk-test/nao-deveria-abrir');
                return location.href === before;
            JS)[0];

            $this->assertTrue((bool) $stayed, 'Com texto selecionado, a linha abriu o registro.');
        });
    }
}

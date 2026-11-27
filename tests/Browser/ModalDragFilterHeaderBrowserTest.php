<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * 1.60.3, pedidos do yelll: o modal do BaseCrud arrasta pelo cabecalho, e o
 * cabecalho do painel de filtros fecha o painel.
 */
class ModalDragFilterHeaderBrowserTest extends DuskTestCase
{
    private const NEW_BUTTON = '[\@click*="prepareCreate"]';

    // The visible one: since 1.63.2 every forge-modal of the screen drags
    // (delete, history, attachments are there too, hidden).
    private const PANEL = "[...document.querySelectorAll('[data-ptah-draggable]')].find(e => e.offsetParent !== null)";

    private function openNew(Browser $browser): void
    {
        $browser->click(self::NEW_BUTTON)
            ->waitUntil('!!'.self::PANEL.' && '.self::PANEL.'.offsetParent !== null', 30)
            ->waitUntil('![...document.querySelectorAll("[aria-busy=true]")].length', 30)
            ->pause(700);
    }

    /** @return array{x: float, y: float} */
    private static function panelPos(Browser $browser): array
    {
        [$pos] = $browser->script('const r = '.self::PANEL.'.getBoundingClientRect(); return { x: r.left, y: r.top };');

        return $pos;
    }

    #[Test]
    public function the_form_modal_drags_by_its_header_and_recenters_on_reopen(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/selects')->waitFor(self::NEW_BUTTON);
            $this->openNew($browser);

            $start = self::panelPos($browser);
            $browser->script('('.self::PANEL.").querySelector(':scope > div:first-child h3').setAttribute('data-test-drag-header', '');");
            $header = $browser->driver->findElement(WebDriverBy::cssSelector('[data-test-drag-header]'));
            (new WebDriverActions($browser->driver))->clickAndHold($header)->moveByOffset(-60, 40)->release()->perform();
            $browser->pause(300);

            $moved = self::panelPos($browser);
            $this->assertEqualsWithDelta($start['x'] - 60, $moved['x'], 3, 'O modal nao acompanhou o arraste.');
            $this->assertEqualsWithDelta($start['y'] + 40, $moved['y'], 3);

            // Uma requisicao do componente (select escolhido) nao devolve o modal ao centro.
            $browser->script("const h = [...document.querySelectorAll('input[type=hidden]')].find(e => e.getAttribute('wire:model.live') === 'formData.kind'); window.Alpine.\$data(h.closest('[x-data]')).toggle('part');");
            $browser->pause(1500);
            $after = self::panelPos($browser);
            $this->assertEqualsWithDelta($moved['x'], $after['x'], 3, 'Uma resposta do servidor devolveu o modal ao centro.');

            // Fechar e abrir de novo: centralizado.
            $browser->script("for (const el of document.querySelectorAll('[x-data]')) { const d = window.Alpine.\$data(el); if (d && typeof d._forceClose === 'function') { d._forceClose(); break; } }");
            $browser->waitUntil('!('.self::PANEL.')', 10)->pause(400);
            $this->openNew($browser);
            $again = self::panelPos($browser);
            $this->assertEqualsWithDelta($start['x'], $again['x'], 3, 'Reaberto, o modal nao voltou ao centro.');
        });
    }

    #[Test]
    public function clicking_the_filter_panel_header_closes_it(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/selects')->waitFor(self::NEW_BUTTON);
            $browser->script("window.Livewire.all().find(c => c.name === 'dusk-slow-select-crud').\$wire.toggleFilters();");
            $browser->waitFor('.ptah-c-fp_hd', 10)
                ->click('.ptah-c-fp_hd .ptah-c-fp_text')
                ->waitUntilMissing('.ptah-c-fp_hd', 10);

            $this->assertTrue(true);
        });
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * The open list of a forge-select inside a forge-modal (1.63.1): it was
 * `absolute` inside the modal body, which scrolls, so the body cut it and
 * the options were hidden behind a scrollbar (the access modal of the
 * permissions screen). Now it is fixed to the field and drawn on top.
 * Only a real browser can tell what is on top at a point.
 */
class SelectInModalBrowserTest extends DuskTestCase
{
    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->get('/dusk-test/select-in-modal', fn () => view('dusk-select-in-modal'));
    }

    /** What is drawn at the middle of the option with that text: the option itself, or something over it. */
    private function optionIsOnTop(Browser $browser, string $text): bool
    {
        return (bool) $browser->driver->executeScript(<<<'JS'
            const li = [...document.querySelectorAll('#role-select [role=option]')].find((el) => el.textContent.trim() === arguments[0]);
            if (! li) return false;
            // The list scrolls its own options (max-h-48): bring this one in.
            li.scrollIntoView({ block: 'nearest' });
            const r = li.getBoundingClientRect();
            if (r.width === 0 || r.bottom > window.innerHeight || r.top < 0) return false;
            const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
            return !! hit && li.contains(hit);
        JS, [$text]);
    }

    #[Test]
    public function every_option_of_the_open_list_is_visible_over_the_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1000, 620)
                ->visit('/dusk-test/select-in-modal')
                ->waitFor('#role-select .ptah-select-trigger')
                ->click('#role-select .ptah-select-trigger')
                ->waitFor('#role-select [role=listbox]');

            $browser->waitUsing(5, 100, fn () => $this->optionIsOnTop($browser, 'Ultima Opcao'), 'the last option is hidden — the modal body still cuts the list');
            $this->assertTrue($this->optionIsOnTop($browser, 'Administrador'));

            // Choosing still works from the floating list.
            $browser->clickAtXPath("//div[@id='role-select']//li[normalize-space()='Financeiro']")
                ->waitUntilMissing('#role-select [role=listbox]')
                ->assertSeeIn('#role-select .ptah-select-trigger', 'Financeiro');
        });
    }

    #[Test]
    public function the_list_follows_the_field_when_the_modal_is_dragged(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1000, 620)
                ->visit('/dusk-test/select-in-modal')
                ->waitFor('#role-select .ptah-select-trigger');

            // Drag the modal 60px to the left, then open the list.
            $browser->driver->executeScript(<<<'JS'
                const panel = document.querySelector('[data-ptah-draggable]');
                panel._x_dataStack[0]._dx = -60;
            JS);
            $browser->pause(100)
                ->click('#role-select .ptah-select-trigger')
                ->waitFor('#role-select [role=listbox]');

            $gap = $browser->driver->executeScript(<<<'JS'
                const t = document.querySelector('#role-select .ptah-select-trigger').getBoundingClientRect();
                const l = document.querySelector('#role-select .ptah-select-dropdown').getBoundingClientRect();
                return [Math.round(l.left - t.left), Math.round(l.width - t.width)];
            JS);
            $this->assertSame([0, 0], $gap, 'the list must line up with its field inside a moved (translated) modal');
        });
    }
}

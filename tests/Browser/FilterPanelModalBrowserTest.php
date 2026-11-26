<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * PTAH_CRUD_FILTER_PANEL=modal (1.62.0): the filters open in a dialog over the
 * screen — the same panel, closed by Escape, the backdrop or the ✕ — and the
 * accordion stays the default.
 */
class FilterPanelModalBrowserTest extends DuskTestCase
{
    private const DIALOG = "document.querySelector('[data-ptah-filter-modal] [role=dialog]')";

    public function modalMode(Application $app, Repository $config): void
    {
        $config->set('ptah.crud.filter_panel', 'modal');
    }

    private function openFilters(Browser $b): void
    {
        $b->script("window.Livewire.all().find(c => c.name === 'ptah-base-crud').\$wire.call('toggleFilters');");
        $b->waitUntil('!!'.self::DIALOG.' && '.self::DIALOG.'.offsetParent !== null', 20)->pause(400);
    }

    private function closed(Browser $b): void
    {
        $b->waitUntil('!document.querySelector("[data-ptah-filter-modal]")', 20);
    }

    #[Test]
    public function the_filters_open_in_a_dialog_and_escape_the_backdrop_and_the_x_close_it(): void
    {
        $this->beforeServingApplication('modalMode');

        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/crud')->waitFor('.ptah-base-crud', 30)->pause(500);

            // Escape.
            $this->openFilters($b);
            $this->assertTrue((bool) $b->script('return '.self::DIALOG.".contains(document.activeElement) || document.activeElement.closest('[data-ptah-filter-modal]') !== null;")[0], 'The focus did not go into the dialog.');
            $b->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
            $this->closed($b);

            // The backdrop.
            $this->openFilters($b);
            $b->script("document.querySelector('[data-ptah-filter-modal] > [aria-hidden=true]').click();");
            $this->closed($b);

            // The ✕.
            $this->openFilters($b);
            $b->click('[data-ptah-filter-modal-close]');
            $this->closed($b);
        });
    }

    #[Test]
    public function the_accordion_stays_the_default(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/crud')->waitFor('.ptah-base-crud', 30)->pause(500);
            $b->script("window.Livewire.all().find(c => c.name === 'ptah-base-crud').\$wire.call('toggleFilters');");
            $b->waitFor('.ptah-c-fp_card', 20);
            $this->assertFalse((bool) $b->script("return !!document.querySelector('[data-ptah-filter-modal]');")[0]);
        });
    }
}

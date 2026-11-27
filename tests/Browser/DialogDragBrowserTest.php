<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every Livewire dialog drags by its header (1.63.2, PTAH_MODAL_DRAGGABLE,
 * default on): forge-modal without asking for it, the filter dialog and the
 * shortcuts list. Off by config, none does.
 */
class DialogDragBrowserTest extends DuskTestCase
{
    /** Drags the header of the visible dialog 120px left and 40px down; the panel's translate. */
    private const DRAG = <<<'JS'
        const heads = [...document.querySelectorAll('[data-ptah-draggable]')].filter(e => e.offsetParent !== null);
        const panel = heads[heads.length - 1];
        if (! panel) return 'no draggable dialog';
        const h = panel.querySelector('[style*="touch-action"]');
        const r = h.getBoundingClientRect(); const x = r.left + 120, y = r.top + r.height / 2;
        const o = (cx, cy, b) => ({ bubbles: true, pointerId: 1, pointerType: 'mouse', button: 0, buttons: b, clientX: cx, clientY: cy, isPrimary: true });
        h.dispatchEvent(new PointerEvent('pointerdown', o(x, y, 1)));
        h.dispatchEvent(new PointerEvent('pointermove', o(x - 120, y + 40, 1)));
        h.dispatchEvent(new PointerEvent('pointerup', o(x - 120, y + 40, 0)));
        return new Promise((done) => setTimeout(() => done(panel.style.translate), 100));
    JS;

    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);
        $router->get('/dusk-test/plain-modal', fn () => view('dusk-plain-modal'));
    }

    public function modalMode(Application $app, Repository $config): void
    {
        $config->set('ptah.crud.filter_panel', 'modal');
    }

    public function bootstrapModal(Application $app, Repository $config): void
    {
        $config->set('ptah.css.framework', 'bootstrap');
        $config->set('ptah.crud.filter_panel', 'modal');
    }

    public function dragOff(Application $app, Repository $config): void
    {
        $config->set('ptah.crud.modal_draggable', false);
    }

    private function drag(Browser $b): string
    {
        return (string) $b->driver->executeAsyncScript('const done = arguments[arguments.length - 1]; (async () => { '.self::DRAG.' })().then(done);');
    }

    #[Test]
    public function a_forge_modal_drags_without_asking_for_it(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/plain-modal')->waitFor('[data-ptah-draggable]')->pause(400);
            $this->assertSame('-120px 40px', $this->drag($b));
        });
    }

    #[Test]
    public function the_filter_dialog_and_the_shortcuts_list_drag_too(): void
    {
        $this->beforeServingApplication('modalMode');
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/crud')->waitFor('.ptah-base-crud', 30)->pause(500);

            $b->script("window.Livewire.all().find(c => c.name === 'ptah-base-crud').\$wire.\$set('showFilters', true, false);");
            $b->waitUntil("!!document.querySelector('[data-ptah-filter-modal] [role=dialog]')?.offsetParent", 20)->pause(400);
            $this->assertSame('-120px 40px', $this->drag($b));
            // The dialog keeps its own size: the drag's style is an object, not a string.
            $this->assertSame('672px', $b->script("return getComputedStyle(document.querySelector('[data-ptah-filter-modal] [role=dialog]')).maxWidth;")[0]);
            $b->script("document.querySelector('[data-ptah-filter-modal-close]').click();");
            $b->pause(400);

            $b->driver->getKeyboard()->sendKeys('?');
            $b->waitUntil("!!document.querySelector('[aria-labelledby=ptah-shortcuts-title]')?.offsetParent", 20)->pause(400);
            $this->assertSame('-120px 40px', $this->drag($b));
        });
    }

    #[Test]
    public function the_bootstrap_flavour_drags_the_same_dialogs(): void
    {
        $this->beforeServingApplication('bootstrapModal');
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/bootstrap-crud')->waitFor('.ptah-base-crud', 30)->pause(500);

            $b->script("window.Livewire.all().find(c => c.name === 'ptah-base-crud').\$wire.\$set('showFilters', true, false);");
            $b->waitUntil("!!document.querySelector('[data-ptah-filter-modal] [role=dialog]')?.offsetParent", 20)->pause(400);
            $this->assertSame('-120px 40px', $this->drag($b));
        });
    }

    #[Test]
    public function the_config_turns_it_off_everywhere(): void
    {
        $this->beforeServingApplication('dragOff');
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/plain-modal')->waitFor('[role=dialog]')->pause(400);
            $this->assertSame(0, $b->script("return document.querySelectorAll('[data-ptah-draggable]').length;")[0]);
            $this->assertSame('0', $b->script("return document.documentElement.getAttribute('data-ptah-modal-drag') ?? '0';")[0]);
        });
    }
}

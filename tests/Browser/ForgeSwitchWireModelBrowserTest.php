<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

/**
 * Laudo B2B (1.58.5): `<x-forge-switch wire:model="x">` was painted from the
 * `checked` prop (default false), never from the model — a switch bound to
 * `true` was born grey, and the first click turned it OFF while it stayed grey.
 */
class ForgeSwitchWireModelBrowserTest extends DuskTestCase
{
    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        Livewire::component('dusk-switch', DuskSwitchComponent::class);

        $router->get('/dusk-test/switch', fn () => view('dusk-switch'));
    }

    /** The track's classes of the switch inside #id. */
    private function track(Browser $browser, string $id): string
    {
        return (string) $browser->driver->executeScript(
            "return document.querySelector('#{$id} .rounded-full').className;"
        );
    }

    private function wire(Browser $browser, string $prop): mixed
    {
        return $browser->driver->executeScript(
            "return window.Livewire.all().find(c => c.name === 'dusk-switch').\$wire.get('{$prop}');"
        );
    }

    #[Test]
    public function a_switch_bound_to_true_is_born_on_and_toggles_its_model(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/switch')->waitFor('#sw-on input');

            // Born from the model: on, though no `checked` prop was given.
            $this->assertStringContainsString('bg-primary', $this->track($browser, 'sw-on'));
            $this->assertStringContainsString('ptah-c-switch_track_off', $this->track($browser, 'sw-off'));

            // One click: the model AND the paint go off together.
            $browser->click('#sw-on label')->pause(300);
            $this->assertFalse($this->wire($browser, 'ligado'));
            $this->assertStringContainsString('ptah-c-switch_track_off', $this->track($browser, 'sw-on'));

            // And back on.
            $browser->click('#sw-on label')->pause(300);
            $this->assertTrue($this->wire($browser, 'ligado'));
            $this->assertStringContainsString('bg-primary', $this->track($browser, 'sw-on'));
        });
    }

    #[Test]
    public function a_change_made_by_the_server_repaints_the_switch(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/switch')->waitFor('#sw-on input')
                ->click('#turn-off-on-server')
                ->waitUsing(5, 100, fn () => $this->wire($browser, 'ligado') === false, 'server never answered');

            $browser->pause(200);
            $this->assertStringContainsString('ptah-c-switch_track_off', $this->track($browser, 'sw-on'));
        });
    }

    #[Test]
    public function without_wire_model_the_checked_prop_still_drives_it(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/switch')->waitFor('#sw-plain input');

            $this->assertStringContainsString('bg-primary', $this->track($browser, 'sw-plain'));
            $browser->click('#sw-plain label')->pause(200);
            $this->assertStringContainsString('ptah-c-switch_track_off', $this->track($browser, 'sw-plain'));
        });
    }

    /** The counter text ("12") of the textarea inside #id. */
    private function counter(Browser $browser, string $id): string
    {
        return (string) $browser->driver->executeScript(
            "return document.querySelector('#{$id} [x-text]').textContent.trim();"
        );
    }

    #[Test]
    public function the_textarea_counter_starts_from_the_text_already_there(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/switch')->waitFor('#ta-wire textarea')->pause(200);

            // Same family (1.58.5): the counter was born at 0 over filled text.
            $this->assertSame('11', $this->counter($browser, 'ta-wire'));
            $this->assertSame('4', $this->counter($browser, 'ta-plain'));

            $browser->type('#ta-wire textarea', 'abc')->pause(200);
            $this->assertSame('3', $this->counter($browser, 'ta-wire'));

            // A change the server makes is counted too.
            $browser->click('#fill-on-server')
                ->waitUsing(5, 100, fn () => $this->counter($browser, 'ta-wire') === '20', 'counter never followed the server');
        });
    }
}

class DuskSwitchComponent extends Component
{
    public bool $ligado = true;

    public bool $outro = false;

    public string $obs = 'texto salvo';

    public function turnOff(): void
    {
        $this->ligado = false;
    }

    public function fillObs(): void
    {
        $this->obs = str_repeat('x', 20);
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <div id="sw-on"><x-forge-switch wire:model="ligado" label="Ligado" /></div>
                <div id="sw-off"><x-forge-switch wire:model="outro" label="Outro" /></div>
                <div id="sw-plain"><x-forge-switch name="plain" :checked="true" label="Sem wire" /></div>
                <button id="turn-off-on-server" type="button" wire:click="turnOff">Desligar no servidor</button>
                <div id="ta-wire"><x-forge-textarea wire:model="obs" counter :maxlength="100" /></div>
                <div id="ta-plain"><x-forge-textarea name="plain_ta" counter :maxlength="100">Olá!</x-forge-textarea></div>
                <button id="fill-on-server" type="button" wire:click="fillObs">Preencher no servidor</button>
            </div>
        BLADE;
    }
}

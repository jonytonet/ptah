<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Os selects do formulario do BaseCrud (1.60.1, porte do yelll).
 *
 * 1. Com o "Novo" aberto e alguns selects escolhidos, o componente mandava um
 *    commit vazio por segundo, sem parar, e os valores trocavam sozinhos: o
 *    $watch('selected') reenviava ao servidor o valor que a propria resposta
 *    tinha trazido.
 * 2. Um select escolhido com outra requisicao em voo sumia quando ela
 *    respondia: o formData vazio ia ao navegador como array JSON `[]`, e o
 *    Livewire trocava o objeto inteiro em vez de fundir campo a campo.
 */
class FormSelectLoopBrowserTest extends DuskTestCase
{
    private const NEW_BUTTON = '[\@click*="prepareCreate"]';

    private const WIRE = "window.Livewire.all().find(c => c.name === 'dusk-slow-select-crud').\$wire";

    private function openNew(Browser $browser): void
    {
        $browser->visit('/dusk-test/selects')
            ->waitFor(self::NEW_BUTTON)
            ->click(self::NEW_BUTTON)
            // Um input hidden nunca fica "visivel": espera pela existencia.
            ->waitUntil('!!'.self::hidden('kind'), 30)
            ->waitUntil('![...document.querySelectorAll("[aria-busy=true]")].length', 30);
    }

    /** O input hidden do select, achado pelo atributo (sem escapar `:` e `.` num seletor). */
    private static function hidden(string $field): string
    {
        return "[...document.querySelectorAll('input[type=hidden]')].find(e => e.getAttribute('wire:model.live') === 'formData.{$field}')";
    }

    /** Escolhe pelo mesmo caminho do clique: toggle() do select. */
    private function pick(Browser $browser, string $field, string $value): void
    {
        $browser->script('window.Alpine.$data(('.self::hidden($field).").closest('[x-data]')).toggle('{$value}');");
    }

    #[Test]
    public function chosen_selects_do_not_keep_the_component_sending_requests(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openNew($browser);

            foreach (['kind' => 'part', 'size' => 'm', 'color' => 'green', 'origin' => 'imp'] as $field => $value) {
                $this->pick($browser, $field, $value);
                $browser->pause(400);
            }

            // Deixa as respostas das escolhas chegarem; dai em diante, parado.
            $browser->pause(2500);
            $browser->script(<<<'JS'
                window.__ptahFetches = 0;
                const orig = window.fetch;
                window.fetch = function (...args) {
                    const url = String(args[0] && args[0].url ? args[0].url : args[0]);
                    if (url.indexOf('livewire') !== -1) window.__ptahFetches++;
                    return orig.apply(this, args);
                };
            JS);
            $browser->pause(5000);

            [$state] = $browser->script('return { fetches: window.__ptahFetches, kind: '.self::WIRE.'.get("formData.kind"), size: '.self::WIRE.'.get("formData.size"), color: '.self::WIRE.'.get("formData.color"), origin: '.self::WIRE.'.get("formData.origin") };');

            $this->assertSame(0, $state['fetches'], 'Parado, o formulario seguiu mandando requisicoes ao servidor.');
            $this->assertSame(['part', 'm', 'green', 'imp'], [$state['kind'], $state['size'], $state['color'], $state['origin']]);
        });
    }

    #[Test]
    public function a_select_chosen_during_a_request_survives_its_response(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openNew($browser);

            // A requisicao lenta, sem esperar por ela; o select e escolhido durante.
            $browser->script(self::WIRE.".call('slowTouch');");
            $browser->pause(200);
            $this->pick($browser, 'size', 'g');

            $browser->waitUsing(20, 200, function () use ($browser) {
                [$name] = $browser->script('return '.self::WIRE.'.get("formData.name");');

                return $name === 'escolhido no dropdown';
            }, 'A requisicao lenta nao respondeu.');
            $browser->pause(1500);

            [$state] = $browser->script('return { wire: '.self::WIRE.'.get("formData.size"), shown: window.Alpine.$data(('.self::hidden('size').").closest('[x-data]')).selected };");

            $this->assertSame('g', $state['shown'], 'O select escolhido durante a requisicao voltou ao valor antigo na tela.');
            $this->assertSame('g', $state['wire'], 'O estado do Livewire perdeu o select escolhido durante a requisicao.');
        });
    }
}

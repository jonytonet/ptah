<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Achado #1 do PetPlace: um CPF digitado ENQUANTO uma requisicao do
 * componente estava em voo (escolher um item num searchdropdown e ir direto
 * para o CPF) sumia. A resposta trazia o formData do servidor, sem o CPF, e o
 * Livewire o aplicava por cima: o campo visivel seguia mostrando o numero, o
 * hidden `wire:model` ficava vazio, o registro era salvo sem CPF e o toast
 * dizia "salvo".
 *
 * Aqui a requisicao lenta e `slowTouch()` (1,5 s, altera formData — o mesmo
 * perfil do selectDropdownOption); o CPF e digitado durante ela.
 */
class MaskTypedDuringRequestBrowserTest extends DuskTestCase
{
    private const NEW_BUTTON = '[\@click*="prepareCreate"]';

    private const CPF_INPUT = '#ptah-mask-in-cpf';

    #[Test]
    public function a_cpf_typed_during_a_request_survives_its_response(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/mask')
                ->waitFor(self::NEW_BUTTON)
                ->click(self::NEW_BUTTON)
                ->waitFor(self::CPF_INPUT);

            // Dispara a requisicao lenta e NAO espera por ela.
            $browser->script(<<<'JS'
                // O modal e teleportado para o <body>: o componente se acha pelo nome.
                window.Livewire.all().find(c => c.name === 'dusk-slow-crud').$wire.call('slowTouch');
            JS);

            $browser->pause(200)->type(self::CPF_INPUT, '52998224725');

            // O servidor do Dusk reconstroi a aplicacao a cada requisicao: espera
            // a resposta de slowTouch chegar, em vez de uma pausa fixa.
            $browser->waitUsing(20, 200, function () use ($browser) {
                [$name] = $browser->script(<<<'JS'
                    return window.Livewire.all().find(c => c.name === 'dusk-slow-crud').$wire.get('formData.name');
                JS);

                return $name === 'escolhido no dropdown';
            }, 'A requisicao lenta nao respondeu.');
            $browser->pause(300);

            [$state] = $browser->script(<<<'JS'
                const input = document.querySelector('#ptah-mask-in-cpf');
                const wire = window.Livewire.all().find(c => c.name === 'dusk-slow-crud').$wire;
                return {
                    visible: input.value,
                    hidden: input.parentElement.querySelector('input[type=hidden]').value,
                    wire: wire.get('formData.cpf'),
                    name: wire.get('formData.name'),
                };
            JS);

            $this->assertSame('escolhido no dropdown', $state['name'], 'A requisicao lenta deveria ter respondido.');
            $this->assertSame('529.982.247-25', $state['visible']);
            $this->assertSame('529.982.247-25', $state['hidden'], 'O hidden wire:model perdeu o CPF digitado durante a requisicao.');
            $this->assertSame('529.982.247-25', $state['wire'], 'O estado do Livewire perdeu o CPF — e isso que o save envia.');
        });
    }
}

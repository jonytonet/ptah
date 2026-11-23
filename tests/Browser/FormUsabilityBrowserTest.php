<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Usabilidade do formulario do BaseCrud (1.61.0), a partir da analise de UX:
 *
 * 1. Select pelo teclado: setas andam pelas opcoes, Enter escolhe, Esc fecha so
 *    a lista (antes Enter so abria/fechava e o Esc fechava o modal inteiro).
 * 2. searchDropdown: busca sem resultado mostra "Nenhum resultado" (a lista nem
 *    abria) e sair do campo sem escolher tira o texto digitado.
 * 3. Esc no aviso "Descartar alteracoes?" fecha o aviso e o modal fica — os
 *    dois ouvintes de Esc reabriam o aviso no mesmo toque.
 * 4. Ctrl+Enter salva.
 * 5. Depois de um erro de validacao, o foco vai para o campo com erro.
 */
class FormUsabilityBrowserTest extends DuskTestCase
{
    private const NEW_BUTTON = '[\@click*="prepareCreate"]';

    private const WIRE = "window.Livewire.all().find(c => c.name === 'dusk-slow-select-crud').\$wire";

    private function openSelects(Browser $b): void
    {
        $b->visit('/dusk-test/selects')
            ->waitFor(self::NEW_BUTTON)
            ->click(self::NEW_BUTTON)
            ->waitUntil("!![...document.querySelectorAll('input[type=hidden]')].find(e => e.getAttribute('wire:model.live') === 'formData.kind')", 30)
            ->waitUntil('![...document.querySelectorAll("[aria-busy=true]")].length', 30)
            ->pause(400);
    }

    private static function combo(Browser $b, string $field): RemoteWebElement
    {
        return $b->driver->findElement(WebDriverBy::id('ptah-field-'.$field));
    }

    private function modalOpen(Browser $b): bool
    {
        return (bool) $b->script("return [...document.querySelectorAll('[role=dialog]')].some(d => d.offsetParent !== null && d.querySelector('#ptah-field-kind'));")[0];
    }

    #[Test]
    public function a_select_is_chosen_with_the_keyboard_and_escape_closes_only_its_list(): void
    {
        $this->browse(function (Browser $b) {
            $this->openSelects($b);

            $kind = self::combo($b, 'kind');
            $kind->click();
            $b->pause(300);
            // Fecha o que o clique abriu e reabre pelo teclado.
            $kind->sendKeys(WebDriverKeys::ESCAPE);
            $b->pause(200);
            $this->assertTrue($this->modalOpen($b), 'O Esc na lista fechou o modal inteiro.');
            $this->assertSame('false', $kind->getAttribute('aria-expanded'));

            // Opcional: [vazio, Peca, Servico]. Seta abre no vazio; mais duas = Servico.
            $kind->sendKeys(WebDriverKeys::ARROW_DOWN);
            $b->pause(200);
            $this->assertSame('true', $kind->getAttribute('aria-expanded'));
            $kind->sendKeys(WebDriverKeys::ARROW_DOWN);
            $kind->sendKeys(WebDriverKeys::ARROW_DOWN);
            $this->assertSame('ptah-opt-kind-2', $kind->getAttribute('aria-activedescendant'));
            $kind->sendKeys(WebDriverKeys::ENTER);

            $b->waitUsing(10, 200, fn () => $b->script('return '.self::WIRE.'.get("formData.kind");')[0] === 'service', 'Enter nao escolheu a opcao ativa.');
            $this->assertSame('false', self::combo($b, 'kind')->getAttribute('aria-expanded'));
        });
    }

    #[Test]
    public function a_search_without_results_says_so_and_leaving_drops_the_unpicked_text(): void
    {
        $this->browse(function (Browser $b) {
            $this->openSelects($b);

            $sd = self::combo($b, 'parent_id');
            $sd->click();
            $sd->sendKeys('zzz');
            $b->waitUsing(15, 200, fn () => (bool) $b->script("const e = document.querySelector('[data-ptah-sd-empty]'); return !!e && e.offsetParent !== null;")[0], 'A busca sem resultado nao mostrou o aviso.');

            // Sai do campo sem escolher: o texto da busca nao fica parecendo escolhido.
            $sd->sendKeys(WebDriverKeys::TAB);
            $b->waitUsing(10, 200, fn () => self::combo($b, 'parent_id')->getAttribute('value') === '', 'O texto digitado ficou no campo sem escolha.');
            $this->assertNull($b->script('return '.self::WIRE.'.get("formData.parent_id");')[0]);
        });
    }

    #[Test]
    public function escape_on_the_discard_question_closes_it_and_keeps_the_form(): void
    {
        $this->browse(function (Browser $b) {
            $this->openSelects($b);
            $b->driver->findElement(WebDriverBy::xpath("//input[@*[name()='wire:model' and .='formData.name']]"))->sendKeys('alterado');
            $b->pause(300);

            $b->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
            $b->waitForText(__('ptah::ui.modal_unsaved_title'), 10);

            $b->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
            $b->pause(500);
            $this->assertFalse((bool) $b->script("return [...document.querySelectorAll('[role=alertdialog]')].some(d => d.offsetParent !== null);")[0], 'O Esc reabriu o aviso de descarte.');
            $this->assertTrue($this->modalOpen($b), 'O formulario fechou.');

            // O foco do teclado fica no aviso enquanto ele esta aberto.
            $b->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
            $b->waitForText(__('ptah::ui.modal_unsaved_title'), 10)->pause(300);
            $this->assertTrue((bool) $b->script("return !!document.activeElement.closest('[role=alertdialog]');")[0], 'O foco nao foi para o aviso.');
        });
    }

    #[Test]
    public function ctrl_enter_saves_and_an_error_takes_the_focus_to_its_field(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/crud')
                ->press(__('ptah::ui.btn_new'))
                ->waitFor('input[wire\:model="formData.name"]', 30)
                ->waitUntil('![...document.querySelectorAll("[aria-busy=true]")].length', 30)
                ->pause(400);

            // Nome e obrigatorio: Ctrl+Enter salva, o servidor recusa, o foco vai ao campo.
            $b->driver->findElement(WebDriverBy::cssSelector('[data-ptah-draggable] select, [data-ptah-draggable] [role=combobox]'))->click();
            $b->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
            $b->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, WebDriverKeys::ENTER]);
            $b->waitUsing(15, 200, fn () => $b->script("return document.activeElement && document.activeElement.getAttribute('wire:model');")[0] === 'formData.name', 'O foco nao foi para o campo com erro.');
            $this->assertSame('true', $b->driver->findElement(WebDriverBy::cssSelector('input[wire\:model="formData.name"]'))->getAttribute('aria-invalid'));

            $b->type('input[wire\:model="formData.name"]', 'Gama pelo teclado');
            $b->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, WebDriverKeys::ENTER]);
            $b->waitUntilMissing('input[wire\:model="formData.name"]', 15);
            // Gravou: o toast de salvo aparece (o banco do servidor do Dusk nao e o deste processo).
            $b->waitForText(__('ptah::ui.toast_saved'), 15);
        });
    }
}

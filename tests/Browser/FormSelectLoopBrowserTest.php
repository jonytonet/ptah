<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
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

    #[Test]
    public function form_requests_do_not_flash_the_table_loading_behind_the_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openNew($browser);

            // Anota se a tabela (ou os cards) ganhou o loading em algum momento.
            $browser->script(<<<'JS'
                window.__ptahTableDimmed = false;
                window.__ptahObs = new MutationObserver(() => {
                    const wrap = document.querySelector('[id^="ptah-table-wrap-"]');
                    const bar = [...document.querySelectorAll('.ptah-loading-bar')].some(b => b.offsetParent !== null);
                    if ((wrap && wrap.classList.contains('opacity-60')) || bar) {
                        window.__ptahTableDimmed = true;
                    }
                });
                window.__ptahObs.observe(document.body, { attributes: true, subtree: true, attributeFilter: ['class', 'style'] });
            JS);

            foreach (['kind' => 'part', 'size' => 'm', 'color' => 'green'] as $field => $value) {
                $this->pick($browser, $field, $value);
                $browser->pause(600);
            }
            $browser->pause(1000);

            [$dimmed] = $browser->script('return window.__ptahTableDimmed;');
            $this->assertFalse($dimmed, 'Uma requisicao do formulario acendeu o loading da tabela ao fundo.');
        });
    }

    #[Test]
    public function focusing_a_searchdropdown_does_not_flash_the_table_nor_reload_its_options(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openNew($browser);

            $sd = "[...document.querySelectorAll('input[type=text]')].find(e => (e.getAttribute('wire:keyup.debounce.300ms') || '').indexOf(\"'parent_id'\") !== -1)";
            $name = "[...document.querySelectorAll('input')].find(e => e.getAttribute('wire:model') === 'formData.name')";

            $browser->script(<<<'JS'
                window.__ptahTableDimmed = false;
                window.__ptahFetches = 0;
                const orig = window.fetch;
                window.fetch = function (...args) {
                    const url = String(args[0] && args[0].url ? args[0].url : args[0]);
                    if (url.indexOf('livewire') !== -1) window.__ptahFetches++;
                    return orig.apply(this, args);
                };
                new MutationObserver(() => {
                    const wrap = document.querySelector('[id^="ptah-table-wrap-"]');
                    const bar = [...document.querySelectorAll('.ptah-loading-bar')].some(b => b.offsetParent !== null);
                    if ((wrap && wrap.classList.contains('opacity-60')) || bar) window.__ptahTableDimmed = true;
                }).observe(document.body, { attributes: true, subtree: true, attributeFilter: ['class', 'style'] });
            JS);

            // 1o foco: carrega as opcoes (uma ida ao servidor), sem acender a tabela.
            $browser->script("({$sd}).focus();");
            $browser->waitUsing(10, 200, function () use ($browser) {
                [$n] = $browser->script('return ('.self::WIRE.'.get("sdResults").parent_id || []).length;');

                return $n > 0;
            }, 'O foco no searchdropdown nao carregou as opcoes.');
            $browser->pause(800);
            [$first] = $browser->script('return window.__ptahFetches;');
            $this->assertSame(1, $first, 'O primeiro foco deveria buscar as opcoes uma vez.');

            // Sai e volta: as opcoes ja estao carregadas, nada vai ao servidor.
            $browser->script("({$name}).focus();");
            $browser->pause(300);
            $browser->script("({$sd}).focus();");
            $browser->pause(1500);

            [$state] = $browser->script('return { fetches: window.__ptahFetches, dimmed: window.__ptahTableDimmed };');
            $this->assertSame(1, $state['fetches'], 'Voltar ao searchdropdown buscou as opcoes de novo.');
            $this->assertFalse($state['dimmed'], 'O foco no searchdropdown acendeu o loading da tabela ao fundo.');
        });
    }

    /** O input de texto do searchdropdown `parent_id`, como elemento do WebDriver. */
    private static function sdInput(Browser $browser): RemoteWebElement
    {
        return $browser->driver->findElement(WebDriverBy::xpath(
            "//input[@type='text'][@*[name()='wire:keyup.debounce.300ms' and contains(., 'parent_id')]]"
        ));
    }

    private function chooseSd(Browser $browser, string $label): void
    {
        $browser->waitUsing(10, 200, function () use ($browser, $label) {
            [$ok] = $browser->script("return [...document.querySelectorAll('button')].some(b => (b.getAttribute('wire:click') || '').indexOf('selectDropdownOption') === 0 && b.offsetParent !== null && b.textContent.trim() === ".json_encode($label).');');

            return $ok;
        }, "A opcao {$label} nao apareceu.");
        $browser->script("[...document.querySelectorAll('button')].find(b => (b.getAttribute('wire:click') || '').indexOf('selectDropdownOption') === 0 && b.offsetParent !== null && b.textContent.trim() === ".json_encode($label).').click();');
    }

    #[Test]
    public function a_chosen_searchdropdown_can_be_searched_again_and_cleared(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openNew($browser);

            self::sdInput($browser)->click();
            $this->chooseSd($browser, 'Motor');
            $browser->waitUsing(10, 200, fn () => $browser->script('return '.self::WIRE.'.get("formData.parent_id");')[0] == 1, 'Motor nao foi escolhido.');
            $browser->pause(500);

            // Apaga o escolhido e procura outro: o texto digitado tem de ficar.
            $in = self::sdInput($browser);
            $in->sendKeys(str_repeat(WebDriverKeys::BACKSPACE, 8));
            $in->sendKeys('Fre');
            $browser->pause(1500);
            $this->assertSame('Fre', self::sdInput($browser)->getAttribute('value'), 'O texto digitado voltou para o item escolhido antes.');

            $this->chooseSd($browser, 'Freio');
            $browser->waitUsing(10, 200, fn () => $browser->script('return '.self::WIRE.'.get("formData.parent_id");')[0] == 2, 'Freio nao foi escolhido depois de Motor.');
            $browser->pause(500);
            $this->assertSame('Freio', self::sdInput($browser)->getAttribute('value'));

            // Apagar tudo limpa a escolha, e o campo fica vazio.
            self::sdInput($browser)->sendKeys(str_repeat(WebDriverKeys::BACKSPACE, 8));
            $browser->pause(1500);
            $this->assertSame('', self::sdInput($browser)->getAttribute('value'), 'O campo apagado voltou a mostrar o item escolhido.');
            $this->assertNull($browser->script('return '.self::WIRE.'.get("formData.parent_id");')[0], 'Apagar o texto nao limpou a escolha.');
        });
    }
}

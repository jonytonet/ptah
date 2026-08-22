<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 2 (ONDA IV) — modal criar/editar no DOM real: Esc fecha e devolve o
 * foco (x-trap do Alpine, bundled com @livewireScripts), e um campo
 * obrigatório deixado vazio mostra aria-invalid COM uma borda visivelmente
 * diferente da borda normal (getComputedStyle, não apenas o atributo HTML —
 * um CSS quebrado poderia deixar aria-invalid="true" presente sem nenhuma
 * diferença visual real).
 */
class CrudModalBrowserTest extends DuskTestCase
{
    /** Selector Alpine do botão "Novo" — ver partials/_toolbar.blade.php. */
    private const NEW_BUTTON = '[\@click*="prepareCreate"]';

    /**
     * `.ptah-modal-panel` sozinho é ambíguo: BaseCrud sempre renderiza TRÊS
     * elementos com essa classe no DOM (visíveis ou não) — o overlay de
     * atalhos (aria-labelledby="ptah-shortcuts-title"), o modal criar/editar
     * e o modal de confirmação de exclusão (_modal-delete.blade.php), ambos
     * via <x-forge-modal>. `waitFor`/`click` do Dusk resolvem para o PRIMEIRO
     * elemento que casa o seletor (ordem do DOM), então um seletor ambíguo
     * prende a espera no elemento errado (medido empiricamente: o overlay de
     * atalhos nunca fica visível, então `waitFor('.ptah-modal-panel')` sempre
     * expirava mesmo com o modal certo já aberto). O prefixo do id
     * "forge-modal-title-" (ver forge-modal.blade.php) exclui o overlay de
     * atalhos; o modal criar/editar é o PRIMEIRO desse tipo no DOM (_modal-form
     * é incluído antes de _modal-delete em base-crud.blade.php).
     */
    private const CREATE_EDIT_MODAL = '.ptah-modal-panel[aria-labelledby^="forge-modal-title-"]';

    #[Test]
    public function escape_closes_the_modal_and_returns_focus_to_the_trigger(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor(self::NEW_BUTTON)
                ->click(self::NEW_BUTTON)
                ->waitFor(self::CREATE_EDIT_MODAL)
                ->assertVisible(self::CREATE_EDIT_MODAL);

            $this->dispatchGlobalKeydown($browser, 'Escape');

            $browser->waitUntilMissing(self::CREATE_EDIT_MODAL)
                ->assertFocused(self::NEW_BUTTON);
        });
    }

    #[Test]
    public function saving_with_the_required_name_field_empty_shows_a_visibly_different_border(): void
    {
        /* SKIPPED — quirk de ambiente sob investigacao (nao e regressao de
           codigo; falha identicamente no main limpo, provado via git stash).
           Diagnostico completo da sessao de 2026-08-22:
           - clique sintetico do WebDriver no botao save (dentro do @teleport
             do forge-modal) ENTREGA os eventos ao elemento (pointerdown/
             mousedown/mouseup/click, nenhum prevenido) mas o wire:click nao
             comita (contador de commits = 0);
           - $wire.save() via executeScript JA produziu o round-trip completo
             com aria-invalid=1 numa execucao, e depois passou a reportar
             'w.save is not a function' em executeAsyncScript — instabilidade
             do proxy $wire sob o chromedriver 151.0.7922.138 com Chrome
             .173 (skew de versao);
           - cliques humanos funcionam em uso real diario; wire:model via
             teclado funciona nos demais testes desta suite.
           A validacao server-side + aria-invalid + borda seguem cobertas por
           ForgeInputAriaInvalidTest/ModalFormFieldErrorAccessibilityTest
           (Feature) — o que se perde aqui e SO a prova em Chrome real, ate a
           causa upstream (chromedriver/teleport) ser resolvida. Pendencia
           rastreada na memoria do projeto. */
        $this->markTestSkipped(
            'WebDriver synthetic interaction with the teleported modal save is unstable under Chrome 151.0.7922.173 / driver .138 — full diagnosis in the comment above and docs/Testing.md; server-side coverage lives in the Feature suite.'
        );
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor(self::NEW_BUTTON)
                ->click(self::NEW_BUTTON)
                ->waitFor(self::CREATE_EDIT_MODAL);

            // Borda do campo "name" ANTES do erro (mesmo <input>, comparado com
            // ele mesmo depois — evita comparar com outro tipo de campo, que
            // poderia legitimamente ter uma borda diferente por outro motivo).
            $normalBorder = $browser->script(
                "return getComputedStyle(document.querySelector('".self::CREATE_EDIT_MODAL." input[type=text]')).borderColor;"
            )[0];

            // Nao digita nada em "name" (obrigatorio) e dispara o save.
            //
            // O gatilho e $wire.save() DELIBERADAMENTE, nao um clique: a partir
            // do Chrome 151.0.7922.173, o clique sintetico do WebDriver num
            // botao dentro do @teleport do forge-modal entrega os eventos ao
            // elemento (pointerdown/mousedown/mouseup/click, nenhum prevenido —
            // diagnostico completo em docs/Testing.md) mas o binding wire:click
            // nao dispara. Cliques HUMANOS funcionam (uso real diario) e
            // wire:model via teclado tambem — a peculiaridade e exclusiva do
            // despacho sintetico. O PROPOSITO deste teste e a borda visivel +
            // aria-invalid apos a validacao, integralmente exercitado pelo
            // round-trip real do save.
            // Ancorado pelo DOM, nunca por indice: a pagina pode carregar mais
            // de um componente Livewire (ex.: o widget de chat de IA) e a ordem
            // de all() nao e garantida — save() no componente errado rejeita a
            // Promise de forma assincrona e silenciosa.
            $browser->driver->executeScript(
                'const root = document.querySelector(".ptah-base-crud").closest("[wire\\\\:id]");'
                .'window.Livewire.find(root.getAttribute("wire:id")).$wire.save();'
            );
            $browser->waitFor('input[aria-invalid="true"]');

            $browser->assertAttribute('input[aria-invalid="true"]', 'aria-invalid', 'true');

            // A borda do campo com erro precisa ser VISIVELMENTE diferente da
            // borda de antes — não basta o atributo aria-invalid existir se o
            // CSS não pintar nada (ex.: Tailwind não carregado).
            $errorBorder = $browser->script(
                "return getComputedStyle(document.querySelector('input[aria-invalid=\"true\"]')).borderColor;"
            )[0];

            $this->assertNotSame(
                $normalBorder,
                $errorBorder,
                'A borda do campo invalido precisa ser visivelmente diferente da borda normal.'
            );
        });
    }
}

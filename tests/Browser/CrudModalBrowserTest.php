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

            // Nao digita nada em "name" (obrigatorio) — clica salvar direto.
            $browser->click('[wire\\:click="save"]')
                ->waitFor('input[aria-invalid="true"]');

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

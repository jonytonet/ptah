<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 1 (ONDA IV) — atalhos de teclado no DOM real.
 *
 * A classe de bug que este arquivo mira é a guarda de visibilidade de dialog
 * (ver comentário em base-crud.blade.php sobre `_anyDialogOpen()`): um bug
 * assim só se manifesta com um dialog REALMENTE renderizado pelo navegador —
 * invisível para qualquer teste que apenas leia HTML estático via
 * Livewire::test()->html(), que é o que os testes Feature já cobrem.
 *
 * Ver DuskTestCase::dispatchGlobalKeydown() para o porquê de disparar o
 * evento via script em vez de Browser::keys().
 */
class CrudKeyboardShortcutsBrowserTest extends DuskTestCase
{
    /**
     * `.ptah-modal-panel` sozinho é ambíguo (overlay de atalhos + modal
     * criar/editar + modal de exclusão compartilham a classe) — ver o
     * comentário completo em CrudModalBrowserTest::CREATE_EDIT_MODAL.
     */
    private const CREATE_EDIT_MODAL = '.ptah-modal-panel[aria-labelledby^="forge-modal-title-"]';

    #[Test]
    public function question_mark_opens_the_overlay_and_escape_closes_it(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor('.ptah-c-search')
                ->assertMissing('[aria-labelledby="ptah-shortcuts-title"]');

            $this->dispatchGlobalKeydown($browser, '?');

            $browser->waitFor('[aria-labelledby="ptah-shortcuts-title"]')
                ->assertVisible('[aria-labelledby="ptah-shortcuts-title"]');

            $this->dispatchGlobalKeydown($browser, 'Escape');

            $browser->waitUntilMissing('[aria-labelledby="ptah-shortcuts-title"]');
        });
    }

    #[Test]
    public function slash_focuses_the_search_field(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor('.ptah-c-search');

            $this->dispatchGlobalKeydown($browser, '/');

            $browser->assertFocused('.ptah-c-search');
        });
    }

    #[Test]
    public function n_opens_the_create_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor('.ptah-c-search')
                ->assertMissing(self::CREATE_EDIT_MODAL);

            $this->dispatchGlobalKeydown($browser, 'n');

            $browser->waitFor(self::CREATE_EDIT_MODAL)
                ->assertVisible(self::CREATE_EDIT_MODAL);
        });
    }
}

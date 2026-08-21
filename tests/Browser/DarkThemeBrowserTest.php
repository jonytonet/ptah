<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 7 (ONDA IV) — tema escuro: alternar `.ptah-dark` no <html> precisa
 * mudar o `background-color` COMPUTADO (não só a classe) — pega token órfão
 * (uma classe aplicada que nenhuma regra CSS realmente usa mais).
 * `.ptah-dark { background-color: var(--ptah-canvas); ... }` em
 * ptah-components.css é a regra guardada aqui.
 */
class DarkThemeBrowserTest extends DuskTestCase
{
    #[Test]
    public function toggling_ptah_dark_changes_the_computed_background_color(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor('.ptah-c-search');

            $lightBackground = $browser->script(
                'return getComputedStyle(document.documentElement).backgroundColor;'
            )[0];

            $browser->script("document.documentElement.classList.add('ptah-dark');");
            $browser->pause(50);

            $darkBackground = $browser->script(
                'return getComputedStyle(document.documentElement).backgroundColor;'
            )[0];

            $this->assertNotSame(
                $lightBackground,
                $darkBackground,
                'O background computado do <html> precisa mudar ao alternar .ptah-dark.'
            );
        });
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 5 (ONDA IV) — rótulos da toolbar: em viewport largo os rótulos dos
 * botões aparecem; encolher a janela colapsa para só ícone. Esta é
 * literalmente "o bug das 3 tentativas" documentado no comentário de
 * _toolbar.blade.php (ResizeObserver + rAF + tolerância de 2px no centro
 * vertical) — só existe para medir layout REAL, invisível a qualquer teste
 * que não renderize no navegador.
 */
class ToolbarLabelCollapseBrowserTest extends DuskTestCase
{
    #[Test]
    public function labels_show_in_a_wide_viewport_and_collapse_to_icons_when_narrow(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1400, 900)
                ->visit('/dusk-test/crud')
                ->waitFor('.ptah-c-toolbar');

            // Espera a primeira medicao (init() -> $nextTick -> _measure()).
            $browser->pause(200);

            // NAO compara com o literal 'inline': o rotulo e filho direto de um
            // container flex (.ptah-c-control/.ptah-c-btn usam inline-flex), e
            // todo navegador "blockifica" o display COMPUTADO de um item flex —
            // a regra em ptah-components.css especifica display:inline, mas
            // getComputedStyle honestamente reporta 'block' para o mesmo item
            // (medido empiricamente). 'none' e a UNICA leitura que corresponde
            // a "rotulo escondido"; qualquer outra coisa e "rotulo visivel".
            $wideDisplay = $browser->script(
                "var l = document.querySelector('.ptah-c-btn_label'); return l ? getComputedStyle(l).display : 'missing';"
            )[0];
            $this->assertNotSame('none', $wideDisplay, 'em viewport largo o rotulo deveria estar visivel');

            // Encolhe bastante para forcar a quebra de linha do grupo de acoes.
            $browser->resize(420, 900);

            // ResizeObserver -> rAF -> _measure(): assincrono, aguarda.
            $browser->pause(500);

            $narrowDisplay = $browser->script(
                "var l = document.querySelector('.ptah-c-btn_label'); return l ? getComputedStyle(l).display : 'missing';"
            )[0];
            $this->assertSame('none', $narrowDisplay, 'em viewport estreito o rotulo deveria colapsar para so-icone');
        });
    }
}

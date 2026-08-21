<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 3 (ONDA IV) — sidebar: Ctrl+B colapsa (largura real muda), rótulos
 * saem do fluxo (não apenas opacity:0 — ver comentário "FIX 1 / Onda C" em
 * forge-sidebar.blade.php), o item ativo de um grupo continua visível
 * colapsado, e clicar no grupo colapsado expande a sidebar de novo.
 *
 * A configuração de `ptah.forge.sidebar_items` em DuskTestCase coloca a rota
 * /dusk-test/crud como filha do grupo "Catalogo", então visitar esta tela
 * deixa esse grupo "ativo" (ver $groupActive em forge-sidebar.blade.php).
 */
class SidebarCollapseBrowserTest extends DuskTestCase
{
    private const GROUP_BUTTON = '.ptah-sidebar button[aria-haspopup="true"]';

    /**
     * `sidebarCollapsed` só é lido do localStorage na inicialização do
     * x-data (ver forge-dashboard-layout.blade.php) — o Dusk reusa a MESMA
     * sessão de navegador (e portanto o mesmo localStorage) entre os métodos
     * de teste desta classe, então o Ctrl+B de um teste anterior deixava o
     * PRÓXIMO teste começar já colapsado (medido empiricamente: o 2º teste
     * via a sidebar expandida DEPOIS do Ctrl+B, porque tinha começado
     * colapsada). Recarrega a página com o flag limpo para cada teste
     * começar do mesmo estado conhecido (expandida).
     */
    private function visitWithExpandedSidebar(Browser $browser): void
    {
        $browser->resize(1280, 900)
            ->visit('/dusk-test/crud')
            ->waitFor('.ptah-sidebar')
            ->script("localStorage.removeItem('ptah_sidebar_collapsed');");

        $browser->refresh()->waitFor('.ptah-sidebar');
    }

    #[Test]
    public function ctrl_b_collapses_the_sidebar_width_and_hides_labels(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitWithExpandedSidebar($browser);
            $browser->assertSeeIn('.ptah-sidebar', 'Catalogo');

            $expandedWidth = (float) $browser->script(
                "return document.querySelector('.ptah-sidebar').getBoundingClientRect().width;"
            )[0];
            // Distinguir expandida (~256px/16rem) de colapsada (~64px/4rem): o limiar
            // fica no meio-termo — medicoes reais variam com scrollbar/zoom do
            // headless (ja se viu 197.46px numa janela estreita), e 200 colado no
            // topo do estado colapsado gerava flake sem bug nenhum.
            $this->assertGreaterThan(150, $expandedWidth, 'sidebar deveria estar expandida (16rem) por padrao');

            $this->dispatchGlobalKeydown($browser, 'b', ctrl: true);

            // A transicao de largura tem duration-300 (ver classe no <aside>) —
            // espera a largura estabilizar em vez de checar no frame errado.
            $browser->pause(400);

            $collapsedWidth = (float) $browser->script(
                "return document.querySelector('.ptah-sidebar').getBoundingClientRect().width;"
            )[0];
            $this->assertLessThan($expandedWidth, $collapsedWidth, 'Ctrl+B deveria colapsar a sidebar (icon-only)');

            // Rotulo sai do FLUXO (x-show), nao so opacity:0 — texto oculto de
            // verdade nao aparece no getText() que assertDontSeeIn usa.
            $browser->assertDontSeeIn('.ptah-sidebar', 'Catalogo');
        });
    }

    #[Test]
    public function the_active_group_pill_stays_visible_while_collapsed(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitWithExpandedSidebar($browser);

            $this->dispatchGlobalKeydown($browser, 'b', ctrl: true);

            $browser->pause(400)
                ->assertAttributeContains(self::GROUP_BUTTON, 'class', 'ptah-nav-active');
        });
    }

    #[Test]
    public function clicking_the_collapsed_group_expands_the_sidebar_again(): void
    {
        $this->browse(function (Browser $browser) {
            $this->visitWithExpandedSidebar($browser);

            $this->dispatchGlobalKeydown($browser, 'b', ctrl: true);

            $browser->pause(400)
                ->assertDontSeeIn('.ptah-sidebar', 'Catalogo');

            // element.click() via script, NAO Browser::click(): o click do Dusk
            // move o mouse real ate o elemento primeiro, o que dispara
            // @mouseenter="hovered = true" no <aside> ANTES do proprio clique —
            // como o grupo "Catalogo" ja esta ativo (x-data do <li> inicializa
            // open=true nessa rota), esse hover fantasma faz iconOnly() virar
            // false bem no instante do clique, e o handler cai no ramo "else"
            // (open = !open), FECHANDO o grupo que ja estava aberto em vez de
            // abri-lo (medido empiricamente). element.click() e exatamente o
            // caso "touch, sem hover" que o comentario de
            // forge-sidebar.blade.php ja documenta como suportado de proposito.
            $browser->script('document.querySelector(\''.self::GROUP_BUTTON.'\').click();');

            $browser->pause(400)
                ->assertSeeIn('.ptah-sidebar', 'Catalogo')
                ->assertVisible('.ptah-sidebar a[href="/dusk-test/crud"]');
        });
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Achado #11 do PetPlace: com filtros no slot do forge-page-header, a area de
 * acoes (shrink-0, sem wrap) nao encolhia e a pagina inteira rolava para o
 * lado num celular de 390px (542px de conteudo).
 */
class PageHeaderMobileBrowserTest extends DuskTestCase
{
    #[Test]
    public function wide_header_actions_do_not_scroll_the_page_sideways_on_a_phone(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(390, 844)
                ->visit('/dusk-test/header')
                ->waitForText('Aplicar filtros');

            [$m] = $browser->script('return { scroll: document.documentElement.scrollWidth, view: window.innerWidth };');

            $this->assertLessThanOrEqual($m['view'], $m['scroll'], "A pagina rola para o lado: {$m['scroll']}px de conteudo em {$m['view']}px.");
        });
    }
}

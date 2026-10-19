<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * 1.46.4 (Engepecas, B2B closing screen): `x-forge-card` always had
 * `overflow-hidden`, so a forge-select's list inside a card was cut at the
 * card's edge — the payment methods trapped inside the rectangle.
 */
class CardPopoverBrowserTest extends DuskTestCase
{
    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->get('/dusk-test/card-select', fn () => view('dusk-card-select'));
    }

    #[Test]
    public function a_select_list_inside_a_card_is_not_cut_at_its_edge(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1000, 900)->visit('/dusk-test/card-select')->waitFor('.ptah-select-trigger');
            $b->click('.ptah-select-trigger')->waitForText('Prazo 30 dias', 5)->pause(200);

            // Um ponto da lista logo ABAIXO da borda do card. (A lista tem altura
            // maxima com rolagem propria: a ultima opcao fica dentro dela.)
            [$cardBottom, $listBottom, $visible] = $b->script(<<<'JS'
                const card = document.getElementById('the-card').getBoundingClientRect();
                const list = document.querySelector('.ptah-select-dropdown');
                const r = list.getBoundingClientRect();
                const hit = document.elementFromPoint(r.left + r.width / 2, card.bottom + 12);
                return [card.bottom, r.bottom, !!hit && list.contains(hit)];
            JS)[0];

            $this->assertGreaterThan($cardBottom + 12, $listBottom, 'O teste precisa de uma lista que passe da borda do card.');
            $this->assertTrue($visible, 'A lista foi cortada na borda do card.');
        });
    }
}

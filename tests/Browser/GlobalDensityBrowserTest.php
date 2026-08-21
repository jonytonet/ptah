<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 4 (ONDA IV) — densidade global: trocar `data-ptah-density` no <html>
 * precisa mudar a altura COMPUTADA de um controle real (`.ptah-c-control`),
 * não apenas o atributo. Pega o bug de herança que o revisor achou na Onda B
 * (regra local `.ptah-base-crud[data-density="global"]` que não deveria
 * existir — ver CrudDensityGlobalDefaultTest, que já cobre isso a nivel de
 * HTML estatico; este teste cobre o efeito real no navegador).
 */
class GlobalDensityBrowserTest extends DuskTestCase
{
    #[Test]
    public function switching_the_global_density_attribute_changes_the_computed_control_height(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/crud')
                ->waitFor('.ptah-c-control');

            $heights = [];

            foreach (['compacta', 'espacosa'] as $density) {
                $browser->script(
                    "document.documentElement.setAttribute('data-ptah-density', '{$density}');"
                );
                $browser->pause(50);

                $heights[$density] = (float) $browser->script(
                    "return getComputedStyle(document.querySelector('.ptah-c-control')).height;"
                )[0];
            }

            $this->assertNotSame(
                $heights['compacta'],
                $heights['espacosa'],
                'A altura computada do controle precisa mudar com a densidade global.'
            );
            $this->assertLessThan(
                $heights['espacosa'],
                $heights['compacta'],
                'compacta deveria ser mais baixa que espacosa (--ptah-control-h: 1.75rem vs 2.75rem).'
            );
        });
    }
}

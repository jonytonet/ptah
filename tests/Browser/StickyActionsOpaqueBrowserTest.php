<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * 1.46.3: in the dark theme the sticky action column turned 70% transparent on
 * row hover (30% over `transparent`), and the cell scrolling under it — a
 * badge — showed through, behind the buttons. It must stay opaque.
 */
class StickyActionsOpaqueBrowserTest extends DuskTestCase
{
    /** Alpha of a computed CSS colour (rgb/rgba/color(srgb ... / a)); 1 when none. */
    private const ALPHA_JS = <<<'JS'
        const c = getComputedStyle(document.querySelector('tbody tr:first-child .ptah-c-sticky_cell')).backgroundColor;
        const m = c.match(/\/\s*([\d.]+)\s*\)$/) || c.match(/^rgba\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\)$/);
        return [c, m ? parseFloat(m[1]) : 1];
    JS;

    #[Test]
    public function the_sticky_action_column_stays_opaque_on_hover_in_dark(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1000, 800)->visit('/dusk-test/crud')->waitForText('Alfa Produto')->pause(300);
            $b->script("document.documentElement.classList.add('ptah-dark'); document.querySelector('.ptah-c-tbl_wrap table').style.minWidth = '2400px';");
            $b->pause(300);

            $b->mouseover('tbody tr:first-child td:nth-child(3)')->pause(400);
            [$colour, $alpha] = $b->script(self::ALPHA_JS)[0];
            $this->assertSame(1.0, (float) $alpha, "A coluna de acoes ficou translucida no hover: {$colour}.");

            // Linha selecionada, mesma regra.
            $b->script("document.querySelector('tbody tr:first-child').classList.add('ptah-c-tr_selected');");
            [$colour, $alpha] = $b->script(self::ALPHA_JS)[0];
            $this->assertSame(1.0, (float) $alpha, "A coluna de acoes ficou translucida na linha selecionada: {$colour}.");
        });
    }
}

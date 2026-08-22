<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-Chrome proof of the searchable forge-select — the riskiest Alpine
 * interaction of its wave (index-based keyboard navigation over a FILTERED
 * list), which the structural tests explicitly cannot exercise (their own
 * "HONEST LIMIT" note). Also pins the review fix: reopening the dropdown
 * starts with a clean filter.
 */
class ForgeSelectSearchableBrowserTest extends DuskTestCase
{
    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->get('/dusk-test/searchable-select', function () {
            return view('dusk-searchable-select');
        });
    }

    #[Test]
    public function typing_filters_with_diacritics_arrows_select_and_reopening_resets(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/searchable-select')
                ->waitFor('.ptah-select-trigger');

            // Open: the filter input receives focus automatically. The focus
            // lands inside a $nextTick, so poll instead of asserting instantly.
            $browser->click('#with-none .ptah-select-trigger')
                ->waitFor('#with-none .ptah-select-filter')
                ->waitUsing(5, 100, function () use ($browser) {
                    return $browser->driver->executeScript(
                        'return document.activeElement?.classList.contains(\'ptah-select-filter\');'
                    );
                }, 'filter input never received focus');

            // Diacritics-insensitive: plain "permissao" must find "Permissão".
            $browser->type('#with-none .ptah-select-filter', 'permissao')
                ->waitForText('Permissão Especial')
                ->assertDontSee('Outra Coisa')
                ->assertDontSee('Ver Custo Médio')
                // The empty-value "None" option is never filtered out.
                ->assertSee('None (everyone sees it)');

            // Arrow down + Enter selects the active (first matching) option.
            $browser->keys('#with-none .ptah-select-filter', ['{arrow_down}'], ['{enter}'])
                ->waitUntilMissing('#with-none .ptah-select-filter')
                ->assertSee('Permissão Especial');

            // Reopen: the previous filter must NOT persist (review finding —
            // a stale filter could hide the selected option on reopen).
            $browser->click('#with-none .ptah-select-trigger')
                ->waitFor('#with-none .ptah-select-filter')
                ->assertValue('#with-none .ptah-select-filter', '')
                ->assertSee('Outra Coisa');

            // Escape with an empty filter closes the dropdown.
            $browser->keys('#with-none .ptah-select-filter', ['{escape}'])
                ->waitUntilMissing('#with-none .ptah-select-filter');
        });
    }

    #[Test]
    public function a_filter_matching_nothing_shows_the_empty_state(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test/searchable-select')
                ->waitFor('.ptah-select-trigger')
                ->click('#without-none .ptah-select-trigger')
                ->waitFor('#without-none .ptah-select-filter')
                ->type('#without-none .ptah-select-filter', 'zzzznada')
                ->waitFor('#without-none .ptah-select-empty');

            // First Escape clears the filter (list returns), second closes.
            $browser->keys('#without-none .ptah-select-filter', ['{escape}'])
                ->waitForText('Beta')
                ->assertValue('#without-none .ptah-select-filter', '')
                ->keys('#without-none .ptah-select-filter', ['{escape}'])
                ->waitUntilMissing('#without-none .ptah-select-filter');
        });
    }
}

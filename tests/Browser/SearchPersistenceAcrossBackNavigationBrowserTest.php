<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fluxo 6 (ONDA IV) — PESQUISA RAPIDA + VOLTAR: o sintoma real relatado pelo
 * usuario, nunca reproduzido antes deste teste. `search` em BaseCrud.php NAO
 * tem #[Url] (é uma propriedade Livewire comum), então nada na URL preserva
 * o filtro — a única forma de o texto E a listagem filtrada sobreviverem a um
 * `history.back()` é o navegador restaurar a página inteira via bfcache (sem
 * nova requisição). Se este teste falhar, é exatamente esse o diagnóstico:
 * NÃO conserte às cegas — a captura de tela/HTML abaixo é para o relatório.
 *
 * Nota: `assertSeeIn('body', ...)`/`waitForTextIn('body', ...)` seriam
 * ambíguos aqui — o resolver de elementos do Dusk usa prefix='body' por
 * padrão, então "body" como seletor formataria "body body" (não casa com
 * nada). Por isso o teste usa os equivalentes sem sufixo "In"
 * (assertSee/assertDontSee/waitForText), que já operam na página inteira.
 */
class SearchPersistenceAcrossBackNavigationBrowserTest extends DuskTestCase
{
    #[Test]
    public function typed_search_and_filtered_listing_survive_a_browser_back_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            // Viewport desktop: a sidebar mobile fica fora da tela
            // (-translate-x-full) até um toggle explícito, o que tornaria o
            // link de navegação abaixo não-interativo no viewport padrão.
            $browser->resize(1280, 900)
                ->visit('/dusk-test/crud')
                // Timeout maior no primeiro load: cada boot migra+semeia do
                // zero (sqlite :memory:, ver DuskTestCase::ensureDuskDatabaseIsReady) —
                // mais lento que os 5s default em maquina sob carga.
                ->waitForText('Alfa Produto', 15)
                ->assertSee('Beta Servico')
                ->type('.ptah-c-search', 'Alfa')
                // debounce de 400ms (wire:model.live.debounce.400ms) + round-trip.
                ->waitUntilMissingText('Beta Servico', 5)
                ->assertSee('Alfa Produto');

            // Navega para OUTRA URL (rota trivial, ver DuskTestCase::defineWebRoutes).
            $browser->click('.ptah-sidebar a[href="/dusk-test/other"]')
                ->waitForText('Fluxo 6')
                ->assertPathIs('/dusk-test/other');

            $browser->back();

            // Evidencia para o relatorio, independente do resultado abaixo.
            $browser->screenshot('fluxo-6-apos-voltar');

            $sourceDir = __DIR__.'/source';
            if (! is_dir($sourceDir)) {
                mkdir($sourceDir, 0777, true);
            }
            file_put_contents($sourceDir.'/fluxo-6-apos-voltar.html', $browser->driver->getPageSource());

            $browser->assertInputValue('.ptah-c-search', 'Alfa');
            $browser->assertDontSee('Beta Servico');
            $browser->assertSee('Alfa Produto');
        });
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Facebook\WebDriver\WebDriverBy;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * The Livewire layout's single-page navigation (`ptah.forge.spa_navigation`),
 * in a real Chrome over the multi-page fixture app (/dusk-test/other,
 * /dusk-test/draft and /dusk-test/crud, all inside the real layout).
 *
 * "No reload" is measured, not inferred: a marker is put on `window` before the
 * click and read after it — a document load would have wiped it.
 */
class SpaNavigationBrowserTest extends DuskTestCase
{
    private const SIDEBAR = 'aside.ptah-sidebar';

    /** Turns the mode on in the Dusk server (and this process). */
    public function spaOn(Application $app, Repository $config): void
    {
        $config->set('ptah.forge.spa_navigation', true);
        $config->set('ptah.forge.sidebar_items', self::menu());
    }

    /** Same menu, mode off. */
    public function spaOff(Application $app, Repository $config): void
    {
        $config->set('ptah.forge.spa_navigation', false);
        $config->set('ptah.forge.sidebar_items', self::menu());
    }

    /**
     * Long enough for the nav to scroll at 900px, with a group nothing on the
     * fixture pages makes active (Extras) — so "open" can only come from a click.
     *
     * @return list<array<string, mixed>>
     */
    private static function menu(): array
    {
        $items = [
            ['label' => 'Inicio', 'url' => '/dusk-test/other', 'icon' => 'bx bx-home-alt', 'type' => 'menuLink', 'match' => 'dusk-test/other'],
            ['label' => 'Contatos', 'url' => '/dusk-test/draft', 'icon' => 'bx bx-user', 'type' => 'menuLink', 'match' => 'dusk-test/draft'],
            ['label' => 'Catalogo', 'icon' => 'bx bx-cube', 'type' => 'menuGroup', 'children' => [
                ['label' => 'Produtos', 'url' => '/dusk-test/crud', 'match' => 'dusk-test/crud'],
            ]],
            ['label' => 'Extras', 'icon' => 'bx bx-folder', 'type' => 'menuGroup', 'children' => [
                ['label' => 'Cabecalho', 'url' => '/dusk-test/header', 'match' => 'dusk-test/header'],
            ]],
        ];

        for ($i = 1; $i <= 25; $i++) {
            $items[] = ['label' => "Item {$i}", 'url' => "/dusk-test/other?item={$i}", 'icon' => 'bx bx-circle', 'type' => 'menuLink', 'match' => "nada-{$i}"];
        }

        $items[] = ['label' => 'Fim', 'url' => '/dusk-test/draft?fim=1', 'icon' => 'bx bx-flag', 'type' => 'menuLink', 'match' => 'nada-fim'];

        return $items;
    }

    private function ready(Browser $b): void
    {
        $b->waitFor(self::SIDEBAR, 30);
        $b->waitUntil('window.Livewire && window.Livewire.initialRenderIsFinished === true', 30);
        $b->pause(300);
    }

    private function start(Browser $b, string $path): void
    {
        $b->resize(1280, 900)->visit($path);
        $this->ready($b);
        $b->script('window.__marker = 42;');
    }

    private function marker(Browser $b): mixed
    {
        return $b->script('return window.__marker === undefined ? null : window.__marker;')[0];
    }

    /** Waits for an in-place navigation to land on $path and settle. */
    private function landed(Browser $b, string $path): void
    {
        $b->waitUntil('window.location.pathname + window.location.search === '.json_encode($path), 30);
        $b->waitUntil("!document.documentElement.hasAttribute('data-ptah-swapping')", 30);
        $b->pause(400);
    }

    #[Test]
    public function a_sidebar_click_swaps_the_page_without_a_reload_and_the_active_item_follows(): void
    {
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/other');

            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
            $this->landed($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30);

            $this->assertSame(42, $this->marker($b), 'O documento recarregou: o marcador sumiu.');
            $b->assertPresent(self::SIDEBAR.' a[href="/dusk-test/draft"][aria-current="page"]');
            $b->assertMissing(self::SIDEBAR.' a[href="/dusk-test/other"][aria-current="page"]');
            $this->assertStringContainsString('Dusk Draft', (string) $b->script('return document.title;')[0]);

            // A group link: the new page renders its group open and its item active.
            $b->click(self::SIDEBAR.' button[aria-haspopup="true"]');
            $b->waitFor(self::SIDEBAR.' a[href="/dusk-test/crud"]', 10)->pause(300);
            $b->click(self::SIDEBAR.' a[href="/dusk-test/crud"]');
            $this->landed($b, '/dusk-test/crud');
            $this->assertSame(42, $this->marker($b));
            $b->assertPresent(self::SIDEBAR.' a[href="/dusk-test/crud"][aria-current="page"]');
            $b->assertVisible(self::SIDEBAR.' a[href="/dusk-test/crud"]');

            // Back and forward stay in the document too.
            $b->script('history.back();');
            $this->landed($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30);
            $b->script('history.forward();');
            $this->landed($b, '/dusk-test/crud');
            $b->waitForText('Alfa Produto', 30);
            $this->assertSame(42, $this->marker($b), 'Voltar/avancar recarregou o documento.');
            $this->assertNoSevereLog($b, 'troca e historico');
        });
    }

    #[Test]
    public function the_sidebar_scroll_and_a_group_the_person_opened_survive(): void
    {
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/other');

            // Open "Extras" by hand (nothing makes it active), then scroll the nav
            // to the bottom, where "Fim" is in view.
            $b->script(<<<'JS'
                const btn = [...document.querySelectorAll('aside.ptah-sidebar button[aria-haspopup="true"]')]
                    .find(x => x.textContent.includes('Extras'));
                btn.click();
            JS);
            $b->pause(400);
            $nav = "document.querySelector('aside.ptah-sidebar nav')";
            $this->assertTrue((bool) $b->script("return {$nav}.scrollHeight > {$nav}.clientHeight + 50;")[0], 'O menu precisa rolar para o teste valer.');
            $b->script("{$nav}.scrollTop = {$nav}.scrollHeight;");
            $b->pause(300);
            $before = (int) $b->script("return {$nav}.scrollTop;")[0];
            $this->assertGreaterThan(50, $before);

            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft?fim=1"]');
            $before = (int) $b->script("return {$nav}.scrollTop;")[0] ?: $before;
            $this->landed($b, '/dusk-test/draft?fim=1');
            $b->waitForText('Bia', 30);

            $this->assertSame(42, $this->marker($b));
            $after = (int) $b->script("return {$nav}.scrollTop;")[0];
            $this->assertEqualsWithDelta($before, $after, 4, 'A rolagem da sidebar voltou ao topo.');
            $this->assertTrue((bool) $b->script(<<<'JS'
                const li = document.querySelector('aside.ptah-sidebar [data-ptah-group="Extras"]');
                return !!li && Alpine.$data(li).open === true;
            JS)[0], 'O grupo aberto a mao fechou na troca de pagina.');
            $this->assertNoSevereLog($b, 'sidebar');
        });
    }

    #[Test]
    public function the_crud_on_the_next_page_works_and_nothing_piles_up_after_going_back_and_forth(): void
    {
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            // Starts ON a CRUD screen: its once-per-document scripts run here, and
            // must still work on the CRUD screens reached later without a reload.
            $this->start($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30);

            // The copy group is created on the first rest over a cell and lives
            // in <body> — which the swaps below throw away.
            $mark = "[...document.querySelectorAll('tbody td')].find(t => t.innerText.trim() === 'Ana').setAttribute('data-ptah-test', 'cell');";
            $b->script($mark);
            $b->mouseover('[data-ptah-test="cell"]')->pause(500)->waitFor('.ptah-cell-actions.is-visible', 5);
            $b->mouseover(self::SIDEBAR)->pause(300);

            for ($i = 0; $i < 3; $i++) {
                $b->click(self::SIDEBAR.' a[href="/dusk-test/other"]');
                $this->landed($b, '/dusk-test/other');
                $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
                $this->landed($b, '/dusk-test/draft');
                $b->waitForText('Bia', 30);
            }
            $this->assertSame(42, $this->marker($b));

            // Search.
            $b->type('.ptah-c-search', 'Ana');
            $b->waitUntilMissingText('Bia', 30)->assertSee('Ana');
            $b->script("const s = document.querySelector('.ptah-c-search'); s.value = ''; s.dispatchEvent(new Event('input', { bubbles: true }));");
            $b->waitForText('Bia', 30);

            // The cell's copy/filter buttons: ONE group, re-attached to the new body.
            $b->script($mark);
            $b->mouseover('[data-ptah-test="cell"]')->pause(500)->waitFor('.ptah-cell-actions.is-visible', 5);
            $this->assertSame(1, (int) $b->script("return document.querySelectorAll('.ptah-cell-actions').length;")[0]);

            // A hotkey fires once: `f` toggles the filter panel. Two listeners would
            // toggle it twice — back to where it was.
            $open = fn () => (bool) $b->script("return !!document.querySelector('.ptah-c-fp_card');")[0];
            $was = $open();
            $b->mouseover('.ptah-c-search')->pause(100);
            $b->script('document.activeElement && document.activeElement.blur();');
            $this->dispatchGlobalKeydown($b, 'f');
            $b->waitUntil(($was ? '!' : '')."document.querySelector('.ptah-c-fp_card')", 15)->pause(1500);
            $this->assertSame(! $was, $open(), 'O atalho disparou mais de uma vez.');
            $this->dispatchGlobalKeydown($b, 'f');
            $b->waitUntil(($was ? '' : '!')."document.querySelector('.ptah-c-fp_card')", 15);

            // The create form opens on this (fourth) visit.
            $this->dispatchGlobalKeydown($b, 'n');
            $b->waitFor('.ptah-modal-panel[aria-labelledby^="forge-modal-title-"]', 15);
            $this->assertNoSevereLog($b, 'crud apos idas e voltas');
        });
    }

    #[Test]
    public function the_theme_toggle_survives_a_navigation(): void
    {
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/other');
            $isDark = fn () => (bool) $b->script("return document.documentElement.classList.contains('ptah-dark');")[0];
            $toggle = "[...document.querySelectorAll('nav.ptah-navbar button')].find(x => x.getAttribute('@click') === 'toggleDark()').click();";

            $was = $isDark();
            $b->script($toggle);
            $b->pause(300);
            $this->assertSame(! $was, $isDark());

            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
            $this->landed($b, '/dusk-test/draft');
            $this->assertSame(42, $this->marker($b));
            $this->assertSame(! $was, $isDark(), 'O tema voltou ao anterior na troca de pagina.');

            // And the toggle of the NEW navbar still works.
            $b->script($toggle);
            $b->pause(300);
            $this->assertSame($was, $isDark());
            $this->assertNoSevereLog($b, 'frame');
        });
    }

    /**
     * 1.59.1 — reported from a production host: after menu clicks in quick
     * succession, back/forward, a theme toggle and the same menu item twice,
     * a click on the row's Edit threw "moreOpen is not defined" / "Illegal
     * invocation" and the modal never opened.
     *
     * The cause was not the sequence but its last two steps: the page being
     * left stays on screen while the next one is on its way, and the Edit was
     * clicked THERE. Its answer arrived after the swap, and Livewire morphed a
     * component that had already left the document.
     */
    #[Test]
    public function a_click_on_the_page_being_left_is_not_taken_and_its_pending_answer_never_lands(): void
    {
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30);
            $b->driver->manage()->getLog('browser');
            // Holds the answer of a navigation (the document), so "on its way" lasts.
            $b->script(<<<'JS'
                window.__hold = 0;
                const f = window.fetch;
                window.fetch = function (input, init) {
                    const url = typeof input === 'string' ? input : input.url;
                    const p = f.apply(this, arguments);
                    const page = !(init && init.method && init.method !== 'GET') && String(url).includes('/dusk-test/') && !String(url).includes('/update');
                    return window.__hold && page ? p.then(r => new Promise(res => setTimeout(() => res(r), window.__hold))) : p;
                };
                window.__edit = () => [...document.querySelectorAll('main button')].find(x => (x.getAttribute('wire:click') || '').startsWith('openEdit('));
                window.__menu = (path) => document.querySelector('aside.ptah-sidebar a[href="' + path + '"]').click();
                window.__modalOpen = () => [...document.querySelectorAll('.ptah-modal-panel[aria-labelledby^="forge-modal-title-"]')].some(p => p.offsetParent !== null);
            JS);
            $inert = fn (): bool => (bool) $b->script("return !!document.querySelector('main') && document.querySelector('main').hasAttribute('inert');")[0];

            // 1. An action in flight when the page is swapped: its answer is dropped.
            //    (A script click reaches an inert element; a person's does not.)
            $b->script("window.__hold = 1500; window.__edit().click(); window.__menu('/dusk-test/crud');");
            $b->pause(200);
            $this->assertTrue($inert(), 'O conteudo da pagina que sai devia estar inerte durante a navegacao.');
            $this->landed($b, '/dusk-test/crud');
            $b->pause(2500);
            $this->assertFalse($inert(), 'A pagina que chegou ficou inerte.');
            $this->assertSame(42, $this->marker($b));
            $this->assertNoSevereLog($b, 'resposta pendente da pagina que saiu');

            // 2. The same menu item again (the page is fetched anew), and a real
            //    click on the Edit of the page still on screen: not taken.
            $b->script('window.__hold = 0;');
            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
            $this->landed($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30);
            $b->script("window.__hold = 3000; document.body.setAttribute('data-old', ''); window.__edit().setAttribute('data-ptah-test', 'old-edit'); window.__menu('/dusk-test/draft');");
            $b->pause(200);
            $this->assertTrue($inert());
            try {
                $b->driver->findElement(WebDriverBy::cssSelector('[data-ptah-test="old-edit"]'))->click();
            } catch (Throwable) {
                // An inert element refuses the click: that is the point.
            }
            $b->waitUntil("!document.body.hasAttribute('data-old')", 30);
            $this->landed($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30)->pause(1500);
            $this->assertFalse((bool) $b->script('return window.__modalOpen();')[0], 'O clique na pagina que saia abriu o modal.');
            $this->assertNoSevereLog($b, 'clique na pagina que saia');

            // 3. On the page that arrived, Edit opens the modal.
            $b->script('window.__hold = 0; window.__edit().setAttribute("data-ptah-test", "new-edit");');
            $b->click('[data-ptah-test="new-edit"]');
            $b->waitUntil('window.__modalOpen()', 20);
            $this->assertNoSevereLog($b, 'editar na pagina que chegou');
        });
    }

    /**
     * The sequence of that report, with back/forward and the theme toggle in
     * the middle: the CRUD that arrives is whole.
     */
    #[Test]
    public function quick_menu_clicks_back_forward_and_a_theme_toggle_leave_the_crud_whole(): void
    {
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/other');
            $b->driver->manage()->getLog('browser');
            $toggle = "[...document.querySelectorAll('nav.ptah-navbar button')].find(x => x.getAttribute('@click') === 'toggleDark()').click();";
            $go = function (string $path) use ($b): void {
                $b->script("document.querySelector('aside.ptah-sidebar a[href=\"{$path}\"]').click();");
                $b->waitUntil('window.location.pathname === '.json_encode($path), 30);
                // Not waiting for the page to settle: the next click comes at once.
                $b->pause(400);
            };

            $go('/dusk-test/draft');
            $go('/dusk-test/crud');
            $go('/dusk-test/header');
            $go('/dusk-test/draft');

            $b->script('history.back();');
            $b->waitUntil("window.location.pathname === '/dusk-test/header'", 30)->pause(400);
            $b->script('history.forward();');
            $b->waitUntil("window.location.pathname === '/dusk-test/draft'", 30)->pause(400);

            $b->script($toggle);
            $b->pause(300);
            $go('/dusk-test/crud');
            $go('/dusk-test/draft');
            $b->script($toggle);
            $b->pause(300);
            // The same item again: the page is fetched anew and swapped.
            $this->landed($b, '/dusk-test/draft');
            $b->script("document.body.setAttribute('data-old', '');");
            $go('/dusk-test/draft');
            $b->waitUntil("!document.body.hasAttribute('data-old')", 30);

            $this->landed($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30);
            $this->assertSame(42, $this->marker($b));

            $b->script("[...document.querySelectorAll('main button')].find(x => (x.getAttribute('wire:click') || '').startsWith('openEdit(')).setAttribute('data-ptah-test', 'edit');");
            $b->click('[data-ptah-test="edit"]');
            $b->waitUntil("[...document.querySelectorAll('.ptah-modal-panel[aria-labelledby^=\"forge-modal-title-\"]')].some(p => p.offsetParent !== null)", 20);
            $this->assertNoSevereLog($b, 'editar apos a sequencia do relato');
        });
    }

    /** No SEVERE entry in the browser log (a missing favicon is not ours). */
    private function assertNoSevereLog(Browser $b, string $where): void
    {
        $severe = array_values(array_filter(
            $b->driver->manage()->getLog('browser'),
            fn (array $e) => $e['level'] === 'SEVERE' && ! str_contains($e['message'], 'favicon.ico')
        ));

        $this->assertSame([], array_map(fn (array $e) => $e['message'], $severe), "Erro no console ({$where}).");
    }

    #[Test]
    public function revisiting_the_same_screen_never_trips_livewires_prefetch_cache(): void
    {
        // PetPlace, two runs in a row: "Uncaught TypeError: Cannot set
        // properties of undefined (setting 'html')". Livewire 4.3.1's prefetch
        // cache (wire:navigate / .hover) expires entries with a 30s timer keyed
        // by URL, so it deletes a NEWER in-flight prefetch of the same URL and
        // its response throws — a click waiting on it never lands. The layout
        // no longer feeds that cache. This replays the exact sequence that
        // reproduced it: hover (prefetch would finish), click, come back, and
        // ~27s later hover the same link with a slowed fetch in flight across
        // the old entry's 30s mark — then click it and it must land.
        $this->beforeServingApplication('spaOn');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/other');
            $b->driver->manage()->getLog('browser');
            $b->script(<<<'JS'
                window.__slow = false;
                const f = window.fetch;
                window.fetch = function (input) {
                    const url = typeof input === 'string' ? input : input.url;
                    const p = f.apply(this, arguments);
                    if (window.__slow && String(url).includes('/dusk-test/draft')) return p.then(r => new Promise(res => setTimeout(() => res(r), 4000)));
                    return p;
                };
                window.__hover = () => document.querySelector('aside.ptah-sidebar a[href="/dusk-test/draft"]').dispatchEvent(new MouseEvent('mouseenter'));
            JS);

            $b->script('window.__hover();');
            $b->pause(3000);
            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
            $this->landed($b, '/dusk-test/draft');
            $b->click(self::SIDEBAR.' a[href="/dusk-test/other"]');
            $this->landed($b, '/dusk-test/other');

            $b->pause(19000);
            $b->script('window.__slow = true; window.__hover();');
            $b->pause(500);
            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
            $this->landed($b, '/dusk-test/draft');
            $b->waitForText('Bia', 30)->pause(5000);

            $this->assertSame(42, $this->marker($b));
            $this->assertNoSevereLog($b, 'revisita');
        });
    }

    /**
     * The other SEVERE PetPlace showed, intermittently, after opening the bell:
     * "Uncaught (in promise)" {status: null, body: null, ...}. Not navigation:
     * a `wire:poll` tick landing while the component has a request in flight is
     * CANCELLED by Livewire, and with `$refresh` (a bare `wire:poll`) the
     * promise of that cancelled action is an async wrapper Livewire does not
     * catch. The bell and the exports panel now poll a named method. This
     * replays the overlap with the fixture: the `$refresh` variant shows the
     * Livewire behaviour, the named-method variant must stay silent.
     */
    #[Test]
    public function a_poll_cancelled_by_an_action_in_flight_leaves_no_unhandled_rejection(): void
    {
        $this->browse(function (Browser $b) {
            $run = function (string $expr) use ($b): array {
                $b->visit('/dusk-test/poll?expr='.$expr)->waitFor('#dusk-poll-slow', 30);
                $b->waitUntil('window.Livewire && window.Livewire.initialRenderIsFinished === true', 30);
                $b->driver->manage()->getLog('browser');
                $b->click('#dusk-poll-slow');
                $b->waitForTextIn('#dusk-poll-done', '1', 30)->pause(800);

                return array_values(array_filter(
                    $b->driver->manage()->getLog('browser'),
                    fn (array $e) => $e['level'] === 'SEVERE' && ! str_contains($e['message'], 'favicon.ico')
                ));
            };

            $this->assertNotSame([], $run('refresh'), 'O fixture nao reproduziu o comportamento do Livewire com $refresh — o teste abaixo deixaria de provar algo.');
            $this->assertSame([], $run('tick'), 'Poll com metodo nomeado deixou rejeicao sem tratamento.');
        });

        $views = dirname(__DIR__, 2).'/resources/views';
        foreach (['livewire/notification/notification-bell.blade.php', 'livewire/exports/exports-panel.blade.php'] as $view) {
            $source = (string) file_get_contents("{$views}/{$view}");
            $this->assertMatchesRegularExpression('/wire:poll[\w.{}$()> -]*="[a-zA-Z]\w*"/', $source, "{$view}: poll tem de chamar um metodo nomeado.");
            $this->assertStringNotContainsString('"$refresh"', $source, "{$view}: nada de wire:poll=\"\$refresh\".");
        }
    }

    #[Test]
    public function with_the_mode_off_a_click_is_a_full_load(): void
    {
        $this->beforeServingApplication('spaOff');

        $this->browse(function (Browser $b) {
            $this->start($b, '/dusk-test/other');
            $b->assertMissing(self::SIDEBAR.' a[data-ptah-navigate]');

            $b->click(self::SIDEBAR.' a[href="/dusk-test/draft"]');
            $b->waitUntil("window.location.pathname === '/dusk-test/draft'", 30);
            $this->ready($b);

            $this->assertNull($this->marker($b), 'Modo desligado tem de recarregar o documento.');
        });
    }
}

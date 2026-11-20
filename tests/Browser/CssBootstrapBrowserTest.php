<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * ptah.css.framework = bootstrap in a real Chrome, with Bootstrap 5.3.9 on the
 * page (docs/Css.md). The pre-compiled stylesheet must give ptah's markup the
 * values a Tailwind host computes and leave every Bootstrap element — the
 * host's page, host markup in the layout's slot, host markup in a forge
 * component's slot — with Bootstrap's own. Computed styles, not the CSS text:
 * the cascade (layers, @scope proximity, revert-layer) is the whole design.
 */
class CssBootstrapBrowserTest extends DuskTestCase
{
    private const NEW_BUTTON = '[\@click*="prepareCreate"]';

    private const CREATE_EDIT_MODAL = '.ptah-modal-panel[aria-labelledby^="forge-modal-title-"]';

    public function bootstrapMode(Application $app, Repository $config): void
    {
        $config->set('ptah.css.framework', 'bootstrap');
    }

    private function style(Browser $b, string $selector, string $property): string
    {
        return (string) $b->script(sprintf(
            'var el = document.querySelector(%s); return el ? getComputedStyle(el)[%s] : "missing";',
            json_encode($selector),
            json_encode($property)
        ))[0];
    }

    private function assertNoSevereLog(Browser $b): void
    {
        $severe = array_values(array_filter(
            $b->driver->manage()->getLog('browser'),
            fn (array $e) => $e['level'] === 'SEVERE' && ! str_contains($e['message'], 'favicon.ico')
        ));

        $this->assertSame([], array_map(fn (array $e) => $e['message'], $severe));
    }

    #[Test]
    public function ptah_markup_keeps_tailwind_values_and_host_markup_keeps_bootstraps_in_the_ptah_layout(): void
    {
        $this->beforeServingApplication('bootstrapMode');

        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/dusk-test/bootstrap-crud')
                ->waitFor('.ptah-base-crud');

            // Dusk prefixes selectors with "body": <head> and <body> itself by script.
            $this->assertSame([true, true, false], $b->script(<<<'JS'
                return [
                    document.body.hasAttribute('data-ptah-scope'),
                    !!document.querySelector('head link[data-ptah-css]'),
                    !!document.querySelector('script[src*="cdn.tailwindcss.com"]'),
                ];
                JS)[0]);

            // ptah: Tailwind's scale (px-3 = .75rem), Bootstrap's px-3 (1rem !important) reverted.
            $this->assertSame('12px', $this->style($b, '.ptah-base-crud th.px-3', 'paddingLeft'));
            $this->assertSame('0px', $this->style($b, '.ptah-base-crud h2, .ptah-base-crud p', 'marginBottom'), 'Bootstrap reboot margins do not reach ptah markup.');

            // Host markup in the layout's slot: Bootstrap's own values.
            $this->assertSame('16px', $this->style($b, '#host-btn', 'paddingLeft'));
            $this->assertSame('24px', $this->style($b, '#host-card-body', 'paddingLeft'));
            $this->assertSame('8px', $this->style($b, '#host-h2', 'marginBottom'));
            $this->assertSame('500', $this->style($b, '#host-h2', 'fontWeight'));
            $this->assertSame('8px', $this->style($b, '#host-td', 'paddingLeft'));
            $this->assertSame('contents', $this->style($b, 'main [data-ptah-slot]', 'display'));

            // A forge component written in the host view is ptah (p-5 = 1.25rem)…
            $b->assertPresent('#forge-card[data-ptah-scope]');
            $this->assertSame('20px', $this->style($b, '#forge-card .p-5', 'paddingLeft'));
            // …and the host markup in its slot is Bootstrap again.
            $this->assertSame('16px', $this->style($b, '#forge-slot-host', 'paddingLeft'));

            // The ptah chrome stays under Bootstrap's modal (1055).
            $this->assertSame('1030', $this->style($b, '.ptah-navbar', 'zIndex'));

            $this->assertNoSevereLog($b);
        });
    }

    #[Test]
    public function a_basecrud_in_the_hosts_own_bootstrap_layout_is_styled_and_its_modal_covers_the_fixed_navbar(): void
    {
        $this->beforeServingApplication('bootstrapMode');

        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/dusk-test/bootstrap-embed')
                ->waitFor('.ptah-base-crud')
                ->assertPresent('[data-ptah-scope] .ptah-base-crud, .ptah-base-crud[data-ptah-scope]');

            $this->assertSame('12px', $this->style($b, '.ptah-base-crud th.px-3', 'paddingLeft'));
            $this->assertSame('12px', $this->style($b, '#host-container', 'paddingLeft'), 'Bootstrap container gutter.');
            $this->assertSame('1030', $this->style($b, '#host-navbar', 'zIndex'));

            // <x-ptah-assets /> appended the toast host to <body>, scoped.
            $this->assertTrue($b->script("return !!document.querySelector('body > [data-ptah-scope][aria-live=polite]');")[0]);

            $b->click(self::NEW_BUTTON)->waitFor(self::CREATE_EDIT_MODAL);

            $overlay = $b->script(<<<'JS'
                var panel = document.querySelector('.ptah-modal-panel[aria-labelledby^="forge-modal-title-"]');
                var overlay = panel.parentElement.closest('.fixed');
                var top = document.elementFromPoint(20, 20);
                return [getComputedStyle(overlay).zIndex, overlay.contains(top), !!top.closest('#host-navbar')];
                JS)[0];

            $this->assertGreaterThan(1055, (int) $overlay[0]);
            $this->assertTrue($overlay[1], 'The point under the host navbar belongs to the ptah modal overlay.');
            $this->assertFalse($overlay[2]);

            $this->assertNoSevereLog($b);
        });
    }

    #[Test]
    public function the_embedded_screen_follows_the_hosts_bootstrap_theme_and_dark_mode(): void
    {
        $this->beforeServingApplication('bootstrapMode');

        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/bootstrap-embed')->waitFor('.ptah-base-crud');

            $token = fn (string $name) => trim((string) $b->script(
                'return getComputedStyle(document.documentElement).getPropertyValue('.json_encode($name).');'
            )[0]);

            // Light: the bridge maps the brand colour and the canvas onto --bs-*.
            $this->assertSame('#0d6efd', $token('--bs-primary'));
            $this->assertSame('rgb(13, 110, 253)', (string) $b->script(<<<'JS'
                var probe = document.createElement('span');
                probe.style.color = 'var(--color-primary)';
                document.querySelector('.ptah-base-crud').appendChild(probe);
                var c = getComputedStyle(probe).color; probe.remove(); return c;
                JS)[0]);

            $b->script("document.documentElement.setAttribute('data-bs-theme', 'dark');");
            $b->pause(200);

            $this->assertSame('rgb(33, 37, 41)', (string) $b->script(<<<'JS'
                var probe = document.createElement('div');
                probe.style.backgroundColor = 'var(--ptah-canvas)';
                document.querySelector('.ptah-base-crud').appendChild(probe);
                var c = getComputedStyle(probe).backgroundColor; probe.remove(); return c;
                JS)[0], 'data-bs-theme="dark" reaches the ptah tokens.');
        });
    }
}

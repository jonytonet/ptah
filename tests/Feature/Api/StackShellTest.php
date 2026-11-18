<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\UserPreference;
use Ptah\Tests\TestCase;

/**
 * What the React/Vue layout needs to behave like the Livewire one (1.59.0):
 * the menu with its search list, and the page carrying the user's appearance.
 */
class StackShellTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
        $app['config']->set('ptah.stack', 'vue');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana', 'email' => 'ana@example.test']));
    }

    #[Test]
    public function the_menu_comes_with_the_search_list_the_rule_for_showing_it_and_the_brand(): void
    {
        config([
            'app.name' => 'ERP',
            'ptah.brand.logo' => 'img/logo.svg',
            'ptah.forge.sidebar_items' => [
                ['label' => 'Início', 'url' => '/inicio', 'type' => 'menuLink'],
                ['label' => 'Compras', 'type' => 'menuGroup', 'children' => [
                    ['label' => 'Cotação', 'url' => '/compras/cotacao'],
                    ['label' => 'Perigo', 'url' => 'javascript:alert(1)'],
                ]],
                ['label' => 'Relatórios', 'url' => '/relatorios', 'type' => 'menuLink'],
            ],
        ]);

        $json = $this->getJson('/ptah/api/menu')->assertOk()->json();

        $this->assertSame(['Início', 'Compras', 'Relatórios'], array_column($json['data'], 'label'));
        // The flat list: only real links, with the trail and the folded text.
        $flat = array_column($json['flat'], null, 'label');
        $this->assertSame(['Compras', 'Cotação'], $flat['Cotação']['breadcrumb']);
        $this->assertSame('compras cotacao', $flat['Cotação']['search']);
        $this->assertArrayNotHasKey('Perigo', $flat);
        // Three links, one inside a group: the search is offered.
        $this->assertTrue($json['jump']);
        $this->assertSame('ERP', $json['brand']['name']);
        $this->assertStringEndsWith('/img/logo.svg', $json['brand']['logo']);
        $this->assertNull($json['brand']['logo_dark']);
    }

    #[Test]
    public function a_small_flat_menu_does_not_offer_the_search(): void
    {
        config(['ptah.forge.sidebar_items' => [
            ['label' => 'Um', 'url' => '/um', 'type' => 'menuLink'],
            ['label' => 'Dois', 'url' => '/dois', 'type' => 'menuLink'],
            ['label' => 'Três', 'url' => '/tres', 'type' => 'menuLink'],
        ]]);

        $this->assertFalse($this->getJson('/ptah/api/menu')->json('jump'));
    }

    #[Test]
    public function the_page_carries_the_users_appearance_like_the_livewire_layout(): void
    {
        UserPreference::set(9, 'theme', ['mode' => 'dark', 'accent' => 'verde', 'density' => 'compacta', 'fontsize' => 'grande'], 'appearance');

        $html = $this->get(route('ptah.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-ptah-accent="verde"', $html);
        $this->assertStringContainsString('data-ptah-density="compacta"', $html);
        $this->assertStringContainsString('data-ptah-fontsize="grande"', $html);
        $this->assertStringContainsString('data-ptah-light="puro"', $html);
        // The anti-flash script of the Livewire layouts, with the user's mode.
        $this->assertStringContainsString('ptah_dark_mode', $html);
        $this->assertMatchesRegularExpression('/serverTheme = .{0,40}dark/', $html);
        // Where the light/dark switch saves the choice.
        $this->assertStringContainsString('data-theme-mode="'.route('ptah.appearance.theme-mode').'"', $html);
    }

    #[Test]
    public function a_visitor_gets_the_defaults_on_the_sign_in_page(): void
    {
        // Back to a visitor (GenericUser has no remember token to cycle on logout()).
        $this->app['auth']->forgetGuards();

        $html = $this->get(route('ptah.auth.login'))->assertOk()->getContent();

        $this->assertStringContainsString('data-ptah-accent="azul"', $html);
        $this->assertStringNotContainsString('data-theme-mode=', $html);
    }

    #[Test]
    public function me_carries_the_photo_for_the_account_menu(): void
    {
        $this->getJson('/ptah/api/me')->assertOk()->assertJsonPath('user.name', 'Ana')->assertJsonStructure(['user' => ['photo_url']]);
    }
}

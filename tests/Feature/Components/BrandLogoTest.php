<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\Brand;
use Ptah\Tests\TestCase;

/**
 * 1.43.3 (projeto Cher): no config for a logo — the login showed the initial
 * of app.name and the dashboard took a logo only as a `logoUrl` prop. Now
 * `ptah.brand.logo` / `logo_dark` feed the login, the sidebar and the navbar.
 */
class BrandLogoTest extends TestCase
{
    #[Test]
    public function a_path_is_resolved_with_asset_and_a_url_is_kept(): void
    {
        config(['ptah.brand.logo' => 'img/logo.svg', 'ptah.brand.logo_dark' => '/img/logo-dark.svg']);

        $this->assertSame(asset('img/logo.svg'), Brand::logo());
        $this->assertSame('/img/logo-dark.svg', Brand::logoDark());

        config(['ptah.brand.logo' => 'javascript:alert(1)']);
        $this->assertNotSame('javascript:alert(1)', Brand::logo());
        $this->assertStringNotContainsString('javascript:', (string) Brand::logo());

        config(['ptah.brand.logo' => '']);
        $this->assertNull(Brand::logo());
    }

    #[Test]
    public function the_navbar_and_sidebar_show_the_configured_logo_and_its_dark_variant(): void
    {
        config(['ptah.brand.logo' => '/img/logo.svg', 'ptah.brand.logo_dark' => '/img/logo-dark.svg']);

        foreach (['<x-forge-navbar />', '<x-forge-sidebar />'] as $component) {
            $html = Blade::render($component);
            $this->assertStringContainsString('src="/img/logo.svg"', $html, $component);
            $this->assertMatchesRegularExpression('/src="\/img\/logo-dark\.svg"[^>]*style="display:none"/', $html, 'A escura comeca escondida.');
        }
    }

    #[Test]
    public function a_logo_url_prop_still_wins_over_the_config(): void
    {
        config(['ptah.brand.logo' => '/img/logo.svg']);

        $html = Blade::render('<x-forge-navbar logo-url="/outro.png" />');

        $this->assertStringContainsString('src="/outro.png"', $html);
        $this->assertStringNotContainsString('/img/logo.svg', $html);
    }

    #[Test]
    public function the_login_shows_the_logo_instead_of_the_initial(): void
    {
        config(['ptah.brand.logo' => '/img/logo.svg']);

        $html = view('ptah::layouts.forge-auth')->render();

        $this->assertStringContainsString('src="/img/logo.svg"', $html);
    }
}

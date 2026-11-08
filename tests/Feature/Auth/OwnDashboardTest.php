<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\MenuResolver;
use Ptah\Tests\TestCase;

/**
 * Laudo B2B (1.57.1): a host with its own dashboard sets
 * `ptah.auth.dashboard`; the package then registers no `ptah.dashboard`
 * route, so there is no duplicate name to break `route:cache`.
 */
class OwnDashboardTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.auth.dashboard', 'host.dashboard');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/painel', fn () => 'host dashboard')->name('host.dashboard');
    }

    #[Test]
    public function the_package_dashboard_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('ptah.dashboard'));
        $this->assertTrue(Route::has('host.dashboard'));
    }

    #[Test]
    public function every_route_name_is_unique_so_route_cache_works(): void
    {
        $names = array_filter(array_map(
            fn ($route) => $route->getName(),
            Route::getRoutes()->getRoutes(),
        ));

        $this->assertSame([], array_keys(array_filter(array_count_values($names), fn ($n) => $n > 1)));
    }

    #[Test]
    public function the_menu_dashboard_points_at_the_host_route(): void
    {
        $this->assertSame(url('/painel'), MenuResolver::dashboardUrl());
    }

    #[Test]
    public function a_url_works_too(): void
    {
        config(['ptah.auth.dashboard' => '/inicio']);

        $this->assertSame(url('/inicio'), MenuResolver::dashboardUrl());
    }

    #[Test]
    public function the_database_menu_uses_it(): void
    {
        config(['ptah.modules.menu' => true, 'ptah.menu.driver' => 'database']);

        $tree = MenuResolver::tree();

        $this->assertSame('Dashboard', $tree[0]['label']);
        $this->assertSame(url('/painel'), $tree[0]['url']);
    }
}

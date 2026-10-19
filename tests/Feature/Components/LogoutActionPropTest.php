<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * 1.46.2 (Engepecas, two identities): the customer portal reuses the sidebar,
 * whose "Sair" posted to the panel's logout — a customer on the `portal`
 * guard was logged out and sent to the panel's login. The host now says where
 * "Sair" and "Perfil" go; without the props nothing changes.
 */
class LogoutActionPropTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.modules.auth' => true]);
        if (! Route::has('ptah.auth.logout')) {
            Route::post('/admin/logout', fn () => '')->name('ptah.auth.logout');
        }
        if (! Route::has('ptah.profile')) {
            Route::get('/admin/profile', fn () => '')->name('ptah.profile');
        }
        Route::post('/sair', fn () => '')->name('portal.logout');
        Route::get('/minha-conta', fn () => '')->name('portal.profile');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Cliente']));
    }

    #[Test]
    public function the_sidebar_logs_out_where_the_host_says(): void
    {
        $html = Blade::render('<x-forge-sidebar :logout-action="$action" />', ['action' => route('portal.logout')]);

        $this->assertStringContainsString('action="'.route('portal.logout').'"', $html);
        $this->assertStringNotContainsString('action="'.route('ptah.auth.logout').'"', $html);
    }

    #[Test]
    public function the_navbar_takes_both_logout_and_profile(): void
    {
        $html = Blade::render('<x-forge-navbar :logout-action="$out" :profile-url="$profile" />', [
            'out' => route('portal.logout'),
            'profile' => route('portal.profile'),
        ]);

        $this->assertStringContainsString('action="'.route('portal.logout').'"', $html);
        $this->assertStringContainsString('href="'.route('portal.profile').'"', $html);
        $this->assertStringNotContainsString(route('ptah.profile'), $html);
    }

    #[Test]
    public function without_the_props_nothing_changes(): void
    {
        $this->assertStringContainsString('action="'.route('ptah.auth.logout').'"', Blade::render('<x-forge-sidebar />'));
        $this->assertStringContainsString('href="'.route('ptah.profile').'"', Blade::render('<x-forge-navbar />'));
    }

    #[Test]
    public function the_dashboard_layout_passes_them_on(): void
    {
        $html = Blade::render('<x-forge-dashboard-layout :logout-action="$out" :profile-url="$profile">x</x-forge-dashboard-layout>', [
            'out' => route('portal.logout'),
            'profile' => route('portal.profile'),
        ]);

        $this->assertSame(2, substr_count($html, 'action="'.route('portal.logout').'"'), 'Sidebar e navbar.');
        $this->assertStringNotContainsString('action="'.route('ptah.auth.logout').'"', $html);
        $this->assertStringContainsString('href="'.route('portal.profile').'"', $html);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * 1.46.5 (Engepecas, guards `web` + `portal`): a guest on a `portal` route
 * was sent to the panel login — the auth module redirected every guest to
 * `ptah.auth.login`, whatever guard the route required.
 */
class GuestRedirectTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.guards.portal', ['driver' => 'session', 'provider' => 'users']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/gr-panel', fn () => 'panel');
        Route::middleware(['web', 'auth:portal'])->get('/gr-portal', fn () => 'portal');
        Route::middleware('web')->get('/portal/entrar', fn () => 'portal login')->name('portal.login');
        Route::middleware('web')->get('/host-login', fn () => 'host login')->name('login');
    }

    #[Test]
    public function the_ptah_guard_still_goes_to_the_ptah_login(): void
    {
        $this->get('/gr-panel')->assertRedirect(route('ptah.auth.login'));
    }

    #[Test]
    public function another_guard_falls_back_to_laravel_not_the_panel_login(): void
    {
        $this->get('/gr-portal')->assertRedirect(route('login'));
    }

    #[Test]
    public function guest_redirect_sends_another_guard_where_the_host_says(): void
    {
        config(['ptah.auth.guest_redirect' => ['portal' => 'portal.login']]);
        $this->get('/gr-portal')->assertRedirect(route('portal.login'));

        config(['ptah.auth.guest_redirect' => '/portal/entrar']);
        $this->get('/gr-portal')->assertRedirect(url('/portal/entrar'));
    }

    #[Test]
    public function json_requests_get_a_401_not_a_redirect(): void
    {
        $this->getJson('/gr-portal')->assertUnauthorized();
    }
}

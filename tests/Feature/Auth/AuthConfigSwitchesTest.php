<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Tests\TestCase;

class AuthSwitchUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * 1.50.0: ptah.auth.two_factor / remember_me / session_protection were in the
 * config and documented, and read by nothing — `two_factor => false` did not
 * turn 2FA off.
 */
class AuthConfigSwitchesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', AuthSwitchUser::class);
    }

    private function user(): AuthSwitchUser
    {
        $u = AuthSwitchUser::create(['name' => 'A', 'email' => 'a'.uniqid().'@x.com', 'password' => bcrypt('secret')]);
        $this->actingAs($u);

        return $u;
    }

    #[Test]
    public function two_factor_off_hides_and_blocks_the_setup(): void
    {
        config(['ptah.auth.two_factor' => false]);
        $this->user();

        $page = Livewire::test(ProfilePage::class);
        $this->assertStringNotContainsString(__('ptah::ui.profile_tab_2fa'), $page->html());

        $page->call('enableEmailTwoFactor');
        $this->assertNull(auth()->user()->fresh()->two_factor_type);
    }

    #[Test]
    public function session_protection_off_hides_the_sessions_tab(): void
    {
        config(['ptah.auth.session_protection' => false]);
        $this->user();

        $this->assertStringNotContainsString(__('ptah::ui.profile_tab_sessions'), Livewire::test(ProfilePage::class)->html());
    }

    #[Test]
    public function remember_me_off_hides_the_checkbox(): void
    {
        config(['ptah.auth.remember_me' => false]);

        $this->assertStringNotContainsString('wire:model="remember"', Livewire::test(LoginPage::class)->html());
    }

    #[Test]
    public function the_defaults_keep_everything_on(): void
    {
        $this->assertStringContainsString('wire:model="remember"', Livewire::test(LoginPage::class)->html());

        $this->user();
        $html = Livewire::test(ProfilePage::class)->html();
        $this->assertStringContainsString(__('ptah::ui.profile_tab_2fa'), $html);
        $this->assertStringContainsString(__('ptah::ui.profile_tab_sessions'), $html);
    }
}

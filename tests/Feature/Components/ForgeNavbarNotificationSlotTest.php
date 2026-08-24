<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component as LivewireComponent;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * FASE 0 of the notification hook plan: the navbar bell slot has 3 states,
 * driven by `config('ptah.navbar.notifications')` through Ptah\Support\NavbarSlot.
 * See resources/views/components/forge-navbar.blade.php's "Notifications" block.
 */
class ForgeNavbarNotificationSlotTest extends TestCase
{
    private const STATIC_BELL_MARK = 'M15 17h5l-1.405-1.405';

    private function loginUser(): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');

        $user = $userClass::forceCreate([
            'name' => 'Navbar Tester',
            'email' => 'navbar-tester@test.com',
            'password' => bcrypt('secret'),
        ]);

        Auth::loginUsingId($user->id);
    }

    private function renderNavbar(): string
    {
        return (string) $this->blade('<x-forge-navbar appName="Test" />');
    }

    #[Test]
    public function default_state_renders_the_static_bell(): void
    {
        $this->loginUser();
        config(['ptah.navbar.notifications' => null]);

        $html = $this->renderNavbar();

        $this->assertStringContainsString(self::STATIC_BELL_MARK, $html);
    }

    #[Test]
    public function hidden_state_renders_neither_the_static_bell_nor_a_component(): void
    {
        $this->loginUser();
        config(['ptah.navbar.notifications' => 'none']);

        $html = $this->renderNavbar();

        $this->assertStringNotContainsString(self::STATIC_BELL_MARK, $html);
    }

    #[Test]
    public function component_state_renders_the_registered_component(): void
    {
        $this->loginUser();

        Livewire::component('navbar-slot-test-bell', (new class extends LivewireComponent
        {
            public function render()
            {
                return '<div>PTAH-NAVBAR-SLOT-TEST-MARK</div>';
            }
        })::class);

        config(['ptah.navbar.notifications' => 'navbar-slot-test-bell']);

        $html = $this->renderNavbar();

        $this->assertStringContainsString('PTAH-NAVBAR-SLOT-TEST-MARK', $html);
        $this->assertStringNotContainsString(self::STATIC_BELL_MARK, $html);
    }

    #[Test]
    public function an_unregistered_alias_falls_back_to_the_default_bell_without_throwing(): void
    {
        $this->loginUser();
        config(['ptah.navbar.notifications' => 'this-alias-does-not-exist']);

        $html = $this->renderNavbar();

        $this->assertStringContainsString(self::STATIC_BELL_MARK, $html);
    }
}

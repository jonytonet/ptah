<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

class StackAdminUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * The administration screens in the React/Vue stack (1.59.0): the same URLs
 * and route names as Livewire, answered by the stack page; the same locks;
 * and the top bar told what to offer.
 */
class StackAdminPagesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.company', true);
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('ptah.modules.menu', true);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
        $app['config']->set('ptah.permissions.cache', false);
        $app['config']->set('ptah.stack', 'react');
        $app['config']->set('auth.providers.users.model', StackAdminUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signIn(bool $master): StackAdminUser
    {
        $user = StackAdminUser::create(['name' => 'Ana', 'email' => uniqid('ana').'@example.test', 'password' => bcrypt('secret')]);

        if ($master) {
            $role = Role::create(['name' => 'Master', 'is_master' => true, 'is_active' => true]);
            UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        }
        $this->actingAs($user);

        return $user;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function pages(): array
    {
        return [
            'companies' => ['ptah.company.index', 'companies'],
            'departments' => ['ptah.acl.departments', 'departments'],
            'roles' => ['ptah.acl.roles', 'roles'],
            'pages' => ['ptah.acl.pages', 'pages'],
            'users' => ['ptah.acl.users', 'access-users'],
            'audit' => ['ptah.acl.audit', 'access-audit'],
            'guide' => ['ptah.acl.guide', 'permission-guide'],
            'menu' => ['ptah.menu.manage', 'menu'],
            'settings' => ['ptah.settings', 'settings'],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function an_administration_url_answers_with_the_stack_page(string $route, string $page): void
    {
        $this->signIn(master: true);

        $html = $this->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('id="ptah-app"', $html);
        $this->assertStringContainsString('data-page="'.$page.'"', $html);
        // Not the Livewire screen.
        $this->assertStringNotContainsString('wire:id', $html);
    }

    #[Test]
    #[DataProvider('pages')]
    public function the_page_keeps_the_lock_of_the_livewire_screen(string $route, string $page): void
    {
        $this->signIn(master: false);

        $this->get(route($route))->assertForbidden();
    }

    #[Test]
    public function the_top_bar_is_told_what_a_master_may_open(): void
    {
        $this->signIn(master: true);

        $shell = $this->getJson('/ptah/api/menu')->assertOk()->json('shell');

        $this->assertTrue($shell['company_switcher']);
        $urls = array_column($shell['admin'], 'url');
        foreach (['ptah.company.index', 'ptah.acl.departments', 'ptah.acl.roles', 'ptah.acl.pages', 'ptah.acl.users', 'ptah.acl.audit', 'ptah.acl.guide', 'ptah.menu.manage'] as $route) {
            $this->assertContains(route($route), $urls, $route);
        }
        $this->assertNotSame('', $shell['admin'][0]['label']);
    }

    #[Test]
    public function a_common_user_gets_no_administration_link(): void
    {
        $this->signIn(master: false);

        $shell = $this->getJson('/ptah/api/menu')->assertOk()->json('shell');

        $this->assertSame([], $shell['admin']);
        // The switcher is everyone's: it changes the company the user works in.
        $this->assertTrue($shell['company_switcher']);
    }
}

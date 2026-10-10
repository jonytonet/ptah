<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Navbar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

/**
 * The gear menu showed Companies, Roles, ACL, Menu... to ANY signed-in user;
 * the screens answered 403 (1.43.2). Each link now follows the gate of the
 * screen it opens.
 */
class AdminMenuGatingTest extends TestCase
{
    use ActsAsPtahUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.modules.company' => true, 'ptah.modules.permissions' => true, 'ptah.modules.menu' => true]);
        // As rotas do modulo de menu sao registradas no boot so com ele ligado.
        if (! Route::has('ptah.menu.manage')) {
            Route::get('/ptah-menu', fn () => '')->name('ptah.menu.manage');
            Route::getRoutes()->refreshNameLookups();
        }

        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');
        $this->actingAs($userClass::forceCreate(['name' => 'U', 'email' => 'u@example.test', 'password' => bcrypt('x')]));
    }

    #[Test]
    public function an_ordinary_user_sees_no_admin_link(): void
    {
        $this->actAsMaster(false);
        $html = Blade::render('<x-forge-navbar />');

        foreach (['navbar_admin_company', 'navbar_admin_roles', 'navbar_admin_audit', 'navbar_admin_menu'] as $key) {
            $this->assertStringNotContainsString(__('ptah::ui.'.$key), $html, "{$key} apareceu para quem leva 403.");
        }
        // O menu continua existindo para hospedar o seletor de empresas no celular.
        $this->assertStringContainsString('ptah-company-switcher-mobile', $html);
    }

    #[Test]
    public function a_master_sees_them(): void
    {
        $this->actAsMaster();
        $html = Blade::render('<x-forge-navbar />');

        foreach (['navbar_admin_company', 'navbar_admin_roles', 'navbar_admin_audit', 'navbar_admin_menu'] as $key) {
            $this->assertStringContainsString(__('ptah::ui.'.$key), $html);
        }
    }

    #[Test]
    public function without_the_company_module_an_ordinary_user_gets_no_gear_at_all(): void
    {
        config(['ptah.modules.company' => false]);
        $this->actAsMaster(false);

        $this->assertStringNotContainsString(__('ptah::ui.navbar_admin_title'), Blade::render('<x-forge-navbar />'));
    }
}

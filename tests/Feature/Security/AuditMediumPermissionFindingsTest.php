<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

/**
 * The permission MEDIUM findings of the 28/09/2026 surface audit (1.41.10).
 */
class AuditMediumPermissionFindingsTest extends TestCase
{
    use ActsAsPtahUser;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // O observer de UserRole so e registrado com o modulo ligado no boot.
        $app['config']->set('ptah.modules.permissions', true);
    }

    #[Test]
    public function changing_a_users_roles_forgets_their_cached_companies(): void
    {
        // Quem perdia o papel na empresa B ainda trocava para B por ate 1h.
        Cache::put('ptah_user_companies:5', ['stale'], 3600);
        $role = Role::create(['name' => 'Operador', 'is_active' => true]);

        $binding = UserRole::create(['user_id' => 5, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        $this->assertFalse(Cache::has('ptah_user_companies:5'), 'Criar o papel nao limpou o cache.');

        Cache::put('ptah_user_companies:5', ['stale'], 3600);
        $binding->delete();
        $this->assertFalse(Cache::has('ptah_user_companies:5'), 'Tirar o papel nao limpou o cache.');
    }

    #[Test]
    public function the_role_modal_user_cannot_be_forged(): void
    {
        // addRole() concedia papel ao id que o cliente escrevesse aqui.
        $this->actAsMaster();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(UserPermissionList::class)->set('bindingUserId', 999);
    }
}

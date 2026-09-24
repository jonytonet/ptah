<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Company;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Company\CompanyService;
use Ptah\Tests\Factories\CompanyFactory;
use Ptah\Tests\TestCase;

/** Plain stub on the shared `users` table (see tests/migrations). */
class SetActiveCompanyUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers CompanyService::setActive(). It used to call Cache::forget() on keys
 * from the pre-versioning cache scheme ("ptah_permissions:{u}:{c}:",
 * "ptah_is_master:{u}") — dead code matching nothing in the current
 * generation-based cache, i.e. a silent no-op left over from before the
 * cache-generation rework.
 */
class CompanyServiceTest extends TestCase
{
    private function actingAsUser(): SetActiveCompanyUser
    {
        $user = SetActiveCompanyUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function set_active_invalidates_the_current_permission_cache_generation_for_the_logged_in_user(): void
    {
        $company = CompanyFactory::new()->create();
        $user = $this->actingAsUser();

        // O usuario PERTENCE a empresa. Antes da 1.34.8 este teste trocava um
        // usuario sem papel nenhum para uma empresa qualquer — e passava,
        // porque setActive() nao conferia pertencimento. Era o defeito de
        // seguranca registrado como fixture. O que o teste mede e a invalidacao
        // do cache numa troca LEGITIMA.
        $role = Role::create(['name' => 'Operador', 'is_active' => true, 'is_master' => false]);
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        // Seed the per-user generation counter so the bump is observable.
        Cache::forever("ptah_perm_uver:{$user->id}", 1);

        app(CompanyService::class)->setActive($company->id);

        $this->assertSame(
            2,
            (int) Cache::get("ptah_perm_uver:{$user->id}"),
            'setActive() must bump the CURRENT versioned permission cache (ptah_perm_uver:{user}) for the logged-in user',
        );
    }

    #[Test]
    public function set_active_does_not_invalidate_another_users_cache(): void
    {
        $company = CompanyFactory::new()->create();
        $user = $this->actingAsUser();
        $otherUserId = $user->id + 1000;

        Cache::forever("ptah_perm_uver:{$user->id}", 1);
        Cache::forever("ptah_perm_uver:{$otherUserId}", 1);

        app(CompanyService::class)->setActive($company->id);

        $this->assertSame(1, (int) Cache::get("ptah_perm_uver:{$otherUserId}"));
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Covers `PermissionService::hasRole()` / `getRoleNames()` and the
 * `ptah_has_role()` helper — an IDENTITY check ("does this user hold this
 * role name"), deliberately NOT a gate: MASTER does not short-circuit it.
 */
class PermissionHasRoleTest extends TestCase
{
    private PermissionService $service;

    private int $userId = 200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PermissionService;
    }

    private function makeRole(string $name, bool $master = false, bool $active = true): Role
    {
        return Role::create(['name' => $name, 'is_master' => $master, 'is_active' => $active]);
    }

    private function assign(int $userId, Role $role, ?int $companyId = null, bool $active = true): UserRole
    {
        return UserRole::create([
            'user_id' => $userId, 'role_id' => $role->id,
            'company_id' => $companyId, 'is_active' => $active,
        ]);
    }

    #[Test]
    public function it_reports_a_role_the_user_actually_holds(): void
    {
        $role = $this->makeRole('Vendas');
        $this->assign($this->userId, $role);

        $this->assertTrue($this->service->hasRole($this->userId, 'Vendas'));
        $this->assertTrue(ptah_has_role('Vendas', $this->userId));
    }

    #[Test]
    public function it_denies_a_role_the_user_does_not_hold(): void
    {
        $role = $this->makeRole('Vendas');
        $this->assign($this->userId, $role);

        $this->assertFalse($this->service->hasRole($this->userId, 'Financeiro'));
    }

    #[Test]
    public function array_of_roles_is_an_or_match(): void
    {
        $role = $this->makeRole('Estoque');
        $this->assign($this->userId, $role);

        $this->assertTrue($this->service->hasRole($this->userId, ['Financeiro', 'Estoque']));
        $this->assertFalse($this->service->hasRole($this->userId, ['Financeiro', 'RH']));
    }

    #[Test]
    public function an_inactive_user_role_binding_does_not_count(): void
    {
        $role = $this->makeRole('Vendas');
        $this->assign($this->userId, $role, active: false);

        $this->assertFalse($this->service->hasRole($this->userId, 'Vendas'));
    }

    #[Test]
    public function a_soft_deleted_user_role_binding_does_not_count(): void
    {
        $role = $this->makeRole('Vendas');
        $ur = $this->assign($this->userId, $role);
        $ur->delete();

        $this->assertFalse($this->service->hasRole($this->userId, 'Vendas'));
    }

    #[Test]
    public function an_inactive_role_does_not_count(): void
    {
        $role = $this->makeRole('Vendas', active: false);
        $this->assign($this->userId, $role);

        $this->assertFalse($this->service->hasRole($this->userId, 'Vendas'));
    }

    #[Test]
    public function master_role_does_not_satisfy_a_check_for_a_different_role_name(): void
    {
        $master = $this->makeRole('Administrador Master', master: true);
        $this->assign($this->userId, $master);

        // MASTER bypasses PERMISSIONS (check()/isMaster()) but does not
        // "hold" an unrelated role name — hasRole() is identity, not a gate.
        $this->assertFalse($this->service->hasRole($this->userId, 'Vendas'));
        $this->assertTrue($this->service->isMaster($this->userId), 'sanity: the user really is master');
        $this->assertTrue($this->service->hasRole($this->userId, 'Administrador Master'));
    }

    #[Test]
    public function match_is_case_insensitive_and_trimmed_but_separators_are_significant(): void
    {
        // A 1a versao tambem casava por Str::slug — 'Vendas-SP' viraria igual a
        // 'vendas sp', e dois papeis distintos que diferem so pelo separador
        // colidiriam (achado de revisao, Onda III). hasRole e identidade: match
        // errado = branch errado no app hospedeiro. Case e espacos nas bordas
        // sao tolerados; separadores NAO sao classe de equivalencia.
        $role = $this->makeRole('Vendas Externas');
        $this->assign($this->userId, $role);

        $this->assertTrue($this->service->hasRole($this->userId, 'VENDAS EXTERNAS'));
        $this->assertTrue($this->service->hasRole($this->userId, '  vendas externas  '));
        $this->assertFalse($this->service->hasRole($this->userId, 'vendas-externas'));
    }

    #[Test]
    public function roles_differing_only_by_separator_do_not_collide(): void
    {
        $sp = $this->makeRole('Vendas-SP');
        $this->assign($this->userId, $sp);

        $this->assertTrue($this->service->hasRole($this->userId, 'Vendas-SP'));
        $this->assertFalse($this->service->hasRole($this->userId, 'vendas sp'));
    }

    #[Test]
    public function role_binding_is_scoped_by_company(): void
    {
        $role = $this->makeRole('Vendas');
        $this->assign($this->userId, $role, companyId: 5);

        $this->assertTrue($this->service->hasRole($this->userId, 'Vendas', 5));
        $this->assertFalse($this->service->hasRole($this->userId, 'Vendas', 7));
    }

    #[Test]
    public function a_guest_never_has_any_role(): void
    {
        $this->assertFalse($this->service->hasRole(null, 'Vendas'));
        $this->assertSame([], $this->service->getRoleNames(null));
    }
}

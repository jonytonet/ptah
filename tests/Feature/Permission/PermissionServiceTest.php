<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Services\Permission\RoleService;
use Ptah\Tests\TestCase;

/**
 * Security-focused tests for the permission engine. The headline case is
 * immediate revocation: changing a role's permissions must take effect on the
 * next check, not after the cache TTL — the bug the cache-generation rework fixed.
 */
class PermissionServiceTest extends TestCase
{
    private PermissionService $service;

    private int $userId = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PermissionService;

        $page = PtahPage::create(['slug' => 'products', 'name' => 'Products', 'is_active' => true]);
        PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'products.index', 'obj_label' => 'Products list',
            'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true,
        ]);
    }

    private function makeRole(bool $master = false): Role
    {
        return Role::create(['name' => 'R'.uniqid(), 'is_master' => $master, 'is_active' => true]);
    }

    private function assign(int $userId, Role $role, ?int $companyId = null): void
    {
        UserRole::create([
            'user_id' => $userId, 'role_id' => $role->id,
            'company_id' => $companyId, 'is_active' => true,
        ]);
    }

    private function grant(Role $role, string $objKey, array $flags): RolePermission
    {
        $obj = PageObject::where('obj_key', $objKey)->firstOrFail();

        return RolePermission::create(array_merge([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false,
        ], $flags));
    }

    // ── Basic checks ───────────────────────────────────────────────────────────

    #[Test]
    public function guest_is_denied_by_default(): void
    {
        config(['ptah.permissions.allow_guest' => false]);
        $this->assertFalse($this->service->check(null, 'products.index', 'read'));
    }

    #[Test]
    public function grants_only_the_actions_marked_true(): void
    {
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, 'products.index', ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'delete'));
        $this->assertFalse($this->service->check($this->userId, 'unknown.key', 'read'));
    }

    #[Test]
    public function master_role_passes_everything(): void
    {
        $this->assign($this->userId, $this->makeRole(master: true));

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'delete'));
        $this->assertTrue($this->service->check($this->userId, 'anything.at.all', 'create'));
        $this->assertTrue($this->service->isMaster($this->userId));
    }

    #[Test]
    public function multiple_roles_combine_with_or_logic(): void
    {
        $reader = $this->makeRole();
        $editor = $this->makeRole();
        $this->assign($this->userId, $reader);
        $this->assign($this->userId, $editor);
        $this->grant($reader, 'products.index', ['can_read' => true]);
        $this->grant($editor, 'products.index', ['can_update' => true]);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'update'));
    }

    // ── Action whitelist (SQLi guard) ───────────────────────────────────────────

    #[Test]
    public function invalid_action_is_rejected_without_touching_the_query(): void
    {
        $this->assign($this->userId, $this->makeRole(master: true));

        // Even a MASTER must get false for a non-whitelisted action string.
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'create) OR 1=1 --'));
        $this->assertSame([], $this->service->getCompaniesForResource($this->userId, 'products.index', 'drop'));
    }

    // ── Immediate revocation (the cache-generation fix) ─────────────────────────

    #[Test]
    public function revoking_a_permission_takes_effect_immediately(): void
    {
        config(['ptah.permissions.cache' => true]);

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $roleService = new RoleService;
        $obj = PageObject::where('obj_key', 'products.index')->first();

        // Grant delete and prime the cache with a positive check.
        $roleService->bindPageObject($role, $obj->id, ['can_read' => true, 'can_delete' => true]);
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'delete'));

        // Revoke delete — must be denied on the very next check, not after TTL.
        $roleService->bindPageObject($role, $obj->id, ['can_read' => true, 'can_delete' => false]);
        $this->assertFalse(
            $this->service->check($this->userId, 'products.index', 'delete'),
            'Revocation must be immediate — stale cache would keep delete granted',
        );
    }

    #[Test]
    public function unbinding_an_object_takes_effect_immediately(): void
    {
        config(['ptah.permissions.cache' => true]);

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $roleService = new RoleService;
        $obj = PageObject::where('obj_key', 'products.index')->first();

        $roleService->bindPageObject($role, $obj->id, ['can_read' => true]);
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));

        $roleService->unbindPageObject($role, $obj->id);
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));
    }

    #[Test]
    public function detaching_a_role_takes_effect_immediately(): void
    {
        config(['ptah.permissions.cache' => true]);

        $role = $this->makeRole();
        $this->grant($role, 'products.index', ['can_read' => true]);
        $this->service->syncRole($this->userId, $role->id);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));

        $this->service->detachRole($this->userId, $role->id);
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));
    }

    // ── Multi-company isolation ─────────────────────────────────────────────────

    #[Test]
    public function company_scoped_role_does_not_leak_to_other_companies(): void
    {
        config(['ptah.permissions.multi_company' => true]);

        $role = $this->makeRole();
        $this->assign($this->userId, $role, companyId: 5);
        $this->grant($role, 'products.index', ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read', 5));
        $this->assertFalse(
            $this->service->check($this->userId, 'products.index', 'read', 7),
            'A role bound to company 5 must not grant access in company 7',
        );
    }

    #[Test]
    public function global_role_grants_across_all_companies(): void
    {
        config(['ptah.permissions.multi_company' => true]);

        // company_id = null → cross-tenant role by design.
        $role = $this->makeRole();
        $this->assign($this->userId, $role, companyId: null);
        $this->grant($role, 'products.index', ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read', 5));
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read', 9));
    }
}

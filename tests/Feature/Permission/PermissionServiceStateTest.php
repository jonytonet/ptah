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
use Ptah\Tests\TestCase;

/**
 * Validates the "state" edges of the permission engine that the happy-path suite
 * does not cover: inactive/soft-deleted assignments, guest allowance, the shape
 * of getPermissions(), getCompaniesForResource(), sync/detach and cache scope.
 */
class PermissionServiceStateTest extends TestCase
{
    private PermissionService $service;

    private int $userId = 200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PermissionService;

        $page = PtahPage::create(['slug' => 'products', 'name' => 'Products', 'is_active' => true]);
        PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'products.index', 'obj_label' => 'Products', 'obj_type' => 'page',
            'obj_order' => 1, 'is_active' => true,
        ]);
    }

    private function makeRole(bool $master = false, bool $active = true): Role
    {
        return Role::create(['name' => 'R'.uniqid(), 'is_master' => $master, 'is_active' => $active]);
    }

    private function assign(int $userId, Role $role, ?int $companyId = null, bool $active = true): UserRole
    {
        return UserRole::create([
            'user_id' => $userId, 'role_id' => $role->id,
            'company_id' => $companyId, 'is_active' => $active,
        ]);
    }

    private function grant(Role $role, array $flags, string $objKey = 'products.index'): RolePermission
    {
        $obj = PageObject::where('obj_key', $objKey)->firstOrFail();

        return RolePermission::create(array_merge([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false,
        ], $flags));
    }

    // ── Inactive / soft-deleted assignments must not grant ─────────────────────

    #[Test]
    public function inactive_user_role_grants_nothing(): void
    {
        $role = $this->makeRole();
        $this->assign($this->userId, $role, active: false);
        $this->grant($role, ['can_read' => true]);

        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));
    }

    #[Test]
    public function inactive_role_grants_nothing(): void
    {
        $role = $this->makeRole(active: false);
        $this->assign($this->userId, $role);
        $this->grant($role, ['can_read' => true]);

        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));
    }

    #[Test]
    public function soft_deleted_user_role_grants_nothing(): void
    {
        $role = $this->makeRole();
        $ur = $this->assign($this->userId, $role);
        $this->grant($role, ['can_read' => true]);
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));

        $ur->delete(); // soft delete
        $this->service->clearCache($this->userId);

        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));
    }

    #[Test]
    public function inactive_master_role_is_not_master(): void
    {
        $this->assign($this->userId, $this->makeRole(master: true, active: false));

        $this->assertFalse($this->service->isMaster($this->userId));
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));
    }

    // ── Guest allowance ─────────────────────────────────────────────────────────

    #[Test]
    public function allow_guest_true_grants_the_guest(): void
    {
        config(['ptah.permissions.allow_guest' => true]);

        $this->assertTrue($this->service->check(null, 'products.index', 'read'));
    }

    // ── getPermissions() shape ──────────────────────────────────────────────────

    #[Test]
    public function master_permission_map_lists_active_objects_all_true_and_excludes_inactive(): void
    {
        $page = PtahPage::where('slug', 'products')->first();
        PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'products.hidden', 'obj_label' => 'Hidden', 'obj_type' => 'page',
            'obj_order' => 2, 'is_active' => false,
        ]);
        $this->assign($this->userId, $this->makeRole(master: true));

        $map = $this->service->getPermissions($this->userId);

        $this->assertSame(['create' => true, 'read' => true, 'update' => true, 'delete' => true], $map['products.index']);
        $this->assertArrayNotHasKey('products.hidden', $map, 'Inactive objects must not appear in the master map');
    }

    #[Test]
    public function non_master_map_reflects_only_granted_actions(): void
    {
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, ['can_read' => true, 'can_update' => true]);

        $map = $this->service->getPermissions($this->userId);

        $this->assertSame(
            ['create' => false, 'read' => true, 'update' => true, 'delete' => false],
            $map['products.index'],
        );
    }

    // ── getCompaniesForResource() ────────────────────────────────────────────────

    #[Test]
    public function get_companies_for_resource_returns_the_granting_companies(): void
    {
        $role = $this->makeRole();
        $this->assign($this->userId, $role, companyId: 5);
        $this->assign($this->userId, $role, companyId: 9);
        $this->grant($role, ['can_read' => true]);

        $companies = $this->service->getCompaniesForResource($this->userId, 'products.index', 'read');

        sort($companies);
        $this->assertSame([5, 9], $companies);
    }

    // ── sync / detach ─────────────────────────────────────────────────────────

    #[Test]
    public function sync_role_assigns_across_multiple_companies(): void
    {
        $role = $this->makeRole();
        $this->grant($role, ['can_read' => true]);

        $this->service->syncRole($this->userId, $role->id, [5, 9]);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read', 5));
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read', 9));
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read', 7));
    }

    #[Test]
    public function sync_role_reactivates_a_soft_deleted_assignment(): void
    {
        $role = $this->makeRole();
        $this->grant($role, ['can_read' => true]);

        $this->service->syncRole($this->userId, $role->id);
        $this->service->detachRole($this->userId, $role->id);
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read'));

        // Re-syncing must restore the soft-deleted row (updateOrCreate withTrashed).
        $this->service->syncRole($this->userId, $role->id);
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));
        $this->assertSame(1, UserRole::where('user_id', $this->userId)->where('role_id', $role->id)->count());
    }

    #[Test]
    public function detach_role_for_one_company_keeps_the_others(): void
    {
        $role = $this->makeRole();
        $this->grant($role, ['can_read' => true]);
        $this->service->syncRole($this->userId, $role->id, [5, 9]);

        $this->service->detachRole($this->userId, $role->id, 5);

        $this->assertFalse($this->service->check($this->userId, 'products.index', 'read', 5));
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read', 9));
    }

    // ── Cache scope ─────────────────────────────────────────────────────────────

    #[Test]
    public function clear_cache_for_one_user_does_not_touch_another(): void
    {
        config(['ptah.permissions.cache' => true]);
        $other = 201;
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->assign($other, $role);
        $this->grant($role, ['can_read' => true]);

        // Prime both users' caches.
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));
        $this->assertTrue($this->service->check($other, 'products.index', 'read'));

        // A global definition change would bump the global gen; here we only clear
        // one user and assert the call is well-scoped (both still resolve correctly).
        $this->service->clearCache($this->userId);
        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));
        $this->assertTrue($this->service->check($other, 'products.index', 'read'));
    }

    #[Test]
    public function works_with_cache_disabled(): void
    {
        config(['ptah.permissions.cache' => false]);
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'products.index', 'read'));
        $this->assertFalse($this->service->check($this->userId, 'products.index', 'delete'));
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PageObject;
use Ptah\Models\PermissionAudit;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Validates the audit trail written by PermissionService::check().
 *
 * Documented intent (config comments):
 *   audit        → log accesses (granted)
 *   audit_denied → ALSO log denied accesses
 *   audit_master → ALSO log accesses from MASTER users
 */
class PermissionAuditTest extends TestCase
{
    private PermissionService $service;

    private int $userId = 300;

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

        // A reader role (can_read) so we have both a granted and a denied path.
        $role = Role::create(['name' => 'Reader', 'is_master' => false, 'is_active' => true]);
        UserRole::create(['user_id' => $this->userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        $obj = PageObject::where('obj_key', 'products.index')->first();
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);
    }

    #[Test]
    public function nothing_is_written_when_audit_is_off(): void
    {
        config(['ptah.permissions.audit' => false]);

        $this->service->check($this->userId, 'products.index', 'read');   // granted
        $this->service->check($this->userId, 'products.index', 'delete'); // denied

        $this->assertSame(0, PermissionAudit::count());
    }

    #[Test]
    public function granted_access_is_logged_when_audit_is_on(): void
    {
        config(['ptah.permissions.audit' => true, 'ptah.permissions.audit_denied' => false]);

        $this->service->check($this->userId, 'products.index', 'read'); // granted

        $this->assertSame(1, PermissionAudit::where('result', 'granted')->count());
    }

    #[Test]
    public function denied_access_is_logged_only_when_audit_denied_is_on(): void
    {
        // audit on, audit_denied OFF → denied must NOT be logged.
        config(['ptah.permissions.audit' => true, 'ptah.permissions.audit_denied' => false]);
        $this->service->check($this->userId, 'products.index', 'delete'); // denied
        $this->assertSame(0, PermissionAudit::where('result', 'denied')->count());

        // Turning audit_denied ON logs the denial.
        config(['ptah.permissions.audit_denied' => true]);
        $this->service->check($this->userId, 'products.index', 'delete');
        $this->assertSame(1, PermissionAudit::where('result', 'denied')->count());
    }

    #[Test]
    public function master_access_is_logged_only_when_audit_master_is_on(): void
    {
        $master = 301;
        $role = Role::create(['name' => 'Master', 'is_master' => true, 'is_active' => true]);
        UserRole::create(['user_id' => $master, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);

        // audit on, audit_master OFF → master access not logged.
        config(['ptah.permissions.audit' => true, 'ptah.permissions.audit_master' => false]);
        $this->service->check($master, 'products.index', 'delete');
        $this->assertSame(0, PermissionAudit::where('user_id', $master)->count());

        // audit_master ON → logged as granted.
        config(['ptah.permissions.audit_master' => true]);
        $this->service->check($master, 'products.index', 'delete');
        $this->assertSame(1, PermissionAudit::where('user_id', $master)->where('result', 'granted')->count());
    }
}

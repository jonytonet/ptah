<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Permission\AuditList;
use Ptah\Livewire\Permission\DepartmentList;
use Ptah\Livewire\Permission\PageList;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * The ACL-management screens (roles, pages, users-ACL, audit, departments) are
 * reachable only through the `ptah.master` route middleware — but Livewire 4
 * does not reapply custom route middleware to the AJAX requests a mounted
 * component makes afterwards. Each component's boot() must re-validate MASTER
 * access (RequiresMasterAccess::assertMasterAccess()) on every request, not
 * only the initial mount, so a user whose MASTER role is revoked mid-session
 * (or a crafted request against an already-mounted snapshot) is still denied.
 */
class AclComponentsReauthorizeTest extends TestCase
{
    private function mockMaster(bool $isMaster): void
    {
        $stub = new class($isMaster) extends PermissionService
        {
            public function __construct(private bool $master) {}

            public function isMaster(mixed $user = null): bool
            {
                return $this->master;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }

    /** @return array<int, class-string> */
    public static function componentsProvider(): array
    {
        return [
            'RoleList' => [RoleList::class],
            'PageList' => [PageList::class],
            'DepartmentList' => [DepartmentList::class],
            'UserPermissionList' => [UserPermissionList::class],
            'AuditList' => [AuditList::class],
            'PermissionGuide' => [PermissionGuide::class],
        ];
    }

    #[Test]
    public function every_acl_component_is_forbidden_for_a_non_master_user(): void
    {
        $this->mockMaster(false);

        foreach (self::componentsProvider() as $component) {
            Livewire::test($component[0])->assertStatus(403);
        }
    }

    #[Test]
    public function every_acl_component_mounts_for_a_master_user(): void
    {
        $this->mockMaster(true);

        foreach (self::componentsProvider() as $component) {
            Livewire::test($component[0])->assertOk();
        }
    }

    /**
     * The core regression: boot() must run on EVERY request, not only mount().
     * Simulates a MASTER role revoked mid-session — the component was mounted
     * while still master, then a mutating call happens after the flag flips.
     */
    #[Test]
    public function a_mutating_call_is_forbidden_once_master_access_is_revoked_mid_session(): void
    {
        $this->mockMaster(true);
        $component = Livewire::test(RoleList::class)->assertOk();

        $this->mockMaster(false);

        $component->call('create')->assertStatus(403);
    }
}

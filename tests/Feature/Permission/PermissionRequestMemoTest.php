<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Services\Permission\RoleService;
use Ptah\Tests\TestCase;

/**
 * Covers the request-scoped memo added to `PermissionService` (isMasterById,
 * getPermissions, getQualifiedPermissions, getRoleNames): repeated lookups
 * within the same request/instance skip the database entirely, while a
 * mid-request revocation is still seen on the very next call — because the
 * memo key embeds the SAME live generation counters `cacheKey()` already
 * used, so a bump (from any PermissionService instance, since the counters
 * live in the shared Cache store) changes the key and misses the memo.
 */
class PermissionRequestMemoTest extends TestCase
{
    private int $userId = 400;

    private function makeRole(): Role
    {
        return Role::create(['name' => 'R'.uniqid(), 'is_master' => false, 'is_active' => true]);
    }

    private function makePageObject(string $key): PageObject
    {
        $page = PtahPage::create(['slug' => 'page-'.uniqid(), 'name' => 'p', 'is_active' => true]);

        return PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => $key, 'obj_label' => $key,
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
    }

    private function countQueries(\Closure $callback): int
    {
        $count = 0;
        $listener = function () use (&$count) {
            $count++;
        };

        DB::listen($listener);
        $callback();

        return $count;
    }

    #[Test]
    public function two_consecutive_checks_hit_the_database_only_once(): void
    {
        $role = $this->makeRole();
        UserRole::create(['user_id' => $this->userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        $obj = $this->makePageObject('memo.key');
        (new RoleService)->bindPageObject($role, $obj->id, ['can_read' => true]);

        $service = new PermissionService;

        // First call warms the memo (and the underlying cache) — some DB work is expected.
        $service->check($this->userId, 'memo.key', 'read');

        // Second call, same user/company/action: must not touch the database at all.
        $queriesOnSecondCall = $this->countQueries(function () use ($service) {
            $this->assertTrue($service->check($this->userId, 'memo.key', 'read'));
        });

        $this->assertSame(0, $queriesOnSecondCall, 'A repeated check() within the same request must not hit the database again');
    }

    #[Test]
    public function a_mid_request_revocation_via_role_service_is_seen_by_the_next_check(): void
    {
        $service = new PermissionService;
        // Sharing the SAME PermissionService instance with RoleService mirrors
        // production wiring (both resolved from the container as the same
        // singleton within one request).
        $roleService = new RoleService($service);

        $role = $this->makeRole();
        UserRole::create(['user_id' => $this->userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        $obj = $this->makePageObject('memo.key');

        $roleService->bindPageObject($role, $obj->id, ['can_read' => true]);
        $this->assertTrue($service->check($this->userId, 'memo.key', 'read'));

        $roleService->bindPageObject($role, $obj->id, ['can_read' => false]);

        $this->assertFalse(
            $service->check($this->userId, 'memo.key', 'read'),
            'A revocation made mid-request (even via the same, memoized instance) must be visible on the very next check()',
        );
    }

    #[Test]
    public function the_memo_does_not_leak_between_different_user_ids(): void
    {
        $service = new PermissionService;

        $role = $this->makeRole();
        $obj = $this->makePageObject('memo.key');
        (new RoleService)->bindPageObject($role, $obj->id, ['can_read' => true]);

        $grantedUserId = $this->userId;
        $otherUserId = $this->userId + 1;

        UserRole::create(['user_id' => $grantedUserId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);

        $this->assertTrue($service->check($grantedUserId, 'memo.key', 'read'));
        $this->assertFalse($service->check($otherUserId, 'memo.key', 'read'), 'A different user must not see the granted user\'s memoized result');
        $this->assertTrue($service->check($grantedUserId, 'memo.key', 'read'), 'The granted user\'s own memoized result must remain correct afterwards');
    }

    #[Test]
    public function with_cache_disabled_the_query_count_still_collapses_and_revocation_stays_immediate(): void
    {
        config(['ptah.permissions.cache' => false]);

        $service = new PermissionService;
        $roleService = new RoleService($service);

        $role = $this->makeRole();
        UserRole::create(['user_id' => $this->userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        $obj = $this->makePageObject('memo.key');
        $roleService->bindPageObject($role, $obj->id, ['can_read' => true]);

        $service->check($this->userId, 'memo.key', 'read');

        $queriesOnSecondCall = $this->countQueries(function () use ($service) {
            $this->assertTrue($service->check($this->userId, 'memo.key', 'read'));
        });

        $this->assertSame(
            0,
            $queriesOnSecondCall,
            'Even with the byte-level cache disabled, a repeated check() within the same request must be memoized',
        );

        // Revocation must still be immediate — the memo must not mask it.
        $roleService->bindPageObject($role, $obj->id, ['can_read' => false]);
        $this->assertFalse($service->check($this->userId, 'memo.key', 'read'));
    }
}

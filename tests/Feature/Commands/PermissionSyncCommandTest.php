<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Services\Permission\RoleService;
use Ptah\Tests\TestCase;

/**
 * Covers ptah:permission:sync — the turnkey bridge between a BaseCrud config's
 * permissionIdentifier and the RBAC tables (ptah_pages / ptah_page_objects /
 * ptah_role_permissions). Without this, an admin has to hand-create those rows
 * before a configured permissionIdentifier can ever be granted.
 */
class PermissionSyncCommandTest extends TestCase
{
    private function seedConfig(string $model, string $permissionKey, array $extra = []): void
    {
        CrudConfig::create([
            'model' => $model,
            'route' => '',
            'config' => array_merge([
                'displayName' => $model,
                'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']],
                'permissions' => ['permissionIdentifier' => $permissionKey],
            ], $extra),
        ]);
    }

    #[Test]
    public function it_creates_page_and_object_for_each_configured_permission_key(): void
    {
        $this->seedConfig('Widget', 'pageWidget');

        $this->artisan('ptah:permission:sync')->assertExitCode(0);

        $this->assertDatabaseHas('ptah_pages', ['slug' => 'Widget']);
        $page = PtahPage::where('slug', 'Widget')->firstOrFail();
        $this->assertDatabaseHas('ptah_page_objects', [
            'page_id' => $page->id,
            'section' => 'main',
            'obj_key' => 'pageWidget',
        ]);
    }

    #[Test]
    public function it_falls_back_to_the_canonical_model_key_when_display_name_is_missing(): void
    {
        CrudConfig::create([
            'model' => 'Widget',
            'route' => '',
            'config' => [
                'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']],
                'permissions' => ['permissionIdentifier' => 'pageWidget'],
            ],
        ]);

        $this->artisan('ptah:permission:sync')->assertExitCode(0);

        $this->assertDatabaseHas('ptah_pages', ['slug' => 'Widget', 'name' => 'Widget']);
    }

    #[Test]
    public function it_falls_back_to_the_legacy_identifier_key(): void
    {
        $this->seedConfig('Widget', '', ['permissions' => ['identifier' => 'pageWidgetLegacy']]);

        $this->artisan('ptah:permission:sync')->assertExitCode(0);

        $this->assertDatabaseHas('ptah_page_objects', ['obj_key' => 'pageWidgetLegacy']);
    }

    #[Test]
    public function it_skips_configs_without_a_permission_key(): void
    {
        $this->seedConfig('Widget', '', ['permissions' => []]);

        $this->artisan('ptah:permission:sync')
            ->expectsOutputToContain('1 skipped')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ptah_pages', ['slug' => 'Widget']);
    }

    #[Test]
    public function it_grants_a_role_when_role_and_grant_are_given(): void
    {
        $this->seedConfig('Widget', 'pageWidget');
        $role = Role::create(['name' => 'Editor', 'is_active' => true]);

        $this->artisan('ptah:permission:sync --role=Editor --grant=create,read')->assertExitCode(0);

        $pageObject = PageObject::where('obj_key', 'pageWidget')->firstOrFail();
        $this->assertDatabaseHas('ptah_role_permissions', [
            'role_id' => $role->id,
            'page_object_id' => $pageObject->id,
            'can_create' => 1,
            'can_read' => 1,
            'can_update' => 0,
            'can_delete' => 0,
        ]);
    }

    #[Test]
    public function grant_all_maps_to_every_action(): void
    {
        $this->seedConfig('Widget', 'pageWidget');
        $role = Role::create(['name' => 'SuperEditor', 'is_active' => true]);

        $this->artisan('ptah:permission:sync --role=SuperEditor --grant=all')->assertExitCode(0);

        $pageObject = PageObject::where('obj_key', 'pageWidget')->firstOrFail();
        $this->assertDatabaseHas('ptah_role_permissions', [
            'role_id' => $role->id,
            'page_object_id' => $pageObject->id,
            'can_create' => 1,
            'can_read' => 1,
            'can_update' => 1,
            'can_delete' => 1,
        ]);
    }

    #[Test]
    public function unknown_role_fails(): void
    {
        $this->seedConfig('Widget', 'pageWidget');

        $this->artisan('ptah:permission:sync --role=DoesNotExist --grant=read')
            ->assertExitCode(1);
    }

    #[Test]
    public function invalid_grant_action_fails(): void
    {
        $this->seedConfig('Widget', 'pageWidget');
        Role::create(['name' => 'Editor', 'is_active' => true]);

        $this->artisan('ptah:permission:sync --role=Editor --grant=fly')
            ->assertExitCode(1);
    }

    #[Test]
    public function role_without_grant_fails(): void
    {
        $this->seedConfig('Widget', 'pageWidget');
        Role::create(['name' => 'Editor', 'is_active' => true]);

        $this->artisan('ptah:permission:sync --role=Editor')->assertExitCode(1);
    }

    #[Test]
    public function rerun_is_idempotent(): void
    {
        $this->seedConfig('Widget', 'pageWidget');
        $role = Role::create(['name' => 'Editor', 'is_active' => true]);

        $this->artisan('ptah:permission:sync --role=Editor --grant=read')->assertExitCode(0);
        $this->artisan('ptah:permission:sync --role=Editor --grant=create')->assertExitCode(0);

        $this->assertSame(1, PtahPage::where('slug', 'Widget')->count());
        $this->assertSame(1, PageObject::where('obj_key', 'pageWidget')->count());
        $this->assertSame(1, RolePermission::where('role_id', $role->id)->count());

        $pageObject = PageObject::where('obj_key', 'pageWidget')->firstOrFail();
        // Second run's --grant=create replaces (upserts) the binding, so read from
        // the first run is no longer present, but the row itself was not duplicated.
        $this->assertDatabaseHas('ptah_role_permissions', [
            'role_id' => $role->id,
            'page_object_id' => $pageObject->id,
            'can_create' => 1,
            'can_read' => 0,
        ]);
    }

    #[Test]
    public function a_failure_mid_batch_rolls_back_the_whole_run(): void
    {
        $this->seedConfig('Widget1', 'pageWidget1');
        $this->seedConfig('Widget2', 'pageWidget2');
        Role::create(['name' => 'Editor', 'is_active' => true]);

        // Succeeds for the first grant, blows up on the second — simulating a
        // failure in the middle of a batch run.
        $fake = new class extends RoleService
        {
            public int $calls = 0;

            public function bindPageObject(Role $role, int $pageObjectId, array $permissions = []): RolePermission
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new \RuntimeException('simulated failure');
                }

                return parent::bindPageObject($role, $pageObjectId, $permissions);
            }
        };
        $this->app->instance(RoleService::class, $fake);

        try {
            Artisan::call('ptah:permission:sync', ['--role' => 'Editor', '--grant' => 'read']);
            $this->fail('Expected the simulated failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        // The whole batch rolled back — Widget1's page/object, created before the
        // failure, do not survive either. No partial state from this run.
        $this->assertDatabaseMissing('ptah_pages', ['slug' => 'Widget1']);
        $this->assertDatabaseMissing('ptah_pages', ['slug' => 'Widget2']);
        $this->assertDatabaseCount('ptah_page_objects', 0);
        $this->assertDatabaseCount('ptah_role_permissions', 0);
    }

    #[Test]
    public function dry_run_does_not_persist_anything(): void
    {
        $this->seedConfig('Widget', 'pageWidget');
        Role::create(['name' => 'Editor', 'is_active' => true]);

        $this->artisan('ptah:permission:sync --role=Editor --grant=read --dry-run')
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ptah_pages', ['slug' => 'Widget']);
        $this->assertDatabaseCount('ptah_page_objects', 0);
        $this->assertDatabaseCount('ptah_role_permissions', 0);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Permission;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PageObject;
use Ptah\Models\PermissionAudit;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\ColumnPermissionService;
use Ptah\Tests\TestCase;

/**
 * Covers ColumnPermissionService::apply() in isolation — the per-column
 * `colsPermission` gate consumed by HasCrudLifecycle. No Livewire component
 * involved here (see ColumnPermissionScreenTest / ColumnPermissionTamperTest
 * for the end-to-end wiring through BaseCrud).
 */
class ColumnPermissionServiceTest extends TestCase
{
    private ColumnPermissionService $service;

    private int $userId = 400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ColumnPermissionService::class);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

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

    private function grant(Role $role, PageObject $obj, array $flags): RolePermission
    {
        return RolePermission::create(array_merge([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false,
        ], $flags));
    }

    private function makePage(string $slug): PtahPage
    {
        return PtahPage::create(['slug' => $slug, 'name' => $slug, 'is_active' => true]);
    }

    private function makeObject(PtahPage $page, string $key, string $section = 'main', bool $active = true): PageObject
    {
        return PageObject::create([
            'page_id' => $page->id, 'section' => $section,
            'obj_key' => $key, 'obj_label' => $key,
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => $active,
        ]);
    }

    private function col(string $field, mixed $tag = null): array
    {
        $col = ['colsNomeFisico' => $field, 'colsNomeLogico' => $field];

        if ($tag !== null) {
            $col[ColumnPermissionService::TAG] = $tag;
        }

        return $col;
    }

    // ── Key extraction: never isset()/empty() as the decision point ───────────

    #[Test]
    public function a_column_without_the_tag_is_always_public(): void
    {
        config(['ptah.modules.permissions' => true]);
        $cols = [$this->col('name')];

        $result = $this->service->apply($cols, null); // guest, allow_guest=false

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    #[Test]
    public function an_empty_string_tag_is_public_not_missing(): void
    {
        $cols = [$this->col('name', '')];

        $result = $this->service->apply($cols, null);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    #[Test]
    public function a_whitespace_only_tag_trims_to_empty_and_is_public(): void
    {
        $cols = [$this->col('name', '   ')];

        $result = $this->service->apply($cols, null);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    #[Test]
    public function a_non_string_tag_is_public(): void
    {
        $cols = [$this->col('name', 123), $this->col('other', ['not', 'a', 'string'])];

        $result = $this->service->apply($cols, null);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    // ── Feature flag off ────────────────────────────────────────────────────

    #[Test]
    public function module_disabled_is_a_byte_identical_passthrough(): void
    {
        config(['ptah.modules.permissions' => false]);

        $cols = [
            $this->col('id'),
            $this->col('cost', 'secret.cost'), // would deny a guest if the module were on
        ];

        $result = $this->service->apply($cols, null);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    // ── MASTER ──────────────────────────────────────────────────────────────

    #[Test]
    public function master_passes_even_a_key_with_no_registered_page_object(): void
    {
        config(['ptah.modules.permissions' => true]);
        $this->assign($this->userId, $this->makeRole(master: true));

        $cols = [$this->col('cost', 'nonexistent.key')];

        $result = $this->service->apply($cols, $this->userId);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    // ── Guest ───────────────────────────────────────────────────────────────

    #[Test]
    public function a_guest_is_denied_by_default(): void
    {
        config(['ptah.modules.permissions' => true, 'ptah.permissions.allow_guest' => false]);

        $cols = [$this->col('cost', 'secret.cost')];

        $result = $this->service->apply($cols, null);

        $this->assertSame([], $result['cols']);
        $this->assertSame(['cost'], $result['denied']);
    }

    #[Test]
    public function a_guest_is_granted_when_allow_guest_is_true(): void
    {
        config(['ptah.modules.permissions' => true, 'ptah.permissions.allow_guest' => true]);

        $cols = [$this->col('cost', 'secret.cost')];

        $result = $this->service->apply($cols, null);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    // ── Ordinary user — bare key ────────────────────────────────────────────

    #[Test]
    public function an_ordinary_user_without_the_grant_has_the_column_denied(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('screen-a');
        $obj = $this->makeObject($page, 'secret.cost');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        // Granted create/update/delete, but NOT read — the gate is read-specific.
        $this->grant($role, $obj, ['can_create' => true, 'can_update' => true, 'can_delete' => true]);

        $cols = [$this->col('id'), $this->col('cost', 'secret.cost')];

        $result = $this->service->apply($cols, $this->userId);

        $this->assertSame([$this->col('id')], $result['cols']);
        $this->assertSame(['cost'], $result['denied']);
    }

    #[Test]
    public function an_ordinary_user_with_the_read_grant_keeps_the_column(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('screen-a');
        $obj = $this->makeObject($page, 'secret.cost');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $cols = [$this->col('cost', 'secret.cost')];

        $result = $this->service->apply($cols, $this->userId);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    // ── Qualified keys ──────────────────────────────────────────────────────

    #[Test]
    public function a_two_part_qualified_key_is_resolved_via_the_qualified_map(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('screen-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        // The bare map has 'shared.key' granted — but the column tag is
        // explicitly qualified. A colliding page-b would otherwise leak the
        // same grant to a bare lookup; this proves the qualified path itself
        // resolves correctly.
        $cols = [$this->col('field', 'screen-a::shared.key')];

        $result = $this->service->apply($cols, $this->userId);

        $this->assertSame($cols, $result['cols']);
        $this->assertSame([], $result['denied']);
    }

    #[Test]
    public function a_three_part_qualified_key_disambiguates_by_section(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('screen-a');
        $objToolbar = $this->makeObject($page, 'shared.key', section: 'toolbar');
        $this->makeObject($page, 'shared.key', section: 'form');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $objToolbar, ['can_read' => true]);

        $cols = [
            $this->col('a', 'screen-a::toolbar::shared.key'),
            $this->col('b', 'screen-a::form::shared.key'),
        ];

        $result = $this->service->apply($cols, $this->userId);

        $this->assertSame([$this->col('a', 'screen-a::toolbar::shared.key')], $result['cols']);
        $this->assertSame(['b'], $result['denied']);
    }

    // ── No N-column query blow-up, no audit rows ───────────────────────────

    #[Test]
    public function n_denied_columns_cost_no_extra_queries_and_write_no_audit_rows(): void
    {
        config([
            'ptah.modules.permissions' => true,
            // Both audit flags ON — proves apply() never calls check(), since
            // check() is the only place that writes to ptah_permission_audits.
            'ptah.permissions.audit' => true,
            'ptah.permissions.audit_denied' => true,
        ]);

        $page = $this->makePage('screen-a');
        // Same role/assignment shape for BOTH measured users — otherwise the
        // query count would vary with eager-load fan-out (whether the role
        // has any RolePermission rows), which has nothing to do with the
        // number of columns/keys requested and would make this assertion
        // meaningless either way.
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->assign($this->userId + 1, $role);
        // No grants at all — every key below is denied.

        $oneCol = [$this->col('f0', 'k0')];
        $manyCols = [];
        for ($i = 0; $i < 20; $i++) {
            $manyCols[] = $this->col('f'.$i, 'k'.$i);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->service->apply($oneCol, $this->userId);
        $queriesForOne = $queries;

        // A different user id with the IDENTICAL role/grant shape, so neither
        // the request memo nor the permission cache can short-circuit the
        // second measurement with a hit from the first, and any query-count
        // difference can only come from the number of columns/keys.
        $queries = 0;
        $this->service->apply($manyCols, $this->userId + 1);
        $queriesForMany = $queries;

        $this->assertSame(
            $queriesForOne,
            $queriesForMany,
            'Query count must not scale with the number of denied columns.'
        );

        $this->assertSame(0, PermissionAudit::query()->count());

        unset($page);
    }

    // ── filterExportColumns() — the EXPORT column shape (Wave 2) ────────────
    //
    // Same key-resolution logic as apply() (resolveKeys(), reused internally
    // — not duplicated), but over the `field`/`label`/`type`/`permission`
    // shape HasCrudExport::getVisibleColumnsForExport() builds, not the raw
    // CrudConfig `cols` shape apply() consumes.

    private function exportCol(string $field, mixed $permission = null): array
    {
        $col = ['field' => $field, 'label' => $field, 'type' => 'text'];

        if ($permission !== null) {
            $col['permission'] = $permission;
        }

        return $col;
    }

    #[Test]
    public function filter_export_columns_is_a_byte_identical_passthrough_when_the_module_is_off(): void
    {
        config(['ptah.modules.permissions' => false]);
        $columns = [
            $this->exportCol('id'),
            $this->exportCol('cost', 'secret.cost'), // would deny a guest if the module were on
        ];

        $result = $this->service->filterExportColumns($columns, null);

        $this->assertSame($columns, $result);
    }

    #[Test]
    public function filter_export_columns_keeps_a_column_with_no_permission_key(): void
    {
        config(['ptah.modules.permissions' => true]);
        $columns = [$this->exportCol('name')];

        $result = $this->service->filterExportColumns($columns, null);

        $this->assertSame($columns, $result);
    }

    #[Test]
    public function filter_export_columns_denies_a_guest_by_default(): void
    {
        config(['ptah.modules.permissions' => true, 'ptah.permissions.allow_guest' => false]);
        $columns = [$this->exportCol('name'), $this->exportCol('cost', 'secret.cost')];

        $result = $this->service->filterExportColumns($columns, null);

        $this->assertSame([$this->exportCol('name')], $result);
    }

    #[Test]
    public function filter_export_columns_grants_a_guest_when_allow_guest_is_true(): void
    {
        config(['ptah.modules.permissions' => true, 'ptah.permissions.allow_guest' => true]);
        $columns = [$this->exportCol('cost', 'secret.cost')];

        $result = $this->service->filterExportColumns($columns, null);

        $this->assertSame($columns, $result);
    }

    #[Test]
    public function filter_export_columns_passes_master_even_with_no_registered_page_object(): void
    {
        config(['ptah.modules.permissions' => true]);
        $this->assign($this->userId, $this->makeRole(master: true));
        $columns = [$this->exportCol('cost', 'nonexistent.key')];

        $result = $this->service->filterExportColumns($columns, $this->userId);

        $this->assertSame($columns, $result);
    }

    #[Test]
    public function filter_export_columns_denies_an_ordinary_user_without_the_read_grant(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('export-screen');
        $obj = $this->makeObject($page, 'items.secret_amount');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_create' => true]); // no read

        $columns = [$this->exportCol('name'), $this->exportCol('amount', 'items.secret_amount')];

        $result = $this->service->filterExportColumns($columns, $this->userId);

        $this->assertSame([$this->exportCol('name')], $result);
    }

    #[Test]
    public function filter_export_columns_keeps_a_column_the_user_has_the_read_grant_for(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('export-screen');
        $obj = $this->makeObject($page, 'items.secret_amount');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $columns = [$this->exportCol('amount', 'items.secret_amount')];

        $result = $this->service->filterExportColumns($columns, $this->userId);

        $this->assertSame($columns, $result);
    }

    #[Test]
    public function filter_export_columns_resolves_qualified_keys_the_same_way_apply_does(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('export-screen');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $columns = [$this->exportCol('field', 'export-screen::shared.key')];

        $result = $this->service->filterExportColumns($columns, $this->userId);

        $this->assertSame($columns, $result);
    }

    #[Test]
    public function filter_export_columns_returns_an_empty_list_when_every_column_is_denied(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = $this->makePage('export-screen');
        $this->makeObject($page, 'items.secret_amount');
        $this->assign($this->userId, $this->makeRole()); // no grant at all

        $columns = [$this->exportCol('amount', 'items.secret_amount')];

        $result = $this->service->filterExportColumns($columns, $this->userId);

        $this->assertSame([], $result);
    }
}

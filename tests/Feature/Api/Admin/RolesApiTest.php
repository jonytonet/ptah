<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Department;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

class RolesApiUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}

/**
 * Roles (with their permission matrix), pages/objects and the permission
 * guide through the API (1.59.0), for the React and Vue stacks. RoleList,
 * PageList and PermissionGuide are the engine — these tests pin the contract
 * and that their guards (MASTER only, one MASTER, MASTER undeletable) hold.
 */
class RolesApiTest extends TestCase
{
    private Role $master;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
        $app['config']->set('ptah.permissions.cache', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = Role::create(['name' => 'Master', 'is_master' => true, 'is_active' => true]);
    }

    private function user(bool $master = true): RolesApiUser
    {
        $user = RolesApiUser::create(['name' => 'Ana', 'email' => uniqid('ana').'@example.test', 'password' => bcrypt('secret')]);

        if ($master) {
            UserRole::create(['user_id' => $user->id, 'role_id' => $this->master->id, 'company_id' => null, 'is_active' => true]);
        }

        return $user;
    }

    private function asMaster(): static
    {
        return $this->actingAs($this->user());
    }

    /** @return array{0: PtahPage, 1: PageObject, 2: PageObject} */
    private function pageWithObjects(): array
    {
        $page = PtahPage::create(['slug' => 'admin.sales', 'name' => 'Sales', 'is_active' => true, 'sort_order' => 0]);
        $create = PageObject::create(['page_id' => $page->id, 'section' => 'main', 'obj_key' => 'sales.create', 'obj_label' => 'Create order', 'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true]);
        $export = PageObject::create(['page_id' => $page->id, 'section' => 'toolbar', 'obj_key' => 'sales.export', 'obj_label' => 'Export', 'obj_type' => 'button', 'obj_order' => 2, 'is_active' => true]);

        return [$page, $create, $export];
    }

    // ── Access ──────────────────────────────────────────────────────────────

    #[Test]
    public function only_a_master_reaches_any_endpoint(): void
    {
        $this->getJson('/ptah/api/admin/roles')->assertStatus(403);

        $this->actingAs($this->user(master: false));

        foreach (['/ptah/api/admin/roles', '/ptah/api/admin/pages', '/ptah/api/admin/permission-guide', '/ptah/api/admin/roles/'.$this->master->id.'/permissions'] as $url) {
            $this->getJson($url)->assertStatus(403);
        }
        $this->postJson('/ptah/api/admin/roles', ['name' => 'Sneaky'])->assertStatus(403);
        $this->deleteJson('/ptah/api/admin/roles/'.$this->master->id)->assertStatus(403);

        $this->assertDatabaseMissing('ptah_roles', ['name' => 'Sneaky']);
    }

    #[Test]
    public function the_routes_are_named_under_ptah_api_roles(): void
    {
        $this->assertTrue(app('router')->has('ptah.api.roles.index'));
        $this->assertTrue(app('router')->has('ptah.api.roles.guide'));
    }

    // ── Roles ───────────────────────────────────────────────────────────────

    #[Test]
    public function the_list_shows_roles_master_first_with_departments_and_a_search(): void
    {
        $department = Department::create(['name' => 'Sales', 'is_active' => true]);
        Role::create(['name' => 'Analyst', 'is_active' => true, 'department_id' => $department->id, 'color' => '#ff0000']);
        Role::create(['name' => 'Buyer', 'is_active' => false]);

        $this->asMaster()->getJson('/ptah/api/admin/roles')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Master')
            ->assertJsonPath('data.0.is_master', true)
            ->assertJsonPath('data.1.name', 'Analyst')
            ->assertJsonPath('data.1.department', 'Sales')
            ->assertJsonPath('data.1.color', '#ff0000')
            ->assertJsonPath('data.2.is_active', false)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('departments.0.name', 'Sales')
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'color', 'department_id', 'department', 'is_master', 'is_active', 'permissions_count']]]);

        $this->getJson('/ptah/api/admin/roles?search=buy')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Buyer');
    }

    #[Test]
    public function the_list_paginates_by_twenty_and_a_page_past_the_end_shows_the_last(): void
    {
        for ($i = 1; $i <= 24; $i++) {
            Role::create(['name' => sprintf('Role %02d', $i), 'is_active' => true]);
        }

        $this->asMaster()->getJson('/ptah/api/admin/roles?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 21);

        $this->getJson('/ptah/api/admin/roles?page=9')->assertOk()->assertJsonPath('meta.page', 2);
    }

    #[Test]
    public function a_role_is_created_and_a_missing_name_is_a_validation_error(): void
    {
        $department = Department::create(['name' => 'IT', 'is_active' => true]);

        $this->asMaster()->postJson('/ptah/api/admin/roles', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/ptah/api/admin/roles', ['name' => 'Seller', 'description' => 'Sells', 'color' => '#00ff00', 'department_id' => $department->id, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.level', 'success');

        $this->assertDatabaseHas('ptah_roles', ['name' => 'Seller', 'department_id' => $department->id, 'is_master' => false]);

        $this->postJson('/ptah/api/admin/roles', ['name' => 'Ghost dept', 'department_id' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['department_id']);
    }

    #[Test]
    public function a_second_master_is_refused(): void
    {
        $this->asMaster()->postJson('/ptah/api/admin/roles', ['name' => 'Another master', 'is_master' => true])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', trans('ptah::ui.role_master_already_exists'));

        $this->assertDatabaseMissing('ptah_roles', ['name' => 'Another master']);
    }

    #[Test]
    public function a_role_is_loaded_and_edited(): void
    {
        $role = Role::create(['name' => 'Seller', 'is_active' => true]);

        $this->asMaster()->getJson('/ptah/api/admin/roles/'.$role->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Seller')
            ->assertJsonPath('data.is_master', false);

        $this->putJson('/ptah/api/admin/roles/'.$role->id, ['name' => 'Senior seller', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('ptah_roles', ['id' => $role->id, 'name' => 'Senior seller', 'is_active' => false]);

        $this->getJson('/ptah/api/admin/roles/999')->assertNotFound();
        $this->putJson('/ptah/api/admin/roles/999', ['name' => 'x'])->assertNotFound();
    }

    #[Test]
    public function the_master_role_cannot_be_demoted_deactivated_or_deleted(): void
    {
        // The screen has no MASTER switch for the MASTER role: a forged flag is ignored.
        $this->asMaster()->putJson('/ptah/api/admin/roles/'.$this->master->id, ['name' => 'Boss', 'is_master' => false, 'is_active' => true])
            ->assertOk();
        $this->assertTrue($this->master->fresh()->is_master);
        $this->assertSame('Boss', $this->master->fresh()->name);

        $this->putJson('/ptah/api/admin/roles/'.$this->master->id, ['name' => 'Boss', 'is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('error', trans('ptah::ui.role_master_cannot_deactivate'));

        $this->deleteJson('/ptah/api/admin/roles/'.$this->master->id)
            ->assertStatus(422)
            ->assertJsonPath('error', trans('ptah::ui.role_master_cannot_delete'));
        $this->assertNotSoftDeleted('ptah_roles', ['id' => $this->master->id]);
    }

    #[Test]
    public function a_role_is_deleted(): void
    {
        $role = Role::create(['name' => 'Temp', 'is_active' => true]);

        $this->asMaster()->deleteJson('/ptah/api/admin/roles/'.$role->id)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSoftDeleted('ptah_roles', ['id' => $role->id]);

        // Gone: the component's own refusal, as a 422 with its message.
        $this->deleteJson('/ptah/api/admin/roles/'.$role->id)->assertStatus(422)->assertJsonPath('ok', false);
    }

    // ── Permission matrix ───────────────────────────────────────────────────

    #[Test]
    public function the_matrix_lists_every_active_object_with_the_role_flags(): void
    {
        [, $create, $export] = $this->pageWithObjects();
        $role = Role::create(['name' => 'Seller', 'is_active' => true]);
        RolePermission::create(['role_id' => $role->id, 'page_object_id' => $create->id, 'can_create' => true, 'can_read' => true, 'can_update' => false, 'can_delete' => false]);

        $this->asMaster()->getJson('/ptah/api/admin/roles/'.$role->id.'/permissions')
            ->assertOk()
            ->assertJsonPath('role.name', 'Seller')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $create->id)
            ->assertJsonPath('data.0.page_name', 'Sales')
            ->assertJsonPath('data.0.can_create', true)
            ->assertJsonPath('data.0.can_update', false)
            ->assertJsonPath('data.1.id', $export->id)
            ->assertJsonPath('data.1.can_read', false);

        $this->getJson('/ptah/api/admin/roles/999/permissions')->assertNotFound();
    }

    #[Test]
    public function saving_the_matrix_binds_and_unbinds_and_ignores_made_up_objects(): void
    {
        [$page, $create, $export] = $this->pageWithObjects();
        $hidden = PageObject::create(['page_id' => $page->id, 'section' => 'main', 'obj_key' => 'sales.secret', 'obj_label' => 'Secret', 'obj_type' => 'field', 'obj_order' => 3, 'is_active' => false]);
        $role = Role::create(['name' => 'Seller', 'is_active' => true]);
        RolePermission::create(['role_id' => $role->id, 'page_object_id' => $create->id, 'can_create' => true, 'can_read' => true, 'can_update' => false, 'can_delete' => false]);

        $this->asMaster()->putJson('/ptah/api/admin/roles/'.$role->id.'/permissions', [
            'objects' => [
                ['id' => $create->id, 'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false],
                ['id' => $export->id, 'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => true],
                // Not on the screen (inactive) and not an object at all: ignored.
                ['id' => $hidden->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true],
                ['id' => 99999, 'can_read' => true],
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSoftDeleted('ptah_role_permissions', ['role_id' => $role->id, 'page_object_id' => $create->id]);
        $this->assertDatabaseHas('ptah_role_permissions', ['role_id' => $role->id, 'page_object_id' => $export->id, 'can_read' => true, 'can_delete' => true, 'can_create' => false, 'deleted_at' => null]);
        $this->assertDatabaseMissing('ptah_role_permissions', ['role_id' => $role->id, 'page_object_id' => $hidden->id]);
        $this->assertDatabaseMissing('ptah_role_permissions', ['page_object_id' => 99999]);
    }

    // ── Pages and objects ───────────────────────────────────────────────────

    #[Test]
    public function pages_are_listed_searched_created_edited_and_deleted(): void
    {
        [$sales] = $this->pageWithObjects();

        $this->asMaster()->getJson('/ptah/api/admin/pages')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'admin.sales')
            ->assertJsonPath('data.0.objects_count', 2)
            ->assertJsonPath('types', PageObject::TYPES);

        $this->postJson('/ptah/api/admin/pages', ['slug' => '', 'name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page_slug', 'page_name']);

        $this->postJson('/ptah/api/admin/pages', ['slug' => 'admin.stock', 'name' => 'Stock', 'icon' => 'bx bx-box', 'sort_order' => 3, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('ok', true);
        $stock = PtahPage::where('slug', 'admin.stock')->firstOrFail();
        $this->assertSame(3, $stock->sort_order);

        $this->getJson('/ptah/api/admin/pages?search=stock')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Stock');

        $this->putJson('/ptah/api/admin/pages/'.$stock->id, ['slug' => 'admin.stock', 'name' => 'Inventory', 'is_active' => false])
            ->assertOk();
        $this->assertDatabaseHas('ptah_pages', ['id' => $stock->id, 'name' => 'Inventory', 'is_active' => false]);

        $this->deleteJson('/ptah/api/admin/pages/'.$sales->id)->assertOk();
        $this->assertDatabaseMissing('ptah_pages', ['id' => $sales->id]);
        $this->putJson('/ptah/api/admin/pages/'.$sales->id, ['slug' => 'x', 'name' => 'x'])->assertNotFound();
    }

    #[Test]
    public function objects_of_a_page_are_listed_created_edited_and_deleted(): void
    {
        [$sales, $create] = $this->pageWithObjects();

        $this->asMaster()->getJson('/ptah/api/admin/pages/'.$sales->id.'/objects')
            ->assertOk()
            ->assertJsonPath('page.name', 'Sales')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.obj_key', 'sales.create');

        $this->getJson('/ptah/api/admin/pages/'.$sales->id.'/objects?search=export')->assertJsonCount(1, 'data');

        $this->postJson('/ptah/api/admin/pages/'.$sales->id.'/objects', ['section' => 'main', 'obj_key' => '', 'obj_label' => '', 'obj_type' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['obj_key', 'obj_label', 'obj_type']);

        $this->postJson('/ptah/api/admin/pages/'.$sales->id.'/objects', ['section' => 'form', 'obj_key' => 'sales.discount', 'obj_label' => 'Discount', 'obj_type' => 'field', 'obj_order' => 5])
            ->assertOk();
        $this->assertDatabaseHas('ptah_page_objects', ['page_id' => $sales->id, 'obj_key' => 'sales.discount', 'obj_type' => 'field', 'obj_order' => 5]);

        $this->putJson('/ptah/api/admin/pages/'.$sales->id.'/objects/'.$create->id, ['section' => 'main', 'obj_key' => 'sales.create', 'obj_label' => 'New order', 'obj_type' => 'button', 'is_active' => false])
            ->assertOk();
        $this->assertDatabaseHas('ptah_page_objects', ['id' => $create->id, 'obj_label' => 'New order', 'is_active' => false]);

        $this->deleteJson('/ptah/api/admin/pages/'.$sales->id.'/objects/'.$create->id)->assertOk();
        $this->assertDatabaseMissing('ptah_page_objects', ['id' => $create->id]);

        $this->getJson('/ptah/api/admin/pages/999/objects')->assertNotFound();
    }

    #[Test]
    public function an_object_of_another_page_cannot_be_edited_or_deleted_through_this_one(): void
    {
        [$sales, $create] = $this->pageWithObjects();
        $other = PtahPage::create(['slug' => 'admin.other', 'name' => 'Other', 'is_active' => true, 'sort_order' => 0]);

        $this->asMaster()->putJson('/ptah/api/admin/pages/'.$other->id.'/objects/'.$create->id, ['section' => 'main', 'obj_key' => 'moved', 'obj_label' => 'Moved', 'obj_type' => 'button'])
            ->assertNotFound();
        $this->deleteJson('/ptah/api/admin/pages/'.$other->id.'/objects/'.$create->id)->assertNotFound();

        $this->assertDatabaseHas('ptah_page_objects', ['id' => $create->id, 'page_id' => $sales->id, 'obj_key' => 'sales.create']);
    }

    // ── Guide ───────────────────────────────────────────────────────────────

    #[Test]
    public function the_guide_answers_the_version_and_its_links(): void
    {
        $this->asMaster()->getJson('/ptah/api/admin/permission-guide')
            ->assertOk()
            ->assertJsonStructure(['version', 'urls' => ['departments', 'pages', 'roles', 'users', 'audit']])
            ->assertJsonPath('urls.roles', route('ptah.acl.roles'));
    }
}

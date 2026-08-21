<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

// ── Stub model on the shared `items` table ──────────────────────────────────

class ColumnPermissionStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id'];
}

class ColumnPermissionCrudUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers the per-column `colsPermission` gate end to end through the real
 * BaseCrud component: a denied column disappears from the table header,
 * the data cells, the card view and the column-visibility dropdown — and,
 * critically, a screen with NO `colsPermission` tags anywhere renders
 * byte-identically to the same screen with the gate absent entirely
 * (regression zero — see ColumnPermissionTamperTest for the adversarial
 * "can a client bypass the gate" coverage).
 */
class ColumnPermissionScreenTest extends TestCase
{
    private const SENSITIVE_AMOUNT = 555444;

    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => ColumnPermissionStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => ColumnPermissionStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        'colsNomeFisico' => 'amount',
                        'colsNomeLogico' => 'Secret Amount',
                        'colsTipo' => 'number',
                        'colsGravar' => true,
                        'colsIsFilterable' => 'S',
                        'colsPermission' => 'items.secret_amount',
                    ],
                ],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function seedRows(): void
    {
        ColumnPermissionStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);
    }

    private function actingAsUser(): ColumnPermissionCrudUser
    {
        $user = ColumnPermissionCrudUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makePage(string $slug): PtahPage
    {
        return PtahPage::create(['slug' => $slug, 'name' => $slug, 'is_active' => true]);
    }

    private function makeObject(PtahPage $page, string $key): PageObject
    {
        return PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => $key, 'obj_label' => $key,
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
    }

    private function makeRole(bool $master = false): Role
    {
        return Role::create(['name' => 'R'.uniqid(), 'is_master' => $master, 'is_active' => true]);
    }

    private function assign(int $userId, Role $role): void
    {
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    private function denyUser(): ColumnPermissionCrudUser
    {
        $user = $this->actingAsUser();
        $this->assign($user->id, $this->makeRole()); // no grant at all → denied

        return $user;
    }

    // ── Denied column disappears everywhere ──────────────────────────────────

    #[Test]
    public function a_denied_column_is_absent_from_the_table_header_and_cells(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $this->makePage('items-screen');
        $this->makeObject(PtahPage::first(), 'items.secret_amount');
        $this->denyUser();

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionStub::class])
            ->assertSee('Alpha')
            ->assertDontSee('Secret Amount')
            ->assertDontSee((string) self::SENSITIVE_AMOUNT);
    }

    #[Test]
    public function a_denied_column_is_absent_from_the_card_view(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $this->makePage('items-screen');
        $this->makeObject(PtahPage::first(), 'items.secret_amount');
        $this->denyUser();

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionStub::class])
            ->call('setViewMode', 'cards')
            ->assertSee('Alpha')
            ->assertDontSee('Secret Amount')
            ->assertDontSee((string) self::SENSITIVE_AMOUNT);
    }

    #[Test]
    public function a_denied_column_is_absent_from_the_column_visibility_dropdown(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $this->makePage('items-screen');
        $this->makeObject(PtahPage::first(), 'items.secret_amount');
        $this->denyUser();

        // The dropdown's label list is `$crudConfig['cols']` itself (already
        // filtered) — "Secret Amount" must not be offered as a toggle at all.
        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionStub::class])
            ->assertDontSee('Secret Amount');
    }

    #[Test]
    public function a_user_with_the_read_grant_still_sees_the_column(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $page = $this->makePage('items-screen');
        $obj = $this->makeObject($page, 'items.secret_amount');
        $user = $this->actingAsUser();
        $role = $this->makeRole();
        $this->assign($user->id, $role);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionStub::class])
            ->assertSee('Alpha')
            ->assertSee('Secret Amount')
            ->assertSee((string) self::SENSITIVE_AMOUNT);
    }

    // ── Regression zero: no tags anywhere ────────────────────────────────────

    #[Test]
    public function a_screen_without_any_colspermission_tag_is_completely_unaffected(): void
    {
        // No colsPermission anywhere — even for a denied (role-less) user,
        // every column must render exactly as if the gate did not exist.
        CrudConfig::create([
            'model' => ColumnPermissionStub::class,
            'route' => '',
            'config' => [
                'crud' => ColumnPermissionStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
        $this->seedRows();
        $this->denyUser();

        $storedCols = CrudConfig::first()->config['cols'];

        $component = Livewire::test(BaseCrud::class, ['model' => ColumnPermissionStub::class])
            ->assertSee('Alpha')
            ->assertSee('Amount')
            ->assertSee((string) self::SENSITIVE_AMOUNT);

        // The component's crudConfig['cols'] must be byte-identical to what
        // CrudConfigService loaded from the DB — the gate must be a total
        // no-op when nothing is tagged.
        $this->assertSame($storedCols, $component->get('crudConfig')['cols']);
    }
}

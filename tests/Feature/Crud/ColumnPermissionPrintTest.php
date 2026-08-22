<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
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

// ── Stub on the shared `items` table ────────────────────────────────────────

class ColumnPermissionPrintStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class ColumnPermissionPrintUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Wave 2, step 6: proves printView() needs NO production code change — a
 * denied column and its totalizer already never reach the cached print
 * payload, because printView() builds its column set from
 * getVisibleColumns() and its totals from totalizadoresData(), both already
 * filtered by HasCrudLifecycle::applyColumnPermissions() (Wave 1) before
 * printView() ever runs. CrudPrintController only renders that already-
 * filtered cached payload — nothing left to gate there either.
 */
class ColumnPermissionPrintTest extends TestCase
{
    private const SENSITIVE_AMOUNT = 733221;

    private function makeConfig(): void
    {
        CrudConfig::create([
            'model' => ColumnPermissionPrintStub::class,
            'route' => '',
            'config' => [
                'crud' => ColumnPermissionPrintStub::class,
                'displayName' => 'Items',
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        'colsNomeFisico' => 'amount',
                        'colsNomeLogico' => 'Secret Amount',
                        'colsTipo' => 'number',
                        'colsGravar' => true,
                        'colsPermission' => 'items.secret_amount',
                    ],
                ],
                'exportConfig' => ['enabled' => true, 'maxRows' => 5000],
                'totalizadores' => [
                    'enabled' => true,
                    'columns' => [['field' => 'amount', 'aggregate' => 'sum']],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function actingAsUser(): ColumnPermissionPrintUser
    {
        $user = ColumnPermissionPrintUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makePageObject(): PageObject
    {
        $page = PtahPage::create(['slug' => 'print-screen-'.uniqid(), 'name' => 'Print screen', 'is_active' => true]);

        return PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'items.secret_amount', 'obj_label' => 'items.secret_amount',
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
    }

    private function denyUser(int $userId): void
    {
        $this->makePageObject();
        $role = Role::create(['name' => 'R'.uniqid(), 'is_active' => true]);
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    private function grantUser(int $userId): void
    {
        $pageObject = $this->makePageObject();
        $role = Role::create(['name' => 'R'.uniqid(), 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $pageObject->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    /** Runs printView() and returns the cached payload for the dispatched token. */
    private function printAndFetch(object $component): array
    {
        $url = null;
        $component->call('printView')->assertDispatched('ptah:open-print', function ($event, $params) use (&$url) {
            $url = $params['url'] ?? null;

            return $url !== null;
        });

        $token = basename(parse_url($url, PHP_URL_PATH));

        return Cache::get('ptah:print:'.$token);
    }

    #[Test]
    public function a_denied_users_print_snapshot_has_neither_the_column_nor_its_total(): void
    {
        $this->makeConfig();
        ColumnPermissionPrintStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);
        $user = $this->actingAsUser();
        $this->denyUser($user->id);

        $component = Livewire::test(BaseCrud::class, ['model' => ColumnPermissionPrintStub::class]);
        $payload = $this->printAndFetch($component);

        $labels = array_column($payload['columns'], 'label');
        $this->assertContains('Name', $labels);
        $this->assertNotContains('Secret Amount', $labels);

        foreach ($payload['columns'] as $col) {
            $this->assertNotSame('amount', $col['field']);
        }

        $this->assertStringNotContainsString(
            (string) self::SENSITIVE_AMOUNT,
            implode('', $payload['rows'][0]['cells'])
        );
    }

    #[Test]
    public function a_granted_users_print_snapshot_still_has_the_column_and_its_total(): void
    {
        $this->makeConfig();
        ColumnPermissionPrintStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);
        $user = $this->actingAsUser();
        $this->grantUser($user->id);

        $component = Livewire::test(BaseCrud::class, ['model' => ColumnPermissionPrintStub::class]);
        $payload = $this->printAndFetch($component);

        $labels = array_column($payload['columns'], 'label');
        $this->assertContains('Secret Amount', $labels);

        $amountCol = collect($payload['columns'])->firstWhere('field', 'amount');
        $this->assertNotNull($amountCol['total']);
    }
}

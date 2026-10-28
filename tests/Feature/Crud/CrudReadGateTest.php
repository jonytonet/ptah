<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Features\SupportTesting\Testable;
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

use function Livewire\store;

// ── Stub on the shared `items` table ────────────────────────────────────────

class ReadGateStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class ReadGateUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers `read` becoming a real gate for BaseCrud (see
 * BaseCrud::getEffectivePermissions()/render()): the listing, export,
 * bulkExport, queueExport and printView must all refuse to disclose data
 * when the CRUD's permissionIdentifier is denied `read` — and, critically,
 * an install with no permissionIdentifier configured (the overwhelming
 * majority, since `can_read` only started being enforced with this change)
 * must be entirely unaffected.
 */
class CrudReadGateTest extends TestCase
{
    private const OBJ_KEY = 'items.read_gate';

    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => ReadGateStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => ReadGateStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => ['permissionIdentifier' => self::OBJ_KEY],
                'exportConfig' => ['enabled' => true],
            ], $extra),
        ]);
    }

    private function seedRows(): void
    {
        ReadGateStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 10]);
    }

    private function actingAsUser(): ReadGateUser
    {
        $user = ReadGateUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makePageObject(): PageObject
    {
        $page = PtahPage::create(['slug' => 'read-gate-'.uniqid(), 'name' => 'Read gate', 'is_active' => true]);

        return PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => self::OBJ_KEY, 'obj_label' => self::OBJ_KEY,
            'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true,
        ]);
    }

    /** Explicitly denies `read` (and every other action) for the acting user. */
    private function denyRead(int $userId): void
    {
        $pageObject = $this->makePageObject();
        $role = Role::create(['name' => 'R'.uniqid(), 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $pageObject->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false,
        ]);
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    /** Explicitly grants `read` for the acting user. */
    private function grantRead(int $userId): void
    {
        $pageObject = $this->makePageObject();
        $role = Role::create(['name' => 'R'.uniqid(), 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $pageObject->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    /**
     * Revokes `read` from every grant the acting user holds, through the model
     * so the observers bump the permission cache. The export tests mount with
     * read granted (the render itself aborts 403 when it is denied — there is
     * no component to call an action on) and revoke before the call: the
     * action must refuse on its own, as it would for a grant withdrawn while
     * the screen is open, or for a crafted request.
     */
    private function revokeRead(): void
    {
        RolePermission::query()->get()->each(fn (RolePermission $rp) => $rp->update(['can_read' => false]));
    }

    /**
     * Calls the action on the live component instance and returns the names
     * of the events it dispatched. Not through ->call(): with read revoked the
     * re-render that follows a call aborts 403, the response carries no
     * effects, and any assertNotDispatched would pass even if the action had
     * exported everything.
     *
     * @return list<string>
     */
    private function dispatchedBy(Testable $component, string $method, mixed ...$args): array
    {
        $instance = $component->instance();
        $before = count(store($instance)->get('dispatched', []));
        $instance->{$method}(...$args);

        return array_map(
            fn ($event) => $event->serialize()['name'],
            array_slice(store($instance)->get('dispatched', []), $before)
        );
    }

    // ── Listing (render) ──────────────────────────────────────────────────────

    #[Test]
    public function the_listing_aborts_403_when_read_is_denied(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyRead($user->id);

        Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class])
            ->assertStatus(403);
    }

    #[Test]
    public function the_listing_renders_normally_when_read_is_granted(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->grantRead($user->id);

        Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class])
            ->assertStatus(200)
            ->assertSee('Alpha');
    }

    #[Test]
    public function the_listing_is_unaffected_when_no_permission_identifier_is_configured(): void
    {
        // No permissionIdentifier at all — screens that never opted into the
        // permissions module/gating must keep working exactly as before,
        // even for a user with no role/grant whatsoever.
        $this->makeConfig(['permissions' => []]);
        $this->seedRows();
        $this->actingAsUser();

        Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class])
            ->assertStatus(200)
            ->assertSee('Alpha');
    }

    // ── Export / bulkExport / queueExport / print ───────────────────────────────

    #[Test]
    public function export_does_not_dispatch_a_download_when_read_is_denied(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->grantRead($user->id);

        $component = Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class]);
        $this->revokeRead();

        $this->assertNotContains('ptah:export-download', $this->dispatchedBy($component, 'export', 'excel'));
    }

    #[Test]
    public function bulk_export_does_not_dispatch_a_download_when_read_is_denied(): void
    {
        $this->makeConfig();
        $row = ReadGateStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 10]);
        $user = $this->actingAsUser();
        $this->grantRead($user->id);

        $component = Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class])
            ->set('selectedRows', [$row->id]);
        $this->revokeRead();

        $this->assertNotContains('ptah:export-download', $this->dispatchedBy($component, 'bulkExport', 'excel'));
    }

    #[Test]
    public function queue_export_does_not_dispatch_a_download_when_read_is_denied(): void
    {
        $this->makeConfig(['exportConfig' => ['enabled' => true, 'asyncExport' => ['enabled' => true, 'excel' => true]]]);
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->grantRead($user->id);

        $component = Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class]);
        $this->revokeRead();

        $dispatched = $this->dispatchedBy($component, 'queueExport', 'excel');
        $this->assertNotContains('ptah:export-download', $dispatched);
        $this->assertNotContains('ptah-toast', $dispatched);
    }

    #[Test]
    public function print_view_does_not_dispatch_when_read_is_denied(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->grantRead($user->id);

        $component = Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class]);
        $this->revokeRead();

        $this->assertNotContains('ptah:open-print', $this->dispatchedBy($component, 'printView'));
    }

    #[Test]
    public function export_still_works_when_read_is_granted(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->grantRead($user->id);

        Livewire::test(BaseCrud::class, ['model' => ReadGateStub::class])
            ->call('export', 'excel')
            ->assertDispatched('ptah:export-download');
    }
}

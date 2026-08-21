<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PageObject;
use Ptah\Models\PermissionAudit;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

// ── Minimal User model, reusing the `users` table from tests/migrations ────
class WhyTestUser extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers `ptah:permission:why` — the permission-diagnosis command. Every
 * granted/denied verdict is asserted to come from `PermissionService::check()`
 * itself (never reimplemented); these tests only exercise the surrounding
 * diagnostics and the exit code.
 *
 * Note: `expectsOutputToContain()` must be chained BEFORE `assertExitCode()`
 * — once an assertion executes the command, PendingCommand runs it again for
 * any expectation registered afterwards, against an empty buffer.
 */
class PermissionWhyCommandTest extends TestCase
{
    private function makePage(string $slug, bool $active = true): PtahPage
    {
        return PtahPage::create(['slug' => $slug, 'name' => $slug, 'is_active' => $active]);
    }

    private function makeObject(PtahPage $page, string $key, string $section = 'main', bool $active = true): PageObject
    {
        return PageObject::create([
            'page_id' => $page->id, 'section' => $section,
            'obj_key' => $key, 'obj_label' => $key,
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => $active,
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

    private function grant(Role $role, PageObject $obj, array $flags): RolePermission
    {
        return RolePermission::create(array_merge([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false,
        ], $flags));
    }

    #[Test]
    public function it_exits_zero_when_access_is_granted(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('CONCEDIDO')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_resolves_the_user_argument_by_email(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $user = WhyTestUser::create(['name' => 'Fulano', 'email' => 'fulano@example.com', 'password' => 'x']);
        $this->assign((int) $user->id, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        config(['ptah.permissions.user_model' => WhyTestUser::class]);

        $this->artisan('ptah:permission:why', ['user' => 'fulano@example.com', 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('CONCEDIDO')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_for_an_unknown_user(): void
    {
        config(['ptah.permissions.user_model' => WhyTestUser::class]);

        $this->artisan('ptah:permission:why', ['user' => 'ghost@example.com', 'objKey' => 'shared.key'])
            ->expectsOutputToContain('não encontrado')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_object_does_not_exist(): void
    {
        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'ghost.key', '--action' => 'read'])
            ->expectsOutputToContain('Nenhum PageObject encontrado')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_object_inactive(): void
    {
        $page = $this->makePage('page-a');
        $this->makeObject($page, 'shared.key', active: false);
        $role = $this->makeRole();
        $this->assign(100, $role);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('existe mas está inativo')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_page_inactive(): void
    {
        $page = $this->makePage('page-a', active: false);
        $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('está inativa (PtahPage.is_active=false)')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_no_active_role(): void
    {
        $page = $this->makePage('page-a');
        $this->makeObject($page, 'shared.key');

        $this->artisan('ptah:permission:why', ['user' => 999, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('não possui nenhum papel')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_no_bind_at_all(): void
    {
        $page = $this->makePage('page-a');
        $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('Nenhum RolePermission vincula')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_can_action_false(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);
        $this->grant($role, $obj, ['can_create' => true, 'can_read' => false]);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('can_read=false')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_bind_trashed(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);
        $bind = $this->grant($role, $obj, ['can_read' => true]);
        $bind->delete();

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain('RolePermission soft-deleted')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_grant_scoped_to_a_different_company(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role, companyId: 5);
        $this->grant($role, $obj, ['can_read' => true]);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read', '--company' => 7])
            ->expectsOutputToContain('apenas para a(s) empresa(s)')
            ->assertExitCode(1);
    }

    #[Test]
    public function missing_piece_grant_only_on_a_different_page_suggests_the_qualified_key(): void
    {
        $pageA = $this->makePage('page-a');
        $pageB = $this->makePage('page-b');
        $this->makeObject($pageA, 'shared.key');
        $objB = $this->makeObject($pageB, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);
        $this->grant($role, $objB, ['can_read' => true]);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'page-a::shared.key', '--action' => 'read'])
            ->expectsOutputToContain('use a chave qualificada: page-b::shared.key')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_collision_prints_both_pages(): void
    {
        $pageA = $this->makePage('page-a');
        $pageB = $this->makePage('page-b');
        $this->makeObject($pageA, 'shared.key');
        $this->makeObject($pageB, 'shared.key');

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'read'])
            ->expectsOutputToContain("page='page-a'")
            ->expectsOutputToContain("page='page-b'");
    }

    #[Test]
    public function it_never_writes_to_the_audit_table_even_when_audit_is_enabled(): void
    {
        config(['ptah.permissions.audit' => true, 'ptah.permissions.audit_denied' => true]);

        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');
        $role = $this->makeRole();
        $this->assign(100, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $this->artisan('ptah:permission:why', ['user' => 100, 'objKey' => 'shared.key', '--action' => 'delete']);

        $this->assertSame(0, PermissionAudit::query()->count());
    }
}

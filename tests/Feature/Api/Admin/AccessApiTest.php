<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Http\Controllers\Admin\AccessApiController;
use Ptah\Models\Company;
use Ptah\Models\PermissionAudit;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

class AccessApiUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    // No `ptahUserRoles` relation here: a host's User model has none, and the
    // role filter must work without it (it asked for one until 1.59.0).
}

class AccessOnlyExampleCom implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('email', 'like', '%@example.com');
    }
}

/**
 * The users-ACL screen and the permission audit log through the API, for the
 * React and Vue stacks. UserPermissionList and AuditList are the engine —
 * these tests pin the contract and that their guards (MASTER only, the
 * host's user scope, a MASTER role is not removable, the user form's rules)
 * hold through it.
 */
class AccessApiTest extends TestCase
{
    private const BASE = '/ptah/api/admin/access';

    private AccessApiUser $admin;

    private Role $master;

    private Role $seller;

    private Company $acme;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        // The auth module registers the reset page the password link points to.
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('ptah.crud_api.enabled', true);
        // The default API middleware (web + auth): a guest gets 401, not a redirect.
        $app['config']->set('ptah.crud_api.middleware', ['web', 'auth']);
        $app['config']->set('auth.providers.users.model', AccessApiUser::class);
        $app['config']->set('ptah.permissions.user_model', AccessApiUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }

        Notification::fake();

        $this->master = Role::create(['name' => 'MASTER', 'is_master' => true, 'is_active' => true]);
        $this->seller = Role::create(['name' => 'Vendedor', 'is_master' => false, 'is_active' => true]);
        $this->acme = Company::forceCreate(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

        $this->admin = $this->user('Admin', 'admin@example.com');
        UserRole::create(['user_id' => $this->admin->id, 'role_id' => $this->master->id, 'company_id' => null, 'is_active' => true]);
    }

    private function user(string $name, string $email): AccessApiUser
    {
        return AccessApiUser::create(['name' => $name, 'email' => $email, 'password' => Hash::make('secret-123')]);
    }

    // ── Guard ───────────────────────────────────────────────────────────────

    #[Test]
    public function only_a_master_reaches_the_api(): void
    {
        $this->getJson(self::BASE.'/users')->assertStatus(401);

        $plain = $this->user('Bia', 'bia@example.com');
        $this->actingAs($plain);

        $this->getJson(self::BASE.'/users')->assertForbidden();
        $this->getJson(self::BASE.'/audit')->assertForbidden();
        $this->postJson(self::BASE.'/users', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->postJson(self::BASE.'/users/'.$plain->id.'/roles', ['role_id' => $this->master->id])->assertForbidden();

        $this->assertSame(0, UserRole::where('user_id', $plain->id)->count());
    }

    #[Test]
    public function the_component_checks_master_again_when_the_route_gate_is_absent(): void
    {
        // A host that mounted the controller without `ptah.master`: boot() refuses.
        $plain = $this->user('Bia', 'bia@example.com');
        $this->actingAs($plain);
        Route::middleware('web')->get('/naked-access-users', [AccessApiController::class, 'users']);

        $this->getJson('/naked-access-users')->assertForbidden();
    }

    // ── Users list ──────────────────────────────────────────────────────────

    #[Test]
    public function the_list_shows_users_with_their_roles_and_the_choices(): void
    {
        $ana = $this->user('Ana', 'ana@example.com');
        UserRole::create(['user_id' => $ana->id, 'role_id' => $this->seller->id, 'company_id' => $this->acme->id, 'is_active' => true]);
        $this->actingAs($this->admin);

        $response = $this->getJson(self::BASE.'/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Admin')
            ->assertJsonPath('data.0.roles.0.master', true)
            ->assertJsonPath('data.1.email', 'ana@example.com')
            ->assertJsonPath('data.1.roles.0.name', 'Vendedor')
            ->assertJsonPath('data.1.roles.0.company', 'Acme')
            ->assertJsonPath('roles.0.name', 'MASTER')
            ->assertJsonPath('companies.0.name', 'Acme')
            ->assertJsonPath('can_send_link', true);

        // Whitelisted: no hidden attribute of the user leaves.
        $this->assertSame(['id', 'name', 'email', 'roles'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('password', $response->getContent());
    }

    #[Test]
    public function the_list_searches_filters_by_role_and_paginates(): void
    {
        $ana = $this->user('Ana', 'ana@example.com');
        UserRole::create(['user_id' => $ana->id, 'role_id' => $this->seller->id, 'company_id' => null, 'is_active' => true]);
        for ($i = 1; $i <= 26; $i++) {
            $this->user(sprintf('Zé %02d', $i), "ze{$i}@example.com");
        }
        $this->actingAs($this->admin);

        $this->getJson(self::BASE.'/users?search=ana@')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Ana');
        $this->getJson(self::BASE.'/users?role='.$this->seller->id)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Ana');

        $this->getJson(self::BASE.'/users?page=2')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonCount(3, 'data');
        // Past the last page: the last page.
        $this->getJson(self::BASE.'/users?page=9')->assertJsonPath('meta.current_page', 2);
    }

    #[Test]
    public function the_host_user_scope_bounds_the_list_and_every_action(): void
    {
        config(['ptah.permissions.user_query_scope' => AccessOnlyExampleCom::class]);
        $outside = $this->user('Fora', 'fora@other.org');
        $this->actingAs($this->admin);

        $this->getJson(self::BASE.'/users')->assertJsonMissing(['email' => 'fora@other.org']);
        $this->getJson(self::BASE.'/users/'.$outside->id)->assertNotFound();
        $this->putJson(self::BASE.'/users/'.$outside->id, ['name' => 'Hack', 'email' => 'fora@other.org'])->assertNotFound();
        $this->postJson(self::BASE.'/users/'.$outside->id.'/password-link')->assertNotFound();
        $this->getJson(self::BASE.'/users/'.$outside->id.'/roles')->assertNotFound();
        // Forged: granting MASTER to a user outside the scope.
        $this->postJson(self::BASE.'/users/'.$outside->id.'/roles', ['role_id' => $this->master->id])->assertNotFound();

        $this->assertSame('Fora', $outside->fresh()->name);
        $this->assertSame(0, UserRole::where('user_id', $outside->id)->count());
        Notification::assertNothingSent();
    }

    // ── Create / edit / password link ───────────────────────────────────────

    #[Test]
    public function creating_a_user_validates_then_saves_and_sends_the_link(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(self::BASE.'/users', ['name' => '', 'email' => 'admin@example.com', 'password' => '123'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
        $this->assertSame(1, AccessApiUser::count());

        $this->postJson(self::BASE.'/users', ['name' => 'Maria', 'email' => 'maria@example.com', 'password' => '', 'send_link' => true])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.level', 'success')
            ->assertJsonPath('messages.0.text', trans('ptah::ui.users_created').' '.trans('ptah::ui.users_link_sent'));

        $maria = AccessApiUser::where('email', 'maria@example.com')->firstOrFail();
        $this->assertNotEmpty($maria->password);
        Notification::assertSentTo($maria, ResetPassword::class);
    }

    #[Test]
    public function editing_a_user_reads_and_saves_it_keeping_a_blank_password(): void
    {
        $ana = $this->user('Ana', 'ana@example.com');
        $this->actingAs($this->admin);

        $this->getJson(self::BASE.'/users/'.$ana->id)
            ->assertOk()
            ->assertExactJson(['id' => $ana->id, 'name' => 'Ana', 'email' => 'ana@example.com']);

        $this->putJson(self::BASE.'/users/'.$ana->id, ['name' => 'Ana Souza', 'email' => 'ana@example.com', 'password' => ''])
            ->assertOk()
            ->assertJsonPath('messages.0.text', trans('ptah::ui.users_saved'));

        $ana->refresh();
        $this->assertSame('Ana Souza', $ana->name);
        $this->assertTrue(Hash::check('secret-123', $ana->password));
        Notification::assertNothingSent();

        $this->putJson(self::BASE.'/users/'.$ana->id, ['name' => 'Ana', 'email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
        $this->getJson(self::BASE.'/users/9999')->assertNotFound();
    }

    #[Test]
    public function the_password_link_is_sent_on_request(): void
    {
        $ana = $this->user('Ana', 'ana@example.com');
        $this->actingAs($this->admin);

        $this->postJson(self::BASE.'/users/'.$ana->id.'/password-link')
            ->assertOk()
            ->assertJsonPath('messages.0.text', trans('ptah::ui.users_link_sent'));

        Notification::assertSentTo($ana, ResetPassword::class);
    }

    // ── Roles of a user ─────────────────────────────────────────────────────

    #[Test]
    public function roles_are_listed_added_per_company_and_removed(): void
    {
        $ana = $this->user('Ana', 'ana@example.com');
        $this->actingAs($this->admin);

        $this->getJson(self::BASE.'/users/'.$ana->id.'/roles')
            ->assertOk()
            ->assertExactJson(['user' => ['id' => $ana->id, 'name' => 'Ana'], 'assigned' => []]);

        $this->postJson(self::BASE.'/users/'.$ana->id.'/roles', ['role_id' => 0])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['role_id']]);

        $added = $this->postJson(self::BASE.'/users/'.$ana->id.'/roles', ['role_id' => $this->seller->id, 'company_id' => $this->acme->id])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assigned.0.role_name', 'Vendedor')
            ->assertJsonPath('assigned.0.company_name', 'Acme');

        $this->postJson(self::BASE.'/users/'.$ana->id.'/roles', ['role_id' => $this->seller->id])
            ->assertOk()
            ->assertJsonCount(2, 'assigned')
            ->assertJsonPath('assigned.1.company_id', null);

        $this->deleteJson(self::BASE.'/users/'.$ana->id.'/roles/'.$added->json('assigned.0.id'))
            ->assertOk()
            ->assertJsonCount(1, 'assigned');
    }

    #[Test]
    public function a_master_role_and_another_users_role_cannot_be_removed(): void
    {
        $ana = $this->user('Ana', 'ana@example.com');
        $anaRole = UserRole::create(['user_id' => $ana->id, 'role_id' => $this->seller->id, 'company_id' => null, 'is_active' => true]);
        $adminMaster = UserRole::where('user_id', $this->admin->id)->firstOrFail();
        $this->actingAs($this->admin);

        $this->deleteJson(self::BASE.'/users/'.$this->admin->id.'/roles/'.$adminMaster->id)
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
        $this->assertNotNull($adminMaster->fresh());

        // Forged: a binding of Ana through the admin's URL.
        $this->deleteJson(self::BASE.'/users/'.$this->admin->id.'/roles/'.$anaRole->id)->assertStatus(422);
        $this->assertNotNull($anaRole->fresh());
    }

    // ── Audit ───────────────────────────────────────────────────────────────

    #[Test]
    public function the_audit_log_lists_filters_and_whitelists(): void
    {
        PermissionAudit::create(['user_id' => 7, 'resource_key' => 'orders', 'action' => 'read', 'result' => 'granted', 'ip_address' => '10.0.0.1', 'user_agent' => 'Secret UA', 'context' => ['x' => 1]]);
        PermissionAudit::create(['user_id' => 8, 'resource_key' => 'invoices', 'action' => 'delete', 'result' => 'denied', 'ip_address' => '10.0.0.2']);
        $this->actingAs($this->admin);

        $response = $this->getJson(self::BASE.'/audit')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 50);
        $this->assertSame(['id', 'created_at', 'created_at_display', 'user_id', 'resource_key', 'action', 'result', 'ip_address'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('Secret UA', $response->getContent());

        $this->getJson(self::BASE.'/audit?result=denied')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.resource_key', 'invoices');
        $this->getJson(self::BASE.'/audit?action=read')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.user_id', 7);
        $this->getJson(self::BASE.'/audit?search=10.0.0.2')->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'/audit?date_from='.now()->addDay()->toDateString())->assertJsonPath('meta.total', 0);
        $this->getJson(self::BASE.'/audit?date_to='.now()->toDateString())->assertJsonPath('meta.total', 2);
    }
}

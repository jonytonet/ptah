<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

class ManagedUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    // O $fillable do host nao decide se o admin cria usuario (forceFill).
    protected $fillable = [];
}

class OnlyExampleDotCom implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('email', 'like', '%@example.com');
    }
}

/**
 * Creating and editing users from the users screen — master-only, through
 * the same query the list uses (the host's `user_query_scope` included).
 */
class UserManagementTest extends TestCase
{
    use ActsAsPtahUser;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // O modulo auth registra `password.reset`, a pagina do link.
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('auth.providers.users.model', ManagedUser::class);
        $app['config']->set('ptah.permissions.user_model', ManagedUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Vem no esqueleto de todo app Laravel; o testbench nao cria.
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }

        $this->actingAs(ManagedUser::forceCreate(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('x')]));
        $this->actAsMaster();
        Notification::fake();
    }

    #[Test]
    public function a_user_created_without_a_password_gets_a_link_to_set_one(): void
    {
        Livewire::test(UserPermissionList::class)
            ->call('newUser')
            ->assertSet('userForm.send_link', true)
            ->set('userForm.name', 'Maria Silva')
            ->set('userForm.email', 'maria@example.com')
            ->call('saveUser')
            ->assertSet('showUserForm', false)
            ->assertSet('userFormErrors', []);

        $maria = ManagedUser::where('email', 'maria@example.com')->first();
        $this->assertNotNull($maria);
        $this->assertSame('Maria Silva', $maria->name);
        $this->assertNotEmpty($maria->password, 'Sem senha nenhuma a linha nao existiria em muitos schemas.');
        Notification::assertSentTo($maria, ResetPassword::class);
    }

    #[Test]
    public function a_typed_password_is_hashed_and_no_link_is_needed(): void
    {
        Livewire::test(UserPermissionList::class)
            ->call('newUser')
            ->set('userForm', ['name' => 'João', 'email' => 'joao@example.com', 'password' => 'segredo-forte', 'send_link' => false])
            ->call('saveUser');

        $joao = ManagedUser::where('email', 'joao@example.com')->first();
        $this->assertTrue(Hash::check('segredo-forte', $joao->password));
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_taken_email_and_a_short_password_are_refused(): void
    {
        Livewire::test(UserPermissionList::class)
            ->call('newUser')
            ->set('userForm', ['name' => 'Outro', 'email' => 'admin@example.com', 'password' => '123', 'send_link' => false])
            ->call('saveUser')
            ->assertSet('showUserForm', true)
            ->assertCount('userFormErrors', 2);

        $this->assertSame(1, ManagedUser::count());
    }

    #[Test]
    public function editing_keeps_the_password_when_the_field_is_blank(): void
    {
        $user = ManagedUser::forceCreate(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => Hash::make('original')]);

        Livewire::test(UserPermissionList::class)
            ->call('editUser', $user->id)
            ->assertSet('userForm.email', 'ana@example.com')
            ->set('userForm.name', 'Ana Souza')
            ->call('saveUser');

        $user->refresh();
        $this->assertSame('Ana Souza', $user->name);
        $this->assertTrue(Hash::check('original', $user->password));
    }

    #[Test]
    public function the_user_query_scope_bounds_edit_and_link_too(): void
    {
        config(['ptah.permissions.user_query_scope' => OnlyExampleDotCom::class]);
        $outside = ManagedUser::forceCreate(['name' => 'Fora', 'email' => 'fora@other.org', 'password' => Hash::make('x')]);

        Livewire::test(UserPermissionList::class)
            ->call('editUser', $outside->id)
            ->assertSet('showUserForm', false)
            ->call('sendPasswordLink', $outside->id);

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_non_master_cannot_open_the_screen(): void
    {
        $this->actAsMaster(false);

        Livewire::test(UserPermissionList::class)->assertStatus(403);
    }
}

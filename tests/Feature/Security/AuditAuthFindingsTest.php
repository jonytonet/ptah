<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\ResetPasswordPage;
use Ptah\Tests\TestCase;

class AuditAuthUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'two_factor_type', 'two_factor_confirmed_at', 'remember_token'];
}

/**
 * The auth findings of the 28/09/2026 surface audit, fixed in 1.41.8: with a
 * stolen session, each of these turned a temporary theft into a permanent one.
 */
class AuditAuthFindingsTest extends TestCase
{
    private AuditAuthUser $user;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Rotas de auth (as views de login/reset apontam para elas).
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', AuditAuthUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => AuditAuthUser::class]);

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }

        $this->user = AuditAuthUser::create([
            'name' => 'Ana', 'email' => 'ana@x.test', 'password' => bcrypt('secret-123'),
            'two_factor_type' => 'email', 'two_factor_confirmed_at' => now(), 'remember_token' => 'old-token',
        ]);
    }

    private function seedSession(string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $this->user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit', 'payload' => 'x', 'last_activity' => time(),
        ]);
    }

    #[Test]
    public function two_factor_cannot_be_turned_off_without_the_password(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ProfilePage::class)->call('disableTwoFactor')->assertHasErrors('twofa_password');
        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);

        Livewire::test(ProfilePage::class)->set('twofa_password', 'secret-123')->call('disableTwoFactor')->assertHasNoErrors();
        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);
    }

    #[Test]
    public function the_recovery_codes_and_a_new_secret_ask_for_the_password_too(): void
    {
        $this->actingAs($this->user);

        foreach (['loadRecoveryCodes', 'regenerateRecoveryCodes', 'initTotp', 'enableEmailTwoFactor'] as $action) {
            Livewire::test(ProfilePage::class)->call($action)->assertHasErrors('twofa_password');
        }
    }

    #[Test]
    public function the_pending_totp_secret_cannot_be_set_by_the_browser(): void
    {
        $this->actingAs($this->user);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ProfilePage::class)->set('pendingTotpSecret', 'ATTACKERSECRET');
    }

    #[Test]
    public function changing_the_password_ends_the_other_sessions_and_the_remember_me(): void
    {
        $this->seedSession('other-device');
        $this->actingAs($this->user);

        Livewire::test(ProfilePage::class)
            ->set('current_password', 'secret-123')
            ->set('password', 'nova-senha-9')->set('password_confirmation', 'nova-senha-9')
            ->call('savePassword');

        $this->assertSame(0, DB::table('sessions')->where('id', 'other-device')->count());
        $this->assertNotSame('old-token', $this->user->fresh()->remember_token);
    }

    #[Test]
    public function revoking_a_device_also_voids_its_remember_me_cookie(): void
    {
        $this->seedSession('lost-phone');
        $this->actingAs($this->user);

        Livewire::test(ProfilePage::class)->call('revokeSession', 'lost-phone');

        $this->assertNotSame('old-token', $this->user->fresh()->remember_token);
    }

    #[Test]
    public function forgot_password_answers_the_same_for_an_unknown_email(): void
    {
        Notification::fake();

        $known = Livewire::test(ForgotPasswordPage::class)->set('email', 'ana@x.test')->call('sendLink');
        $unknown = Livewire::test(ForgotPasswordPage::class)->set('email', 'ninguem@x.test')->call('sendLink');

        $this->assertSame($known->get('status'), $unknown->get('status'));
        $this->assertSame('', $unknown->get('errorMsg'));
    }

    #[Test]
    public function resetting_the_password_ends_every_session_of_the_account(): void
    {
        $this->seedSession('attacker');
        $token = Password::broker()->createToken($this->user);

        Livewire::test(ResetPasswordPage::class, ['token' => $token])
            ->set('email', 'ana@x.test')
            ->set('password', 'outra-senha-9')->set('password_confirmation', 'outra-senha-9')
            ->call('resetPassword');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
    }
}

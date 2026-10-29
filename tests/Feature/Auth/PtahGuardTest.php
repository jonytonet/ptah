<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Services\Auth\SessionService;
use Ptah\Support\PtahGuard;
use Ptah\Support\PtahPasswordReset;
use Ptah\Tests\TestCase;

/** The admins of a host whose staff sign in on their own guard. */
class GuardAdmin extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'two_factor_type', 'two_factor_recovery_codes'];

    public static ?string $lastResetUrl = null;

    public function sendPasswordResetNotification($token): void
    {
        // Rendered at send time, like a non-queued notification is.
        self::$lastResetUrl = (new ResetPassword($token))->toMail($this)->actionUrl;
    }
}

/**
 * Laudo 1.53.0: the auth module ignored `ptah.auth.guard` — the 2FA challenge
 * loaded `auth.providers.users.model` and signed into the default guard, the
 * login did the same, sessions of two guards with the same user id mixed, and
 * the reset page's name collided with Fortify's.
 */
class PtahGuardTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.auth.guard', 'admin');
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
        $app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => GuardAdmin::class]);
        $app['config']->set('auth.passwords.admins', ['provider' => 'admins', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 0]);
    }

    private function admin(array $extra = []): GuardAdmin
    {
        return GuardAdmin::create(array_merge([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ], $extra));
    }

    #[Test]
    public function the_helper_resolves_the_configured_guard_and_its_provider(): void
    {
        $admin = $this->admin();

        $this->assertSame('admin', PtahGuard::name());
        $this->assertInstanceOf(GuardAdmin::class, PtahGuard::findUser($admin->id));
        $this->assertSame('admins', PtahGuard::broker());
    }

    #[Test]
    public function login_signs_into_the_ptah_guard(): void
    {
        $admin = $this->admin();

        Livewire::test(LoginPage::class)
            ->set('email', $admin->email)
            ->set('password', 'secret')
            ->call('login');

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function the_2fa_challenge_loads_the_guards_model_and_signs_into_its_guard_once(): void
    {
        Event::fake([Login::class]);
        $admin = $this->admin([
            'two_factor_type' => 'totp',
            'two_factor_recovery_codes' => encrypt(json_encode(['RECOVERY-1'])),
        ]);
        session(['ptah.2fa.user_id' => $admin->id]);

        Livewire::test(TwoFactorChallengePage::class)
            ->set('usingRecovery', true)
            ->set('code', 'RECOVERY-1')
            ->call('verify')
            ->assertSet('errorMsg', '');

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertFalse(Auth::guard('web')->check());
        Event::assertDispatchedTimes(Login::class, 1);
        Event::assertDispatched(Login::class, fn (Login $e) => $e->guard === 'admin');
    }

    #[Test]
    public function the_protected_routes_require_the_ptah_guard(): void
    {
        $this->assertContains('auth:admin', Route::getRoutes()->getByName('ptah.profile')->middleware());
    }

    // ── sessions ──────────────────────────────────────────────────────────

    private function seedSession(string $id, int $userId, string $guard): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(serialize(['login_'.$guard.'_'.sha1(SessionGuard::class) => $userId])),
            'last_activity' => time(),
        ]);
    }

    #[Test]
    public function sessions_of_another_guard_with_the_same_id_are_not_the_users(): void
    {
        $admin = $this->admin();
        $this->seedSession('admin-session', $admin->id, 'admin');
        $this->seedSession('portal-session', $admin->id, 'portal');
        $this->seedSession('unreadable', $admin->id, 'admin');
        DB::table('sessions')->where('id', 'unreadable')->update(['payload' => 'x']);

        $service = new SessionService;

        $this->assertSame(['admin-session'], array_column($service->getActiveSessions($admin)->all(), 'id'));

        $service->revokeSession('portal-session', $admin);
        $this->assertDatabaseHas('sessions', ['id' => 'portal-session']);

        $this->assertSame(1, $service->revokeOtherSessions($admin, 'none'));
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'portal-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unreadable']);
    }

    // ── password reset ────────────────────────────────────────────────────

    private function resetTokensTable(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    #[Test]
    public function the_reset_page_no_longer_takes_fortifys_route_name(): void
    {
        $this->assertTrue(Route::has(PtahPasswordReset::ROUTE));
        $this->assertFalse(Route::has('password.reset'));
    }

    #[Test]
    public function ptahs_reset_link_points_at_the_ptah_page_even_when_password_reset_exists(): void
    {
        $this->resetTokensTable();
        Route::get('/fortify/reset/{token}', fn () => 'fortify')->name('password.reset');
        app('router')->getRoutes()->refreshNameLookups();
        $admin = $this->admin();

        PtahPasswordReset::sendLink($admin->email);

        $this->assertStringContainsString('/reset-password/', (string) GuardAdmin::$lastResetUrl);
        $this->assertStringNotContainsString('/fortify/', (string) GuardAdmin::$lastResetUrl);

        // Outside ptah, Laravel's own target wins when the host has one.
        $this->assertStringContainsString('/fortify/reset/', PtahPasswordReset::fallbackUrl($admin, 'tok'));
    }

    #[Test]
    public function a_reset_link_sent_outside_ptah_falls_back_to_the_ptah_page(): void
    {
        $this->resetTokensTable();
        $admin = $this->admin();

        $this->assertStringContainsString('/reset-password/tok', PtahPasswordReset::fallbackUrl($admin, 'tok'));
        $this->assertNotNull(ResetPassword::$createUrlCallback, 'O provider registra o fallback quando o host nao tem callback proprio.');
    }
}

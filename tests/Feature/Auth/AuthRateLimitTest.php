<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Tests\TestCase;

// ── Minimal User model for auth tests ────────────────────────────────────────

class AuthTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'two_factor_type'];

    protected $hidden = ['password', 'remember_token'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * P0 regression tests for rate limiting on the auth screens.
 *
 * Verifies that LoginPage, ForgotPasswordPage and TwoFactorChallengePage all
 * enforce their respective attempt limits. Throttle keys are pre-hit manually
 * to avoid real password-reset emails or TOTP verification calls.
 */
class AuthRateLimitTest extends TestCase
{
    /**
     * Enable the auth module BEFORE providers boot so ptah-auth.php routes
     * are registered and views can call route('ptah.auth.login') etc.
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', AuthTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Also set it at runtime so config() calls inside components see it.
        config(['auth.providers.users.model' => AuthTestUser::class]);
    }

    // ── LoginPage ─────────────────────────────────────────────────────────────

    #[Test]
    public function login_page_blocks_after_five_failed_attempts(): void
    {
        $email = 'brute@example.com';
        $ip = request()->ip();
        $key = mb_strtolower($email).'|'.$ip;

        // 5 pre-hits simulate 5 failed login attempts.
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(LoginPage::class)
            ->set('email', $email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertSet('errorMessage', fn ($v) => ! empty($v));
    }

    #[Test]
    public function login_page_allows_attempts_under_the_limit(): void
    {
        AuthTestUser::create([
            'name' => 'Valid User',
            'email' => 'valid@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // 4 hits — one below the limit of 5.
        $email = 'valid@example.com';
        $key = mb_strtolower($email).'|'.request()->ip();

        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit($key, 60);
        }

        // 5th attempt with WRONG password → "invalid credentials", NOT throttled.
        $component = Livewire::test(LoginPage::class)
            ->set('email', $email)
            ->set('password', 'wrong')
            ->call('login');

        // Must show credentials error, not a too-many-attempts error.
        $errorMsg = $component->get('errorMessage');
        $this->assertNotEmpty($errorMsg);
        $this->assertStringNotContainsString(
            (string) trans('ptah::ui.auth_too_many_attempts', ['seconds' => 60]),
            $errorMsg,
        );
    }

    // ── ForgotPasswordPage ────────────────────────────────────────────────────

    #[Test]
    public function forgot_password_blocks_after_three_attempts(): void
    {
        $email = 'reset@example.com';
        $ip = request()->ip();
        $key = 'ptah-forgot|'.mb_strtolower($email).'|'.$ip;

        // 3 pre-hits → next call must be throttled (tooManyAttempts(3, 3) == true).
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($key, 300);
        }

        Livewire::test(ForgotPasswordPage::class)
            ->set('email', $email)
            ->call('sendLink')
            ->assertSet('status', '')
            ->assertSet('errorMsg', fn ($v) => ! empty($v));
    }

    #[Test]
    public function forgot_password_does_not_throttle_below_limit(): void
    {
        $email = 'ok@example.com';
        $ip = request()->ip();
        $key = 'ptah-forgot|'.mb_strtolower($email).'|'.$ip;

        // 2 pre-hits — one below the limit.
        RateLimiter::hit($key, 300);
        RateLimiter::hit($key, 300);

        // 3rd attempt: NOT throttled, but will fail because no user for password reset.
        // We only assert it's not the throttle error.
        $component = Livewire::test(ForgotPasswordPage::class)
            ->set('email', $email)
            ->call('sendLink');

        $errorMsg = $component->get('errorMsg');

        // If throttled the status would be empty and errorMsg would be the throttle msg;
        // if not throttled the errorMsg is either empty (link sent) or the broker error.
        // Either way, the throttle message key must NOT appear here.
        if (! empty($errorMsg)) {
            $this->assertStringNotContainsString(
                (string) trans('ptah::ui.auth_too_many_attempts', ['seconds' => 300]),
                $errorMsg,
                'Under the rate limit, the throttle message must not appear',
            );
        }
    }

    // ── TwoFactorChallengePage ────────────────────────────────────────────────

    #[Test]
    public function two_factor_blocks_after_five_failed_code_attempts(): void
    {
        $userId = 42; // Arbitrary ID — never reaches findOrFail when throttled.
        $ip = request()->ip();
        $key = 'ptah-2fa|'.$userId.'|'.$ip;

        // Put the user ID in the session so mount() does not redirect.
        session(['ptah.2fa.user_id' => $userId]);

        // 5 pre-hits → next call must be throttled.
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(TwoFactorChallengePage::class)
            ->set('code', '000000')
            ->call('verify')
            ->assertSet('errorMsg', fn ($v) => ! empty($v));
    }

    #[Test]
    public function two_factor_throttle_key_includes_user_id_and_ip(): void
    {
        // Verify the key is user-specific: a different userId has its own counter.
        $userId = 99;
        $ip = request()->ip();

        session(['ptah.2fa.user_id' => $userId]);

        // Only 4 hits for userId=99 — must NOT be throttled yet.
        $keyFor99 = 'ptah-2fa|99|'.$ip;
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit($keyFor99, 60);
        }

        // Also hit 5 times for userId=1 (a different user) — irrelevant to user 99.
        $keyFor1 = 'ptah-2fa|1|'.$ip;
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($keyFor1, 60);
        }

        // User 99 must NOT be throttled yet (only 4 hits).
        // The component will reach findOrFail(99) and fail with ModelNotFoundException.
        // That exception propagates from Livewire as a 500 — acceptable here because
        // the rate-limit guard did NOT activate (which is what we're testing).
        try {
            $component = Livewire::test(TwoFactorChallengePage::class)
                ->set('code', '000000')
                ->call('verify');

            // If we reach this point, either the user was found (unlikely in tests)
            // or verify() bailed earlier. In any case, the errorMsg must NOT be
            // the throttle message.
            $errorMsg = $component->get('errorMsg');
            if (! empty($errorMsg)) {
                $this->assertStringNotContainsString(
                    (string) trans('ptah::ui.auth_too_many_attempts', ['seconds' => 60]),
                    $errorMsg,
                );
            }
        } catch (ModelNotFoundException $e) {
            // Expected — user 99 doesn't exist in the test DB. Rate limit was NOT
            // hit (otherwise we'd have returned early and never called findOrFail).
            $this->assertTrue(true, 'Rate limit correctly not triggered for user with < 5 attempts');
        }
    }
}

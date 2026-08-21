<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Mail\TwoFactorCodeMail;
use Ptah\Tests\TestCase;

class SendRateLimitTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'two_factor_type'];

    protected $hidden = ['password', 'remember_token'];
}

/**
 * Guards the rate limit on the two-factor EMAIL CODE SEND action — without
 * it, an attacker who knows (or guesses) a victim's user id can repeatedly
 * trigger sendEmailCode()/enableEmailTwoFactor() to flood the victim's inbox
 * ("email bombing"). Mirrors the existing throttle on code VERIFICATION
 * (see AuthRateLimitTest), but on the send side, with its own bucket/key so
 * the two limits do not interfere with each other.
 */
class EmailTwoFactorSendRateLimitTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', SendRateLimitTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => SendRateLimitTestUser::class]);
    }

    // ── TwoFactorChallengePage::sendEmailCode ────────────────────────────────

    #[Test]
    public function challenge_page_blocks_the_fourth_send_within_a_minute(): void
    {
        Mail::fake();

        $user = SendRateLimitTestUser::create(['name' => 'Vic', 'email' => 'vic@example.com', 'password' => 'x']);
        session(['ptah.2fa.user_id' => $user->id]);

        $key = 'ptah-2fa-send|'.$user->id.'|'.request()->ip();

        // 3 pre-hits simulate 3 sends already made this minute.
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(TwoFactorChallengePage::class)
            ->call('sendEmailCode')
            ->assertSet('errorMsg', fn ($v) => ! empty($v));

        Mail::assertNothingSent();
    }

    #[Test]
    public function challenge_page_allows_sends_under_the_limit(): void
    {
        Mail::fake();

        $user = SendRateLimitTestUser::create(['name' => 'Vic', 'email' => 'vic@example.com', 'password' => 'x']);
        session(['ptah.2fa.user_id' => $user->id]);

        Livewire::test(TwoFactorChallengePage::class)
            ->call('sendEmailCode')
            ->assertSet('errorMsg', '');

        Mail::assertSent(TwoFactorCodeMail::class);
    }

    // ── ProfilePage::enableEmailTwoFactor ─────────────────────────────────────

    #[Test]
    public function profile_page_blocks_the_fourth_send_within_a_minute(): void
    {
        Mail::fake();

        $user = SendRateLimitTestUser::create(['name' => 'Vic', 'email' => 'vic@example.com', 'password' => 'x']);
        $this->actingAs($user);

        $key = 'ptah-2fa-send|'.$user->id.'|'.request()->ip();

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(ProfilePage::class)
            ->call('enableEmailTwoFactor')
            ->assertSet('errorMsg', fn ($v) => ! empty($v));

        Mail::assertNothingSent();
    }

    #[Test]
    public function profile_page_allows_sends_under_the_limit(): void
    {
        Mail::fake();

        $user = SendRateLimitTestUser::create(['name' => 'Vic', 'email' => 'vic@example.com', 'password' => 'x']);
        $this->actingAs($user);

        Livewire::test(ProfilePage::class)
            ->call('enableEmailTwoFactor')
            ->assertSet('errorMsg', '');

        Mail::assertSent(TwoFactorCodeMail::class);
    }
}

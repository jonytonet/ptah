<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Models\UserPreference;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;

// ── Stub ──────────────────────────────────────────────────────────────────────

class TwoFactorAppearanceTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'two_factor_type', 'two_factor_recovery_codes'];

    protected $hidden = ['password', 'remember_token'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * A user with 2FA enabled only finishes "login" on TwoFactorChallengePage::verify(),
 * not on LoginPage::login() — so the ptah_appearance cookie must be seeded/refreshed
 * there too, or that user's browser would never see their saved theme on the next
 * visit to the login screen (see AppearanceCookieTest for the LoginPage half).
 */
class TwoFactorChallengeAppearanceCookieTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', TwoFactorAppearanceTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => TwoFactorAppearanceTestUser::class]);
    }

    #[Test]
    public function completing_the_2fa_challenge_queues_the_cookie_from_the_database_preference(): void
    {
        $user = TwoFactorAppearanceTestUser::create([
            'name' => 'Ana',
            'email' => 'ana'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'two_factor_type' => 'totp',
            'two_factor_recovery_codes' => encrypt(json_encode(['RECOVERY-CODE-1'])),
        ]);

        UserPreference::set($user->id, 'theme', [
            'mode' => 'dark',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
            'density' => 'compacta',
            'fontsize' => 'pequena',
        ], 'appearance');

        session(['ptah.2fa.user_id' => $user->id]);

        Livewire::test(TwoFactorChallengePage::class)
            ->set('usingRecovery', true)
            ->set('code', 'RECOVERY-CODE-1')
            ->call('verify');

        $queued = Cookie::queued(AppearancePresets::COOKIE);
        $this->assertNotNull($queued);
        $this->assertSame([
            'mode' => 'dark',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
            'density' => 'compacta',
            'fontsize' => 'pequena',
        ], json_decode($queued->getValue(), true));
    }
}

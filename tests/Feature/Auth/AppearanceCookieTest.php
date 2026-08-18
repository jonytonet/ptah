<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Models\UserPreference;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;

// ── Stub ──────────────────────────────────────────────────────────────────────

class AppearanceCookieTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers the `ptah_appearance` cookie end-to-end on the screens that have no
 * authenticated user (resources/views/layouts/forge-auth.blade.php), plus the
 * precedence rule on the dashboard layout: the database always wins over the
 * cookie for an authenticated user who has a saved preference.
 *
 * See also tests/Unit/Support/AppearanceCookieSanitizeTest.php (pure sanitize()
 * coverage, no HTTP/view rendering) and
 * tests/Feature/Livewire/ProfilePageAppearanceTest.php (the /profile tab itself).
 */
class AppearanceCookieTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', AppearanceCookieTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => AppearanceCookieTestUser::class]);
    }

    private function makeUser(): AppearanceCookieTestUser
    {
        return AppearanceCookieTestUser::create([
            'name' => 'Ana',
            'email' => 'ana'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);
    }

    // ── (a) lixo no cookie → defaults no HTML ────────────────────────────────

    #[Test]
    public function login_screen_renders_defaults_when_the_cookie_is_garbage(): void
    {
        $response = $this
            ->withCookie(AppearancePresets::COOKIE, '" onload=alert(1)')
            ->get(route('ptah.auth.login'));

        $response->assertOk();
        $response->assertSee('data-ptah-light="'.AppearancePresets::DEFAULT_LIGHT.'"', false);
        $response->assertSee('data-ptah-dark="'.AppearancePresets::DEFAULT_DARK.'"', false);
        $response->assertSee('data-ptah-accent="'.AppearancePresets::DEFAULT_ACCENT.'"', false);
        $response->assertSee('data-ptah-text="'.AppearancePresets::DEFAULT_TEXT.'"', false);
        $response->assertDontSee('onload=alert', false);
    }

    #[Test]
    public function login_screen_renders_defaults_when_the_cookie_names_an_unknown_preset(): void
    {
        $raw = json_encode(['light' => 'nao-existe', 'accent' => 'nao-existe']);

        $response = $this
            ->withCookie(AppearancePresets::COOKIE, $raw)
            ->get(route('ptah.auth.login'));

        $response->assertOk();
        $response->assertSee('data-ptah-light="'.AppearancePresets::DEFAULT_LIGHT.'"', false);
        $response->assertSee('data-ptah-accent="'.AppearancePresets::DEFAULT_ACCENT.'"', false);
        $response->assertDontSee('nao-existe', false);
    }

    // ── (b) a tela de login renderiza os atributos do cookie ─────────────────

    #[Test]
    public function login_screen_renders_the_axes_from_a_well_formed_cookie(): void
    {
        $raw = json_encode([
            'mode' => 'dark',
            'light' => 'papel',
            'dark' => 'carvao',
            'accent' => 'ciano',
            'text' => 'forte',
        ]);

        $response = $this
            ->withCookie(AppearancePresets::COOKIE, $raw)
            ->get(route('ptah.auth.login'));

        $response->assertOk();
        $response->assertSee('data-ptah-light="papel"', false);
        $response->assertSee('data-ptah-dark="carvao"', false);
        $response->assertSee('data-ptah-accent="ciano"', false);
        $response->assertSee('data-ptah-text="forte"', false);
        // Anti-flash boot script (ptah::partials.appearance-boot) receives the
        // sanitized "mode" via @js($theme).
        $response->assertSee("var serverTheme = 'dark'", false);
    }

    // ── (c) usuário autenticado com preferência no banco ignora cookie divergente ──

    #[Test]
    public function dashboard_layout_prefers_the_database_over_a_divergent_cookie(): void
    {
        $user = $this->makeUser();

        UserPreference::set($user->id, 'theme', [
            'mode' => 'light',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
        ], 'appearance');

        $divergentCookie = json_encode([
            'mode' => 'dark',
            'light' => 'papel',
            'dark' => 'carvao',
            'accent' => 'ciano',
            'text' => 'forte',
        ]);

        $response = $this
            ->actingAs($user)
            ->withCookie(AppearancePresets::COOKIE, $divergentCookie)
            ->get(route('ptah.dashboard'));

        $response->assertOk();
        $response->assertSee('data-ptah-light="nevoa"', false);
        $response->assertSee('data-ptah-dark="meianoite"', false);
        $response->assertSee('data-ptah-accent="teal"', false);
        $response->assertSee('data-ptah-text="suave"', false);
        $response->assertDontSee('data-ptah-light="papel"', false);
        $response->assertDontSee('data-ptah-accent="ciano"', false);
    }

    #[Test]
    public function dashboard_layout_falls_back_to_the_cookie_when_the_user_never_saved_a_preference(): void
    {
        $user = $this->makeUser();

        $cookie = json_encode([
            'mode' => 'dark',
            'light' => 'papel',
            'dark' => 'carvao',
            'accent' => 'ciano',
            'text' => 'forte',
        ]);

        $response = $this
            ->actingAs($user)
            ->withCookie(AppearancePresets::COOKIE, $cookie)
            ->get(route('ptah.dashboard'));

        $response->assertOk();
        $response->assertSee('data-ptah-light="papel"', false);
        $response->assertSee('data-ptah-dark="carvao"', false);
        $response->assertSee('data-ptah-accent="ciano"', false);
        $response->assertSee('data-ptah-text="forte"', false);
    }

    // ── Servidor grava o cookie no login ──────────────────────────────────────

    #[Test]
    public function successful_login_queues_the_cookie_from_the_database_preference(): void
    {
        $user = AppearanceCookieTestUser::create([
            'name' => 'Ana',
            'email' => 'ana'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);

        UserPreference::set($user->id, 'theme', [
            'mode' => 'dark',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
        ], 'appearance');

        Livewire::test(LoginPage::class)
            ->set('email', $user->email)
            ->set('password', 'secret')
            ->call('login');

        $this->assertTrue(Cookie::hasQueued(AppearancePresets::COOKIE));

        $queued = Cookie::queued(AppearancePresets::COOKIE);
        $this->assertNotNull($queued);
        $this->assertSame([
            'mode' => 'dark',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
        ], json_decode($queued->getValue(), true));
        $this->assertTrue($queued->isHttpOnly());
    }
}

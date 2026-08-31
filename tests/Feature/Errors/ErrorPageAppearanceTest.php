<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Errors;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\UserPreference;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;

// ── Stub ──────────────────────────────────────────────────────────────────────

class ErrorPageAppearanceUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * The error pages have to honour the appearance the user actually chose.
 *
 * 1.29.0 shipped them following the theme *tokens* but never stamping the
 * theme: `--ptah-canvas` resolved correctly, yet nothing put `.ptah-dark` on
 * `<html>`, so a user whose profile says dark was shown a white 403. Reported
 * by the package author against the release.
 *
 * The dashboard and auth layouts paint that class from a blocking script
 * (`ptah::partials.appearance-boot`). These pages cannot: they carry no JS by
 * design. So the class is stamped server-side, which is strictly better here —
 * no flash is possible — and is what these tests pin.
 *
 * The last test is the one that must never regress: resolving the preference
 * touches the database, and a 500 may be rendering precisely because the
 * database is gone. The page must degrade, never throw.
 */
class ErrorPageAppearanceTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', ErrorPageAppearanceUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => ErrorPageAppearanceUser::class]);
    }

    private function makeUser(): ErrorPageAppearanceUser
    {
        return ErrorPageAppearanceUser::create([
            'name' => 'Ana',
            'email' => 'ana'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderError(string $code, array $data = []): string
    {
        app('view')->flushState();

        return View::make("ptah::errors.{$code}", $data)->render();
    }

    #[Test]
    public function a_dark_preference_in_the_database_paints_the_page_dark(): void
    {
        $user = $this->makeUser();

        UserPreference::set($user->id, 'theme', [
            'mode' => 'dark',
            'dark' => 'meianoite',
            'accent' => 'teal',
        ], 'appearance');

        $this->actingAs($user);

        $html = $this->renderError('403');

        // The class is what actually switches the token block in
        // ptah-components.css. Without it every colour falls to `:root`.
        $this->assertStringContainsString(
            'ptah-dark',
            $html,
            'A pagina de erro precisa estampar .ptah-dark quando o usuario escolheu escuro — '.
            'sem essa classe os tokens --ptah-* resolvem para o bloco claro de :root.'
        );

        // And the specific palettes, so it is the chosen dark, not just any dark.
        $this->assertStringContainsString('data-ptah-dark="meianoite"', $html);
        $this->assertStringContainsString('data-ptah-accent="teal"', $html);
    }

    #[Test]
    public function an_explicit_light_preference_is_not_overridden(): void
    {
        $user = $this->makeUser();

        UserPreference::set($user->id, 'theme', ['mode' => 'light', 'light' => 'papel'], 'appearance');

        $this->actingAs($user);

        $html = $this->renderError('404');

        $this->assertStringNotContainsString('ptah-dark"', $html);
        $this->assertStringContainsString('data-ptah-light="papel"', $html);
    }

    #[Test]
    public function a_visitor_gets_the_appearance_from_the_cookie(): void
    {
        // No authenticated user: the `ptah_appearance` cookie is the only
        // server-visible record, and the theme-mode endpoint writes it
        // alongside the database precisely so screens like this one can read it.
        request()->cookies->set(
            AppearancePresets::COOKIE,
            (string) json_encode(['mode' => 'dark', 'dark' => 'carvao'])
        );

        $html = $this->renderError('500', ['errorId' => null]);

        $this->assertStringContainsString('ptah-dark', $html);
        $this->assertStringContainsString('data-ptah-dark="carvao"', $html);
    }

    #[Test]
    public function the_page_still_renders_when_the_preference_lookup_throws(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // Simulates the scenario the 500 page exists for: the database is not
        // answering. Reading the appearance must not be able to take the error
        // page down with it — if it throws here, Laravel falls back to its bare
        // handler and the user sees nothing useful at all.
        Schema::drop('user_preferences');

        $html = $this->renderError('500', ['errorId' => 'abc123']);

        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('err-title', $html);

        // Degraded, not broken. The six axes still carry their defaults (the
        // resolver returns sanitize(null), never null), but `mode` stays unknown
        // so no dark class is claimed and the shell's prefers-color-scheme
        // fallback is what decides the colours.
        $this->assertStringNotContainsString('class="ptah-dark"', $html);
        $this->assertStringContainsString('prefers-color-scheme', $html);
    }

    #[Test]
    public function the_appearance_is_stamped_without_adding_any_script(): void
    {
        $user = $this->makeUser();
        UserPreference::set($user->id, 'theme', ['mode' => 'dark'], 'appearance');
        $this->actingAs($user);

        // The whole reason this is done server-side. A script here would be one
        // more thing that can fail while the site is already failing, and would
        // reintroduce the flash the dashboard's blocking script exists to avoid.
        $html = $this->renderError('403');

        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function a_real_404_request_outside_the_web_group_still_follows_the_theme(): void
    {
        // THE test this feature was missing. Every earlier appearance test
        // rendered the view directly, which quietly supplies a fully-booted
        // request: session started, cookies already decrypted by
        // `EncryptCookies`. A 404 for an unmatched URI gets none of that — the
        // route never enters the `web` group — so `request()->cookie()` returns
        // the RAW ENCRYPTED payload and the old code read it as "no preference".
        //
        // Result: the 403 honoured dark and the 404 did not, reported from the
        // ERP. Rendering the view could never have caught it; only a request
        // through the real path can.
        $response = $this
            // Plain JSON on purpose: `withCookie` encrypts it with the correct
            // prefix itself (MakesHttpRequests::prepareCookiesForRequest), which
            // is precisely what a browser sends. Encrypting it here as well
            // would double-wrap it and stop testing the real shape.
            ->withCookie(
                AppearancePresets::COOKIE,
                (string) json_encode(['mode' => 'dark', 'dark' => 'meianoite', 'accent' => 'teal'])
            )
            ->get('/uma-rota-que-nao-existe-'.uniqid());

        $response->assertNotFound();
        $response->assertSee('class="ptah-dark"', false);
        $response->assertSee('data-ptah-dark="meianoite"', false);
        $response->assertSee('data-ptah-accent="teal"', false);
    }

    #[Test]
    public function a_cookie_encrypted_under_a_foreign_key_is_rejected_not_half_trusted(): void
    {
        // The prefix check is the same one EncryptCookies performs. Without it,
        // decrypting whatever arrives would let a value minted elsewhere steer
        // the attributes — sanitize() would still bound them to the whitelist,
        // but the page would be honouring a cookie this app never issued.
        $foreign = Crypt::encrypt(
            CookieValuePrefix::create(AppearancePresets::COOKIE, str_repeat('x', 32))
            .json_encode(['mode' => 'dark', 'dark' => 'carvao']),
            false
        );

        // `withUnencryptedCookie`, not `withCookie`: the value must reach the
        // request exactly as built, since `withCookie` would re-encrypt it with
        // a VALID prefix and the foreign one would never be the prefix under
        // test.
        $response = $this
            ->withUnencryptedCookie(AppearancePresets::COOKIE, $foreign)
            ->get('/outra-rota-inexistente-'.uniqid());

        $response->assertNotFound();
        $response->assertDontSee('class="ptah-dark"', false);
    }
}

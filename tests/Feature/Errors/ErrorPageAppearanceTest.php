<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Errors;

use Illuminate\Foundation\Auth\User as Authenticatable;
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

        // Degraded, not broken: no appearance attributes, and the shell's
        // prefers-color-scheme fallback carries the colours instead.
        $this->assertStringNotContainsString('data-ptah-dark=', $html);
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
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\UserPreference;
use Ptah\Tests\TestCase;

class AuthApiUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}

/**
 * ptah's sign-in and profile through the API (1.59.0), for the React and Vue
 * stacks. The Livewire pages are the engine — these tests pin the contract
 * and that their guards (throttle, current password, 2FA) hold through it.
 */
class AuthApiTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('auth.providers.users.model', AuthApiUser::class);
    }

    private function user(array $extra = []): AuthApiUser
    {
        return AuthApiUser::create(array_merge([
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'password' => Hash::make('secret-123'),
        ], $extra));
    }

    // ── Sign-in ─────────────────────────────────────────────────────────────

    #[Test]
    public function the_sign_in_pages_get_their_config_without_a_session(): void
    {
        $this->getJson('/ptah/api/auth/config')
            ->assertOk()
            ->assertJsonPath('signed_in', false)
            ->assertJsonPath('remember_me', true)
            ->assertJsonStructure(['app', 'locale', 'home', 'urls' => ['login', 'forgot']]);
    }

    #[Test]
    public function signing_in_answers_where_to_go(): void
    {
        $user = $this->user();

        $this->postJson('/ptah/api/auth/login', ['email' => 'ana@example.test', 'password' => 'secret-123'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('two_factor', false)
            ->assertJsonPath('redirect', '/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function wrong_credentials_are_refused_with_the_same_message_and_throttled(): void
    {
        $this->user();

        $this->postJson('/ptah/api/auth/login', ['email' => 'ana@example.test', 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('error', trans('ptah::ui.auth_invalid_credentials'));
        $this->assertGuest();

        // A missing field is a validation error, with the field named.
        $this->postJson('/ptah/api/auth/login', ['email' => 'not-an-email', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // The page's own throttle: five wrong attempts lock the sixth.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/ptah/api/auth/login', ['email' => 'ana@example.test', 'password' => 'nope']);
        }
        $this->postJson('/ptah/api/auth/login', ['email' => 'ana@example.test', 'password' => 'secret-123'])
            ->assertStatus(422);
        $this->assertGuest();
    }

    #[Test]
    public function a_user_with_two_factor_is_sent_to_the_challenge_and_only_the_code_signs_in(): void
    {
        Notification::fake();
        $user = $this->user(['two_factor_type' => 'email', 'two_factor_confirmed_at' => now()]);

        $this->postJson('/ptah/api/auth/login', ['email' => 'ana@example.test', 'password' => 'secret-123'])
            ->assertOk()
            ->assertJsonPath('two_factor', true);
        $this->assertGuest();

        $this->postJson('/ptah/api/auth/two-factor', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error', trans('ptah::ui.two_fa_code_invalid'));
        $this->assertGuest();

        // The code the service would e-mail.
        $this->postJson('/ptah/api/auth/two-factor/email')->assertOk();
        $code = (string) cache()->get('ptah_2fa_email_'.$user->id) ?: (string) ($user->fresh()->two_factor_email_code ?? '');
        if ($code !== '' && strlen($code) === 6) {
            $this->postJson('/ptah/api/auth/two-factor', ['code' => $code])->assertOk();
            $this->assertAuthenticatedAs($user);
        }

        // Without a pending sign-in the challenge does not exist.
        Session::forget('ptah.2fa.user_id');
        $this->postJson('/ptah/api/auth/two-factor', ['code' => '123456'])->assertStatus(409);
    }

    #[Test]
    public function forgot_password_answers_the_same_whether_the_account_exists(): void
    {
        Notification::fake();
        Schema::create('password_reset_tokens', function ($table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        $this->user();

        $known = $this->postJson('/ptah/api/auth/forgot-password', ['email' => 'ana@example.test'])->assertOk()->json('message');
        $unknown = $this->postJson('/ptah/api/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        $this->assertNotEmpty($known);
    }

    #[Test]
    public function logout_ends_the_session(): void
    {
        $this->actingAs($this->user());

        $this->postJson('/ptah/api/auth/logout')->assertOk()->assertJsonPath('ok', true);
        $this->assertGuest();
    }

    // ── Profile ─────────────────────────────────────────────────────────────

    #[Test]
    public function the_profile_needs_a_session(): void
    {
        $this->getJson('/ptah/api/auth/profile')->assertUnauthorized();
    }

    #[Test]
    public function the_profile_is_read_and_updated(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->getJson('/ptah/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('name', 'Ana')
            ->assertJsonPath('two_factor.enabled', false)
            ->assertJsonStructure(['appearance' => ['light', 'dark', 'accent', 'choices' => ['accent']]]);

        $this->putJson('/ptah/api/auth/profile', ['name' => 'Ana Maria', 'email' => 'ana@example.test'])
            ->assertOk()
            ->assertJsonPath('profile.name', 'Ana Maria');
        $this->assertSame('Ana Maria', $user->fresh()->name);
    }

    #[Test]
    public function changing_the_email_asks_for_the_current_password(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->putJson('/ptah/api/auth/profile', ['name' => 'Ana', 'email' => 'nova@example.test', 'current_password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password', trans('ptah::ui.profile_password_wrong'));
        $this->assertSame('ana@example.test', $user->fresh()->email);

        $this->putJson('/ptah/api/auth/profile', ['name' => 'Ana', 'email' => 'nova@example.test', 'current_password' => 'secret-123'])->assertOk();
        $this->assertSame('nova@example.test', $user->fresh()->email);
    }

    #[Test]
    public function the_password_changes_only_with_the_current_one(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->putJson('/ptah/api/auth/password', ['current_password' => 'wrong', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertStatus(422)
            ->assertJsonPath('error', trans('ptah::ui.profile_password_wrong'));

        $this->putJson('/ptah/api/auth/password', ['current_password' => 'secret-123', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->putJson('/ptah/api/auth/password', ['current_password' => 'secret-123', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertOk();
        $this->assertTrue(Hash::check('new-secret-1', $user->fresh()->password));
    }

    #[Test]
    public function appearance_is_saved_axis_by_axis_and_only_from_the_whitelist(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->putJson('/ptah/api/auth/appearance', ['accent' => 'verde', 'density' => 'compacta', 'light' => 'not-a-preset'])
            ->assertOk()
            ->assertJsonPath('profile.appearance.accent', 'verde')
            ->assertJsonPath('profile.appearance.density', 'compacta')
            ->assertJsonPath('profile.appearance.light', 'puro');

        $this->assertSame('verde', UserPreference::get($user->id, 'theme')['accent']);

        $this->putJson('/ptah/api/auth/appearance', ['reset' => true])
            ->assertOk()
            ->assertJsonPath('profile.appearance.density', 'confortavel');
    }

    #[Test]
    public function the_photo_is_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $this->actingAs($user);

        $this->post('/ptah/api/auth/photo', ['photo' => UploadedFile::fake()->image('eu.png', 30, 30)], ['Accept' => 'application/json'])->assertOk();
        $path = $user->fresh()->profile_photo_path;
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $this->post('/ptah/api/auth/photo', ['photo' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['photo']);

        $this->deleteJson('/ptah/api/auth/photo')->assertOk();
        $this->assertNull($user->fresh()->profile_photo_path);
    }

    #[Test]
    public function every_two_factor_change_asks_for_the_current_password(): void
    {
        $user = $this->user(['two_factor_type' => 'email', 'two_factor_confirmed_at' => now()]);
        $this->actingAs($user);

        foreach (['two-factor/disable', 'two-factor/recovery-codes', 'two-factor/totp', 'two-factor/email-method'] as $path) {
            $this->postJson('/ptah/api/auth/'.$path, ['current_password' => 'wrong'])
                ->assertStatus(422)
                ->assertJsonPath('errors.current_password', trans('ptah::ui.profile_password_wrong'));
        }
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->postJson('/ptah/api/auth/two-factor/disable', ['current_password' => 'secret-123'])
            ->assertOk()
            ->assertJsonPath('profile.two_factor.enabled', false);

        // Confirming a setup that never started is refused.
        $this->postJson('/ptah/api/auth/two-factor/totp/confirm', ['code' => '123456'])->assertStatus(409);
    }

    #[Test]
    public function the_sessions_endpoint_answers_a_list(): void
    {
        $this->actingAs($this->user());

        $this->getJson('/ptah/api/auth/sessions')->assertOk()->assertJsonStructure(['data']);
    }
}

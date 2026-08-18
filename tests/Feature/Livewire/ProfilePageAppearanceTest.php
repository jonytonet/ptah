<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Livewire;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Models\UserPreference;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;

// ── Stub ──────────────────────────────────────────────────────────────────────

class AppearanceTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Feature coverage for the "Aparência" tab of /profile (Ptah\Livewire\Auth\ProfilePage):
 * loads defaults, persists each axis independently, rejects values outside the
 * whitelist without corrupting what is already stored, and survives a fresh mount.
 */
class ProfilePageAppearanceTest extends TestCase
{
    /**
     * Enable the auth module BEFORE providers boot so ptah-auth.php routes are
     * registered (mirrors AuthRateLimitTest).
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', AppearanceTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => AppearanceTestUser::class]);
    }

    private function actingAsUser(): AppearanceTestUser
    {
        $user = AppearanceTestUser::create([
            'name' => 'Ana',
            'email' => 'ana'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function carrega_os_defaults_quando_nao_ha_preferencia_salva(): void
    {
        $this->actingAsUser();

        Livewire::test(ProfilePage::class)
            ->assertSet('themeLight', AppearancePresets::DEFAULT_LIGHT)
            ->assertSet('themeDark', AppearancePresets::DEFAULT_DARK)
            ->assertSet('themeAccent', AppearancePresets::DEFAULT_ACCENT)
            ->assertSet('themeText', AppearancePresets::DEFAULT_TEXT);
    }

    #[Test]
    public function cada_eixo_e_persistido_de_forma_independente(): void
    {
        $user = $this->actingAsUser();

        Livewire::test(ProfilePage::class)
            ->call('setLight', 'papel')
            ->assertSet('themeLight', 'papel')
            ->call('setDark', 'carvao')
            ->assertSet('themeDark', 'carvao')
            ->call('setAccent', 'ciano')
            ->assertSet('themeAccent', 'ciano')
            ->call('setText', 'forte')
            ->assertSet('themeText', 'forte');

        $stored = UserPreference::get($user->id, 'theme');

        $this->assertSame([
            'mode' => null,
            'light' => 'papel',
            'dark' => 'carvao',
            'accent' => 'ciano',
            'text' => 'forte',
        ], $stored);
    }

    #[Test]
    public function valor_fora_da_whitelist_e_ignorado_sem_corromper_o_que_ja_estava_salvo(): void
    {
        $user = $this->actingAsUser();

        Livewire::test(ProfilePage::class)
            ->call('setAccent', 'rosa')
            ->assertSet('themeAccent', 'rosa')
            ->call('setAccent', 'javascript:alert(1)')
            ->assertSet('themeAccent', 'rosa')
            ->call('setLight', '../../etc/passwd')
            ->assertSet('themeLight', AppearancePresets::DEFAULT_LIGHT);

        $stored = UserPreference::get($user->id, 'theme');

        $this->assertSame('rosa', $stored['accent']);
        $this->assertSame(AppearancePresets::DEFAULT_LIGHT, $stored['light']);
    }

    #[Test]
    public function preferencia_salva_e_recarregada_em_um_novo_mount(): void
    {
        $user = $this->actingAsUser();

        UserPreference::set($user->id, 'theme', [
            'mode' => 'dark',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
        ], 'appearance');

        Livewire::test(ProfilePage::class)
            ->assertSet('themeLight', 'nevoa')
            ->assertSet('themeDark', 'meianoite')
            ->assertSet('themeAccent', 'teal')
            ->assertSet('themeText', 'suave');
    }

    #[Test]
    public function rota_de_persistencia_do_modo_rejeita_valor_fora_da_whitelist(): void
    {
        $this->actingAsUser();

        $this->postJson(route('ptah.appearance.theme-mode'), ['mode' => 'sepia'])
            ->assertStatus(422);

        $this->postJson(route('ptah.appearance.theme-mode'), ['mode' => 'dark'])
            ->assertStatus(204);
    }

    #[Test]
    public function rota_de_persistencia_do_modo_tambem_atualiza_o_cookie(): void
    {
        $this->actingAsUser();

        $this->postJson(route('ptah.appearance.theme-mode'), ['mode' => 'dark'])
            ->assertStatus(204);

        $queued = Cookie::queued(AppearancePresets::COOKIE);
        $this->assertNotNull($queued);
        $this->assertSame('dark', json_decode($queued->getValue(), true)['mode']);
    }

    // ── "Voltar ao original" ──────────────────────────────────────────────────

    #[Test]
    public function resetar_a_aparencia_restaura_os_4_eixos_mas_nao_mexe_no_modo(): void
    {
        $user = $this->actingAsUser();

        UserPreference::set($user->id, 'theme', [
            'mode' => 'dark',
            'light' => 'nevoa',
            'dark' => 'meianoite',
            'accent' => 'teal',
            'text' => 'suave',
        ], 'appearance');

        Livewire::test(ProfilePage::class)
            ->call('resetAppearance')
            ->assertSet('themeLight', AppearancePresets::DEFAULT_LIGHT)
            ->assertSet('themeDark', AppearancePresets::DEFAULT_DARK)
            ->assertSet('themeAccent', AppearancePresets::DEFAULT_ACCENT)
            ->assertSet('themeText', AppearancePresets::DEFAULT_TEXT);

        $stored = UserPreference::get($user->id, 'theme');

        $this->assertSame([
            'mode' => 'dark',
            'light' => AppearancePresets::DEFAULT_LIGHT,
            'dark' => AppearancePresets::DEFAULT_DARK,
            'accent' => AppearancePresets::DEFAULT_ACCENT,
            'text' => AppearancePresets::DEFAULT_TEXT,
        ], $stored);

        $queued = Cookie::queued(AppearancePresets::COOKIE);
        $this->assertNotNull($queued);
        $this->assertSame($stored, json_decode($queued->getValue(), true));
    }
}

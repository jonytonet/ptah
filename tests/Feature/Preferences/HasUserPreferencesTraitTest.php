<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Preferences;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\UserPreference;
use Ptah\Support\PreferenceOwners;
use Ptah\Tests\TestCase;
use Ptah\Traits\HasUserPreferences;

/**
 * An identity model whose class name is not `User` — the whole point.
 */
class PortalStaffUser extends Model
{
    use HasUserPreferences;

    protected $table = 'portal_staff_users';

    protected $fillable = ['name'];
}

/**
 * And one whose primary key is not `id` either.
 */
class BadgeHolder extends Model
{
    use HasUserPreferences;

    protected $table = 'badge_holders';

    protected $primaryKey = 'badge';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['badge'];
}

class SoftIdentity extends Model
{
    use HasUserPreferences, SoftDeletes;

    protected $table = 'soft_identities';

    protected $fillable = ['name'];
}

/**
 * The trait's relation inferred a column name from the model's CLASS name.
 *
 * `hasMany(UserPreference::class)` with no foreign key lets Eloquent build one
 * from the parent: `Str::snake(class_basename($this)).'_'.$this->getKeyName()`.
 * On a host whose identity model is `PortalStaffUser`, that is
 * `portal_staff_user_id` — a column `user_preferences` does not have:
 *
 *     SQLSTATE[42S22]: Unknown column 'user_preferences.portal_staff_user_id'
 *
 * It is the same shape as the foreign key that 1.34.6 fixed in the schema —
 * something inferred from a name instead of being stated — and it survived that
 * release because `UserPreference::user()` was corrected and this was not.
 *
 * ── Why no test caught it ────────────────────────────────────────────────
 *
 * Nothing in the suite used the trait at all. The package itself never calls
 * `->preferences`, and neither do hosts in practice: `setPreference()`,
 * `getPreference()`, `getPreferenceGroup()` and `removePreference()` all go
 * through `UserPreference`'s statics with an explicit `user_id`, and those work.
 * So the broken member is the one part of a public trait nobody exercised —
 * which is exactly the kind of thing that only a host calling it finds.
 *
 * This file therefore covers the trait as a whole, including the delete cleanup
 * added in 1.34.6, which had shipped with no test of its own.
 */
class HasUserPreferencesTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('portal_staff_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('badge_holders', function (Blueprint $table) {
            $table->string('badge')->primary();
            $table->timestamps();
        });

        Schema::create('soft_identities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    // ── More than one owner ────────────────────────────────────────────────

    #[Test]
    public function a_second_owner_of_the_table_is_said_out_loud(): void
    {
        // `user_preferences` nao distingue donos: o unique e (user_id, key), e
        // ids iguais em identidades diferentes disputam a MESMA linha — quem
        // grava por ultimo sobrescreve, sem erro — enquanto a limpeza no delete
        // apaga as duas. O host chega nisso trocando de identidade e deixando o
        // trait no `App\Models\User` do scaffold ao lado do model real.
        PreferenceOwners::flush();
        Log::spy();

        PreferenceOwners::register(PortalStaffUser::class);
        PreferenceOwners::register(BadgeHolder::class);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'mais de um model'))
            ->once();
    }

    #[Test]
    public function one_owner_alone_says_nothing(): void
    {
        PreferenceOwners::flush();
        Log::spy();

        PreferenceOwners::register(PortalStaffUser::class);

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function a_host_that_declared_multi_identity_is_not_nagged(): void
    {
        // `PTAH_PREFERENCES_FK=false` E a declaracao de que ha mais de uma
        // identidade. Avisar ai seria barulho dirigido exatamente a quem ja leu
        // a nota.
        config()->set('ptah.preferences.foreign_key', false);
        PreferenceOwners::flush();
        Log::spy();

        PreferenceOwners::register(PortalStaffUser::class);
        PreferenceOwners::register(BadgeHolder::class);

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function the_registry_does_not_live_in_the_trait(): void
    {
        // Uma estatica declarada em TRAIT e por classe que o usa: cada model
        // teria a sua, o contador leria sempre 1, e o aviso nunca dispararia.
        // A primeira versao disto fazia exatamente isso — sem efeito nenhum, e
        // sem teste que o revelasse. Este fixa o arranjo, nao so o efeito.
        $traitStatics = (new \ReflectionClass(HasUserPreferences::class))
            ->getProperties(\ReflectionProperty::IS_STATIC);

        $this->assertSame(
            [],
            array_map(static fn (\ReflectionProperty $p): string => $p->getName(), $traitStatics),
            'Estado compartilhado nao pode morar numa estatica deste trait.'
        );
    }

    #[Test]
    public function two_owners_really_do_collide_on_the_same_row(): void
    {
        // O motivo de o aviso existir, demonstrado: mesmos ids, mesma chave,
        // uma linha so. Se algum dia a tabela ganhar `user_type`, este teste
        // falha e manda reescrever a nota.
        $staff = PortalStaffUser::create(['name' => 'Interno']);
        $other = PortalStaffUser::create(['name' => 'Outro']);

        $staff->setPreference('theme', 'dark');

        // Uma segunda identidade com o MESMO id gravaria por cima: aqui,
        // simulado por id igual vindo de outra tabela.
        UserPreference::set($staff->getKey(), 'theme', 'light');

        $this->assertSame(
            1,
            UserPreference::where('user_id', $staff->getKey())->where('key', 'theme')->count(),
            'O unique (user_id, key) admite uma linha so — e por isso que dois donos colidem.'
        );
        $this->assertSame('light', $staff->fresh()->getPreference('theme'));
        $this->assertSame(0, UserPreference::where('user_id', $other->getKey())->count());
    }

    #[Test]
    public function the_relation_works_on_an_identity_that_is_not_called_user(): void
    {
        $staff = PortalStaffUser::create(['name' => 'Operador']);

        $staff->setPreference('theme', ['mode' => 'dark'], 'appearance');

        // A chamada que estourava com "Unknown column
        // user_preferences.portal_staff_user_id".
        $this->assertSame(1, $staff->preferences()->count());
        $this->assertSame('theme', $staff->preferences()->first()->key);
    }

    #[Test]
    public function the_relation_works_when_the_primary_key_is_not_id_either(): void
    {
        // `getForeignKey()` concatena a CHAVE tambem, entao aqui a coluna
        // inferida seria `badge_holder_badge`. A local key padrao do hasMany ja
        // e `getKeyName()`, entao nomear so a coluna estrangeira basta.
        $holder = BadgeHolder::create(['badge' => 'A-77']);

        $holder->setPreference('density', 'compact');

        $this->assertSame(1, $holder->preferences()->count());
        $this->assertSame('A-77', $holder->preferences()->first()->user_id);
    }

    #[Test]
    public function the_relation_only_returns_that_users_rows(): void
    {
        // Nomear a coluna nao pode ter custado o filtro: sem a local key certa,
        // a relacao traria tudo.
        $one = PortalStaffUser::create(['name' => 'Um']);
        $two = PortalStaffUser::create(['name' => 'Dois']);

        $one->setPreference('theme', 'dark');
        $two->setPreference('theme', 'light');

        $this->assertSame(1, $one->preferences()->count());
        $this->assertSame('dark', $one->preferences()->first()->value);
    }

    // ── The paths that already worked, pinned ──────────────────────────────

    #[Test]
    public function the_static_backed_helpers_keep_working(): void
    {
        $staff = PortalStaffUser::create(['name' => 'Operador']);

        $staff->setPreference('theme', ['mode' => 'dark'], 'appearance');
        $staff->setPreference('accent', 'violet', 'appearance');

        $this->assertSame(['mode' => 'dark'], $staff->getPreference('theme'));
        $this->assertSame('padrao', $staff->getPreference('missing', 'padrao'));

        $group = $staff->getPreferenceGroup('appearance');
        $this->assertArrayHasKey('theme', $group);
        $this->assertArrayHasKey('accent', $group);

        $this->assertTrue($staff->removePreference('accent'));
        $this->assertNull($staff->getPreference('accent'));
    }

    // ── The cleanup added in 1.34.6 ────────────────────────────────────────

    #[Test]
    public function deleting_the_user_clears_the_preferences(): void
    {
        // Era o ON DELETE CASCADE da FK, que um host sem constraint nao tem
        // mais. Foi para o model na 1.34.6 e nao tinha teste proprio.
        $staff = PortalStaffUser::create(['name' => 'Operador']);
        $staff->setPreference('theme', 'dark');

        $staff->delete();

        $this->assertSame(0, UserPreference::where('user_id', $staff->getKey())->count());
    }

    #[Test]
    public function a_soft_delete_keeps_them(): void
    {
        // Soft delete nao e exclusao: a linha volta, e o tema escolhido pela
        // pessoa deveria voltar com ela.
        $identity = SoftIdentity::create(['name' => 'Temporario']);
        $identity->setPreference('theme', 'dark');

        $identity->delete();

        $this->assertSoftDeleted('soft_identities', ['id' => $identity->getKey()]);
        $this->assertSame(1, UserPreference::where('user_id', $identity->getKey())->count());
    }

    #[Test]
    public function a_force_delete_clears_them(): void
    {
        $identity = SoftIdentity::create(['name' => 'Temporario']);
        $identity->setPreference('theme', 'dark');

        $identity->forceDelete();

        $this->assertSame(0, UserPreference::where('user_id', $identity->getKey())->count());
    }

    #[Test]
    public function deleting_one_user_does_not_touch_another(): void
    {
        $one = PortalStaffUser::create(['name' => 'Um']);
        $two = PortalStaffUser::create(['name' => 'Dois']);

        $one->setPreference('theme', 'dark');
        $two->setPreference('theme', 'light');

        $one->delete();

        $this->assertSame(1, UserPreference::where('user_id', $two->getKey())->count());
    }
}

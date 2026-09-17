<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Migrations;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\UserIdentity;
use Ptah\Tests\TestCase;
use RuntimeException;

class StaffIdentity extends Model
{
    protected $table = 'staff_users';

    protected $fillable = ['name'];
}

class UuidIdentity extends Model
{
    use HasUuids;

    protected $table = 'uuid_members';

    protected $fillable = ['name'];
}

class UlidIdentity extends Model
{
    use HasUlids;

    protected $table = 'ulid_members';
}

class CodeIdentity extends Model
{
    protected $table = 'code_users';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';
}

/**
 * `user_preferences` was the one table in the package that guessed where the
 * host keeps its users.
 *
 * `foreignId('user_id')->constrained()` — with no table named — makes Laravel
 * infer `users` from the column name. Every other `user_id` column in the
 * package carries no constraint at all, and every internal foreign key names
 * its table; the bare `constrained()` appeared exactly once in the whole
 * package. And it contradicted a promise the config file makes out loud beside
 * `user_model`: "host application's User model (no hard-coded FK)".
 *
 * The consequence on a host with its own identity model was small in the UI and
 * large in meaning: changing theme, accent, density or font size failed with a
 * foreign key violation against a `users` table the application does not use.
 * The PHP side was already agnostic — `UserPreference::user()` resolves the
 * model from config — so only the schema had been left behind.
 *
 * ── On what these tests can and cannot show ──────────────────────────────
 *
 * The package's SQLite connection does not set `foreign_key_constraints`, and
 * SQLite's own default is OFF — so an insert violating a foreign key succeeds
 * here unless the pragma is turned on. Most of these tests therefore assert the
 * SCHEMA, which is where the defect lives: which table `user_id` points at, and
 * what type it is. One test turns the pragma on and reproduces the reported
 * error itself, so the symptom is on record and not only its cause.
 */
class UserPreferencesIdentityTest extends TestCase
{
    private const CREATE = __DIR__.'/../../../src/Migrations/2024_01_01_000000_create_user_preferences_table.php';

    /**
     * Turn foreign key enforcement ON for this class.
     *
     * The package's test connection leaves `foreign_key_constraints` unset, and
     * SQLite's own default is OFF — so a violating insert simply succeeds, and
     * a test asserting "the row is rejected" passes for the wrong reason. It
     * has to be set on the CONNECTION: `PRAGMA foreign_keys` is a no-op inside
     * a transaction, and `RefreshDatabase` wraps every test in one. The first
     * version of this file used the pragma and the enforcement test failed,
     * which is what exposed that its neighbour had been passing vacuously.
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $connection = $app['config']->get('database.connections.testing');
        $connection['foreign_key_constraints'] = true;
        $app['config']->set('database.connections.testing', $connection);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('uuid_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
        });

        Schema::create('ulid_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
        });

        Schema::create('code_users', function (Blueprint $table) {
            $table->string('codigo')->primary();
        });
    }

    /**
     * Rebuild `user_preferences` with the package's own migration, under
     * whatever identity the config currently names.
     */
    private function rebuild(): void
    {
        Schema::dropIfExists('user_preferences');

        (require self::CREATE)->up();
    }

    private function useIdentity(string $model): void
    {
        config()->set('ptah.permissions.user_model', $model);
    }

    /**
     * @return array{table: string|null, column: string|null}
     */
    private function foreignKey(): array
    {
        foreach (Schema::getForeignKeys('user_preferences') as $key) {
            if (in_array('user_id', $key['columns'] ?? [], true)) {
                return [
                    'table' => $key['foreign_table'] ?? null,
                    'column' => $key['foreign_columns'][0] ?? null,
                ];
            }
        }

        return ['table' => null, 'column' => null];
    }

    private function columnType(string $column): string
    {
        foreach (Schema::getColumns('user_preferences') as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return strtolower((string) ($definition['type'] ?? $definition['type_name'] ?? ''));
            }
        }

        $this->fail("Coluna {$column} nao existe em user_preferences.");
    }

    // ── The defect ─────────────────────────────────────────────────────────

    #[Test]
    public function the_constraint_points_at_the_identity_the_host_configured(): void
    {
        $this->useIdentity(StaffIdentity::class);
        $this->rebuild();

        $this->assertSame(
            'staff_users',
            $this->foreignKey()['table'],
            'A FK foi inferida a partir do nome da coluna em vez de ler a identidade configurada.'
        );
    }

    #[Test]
    public function the_reported_insert_succeeds_under_a_configured_identity(): void
    {
        // O sintoma do relato, reproduzido: com a FK imposta, gravar a
        // preferencia de um usuario que existe na identidade do host falhava
        // com "Cannot add or update a child row" contra `users`.
        $this->useIdentity(StaffIdentity::class);
        $this->rebuild();

        $staff = StaffIdentity::create(['name' => 'Comprador']);

        DB::table('user_preferences')->insert([
            'user_id' => $staff->getKey(),
            'key' => 'theme',
            'value' => json_encode(['mode' => 'dark']),
            'group' => 'appearance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('user_preferences', ['user_id' => $staff->getKey(), 'key' => 'theme']);
    }

    #[Test]
    public function a_row_for_a_user_that_does_not_exist_is_still_rejected(): void
    {
        // A contrapartida: a constraint tem de continuar sendo uma constraint.
        // Sem isto, "apontar para a tabela certa" poderia ser satisfeito
        // simplesmente nao criando FK nenhuma.
        $this->useIdentity(StaffIdentity::class);
        $this->rebuild();

        $this->expectException(QueryException::class);

        DB::table('user_preferences')->insert([
            'user_id' => 9999,
            'key' => 'theme',
            'value' => json_encode(['mode' => 'dark']),
            'group' => 'appearance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Key types ──────────────────────────────────────────────────────────

    #[Test]
    public function a_uuid_identity_gets_a_char_column_and_not_a_bigint(): void
    {
        $this->useIdentity(UuidIdentity::class);
        $this->rebuild();

        $this->assertStringNotContainsString('int', $this->columnType('user_id'));
        $this->assertSame('uuid_members', $this->foreignKey()['table']);
    }

    #[Test]
    public function a_ulid_identity_gets_a_char_column_too(): void
    {
        $this->useIdentity(UlidIdentity::class);
        $this->rebuild();

        $this->assertStringNotContainsString('int', $this->columnType('user_id'));
    }

    #[Test]
    public function a_string_key_without_either_trait_is_not_treated_as_an_integer(): void
    {
        // O arm `default =>` de uma leitura so por traits mandaria bigint aqui,
        // e o tipo nem sequer casaria com a chave.
        $this->useIdentity(CodeIdentity::class);
        $this->rebuild();

        $this->assertStringNotContainsString('int', $this->columnType('user_id'));
        $this->assertSame('codigo', $this->foreignKey()['column']);
    }

    // ── No regression for the ordinary host ────────────────────────────────

    #[Test]
    public function the_default_host_still_gets_exactly_what_it_had(): void
    {
        // A instalacao prevista: `users`, bigint, cascade. Se isto mudar, a
        // correcao virou uma migracao forcada para quem estava bem.
        config()->set('ptah.permissions.user_model', null);
        config()->set('auth.providers.users.model', null);
        $this->rebuild();

        $this->assertSame('users', $this->foreignKey()['table']);
        $this->assertStringContainsString('int', $this->columnType('user_id'));
    }

    // ── Hosts no single constraint can serve ───────────────────────────────

    #[Test]
    public function an_identity_table_that_is_not_there_yet_gets_an_index_instead(): void
    {
        $this->useIdentity('App\\Models\\ThisDoesNotExist');
        config()->set('auth.providers.users.model', 'App\\Models\\ThisDoesNotExist');

        Schema::drop('users');
        $this->rebuild();

        $this->assertNull($this->foreignKey()['table'], 'Sem tabela de identidade nao ha FK possivel.');
        $this->assertTrue(Schema::hasColumn('user_preferences', 'user_id'));
    }

    #[Test]
    public function the_host_can_turn_the_constraint_off_entirely(): void
    {
        // Para host com MAIS DE UMA identidade gravando preferencia: nenhuma
        // tabela unica e destino valido, e uma FK trancaria a coluna na
        // primeira que migrasse.
        config()->set('ptah.preferences.foreign_key', false);
        $this->useIdentity(StaffIdentity::class);
        $this->rebuild();

        $this->assertNull($this->foreignKey()['table']);
    }

    #[Test]
    public function demanding_the_constraint_fails_loudly_when_it_cannot_be_made(): void
    {
        config()->set('ptah.preferences.foreign_key', true);
        $this->useIdentity('App\\Models\\ThisDoesNotExist');
        config()->set('auth.providers.users.model', 'App\\Models\\ThisDoesNotExist');

        Schema::drop('users');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/exige a chave estrangeira/');

        $this->rebuild();
    }

    // ── The configured key field ───────────────────────────────────────────

    #[Test]
    public function an_explicitly_named_key_field_wins_over_the_models_own(): void
    {
        // `ResolvesUser` ja le `ptah.permissions.user_id_field`, e e ele que
        // decide o que vai gravado em `user_id` — a constraint tem de apontar
        // para a mesma coluna, senao aponta para uma que ninguem escreve.
        config()->set('ptah.permissions.user_id_field', 'codigo');
        $this->useIdentity(CodeIdentity::class);

        $this->assertSame('codigo', UserIdentity::resolve()->keyName);
    }

    #[Test]
    public function the_default_key_field_does_not_override_a_models_own_key(): void
    {
        // `user_id_field` vale 'id' para todo mundo por padrao. Trata-lo como
        // explicito apontaria a constraint para uma coluna `id` que um model de
        // chave propria nao tem.
        config()->set('ptah.permissions.user_id_field', 'id');
        $this->useIdentity(CodeIdentity::class);

        $this->assertSame('codigo', UserIdentity::resolve()->keyName);
    }

    // ── The upgrade path ───────────────────────────────────────────────────

    #[Test]
    public function an_existing_install_has_its_constraint_moved(): void
    {
        // A correcao na migration de criacao so alcanca banco novo. Toda
        // instalacao feita antes carrega a FK para `users`.
        config()->set('ptah.permissions.user_model', null);
        $this->rebuild();
        $this->assertSame('users', $this->foreignKey()['table']);

        $this->useIdentity(StaffIdentity::class);

        $this->artisan('ptah:preferences:realign', ['--force' => true])->assertExitCode(0);

        $this->assertSame('staff_users', $this->foreignKey()['table']);
    }

    #[Test]
    public function the_upgrade_refuses_rather_than_deleting_orphans(): void
    {
        // Uma migration de pacote que apaga linha do banco de uma aplicacao
        // consumidora e uma surpresa que ninguem quer descobrir em producao.
        //
        // A orfa e criada numa tabela SEM constraint: com a FK imposta nesta
        // classe nao ha como inserir uma linha orfa por cima de uma FK valida,
        // e o cenario real e justamente o de um banco cuja constraint nao
        // cobria a identidade que passou a valer.
        config()->set('ptah.preferences.foreign_key', false);
        config()->set('ptah.permissions.user_model', null);
        $this->rebuild();
        config()->set('ptah.preferences.foreign_key', 'auto');

        DB::table('user_preferences')->insert([
            'user_id' => 4242,
            'key' => 'theme',
            'value' => json_encode(['mode' => 'dark']),
            'group' => 'appearance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->useIdentity(StaffIdentity::class);

        $this->artisan('ptah:preferences:realign', ['--force' => true])
            ->expectsOutputToContain('não apaga dado')
            ->assertExitCode(1);

        // A FK nao foi mexida: o comando parou antes.
        $this->assertNull($this->foreignKey()['table']);

        // E a linha continua la: o ponto do teste nao e o erro, e o dado
        // intacto depois dele.
        $this->assertDatabaseHas('user_preferences', ['user_id' => 4242]);
    }

    #[Test]
    public function the_upgrade_does_nothing_when_the_constraint_is_already_right(): void
    {
        $this->useIdentity(StaffIdentity::class);
        $this->rebuild();

        $this->artisan('ptah:preferences:realign', ['--force' => true])
            ->expectsOutputToContain('Nada a fazer')
            ->assertExitCode(0);

        $this->assertSame('staff_users', $this->foreignKey()['table']);
    }

    #[Test]
    public function the_dry_run_changes_nothing(): void
    {
        // O esquema de producao so muda depois de alguem olhar o relatorio.
        config()->set('ptah.permissions.user_model', null);
        $this->rebuild();
        $this->useIdentity(StaffIdentity::class);

        $this->artisan('ptah:preferences:realign', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('users', $this->foreignKey()['table'], 'O dry-run nao pode alterar nada.');
    }

    #[Test]
    public function the_realign_is_a_command_and_never_an_auto_discovered_migration(): void
    {
        // O guard SchemaIsFrozenTest pegou a primeira versao disto: uma
        // migration do pacote e AUTO-DESCOBERTA e roda no proximo `migrate` que
        // o consumidor executar por motivo dele. Pior ainda aqui, porque esta
        // operacao pode legitimamente RECUSAR — e como migration, a recusa
        // derrubaria o deploy de alguem por uma mudanca que ninguem pediu.
        $files = glob(__DIR__.'/../../../src/Migrations/*realign*') ?: [];

        $this->assertSame([], $files, 'O realinhamento nao pode voltar a ser migration.');
    }
}

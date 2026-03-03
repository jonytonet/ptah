<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;
use Ptah\Traits\HasAuditFields;

// ─── Stub models ─────────────────────────────────────────────────────────────
//
// Defined at namespace level in the same file for self-contained tests.
// Three variants exercise different combinations of fillable and SoftDeletes.

/**
 * Full audit model: all three columns in $fillable + SoftDeletes.
 * This is the "happy path" model used by most tests.
 */
class AuditableStub extends Model
{
    use HasAuditFields;
    use SoftDeletes;

    protected $table    = 'has_audit_stubs';
    protected $fillable = ['name', 'created_by', 'updated_by', 'deleted_by'];
    protected $casts    = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];
}

/**
 * Audit columns deliberately absent from $fillable.
 * The trait must silently skip — no exception, no fill.
 */
class AuditableNoFillableStub extends Model
{
    use HasAuditFields;

    protected $table    = 'has_audit_stubs';
    protected $fillable = ['name'];
}

/**
 * No SoftDeletes: only created_by/updated_by are supported.
 * The trait must not attempt to stamp deleted_by or run a raw UPDATE.
 */
class AuditableHardDeleteStub extends Model
{
    use HasAuditFields;

    protected $table    = 'no_soft_delete_stubs';
    protected $fillable = ['name', 'created_by', 'updated_by'];
    protected $casts    = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}

// ─── Test class ──────────────────────────────────────────────────────────────

/**
 * Unit tests for Ptah\Traits\HasAuditFields.
 *
 * Coverage:
 *  creating event:
 *    - fills created_by and updated_by when user is authenticated
 *    - does nothing when there is no authenticated user
 *    - === null guard: does not overwrite created_by = 0 (falsy but not null)
 *    - does not overwrite a pre-set created_by value
 *    - silently skips when audit columns are absent from $fillable
 *
 *  updating event:
 *    - always overwrites updated_by on save
 *    - preserves updated_by when there is no authenticated user
 *    - created_by remains the original creator after an update
 *
 *  deleted event (deleted_by after soft-delete):
 *    - stamps deleted_by via raw SQL after the soft-delete commits
 *    - does nothing when there is no authenticated user
 *    - does nothing on a model that does not use SoftDeletes
 *
 *  Relationships:
 *    - createdBy(), updatedBy(), deletedBy() resolve to the correct User record
 */
class HasAuditFieldsTest extends TestCase
{
    // ── Environment overrides ─────────────────────────────────────────────────

    /**
     * Exclude PtahServiceProvider intentionally.
     *
     * HasAuditFields is a standalone trait — it has no service provider
     * dependency. Including PtahServiceProvider would force ALL Ptah
     * migrations (including auth/permissions migrations) to be registered,
     * causing ordering issues and unnecessary overhead in a unit test.
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
        ];
    }

    /**
     * Minimal environment — only what HasAuditFields needs:
     *  - SQLite :memory: database
     *  - auth guard pointing to App\Models\User (Testbench default)
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('session.driver', 'array');
    }

    /**
     * Load only the stub tables (plus the minimal users table defined
     * in the same migration file). No Ptah migrations needed here.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Creates a real user in the DB and logs them in.
     * Uses the model resolved from config so the test respects any override.
     */
    private function createAndLoginUser(string $email = 'user@test.com'): mixed
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userClass */
        $userClass = config('auth.providers.users.model');

        $user = $userClass::forceCreate([
            'name'     => 'Tester',
            'email'    => $email,
            'password' => bcrypt('secret'),
        ]);

        Auth::loginUsingId($user->id);

        return $user;
    }

    // ── creating event ────────────────────────────────────────────────────────

    #[Test]
    public function preenche_created_by_e_updated_by_ao_criar(): void
    {
        $user   = $this->createAndLoginUser();
        $record = AuditableStub::create(['name' => 'Registro A']);
        $fresh  = $record->fresh();

        $this->assertSame($user->id, $fresh->created_by);
        $this->assertSame($user->id, $fresh->updated_by);
    }

    #[Test]
    public function nao_preenche_audit_fields_sem_usuario_autenticado(): void
    {
        // No Auth::login call — guest session
        $record = AuditableStub::create(['name' => 'Anônimo']);
        $fresh  = $record->fresh();

        $this->assertNull($fresh->created_by);
        $this->assertNull($fresh->updated_by);
    }

    /**
     * Critical guard: the trait uses === null (not empty()) so user ID 0
     * is falsy but must NOT be treated as "unset" and overwritten.
     */
    #[Test]
    public function nao_sobrescreve_created_by_zero_pois_zero_nao_e_null(): void
    {
        $this->createAndLoginUser();

        $record = AuditableStub::create(['name' => 'Zero ID', 'created_by' => 0]);

        $this->assertSame(0, $record->fresh()->created_by);
    }

    #[Test]
    public function nao_sobrescreve_created_by_ja_preenchido(): void
    {
        $this->createAndLoginUser();

        $record = AuditableStub::create(['name' => 'Pre-set', 'created_by' => 99]);

        $this->assertSame(99, $record->fresh()->created_by);
    }

    #[Test]
    public function silenciosamente_ignora_colunas_ausentes_do_fillable(): void
    {
        $this->createAndLoginUser();

        // Must not throw even though created_by/updated_by are not in $fillable
        $record = AuditableNoFillableStub::create(['name' => 'Sem Fillable']);

        $this->assertNotNull($record->id);

        // The DB column is null (trait could not fill it via the Model)
        $this->assertNull($record->fresh()->created_by);
    }

    // ── updating event ────────────────────────────────────────────────────────

    #[Test]
    public function atualiza_updated_by_ao_salvar(): void
    {
        $userA  = $this->createAndLoginUser('a@test.com');
        $record = AuditableStub::create(['name' => 'Original']);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userClass */
        $userClass = config('auth.providers.users.model');
        $userB = $userClass::forceCreate([
            'name'     => 'User B',
            'email'    => 'b@test.com',
            'password' => bcrypt('x'),
        ]);
        Auth::loginUsingId($userB->id);

        $record->update(['name' => 'Atualizado']);
        $fresh = $record->fresh();

        $this->assertSame($userB->id, $fresh->updated_by); // last editor
        $this->assertSame($userA->id, $fresh->created_by); // original creator preserved
    }

    #[Test]
    public function nao_altera_updated_by_sem_usuario_autenticado(): void
    {
        $this->createAndLoginUser();
        $record            = AuditableStub::create(['name' => 'Registro']);
        $originalUpdatedBy = $record->fresh()->updated_by;

        Auth::logout();

        $record->update(['name' => 'Mudado sem Auth']);

        $this->assertSame($originalUpdatedBy, $record->fresh()->updated_by);
    }

    // ── deleted event (deleted_by after soft-delete) ──────────────────────────

    #[Test]
    public function preenche_deleted_by_apos_soft_delete(): void
    {
        $user   = $this->createAndLoginUser();
        $record = AuditableStub::create(['name' => 'Para Deletar']);

        $record->delete();

        $this->assertSoftDeleted('has_audit_stubs', ['id' => $record->id]);

        $fresh = AuditableStub::withTrashed()->find($record->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame($user->id, $fresh->deleted_by);
    }

    #[Test]
    public function nao_preenche_deleted_by_sem_usuario_autenticado(): void
    {
        $this->createAndLoginUser();
        $record = AuditableStub::create(['name' => 'To Delete']);

        Auth::logout();
        $record->delete();

        $fresh = AuditableStub::withTrashed()->find($record->id);
        $this->assertNull($fresh->deleted_by);
    }

    /**
     * When the model does not use SoftDeletes, the trait's `deleted` handler
     * must detect the missing getDeletedAtColumn() and return early without
     * throwing or touching the database.
     */
    #[Test]
    public function nao_preenche_deleted_by_em_model_sem_soft_delete(): void
    {
        $this->createAndLoginUser();
        $record = AuditableHardDeleteStub::create(['name' => 'Hard Delete']);

        // delete() must not throw
        $record->delete();

        $this->assertDatabaseMissing('no_soft_delete_stubs', ['id' => $record->id]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    #[Test]
    public function relacionamento_createdBy_resolve_usuario_correto(): void
    {
        $user   = $this->createAndLoginUser();
        $record = AuditableStub::create(['name' => 'CreatedBy Rel']);

        $this->assertSame($user->id, $record->fresh()->createdBy->id);
    }

    #[Test]
    public function relacionamento_updatedBy_resolve_usuario_correto(): void
    {
        $user   = $this->createAndLoginUser();
        $record = AuditableStub::create(['name' => 'UpdatedBy Rel']);

        $this->assertSame($user->id, $record->fresh()->updatedBy->id);
    }

    #[Test]
    public function relacionamento_deletedBy_resolve_usuario_correto(): void
    {
        $user   = $this->createAndLoginUser();
        $record = AuditableStub::create(['name' => 'DeletedBy Rel']);

        $record->delete();

        $fresh = AuditableStub::withTrashed()->find($record->id);
        $this->assertSame($user->id, $fresh->deletedBy->id);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Jobs\GenerateCrudExportJob;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\Export;
use Ptah\Tests\TestCase;

// ── Stubs ─────────────────────────────────────────────────────────────────────

/** Plain stub on the shared `items` table (see tests/migrations). */
class QueueExportStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/** Stub WITH a company_id column, for the multi-tenant scoping test. */
class QueueExportScopeStub extends Model
{
    protected $table = 'export_scope_stubs';

    protected $fillable = ['name', 'company_id'];
}

class QueueExportUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers HasCrudExport::queueExport() — the Fase 3 "grande volume" entry
 * point: opt-in gate, dispatch to GenerateCrudExportJob when a real queue is
 * configured, degrade to the synchronous export otherwise, and that the ids
 * captured in the payload honour the same company scope as the listing.
 */
class HasCrudExportQueueTest extends TestCase
{
    private function actingAsUser(): QueueExportUser
    {
        $user = QueueExportUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makeConfig(string $model, array $exportConfig = []): void
    {
        CrudConfig::create([
            'model' => $model,
            'route' => '',
            'config' => [
                'crud' => $model,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'exportConfig' => array_merge([
                    'enabled' => true,
                    'asyncExport' => ['enabled' => true, 'excel' => true, 'pdf' => false],
                ], $exportConfig),
                'permissions' => [],
            ],
        ]);
    }

    #[Test]
    public function it_is_a_no_op_when_export_is_not_enabled_at_all(): void
    {
        $this->actingAsUser();
        $this->makeConfig(QueueExportStub::class, ['enabled' => false]);
        QueueExportStub::create(['name' => 'Alpha']);

        Livewire::test(BaseCrud::class, ['model' => QueueExportStub::class])
            ->call('queueExport', 'excel');

        $this->assertSame(0, Export::query()->count());
    }

    #[Test]
    public function it_is_a_no_op_when_async_export_is_not_opted_in(): void
    {
        $this->actingAsUser();
        // enabled, but asyncExport missing/off (default) — the sync export
        // button stays, but queueExport() must not do anything.
        $this->makeConfig(QueueExportStub::class, ['asyncExport' => ['enabled' => false]]);
        QueueExportStub::create(['name' => 'Alpha']);

        Livewire::test(BaseCrud::class, ['model' => QueueExportStub::class])
            ->call('queueExport', 'excel');

        $this->assertSame(0, Export::query()->count());
    }

    #[Test]
    public function it_dispatches_the_job_and_creates_a_queued_row_when_a_real_queue_is_configured(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $user = $this->actingAsUser();
        $this->makeConfig(QueueExportStub::class);
        $a = QueueExportStub::create(['name' => 'Alpha']);
        $b = QueueExportStub::create(['name' => 'Bravo']);

        Livewire::test(BaseCrud::class, ['model' => QueueExportStub::class])
            ->call('queueExport', 'excel')
            ->assertDispatched('ptah-toast');

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame('queued', $export->status);
        $this->assertSame($user->id, $export->user_id);
        $this->assertSame('excel', $export->format);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $export->payload['ids']);

        Queue::assertPushed(
            GenerateCrudExportJob::class,
            fn (GenerateCrudExportJob $job) => $job->exportId === $export->id
        );
    }

    #[Test]
    public function it_degrades_to_the_synchronous_export_when_the_queue_connection_is_sync(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync by default — no override needed.
        Excel::fake();

        $this->actingAsUser();
        $this->makeConfig(QueueExportStub::class);
        QueueExportStub::create(['name' => 'Alpha']);

        Livewire::test(BaseCrud::class, ['model' => QueueExportStub::class])
            ->call('queueExport', 'excel')
            ->assertDispatched('ptah:export-download')
            ->assertDispatched('ptah-toast');

        $this->assertSame(0, Export::query()->count());
    }

    #[Test]
    public function it_degrades_when_the_ptah_exports_table_is_missing(): void
    {
        config(['queue.default' => 'database']);
        Excel::fake();
        Schema::dropIfExists('ptah_exports');

        $this->actingAsUser();
        $this->makeConfig(QueueExportStub::class);
        QueueExportStub::create(['name' => 'Alpha']);

        Livewire::test(BaseCrud::class, ['model' => QueueExportStub::class])
            ->call('queueExport', 'excel')
            ->assertDispatched('ptah:export-download')
            ->assertDispatched('ptah-toast');
    }

    #[Test]
    public function the_payload_ids_are_scoped_to_the_active_company(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $this->actingAsUser();
        $this->makeConfig(QueueExportScopeStub::class);

        $ownRecord = QueueExportScopeStub::create(['name' => 'Mine', 'company_id' => 1]);
        QueueExportScopeStub::create(['name' => 'Theirs', 'company_id' => 2]);

        Livewire::test(BaseCrud::class, ['model' => QueueExportScopeStub::class, 'companyFilter' => 1])
            ->call('queueExport', 'excel');

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame([$ownRecord->id], $export->payload['ids']);
    }

    // ── CRITICAL — cross-model leak (payload must record the RESOLVED model,
    // never the raw $model) ──────────────────────────────────────────────────
    //
    // resolveEloquentModel() resolves crudConfig['crud'] ?? $this->model — a
    // CRUD can legitimately be keyed by a routing/config alias that differs
    // from the actual Eloquent class (config['crud']). Storing the raw
    // $this->model in the payload/Export row would (a) be wrong even in this
    // benign case (an alias is not an instantiable FQCN) and (b) — per the
    // security review — is exactly the gap a forged request could exploit:
    // Livewire's own lifecycle runs boot() (which reloads crudConfig from
    // $this->model) BEFORE client updates are applied, so a request that
    // updates $model would leave crudConfig pointing at the ORIGINAL model
    // while $this->model itself now names a different one. Because
    // resolveEloquentModel() prefers crudConfig['crud'], ids still come from
    // the original/authorised model — but the OLD code stored the forged
    // $this->model alongside them, so GenerateCrudExportJob would have queried
    // a DIFFERENT model with those ids. Recording get_class(resolveEloquentModel())
    // instead closes this regardless of how model ends up diverging.

    #[Test]
    public function the_payload_records_the_resolved_model_class_never_the_raw_model_key(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $this->actingAsUser();

        // "QueueExportAlias" is the crud_configs lookup key; the REAL Eloquent
        // class only lives in config['crud'] — mirrors resolveEloquentModel()'s
        // own precedence (crudConfig['crud'] ?? $this->model).
        CrudConfig::create([
            'model' => 'QueueExportAlias',
            'route' => '',
            'config' => [
                'crud' => QueueExportStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'exportConfig' => ['enabled' => true, 'asyncExport' => ['enabled' => true, 'excel' => true]],
                'permissions' => [],
            ],
        ]);

        QueueExportStub::create(['name' => 'Alpha']);

        Livewire::test(BaseCrud::class, ['model' => 'QueueExportAlias'])
            ->call('queueExport', 'excel');

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame(QueueExportStub::class, $export->model);
        $this->assertSame(QueueExportStub::class, $export->payload['model']);
        $this->assertNotSame('QueueExportAlias', $export->model);
    }

    #[Test]
    public function the_client_cannot_mutate_model_directly(): void
    {
        $this->actingAsUser();
        $this->makeConfig(QueueExportStub::class);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, ['model' => QueueExportStub::class])
            ->set('model', 'App\\Models\\SomethingElseEntirely');
    }

    #[Test]
    public function the_client_cannot_mutate_company_filter_directly(): void
    {
        $this->actingAsUser();
        $this->makeConfig(QueueExportScopeStub::class);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, ['model' => QueueExportScopeStub::class, 'companyFilter' => 1])
            ->set('companyFilter', 2);
    }
}

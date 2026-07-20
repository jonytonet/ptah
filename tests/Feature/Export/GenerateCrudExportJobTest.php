<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Jobs\GenerateCrudExportJob;
use Ptah\Models\CrudConfig;
use Ptah\Models\Export;
use Ptah\Tests\TestCase;

/** Plain stub on the shared `items` table (see tests/migrations). */
class JobExportStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/** Stub deliberately WITHOUT a crud_configs row — the allowlist gate test. */
class JobExportUnlistedStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Covers GenerateCrudExportJob — generates the file for an already-queued
 * Export row FROM THE IDS IT ALREADY HAS (never rebuilds the listing query),
 * re-checks the allowlist/permission gate (defence in depth — the same gate
 * ExportController::download() enforces) BEFORE touching the query or disk,
 * and writes the file/updates status/rows/expires_at.
 */
class GenerateCrudExportJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Allowlisted, no permissionIdentifier — the permission module is
        // active in the test environment (see TestCase), but with no
        // identifier configured the CRUD opts out of that extra check.
        CrudConfig::create([
            'model' => JobExportStub::class,
            'route' => '',
            'config' => [
                'crud' => JobExportStub::class,
                'cols' => [],
                'permissions' => [],
            ],
        ]);
    }

    private function makeExport(array $overrides = []): Export
    {
        return Export::create(array_merge([
            'user_id' => 1,
            'company_id' => null,
            'model' => JobExportStub::class,
            'route' => 'items',
            'format' => 'excel',
            'status' => 'queued',
            'payload' => [
                'version' => 1,
                'userId' => 1,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [],
                'columns' => [['field' => 'name', 'label' => 'Name', 'type' => 'text']],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ], $overrides));
    }

    #[Test]
    public function it_generates_an_excel_file_and_marks_the_export_done(): void
    {
        Storage::fake('local');

        $a = JobExportStub::create(['name' => 'Alpha']);
        $b = JobExportStub::create(['name' => 'Bravo']);

        $export = $this->makeExport([
            'payload' => [
                'version' => 1,
                'userId' => 1,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id, $b->id],
                'columns' => [['field' => 'name', 'label' => 'Name', 'type' => 'text']],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('done', $export->status);
        $this->assertSame(2, $export->rows);
        $this->assertSame('local', $export->file_disk);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->expires_at);
        Storage::disk('local')->assertExists($export->file_path);
    }

    #[Test]
    public function it_generates_a_pdf_file_and_marks_the_export_done(): void
    {
        Storage::fake('local');

        $a = JobExportStub::create(['name' => 'Alpha']);

        $export = $this->makeExport([
            'format' => 'pdf',
            'payload' => [
                'version' => 1,
                'userId' => 1,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id],
                'columns' => [['field' => 'name', 'label' => 'Name', 'type' => 'text']],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'pdf',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('done', $export->status);
        $this->assertSame(1, $export->rows);
        $this->assertStringEndsWith('.pdf', $export->file_path);
        Storage::disk('local')->assertExists($export->file_path);
    }

    #[Test]
    public function it_marks_the_export_failed_when_the_model_cannot_be_resolved(): void
    {
        $export = $this->makeExport([
            'model' => 'Totally\\Unknown\\Model',
            'payload' => [
                'version' => 1,
                'userId' => 1,
                'model' => 'Totally\\Unknown\\Model',
                'route' => '',
                'companyId' => null,
                'ids' => [1],
                'columns' => [],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('failed', $export->status);
        $this->assertNotNull($export->error);
        $this->assertNull($export->file_path);
    }

    #[Test]
    public function it_marks_the_export_failed_and_writes_no_file_when_the_model_is_not_allowlisted(): void
    {
        Storage::fake('local');

        // JobExportUnlistedStub has NO crud_configs row — the allowlist gate
        // (ExportAuthorizer, same one download() enforces) must reject it
        // BEFORE any query/file-generation happens, even though the class
        // itself resolves fine and the ids are real rows.
        $a = JobExportStub::create(['name' => 'Alpha']);

        $export = $this->makeExport([
            'model' => JobExportUnlistedStub::class,
            'payload' => [
                'version' => 1,
                'userId' => 1,
                'model' => JobExportUnlistedStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id],
                'columns' => [],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('failed', $export->status);
        $this->assertNotNull($export->error);
        $this->assertNull($export->file_path);
        Storage::disk('local')->assertDirectoryEmpty('ptah-exports');
    }

    #[Test]
    public function it_marks_the_export_failed_when_the_permission_module_denies_read(): void
    {
        CrudConfig::where('model', JobExportStub::class)->delete();
        CrudConfig::create([
            'model' => JobExportStub::class,
            'route' => '',
            'config' => [
                'crud' => JobExportStub::class,
                'cols' => [],
                'permissions' => ['permissionIdentifier' => 'job.export.denied'],
            ],
        ]);

        $export = $this->makeExport();

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('failed', $export->status);
        $this->assertNotNull($export->error);
    }

    #[Test]
    public function it_returns_silently_when_the_export_row_no_longer_exists(): void
    {
        // Never created — the authorizer/Excel/Pdf must never be touched.
        (new GenerateCrudExportJob(999999))->handle();

        $this->assertSame(0, Export::query()->count());
    }

    #[Test]
    public function it_returns_silently_when_the_export_has_already_expired(): void
    {
        $export = $this->makeExport([
            'status' => 'done',
            'expires_at' => now()->subDay(),
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        // Untouched — still 'done', not flipped back to 'processing'.
        $this->assertSame('done', $export->status);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Jobs\GenerateCrudExportJob;
use Ptah\Models\CrudConfig;
use Ptah\Models\Export;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
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

    // ── Real worker scenario: no request, no session, no auth() ────────────────
    //
    // A queue worker never has an authenticated user or an active session. The
    // job must authorize by the EXPORT'S OWNER (user_id/company_id), not by the
    // ambient request context — which is always empty here and would make
    // every queued export fail (the "always denied" bug this test guards).

    #[Test]
    public function it_authorizes_by_the_export_owner_even_with_no_authenticated_user(): void
    {
        Storage::fake('local');

        CrudConfig::where('model', JobExportStub::class)->delete();
        CrudConfig::create([
            'model' => JobExportStub::class,
            'route' => '',
            'config' => [
                'crud' => JobExportStub::class,
                'cols' => [],
                'permissions' => ['permissionIdentifier' => 'job.export.owned'],
            ],
        ]);

        $page = PtahPage::create(['slug' => 'job-export', 'name' => 'Job export', 'is_active' => true]);
        $pageObject = PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'job.export.owned', 'obj_label' => 'Job export',
            'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true,
        ]);
        $role = Role::create(['name' => 'ExportOwner', 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $pageObject->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);
        $ownerId = 42;
        UserRole::create(['user_id' => $ownerId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);

        $this->assertGuest();

        $a = JobExportStub::create(['name' => 'Alpha']);

        $export = $this->makeExport([
            'user_id' => $ownerId,
            'payload' => [
                'version' => 1,
                'userId' => $ownerId,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id],
                'columns' => [['field' => 'name', 'label' => 'Name', 'type' => 'text']],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('done', $export->status);
        $this->assertNull($export->error);
    }

    #[Test]
    public function it_denies_when_the_export_owner_lacks_the_grant_even_with_no_authenticated_user(): void
    {
        CrudConfig::where('model', JobExportStub::class)->delete();
        CrudConfig::create([
            'model' => JobExportStub::class,
            'route' => '',
            'config' => [
                'crud' => JobExportStub::class,
                'cols' => [],
                'permissions' => ['permissionIdentifier' => 'job.export.owned'],
            ],
        ]);

        $page = PtahPage::create(['slug' => 'job-export', 'name' => 'Job export', 'is_active' => true]);
        PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'job.export.owned', 'obj_label' => 'Job export',
            'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true,
        ]);

        $this->assertGuest();

        // ownerId 999 has NO role/grant at all.
        $export = $this->makeExport(['user_id' => 999]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('failed', $export->status);
        $this->assertSame('You are not allowed to export this data.', $export->error);
    }

    // ── Wave 2: re-authorizing columns at file-generation time ──────────────
    //
    // The job must never trust the permission baked into $payload['columns']
    // at queueExport() time — it re-evaluates the export OWNER's current
    // grants (never the ambient auth()/session, which are empty in a worker)
    // right before touching the query/disk, exactly like the allowlist gate
    // above.

    private function denyOwner(int $ownerId): void
    {
        $page = PtahPage::create(['slug' => 'job-column-'.uniqid(), 'name' => 'Job column', 'is_active' => true]);
        PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'items.secret_amount', 'obj_label' => 'items.secret_amount',
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
        UserRole::create([
            'user_id' => $ownerId,
            'role_id' => Role::create(['name' => 'R'.uniqid(), 'is_active' => true])->id,
            'company_id' => null, 'is_active' => true,
        ]);
    }

    /** @return array{role: Role, pageObject: PageObject} */
    private function grantOwner(int $ownerId): array
    {
        $page = PtahPage::create(['slug' => 'job-column-'.uniqid(), 'name' => 'Job column', 'is_active' => true]);
        $pageObject = PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'items.secret_amount', 'obj_label' => 'items.secret_amount',
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
        $role = Role::create(['name' => 'R'.uniqid(), 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $pageObject->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);
        UserRole::create(['user_id' => $ownerId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);

        return ['role' => $role, 'pageObject' => $pageObject];
    }

    #[Test]
    public function it_omits_a_column_the_export_owner_has_no_grant_for(): void
    {
        Excel::fake();
        $ownerId = 501;
        $this->denyOwner($ownerId);

        $a = JobExportStub::create(['name' => 'Alpha', 'amount' => 823199]);

        $export = $this->makeExport([
            'user_id' => $ownerId,
            'payload' => [
                'version' => 1,
                'userId' => $ownerId,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id],
                'columns' => [
                    ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
                ],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();
        $this->assertSame('done', $export->status);

        Excel::assertStored($export->file_path, $export->file_disk, function ($job) {
            $this->assertSame(['Name'], $job->headings());

            return true;
        });
    }

    #[Test]
    public function a_grant_revoked_between_dispatch_and_the_worker_running_still_excludes_the_column(): void
    {
        Excel::fake();
        $ownerId = 502;
        // Owner is GRANTED when the export is "queued" — the payload below
        // carries the column exactly as queueExport() would have built it at
        // that moment (tagged, and included because the grant existed then).
        ['role' => $role] = $this->grantOwner($ownerId);

        $a = JobExportStub::create(['name' => 'Alpha', 'amount' => 823199]);

        $export = $this->makeExport([
            'user_id' => $ownerId,
            'payload' => [
                'version' => 1,
                'userId' => $ownerId,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id],
                'columns' => [
                    ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
                ],
                'order' => 'id',
                'direction' => 'DESC',
                'format' => 'excel',
            ],
        ]);

        // The grant is revoked AFTER dispatch, BEFORE the worker picks the
        // job up — authorization must never be frozen into the payload.
        $role->delete();

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();
        $this->assertSame('done', $export->status);

        Excel::assertStored($export->file_path, $export->file_disk, function ($job) {
            $this->assertSame(['Name'], $job->headings());

            return true;
        });
    }

    #[Test]
    public function it_marks_the_export_failed_and_writes_no_file_when_every_column_is_denied(): void
    {
        Storage::fake('local');
        Excel::fake();
        $ownerId = 503;
        $this->denyOwner($ownerId);

        $a = JobExportStub::create(['name' => 'Alpha', 'amount' => 823199]);

        // The ONLY column in the payload is the denied one — without the
        // fail-closed guard, CrudExport would treat this as "no columns
        // configured" and dump every attribute instead of failing.
        $export = $this->makeExport([
            'user_id' => $ownerId,
            'payload' => [
                'version' => 1,
                'userId' => $ownerId,
                'model' => JobExportStub::class,
                'route' => 'items',
                'companyId' => null,
                'ids' => [$a->id],
                'columns' => [
                    ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
                ],
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
    public function a_v1_payload_with_no_permission_key_on_any_column_keeps_working_unchanged(): void
    {
        Storage::fake('local');

        $a = JobExportStub::create(['name' => 'Alpha']);

        // No `permission` key anywhere — exactly the payload shape built
        // before this wave shipped. filterExportColumns() must treat both
        // columns as public and leave the file untouched.
        $export = $this->makeExport([
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
                'format' => 'excel',
            ],
        ]);

        (new GenerateCrudExportJob($export->id))->handle();

        $export->refresh();

        $this->assertSame('done', $export->status);
        Storage::disk('local')->assertExists($export->file_path);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\Export;
use Ptah\Tests\TestCase;

/** Plain stub on the shared `items` table (see tests/migrations). */
class FileDownloadStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class FileDownloadUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers ExportController::file() — the persistent, DB-backed download for a
 * queued export (Fase 3 — "grande volume"): ownership check, the same CRUD
 * allowlist/permission gate as the synchronous download(), and status/expiry.
 */
class ExportControllerFileTest extends TestCase
{
    private function actingAsUser(): FileDownloadUser
    {
        $user = FileDownloadUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function configureAllowlist(string $model, array $permissions = []): void
    {
        CrudConfig::create([
            'model' => $model,
            'route' => '',
            'config' => [
                'crud' => $model,
                'cols' => [],
                'permissions' => $permissions,
            ],
        ]);
    }

    private function makeExport(int $userId, array $overrides = []): Export
    {
        return Export::create(array_merge([
            'user_id' => $userId,
            'company_id' => null,
            'model' => FileDownloadStub::class,
            'route' => '',
            'format' => 'excel',
            'status' => 'done',
            'file_disk' => 'local',
            'file_path' => 'ptah-exports/test.xlsx',
            'rows' => 1,
            'payload' => ['version' => 1],
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    /**
     * Sets the ACTIVE company the same way ptah_company_id() reads it —
     * config('ptah.permissions.company_session_key') in the session — without
     * pulling in the full Company module (CompanyService::setActive() also
     * requires a real ptah_companies row, irrelevant to this gate).
     */
    private function setActiveCompany(int $id): void
    {
        session([config('ptah.permissions.company_session_key', 'ptah_company_id') => $id]);
    }

    #[Test]
    public function it_is_forbidden_for_a_different_owner(): void
    {
        $this->actingAsUser();
        $this->configureAllowlist(FileDownloadStub::class);
        $export = $this->makeExport(999999);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertForbidden();
    }

    #[Test]
    public function it_is_forbidden_when_the_model_is_not_an_allowlisted_crud(): void
    {
        $user = $this->actingAsUser();
        // No CrudConfig row for FileDownloadStub — authorizeExport() must deny.
        $export = $this->makeExport($user->id);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertForbidden();
    }

    #[Test]
    public function it_is_forbidden_when_the_permission_module_denies_read(): void
    {
        $user = $this->actingAsUser();
        // Permissions module is active in the test environment; a configured
        // permissionIdentifier with no grant for this user must deny (fail-closed).
        $this->configureAllowlist(FileDownloadStub::class, ['permissionIdentifier' => 'export.file.test']);
        $export = $this->makeExport($user->id);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertForbidden();
    }

    #[Test]
    public function it_returns_404_when_the_export_is_not_done(): void
    {
        $user = $this->actingAsUser();
        $this->configureAllowlist(FileDownloadStub::class);
        $export = $this->makeExport($user->id, ['status' => 'processing']);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertNotFound();
    }

    #[Test]
    public function it_returns_410_when_the_export_has_expired(): void
    {
        $user = $this->actingAsUser();
        $this->configureAllowlist(FileDownloadStub::class);
        $export = $this->makeExport($user->id, ['expires_at' => now()->subDay()]);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertStatus(410);
    }

    #[Test]
    public function it_downloads_the_file_for_the_owner_when_done_and_not_expired(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/test.xlsx', 'fake-xlsx-content');

        $user = $this->actingAsUser();
        $this->configureAllowlist(FileDownloadStub::class);
        $export = $this->makeExport($user->id);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertOk();
    }

    #[Test]
    public function it_denies_download_when_the_export_belongs_to_another_company(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/test.xlsx', 'fake-xlsx-content');

        $user = $this->actingAsUser();
        $this->configureAllowlist(FileDownloadStub::class);
        // Export was generated while company A (1) was active…
        $export = $this->makeExport($user->id, ['company_id' => 1]);
        // …but the user's ACTIVE company is now B (2) — authorization must not
        // stay "frozen" at creation time, even though the user still OWNS the
        // export.
        $this->setActiveCompany(2);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertForbidden();
    }

    #[Test]
    public function it_allows_download_when_the_active_company_matches_the_exports_company(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/test.xlsx', 'fake-xlsx-content');

        $user = $this->actingAsUser();
        $this->configureAllowlist(FileDownloadStub::class);
        $export = $this->makeExport($user->id, ['company_id' => 1]);
        $this->setActiveCompany(1);

        $this->get(route('ptah.export.file', ['export' => $export->id]))->assertOk();
    }

    #[Test]
    public function it_returns_404_for_an_unknown_export_id(): void
    {
        $this->actingAsUser();

        $this->get(route('ptah.export.file', ['export' => 999999]))->assertNotFound();
    }
}

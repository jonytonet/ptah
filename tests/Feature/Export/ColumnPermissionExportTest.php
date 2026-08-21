<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

// ── Stub on the shared `items` table ────────────────────────────────────────

class ColumnPermissionExportStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class ColumnPermissionExportUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * A minimal stand-in for barryvdh/laravel-dompdf's `dompdf.wrapper` binding
 * (see Barryvdh\DomPDF\Facade\Pdf) that records the data handed to
 * `loadView()` instead of actually rendering a PDF — cheap and precise for
 * asserting exactly which columns/totalizers reached the view, without
 * parsing a real PDF binary or introducing a mocking library this codebase's
 * tests do not otherwise use.
 */
class RecordingDompdfWrapper
{
    /** @var array<string, mixed> */
    public array $lastData = [];

    public function loadView(string $view, array $data = [], array $mergeData = [], ?string $encoding = null): static
    {
        $this->lastData = $data;

        return $this;
    }

    public function setPaper(string $paper, string $orientation = 'portrait'): static
    {
        return $this;
    }

    public function download(string $filename = 'document.pdf')
    {
        return response('fake-pdf-content');
    }

    public function save(string $path, ?string $disk = null): static
    {
        return $this;
    }
}

/**
 * Wave 2 — re-authorizing EXPORT columns at file-generation time
 * (ExportController::download()), instead of trusting the `colsPermission`
 * gate applied only once, when the token/queued payload was built (Wave 1).
 *
 * Covers both formats (a denied column must disappear from the generated
 * Excel AND PDF), the PDF totalizer leak this wave closes
 * (ExportController::getTotalizers() re-querying crud_configs with no gate
 * at all), the fail-closed guard when every column ends up denied, and that
 * a v1 payload (no `permission` key on any column) keeps working unchanged.
 *
 * GenerateCrudExportJob's own re-authorization (the queued/async path) is
 * covered separately in GenerateCrudExportJobTest.
 */
class ColumnPermissionExportTest extends TestCase
{
    private const SENSITIVE_AMOUNT = 823199;

    private function actingAsUser(): ColumnPermissionExportUser
    {
        $user = ColumnPermissionExportUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function configureAllowlist(array $extra = []): void
    {
        CrudConfig::create([
            'model' => ColumnPermissionExportStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => ColumnPermissionExportStub::class,
                'cols' => [],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function denyUser(int $userId): void
    {
        $page = PtahPage::create(['slug' => 'export-screen-'.uniqid(), 'name' => 'Export screen', 'is_active' => true]);
        PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'items.secret_amount', 'obj_label' => 'items.secret_amount',
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
        $role = Role::create(['name' => 'R'.uniqid(), 'is_master' => false, 'is_active' => true]);
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    private function grantUser(int $userId): void
    {
        $page = PtahPage::create(['slug' => 'export-screen-'.uniqid(), 'name' => 'Export screen', 'is_active' => true]);
        $obj = PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'items.secret_amount', 'obj_label' => 'items.secret_amount',
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
        $role = Role::create(['name' => 'R'.uniqid(), 'is_master' => false, 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);
        UserRole::create(['user_id' => $userId, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    private function putToken(int $userId, array $columns, array $overrides = []): string
    {
        $token = 'test-token-'.uniqid();
        Cache::put('ptah:export:'.$token, array_merge([
            'version' => 1,
            'userId' => $userId,
            'model' => ColumnPermissionExportStub::class,
            'ids' => [],
            'columns' => $columns,
            'order' => 'id',
            'direction' => 'DESC',
            'format' => 'excel',
        ], $overrides), now()->addMinutes(10));

        return $token;
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    #[Test]
    public function excel_download_omits_a_column_the_user_lost_access_to(): void
    {
        Excel::fake();
        Excel::matchByRegex();
        $this->configureAllowlist();
        $user = $this->actingAsUser();
        $this->denyUser($user->id);
        ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $token = $this->putToken($user->id, [
            ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
        ]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertOk();

        Excel::assertDownloaded('/\.xlsx$/', function ($export) {
            $this->assertSame(['Name'], $export->headings());

            return true;
        });
    }

    #[Test]
    public function excel_download_keeps_a_column_the_user_still_has_the_grant_for(): void
    {
        Excel::fake();
        Excel::matchByRegex();
        $this->configureAllowlist();
        $user = $this->actingAsUser();
        $this->grantUser($user->id);
        ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $token = $this->putToken($user->id, [
            ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
        ]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertOk();

        Excel::assertDownloaded('/\.xlsx$/', function ($export) {
            $this->assertSame(['Name', 'Secret Amount'], $export->headings());

            return true;
        });
    }

    #[Test]
    public function a_v1_payload_with_no_permission_key_on_any_column_keeps_working_unchanged(): void
    {
        Excel::fake();
        Excel::matchByRegex();
        $this->configureAllowlist();
        $user = $this->actingAsUser();
        // Deliberately NOT calling denyUser()/grantUser() — no PageObject for
        // any key exists at all, exactly like a payload cached/queued before
        // this wave shipped (no `permission` key on either column).
        ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $token = $this->putToken($user->id, [
            ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['field' => 'amount', 'label' => 'Amount', 'type' => 'number'],
        ]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertOk();

        Excel::assertDownloaded('/\.xlsx$/', function ($export) {
            $this->assertSame(['Name', 'Amount'], $export->headings());

            return true;
        });
    }

    // ── Fail-closed: every column denied ────────────────────────────────────

    #[Test]
    public function the_synchronous_download_is_forbidden_when_every_column_is_denied(): void
    {
        Excel::fake();
        $this->configureAllowlist();
        $user = $this->actingAsUser();
        $this->denyUser($user->id);
        ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        // The ONLY column in the payload is the denied one — without the
        // fail-closed guard, CrudExport::headings()/map() would treat this
        // exactly like "no columns configured" and dump every attribute.
        $token = $this->putToken($user->id, [
            ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
        ]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertForbidden();
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    #[Test]
    public function pdf_download_omits_a_column_the_user_lost_access_to(): void
    {
        $this->configureAllowlist();
        $user = $this->actingAsUser();
        $this->denyUser($user->id);
        $row = ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $wrapper = new RecordingDompdfWrapper;
        $this->app->instance('dompdf.wrapper', $wrapper);

        $token = $this->putToken($user->id, [
            ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
        ], ['format' => 'pdf', 'ids' => [$row->id]]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertOk();

        $labels = array_column($wrapper->lastData['columns'], 'label');
        $this->assertContains('Name', $labels);
        $this->assertNotContains('Secret Amount', $labels);
    }

    #[Test]
    public function pdf_download_omits_the_totalizer_for_a_column_the_user_lost_access_to(): void
    {
        // The Wave 2, step 7 leak: getTotalizers() re-queries crud_configs
        // fresh from the DB and, before this wave's fix, aggregated every
        // configured totalizer column with NO permission check at all — the
        // sum of the denied `amount` column would still reach the PDF even
        // though the column itself is gone from the table.
        $this->configureAllowlist([
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text'],
                [
                    'colsNomeFisico' => 'amount',
                    'colsNomeLogico' => 'Secret Amount',
                    'colsTipo' => 'number',
                    'colsPermission' => 'items.secret_amount',
                ],
            ],
            'totalizadores' => [
                'enabled' => true,
                'columns' => [['field' => 'amount', 'aggregate' => 'sum', 'label' => 'Secret Amount']],
            ],
            'ui' => ['showTotalizador' => true],
        ]);
        $user = $this->actingAsUser();
        $this->denyUser($user->id);
        $row = ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $wrapper = new RecordingDompdfWrapper;
        $this->app->instance('dompdf.wrapper', $wrapper);

        $token = $this->putToken($user->id, [
            ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
        ], ['format' => 'pdf', 'ids' => [$row->id]]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertOk();

        $this->assertSame([], $wrapper->lastData['totalizers']);
    }

    #[Test]
    public function pdf_download_keeps_the_totalizer_when_the_user_still_has_the_grant(): void
    {
        $this->configureAllowlist([
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text'],
                [
                    'colsNomeFisico' => 'amount',
                    'colsNomeLogico' => 'Secret Amount',
                    'colsTipo' => 'number',
                    'colsPermission' => 'items.secret_amount',
                ],
            ],
            'totalizadores' => [
                'enabled' => true,
                'columns' => [['field' => 'amount', 'aggregate' => 'sum', 'label' => 'Secret Amount']],
            ],
            'ui' => ['showTotalizador' => true],
        ]);
        $user = $this->actingAsUser();
        $this->grantUser($user->id);
        $row = ColumnPermissionExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $wrapper = new RecordingDompdfWrapper;
        $this->app->instance('dompdf.wrapper', $wrapper);

        $token = $this->putToken($user->id, [
            ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['field' => 'amount', 'label' => 'Secret Amount', 'type' => 'number', 'permission' => 'items.secret_amount'],
        ], ['format' => 'pdf', 'ids' => [$row->id]]);

        $this->get(route('ptah.export.download', ['token' => $token]))->assertOk();

        $this->assertCount(1, $wrapper->lastData['totalizers']);
        $this->assertSame(self::SENSITIVE_AMOUNT, $wrapper->lastData['totalizers'][0]['value']);
    }
}

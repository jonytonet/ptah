<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Exports\CrudExport;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ExportStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Covers ExportController::download — the token-based export. The BaseCrud
 * component filters the listing and caches the ordered ids under a user-scoped
 * token; the controller only fetches those ids (model resolved server-side, no
 * client model param, allowlist re-checked).
 */
class CrudExportControllerTest extends TestCase
{
    private function configureExportStub(): void
    {
        CrudConfig::create([
            'model' => ExportStub::class,
            'route' => '',
            'config' => [
                'crud' => ExportStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function putToken(string $token, array $overrides = []): void
    {
        Cache::put('ptah:export:'.$token, array_merge([
            'version' => 1,
            'userId' => null,
            'model' => ExportStub::class,
            'ids' => [1, 2],
            'columns' => [['field' => 'name', 'label' => 'Name', 'type' => 'text']],
            'order' => 'id',
            'direction' => 'DESC',
            'format' => 'excel',
        ], $overrides), now()->addMinutes(10));
    }

    #[Test]
    public function unknown_token_returns_404(): void
    {
        $this->get(route('ptah.export.download', ['token' => 'nope']))->assertNotFound();
    }

    #[Test]
    public function token_owned_by_another_user_is_forbidden(): void
    {
        $this->configureExportStub();
        $this->putToken('owned', ['userId' => 999]); // current request is a guest

        $this->get(route('ptah.export.download', ['token' => 'owned']))->assertForbidden();
    }

    #[Test]
    public function model_without_a_crud_config_is_forbidden(): void
    {
        // Token references a model that is not a configured Ptah CRUD.
        $this->putToken('nocfg', ['model' => 'App\\Models\\User']);

        $this->get(route('ptah.export.download', ['token' => 'nocfg']))->assertForbidden();
    }

    #[Test]
    public function valid_token_downloads_the_export(): void
    {
        Excel::fake();
        $this->configureExportStub();
        $a = ExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 1]);
        $b = ExportStub::create(['name' => 'Bravo', 'status' => 'active', 'amount' => 2]);

        $this->putToken('good', ['ids' => [$a->id, $b->id]]);

        $this->get(route('ptah.export.download', ['token' => 'good']))->assertOk();
    }

    /** 1.50.0: a column from a JOIN left the exported file empty. */
    #[Test]
    public function a_joined_column_reaches_the_exported_file(): void
    {
        Excel::fake();
        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->configureExportStub();
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Dona Maria', 'email' => 'dm'.uniqid().'@ex.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $a = ExportStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 1]);
        DB::table('items')->where('id', $a->id)->update(['owner_id' => $ownerId]);

        $this->putToken('joined', [
            'ids' => [$a->id],
            'columns' => [['field' => 'name', 'label' => 'Name', 'type' => 'text'], ['field' => 'owner_name', 'label' => 'Owner', 'type' => 'text']],
            'joins' => [['type' => 'left', 'table' => 'users', 'first' => 'items.owner_id', 'second' => 'users.id',
                'select' => [['column' => 'users.name', 'alias' => 'owner_name']]]],
        ]);

        $this->get(route('ptah.export.download', ['token' => 'joined']))->assertOk();

        Excel::assertDownloaded(
            'exportstub-'.now()->format('Y-m-d-His').'.xlsx',
            function (CrudExport $export) {
                $row = $export->query()->first();

                return $export->map($row) === ['Alpha', 'Dona Maria'];
            }
        );
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ExportStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Covers the ExportController allowlist guard: the /ptah/export endpoint runs
 * with only the `web` middleware, so it must refuse to export a model that is
 * not configured as a Ptah CRUD (blocks arbitrary ?model=User data dumps).
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

    #[Test]
    public function export_is_forbidden_for_a_model_without_a_crud_config(): void
    {
        // App\Models\User has no crud_configs row → the classic ?model=User dump.
        $this->get(route('ptah.export', ['model' => 'User', 'format' => 'excel']))
            ->assertForbidden();
    }

    #[Test]
    public function export_is_forbidden_when_the_model_param_is_empty(): void
    {
        $this->get(route('ptah.export', ['format' => 'excel']))
            ->assertForbidden();
    }

    #[Test]
    public function export_is_allowed_for_a_configured_crud(): void
    {
        Excel::fake();
        $this->configureExportStub();
        ExportStub::create(['name' => 'Row', 'status' => 'active', 'amount' => 1]);

        $this->get(route('ptah.export', ['model' => ExportStub::class, 'format' => 'excel']))
            ->assertOk();
    }

    #[Test]
    public function bulk_export_is_forbidden_for_an_unconfigured_model(): void
    {
        $this->get(route('ptah.export.bulk', ['model' => 'User', 'ids' => '[1,2]', 'format' => 'excel']))
            ->assertForbidden();
    }
}

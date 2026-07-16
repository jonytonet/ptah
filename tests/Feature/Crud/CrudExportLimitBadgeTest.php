<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class ExportLimitStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Covers the "Limite" export-menu badge (BaseCrud::render()): shown when the
 * filtered listing exceeds exportConfig.maxRows, suppressed under groupBy
 * (total() is unreliable there) and when under the limit.
 */
class CrudExportLimitBadgeTest extends TestCase
{
    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => ExportLimitStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => ExportLimitStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'exportConfig' => ['enabled' => true, 'maxRows' => 2],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function seedRows(): void
    {
        ExportLimitStub::create(['name' => 'Alpha', 'status' => 'active']);
        ExportLimitStub::create(['name' => 'Beta', 'status' => 'active']);
        ExportLimitStub::create(['name' => 'Charlie', 'status' => 'active']);
    }

    #[Test]
    public function badge_is_shown_when_the_filtered_total_exceeds_max_rows(): void
    {
        $this->makeConfig();
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => ExportLimitStub::class]);

        $this->assertTrue($component->viewData('exportOverLimit'));
        $component->assertSee(__('ptah::ui.export_limit_badge'));
    }

    #[Test]
    public function badge_is_hidden_when_under_the_limit(): void
    {
        $this->makeConfig(['exportConfig' => ['enabled' => true, 'maxRows' => 10]]);
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => ExportLimitStub::class]);

        $this->assertFalse($component->viewData('exportOverLimit'));
    }

    #[Test]
    public function badge_is_suppressed_when_group_by_is_active_even_over_the_limit(): void
    {
        $this->makeConfig(['groupBy' => 'status']);
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => ExportLimitStub::class]);

        // total() counts groups (not raw rows) with groupBy — unreliable, so the
        // badge must stay off regardless of the maxRows comparison.
        $this->assertFalse($component->viewData('exportOverLimit'));
    }
}

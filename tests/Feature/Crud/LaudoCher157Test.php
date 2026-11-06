<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Exports\CrudExport;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\CrudScreenInspector;
use Ptah\Tests\TestCase;

class Cher157Item extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class Cher157ViewRow extends Model
{
    protected $table = 'report_items_by_status';
}

/**
 * Laudo Cher (1.57.0): ptah:check over SQL views, money in cents, groupBy
 * aggregates.
 */
class LaudoCher157Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9]));
    }

    private function configure(array $config): void
    {
        CrudConfig::updateOrCreate(['model' => Cher157Item::class, 'route' => ''], ['config' => array_merge([
            'crud' => Cher157Item::class,
            'permissions' => [],
        ], $config)]);
    }

    #[Test]
    public function a_screen_over_a_sql_view_is_inspected_like_a_table(): void
    {
        DB::statement('CREATE VIEW report_items_by_status AS SELECT status, COUNT(*) AS total FROM items GROUP BY status');

        try {
            $findings = CrudScreenInspector::inspect(new Cher157ViewRow, ['cols' => [['colsNomeFisico' => 'status']]]);

            $this->assertNotContains('error', array_column($findings, 'level'), json_encode($findings));
        } finally {
            DB::statement('DROP VIEW report_items_by_status');
        }

        $missing = CrudScreenInspector::inspect(new class extends Model
        {
            protected $table = 'nowhere_at_all';
        }, ['cols' => []]);
        $this->assertSame('error', $missing[0]['level']);
    }

    #[Test]
    public function money_in_cents_shows_in_currency_units_in_the_cell_and_the_footer(): void
    {
        $this->configure([
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsRenderer' => 'money', 'colsRendererCents' => true],
            ],
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]],
        ]);
        Cher157Item::create(['name' => 'A', 'amount' => 123456]);
        Cher157Item::create(['name' => 'B', 'amount' => 50]);

        Livewire::test(BaseCrud::class, ['model' => Cher157Item::class])
            ->assertSee('R$ 1.234,56')
            ->assertSee('R$ 0,50')
            ->assertSee('R$ 1.235,06');
    }

    #[Test]
    public function the_excel_export_gets_money_in_cents_as_currency_units(): void
    {
        $export = new CrudExport(Cher157Item::query(), [['field' => 'amount', 'label' => 'Valor', 'type' => 'number', 'cents' => true]]);

        $this->assertSame([1234.56], $export->map(new Cher157Item(['amount' => 123456])));
    }

    #[Test]
    public function group_by_returns_aggregates_per_group(): void
    {
        $this->configure([
            'groupBy' => 'status',
            'groupByAggregates' => [
                ['field' => 'amount', 'aggregate' => 'sum'],
                ['field' => '*', 'aggregate' => 'count', 'alias' => 'total'],
                ['field' => 'amount; drop', 'aggregate' => 'sum'],
                ['field' => 'amount', 'aggregate' => 'median'],
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'id'],
            ],
            'cols' => [
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Soma', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'total', 'colsNomeLogico' => 'Qtde', 'colsTipo' => 'number'],
            ],
        ]);
        Cher157Item::create(['name' => 'A', 'status' => 'active', 'amount' => 10]);
        Cher157Item::create(['name' => 'B', 'status' => 'active', 'amount' => 15]);
        Cher157Item::create(['name' => 'C', 'status' => 'inactive', 'amount' => 7]);

        $rows = Livewire::test(BaseCrud::class, ['model' => Cher157Item::class])->instance()->rows;
        $byStatus = collect($rows->items())->keyBy('status');

        $this->assertSame(25, (int) $byStatus['active']->amount);
        $this->assertSame(2, (int) $byStatus['active']->total);
        $this->assertSame(7, (int) $byStatus['inactive']->amount);
        $this->assertSame(1, (int) $byStatus['inactive']->total);
    }
}

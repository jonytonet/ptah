<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\DashboardService;
use Ptah\Tests\TestCase;

class ChartSale extends Model
{
    protected $table = 'chart_sales';

    protected $guarded = [];

    protected $hidden = ['secret_note'];

    public function method(): BelongsTo
    {
        return $this->belongsTo(ChartMethod::class, 'method_id');
    }
}

class ChartMethod extends Model
{
    protected $table = 'chart_methods';

    protected $guarded = [];

    public $timestamps = false;
}

/**
 * 1.43.0: `trend` totals a value (sum/avg) per day, week or month, grouped
 * in the database; `breakdown` totals per category; widgets come in named
 * groups for other pages.
 */
class DashboardChartsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-24 10:00:00');

        Schema::create('chart_methods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('chart_sales', function (Blueprint $t) {
            $t->id();
            $t->string('kind')->nullable();
            $t->decimal('total', 10, 2);
            $t->unsignedBigInteger('method_id')->nullable();
            $t->string('secret_note')->nullable();
            $t->unsignedBigInteger('company_id');
            $t->timestamps();
        });

        $pix = ChartMethod::create(['name' => 'PIX'])->id;
        $card = ChartMethod::create(['name' => 'Cartão'])->id;
        $cash = ChartMethod::create(['name' => 'Dinheiro'])->id;

        foreach ([
            ['pix', 100, $pix, 1, '2026-09-24 08:00:00'],
            ['pix', 50, $pix, 1, '2026-09-23 08:00:00'],
            ['card', 300, $card, 1, '2026-09-10 08:00:00'],
            ['cash', 20, $cash, 1, '2026-09-02 08:00:00'],
            [null, 10, null, 1, '2026-09-01 08:00:00'],
            ['pix', 1000, $pix, 1, '2026-07-15 08:00:00'],
            ['pix', 7777, $pix, 2, '2026-09-24 08:00:00'],
        ] as [$kind, $total, $method, $company, $at]) {
            ChartSale::query()->forceCreate(['kind' => $kind, 'total' => $total, 'method_id' => $method, 'company_id' => $company, 'secret_note' => 'x', 'created_at' => $at, 'updated_at' => $at]);
        }

        session(['ptah_company_id' => 1]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function widget(array $w): array
    {
        return app(DashboardService::class)->compute($w + ['model' => ChartSale::class, 'cache' => 0]);
    }

    #[Test]
    public function a_trend_sums_a_value_per_month_with_money_format(): void
    {
        $trend = $this->widget(['type' => 'trend', 'aggregate' => 'sum', 'field' => 'total', 'group' => 'month', 'days' => 90, 'format' => 'money']);

        $this->assertNull($trend['error']);
        $this->assertSame(['2026-07-01', '2026-08-01', '2026-09-01'], array_column($trend['points'], 'date'), '90 dias por mes = 3 barras, do inicio do mes.');
        $this->assertEquals([1000, 0, 480], array_column($trend['points'], 'value'));
        $this->assertSame('R$ 1.480,00', $trend['display_total'], 'A empresa 2 nao entra.');
        $this->assertSame('09/2026', end($trend['points'])['label']);
    }

    #[Test]
    public function a_trend_average_is_sum_over_count_not_an_average_of_days(): void
    {
        $trend = $this->widget(['type' => 'trend', 'aggregate' => 'avg', 'field' => 'total', 'group' => 'week', 'days' => 21]);

        // 21 dias = 3 semanas, desde 07/09. Semana de 21/09: 100 e 50 -> 75.
        // A media do total: (100 + 50 + 300) / 3 = 150, nao a media das semanas.
        $this->assertEquals(75, end($trend['points'])['value']);
        $this->assertEquals(150, $trend['total']);
    }

    #[Test]
    public function a_trend_is_grouped_in_the_database(): void
    {
        DB::enableQueryLog();
        $this->widget(['type' => 'trend', 'days' => 30]);
        $sql = strtolower(implode(' ', array_column(DB::getQueryLog(), 'query')));

        $this->assertStringContainsString('group by', $sql, 'O trend voltou a trazer as linhas para o PHP.');
    }

    #[Test]
    public function a_breakdown_totals_per_column_with_labels_and_others(): void
    {
        $bd = $this->widget(['type' => 'breakdown', 'group_by' => 'kind', 'aggregate' => 'sum', 'field' => 'total', 'period' => 'month', 'limit' => 2,
            'labels' => ['card' => 'Cartão', 'pix' => 'PIX']]);

        $this->assertNull($bd['error']);
        $this->assertSame(['Cartão', 'PIX', __('ptah::ui.dashboard_others')], array_column($bd['items'], 'label'));
        $this->assertEquals([300, 150, 30], array_column($bd['items'], 'value'), 'Os outros somam o resto: dinheiro 20 + sem valor 10.');
        $this->assertEquals(480, $bd['total']);
    }

    #[Test]
    public function a_breakdown_groups_by_a_belongs_to_relation(): void
    {
        $bd = $this->widget(['type' => 'breakdown', 'group_by' => 'method.name', 'period' => 'month', 'limit' => 5]);

        $this->assertNull($bd['error']);
        $got = array_combine(array_column($bd['items'], 'label'), array_column($bd['items'], 'value'));
        $want = ['PIX' => 2, 'Cartão' => 1, 'Dinheiro' => 1, __('ptah::ui.dashboard_no_value') => 1];
        ksort($got);
        ksort($want);
        $this->assertSame($want, $got);
        $this->assertSame('PIX', $bd['items'][0]['label'], 'O maior primeiro.');
    }

    #[Test]
    public function a_breakdown_never_groups_by_a_hidden_attribute_or_a_model_method(): void
    {
        $this->assertStringContainsString('cannot be grouped', (string) $this->widget(['type' => 'breakdown', 'group_by' => 'secret_note'])['error']);
        $this->assertStringContainsString('not a valid column', (string) $this->widget(['type' => 'breakdown', 'group_by' => 'delete.x;'])['error']);
        $this->assertSame(1, ChartSale::query()->where('company_id', 2)->count(), 'Um metodo do Model nao pode ser chamado.');
    }

    #[Test]
    public function widgets_come_in_named_groups_and_the_flat_list_is_the_default(): void
    {
        config(['ptah-dashboard.widgets' => [
            ['type' => 'stat', 'label' => 'Vendas', 'model' => ChartSale::class, 'cache' => 0],
            'financeiro' => [
                ['type' => 'stat', 'label' => 'A receber', 'model' => ChartSale::class, 'cache' => 0],
            ],
        ]]);
        $service = app(DashboardService::class);

        $this->assertSame(['Vendas'], array_column($service->visibleWidgets(), 'label'));
        $this->assertSame(['A receber'], array_column($service->visibleWidgets('financeiro'), 'label'));
        $this->assertSame([], $service->visibleWidgets('nao-existe'));

        $html = view('ptah::dashboard.widgets', ['group' => 'financeiro'])->render();
        $this->assertStringContainsString('A receber', $html);
        $this->assertStringNotContainsString('Vendas', $html);
    }

    #[Test]
    public function the_partial_draws_a_breakdown_and_a_value_trend(): void
    {
        config(['ptah-dashboard.widgets' => [
            ['type' => 'breakdown', 'label' => 'Por forma', 'model' => ChartSale::class, 'group_by' => 'method.name', 'aggregate' => 'sum', 'field' => 'total', 'format' => 'money', 'cache' => 0],
            ['type' => 'trend', 'label' => 'Faturamento', 'model' => ChartSale::class, 'aggregate' => 'sum', 'field' => 'total', 'format' => 'money', 'days' => 7, 'cache' => 0],
        ]]);

        $html = view('ptah::dashboard.widgets')->render();

        $this->assertStringContainsString('Por forma', $html);
        $this->assertStringContainsString('R$ 1.150,00', $html, 'PIX: 100 + 50 + 1000.');
        $this->assertStringContainsString('R$ 150,00', $html, 'Faturamento dos ultimos 7 dias.');
    }
}

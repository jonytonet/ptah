<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\DashboardService;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

class DashOrder extends Model
{
    protected $table = 'dash_orders';

    protected $guarded = [];

    protected $hidden = ['secret_note'];
}

/**
 * Dashboard widgets — numbers from the host's models, inside the active
 * company, hidden from who cannot read them, and failing as a widget.
 */
class DashboardWidgetsTest extends TestCase
{
    use ActsAsPtahUser;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-24 10:00:00');

        Schema::create('dash_orders', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('status');
            $t->decimal('total', 10, 2);
            $t->string('secret_note')->nullable();
            $t->unsignedBigInteger('company_id');
            $t->timestamps();
        });

        foreach ([
            ['A1', 'invoiced', 100, 1, '2026-09-24 08:00:00'],
            ['A2', 'invoiced', 250.5, 1, '2026-09-20 08:00:00'],
            ['A3', 'open', 99, 1, '2026-09-24 09:00:00'],
            ['A4', 'invoiced', 400, 1, '2026-08-10 08:00:00'],
            ['B1', 'invoiced', 9999, 2, '2026-09-24 08:00:00'],
        ] as [$code, $status, $total, $company, $at]) {
            DashOrder::query()->forceCreate(['code' => $code, 'status' => $status, 'total' => $total, 'company_id' => $company, 'secret_note' => 'x', 'created_at' => $at, 'updated_at' => $at]);
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
        return app(DashboardService::class)->compute($w + ['model' => DashOrder::class, 'cache' => 0]);
    }

    #[Test]
    public function stats_count_and_sum_inside_the_company_and_period(): void
    {
        $this->assertSame(2, $this->widget(['type' => 'stat', 'period' => 'today'])['raw'], 'A empresa 2 nao entra.');

        $sum = $this->widget(['type' => 'stat', 'aggregate' => 'sum', 'field' => 'total', 'period' => 'month', 'format' => 'money', 'where' => [['status', '=', 'invoiced']]]);
        $this->assertSame(350.5, $sum['raw']);
        $this->assertSame('R$ 350,50', $sum['value']);
    }

    #[Test]
    public function a_trend_counts_per_day(): void
    {
        $trend = $this->widget(['type' => 'trend', 'days' => 7]);

        $this->assertCount(7, $trend['points']);
        $this->assertSame(['date' => '2026-09-24', 'count' => 2], end($trend['points']));
        $this->assertSame(3, $trend['total']);
    }

    #[Test]
    public function latest_never_shows_a_hidden_attribute(): void
    {
        $latest = $this->widget(['type' => 'latest', 'columns' => ['code', 'secret_note'], 'limit' => 2]);

        $this->assertSame(['code'], $latest['columns']);
        $this->assertSame([['A3'], ['A1']], $latest['rows']);
    }

    #[Test]
    public function a_bad_definition_fails_as_the_widget_not_as_sql(): void
    {
        $this->assertStringContainsString('not a valid column', (string) $this->widget(['type' => 'stat', 'aggregate' => 'sum', 'field' => 'total; drop table x'])['error']);
        $this->assertStringContainsString('not allowed', (string) $this->widget(['type' => 'stat', 'where' => [['status', 'or 1=1 --', 'x']]])['error']);
        $this->assertStringContainsString('does not exist', (string) app(DashboardService::class)->compute(['type' => 'stat', 'model' => 'App\\Nope', 'cache' => 0])['error']);
    }

    #[Test]
    public function a_widget_with_a_permission_is_hidden_from_who_cannot_read_it(): void
    {
        config(['ptah.modules.permissions' => true, 'ptah-dashboard.widgets' => [
            ['type' => 'stat', 'label' => 'Aberto', 'model' => DashOrder::class, 'cache' => 0],
            ['type' => 'stat', 'label' => 'Faturado', 'model' => DashOrder::class, 'permission' => 'pageFinance', 'cache' => 0],
        ]]);

        $this->actAsUserWhoCan(false);
        $this->assertSame(['Aberto'], array_column(app(DashboardService::class)->visibleWidgets(), 'label'));

        $this->actAsUserWhoCan(true);
        $this->assertSame(['Aberto', 'Faturado'], array_column(app(DashboardService::class)->visibleWidgets(), 'label'));
    }

    #[Test]
    public function the_partial_renders_every_widget_type(): void
    {
        config(['ptah-dashboard.widgets' => [
            ['type' => 'stat', 'label' => 'Pedidos hoje', 'model' => DashOrder::class, 'period' => 'today', 'cache' => 0],
            ['type' => 'trend', 'label' => 'Por dia', 'model' => DashOrder::class, 'days' => 7, 'cache' => 0],
            ['type' => 'latest', 'label' => 'Últimos', 'model' => DashOrder::class, 'columns' => ['code'], 'cache' => 0],
        ]]);

        $html = view('ptah::dashboard.widgets')->render();

        $this->assertStringContainsString('Pedidos hoje', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('A3', $html);
        $this->assertStringNotContainsString('B1', $html);
    }
}

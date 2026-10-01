<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class TotStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * The same field totalled twice — sum AND avg of the value, the docs' own
 * example. Until 1.43.0 the average overwrote the sum, and the footer showed
 * it under "Total" as if it were the total.
 */
class CrudTotalizersSameFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('pt_BR');

        CrudConfig::create(['model' => TotStub::class, 'route' => '', 'config' => [
            'crud' => TotStub::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Total', 'colsTipo' => 'number', 'colsHelper' => 'currencyFormat'],
            ],
            'totalizadores' => ['enabled' => true, 'columns' => [
                ['field' => 'amount', 'aggregate' => 'sum'],
                ['field' => 'amount', 'aggregate' => 'avg'],
                ['field' => 'amount', 'aggregate' => 'count'],
            ]],
            'exportConfig' => ['enabled' => true, 'maxRows' => 5000],
            'permissions' => [],
        ]]);

        foreach ([10, 20, 60] as $amount) {
            TotStub::create(['name' => 'N'.$amount, 'status' => 'open', 'amount' => $amount]);
        }
    }

    #[Test]
    public function every_aggregate_is_kept_and_the_field_map_keeps_the_first(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => TotStub::class])->instance();

        $this->assertSame(['sum', 'avg', 'count'], array_column($crud->totalizadoresItems(), 'aggregate'));
        $this->assertEqualsWithDelta(90, (float) $crud->totalizadoresData()['amount'], 0.001, 'A media sobrescreveu a soma.');
    }

    #[Test]
    public function the_footer_shows_each_value_named_and_a_count_is_not_money(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => TotStub::class])->html();
        $foot = substr($html, (int) strpos($html, '<tfoot'));

        $this->assertStringContainsString('90,00', $foot);
        $this->assertStringContainsString('30,00', $foot);
        $this->assertStringContainsString(__('ptah::ui.export_sum'), $foot);
        $this->assertStringContainsString(__('ptah::ui.export_avg'), $foot);
        $this->assertStringNotContainsString(__('ptah::ui.currency_prefix').'3,00', $foot, 'A contagem saiu como dinheiro.');
    }

    #[Test]
    public function the_print_screen_carries_every_value_too(): void
    {
        $url = null;
        Livewire::test(BaseCrud::class, ['model' => TotStub::class])->call('printView')
            ->assertDispatched('ptah:open-print', function ($e, $p) use (&$url) {
                $url = $p['url'] ?? null;

                return $url !== null;
            });

        $payload = Cache::get('ptah:print:'.basename((string) parse_url((string) $url, PHP_URL_PATH)));
        $total = collect($payload['columns'])->firstWhere('field', 'amount')['total'];

        $this->assertStringContainsString('90,00', $total);
        $this->assertStringContainsString('30,00', $total);
        $this->assertStringContainsString(__('ptah::ui.export_count').' 3', $total);
    }

    #[Test]
    public function a_single_total_per_column_looks_as_before(): void
    {
        CrudConfig::where('model', TotStub::class)->first()->update(['config' => array_replace(
            CrudConfig::where('model', TotStub::class)->first()->config,
            ['totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]]],
        )]);

        $html = Livewire::test(BaseCrud::class, ['model' => TotStub::class])->html();
        $foot = substr($html, (int) strpos($html, '<tfoot'));

        $this->assertStringContainsString('90,00', $foot);
        $this->assertStringNotContainsString(__('ptah::ui.export_sum'), $foot, 'Um total so nao precisa de nome.');
    }
}

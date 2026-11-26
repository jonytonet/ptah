<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\MoneyInput;
use Ptah\Tests\TestCase;

class CentsOrder extends Model
{
    protected $table = 'cents_orders';

    protected $guarded = [];
}

/**
 * A filter on a money column kept in cents takes reais (1.62.0).
 *
 * The filter compared what was typed straight with the stored cents: 2160
 * looked for R$ 21,60, and "2.160,00" did not become a number at all.
 */
class CrudCentsFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('cents_orders', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->bigInteger('total');
            $t->timestamps();
        });
        CentsOrder::create(['code' => 'small', 'total' => 150]);
        CentsOrder::create(['code' => 'big', 'total' => 216000]);

        CrudConfig::updateOrCreate(['model' => CentsOrder::class, 'route' => ''], ['config' => [
            'crud' => CentsOrder::class,
            'cols' => [
                ['colsNomeFisico' => 'code', 'colsNomeLogico' => 'Código', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'total', 'colsNomeLogico' => 'Total', 'colsTipo' => 'number', 'colsGravar' => true, 'colsIsFilterable' => true,
                    'colsRenderer' => 'money', 'colsRendererCents' => true],
            ],
            'permissions' => [],
        ]]);
    }

    /** @return list<string> codes shown */
    private function codes(array $filters = [], array $ranges = [], array $advanced = []): array
    {
        // Filters are remembered per user: each case starts clean.
        $crud = Livewire::test(BaseCrud::class, ['model' => CentsOrder::class])->call('clearFilters');
        foreach ($filters as $k => $v) {
            $crud->set("filters.{$k}", $v);
        }
        foreach ($ranges as $k => $v) {
            $crud->set("dateRanges.{$k}", $v);
        }
        if ($advanced !== []) {
            $crud->set('advancedSearchActive', true)->set('advancedSearchFields', $advanced);
        }

        return $crud->instance()->rows->pluck('code')->sort()->values()->all();
    }

    /** @return array<string, array{string, list<string>}> */
    public static function typed(): array
    {
        return [
            '1,50' => ['1,50', ['small']],
            '1.50' => ['1.50', ['small']],
            'R$ 1,50' => ['R$ 1,50', ['small']],
            '2.160,00' => ['2.160,00', ['big']],
            '2160' => ['2160', ['big']],
            '2160.00' => ['2160.00', ['big']],
            'R$ 2.160,00' => ['R$ 2.160,00', ['big']],
            'abc' => ['abc', ['big', 'small']],
        ];
    }

    #[Test]
    #[DataProvider('typed')]
    public function the_filter_reads_reais(string $typed, array $expected): void
    {
        $this->assertSame($expected, $this->codes(['total' => $typed]));
    }

    #[Test]
    public function a_range_in_reais_finds_only_what_is_inside(): void
    {
        $this->assertSame(['small'], $this->codes(ranges: ['total_start' => '1,00', 'total_end' => '2,00']));
        $this->assertSame(['big'], $this->codes(ranges: ['total_start' => '2.000']));
    }

    #[Test]
    public function the_advanced_search_reads_reais(): void
    {
        $this->assertSame(['big'], $this->codes(advanced: [['field' => 'total', 'operator' => '>=', 'value' => '1.000,00', 'logic' => 'AND']]));
    }

    #[Test]
    public function filter_by_this_value_keeps_finding_the_record_and_the_chip_shows_reais(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => CentsOrder::class])
            ->call('filterByCell', CentsOrder::where('code', 'big')->value('id'), 'total');

        $this->assertSame('2160.00', $crud->get('filters.total'));
        $this->assertSame(['big'], $crud->instance()->rows->pluck('code')->all());
    }

    #[Test]
    public function the_reader_follows_the_rules(): void
    {
        foreach ([
            '2.160,00' => 216000, '2,160.00' => 216000, '1,50' => 150, '2160,5' => 216050,
            '1.50' => 150, '2160.5' => 216050, '2.160' => 216000, '1.234.567' => 123456700,
            '150' => 15000, '0,29' => 29, '0.29' => 29, 'R$ 1.234,56' => 123456, '-3,10' => -310,
            '1,005' => 101, '€ 10' => null,
        ] as $raw => $cents) {
            $this->assertSame($cents, MoneyInput::toCents((string) $raw), (string) $raw);
        }
        $this->assertSame(1000, MoneyInput::toCents('€ 10', '€'));
        foreach (['', 'abc', '1,2,a', ',', '.'] as $bad) {
            $this->assertNull(MoneyInput::toCents($bad), $bad);
        }
        $this->assertSame('2160.00', MoneyInput::fromCents(216000));
        $this->assertSame('0.05', MoneyInput::fromCents(5));
    }
}

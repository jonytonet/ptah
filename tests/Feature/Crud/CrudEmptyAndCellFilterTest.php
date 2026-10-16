<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class EmptyFilterCustomer extends Model
{
    protected $table = 'empty_filter_customers';

    protected $guarded = [];
}

/**
 * 1.46.0: "clientes sem telefone" — "empty" also catches '' on text columns
 * (never on numbers), selects get "(empty)" / "(filled)", and the cell's
 * "filter by this value" button filters by the RECORD's value.
 */
class CrudEmptyAndCellFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empty_filter_customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('status')->nullable();
            $t->integer('visits')->nullable();
            $t->date('since')->nullable();
            $t->string('secret_note')->nullable();
            $t->timestamps();
        });

        foreach ([
            ['Ana', '1111', 'active', 3, '2026-01-10'],
            ['Bia', null, 'inactive', 0, '2026-02-20'],
            ['Caio', '', null, null, null],
            ['Duda', '2222', 'active', 5, '2026-01-10'],
        ] as [$name, $phone, $status, $visits, $since]) {
            EmptyFilterCustomer::create(compact('name', 'phone', 'status', 'visits', 'since'));
        }

        CrudConfig::create(['model' => EmptyFilterCustomer::class, 'route' => '', 'config' => [
            'crud' => EmptyFilterCustomer::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'phone', 'colsNomeLogico' => 'Telefone', 'colsTipo' => 'text', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'select', 'colsIsFilterable' => 'S',
                    'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive']],
                ['colsNomeFisico' => 'visits', 'colsNomeLogico' => 'Visitas', 'colsTipo' => 'number', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'since', 'colsNomeLogico' => 'Desde', 'colsTipo' => 'date', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'secret_note', 'colsNomeLogico' => 'Nota', 'colsTipo' => 'text'],
            ],
            'permissions' => [],
        ]]);
    }

    private function names($crud): array
    {
        $names = collect($crud->viewData('rows')->items())->pluck('name')->all();
        sort($names);

        return $names;
    }

    /** Each scenario from a clean panel: the filters are kept in the user's preferences. */
    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => EmptyFilterCustomer::class])->call('clearFilters');
    }

    #[Test]
    public function empty_on_a_text_column_catches_null_and_blank(): void
    {
        $this->assertSame(['Bia', 'Caio'], $this->names($this->crud()->set('filterOperators.phone', 'IS NULL')), 'O telefone gravado como "" ficou de fora.');
        $this->assertSame(['Ana', 'Duda'], $this->names($this->crud()->set('filterOperators.phone', 'IS NOT NULL')));
    }

    #[Test]
    public function empty_on_a_number_column_never_matches_zero(): void
    {
        // Comparar com '' viraria 0 no MySQL: em numero, "vazio" e so NULL.
        $this->assertSame(['Caio'], $this->names($this->crud()->set('filterOperators.visits', 'IS NULL')));
    }

    #[Test]
    public function a_select_can_filter_the_ones_without_a_value(): void
    {
        $crud = $this->crud()->set('filters.status', BaseCrud::FILTER_EMPTY);
        $this->assertSame(['Caio'], $this->names($crud));
        $this->assertSame(__('ptah::ui.filters_op_is_null'), collect($crud->get('textFilter'))->firstWhere('field', 'status')['value']);

        $this->assertSame(['Ana', 'Bia', 'Duda'], $this->names($this->crud()->set('filters.status', BaseCrud::FILTER_FILLED)));
        $this->assertStringContainsString(BaseCrud::FILTER_EMPTY, $this->crud()->set('showFilters', true)->html());
    }

    #[Test]
    public function a_value_picked_from_a_select_is_exact(): void
    {
        // Com LIKE, "Ativo" (active) trazia tambem os "Inativo" (inactive).
        $this->assertSame(['Ana', 'Duda'], $this->names($this->crud()->set('filters.status', 'active')));
    }

    #[Test]
    public function an_empty_operator_shows_up_as_a_filter_badge(): void
    {
        $crud = $this->crud()->set('filterOperators.phone', 'IS NULL')->call('buildTextFilter');

        $this->assertSame(__('ptah::ui.filters_op_is_null'), collect($crud->get('textFilter'))->firstWhere('field', 'phone')['value']);
    }

    #[Test]
    public function filter_by_cell_uses_the_records_value(): void
    {
        $ana = EmptyFilterCustomer::where('name', 'Ana')->first();
        $caio = EmptyFilterCustomer::where('name', 'Caio')->first();

        $this->assertSame(['Ana', 'Duda'], $this->names($this->crud()->call('filterByCell', $ana->id, 'status')), 'Select: o valor gravado (active), nao o rotulo.');
        $this->assertSame(['Ana'], $this->names($this->crud()->call('filterByCell', $ana->id, 'visits')));
        $this->assertSame(['Ana', 'Duda'], $this->names($this->crud()->call('filterByCell', $ana->id, 'since')), 'Data: o dia inteiro.');
        $this->assertSame(['Bia', 'Caio'], $this->names($this->crud()->call('filterByCell', $caio->id, 'phone')), 'Sem valor: filtra os vazios.');
    }

    #[Test]
    public function filter_by_cell_ignores_a_column_that_is_not_filterable(): void
    {
        $ana = EmptyFilterCustomer::where('name', 'Ana')->first();

        $crud = $this->crud()->call('filterByCell', $ana->id, 'secret_note');

        $this->assertSame([], array_filter($crud->get('filters')));
        $this->assertCount(4, $crud->viewData('rows')->items());
    }

    #[Test]
    public function the_cells_carry_what_the_filter_button_needs(): void
    {
        $html = $this->crud()->html();

        $this->assertStringContainsString('data-ptah-filter-field="phone"', $html);
        $this->assertStringNotContainsString('data-ptah-filter-field="secret_note"', $html);
        $this->assertMatchesRegularExpression('/data-ptah-row="\d+"/', $html);
    }
}

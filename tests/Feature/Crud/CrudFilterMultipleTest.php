<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class MultiFilterStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Filters by more than one value (1.63.0, `colsFilterMultiple`): a select or
 * searchdropdown column whose filter keeps a list and queries with IN / NOT IN.
 */
class CrudFilterMultipleTest extends TestCase
{
    private function configure(bool $multiple = true): void
    {
        CrudConfig::create([
            'model' => MultiFilterStub::class,
            'route' => '',
            'config' => [
                'crud' => MultiFilterStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        'colsNomeFisico' => 'status',
                        'colsNomeLogico' => 'Status',
                        'colsTipo' => 'select',
                        'colsGravar' => true,
                        'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive', 'Pendente' => 'pending'],
                        'colsIsFilterable' => 'S',
                        'colsFilterMultiple' => $multiple,
                    ],
                    [
                        'colsNomeFisico' => 'amount',
                        'colsNomeLogico' => 'Item',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => MultiFilterStub::class,
                        'colsSDLabel' => 'name',
                        'colsSDValor' => 'id',
                        'colsIsFilterable' => 'S',
                        'colsFilterMultiple' => $multiple,
                    ],
                ],
                'permissions' => [],
            ],
        ]);

        MultiFilterStub::create(['name' => 'Apple', 'status' => 'active', 'amount' => 1]);
        MultiFilterStub::create(['name' => 'Banana', 'status' => 'inactive', 'amount' => 2]);
        MultiFilterStub::create(['name' => 'Cherry', 'status' => 'pending', 'amount' => 3]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => MultiFilterStub::class]);
    }

    /** @return list<string> */
    private function names($component): array
    {
        return collect($component->instance()->rows()->items())->pluck('name')->sort()->values()->all();
    }

    #[Test]
    public function a_multiple_searchdropdown_keeps_every_value_chosen_and_filters_by_in(): void
    {
        $this->configure();

        $c = $this->crud()
            ->set('showFilters', true)
            ->call('selectFilterDropdownOption', 'amount', 1, 'Um')
            ->call('selectFilterDropdownOption', 'amount', 3, 'Três')
            ->call('selectFilterDropdownOption', 'amount', 3, 'Três');

        $this->assertSame(['1', '3'], $c->get('filters')['amount']);
        $this->assertSame(['1' => 'Um', '3' => 'Três'], $c->get('sdFilterLabels')['amount']);
        $this->assertSame(['Apple', 'Cherry'], $this->names($c));
        $c->assertSee('Um')->assertSee('Três')->assertSeeHtml('data-ptah-filter-multi');
    }

    #[Test]
    public function the_different_operator_turns_the_list_into_not_in(): void
    {
        $this->configure();

        $c = $this->crud()
            ->set('filterOperators.amount', '!=')
            ->call('selectFilterDropdownOption', 'amount', 1, 'Um')
            ->call('selectFilterDropdownOption', 'amount', 2, 'Dois');

        $this->assertSame(['Cherry'], $this->names($c));
    }

    #[Test]
    public function removing_one_value_keeps_the_others_and_the_last_clears_the_filter(): void
    {
        $this->configure();

        $c = $this->crud()
            ->call('selectFilterDropdownOption', 'amount', 1, 'Um')
            ->call('selectFilterDropdownOption', 'amount', 2, 'Dois')
            ->call('removeFilterValue', 'amount', '1');

        $this->assertSame(['2'], $c->get('filters')['amount']);
        $this->assertSame(['2' => 'Dois'], $c->get('sdFilterLabels')['amount']);
        $this->assertSame(['Banana'], $this->names($c));

        $c->call('removeFilterValue', 'amount', '2');
        $this->assertArrayNotHasKey('amount', $c->get('filters'));
        $this->assertArrayNotHasKey('amount', $c->get('sdFilterLabels'));
        $this->assertCount(3, $this->names($c));
    }

    #[Test]
    public function a_multiple_select_toggles_its_options_and_ignores_unknown_values(): void
    {
        $this->configure();

        $c = $this->crud()
            ->set('showFilters', true)
            ->call('toggleFilterValue', 'status', 'active')
            ->call('toggleFilterValue', 'status', 'pending')
            ->call('toggleFilterValue', 'status', 'invented');

        $this->assertSame(['active', 'pending'], $c->get('filters')['status']);
        $this->assertSame(['Apple', 'Cherry'], $this->names($c));
        $c->assertSeeHtml('aria-pressed="true"');

        $c->call('toggleFilterValue', 'status', 'active');
        $this->assertSame(['pending'], $c->get('filters')['status']);
        $this->assertSame(['Cherry'], $this->names($c));
    }

    #[Test]
    public function the_chip_of_the_toolbar_lists_every_label(): void
    {
        $this->configure();

        $c = $this->crud()
            ->call('toggleFilterValue', 'status', 'active')
            ->call('toggleFilterValue', 'status', 'inactive');

        $c->assertSee('Ativo, Inativo');
    }

    #[Test]
    public function a_column_that_is_not_multiple_takes_only_the_first_value_of_a_list(): void
    {
        $this->configure(multiple: false);

        $c = $this->crud()->set('filters.status', ['inactive', 'active']);

        $this->assertSame(['Banana'], $this->names($c));

        // And the toggle does nothing there.
        $c->call('toggleFilterValue', 'status', 'pending');
        $this->assertSame(['inactive', 'active'], $c->get('filters')['status']);
    }

    #[Test]
    public function a_list_is_capped(): void
    {
        $this->configure();

        $c = $this->crud();
        foreach (range(1, BaseCrud::FILTER_LIST_MAX + 5) as $i) {
            $c->call('selectFilterDropdownOption', 'amount', $i, "L{$i}");
        }

        $this->assertCount(BaseCrud::FILTER_LIST_MAX, $c->get('filters')['amount']);
    }
}

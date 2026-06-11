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

class QueryStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers the HasCrudQuery pipeline through the real BaseCrud component:
 * global search, column sort, per-page, form filters and quick date filters.
 */
class CrudQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => QueryStub::class,
            'route' => '',
            'config' => [
                'crud' => QueryStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true, 'colsIsFilterable' => true],
                ],
                'permissions' => [],
            ],
        ]);

        QueryStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 10]);
        QueryStub::create(['name' => 'Beta', 'status' => 'inactive', 'amount' => 20]);
        QueryStub::create(['name' => 'Gamma', 'status' => 'active', 'amount' => 30]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => QueryStub::class]);
    }

    #[Test]
    public function global_search_narrows_the_listing(): void
    {
        $this->crud()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->set('search', 'alp')
            ->assertSee('Alpha')
            ->assertDontSee('Beta')
            ->assertDontSee('Gamma');
    }

    #[Test]
    public function clearing_the_search_restores_all_rows(): void
    {
        $this->crud()
            ->set('search', 'alp')
            ->assertDontSee('Beta')
            ->set('search', '')
            ->assertSee('Beta')
            ->assertSee('Gamma');
    }

    #[Test]
    public function sorting_by_a_column_orders_the_rows(): void
    {
        $component = $this->crud()
            ->set('sort', 'amount')
            ->set('direction', 'ASC');

        $html = $component->html();

        // Alpha (10) must appear before Gamma (30) in ascending order.
        $this->assertLessThan(
            strpos($html, 'Gamma'),
            strpos($html, 'Alpha'),
            'ASC sort by amount must list Alpha before Gamma',
        );

        $component->set('direction', 'DESC');
        $html = $component->html();

        $this->assertLessThan(
            strpos($html, 'Alpha'),
            strpos($html, 'Gamma'),
            'DESC sort by amount must list Gamma before Alpha',
        );
    }

    #[Test]
    public function form_filter_restricts_by_field_value(): void
    {
        $this->crud()
            ->set('filters.status', 'inactive')
            ->assertSee('Beta')
            ->assertDontSee('Alpha')
            ->assertDontSee('Gamma');
    }

    #[Test]
    public function numeric_filter_with_operator_applies(): void
    {
        $this->crud()
            ->set('filterOperators.amount', '>')
            ->set('filters.amount', '15')
            ->assertSee('Beta')
            ->assertSee('Gamma')
            ->assertDontSee('Alpha');
    }

    #[Test]
    public function per_page_limits_the_listing(): void
    {
        $component = $this->crud()->set('perPage', 2);

        $rows = $component->viewData('rows');

        $this->assertSame(2, $rows->count());
        $this->assertSame(3, $rows->total());
    }

    #[Test]
    public function quick_date_filter_today_keeps_rows_created_now(): void
    {
        $this->crud()
            ->set('quickDateFilter', 'today')
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Gamma');
    }
}

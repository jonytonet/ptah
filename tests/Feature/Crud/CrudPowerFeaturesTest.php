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

class PowerStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers the ScriptCase-inspired power features: group break (headers +
 * subtotals), master/detail (locked filters + row expansion) and the
 * table ⇄ cards view toggle.
 */
class CrudPowerFeaturesTest extends TestCase
{
    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => PowerStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => PowerStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true],
                ],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function seedRows(): void
    {
        PowerStub::create(['name' => 'B-row', 'status' => 'beta', 'amount' => 10]);
        PowerStub::create(['name' => 'A-row', 'status' => 'alpha', 'amount' => 1]);
        PowerStub::create(['name' => 'B2-row', 'status' => 'beta', 'amount' => 20]);
        PowerStub::create(['name' => 'A2-row', 'status' => 'alpha', 'amount' => 2]);
    }

    // ── Group break (quebra) ──────────────────────────────────────────────────

    #[Test]
    public function group_break_makes_the_break_field_the_primary_sort(): void
    {
        $this->makeConfig(['groupBreak' => 'status']);
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => PowerStub::class]);

        $statuses = collect($component->instance()->rows()->items())->pluck('status')->all();

        $this->assertSame(['alpha', 'alpha', 'beta', 'beta'], $statuses);
    }

    #[Test]
    public function group_break_renders_headers_and_subtotals(): void
    {
        $this->makeConfig([
            'groupBreak' => 'status',
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'type' => 'sum']]],
        ]);
        $this->seedRows();

        Livewire::test(BaseCrud::class, ['model' => PowerStub::class])
            ->assertSeeHtml('ptah-c-break_row')
            ->assertSeeHtml('ptah-c-break_subtotal')
            ->assertSee('alpha')
            ->assertSee('beta');
    }

    #[Test]
    public function unsafe_group_break_field_is_ignored(): void
    {
        $this->makeConfig(['groupBreak' => 'status) OR 1=1 --']);
        $this->seedRows();

        // Must not throw — guard discards the unsafe identifier.
        Livewire::test(BaseCrud::class, ['model' => PowerStub::class])->assertOk();
    }

    // ── Master/Detail ─────────────────────────────────────────────────────────

    #[Test]
    public function locked_filters_restrict_rows_and_survive_clear_filters(): void
    {
        $this->makeConfig();
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, [
            'model' => PowerStub::class,
            'lockedFilters' => ['status' => 'alpha'],
        ]);

        $this->assertSame(2, $component->instance()->rows()->total());

        // clearFilters must NOT unlock the parent constraint.
        $component->call('clearFilters');
        $this->assertSame(2, $component->instance()->rows()->total());
    }

    #[Test]
    public function toggle_detail_expands_and_collapses_a_row(): void
    {
        $this->makeConfig([
            'masterDetail' => [['model' => PowerStub::class, 'foreignKey' => 'amount', 'title' => 'Children']],
        ]);
        $this->seedRows();

        $id = PowerStub::first()->id;

        $component = Livewire::test(BaseCrud::class, ['model' => PowerStub::class])
            ->call('toggleDetail', $id)
            ->assertSet('expandedRows', [$id]);

        // Expanded row renders the nested detail section with its title.
        $component->assertSee('Children');

        $component->call('toggleDetail', $id)->assertSet('expandedRows', []);
    }

    // ── View mode (table ⇄ cards) ─────────────────────────────────────────────

    #[Test]
    public function view_mode_toggles_between_table_and_cards(): void
    {
        $this->makeConfig();
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => PowerStub::class])
            ->assertSet('viewMode', 'table')
            ->call('setViewMode', 'cards')
            ->assertSet('viewMode', 'cards');

        // Card view renders the definition-list layout instead of the table body.
        $component->assertSee('A-row');

        // Invalid modes are rejected.
        $component->call('setViewMode', 'bogus')->assertSet('viewMode', 'cards');
    }
}

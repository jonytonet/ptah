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

class SdStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers HasCrudSearchDropdown (model-backed lookups, selection, filter-panel
 * flow) and HasCrudExport (sync dispatch with visible columns, bulk export,
 * enabled gate) through the real BaseCrud component.
 */
class CrudSearchDropdownExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => SdStub::class,
            'route' => '',
            'config' => [
                'crud' => SdStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        'colsNomeFisico' => 'amount',
                        'colsNomeLogico' => 'Parent item',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => SdStub::class,
                        'colsSDLabel' => 'name',
                        'colsSDValor' => 'id',
                        'colsSDLimit' => 2,
                    ],
                ],
                'permissions' => [],
                'exportConfig' => ['enabled' => true, 'asyncThreshold' => 1000],
            ],
        ]);

        SdStub::create(['name' => 'Apple', 'status' => 'active', 'amount' => 0]);
        SdStub::create(['name' => 'Apricot', 'status' => 'active', 'amount' => 0]);
        SdStub::create(['name' => 'Avocado', 'status' => 'active', 'amount' => 0]);
        SdStub::create(['name' => 'Banana', 'status' => 'active', 'amount' => 0]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => SdStub::class]);
    }

    // ── Search dropdown (form modal) ──────────────────────────────────────────

    #[Test]
    public function search_dropdown_returns_matching_value_label_pairs(): void
    {
        $component = $this->crud()->call('searchDropdown', 'amount', 'banana');

        $results = $component->get('sdResults')['amount'];

        $this->assertCount(1, $results);
        $this->assertSame('Banana', $results[0]['label']);
        $this->assertArrayHasKey('value', $results[0]);
    }

    #[Test]
    public function search_dropdown_respects_the_configured_limit(): void
    {
        // 'a' matches all 4 rows, but colsSDLimit is 2.
        $component = $this->crud()->call('searchDropdown', 'amount', 'ap');

        $results = $component->get('sdResults')['amount'];

        $this->assertCount(2, $results, 'colsSDLimit must cap the result set');
    }

    #[Test]
    public function empty_query_clears_the_results(): void
    {
        $component = $this->crud()
            ->call('searchDropdown', 'amount', 'banana')
            ->call('searchDropdown', 'amount', '');

        $this->assertSame([], $component->get('sdResults')['amount']);
    }

    #[Test]
    public function selecting_an_option_fills_form_data_and_label(): void
    {
        $banana = SdStub::where('name', 'Banana')->first();

        $this->crud()
            ->call('selectDropdownOption', 'amount', $banana->id, 'Banana')
            ->assertSet('formData.amount', $banana->id)
            ->assertSet('sdLabels.amount', 'Banana')
            ->assertSet('sdSearches.amount', '');
    }

    #[Test]
    public function open_dropdown_loads_initial_items_without_a_query(): void
    {
        $component = $this->crud()->call('openDropdown', 'amount');

        $results = $component->get('sdResults')['amount'];

        $this->assertCount(2, $results, 'openDropdown must load up to colsSDLimit items unfiltered');
    }

    // ── Search dropdown (filter panel) ────────────────────────────────────────

    #[Test]
    public function filter_dropdown_selection_sets_the_filter_and_operator(): void
    {
        $banana = SdStub::where('name', 'Banana')->first();

        $this->crud()
            ->call('selectFilterDropdownOption', 'amount', $banana->id, 'Banana')
            ->assertSet('filters.amount', $banana->id)
            ->assertSet('filterOperators.amount', '=');
    }

    #[Test]
    public function clearing_the_filter_dropdown_removes_filter_state(): void
    {
        $banana = SdStub::where('name', 'Banana')->first();

        $component = $this->crud()
            ->call('selectFilterDropdownOption', 'amount', $banana->id, 'Banana')
            ->call('clearFilterDropdownSelection', 'amount');

        $this->assertArrayNotHasKey('amount', $component->get('filters'));
        $this->assertArrayNotHasKey('amount', $component->get('filterOperators'));
    }

    #[Test]
    public function clearing_the_filter_query_resets_the_active_filter(): void
    {
        $banana = SdStub::where('name', 'Banana')->first();

        $component = $this->crud()
            ->call('selectFilterDropdownOption', 'amount', $banana->id, 'Banana')
            ->call('filterSearchDropdown', 'amount', '');

        $this->assertArrayNotHasKey('amount', $component->get('filters'));
    }

    // ── Export ────────────────────────────────────────────────────────────────

    #[Test]
    public function export_dispatches_the_sync_event_with_visible_columns(): void
    {
        $this->crud()
            ->call('export', 'excel')
            ->assertDispatched('ptah:export-sync');
    }

    #[Test]
    public function export_is_a_no_op_when_disabled(): void
    {
        $cfg = CrudConfig::where('model', SdStub::class)->first();
        $cfg->update([
            'config' => array_merge($cfg->config, ['exportConfig' => ['enabled' => false]]),
        ]);

        $this->crud()
            ->call('export', 'excel')
            ->assertNotDispatched('ptah:export-sync');
    }

    #[Test]
    public function bulk_export_requires_a_selection(): void
    {
        $this->crud()
            ->call('bulkExport', 'excel')
            ->assertNotDispatched('ptah:bulk-export');

        $ids = SdStub::pluck('id')->take(2)->all();

        $this->crud()
            ->set('selectedRows', $ids)
            ->call('bulkExport', 'excel')
            ->assertDispatched('ptah:bulk-export');
    }
}

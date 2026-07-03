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
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        // Cascading: options filtered by the value of `status`.
                        'colsNomeFisico' => 'cascade_child',
                        'colsNomeLogico' => 'Child',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => SdStub::class,
                        'colsSDLabel' => 'name',
                        'colsSDValor' => 'id',
                        'colsSDDependsOn' => 'status',
                        'colsSDFilterColumn' => 'status',
                    ],
                    [
                        // Third level: depends on cascade_child (reset recursion).
                        'colsNomeFisico' => 'cascade_grand',
                        'colsNomeLogico' => 'Grandchild',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => SdStub::class,
                        'colsSDLabel' => 'name',
                        'colsSDDependsOn' => 'cascade_child',
                    ],
                ],
                'permissions' => [],
                'exportConfig' => ['enabled' => true, 'asyncThreshold' => 1000],
            ],
        ]);

        SdStub::create(['name' => 'Apple', 'status' => 'active', 'amount' => 0]);
        SdStub::create(['name' => 'Apricot', 'status' => 'active', 'amount' => 0]);
        SdStub::create(['name' => 'Avocado', 'status' => 'inactive', 'amount' => 0]);
        SdStub::create(['name' => 'Banana', 'status' => 'inactive', 'amount' => 0]);
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

    // ── Cascading (dependent) dropdowns ───────────────────────────────────────

    #[Test]
    public function cascading_child_is_gated_until_the_parent_has_a_value(): void
    {
        $component = $this->crud()->call('searchDropdown', 'cascade_child', 'a');

        $this->assertSame(
            [],
            $component->get('sdResults')['cascade_child'],
            'Child dropdown must return nothing while the parent field is empty',
        );
    }

    #[Test]
    public function cascading_child_options_are_filtered_by_the_parent_value(): void
    {
        $component = $this->crud()
            ->set('formData.status', 'inactive')
            ->call('searchDropdown', 'cascade_child', 'a');

        $labels = array_column($component->get('sdResults')['cascade_child'], 'label');

        // Only inactive rows (Avocado, Banana) — never the active Apple/Apricot.
        $this->assertEqualsCanonicalizing(['Avocado', 'Banana'], $labels);
    }

    #[Test]
    public function changing_the_parent_resets_child_and_grandchild(): void
    {
        $component = $this->crud()
            ->set('formData.status', 'active')
            ->set('formData.cascade_child', 10)
            ->set('formData.cascade_grand', 20)
            // New parent value → entire descendant chain must be cleared.
            ->set('formData.status', 'inactive');

        $formData = $component->get('formData');

        $this->assertArrayNotHasKey('cascade_child', $formData, 'Child must reset when the parent changes');
        $this->assertArrayNotHasKey('cascade_grand', $formData, 'Reset must cascade to the grandchild');
        $this->assertSame('inactive', $formData['status']);
    }

    #[Test]
    public function selecting_a_dropdown_option_resets_its_dependents(): void
    {
        $apple = SdStub::where('name', 'Apple')->first();

        $component = $this->crud()
            ->set('formData.cascade_grand', 99)
            // cascade_child is the parent of cascade_grand; selecting it must clear the grandchild.
            ->call('selectDropdownOption', 'cascade_child', $apple->id, 'Apple');

        $this->assertArrayNotHasKey('cascade_grand', $component->get('formData'));
        $this->assertSame($apple->id, $component->get('formData')['cascade_child']);
    }

    #[Test]
    public function filter_panel_cascade_resets_dependent_filters(): void
    {
        $apple = SdStub::where('name', 'Apple')->first();

        $component = $this->crud()
            // Simulate an active child filter, then select a new parent filter value.
            ->call('selectFilterDropdownOption', 'cascade_child', $apple->id, 'Apple')
            ->call('selectFilterDropdownOption', 'status', 'inactive', 'Inactive');

        $this->assertArrayNotHasKey(
            'cascade_child',
            $component->get('filters'),
            'Child filter must reset when the parent filter changes',
        );
    }

    // ── Export ────────────────────────────────────────────────────────────────

    #[Test]
    public function export_dispatches_the_download_event(): void
    {
        $this->crud()
            ->call('export', 'excel')
            ->assertDispatched('ptah:export-download');
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
            ->assertNotDispatched('ptah:export-download');
    }

    #[Test]
    public function bulk_export_requires_a_selection(): void
    {
        $this->crud()
            ->call('bulkExport', 'excel')
            ->assertNotDispatched('ptah:export-download');

        $ids = SdStub::pluck('id')->take(2)->all();

        $this->crud()
            ->set('selectedRows', $ids)
            ->call('bulkExport', 'excel')
            ->assertDispatched('ptah:export-download');
    }
}

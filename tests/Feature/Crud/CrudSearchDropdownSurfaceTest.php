<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class SdSurfaceStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(self::class, 'category_id');
    }
}

/**
 * Covers the full BaseCrud searchdropdown configuration surface added by this
 * feature: colsSDInitWithData, colsSDLabelTwo/Three, colsSDMaskOne/Two/Three,
 * colsSDArraySearch, colsSDFilters (all three accepted shapes) and
 * colsSDPlaceholder — via HasCrudSearchDropdown::sdSettings() and
 * resolveSearchDropdownResults().
 */
class CrudSearchDropdownSurfaceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $sdExtra  Extra keys merged into the
     *                                         "target" searchdropdown column.
     */
    private function makeConfig(array $sdExtra): void
    {
        CrudConfig::updateOrCreate(
            ['model' => SdSurfaceStub::class, 'route' => ''],
            ['config' => [
                'crud' => SdSurfaceStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    array_merge([
                        'colsNomeFisico' => 'category_id',
                        'colsNomeLogico' => 'Target',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => SdSurfaceStub::class,
                        'colsSDLabel' => 'name',
                        'colsSDValor' => 'id',
                    ], $sdExtra),
                ],
                'permissions' => [],
            ]],
        );
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => SdSurfaceStub::class]);
    }

    // ── initWithData ─────────────────────────────────────────────────────────

    #[Test]
    public function init_with_data_absent_defaults_to_loading_the_list_on_open(): void
    {
        $this->makeConfig([]);
        SdSurfaceStub::create(['name' => 'Alpha']);

        $results = $this->crud()->call('openDropdown', 'category_id')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
    }

    #[Test]
    public function init_with_data_true_loads_the_list_on_open(): void
    {
        $this->makeConfig(['colsSDInitWithData' => true]);
        SdSurfaceStub::create(['name' => 'Alpha']);

        $results = $this->crud()->call('openDropdown', 'category_id')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
    }

    #[Test]
    public function init_with_data_false_and_empty_term_returns_no_results_on_open(): void
    {
        $this->makeConfig(['colsSDInitWithData' => false]);
        SdSurfaceStub::create(['name' => 'Alpha']);

        $results = $this->crud()->call('openDropdown', 'category_id')->get('sdResults')['category_id'];

        $this->assertSame([], $results);
    }

    #[Test]
    public function init_with_data_false_but_a_term_already_typed_reactivates_open(): void
    {
        $this->makeConfig(['colsSDInitWithData' => false]);
        SdSurfaceStub::create(['name' => 'Alpha']);

        $component = $this->crud()
            ->call('searchDropdown', 'category_id', 'alp')
            ->call('openDropdown', 'category_id');

        // openDropdown short-circuits when sdResults already has entries —
        // the searchDropdown() call above already populated it, proving the
        // term (not initWithData) governs once one exists.
        $results = $component->get('sdResults')['category_id'];
        $this->assertCount(1, $results);
    }

    // ── labelTwo / labelThree ────────────────────────────────────────────────

    #[Test]
    public function label_two_and_three_are_included_only_when_configured(): void
    {
        $this->makeConfig([
            'colsSDLabelTwo' => 'status',
            'colsSDLabelThree' => 'amount',
        ]);
        SdSurfaceStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 42]);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'alpha')->get('sdResults')['category_id'];

        $this->assertSame('active', $results[0]['labelTwo']);
        // No colsSDMaskThree configured (defaultMask) — raw, untouched type.
        $this->assertSame(42, $results[0]['labelThree']);
    }

    // ── Masks ────────────────────────────────────────────────────────────────

    #[Test]
    public function cnpj_mask_formats_the_label(): void
    {
        $this->makeConfig(['colsSDMaskOne' => 'cnpj']);
        SdSurfaceStub::create(['name' => '11222333000181']);

        $results = $this->crud()->call('searchDropdown', 'category_id', '1122')->get('sdResults')['category_id'];

        $this->assertSame('11.222.333/0001-81', $results[0]['label']);
    }

    #[Test]
    public function cpf_mask_formats_the_label(): void
    {
        $this->makeConfig(['colsSDMaskOne' => 'cpf']);
        SdSurfaceStub::create(['name' => '12345678909']);

        $results = $this->crud()->call('searchDropdown', 'category_id', '1234')->get('sdResults')['category_id'];

        $this->assertSame('123.456.789-09', $results[0]['label']);
    }

    #[Test]
    public function money_mask_formats_the_label(): void
    {
        $this->makeConfig(['colsSDMaskOne' => 'money']);
        SdSurfaceStub::create(['name' => '1234.5']);

        $results = $this->crud()->call('searchDropdown', 'category_id', '1234')->get('sdResults')['category_id'];

        $this->assertSame('R$ 1.234,50', $results[0]['label']);
    }

    #[Test]
    public function phone_mask_formats_the_label(): void
    {
        $this->makeConfig(['colsSDMaskOne' => 'phone']);
        SdSurfaceStub::create(['name' => '11999998888']);

        $results = $this->crud()->call('searchDropdown', 'category_id', '1199')->get('sdResults')['category_id'];

        $this->assertSame('(11) 9 9999-8888', $results[0]['label']);
    }

    #[Test]
    public function date_mask_formats_the_label(): void
    {
        $this->makeConfig(['colsSDMaskOne' => 'date']);
        SdSurfaceStub::create(['name' => '2024-01-15']);

        $results = $this->crud()->call('searchDropdown', 'category_id', '2024')->get('sdResults')['category_id'];

        $this->assertSame('15/01/2024', $results[0]['label']);
    }

    #[Test]
    public function unknown_mask_name_returns_the_raw_value(): void
    {
        $this->makeConfig(['colsSDMaskOne' => 'not-a-real-mask']);
        SdSurfaceStub::create(['name' => 'Alpha']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'alpha')->get('sdResults')['category_id'];

        $this->assertSame('Alpha', $results[0]['label']);
    }

    // ── arraySearch ──────────────────────────────────────────────────────────

    #[Test]
    public function array_search_matches_via_an_extra_column_not_the_label(): void
    {
        $this->makeConfig(['colsSDArraySearch' => 'status']);
        SdSurfaceStub::create(['name' => 'Alpha', 'status' => 'findme-status']);
        SdSurfaceStub::create(['name' => 'Beta', 'status' => 'other']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'findme')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results[0]['label']);
    }

    #[Test]
    public function array_search_silently_drops_an_unsafe_column(): void
    {
        // "status; DROP TABLE items" is rejected by SqlIdentifier::isSafe();
        // "status" (safe) must still work. No exception, no data loss.
        $this->makeConfig(['colsSDArraySearch' => ['status; DROP TABLE items', 'status']]);
        SdSurfaceStub::create(['name' => 'Alpha', 'status' => 'findme-status']);
        SdSurfaceStub::create(['name' => 'Beta', 'status' => 'other']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'findme')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame(2, SdSurfaceStub::count());
    }

    // ── Filters (three accepted shapes) ─────────────────────────────────────

    #[Test]
    public function filters_as_json_string_restrict_the_results(): void
    {
        $this->makeConfig(['colsSDFilters' => '[{"field":"status","value":"active"}]']);
        SdSurfaceStub::create(['name' => 'Alpha', 'status' => 'active']);
        SdSurfaceStub::create(['name' => 'Alphb', 'status' => 'inactive']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'alph')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results[0]['label']);
    }

    #[Test]
    public function filters_as_a_list_of_triples_restrict_the_results(): void
    {
        $this->makeConfig(['colsSDFilters' => [['status', '!=', 'active']]]);
        SdSurfaceStub::create(['name' => 'Alpha', 'status' => 'active']);
        SdSurfaceStub::create(['name' => 'Alphb', 'status' => 'inactive']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'alph')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Alphb', $results[0]['label']);
    }

    #[Test]
    public function filters_as_an_assoc_map_restrict_the_results(): void
    {
        $this->makeConfig(['colsSDFilters' => ['status' => 'active']]);
        SdSurfaceStub::create(['name' => 'Alpha', 'status' => 'active']);
        SdSurfaceStub::create(['name' => 'Alphb', 'status' => 'inactive']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'alph')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results[0]['label']);
    }

    // ── Placeholder ──────────────────────────────────────────────────────────

    #[Test]
    public function custom_placeholder_is_rendered_on_the_field(): void
    {
        $this->makeConfig(['colsSDPlaceholder' => 'Pick a target...']);

        $this->crud()->call('openCreate')->assertSee('Pick a target...');
    }

    // ── Edit-form preload applies colsSDMaskOne (HasCrudForm::preloadSdLabels) ─

    #[Test]
    public function editing_a_record_preloads_a_masked_label(): void
    {
        $this->makeConfig([
            'colsSDMaskOne' => 'cnpj',
            'colsRelacao' => 'target',
            'colsRelacaoExibe' => 'name',
        ]);

        $target = SdSurfaceStub::create(['name' => '11222333000181']);
        $record = SdSurfaceStub::create(['name' => 'Item', 'category_id' => $target->id]);

        $this->crud()
            ->call('openEdit', $record->id)
            ->assertSet('sdLabels.category_id', '11.222.333/0001-81');
    }

    #[Test]
    public function editing_a_record_without_a_mask_preloads_the_raw_label(): void
    {
        $this->makeConfig([
            'colsRelacao' => 'target',
            'colsRelacaoExibe' => 'name',
        ]);

        $target = SdSurfaceStub::create(['name' => 'Alpha']);
        $record = SdSurfaceStub::create(['name' => 'Item', 'category_id' => $target->id]);

        $this->crud()
            ->call('openEdit', $record->id)
            ->assertSet('sdLabels.category_id', 'Alpha');
    }
}

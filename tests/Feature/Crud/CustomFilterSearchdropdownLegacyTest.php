<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class LegacyFilterItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * The `searchdropdown` custom filter never worked (it came in as a draft):
 * the panel called searchDropdown('cf_<field>'), which looks for a COLUMN of
 * that name and returns nothing, and a click would have written the choice
 * into the edit form, not the filter. Since 1.41.5 it is no longer offered,
 * and one already saved renders as the text filter — which filters for real.
 */
class CustomFilterSearchdropdownLegacyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        LegacyFilterItem::create(['name' => 'Parafuso', 'status' => 'open']);
        LegacyFilterItem::create(['name' => 'Porca', 'status' => 'done']);

        CrudConfig::updateOrCreate(['model' => LegacyFilterItem::class, 'route' => ''], ['config' => [
            'crud' => LegacyFilterItem::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true]],
            'customFilters' => [[
                'field' => 'status', 'label' => 'Situação', 'type' => 'searchdropdown', 'colsFilterType' => 'searchdropdown',
                'operator' => '=', 'colsFilterSdTable' => 'statuses',
            ]],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function a_saved_searchdropdown_filter_renders_as_a_text_filter(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => LegacyFilterItem::class])->set('showFilters', true)->html();

        $this->assertStringContainsString('wire:model.live.debounce.400ms="filters.status"', $html);
        $this->assertStringNotContainsString("searchDropdown('cf_status'", $html, 'A busca morta nao pode voltar.');
    }

    #[Test]
    public function and_it_filters_by_the_typed_value(): void
    {
        $rows = Livewire::test(BaseCrud::class, ['model' => LegacyFilterItem::class])
            ->set('filters.status', 'done')
            ->viewData('rows');

        $this->assertSame(['Porca'], collect($rows->items())->pluck('name')->all());
    }

    #[Test]
    public function a_screen_whose_only_filters_are_custom_filters_shows_them(): void
    {
        // O grid dos filtros so renderizava com alguma COLUNA filtravel; esta
        // tela nao tem nenhuma, e o filtro customizado sumia do painel.
        $html = Livewire::test(BaseCrud::class, ['model' => LegacyFilterItem::class])->set('showFilters', true)->html();

        $this->assertStringContainsString('Situação', $html);
    }

    #[Test]
    public function it_is_no_longer_offered_as_a_custom_filter_type(): void
    {
        $this->assertNotContains('searchdropdown', CrudConfigEnums::FILTER_TYPES);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;

class PanelModeItem extends Model
{
    protected $table = 'panel_mode_items';

    protected $guarded = [];
}

/**
 * Where the filters open (1.62.0): PTAH_CRUD_FILTER_PANEL = accordion
 * (default, as before) or modal.
 */
class CrudFilterPanelModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('panel_mode_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        CrudConfig::updateOrCreate(['model' => PanelModeItem::class, 'route' => ''], ['config' => [
            'crud' => PanelModeItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
            ],
            'permissions' => [],
        ]]);
    }

    private function panel(): string
    {
        return Livewire::test(BaseCrud::class, ['model' => PanelModeItem::class])->call('toggleFilters')->html();
    }

    #[Test]
    public function the_accordion_stays_the_default(): void
    {
        $this->assertSame('accordion', config('ptah.crud.filter_panel'));
        $html = $this->panel();

        $this->assertStringContainsString('ptah-c-fp_card', $html);
        $this->assertStringNotContainsString('data-ptah-filter-modal', $html);
    }

    #[Test]
    public function modal_opens_the_same_panel_in_a_dialog(): void
    {
        config(['ptah.crud.filter_panel' => 'modal']);
        $html = $this->panel();

        $this->assertStringContainsString('data-ptah-filter-modal', $html);
        $this->assertMatchesRegularExpression('/role="dialog" aria-modal="true" aria-label="'.preg_quote(e(__('ptah::ui.filters_title')), '/').'"/', $html);
        // The same panel inside: filters and clear all.
        $this->assertStringContainsString('ptah-c-fp_card', $html);
        $this->assertStringContainsString('filters.name', $html);
        $this->assertStringContainsString('data-ptah-filter-modal-close', $html);

        // Closed: nothing of it on the page.
        $closed = Livewire::test(BaseCrud::class, ['model' => PanelModeItem::class])->html();
        $this->assertStringNotContainsString('data-ptah-filter-modal', $closed);
    }

    #[Test]
    public function an_unknown_value_keeps_the_accordion(): void
    {
        config(['ptah.crud.filter_panel' => 'popup']);
        $this->assertStringNotContainsString('data-ptah-filter-modal', $this->panel());
    }

    #[Test]
    public function the_env_switch_reaches_a_config_published_before_the_key_existed(): void
    {
        config(['ptah.crud' => ['responsive_cards' => true]]);
        putenv('PTAH_CRUD_FILTER_PANEL=modal');
        $_ENV['PTAH_CRUD_FILTER_PANEL'] = $_SERVER['PTAH_CRUD_FILTER_PANEL'] = 'modal';

        try {
            $provider = new PtahServiceProvider($this->app);
            (fn () => $this->fillMissingNestedConfig('crud', ['form_draft', 'filter_panel']))->call($provider);
        } finally {
            putenv('PTAH_CRUD_FILTER_PANEL');
            unset($_ENV['PTAH_CRUD_FILTER_PANEL'], $_SERVER['PTAH_CRUD_FILTER_PANEL']);
        }

        $this->assertSame('modal', config('ptah.crud.filter_panel'));
        $source = (string) file_get_contents(dirname(__DIR__, 3).'/src/PtahServiceProvider.php');
        $this->assertStringContainsString("fillMissingNestedConfig('crud', ['form_draft', 'filter_panel'])", $source);
    }

    #[Test]
    public function the_react_and_vue_screens_are_told(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => PanelModeItem::class])->instance();
        $this->assertFalse($crud->filterPanelIsModal());

        config(['ptah.crud.filter_panel' => 'modal']);
        $this->assertTrue($crud->filterPanelIsModal());

        $runtime = (string) file_get_contents(dirname(__DIR__, 3).'/src/Crud/CrudRuntime.php');
        $this->assertStringContainsString("'filterModal' => \$this->filterPanelIsModal()", $runtime);
    }
}

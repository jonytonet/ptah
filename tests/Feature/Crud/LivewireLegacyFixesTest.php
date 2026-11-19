<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigEditor;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class LegacyFixItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * Defects of the Livewire screens found while porting them to React and Vue
 * (1.59.0). Each one is fixed in the engine, so the three stacks get it.
 */
class LivewireLegacyFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The opt-in of the visual editor while the permissions module is off.
        config(['ptah.modules.permissions' => false, 'ptah.crud.config_editor' => true]);
        $this->actingAs(new GenericUser(['id' => 5]));
    }

    /** @param array<string, mixed> $extra */
    private function configure(array $extra = [], array $moreCols = []): void
    {
        CrudConfig::updateOrCreate(['model' => LegacyFixItem::class, 'route' => ''], ['config' => [
            'crud' => LegacyFixItem::class,
            'cols' => array_merge([
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsGravar' => true],
            ], $moreCols),
            'permissions' => [],
        ] + $extra]);
    }

    // ── The "is empty" chip ─────────────────────────────────────────────────

    #[Test]
    public function removing_the_chip_of_an_is_empty_filter_removes_the_filter(): void
    {
        $this->configure();
        LegacyFixItem::create(['name' => 'Alfa', 'status' => 'active', 'category_id' => 3]);
        LegacyFixItem::create(['name' => 'Beta', 'status' => 'active', 'category_id' => null]);

        $screen = Livewire::test(BaseCrud::class, ['model' => LegacyFixItem::class])
            ->set('filterOperators.name', 'IS NULL')
            ->call('buildTextFilter');

        $this->assertSame(['name'], array_column($screen->get('textFilter'), 'field'));

        // The operator stayed behind: the chip came back and the filter went on.
        $screen->call('removeTextFilterBadge', 'name')
            ->assertSet('textFilter', [])
            ->assertSet('filterOperators', []);
    }

    #[Test]
    public function removing_the_chip_of_a_value_keeps_its_operator_choice(): void
    {
        $this->configure();

        Livewire::test(BaseCrud::class, ['model' => LegacyFixItem::class])
            ->set('filters.name', 'Alfa')
            ->set('filterOperators.name', 'LIKE')
            ->call('removeTextFilterBadge', 'name')
            ->assertSet('filterOperators.name', 'LIKE');
    }

    // ── Quick dates ─────────────────────────────────────────────────────────

    /** @return array<string, array{0: mixed}> */
    public static function filterableSpellings(): array
    {
        return ['true' => [true], 'S' => ['S'], '1' => [1], "'1'" => ['1']];
    }

    #[Test]
    #[DataProvider('filterableSpellings')]
    public function the_date_shortcuts_show_for_every_spelling_of_filterable(mixed $filterable): void
    {
        // The engine reads true, 'S', 1 and '1' as "filterable"; the panel
        // compared loosely with 'S', so a column saved as 1 or '1' filtered
        // by date but never offered the shortcuts.
        $this->configure(moreCols: [['colsNomeFisico' => 'created_at', 'colsNomeLogico' => 'Criado em', 'colsTipo' => 'date', 'colsIsFilterable' => $filterable]]);

        Livewire::test(BaseCrud::class, ['model' => LegacyFixItem::class])
            ->set('showFilters', true)
            ->assertSee(trans('ptah::ui.filters_date_shortcuts'));
    }

    #[Test]
    public function the_date_shortcuts_stay_hidden_without_a_filterable_date_column(): void
    {
        $this->configure(moreCols: [['colsNomeFisico' => 'created_at', 'colsNomeLogico' => 'Criado em', 'colsTipo' => 'date', 'colsIsFilterable' => false]]);

        Livewire::test(BaseCrud::class, ['model' => LegacyFixItem::class])
            ->set('showFilters', true)
            ->assertDontSee(trans('ptah::ui.filters_date_shortcuts'));
    }

    // ── A database error on save ────────────────────────────────────────────

    #[Test]
    public function a_database_error_on_save_does_not_show_its_sql(): void
    {
        // `ghost` is no column of the table: the insert fails in the database.
        $this->configure(moreCols: [['colsNomeFisico' => 'ghost', 'colsNomeLogico' => 'Fantasma', 'colsTipo' => 'text', 'colsGravar' => true]]);
        config(['app.debug' => false]);

        $screen = Livewire::test(BaseCrud::class, ['model' => LegacyFixItem::class])
            ->call('openCreate')
            ->set('formData.name', 'Alfa')
            ->set('formData.ghost', 'x')
            ->call('save');

        $message = $screen->get('formErrors')['_general'] ?? '';
        $this->assertSame(trans('ptah::ui.crud_save_error_generic'), $message);
        $this->assertStringNotContainsStringIgnoringCase('ghost', $message);
        $this->assertStringNotContainsStringIgnoringCase('insert', $message);
    }

    #[Test]
    public function in_debug_the_database_error_is_shown_to_the_developer(): void
    {
        $this->configure(moreCols: [['colsNomeFisico' => 'ghost', 'colsNomeLogico' => 'Fantasma', 'colsTipo' => 'text', 'colsGravar' => true]]);
        config(['app.debug' => true]);

        $screen = Livewire::test(BaseCrud::class, ['model' => LegacyFixItem::class])
            ->call('openCreate')
            ->set('formData.name', 'Alfa')
            ->set('formData.ghost', 'x')
            ->call('save');

        $this->assertStringContainsStringIgnoringCase('ghost', $screen->get('formErrors')['_general'] ?? '');
    }

    // ── The visual editor: drag to reorder ──────────────────────────────────

    #[Test]
    public function columns_are_reordered_by_drag_on_a_screen_that_has_row_actions(): void
    {
        $this->configure(moreCols: [
            ['colsNomeFisico' => 'open', 'colsNomeLogico' => 'Abrir', 'colsTipo' => 'action', 'actionType' => 'link', 'actionValue' => '/items/%id%'],
        ]);

        $editor = Livewire::test(CrudConfigEditor::class, ['model' => LegacyFixItem::class])->call('openModal');
        $fields = $editor->get('formEditFields');
        $this->assertSame(['name', 'status', 'amount', 'open'], array_column($fields, 'colsNomeFisico'));

        // What the drag sends: the indexes of the rows shown — the action is not one of them.
        $editor->call('reorderFields', [2, 0, 1]);

        // It did nothing before (the count never matched). The action keeps its slot.
        $this->assertSame(['amount', 'name', 'status', 'open'], array_column($editor->get('formEditFields'), 'colsNomeFisico'));
    }

    #[Test]
    public function a_forged_order_is_ignored(): void
    {
        $this->configure(moreCols: [
            ['colsNomeFisico' => 'open', 'colsNomeLogico' => 'Abrir', 'colsTipo' => 'action', 'actionType' => 'link', 'actionValue' => '/items/%id%'],
        ]);

        $editor = Livewire::test(CrudConfigEditor::class, ['model' => LegacyFixItem::class])->call('openModal');

        foreach ([[0, 0, 1], [0, 1], [0, 1, 3], [0, 1, 99]] as $order) {
            $editor->call('reorderFields', $order);
            $this->assertSame(['name', 'status', 'amount', 'open'], array_column($editor->get('formEditFields'), 'colsNomeFisico'), json_encode($order));
        }
    }

    // ── The gear button ─────────────────────────────────────────────────────

    #[Test]
    public function the_gear_button_follows_the_gate_of_the_editor(): void
    {
        $this->configure();

        // Allowed by the editor's gate (ptah.crud.config_editor): the button is there.
        Livewire::test(CrudConfigEditor::class, ['model' => LegacyFixItem::class])
            ->assertSeeHtml('<span class="ptah-c-btn_label">Config</span>');

        config(['ptah.crud.config_editor' => false]);

        Livewire::test(CrudConfigEditor::class, ['model' => LegacyFixItem::class])
            ->assertDontSeeHtml('<span class="ptah-c-btn_label">Config</span>');
    }
}

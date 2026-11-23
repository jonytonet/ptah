<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class UxItem extends Model
{
    use SoftDeletes;

    protected $table = 'ux_items';

    protected $guarded = [];
}

class UxHardItem extends Model
{
    protected $table = 'ux_hard_items';

    protected $guarded = [];
}

/**
 * Usability of the listing and the form marks (1.61.0), from the UX review.
 */
class CrudListingUsabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        foreach (['ux_items', 'ux_hard_items'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->string('name');
                $t->string('status')->default('open');
                $t->string('note')->nullable();
                $t->timestamps();
                if ($table === 'ux_items') {
                    $t->softDeletes();
                }
            });
        }

        foreach ([UxItem::class, UxHardItem::class] as $model) {
            CrudConfig::updateOrCreate(['model' => $model, 'route' => ''], ['config' => [
                'crud' => $model,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true, 'colsIsFilterable' => true,
                        'colsSelect' => ['Aberto' => 'open', 'Fechado' => 'closed']],
                    ['colsNomeFisico' => 'note', 'colsNomeLogico' => 'Nota', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ]]);
        }

        for ($i = 1; $i <= 30; $i++) {
            UxItem::create(['name' => "Item {$i}", 'status' => $i % 2 ? 'open' : 'closed']);
        }
        UxHardItem::create(['name' => 'Fixo']);
    }

    #[Test]
    public function the_delete_question_does_not_say_cannot_be_undone_when_it_can(): void
    {
        Livewire::test(BaseCrud::class, ['model' => UxItem::class])
            ->call('confirmDelete', 1)
            ->assertSee(__('ptah::ui.delete_message_undoable'))
            ->assertDontSee(__('ptah::ui.delete_message'));

        Livewire::test(BaseCrud::class, ['model' => UxHardItem::class])
            ->call('confirmDelete', 1)
            ->assertSee(__('ptah::ui.delete_message'));
    }

    #[Test]
    public function the_delete_toast_is_a_success_and_offers_undo(): void
    {
        Livewire::test(BaseCrud::class, ['model' => UxItem::class])
            ->call('confirmDelete', 1)
            ->call('deleteRecord')
            ->assertDispatched('ptah-toast', title: trans('ptah::ui.toast_deleted'), color: 'success', undoId: 1);
    }

    #[Test]
    public function every_result_can_be_selected_not_only_the_page(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => UxItem::class])
            ->set('perPage', 10)
            ->call('toggleSelectAll');
        $this->assertCount(10, $crud->get('selectedRows'));
        $crud->assertSee(__('ptah::ui.bulk_select_all_matching', ['n' => 30]));

        $crud->call('selectAllMatching');
        $this->assertCount(30, $crud->get('selectedRows'));
        $crud->assertSee(__('ptah::ui.bulk_all_matching_selected', ['n' => 30]));

        // Same filters as the listing: only the open ones.
        $crud->call('clearSelection')->set('filters.status', 'open')->call('selectAllMatching');
        $this->assertCount(15, $crud->get('selectedRows'));
    }

    #[Test]
    public function the_filter_chips_say_the_list_is_filtered_and_show_the_option_name(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => UxItem::class])
            ->set('filters.status', 'closed')
            ->call('buildTextFilter');

        $crud->assertSee(__('ptah::ui.filters_applied'));
        $this->assertSame('Fechado', collect($crud->get('textFilter'))->firstWhere('field', 'status')['value']);
        $crud->assertSee(__('ptah::ui.filters_remove', ['label' => 'Situação']));
    }

    #[Test]
    public function a_not_null_column_without_default_is_marked_required(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => UxItem::class])->call('openCreate');
        $html = $crud->html();

        // name: NOT NULL, no DEFAULT → *; status has a DEFAULT and note is nullable.
        $this->assertMatchesRegularExpression('/Nome(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<span class="text-red-500 ml-0\.5">\*<\/span>/', $html);
        $this->assertDoesNotMatchRegularExpression('/Nota(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<span class="text-red-500 ml-0\.5">\*<\/span>/', $html);
        $this->assertStringContainsString(__('ptah::ui.form_required_hint'), $html);

        $component = $crud->instance();
        $cols = collect($component->crudConfig['cols'])->keyBy('colsNomeFisico');
        $this->assertTrue($component->fieldIsRequired($cols['name']));
        $this->assertFalse($component->fieldIsRequired($cols['status']));
        $this->assertFalse($component->fieldIsRequired($cols['note']));
        $this->assertTrue($component->deleteIsUndoable());
    }

    #[Test]
    public function new_confirms_the_modal_open_on_the_server(): void
    {
        // Closing and clicking "New" at once: the close answered after the click
        // and shut the new form (1.61.0).
        Livewire::test(BaseCrud::class, ['model' => UxItem::class])
            ->call('closeModal')
            ->call('prepareCreate')
            ->assertSet('showModal', true);
    }
}

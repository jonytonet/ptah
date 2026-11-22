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
use Ptah\Tests\TestCase;

class SdClearItem extends Model
{
    protected $table = 'sd_clear_items';

    protected $guarded = [];
}

/**
 * Erasing a chosen searchdropdown's text undoes the choice (1.60.3, yelll).
 *
 * The field went blank while formData kept the old id, so saving stored the
 * item the person had just erased. FormSelectLoopBrowserTest covers the
 * browser side (the typed text no longer snaps back to the chosen label).
 */
class CrudSearchDropdownClearTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('sd_clear_items', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->unsignedBigInteger('child_id')->nullable();
            $t->timestamps();
        });
        SdClearItem::create(['name' => 'Motor']);

        $sd = fn (string $field, array $extra = []) => array_merge([
            'colsNomeFisico' => $field, 'colsNomeLogico' => ucfirst($field), 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
            'colsSDModel' => SdClearItem::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id', 'colsSDTipo' => 'model',
        ], $extra);

        CrudConfig::updateOrCreate(['model' => SdClearItem::class, 'route' => ''], ['config' => [
            'crud' => SdClearItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                $sd('parent_id'),
                $sd('child_id', ['colsSDDependsOn' => 'parent_id', 'colsSDFilterColumn' => 'id']),
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function erasing_the_text_clears_the_value_the_label_and_the_dependents(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => SdClearItem::class])
            ->call('openCreate')
            ->call('selectDropdownOption', 'parent_id', 1, 'Motor')
            ->call('selectDropdownOption', 'child_id', 1, 'Motor')
            ->call('searchDropdown', 'parent_id', '');

        $this->assertNull($crud->get('formData.parent_id'));
        $this->assertArrayNotHasKey('parent_id', $crud->get('sdLabels'));
        $this->assertNull($crud->get('formData.child_id') ?? null, 'The dependent kept a value under a cleared parent.');
    }

    #[Test]
    public function an_empty_search_on_a_field_that_is_not_a_searchdropdown_changes_nothing(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => SdClearItem::class])
            ->call('openCreate')
            ->set('formData.name', 'Pedido')
            ->call('searchDropdown', 'name', '');

        $this->assertSame('Pedido', $crud->get('formData.name'));
    }
}

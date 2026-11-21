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

class NewFormKeysItem extends Model
{
    protected $table = 'new_form_keys_items';

    protected $guarded = [];
}

/**
 * The "New" form opens with one key per field (1.60.1, yelll).
 *
 * An empty formData went to the browser as a JSON array, and Livewire merges a
 * response field by field only when both sides are objects with the same keys
 * in the same order — otherwise it swaps the whole object. A select chosen
 * while another request was in flight was wiped by that request's response
 * (FormSelectLoopBrowserTest proves it in a browser). The keys start null, and
 * null on a new record means "not filled in": save() leaves those columns out
 * of the INSERT, so the column's DEFAULT still applies, as when the key did not
 * exist.
 */
class CrudNewFormKeysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('new_form_keys_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status')->default('draft');
            $t->string('note')->nullable();
            $t->timestamps();
        });

        CrudConfig::updateOrCreate(['model' => NewFormKeysItem::class, 'route' => ''], ['config' => [
            'crud' => NewFormKeysItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Rascunho' => 'draft', 'Pronto' => 'ready']],
                ['colsNomeFisico' => 'note', 'colsNomeLogico' => 'Nota', 'colsTipo' => 'text', 'colsGravar' => true],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function the_new_form_opens_with_every_field_in_form_order(): void
    {
        $component = Livewire::test(BaseCrud::class, ['model' => NewFormKeysItem::class])->call('openCreate');

        $this->assertSame(['name' => null, 'status' => null, 'note' => null], $component->get('formData'));
    }

    #[Test]
    public function a_field_left_untouched_on_a_new_record_keeps_the_column_default(): void
    {
        Livewire::test(BaseCrud::class, ['model' => NewFormKeysItem::class])
            ->call('openCreate')
            ->set('formData.name', 'Pedido')
            ->call('save')
            ->assertHasNoErrors();

        $row = NewFormKeysItem::firstOrFail();
        $this->assertSame('draft', $row->status, 'The untouched select must not insert NULL over the DEFAULT.');
        $this->assertNull($row->note);
    }

    #[Test]
    public function on_an_edit_a_cleared_field_still_saves_null(): void
    {
        $item = NewFormKeysItem::create(['name' => 'Pedido', 'status' => 'ready', 'note' => 'urgente']);

        Livewire::test(BaseCrud::class, ['model' => NewFormKeysItem::class])
            ->call('openEdit', $item->id)
            ->set('formData.note', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($item->fresh()->note);
    }

    #[Test]
    public function the_select_sends_only_what_the_person_chose(): void
    {
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/partials/_modal-form.blade.php');

        // The value a response brought is not sent back: that was a request per second, forever.
        $this->assertMatchesRegularExpression(
            '/\$watch\(\'selected\'.*?\$wire\.\$get\(\'formData\.\{\{ \$fField \}\}\'\).*?if \(now === String\(val \?\? \'\'\)\) return;\s*\$el\.dispatchEvent/s',
            $blade
        );
    }
}

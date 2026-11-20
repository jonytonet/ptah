<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudScreen;
use Ptah\Crud\CrudScreenRegistry;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class MaskEmptyItem extends Model
{
    protected $table = 'mask_empty_items';

    protected $guarded = [];
}

/**
 * A money field emptied on a column where empty means "not informed" (1.59.2,
 * PetPlace): money_to_float turned '' into 0.0, so "free delivery above"
 * empty — never — was saved as 0 — always. A nullable column now saves null
 * and the input stays empty; a NOT NULL one keeps 0; `colsMaskEmpty` decides
 * field by field.
 */
class CrudMaskEmptyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('mask_empty_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('free_above', 10, 2)->nullable();
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('forced_null', 10, 2)->default(0);
            $t->decimal('forced_zero', 10, 2)->nullable();
            $t->string('discount')->nullable();
            $t->timestamps();
        });

        $money = fn (string $field, array $extra = []) => [
            'colsNomeFisico' => $field, 'colsNomeLogico' => $field, 'colsTipo' => 'text', 'colsGravar' => true,
            'colsMask' => 'money_brl', 'colsMaskTransform' => 'money_to_float',
        ] + $extra;

        CrudConfig::updateOrCreate(['model' => MaskEmptyItem::class, 'route' => ''], ['config' => [
            'crud' => MaskEmptyItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                $money('free_above'),
                $money('price'),
                $money('forced_null', ['colsMaskEmpty' => 'null']),
                $money('forced_zero', ['colsMaskEmpty' => 'zero']),
                // The `decimal` store rule, without a transform.
                ['colsNomeFisico' => 'discount', 'colsNomeLogico' => 'Desconto', 'colsTipo' => 'text', 'colsGravar' => true, 'colsMask' => 'decimal'],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function an_emptied_field_saves_null_on_a_nullable_column_and_zero_on_a_not_null_one(): void
    {
        Livewire::test(BaseCrud::class, ['model' => MaskEmptyItem::class])
            ->call('openCreate')
            ->set('formData.name', 'Entrega')
            ->set('formData.free_above', '')
            ->set('formData.price', '')
            ->set('formData.forced_zero', '')
            ->set('formData.discount', '')
            ->call('save')
            ->assertHasNoErrors();

        $row = MaskEmptyItem::firstOrFail();
        $this->assertNull($row->free_above, 'Nullable: empty is "not informed".');
        $this->assertEquals(0, $row->price, 'NOT NULL: empty is zero, as before.');
        $this->assertNotNull($row->forced_zero, 'colsMaskEmpty: zero wins over a nullable column.');
        $this->assertEquals(0, $row->forced_zero);
        $this->assertNull($row->discount, 'The decimal store rule follows the same answer.');
    }

    #[Test]
    public function colsmaskempty_null_wins_over_a_not_null_column_rule_and_a_filled_value_is_still_converted(): void
    {
        $runtime = new class extends BaseCrud
        {
            /** @param array<string, mixed> $col */
            public function asks(array $col): bool
            {
                return $this->maskEmptyIsNull($col);
            }
        };
        $runtime->model = MaskEmptyItem::class;

        $this->assertTrue($runtime->asks(['colsNomeFisico' => 'forced_null', 'colsMaskEmpty' => 'null']));
        $this->assertFalse($runtime->asks(['colsNomeFisico' => 'price']));
        $this->assertTrue($runtime->asks(['colsNomeFisico' => 'free_above']));

        Livewire::test(BaseCrud::class, ['model' => MaskEmptyItem::class])
            ->call('openCreate')
            ->set('formData.name', 'Cupom')
            ->set('formData.free_above', 'R$ 1.253,08')
            ->set('formData.price', 'R$ 10,00')
            ->call('save')
            ->assertHasNoErrors();

        $row = MaskEmptyItem::firstOrFail();
        $this->assertEquals(1253.08, (float) $row->free_above);
        $this->assertEquals(10.0, (float) $row->price);
    }

    #[Test]
    public function opening_and_saving_a_record_untouched_keeps_null(): void
    {
        $item = MaskEmptyItem::create(['name' => 'Sem teto', 'free_above' => null, 'price' => 5]);

        Livewire::test(BaseCrud::class, ['model' => MaskEmptyItem::class])
            ->call('openEdit', $item->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($item->fresh()->free_above);
        $this->assertEquals(5, (float) $item->fresh()->price);
    }

    #[Test]
    public function the_money_input_stays_empty_instead_of_showing_zero_only_where_empty_means_null(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => MaskEmptyItem::class])->call('openCreate')->html();

        $this->assertMatchesRegularExpression('/wire:key="ptah-money-free_above-new"[^>]*x-data="\{\s*display: \'\',\s*blank: true,/s', $html);
        $this->assertMatchesRegularExpression('/wire:key="ptah-money-price-new"[^>]*x-data="\{\s*display: \'\',\s*blank: false,/s', $html);
    }

    #[Test]
    public function react_and_vue_get_the_same_answer_in_the_schema(): void
    {
        CrudScreen::register('mask-empty', MaskEmptyItem::class)->configRoute('');
        config(['ptah.crud_api.enabled' => true]);

        $screen = app(CrudScreenRegistry::class)->find('mask-empty');
        $this->assertNotNull($screen);
        $fields = collect(CrudRuntime::for($screen, request())->schema()['form'])->keyBy('field');

        $this->assertTrue($fields['free_above']['emptyNull']);
        $this->assertFalse($fields['price']['emptyNull']);
        $this->assertTrue($fields['forced_null']['emptyNull']);
        $this->assertFalse($fields['forced_zero']['emptyNull']);
        $this->assertFalse($fields['name']['emptyNull']);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudScreen;
use Ptah\Crud\CrudScreenRegistry;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class SwitchDefaultItem extends Model
{
    protected $table = 'switch_default_items';

    protected $guarded = [];
}

/**
 * A boolean NOT NULL column is a switch, and a switch has no "not informed":
 * on "New" it opened OFF and saved 0 even where the column's DEFAULT is 1 —
 * a new category was born inactive (1.59.2, PetPlace). The column's default
 * now fills the switch when the config declares none.
 */
class CrudSwitchDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('switch_default_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_featured')->default(false);
            $t->boolean('declared')->default(true);
            $t->boolean('no_default');
            $t->timestamps();
        });

        $switch = fn (string $field, array $extra = []) => [
            'colsNomeFisico' => $field, 'colsNomeLogico' => $field, 'colsTipo' => 'boolean', 'colsGravar' => true,
        ] + $extra;

        CrudConfig::updateOrCreate(['model' => SwitchDefaultItem::class, 'route' => ''], ['config' => [
            'crud' => SwitchDefaultItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                $switch('is_active'),
                $switch('is_featured'),
                // A declared default still wins over the column's.
                $switch('declared', ['colsDefaultValue' => '0']),
                $switch('no_default'),
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function new_opens_each_switch_at_the_column_default_and_saves_it_untouched(): void
    {
        $screen = Livewire::test(BaseCrud::class, ['model' => SwitchDefaultItem::class])->call('openCreate');

        $this->assertTrue($screen->get('formData.is_active'));
        $this->assertFalse($screen->get('formData.is_featured'));
        $this->assertFalse($screen->get('formData.declared'));
        $this->assertNull($screen->get('formData.no_default'));

        // Untouched, all of them: a column without a default must still save off, never null.
        $screen->set('formData.name', 'Banho')->call('save')->assertHasNoErrors();

        $row = SwitchDefaultItem::firstOrFail();
        $this->assertEquals(1, $row->is_active, 'DEFAULT 1: a new record is born active.');
        $this->assertEquals(0, $row->is_featured);
        $this->assertEquals(0, $row->declared, 'colsDefaultValue wins over the column default.');
        $this->assertEquals(0, $row->no_default, 'NOT NULL without a default: off, as before.');
    }

    #[Test]
    public function react_and_vue_get_the_same_starting_values(): void
    {
        CrudScreen::register('switch-default', SwitchDefaultItem::class)->configRoute('');
        $screen = app(CrudScreenRegistry::class)->find('switch-default');
        $this->assertNotNull($screen);

        $data = CrudRuntime::for($screen, request())->blank()['data'];

        $this->assertTrue($data['is_active']);
        $this->assertFalse($data['is_featured']);
        $this->assertFalse($data['declared']);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function defaults(): array
    {
        return [
            'sqlite 1' => ['1', true],
            'sqlite quoted' => ["'1'", true],
            'mysql 0' => ['0', false],
            'postgres true' => ['true', true],
            'postgres false' => ['false', false],
            'postgres cast' => ["'1'::integer", true],
            'mysql bit' => ["b'1'", true],
            'mysql bit off' => ["b'0'", false],
            'parenthesised' => ['(1)', true],
        ];
    }

    #[Test]
    #[DataProvider('defaults')]
    public function a_column_default_is_read_as_on_or_off_in_every_dialect(string $default, bool $on): void
    {
        $probe = new class extends BaseCrud
        {
            public static function reads(string $default): bool
            {
                return self::defaultIsOn($default);
            }
        };

        $this->assertSame($on, $probe::reads($default));
    }
}

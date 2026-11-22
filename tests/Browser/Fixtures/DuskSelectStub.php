<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;

/**
 * Screen with several inline selects and nothing with a default, for
 * FormSelectLoopBrowserTest (1.60.1, yelll): the "New" form opens with an
 * empty formData, the case that went to the browser as a JSON array. The
 * searchdropdown (`parent_id`, over this same table) is for 1.60.2: focusing
 * it must not make the table behind the modal flash.
 */
class DuskSelectStub extends Model
{
    protected $table = 'dusk_select_stubs';

    protected $fillable = ['name', 'kind', 'size', 'color', 'origin', 'parent_id'];

    public static function seedFixtures(): void
    {
        if (! Schema::hasTable('dusk_select_stubs')) {
            Schema::create('dusk_select_stubs', function (Blueprint $t) {
                $t->id();
                $t->string('name')->nullable();
                $t->string('kind')->nullable();
                $t->string('size')->nullable();
                $t->string('color')->nullable();
                $t->string('origin')->nullable();
                $t->unsignedBigInteger('parent_id')->nullable();
                $t->timestamps();
            });
        }

        if (! self::query()->exists()) {
            self::query()->create(['name' => 'Motor']);
            self::query()->create(['name' => 'Freio']);
        }

        $select = fn (string $field, array $options) => [
            'colsNomeFisico' => $field, 'colsNomeLogico' => ucfirst($field), 'colsTipo' => 'select',
            'colsGravar' => true, 'colsSelect' => $options,
        ];

        CrudConfig::firstOrCreate(['model' => self::class], ['route' => '', 'config' => [
            'crud' => self::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                $select('kind', ['Peça' => 'part', 'Serviço' => 'service']),
                $select('size', ['P' => 'p', 'M' => 'm', 'G' => 'g']),
                $select('color', ['Azul' => 'blue', 'Verde' => 'green']),
                $select('origin', ['Nacional' => 'nac', 'Importado' => 'imp']),
                ['colsNomeFisico' => 'parent_id', 'colsNomeLogico' => 'Pai', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsSDModel' => self::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id', 'colsSDTipo' => 'model'],
            ],
            'permissions' => [],
        ]]);

        Livewire::component('dusk-slow-select-crud', DuskSlowSelectCrud::class);
    }
}

/**
 * `slowTouch()` is a slow round trip that changes formData on the server, the
 * profile of a searchdropdown pick: a select chosen while it is in flight must
 * survive its response.
 */
class DuskSlowSelectCrud extends BaseCrud
{
    public function slowTouch(): void
    {
        usleep(1_500_000);
        $this->formData['name'] = 'escolhido no dropdown';
    }
}

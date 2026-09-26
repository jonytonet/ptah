<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\PtahMask;

/**
 * Screen with a masked CPF field, for MaskTypedDuringRequestBrowserTest.
 */
class DuskMaskStub extends Model
{
    protected $table = 'dusk_mask_stubs';

    protected $fillable = ['name', 'cpf'];

    public static function seedFixtures(): void
    {
        if (! Schema::hasTable('dusk_mask_stubs')) {
            Schema::create('dusk_mask_stubs', function (Blueprint $t) {
                $t->id();
                $t->string('name')->nullable();
                $t->string('cpf')->nullable();
                $t->timestamps();
            });
        }

        CrudConfig::firstOrCreate(['model' => self::class], ['route' => '', 'config' => [
            'crud' => self::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'cpf', 'colsNomeLogico' => 'CPF', 'colsTipo' => 'text', 'colsGravar' => true, 'colsMask' => 'cpf'],
            ],
            'permissions' => [],
        ]]);

        // cpf vem do preset br, que o pacote nao liga por padrao.
        PtahMask::preset('br');
        Livewire::component('dusk-slow-crud', DuskSlowCrud::class);
    }
}

/**
 * A BaseCrud whose `slowTouch()` stands in for selectDropdownOption: a round
 * trip that takes a while and CHANGES formData on the server — the response
 * replaces formData on the client, which is what dropped the typed CPF.
 */
class DuskSlowCrud extends BaseCrud
{
    public function slowTouch(): void
    {
        usleep(1_500_000);
        $this->formData['name'] = 'escolhido no dropdown';
    }
}

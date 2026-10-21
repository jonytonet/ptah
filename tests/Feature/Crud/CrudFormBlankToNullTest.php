<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class BlankNotice extends Model
{
    protected $table = 'blank_notices';

    protected $guarded = [];
}

/**
 * 1.47.0 (Engepecas, avisos): a cleared optional field reached the database
 * as '' — a strict MySQL refuses it for an integer or a timestamp. And the
 * form's select, once chosen, could never go back to empty.
 */
class CrudFormBlankToNullTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('blank_notices', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('note')->default('');
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamps();
        });

        CrudConfig::create(['model' => BlankNotice::class, 'route' => '', 'config' => [
            'crud' => BlankNotice::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Titulo', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'note', 'colsNomeLogico' => 'Nota', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'branch_id', 'colsNomeLogico' => 'Filial', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Matriz' => '1', 'Filial 2' => '2']],
                ['colsNomeFisico' => 'starts_at', 'colsNomeLogico' => 'Inicio', 'colsTipo' => 'datetime', 'colsGravar' => true],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function a_cleared_field_on_a_nullable_column_is_saved_as_null(): void
    {
        $row = BlankNotice::create(['title' => 'Aviso', 'note' => 'x', 'branch_id' => 2, 'starts_at' => now()]);

        Livewire::test(BaseCrud::class, ['model' => BlankNotice::class])
            ->call('openEdit', $row->id)
            ->set('formData.branch_id', '')
            ->set('formData.starts_at', '')
            ->set('formData.note', '')
            ->call('save')
            ->assertSet('formErrors', []);

        $saved = DB::table('blank_notices')->find($row->id);
        $this->assertNull($saved->branch_id);
        $this->assertNull($saved->starts_at);
        $this->assertSame('', $saved->note, 'Coluna NOT NULL de texto mantem o vazio.');
    }

    #[Test]
    public function an_optional_select_offers_a_way_back_to_empty(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => BlankNotice::class])->call('prepareCreate')->html();

        $this->assertStringContainsString('data-ptah-select-clear', $html);
        $this->assertStringNotContainsString('ring-red-200', $html);
    }
}

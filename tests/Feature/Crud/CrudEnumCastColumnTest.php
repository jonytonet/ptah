<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Exports\CrudExport;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\Scalar;
use Ptah\Tests\TestCase;

enum PieceStatus: string
{
    case Requested = 'requested';
    case Done = 'done';
}

enum PiecePriority
{
    case Low;
    case High;
}

class PieceRequest extends Model
{
    protected $table = 'piece_requests';

    protected $guarded = [];

    protected $casts = ['status' => PieceStatus::class];
}

/**
 * 1.46.4 (Engepecas, "Pecas solicitadas"): a column cast to an enum made the
 * listing a 500 ("Object of class ... could not be converted to string") —
 * only once there was a row, so an empty screen hid it.
 */
class CrudEnumCastColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('piece_requests', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status');
            $t->timestamps();
        });
        PieceRequest::create(['name' => 'Rolamento', 'status' => PieceStatus::Requested]);
        PieceRequest::create(['name' => 'Correia', 'status' => PieceStatus::Done]);

        CrudConfig::create(['model' => PieceRequest::class, 'route' => '', 'config' => [
            'crud' => PieceRequest::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Peca', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'select', 'colsIsFilterable' => 'S',
                    'colsSelect' => ['Solicitada' => 'requested', 'Pronta' => 'done'],
                    'colsRenderer' => 'badge',
                    'colsRendererBadges' => [['value' => 'requested', 'label' => 'Solicitada', 'color' => 'warn'], ['value' => 'done', 'label' => 'Pronta', 'color' => 'success']]],
            ],
            'contitionStyles' => [['field' => 'status', 'condition' => '==', 'value' => 'done', 'style' => 'opacity:.6']],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function the_listing_renders_an_enum_cast_column(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => PieceRequest::class])->call('clearFilters')->html();

        $this->assertStringContainsString('Solicitada', $html);
        $this->assertStringContainsString('Pronta', $html);
        $this->assertStringContainsString('opacity:.6', $html, 'O estilo condicional compara o valor do enum.');
    }

    #[Test]
    public function filter_by_cell_and_the_export_read_the_enum_value(): void
    {
        $done = PieceRequest::where('name', 'Correia')->first();

        $rows = Livewire::test(BaseCrud::class, ['model' => PieceRequest::class])->call('clearFilters')
            ->call('filterByCell', $done->id, 'status')->viewData('rows');
        $this->assertSame(['Correia'], collect($rows->items())->pluck('name')->all());

        $export = new CrudExport(PieceRequest::query(), [['field' => 'status', 'label' => 'Situacao', 'type' => 'text']]);
        $this->assertSame(['done'], $export->map($done));
    }

    #[Test]
    public function scalar_of_flattens_backed_and_pure_enums(): void
    {
        $this->assertSame('done', Scalar::of(PieceStatus::Done));
        $this->assertSame('High', Scalar::of(PiecePriority::High));
        $this->assertSame(3, Scalar::of(3));
    }

    #[Test]
    public function the_checkbox_draws_its_border_from_a_token(): void
    {
        $html = Blade::render('<x-forge-checkbox label="Aceito" />');

        $this->assertStringContainsString('ptah-c-ctrl_check', $html);
        $this->assertStringNotContainsString('border-gray-300', $html, 'Borda fixa clara no tema escuro.');
    }
}

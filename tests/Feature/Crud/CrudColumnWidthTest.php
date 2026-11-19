<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ColumnWidthItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * The resize handle of a column header: a drag saves the width, a
 * double-click (`saveColumnWidth(field, null)` in _scripts.blade.php) forgets
 * it. The method was typed `int`, so the double-click was a TypeError and the
 * saved width came back on the next load.
 */
class CrudColumnWidthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 5]));

        CrudConfig::create(['model' => ColumnWidthItem::class, 'route' => '', 'config' => [
            'crud' => ColumnWidthItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number'],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function a_drag_saves_the_width_with_a_floor(): void
    {
        Livewire::test(BaseCrud::class, ['model' => ColumnWidthItem::class])
            ->call('saveColumnWidth', 'name', 180)
            ->call('saveColumnWidth', 'amount', 10)
            ->assertSet('columnWidths', ['name' => 180, 'amount' => 60]);
    }

    #[Test]
    public function a_double_click_forgets_the_saved_width_and_it_stays_forgotten(): void
    {
        Livewire::test(BaseCrud::class, ['model' => ColumnWidthItem::class])
            ->call('saveColumnWidth', 'name', 180)
            ->call('saveColumnWidth', 'amount', 120)
            ->call('saveColumnWidth', 'name', null)
            ->assertSet('columnWidths', ['amount' => 120]);

        // Saved: the next opening of the screen reads the same.
        Livewire::test(BaseCrud::class, ['model' => ColumnWidthItem::class])
            ->assertSet('columnWidths', ['amount' => 120]);
    }
}

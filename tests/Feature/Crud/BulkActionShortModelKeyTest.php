<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class BulkShortKeyItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * 1.50.0: bulk actions did `new ($this->model)`. A screen mounted with a
 * model KEY ("Product", "Catalog/Product") — the normal case in a host —
 * died with "Class not found" on select-all and every bulk action.
 */
class BulkActionShortModelKeyTest extends TestCase
{
    #[Test]
    public function select_all_and_bulk_delete_work_with_a_short_model_key(): void
    {
        CrudConfig::create(['model' => 'BulkShortKeyItem', 'route' => '', 'config' => [
            'crud' => BulkShortKeyItem::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text']],
            'permissions' => [],
        ]]);
        $id = DB::table('items')->insertGetId(['name' => 'A', 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(BaseCrud::class, ['model' => 'BulkShortKeyItem'])
            ->call('toggleSelectAll')
            ->assertSet('selectedRows', [(string) $id])
            ->call('bulkDelete')
            ->assertHasNoErrors();

        $this->assertSame(0, DB::table('items')->where('id', $id)->count());
    }
}

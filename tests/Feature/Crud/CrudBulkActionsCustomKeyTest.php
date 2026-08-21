<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

/** Model whose primary key is NOT `id` — see tests/migrations. */
class CustomKeyBulkStub extends Model
{
    use SoftDeletes;

    protected $table = 'custom_key_bulk_stubs';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name'];
}

/**
 * FIX 7 (Onda 2 audit): HasCrudBulkActions::bulkDelete/bulkRestore/
 * bulkForceDelete used to hardcode `whereIn('id', $this->selectedRows)`
 * instead of matching the model's actual primary key (as bulkExport already
 * did — HasCrudExport.php:84). On a CRUD keyed by something other than `id`,
 * the bulk action matched nothing at all.
 */
class CrudBulkActionsCustomKeyTest extends TestCase
{
    private function makeConfig(): void
    {
        CrudConfig::create([
            'model' => CustomKeyBulkStub::class,
            'route' => '',
            'config' => [
                'crud' => CustomKeyBulkStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'code', 'colsNomeLogico' => 'Code', 'colsTipo' => 'text', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    #[Test]
    public function toggle_select_all_collects_the_models_actual_primary_key(): void
    {
        $this->makeConfig();
        CustomKeyBulkStub::create(['code' => 'c1', 'name' => 'One']);
        CustomKeyBulkStub::create(['code' => 'c2', 'name' => 'Two']);

        Livewire::test(BaseCrud::class, ['model' => CustomKeyBulkStub::class])
            ->call('toggleSelectAll')
            ->assertSet('selectedRows', ['c1', 'c2']);
    }

    #[Test]
    public function bulk_delete_matches_by_the_models_actual_primary_key(): void
    {
        $this->makeConfig();
        $record = CustomKeyBulkStub::create(['code' => 'c1', 'name' => 'One']);

        Livewire::test(BaseCrud::class, ['model' => CustomKeyBulkStub::class])
            ->set('selectedRows', [$record->code])
            ->call('bulkDelete');

        $this->assertNotNull(
            CustomKeyBulkStub::withTrashed()->find('c1')->deleted_at,
            'bulkDelete must match on the `code` primary key, not a non-existent `id` column',
        );
    }

    #[Test]
    public function bulk_restore_matches_by_the_models_actual_primary_key(): void
    {
        $this->makeConfig();
        $record = CustomKeyBulkStub::create(['code' => 'c1', 'name' => 'One']);
        $record->delete();

        Livewire::test(BaseCrud::class, ['model' => CustomKeyBulkStub::class])
            ->set('selectedRows', [$record->code])
            ->call('bulkRestore');

        $this->assertNull(
            CustomKeyBulkStub::withTrashed()->find('c1')->deleted_at,
            'bulkRestore must match on the `code` primary key, not a non-existent `id` column',
        );
    }

    #[Test]
    public function bulk_force_delete_matches_by_the_models_actual_primary_key(): void
    {
        $this->makeConfig();
        $record = CustomKeyBulkStub::create(['code' => 'c1', 'name' => 'One']);
        $record->delete();

        Livewire::test(BaseCrud::class, ['model' => CustomKeyBulkStub::class])
            ->set('selectedRows', [$record->code])
            ->call('bulkForceDelete');

        $this->assertNull(
            CustomKeyBulkStub::withTrashed()->find('c1'),
            'bulkForceDelete must match on the `code` primary key, not a non-existent `id` column',
        );
    }
}

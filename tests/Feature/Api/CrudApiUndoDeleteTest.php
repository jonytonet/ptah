<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class UndoSoftItem extends Model
{
    use SoftDeletes;

    protected $table = 'bulk_action_stubs';

    protected $guarded = [];
}

class UndoHardItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * "Undo" on the toast of a delete (1.59.0): the Livewire toast of a soft
 * delete carries the record and restores it on a click. The API answers the
 * same id with the message, and the restore endpoint brings it back.
 */
class CrudApiUndoDeleteTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));

        foreach (['soft' => UndoSoftItem::class, 'hard' => UndoHardItem::class] as $key => $model) {
            CrudScreen::register($key, $model)->configRoute('');
            CrudConfig::create(['model' => $model, 'route' => '', 'config' => [
                'crud' => $model,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ]]);
        }
    }

    #[Test]
    public function deleting_a_soft_deleted_record_offers_undo_and_undo_brings_it_back(): void
    {
        $item = UndoSoftItem::create(['name' => 'Alfa']);

        $messages = $this->deleteJson("/ptah/api/crud/soft/{$item->id}")->assertOk()->assertJsonPath('ok', true)->json('messages');

        $this->assertSame($item->id, $messages[0]['undo']);
        $this->assertSoftDeleted('bulk_action_stubs', ['id' => $item->id]);

        $this->postJson("/ptah/api/crud/soft/{$item->id}/restore")->assertOk()->assertJsonPath('ok', true);
        $this->assertNotSoftDeleted('bulk_action_stubs', ['id' => $item->id]);
    }

    #[Test]
    public function a_delete_that_cannot_be_undone_offers_nothing(): void
    {
        $item = UndoHardItem::create(['name' => 'Beta']);

        $messages = $this->deleteJson("/ptah/api/crud/hard/{$item->id}")->assertOk()->json('messages');

        $this->assertNotSame([], $messages);
        $this->assertArrayNotHasKey('undo', $messages[0]);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }
}

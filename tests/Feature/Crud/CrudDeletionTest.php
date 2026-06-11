<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use Ptah\Traits\HasAuditFields;

// ── Stubs ─────────────────────────────────────────────────────────────────────

/** Soft-deletable model on the has_audit_stubs table (created by test migration). */
class DeletionStub extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $table = 'has_audit_stubs';

    protected $fillable = ['name', 'created_by', 'updated_by', 'deleted_by'];
}

class DeletionTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers HasCrudDeletion through the real BaseCrud component: soft delete with
 * deleted_by stamping, restore, trashed count and the confirm/cancel flow.
 * Mounting the real component also exercises the HasCrudQuery render pipeline.
 */
class CrudDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => DeletionStub::class,
            'route' => '',
            'config' => [
                'crud' => DeletionStub::class,
                'cols' => [
                    [
                        'colsNomeFisico' => 'id',
                        'colsNomeLogico' => 'ID',
                        'colsTipo' => 'number',
                        'colsGravar' => false,
                    ],
                    [
                        'colsNomeFisico' => 'name',
                        'colsNomeLogico' => 'Name',
                        'colsTipo' => 'text',
                        'colsGravar' => true,
                    ],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function actingAsUser(): DeletionTestUser
    {
        $user = DeletionTestUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function the_component_mounts_and_renders_with_a_config_row(): void
    {
        DeletionStub::create(['name' => 'Visible row']);

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->assertOk()
            ->assertSee('Visible row');
    }

    #[Test]
    public function delete_record_soft_deletes_and_stamps_deleted_by(): void
    {
        $user = $this->actingAsUser();
        $record = DeletionStub::create(['name' => 'Doomed']);

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->call('confirmDelete', $record->id)
            ->assertSet('showDeleteConfirm', true)
            ->assertSet('deletingId', $record->id)
            ->call('deleteRecord')
            ->assertSet('showDeleteConfirm', false)
            ->assertDispatched('crud-deleted');

        $trashed = DeletionStub::withTrashed()->find($record->id);
        $this->assertNotNull($trashed->deleted_at, 'Record must be soft deleted, not hard deleted');
        $this->assertSame($user->id, (int) $trashed->deleted_by);
    }

    #[Test]
    public function delete_without_confirmation_is_a_no_op(): void
    {
        $record = DeletionStub::create(['name' => 'Safe']);

        // deletingId never set → deleteRecord must do nothing.
        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->call('deleteRecord');

        $this->assertNull(DeletionStub::find($record->id)->deleted_at);
    }

    #[Test]
    public function cancel_delete_resets_the_confirmation_state(): void
    {
        $record = DeletionStub::create(['name' => 'Kept']);

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->call('confirmDelete', $record->id)
            ->call('cancelDelete')
            ->assertSet('deletingId', null)
            ->assertSet('showDeleteConfirm', false)
            ->call('deleteRecord');

        $this->assertNull(DeletionStub::find($record->id)->deleted_at, 'After cancel, nothing may be deleted');
    }

    #[Test]
    public function delete_toast_carries_the_undo_id_for_soft_deletes(): void
    {
        $this->actingAsUser();
        $record = DeletionStub::create(['name' => 'Undoable']);

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->call('confirmDelete', $record->id)
            ->call('deleteRecord')
            ->assertDispatched('ptah-toast', undoId: $record->id);
    }

    #[Test]
    public function restore_record_brings_a_trashed_row_back(): void
    {
        $this->actingAsUser();
        $record = DeletionStub::create(['name' => 'Phoenix']);
        $record->delete();

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->call('restoreRecord', $record->id)
            ->assertDispatched('crud-restored');

        $this->assertNull(DeletionStub::find($record->id)->deleted_at);
    }

    #[Test]
    public function trashed_count_tracks_soft_deleted_rows(): void
    {
        DeletionStub::create(['name' => 'Active']);
        DeletionStub::create(['name' => 'Trashed 1'])->delete();
        DeletionStub::create(['name' => 'Trashed 2'])->delete();

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->assertSet('trashedCount', 2);
    }

    #[Test]
    public function toggle_trashed_switches_the_listing_to_deleted_rows(): void
    {
        DeletionStub::create(['name' => 'Living row']);
        DeletionStub::create(['name' => 'Buried row'])->delete();

        $component = Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->assertSee('Living row')
            ->assertDontSee('Buried row')
            ->call('toggleTrashed')
            ->assertSet('showTrashed', true);

        $component->assertSee('Buried row');
    }

    #[Test]
    public function anonymous_user_cannot_delete_when_a_permission_identifier_is_set(): void
    {
        // Fail-closed: permissions module on + identifier configured + guest → denied.
        CrudConfig::where('model', DeletionStub::class)->first()->update([
            'config' => array_merge(
                CrudConfig::where('model', DeletionStub::class)->first()->config,
                ['permissions' => ['permissionIdentifier' => 'stub.items']],
            ),
        ]);

        $record = DeletionStub::create(['name' => 'Protected']);

        Livewire::test(BaseCrud::class, ['model' => DeletionStub::class])
            ->call('confirmDelete', $record->id)
            ->call('deleteRecord');

        $this->assertNull(
            DeletionStub::find($record->id)->deleted_at,
            'Anonymous users must not delete when a permissionIdentifier is configured',
        );
    }
}

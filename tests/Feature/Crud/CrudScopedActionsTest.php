<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stubs ─────────────────────────────────────────────────────────────────────

class ScopedStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/** Overrides afterCreate to return a redirect, exercising the save() redirect path. */
class RedirectingCrud extends BaseCrud
{
    protected function afterCreate(Model $record): mixed
    {
        return redirect('/done');
    }
}

/**
 * Covers the single-record IDOR guard (edit/delete/save re-scope a client id via
 * scopedQuery → company / master-detail lock) and the save() redirect fix
 * (afterCreate/afterUpdate RedirectResponse must actually navigate).
 */
class CrudScopedActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => ScopedStub::class,
            'route' => '',
            'config' => [
                'crud' => ScopedStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function seedTwo(): array
    {
        return [
            'in' => ScopedStub::create(['name' => 'InScope', 'status' => 'active', 'amount' => 1]),
            'out' => ScopedStub::create(['name' => 'OutScope', 'status' => 'archived', 'amount' => 2]),
        ];
    }

    /** A CRUD locked to status=active — "archived" rows are outside the scope. */
    private function lockedCrud()
    {
        return Livewire::test(BaseCrud::class, [
            'model' => ScopedStub::class,
            'lockedFilters' => ['status' => 'active'],
        ]);
    }

    // ── IDOR: edit ──────────────────────────────────────────────────────────

    #[Test]
    public function open_edit_ignores_a_record_outside_the_lock_scope(): void
    {
        ['out' => $out] = $this->seedTwo();

        $this->lockedCrud()
            ->call('openEdit', $out->id)
            ->assertSet('editingId', null)   // record not found within scope → no-op
            ->assertSet('showModal', false);
    }

    #[Test]
    public function open_edit_works_for_a_record_inside_the_scope(): void
    {
        ['in' => $in] = $this->seedTwo();

        $this->lockedCrud()
            ->call('openEdit', $in->id)
            ->assertSet('editingId', $in->id)
            ->assertSet('showModal', true);
    }

    // ── IDOR: delete ──────────────────────────────────────────────────────────

    #[Test]
    public function delete_record_cannot_reach_a_row_outside_the_scope(): void
    {
        ['out' => $out] = $this->seedTwo();

        $this->lockedCrud()
            ->set('deletingId', $out->id)
            ->call('deleteRecord');

        $this->assertDatabaseHas('items', ['id' => $out->id]);
    }

    #[Test]
    public function delete_record_removes_an_in_scope_row(): void
    {
        ['in' => $in] = $this->seedTwo();

        $this->lockedCrud()
            ->set('deletingId', $in->id)
            ->call('deleteRecord');

        $this->assertDatabaseMissing('items', ['id' => $in->id]);
    }

    // ── IDOR: save (update) ─────────────────────────────────────────────────

    #[Test]
    public function save_update_rejects_a_client_supplied_out_of_scope_id(): void
    {
        ['out' => $out] = $this->seedTwo();

        // editingId is a public property — simulate a tampered client update.
        $this->lockedCrud()
            ->set('editingId', $out->id)
            ->set('formData.name', 'Hacked')
            ->set('formData.status', 'archived')
            ->call('save');

        // The out-of-scope row must be untouched.
        $this->assertDatabaseHas('items', ['id' => $out->id, 'name' => 'OutScope']);
        $this->assertDatabaseMissing('items', ['name' => 'Hacked']);
    }

    // ── save() redirect fix ─────────────────────────────────────────────────

    #[Test]
    public function after_create_redirect_actually_navigates(): void
    {
        Livewire::test(RedirectingCrud::class, ['model' => ScopedStub::class])
            ->set('formData.name', 'New')
            ->set('formData.status', 'active')
            ->call('save')
            ->assertRedirect('/done');
    }
}

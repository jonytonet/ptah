<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class SaveStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * End-to-end save() through the real BaseCrud component: validation gate,
 * mask transforms, inline sandbox hooks and the mass-assignment guard all
 * acting together on a single persisted row.
 */
class CrudSaveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => SaveStub::class,
            'route' => '',
            'config' => [
                'crud' => SaveStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    [
                        'colsNomeFisico' => 'name',
                        'colsNomeLogico' => 'Name',
                        'colsTipo' => 'text',
                        'colsGravar' => true,
                        'colsRequired' => true,
                        'colsMaskTransform' => 'uppercase',
                    ],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true],
                    // Deliberately marked savable to prove the guard strips it anyway.
                    ['colsNomeFisico' => 'created_at', 'colsNomeLogico' => 'Created', 'colsTipo' => 'date', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => SaveStub::class]);
    }

    #[Test]
    public function save_creates_a_record_and_applies_the_mask_transform(): void
    {
        $this->crud()
            ->set('formData.name', 'lower name')
            ->set('formData.status', 'active')
            ->set('formData.amount', 7)
            ->call('save')
            ->assertSet('formErrors', [])
            ->assertDispatched('crud-saved');

        $record = SaveStub::first();
        $this->assertNotNull($record);
        $this->assertSame('LOWER NAME', $record->name, 'uppercase mask must run before persisting');
        $this->assertSame('active', $record->status);
    }

    #[Test]
    public function save_is_blocked_when_a_required_field_is_empty(): void
    {
        $component = $this->crud()
            ->set('formData.status', 'active')
            ->call('save');

        $errors = $component->get('formErrors');

        $this->assertArrayHasKey('name', $errors);
        $this->assertSame(0, SaveStub::count(), 'Validation failure must prevent the insert');
    }

    #[Test]
    public function guarded_fields_are_stripped_even_when_marked_savable(): void
    {
        $this->crud()
            ->set('formData.name', 'Guarded test')
            ->set('formData.created_at', '2000-01-01 00:00:00')
            ->call('save');

        $record = SaveStub::first();

        $this->assertNotNull($record);
        $this->assertTrue(
            $record->created_at->isToday(),
            'created_at from the form must be discarded by guardedFormFields()',
        );
    }

    #[Test]
    public function inline_sandbox_hook_reshapes_the_data_on_create(): void
    {
        CrudConfig::where('model', SaveStub::class)->first()->update([
            'config' => array_merge(
                CrudConfig::where('model', SaveStub::class)->first()->config,
                ['lifecycleHooks' => ['beforeCreate' => "merge(data, {'status': 'from-hook'})"]],
            ),
        ]);

        $this->crud()
            ->set('formData.name', 'Hooked')
            ->call('save');

        $this->assertSame('from-hook', SaveStub::first()->status);
    }

    #[Test]
    public function save_updates_an_existing_record_when_editing(): void
    {
        $record = SaveStub::create(['name' => 'OLD', 'status' => 'active', 'amount' => 1]);

        $this->crud()
            ->call('openEdit', $record->id)
            ->assertSet('editingId', $record->id)
            ->assertSet('showModal', true)
            ->set('formData.name', 'new value')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertSame('NEW VALUE', $record->fresh()->name);
        $this->assertSame(1, SaveStub::count(), 'Edit must update, never duplicate');
    }

    #[Test]
    public function prepare_create_resets_the_form_state(): void
    {
        $this->crud()
            ->set('formData.name', 'leftover')
            ->set('editingId', 99)
            ->call('prepareCreate')
            ->assertSet('formData', [])
            ->assertSet('editingId', null)
            ->assertSet('formErrors', []);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table (boolean-cast `is_active` column) ────────

class BooleanFormStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Regression coverage for the "boolean field has no form control" bug:
 * colsTipo 'boolean' had no branch in _modal-form.blade.php and fell through
 * to the plain text @else branch. The fix reuses the select-inline branch
 * (already known to persist '0'/'1' correctly) with a Sim/Não option map.
 */
class CrudBooleanFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => BooleanFormStub::class,
            'route' => '',
            'config' => [
                'crud' => BooleanFormStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    [
                        'colsNomeFisico' => 'name',
                        'colsNomeLogico' => 'Name',
                        'colsTipo' => 'text',
                        'colsGravar' => true,
                        'colsRequired' => true,
                    ],
                    [
                        'colsNomeFisico' => 'is_active',
                        'colsNomeLogico' => 'Active',
                        'colsTipo' => 'boolean',
                        'colsGravar' => true,
                    ],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => BooleanFormStub::class]);
    }

    #[Test]
    public function boolean_column_renders_the_inline_select_not_a_text_input(): void
    {
        $html = $this->crud()->call('prepareCreate')->html();

        // Select branch marker (wire:key is unique per field) — proves the
        // boolean column was routed through the select-inline branch.
        $this->assertStringContainsString('wire:key="ptah-select-is_active-new"', $html);

        // The plain-text @else branch (<x-forge-input ... wire:model="formData.X">,
        // no ".live") must never have been reached for this field.
        $this->assertStringNotContainsString('wire:model="formData.is_active"', $html);
        $this->assertStringContainsString('wire:model.live="formData.is_active"', $html);
    }

    #[Test]
    public function boolean_column_prefills_1_for_true_and_0_for_false(): void
    {
        $active = BooleanFormStub::create(['name' => 'Active one', 'is_active' => true]);
        $inactive = BooleanFormStub::create(['name' => 'Inactive one', 'is_active' => false]);

        // The x-data attribute is HTML-escaped (it lives inside a double-quoted
        // attribute), so the raw markup carries &quot; rather than a literal ".
        $htmlActive = $this->crud()->call('openEdit', $active->id)->html();
        $this->assertStringContainsString('selected: &quot;1&quot;', $htmlActive);

        $htmlInactive = $this->crud()->call('openEdit', $inactive->id)->html();
        $this->assertStringContainsString('selected: &quot;0&quot;', $htmlInactive);
    }

    #[Test]
    public function saving_0_and_1_through_the_boolean_field_persists_both_directions(): void
    {
        $record = BooleanFormStub::create(['name' => 'Toggle me', 'is_active' => true]);

        $this->crud()
            ->call('openEdit', $record->id)
            ->set('formData.is_active', '0')
            ->call('save')
            ->assertSet('formErrors', []);

        $this->assertSame(0, (int) $record->fresh()->is_active);

        $this->crud()
            ->call('openEdit', $record->id)
            ->set('formData.is_active', '1')
            ->call('save')
            ->assertSet('formErrors', []);

        $this->assertSame(1, (int) $record->fresh()->is_active);
    }

    #[Test]
    public function a_leftover_string_cols_select_still_renders_the_select(): void
    {
        // The field editor can leave colsSelect as an edit-state string
        // ("label;value;;…", see ConfigFormPreviewTest). The boolean branch
        // must override it with a fresh array instead of erroring or falling
        // through to the text-input branch.
        CrudConfig::where('model', BooleanFormStub::class)->first()->update([
            'config' => array_replace_recursive(
                CrudConfig::where('model', BooleanFormStub::class)->first()->config,
                ['cols' => [
                    2 => ['colsSelect' => 'Sim;1;;Não;0'],
                ]]
            ),
        ]);

        $html = $this->crud()->call('prepareCreate')->html();

        $this->assertStringContainsString('wire:key="ptah-select-is_active-new"', $html);
        $this->assertStringContainsString(__('ptah::ui.bool_yes'), $html);
        $this->assertStringContainsString(__('ptah::ui.bool_no'), $html);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Tests\TestCase;

/**
 * Covers the inert "preview form" overlay in the CrudConfig modal: it mirrors
 * the savable columns being configured (unsaved), renders sections and the
 * cascade hint, and exposes no real action.
 */
class ConfigFormPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tests exercise the editor UI, not its authorization. Run with the
        // permissions module off and the editor opt-in enabled so the gate allows.
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);
    }

    private function configWithFields(): array
    {
        return [
            ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
            ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Full name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true, 'colsFormBlock' => 'Identity'],
            ['colsNomeFisico' => 'state_id', 'colsNomeLogico' => 'State', 'colsTipo' => 'searchdropdown', 'colsGravar' => true],
            ['colsNomeFisico' => 'city_id', 'colsNomeLogico' => 'City', 'colsTipo' => 'searchdropdown', 'colsGravar' => true, 'colsSDDependsOn' => 'state_id', 'colsFormBlock' => 'Address'],
            ['colsNomeFisico' => 'internal', 'colsNomeLogico' => 'Internal note', 'colsTipo' => 'text', 'colsGravar' => false],
        ];
    }

    private function cfg()
    {
        return Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->set('formEditFields', $this->configWithFields());
    }

    #[Test]
    public function preview_starts_closed_and_opens_on_demand(): void
    {
        $this->cfg()
            ->assertSet('showPreview', false)
            ->call('previewForm')
            ->assertSet('showPreview', true)
            ->call('closePreview')
            ->assertSet('showPreview', false);
    }

    #[Test]
    public function preview_form_cols_returns_only_savable_non_action_columns(): void
    {
        $cols = Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->set('formEditFields', $this->configWithFields())
            ->instance()
            ->previewFormCols();

        $fields = array_column($cols, 'colsNomeFisico');

        $this->assertEqualsCanonicalizing(['name', 'state_id', 'city_id'], $fields);
        $this->assertNotContains('id', $fields, 'Non-savable columns must be excluded');
        $this->assertNotContains('internal', $fields, 'colsGravar=false must be excluded');
    }

    #[Test]
    public function open_preview_renders_labels_sections_and_cascade_hint(): void
    {
        $this->cfg()
            ->call('previewForm')
            ->assertSet('showPreview', true)
            ->assertSee('Full name')   // savable field label
            ->assertSee('Identity')    // form block / section heading
            ->assertSee('Address');    // second section
        // (Exclusion of non-savable columns is asserted in previewFormCols above —
        // the config modal's column list naturally shows every column name.)
    }

    // ── Regression: select field with editable string colsSelect ───────────────

    /**
     * In the edit state colsSelect is a string ("label;value;;…"). The preview
     * renders real <option>s, so previewFormCols() must hand back the array form
     * — otherwise the Blade foreach over a string throws and the whole BaseCrud
     * page 500s for admins (the preview is rendered, hidden via x-show).
     */
    private function selectFields(): array
    {
        return [
            ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
            ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'select', 'colsGravar' => true, 'colsSelect' => 'Active;1;;Inactive;0'],
        ];
    }

    #[Test]
    public function preview_form_cols_normalises_select_string_to_array(): void
    {
        $component = Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->set('formEditFields', $this->selectFields());

        $cols = $component->instance()->previewFormCols();
        $statusCol = collect($cols)->firstWhere('colsNomeFisico', 'status');

        $this->assertSame(['Active' => '1', 'Inactive' => '0'], $statusCol['colsSelect']);

        // The edit state itself must stay a string (drives the editable input).
        $editStatus = collect($component->get('formEditFields'))->firstWhere('colsNomeFisico', 'status');
        $this->assertSame('Active;1;;Inactive;0', $editStatus['colsSelect']);
    }

    #[Test]
    public function open_preview_renders_select_options_without_error(): void
    {
        Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->set('formEditFields', $this->selectFields())
            ->call('previewForm')
            ->assertSet('showPreview', true)
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('Inactive');
    }
}

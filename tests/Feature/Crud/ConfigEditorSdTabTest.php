<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigComponent;
use Ptah\Models\CrudConfig;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Covers the visual CrudConfig editor's SearchDropdown ("sd") sub-tab:
 *
 *  - the tab is only rendered while the column being edited is of type
 *    'searchdropdown' (:1246-1329's "sd" entry in $fTabs is now conditional);
 *  - the new inputs/defaults added to the tab (colsSDInitWithData, the
 *    renamed canonical wire:model bindings, the new mask/array-search/
 *    start-list fields);
 *  - addField() seeds colsSDTipo='model' and colsSDInitWithData=true;
 *  - CrudConfig::formatFieldsForDb() composes colsSDModel from
 *    colsSDService+colsSDServiceMethod in service mode, and rewrites the
 *    legacy dialect (colsSDMode/colsSDValueField/colsSDLabelField/
 *    colsSDOrderBy) into the canonical keys without deleting the originals.
 */
class ConfigEditorSdTabTest extends TestCase
{
    private function editor(string $model = 'Widget')
    {
        return Livewire::test(CrudConfigComponent::class, ['model' => $model]);
    }

    /** Binds a master PermissionService stub so save()/openModal() are allowed. */
    private function actAsMaster(): void
    {
        $stub = new class extends PermissionService
        {
            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }

    // ── Conditional tab ──────────────────────────────────────────────────────

    #[Test]
    public function the_sd_tab_is_hidden_while_editing_a_non_searchdropdown_column(): void
    {
        $this->actAsMaster();

        // The sub-tab BUTTON is what's conditional (its click handler sets
        // editTab to 'sd') — not asserted via the tab label text alone,
        // since that same word also appears in the (always-rendered, merely
        // Alpine-hidden) content hint box.
        $html = $this->editor()
            ->call('openModal')
            ->set('formDataField.colsTipo', 'text')
            ->html();

        $this->assertStringNotContainsString("editTab = 'sd'", $html);
    }

    #[Test]
    public function the_sd_tab_appears_once_the_column_type_is_searchdropdown(): void
    {
        $this->actAsMaster();

        $html = $this->editor()
            ->call('openModal')
            ->set('formDataField.colsTipo', 'searchdropdown')
            ->html();

        $this->assertStringContainsString("editTab = 'sd'", $html);
    }

    // ── New inputs rendered on the tab ───────────────────────────────────────

    #[Test]
    public function the_tab_renders_the_new_surface_fields(): void
    {
        $this->actAsMaster();

        $html = $this->editor()
            ->call('openModal')
            ->set('formDataField.colsTipo', 'searchdropdown')
            ->html();

        $this->assertStringContainsString('formDataField.colsSDInitWithData', $html);
        $this->assertStringContainsString('formDataField.colsSDTipo', $html);
        $this->assertStringContainsString('formDataField.colsSDValor', $html);
        $this->assertStringContainsString('formDataField.colsSDLabel"', $html);
        $this->assertStringContainsString('formDataField.colsSDLabelThree', $html);
        $this->assertStringContainsString('formDataField.colsSDOrder"', $html);
        $this->assertStringContainsString('formDataField.colsSDStartList', $html);
        $this->assertStringContainsString('formDataField.colsSDArraySearch', $html);
        $this->assertStringContainsString('formDataField.colsSDMaskOne', $html);
        $this->assertStringContainsString('formDataField.colsSDMaskTwo', $html);
        $this->assertStringContainsString('formDataField.colsSDMaskThree', $html);

        // The old dialect must no longer be bound directly by the editor.
        $this->assertStringNotContainsString('formDataField.colsSDMode"', $html);
        $this->assertStringNotContainsString('formDataField.colsSDValueField', $html);
        $this->assertStringNotContainsString('formDataField.colsSDLabelField', $html);
        $this->assertStringNotContainsString('formDataField.colsSDOrderBy', $html);
    }

    // ── addField() defaults ──────────────────────────────────────────────────

    #[Test]
    public function add_field_seeds_model_mode_and_init_with_data_true(): void
    {
        $component = $this->editor()
            ->set('formDataField', [
                'colsNomeFisico' => 'supplier_id',
                'colsNomeLogico' => 'Supplier',
                'colsTipo' => 'searchdropdown',
            ])
            ->call('addField');

        $field = collect($component->get('formEditFields'))->firstWhere('colsNomeFisico', 'supplier_id');

        $this->assertSame('model', $field['colsSDTipo']);
        $this->assertTrue($field['colsSDInitWithData']);
    }

    // ── formatFieldsForDb(): service-mode composition ────────────────────────

    #[Test]
    public function saving_a_service_mode_column_composes_colssdmodel(): void
    {
        $this->actAsMaster();

        $this->editor()
            ->call('openModal')
            ->set('formDataField', [
                'colsNomeFisico' => 'supplier_id',
                'colsNomeLogico' => 'Supplier',
                'colsTipo' => 'searchdropdown',
                'colsSDTipo' => 'service',
                'colsSDService' => 'Purchase\\SupplierService',
                'colsSDServiceMethod' => 'search',
            ])
            ->call('addField')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $col = collect($stored->config['cols'])->firstWhere('colsNomeFisico', 'supplier_id');

        $this->assertSame('Purchase\\SupplierService\\search', $col['colsSDModel']);
        // Originals preserved — additive only.
        $this->assertSame('Purchase\\SupplierService', $col['colsSDService']);
        $this->assertSame('search', $col['colsSDServiceMethod']);
    }

    // ── formatFieldsForDb(): legacy dialect rewrite ──────────────────────────

    #[Test]
    public function saving_rewrites_the_legacy_dialect_to_canonical_keys_without_deleting_it(): void
    {
        $this->actAsMaster();

        $this->editor()
            ->call('openModal')
            ->set('formDataField', [
                'colsNomeFisico' => 'supplier_id',
                'colsNomeLogico' => 'Supplier',
                'colsTipo' => 'searchdropdown',
                'colsSDModel' => 'Supplier',
                'colsSDMode' => 'model',
                'colsSDValueField' => 'id',
                'colsSDLabelField' => 'name',
                'colsSDOrderBy' => 'name asc',
            ])
            ->call('addField')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $col = collect($stored->config['cols'])->firstWhere('colsNomeFisico', 'supplier_id');

        $this->assertSame('model', $col['colsSDTipo']);
        $this->assertSame('id', $col['colsSDValor']);
        $this->assertSame('name', $col['colsSDLabel']);
        $this->assertSame('name asc', $col['colsSDOrder']);

        // Legacy keys are preserved, not deleted.
        $this->assertSame('model', $col['colsSDMode']);
        $this->assertSame('id', $col['colsSDValueField']);
        $this->assertSame('name', $col['colsSDLabelField']);
        $this->assertSame('name asc', $col['colsSDOrderBy']);
    }

    #[Test]
    public function saving_does_not_override_an_already_canonical_value(): void
    {
        $this->actAsMaster();

        $this->editor()
            ->call('openModal')
            ->set('formDataField', [
                'colsNomeFisico' => 'supplier_id',
                'colsNomeLogico' => 'Supplier',
                'colsTipo' => 'searchdropdown',
                'colsSDModel' => 'Supplier',
                'colsSDTipo' => 'model',
                'colsSDValor' => 'uuid',
                'colsSDValueField' => 'id',
            ])
            ->call('addField')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $col = collect($stored->config['cols'])->firstWhere('colsNomeFisico', 'supplier_id');

        $this->assertSame('uuid', $col['colsSDValor'], 'The canonical value must win over the legacy dialect.');
    }
}

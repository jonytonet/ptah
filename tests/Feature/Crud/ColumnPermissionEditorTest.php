<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigComponent;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Covers the wave-3 visual editor for the per-column `colsPermission` gate:
 *
 *  - CrudConfig::availablePermissionKeys() — the select's option source, an
 *    active PageObject on an active page, qualified only when its obj_key
 *    collides across pages (see ConfigDoctorCommand's "obj_key collision").
 *  - The `<select>` and lock badge in the crud-config.blade.php view.
 *  - formatFieldsForDb() dropping a blank tag on save (the second trap —
 *    ColumnPermissionService::extractKey() is the first, at read time).
 *
 * The base TestCase turns `ptah.modules.permissions` ON by default.
 */
class ColumnPermissionEditorTest extends TestCase
{
    private function editor(string $model = 'Widget')
    {
        return Livewire::test(CrudConfigComponent::class, ['model' => $model]);
    }

    private function makePage(string $slug, bool $active = true): PtahPage
    {
        return PtahPage::create(['slug' => $slug, 'name' => $slug, 'is_active' => $active]);
    }

    private function makeObject(PtahPage $page, string $key, bool $active = true, string $label = ''): PageObject
    {
        return PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => $key, 'obj_label' => $label ?: $key,
            'obj_type' => 'field', 'obj_order' => 1, 'is_active' => $active,
        ]);
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

    // ── availablePermissionKeys() ────────────────────────────────────────────

    #[Test]
    public function returns_empty_when_the_permissions_module_is_off(): void
    {
        config()->set('ptah.modules.permissions', false);
        $this->makeObject($this->makePage('sales'), 'sales.view_cost');

        $this->assertSame([], $this->editor()->instance()->availablePermissionKeys());
    }

    #[Test]
    public function lists_an_active_object_on_an_active_page_with_the_bare_key(): void
    {
        $this->makeObject($this->makePage('sales'), 'sales.view_cost', label: 'View cost');

        $options = $this->editor()->instance()->availablePermissionKeys();

        $this->assertCount(1, $options);
        $this->assertSame('sales.view_cost', $options[0]['value']);
        $this->assertSame('sales.view_cost — View cost (sales)', $options[0]['label']);
    }

    #[Test]
    public function excludes_an_inactive_object(): void
    {
        $this->makeObject($this->makePage('sales'), 'sales.view_cost', active: false);

        $this->assertSame([], $this->editor()->instance()->availablePermissionKeys());
    }

    #[Test]
    public function excludes_an_object_on_an_inactive_page(): void
    {
        $this->makeObject($this->makePage('sales', active: false), 'sales.view_cost');

        $this->assertSame([], $this->editor()->instance()->availablePermissionKeys());
    }

    #[Test]
    public function qualifies_the_value_when_the_obj_key_collides_across_pages(): void
    {
        $this->makeObject($this->makePage('page-a'), 'shared.button');
        $this->makeObject($this->makePage('page-b'), 'shared.button');

        $options = $this->editor()->instance()->availablePermissionKeys();
        $values = array_column($options, 'value');

        $this->assertCount(2, $options);
        $this->assertContains('page-a::shared.button', $values);
        $this->assertContains('page-b::shared.button', $values);
        $this->assertNotContains('shared.button', $values);
    }

    #[Test]
    public function does_not_qualify_an_obj_key_scoped_to_a_single_page(): void
    {
        $this->makeObject($this->makePage('sales'), 'sales.view_cost');

        $options = $this->editor()->instance()->availablePermissionKeys();

        $this->assertSame('sales.view_cost', $options[0]['value']);
    }

    // ── View: select + hint (module-gated) ──────────────────────────────────

    #[Test]
    public function the_select_and_registered_options_render_when_the_module_is_on(): void
    {
        $this->makeObject($this->makePage('sales'), 'sales.view_cost', label: 'View cost');

        $html = $this->editor()
            ->assertSee(__('ptah::ui.cfg_col_permission_label'))
            ->assertSee(__('ptah::ui.cfg_col_permission_hint'))
            ->html();

        // Onda: colsPermission moved from a plain <select><option> (real text
        // nodes) to <x-forge-select searchable>, which serializes `options`
        // into the `x-data` JSON blob instead — option labels only become
        // visible TEXT once Alpine hydrates client-side, which this
        // server-render assertion cannot execute. assertSee() still works
        // (it substring-searches the raw HTML, JSON blob included), but
        // json_encode() escapes non-ASCII by default, so the em dash in
        // "sales.view_cost — View cost (sales)" is NOT a literal "—" in the
        // markup — it is the escaped `—` sequence PHP's json_encode
        // produces. Assert against that same escaped form instead of
        // reverting the encoding just to keep a literal string comparison.
        $this->assertStringContainsString(
            trim(json_encode(__('ptah::ui.cfg_col_permission_none')), '"'),
            $html
        );
        $this->assertStringContainsString(
            trim(json_encode('sales.view_cost — View cost (sales)'), '"'),
            $html
        );
    }

    #[Test]
    public function the_select_is_absent_when_the_module_is_off(): void
    {
        config()->set('ptah.modules.permissions', false);

        $this->editor()->assertDontSee(__('ptah::ui.cfg_col_permission_label'));
    }

    /**
     * The colsPermission field is a searchable <x-forge-select>, not a plain
     * <select> — this is what forge-select actually renders as (see
     * ForgeSelectSearchableTest for the markup contract) — and its options
     * carry the qualified `{page}::{key}` values on collision, unmodified
     * from availablePermissionKeys().
     */
    #[Test]
    public function the_permission_field_renders_as_a_searchable_forge_select_with_qualified_values_on_collision(): void
    {
        $this->makeObject($this->makePage('page-a'), 'shared.button');
        $this->makeObject($this->makePage('page-b'), 'shared.button');

        $html = $this->editor()->html();

        $this->assertStringContainsString('ptah-select-filter', $html, 'colsPermission nao esta usando um forge-select searchable.');
        $this->assertStringNotContainsString('<select wire:model="formDataField.colsPermission"', $html);
        $this->assertStringContainsString('page-a::shared.button', $html);
        $this->assertStringContainsString('page-b::shared.button', $html);
    }

    // ── View: lock badge in the columns list ────────────────────────────────

    #[Test]
    public function a_tagged_column_shows_a_lock_badge_in_the_columns_list(): void
    {
        CrudConfig::create([
            'model' => 'Widget',
            'route' => '',
            'config' => [
                'crud' => 'Widget',
                'cols' => [
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsPermission' => 'sales.view_cost'],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text'],
                ],
            ],
        ]);

        $html = $this->editor()->html();

        $this->assertStringContainsString(
            __('ptah::ui.cfg_col_permission_badge_title', ['key' => 'sales.view_cost']),
            $html
        );
    }

    #[Test]
    public function an_untagged_column_shows_no_lock_badge(): void
    {
        CrudConfig::create([
            'model' => 'Widget',
            'route' => '',
            'config' => [
                'crud' => 'Widget',
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text'],
                ],
            ],
        ]);

        $html = $this->editor()->html();

        $this->assertStringNotContainsString('cfg_col_permission_badge_title', $html);
    }

    // ── formatFieldsForDb(): drops a blank tag on save ──────────────────────

    #[Test]
    public function saving_drops_a_blank_colspermission_tag(): void
    {
        $this->actAsMaster();

        $this->editor()
            ->call('openModal')
            ->set('formDataField', [
                'colsNomeFisico' => 'name',
                'colsNomeLogico' => 'Name',
                'colsTipo' => 'text',
                'colsPermission' => '   ',
            ])
            ->call('addField')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $this->assertArrayNotHasKey('colsPermission', $stored->config['cols'][0]);
    }

    #[Test]
    public function saving_keeps_a_non_blank_colspermission_tag(): void
    {
        $this->actAsMaster();

        $this->editor()
            ->call('openModal')
            ->set('formDataField', [
                'colsNomeFisico' => 'amount',
                'colsNomeLogico' => 'Amount',
                'colsTipo' => 'number',
                'colsPermission' => 'sales.view_cost',
            ])
            ->call('addField')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $this->assertSame('sales.view_cost', $stored->config['cols'][0]['colsPermission']);
    }
}

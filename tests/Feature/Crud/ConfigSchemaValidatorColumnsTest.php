<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Tests\TestCase;

/**
 * Covers two ConfigSchemaValidator bugs that made the visual editor unusable:
 *
 *  - colsTipo / colsRenderer '' (empty string) is what the editor persists for
 *    "no type set yet" / "Nenhum" — isset() alone treats '' as present and
 *    rejects it, even though the runtime (applyCellRenderer / formatCell)
 *    already treats '' as "nothing configured".
 *  - JOINs are validated against the legacy keys (colsTipo/colsTable/colsOn),
 *    but both the editor (CrudConfig::addJoin) and the runtime
 *    (HasCrudQuery::applyJoins) read/write type/table/first/second.
 */
class ConfigSchemaValidatorColumnsTest extends TestCase
{
    private function validator(): ConfigSchemaValidator
    {
        return new ConfigSchemaValidator;
    }

    // ── colsTipo / colsRenderer empty string ────────────────────────────────

    #[Test]
    public function an_empty_cols_tipo_passes(): void
    {
        $this->validator()->validate([
            'cols' => [
                ['colsNomeFisico' => 'is_active', 'colsTipo' => ''],
            ],
        ], 'Widget');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function an_empty_cols_renderer_passes(): void
    {
        $this->validator()->validate([
            'cols' => [
                ['colsNomeFisico' => 'is_active', 'colsTipo' => 'boolean', 'colsRenderer' => ''],
            ],
        ], 'Widget');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function an_invalid_cols_tipo_still_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'cols' => [
                ['colsNomeFisico' => 'is_active', 'colsTipo' => 'bool'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function an_invalid_cols_renderer_still_throws_and_the_message_names_the_renderer(): void
    {
        try {
            $this->validator()->validate([
                'cols' => [
                    ['colsNomeFisico' => 'is_active', 'colsTipo' => 'boolean', 'colsRenderer' => 'switch'],
                ],
            ], 'Widget');
            $this->fail('Expected a ConfigValidationException');
        } catch (ConfigValidationException $e) {
            $this->assertStringContainsString('renderer', $e->getMessage());
            $this->assertStringNotContainsString('column type', $e->getMessage());
        }
    }

    // ── Full editor round-trip (the exact user scenario) ────────────────────

    #[Test]
    public function the_editor_can_add_a_field_and_save_without_a_renderer_chosen(): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);

        Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->call('openModal')
            ->set('formDataField.colsNomeFisico', 'is_active')
            ->call('addField')
            ->call('save');

        $this->assertDatabaseHas('crud_configs', ['model' => 'Widget']);
    }

    // ── JOINs: runtime key names (type/table/first/second) ──────────────────

    #[Test]
    public function a_join_using_the_runtime_key_names_passes(): void
    {
        $this->validator()->validate([
            'joins' => [
                ['type' => 'left', 'table' => 'items', 'first' => 'widgets.item_id', 'second' => 'items.id'],
            ],
        ], 'Widget');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_join_missing_the_table_still_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'joins' => [
                ['type' => 'left', 'first' => 'widgets.item_id', 'second' => 'items.id'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function a_join_missing_first_or_second_still_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'joins' => [
                ['type' => 'left', 'table' => 'items', 'first' => 'widgets.item_id'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function an_unknown_join_type_still_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'joins' => [
                ['type' => 'outer', 'table' => 'items', 'first' => 'widgets.item_id', 'second' => 'items.id'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function a_join_on_a_nonexistent_table_still_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'joins' => [
                ['type' => 'left', 'table' => 'this_table_does_not_exist', 'first' => 'widgets.item_id', 'second' => 'this_table_does_not_exist.id'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function the_legacy_join_form_is_still_accepted_permissively(): void
    {
        // Never read by the runtime (HasCrudQuery::applyJoins reads
        // type/table/first/second only) — accepted for old configs, but not
        // validated in depth (no first/second granularity to check).
        $this->validator()->validate([
            'joins' => [
                ['colsTipo' => 'left', 'colsTable' => 'items', 'colsOn' => 'widgets.item_id=items.id'],
            ],
        ], 'Widget');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function saving_a_join_through_the_editor_persists_it(): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);

        Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->call('openModal')
            ->set('formDataJoin.table', 'items')
            ->set('formDataJoin.first', 'widgets.item_id')
            ->set('formDataJoin.second', 'items.id')
            ->call('addJoin')
            ->call('save');

        $config = \Ptah\Models\CrudConfig::where('model', 'Widget')->first()->config;

        $this->assertSame('items', $config['joins'][0]['table']);
        $this->assertSame('widgets.item_id', $config['joins'][0]['first']);
        $this->assertSame('items.id', $config['joins'][0]['second']);
    }
}

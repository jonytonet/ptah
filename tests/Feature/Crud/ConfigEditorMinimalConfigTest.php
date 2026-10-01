<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Models\CrudConfig as CrudConfigModel;
use Ptah\Tests\TestCase;

/**
 * A config written by hand (or imported) with only the essential keys passes
 * the validator and works on the listing — the editor must open it too. It is
 * drawn on the page of whoever may configure, so an "Undefined array key"
 * there was a 500 for the admin only (1.43.0).
 */
class ConfigEditorMinimalConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);
    }

    #[Test]
    public function a_minimal_column_of_every_type_opens_in_the_editor(): void
    {
        $cols = [];
        foreach (CrudConfigEnums::COLUMN_TYPES as $type) {
            $cols[] = ['colsNomeFisico' => 'f_'.str_replace('-', '_', $type), 'colsNomeLogico' => ucfirst($type), 'colsTipo' => $type];
        }
        $cols[] = ['colsTipo' => 'action', 'colsNomeFisico' => 'x', 'colsNomeLogico' => 'Horários', 'actionType' => 'link', 'actionValue' => '/x/%id%'];

        CrudConfigModel::updateOrCreate(['model' => 'Widget', 'route' => ''], ['config' => ['crud' => 'Widget', 'cols' => $cols]]);

        $editor = Livewire::test(CrudConfig::class, ['model' => 'Widget'])->call('openModal');
        $html = $editor->html();

        $this->assertStringContainsString('Horários', $html);
        $this->assertStringContainsString('bx bx-link', $html, 'Sem actionIcon, o icone padrao.');

        // E cada uma aberta para edicao — o formulario do editor le as mesmas chaves.
        $last = count($cols) - 1;
        foreach (array_keys($cols) as $i) {
            $i === $last ? $editor->call('editAction', $i) : $editor->call('editField', $i);
            $this->assertNotSame('', $editor->html());
        }
    }
}

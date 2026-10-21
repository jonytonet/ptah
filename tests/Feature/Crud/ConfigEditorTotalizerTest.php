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
 * 1.50.0: the editor's Totalizer sub-tab wrote totalizadorEnabled/Type into
 * the column, and the footer reads only `totalizadores.columns` — a total set
 * in the editor never appeared.
 */
class ConfigEditorTotalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => true]);
        $this->app->instance(PermissionService::class, new class extends PermissionService
        {
            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        });

        CrudConfig::create(['model' => 'Order', 'route' => '', 'config' => [
            'cols' => [
                ['colsNomeFisico' => 'number', 'colsNomeLogico' => 'Número', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number'],
            ],
            // A total set outside the editor, on a field it does not list.
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'freight', 'aggregate' => 'sum']]],
        ]]);
    }

    #[Test]
    public function a_total_set_in_the_editor_reaches_totalizadores_columns(): void
    {
        Livewire::test(CrudConfigComponent::class, ['model' => 'Order'])
            ->call('openModal')
            ->call('editField', 1)
            ->set('formDataField.totalizadorEnabled', true)
            ->set('formDataField.totalizadorType', 'avg')
            ->call('updateField')
            ->call('save');

        $cfg = CrudConfig::where('model', 'Order')->latest('id')->first()->config;

        $this->assertTrue($cfg['totalizadores']['enabled']);
        $this->assertSame([
            ['field' => 'amount', 'aggregate' => 'avg'],
            ['field' => 'freight', 'aggregate' => 'sum'],
        ], $cfg['totalizadores']['columns']);
        $this->assertArrayNotHasKey('totalizadorEnabled', $cfg['cols'][1], 'Chave morta nao vai para a coluna.');
    }

    #[Test]
    public function opening_the_editor_shows_the_totals_the_runtime_uses(): void
    {
        CrudConfig::where('model', 'Order')->update(['config' => [
            'cols' => [['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'totalizadorEnabled' => false]],
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'max']]],
        ]]);

        $fields = Livewire::test(CrudConfigComponent::class, ['model' => 'Order'])->call('openModal')->get('formEditFields');

        $this->assertTrue($fields[0]['totalizadorEnabled']);
        $this->assertSame('max', $fields[0]['totalizadorType']);
    }
}

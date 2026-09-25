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
 * The visual editor's Features section writes the keys the runtime reads —
 * import, history, attachments, kanban and calendar were reachable only
 * through `ptah:config --set` before 1.38.0, and a user could not find them.
 */
class ConfigEditorFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O editor so salva para quem administra config (ptah_can_manage_config).
        config(['ptah.modules.permissions' => true]);

        $this->app->instance(PermissionService::class, new class extends PermissionService
        {
            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        });

        CrudConfig::create(['model' => 'Task', 'route' => '', 'config' => [
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsSelect' => ['A fazer' => 'todo', 'Feito' => 'done']],
                ['colsNomeFisico' => 'due_date', 'colsNomeLogico' => 'Prazo', 'colsTipo' => 'date'],
            ],
            'importConfig' => ['maxRows' => 500],
        ]]);
    }

    #[Test]
    public function the_features_are_saved_where_basecrud_reads_them(): void
    {
        Livewire::test(CrudConfigComponent::class, ['model' => 'Task'])
            ->call('openModal')
            ->assertSee(__('ptah::ui.cfg_features'))
            ->set('featureImport', true)
            ->set('featureImportMode', 'upsert')
            ->set('featureImportKey', 'title')
            ->set('featureHistory', false)
            ->set('kanbanField', 'status')
            ->set('calendarStart', 'due_date')
            ->call('save');

        $cfg = CrudConfig::where('model', 'Task')->latest('id')->first()->config; // o editor grava na rota da tela aberta

        $this->assertSame(['maxRows' => 500, 'enabled' => true, 'mode' => 'upsert', 'key' => 'title'], $cfg['importConfig'], 'O maxRows ja gravado nao pode se perder.');
        $this->assertFalse($cfg['history']['enabled']);
        $this->assertTrue($cfg['attachments']['enabled']);
        $this->assertSame('status', $cfg['kanbanConfig']['field']);
        $this->assertSame(['start' => 'due_date', 'end' => null], $cfg['calendarConfig']);
    }

    #[Test]
    public function turning_the_board_and_calendar_off_removes_them(): void
    {
        CrudConfig::where('model', 'Task')->update(['config' => json_encode([
            'cols' => [['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsSelect' => ['A' => 'a']]],
            'kanbanConfig' => ['field' => 'status'],
            'calendarConfig' => ['start' => 'due_date'],
        ])]);

        Livewire::test(CrudConfigComponent::class, ['model' => 'Task'])
            ->call('openModal')
            ->assertSet('kanbanField', 'status')
            ->set('kanbanField', '')
            ->set('calendarStart', '')
            ->call('save');

        $cfg = CrudConfig::where('model', 'Task')->latest('id')->first()->config; // o editor grava na rota da tela aberta
        $this->assertNull($cfg['kanbanConfig']);
        $this->assertNull($cfg['calendarConfig']);
    }
}

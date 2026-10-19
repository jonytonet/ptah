<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class EnvelopeStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * 1.46.3: `ptah:config --import` stored an `export-all` file's envelope
 * {model, route, config} as the config itself (no cols, "imported
 * successfully"), and `--list` read only the OLD config keys — zero columns
 * and every button "Yes" on any screen.
 */
class ConfigImportEnvelopeAndListTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/ptah-envelope-'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function screen(): array
    {
        return [
            'crud' => EnvelopeStub::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S', 'colsRequired' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text', 'colsVisibleList' => false],
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'Abrir', 'colsTipo' => 'action', 'actionType' => 'link', 'actionValue' => '/x/%id%'],
            ],
            'permissions' => ['showCreateButton' => false, 'showEditButton' => false, 'showDeleteButton' => false, 'permissionIdentifier' => 'pedidos', 'create' => 'gate.create'],
            'cacheStrategy' => ['enabled' => true, 'ttl' => 120],
            'contitionStyles' => [['field' => 'status', 'condition' => '==', 'value' => 'x', 'style' => 'background:#fee']],
            'joins' => [['type' => 'left', 'table' => 'others', 'first' => 'items.id', 'second' => 'others.item_id']],
        ];
    }

    #[Test]
    public function import_unwraps_an_export_all_file(): void
    {
        $file = $this->dir.'/items.json';
        // Sem o JOIN de exemplo: o import valida, e a tabela `others` nao existe.
        $screen = array_diff_key($this->screen(), ['joins' => true]);
        File::put($file, json_encode(['model' => ModelKey::canonical(EnvelopeStub::class), 'route' => '', 'config' => $screen]));

        $this->artisan('ptah:config', ['model' => EnvelopeStub::class, '--import' => $file])->assertExitCode(0);

        $config = CrudConfig::where('model', ModelKey::canonical(EnvelopeStub::class))->first()->config;
        $this->assertSame('name', $config['cols'][0]['colsNomeFisico'], 'O envelope foi gravado como config.');
        $this->assertSame('pedidos', $config['permissions']['permissionIdentifier']);
        $this->assertArrayNotHasKey('config', $config);
    }

    #[Test]
    public function import_refuses_an_export_of_another_model(): void
    {
        $file = $this->dir.'/other.json';
        File::put($file, json_encode(['model' => 'Outro/Modelo', 'route' => '', 'config' => $this->screen()]));

        $this->artisan('ptah:config', ['model' => EnvelopeStub::class, '--import' => $file])
            ->expectsOutputToContain('Outro/Modelo')
            ->assertExitCode(1);
    }

    #[Test]
    public function list_reads_the_current_config_format(): void
    {
        CrudConfig::updateOrCreate(['model' => ModelKey::canonical(EnvelopeStub::class), 'route' => ''], ['config' => $this->screen()]);

        $this->artisan('ptah:config', ['model' => EnvelopeStub::class, '--list' => true])
            ->expectsOutputToContain('COLUMNS (2)')
            ->expectsOutputToContain('CUSTOM ACTIONS (1)')
            ->expectsOutputToContain('FILTERS (1)')
            ->expectsOutputToContain('CONDITIONAL STYLES (1)')
            ->expectsOutputToContain('JOINS (1)')
            ->expectsOutputToContain('pedidos')
            ->expectsOutputToContain('gate.create')
            ->expectsOutputToContain('120s')
            ->assertExitCode(0);
    }
}

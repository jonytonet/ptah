<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Registrar;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Mcp\PtahMcpTools;
use Ptah\Mcp\PtahServer;
use Ptah\Mcp\Tools\PtahDocs;
use Ptah\Mcp\Tools\PtahDoctor;
use Ptah\Mcp\Tools\PtahValidateConfig;
use Ptah\Models\CrudConfig;
use Ptah\PtahServiceProvider;
use Ptah\Support\DocsSearch;
use Ptah\Tests\TestCase;

class McpValidateWidget extends Model
{
    protected $table = 'mcp_validate_widgets';

    protected $fillable = ['name'];
}

/**
 * 1.52.0: an agent can check a config before saving it, search the docs by
 * subject, and reach the tools without Laravel Boost.
 */
class PtahMcpValidateAndSearchTest extends TestCase
{
    private const GOOD = ['cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text']]];

    private function doctor(array $args): array
    {
        $code = Artisan::call('ptah:config:doctor', $args);

        return [$code, Artisan::output()];
    }

    #[Test]
    public function a_clean_config_passes_and_nothing_is_saved(): void
    {
        [$code, $out] = $this->doctor(['--config' => json_encode(self::GOOD), '--model' => McpValidateWidget::class]);

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Checked 1 config(s): 0 error(s)', $out);
        $this->assertSame(0, CrudConfig::query()->count());
    }

    #[Test]
    public function the_traps_agents_fall_into_are_reported(): void
    {
        $config = self::GOOD;
        $config['cols'][0]['colsSortabel'] = true;
        $config['actions'] = [['colsNomeLogico' => 'Aprovar', 'colsTipo' => 'action', 'actionType' => 'livewire', 'actionValue' => 'approve(%id%)']];
        $config['filters'] = [['field' => 'name', 'label' => 'Nome', 'colsFilterType' => 'text']];

        [$code, $out] = $this->doctor(['--config' => json_encode($config), '--model' => McpValidateWidget::class]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('unknown column key', $out);
        $this->assertStringContainsString('colsSortabel', $out);
        $this->assertStringContainsString('dead actions section', $out);
        $this->assertStringContainsString('legacy filters key', $out);
        $this->assertSame(0, CrudConfig::query()->count());
    }

    #[Test]
    public function an_export_envelope_carries_its_model(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ptah').'.json';
        file_put_contents($file, json_encode(['model' => McpValidateWidget::class, 'route' => '', 'config' => self::GOOD]));

        [$code, $out] = $this->doctor(['--file' => $file]);
        @unlink($file);

        $this->assertSame(0, $code, $out);
    }

    #[Test]
    public function fix_bad_json_and_a_missing_model_are_refused(): void
    {
        $this->assertSame(1, $this->doctor(['--config' => json_encode(self::GOOD), '--model' => 'X', '--fix' => true])[0]);
        $this->assertStringContainsString('Invalid JSON', $this->doctor(['--config' => '{cols:', '--model' => 'X'])[1]);
        $this->assertStringContainsString('--model', $this->doctor(['--config' => json_encode(self::GOOD)])[1]);
    }

    #[Test]
    public function the_validate_tool_answers_with_the_audit(): void
    {
        $config = self::GOOD;
        $config['filters'] = [['field' => 'name']];

        $response = (new PtahValidateConfig)->handle(new Request([
            'config' => json_encode($config),
            'model' => McpValidateWidget::class,
        ]));

        $this->assertFalse($response->isError());
        $this->assertStringContainsString('legacy filters key', (string) $response->content());
        $this->assertStringContainsString('(exit 1)', (string) $response->content());
    }

    #[Test]
    public function the_doctor_tool_never_fixes(): void
    {
        CrudConfig::create(['model' => McpValidateWidget::class, 'route' => '', 'config' => self::GOOD]);

        $response = (new PtahDoctor)->handle(new Request([]));

        $this->assertStringContainsString('orphan key', (string) $response->content());
        $this->assertSame(McpValidateWidget::class, CrudConfig::query()->value('model'), 'O doctor via MCP nao pode reescrever nada.');
    }

    #[Test]
    public function docs_search_finds_the_section_by_subject(): void
    {
        $headings = array_column(DocsSearch::search('column permission'), 'heading');
        $this->assertContains('Column-level Permissions', $headings);

        // Start of a word: "por" (a stopword, and inside "export") ranks nothing.
        $this->assertSame([], DocsSearch::search('por com'));

        $response = (new PtahDocs)->handle(new Request(['search' => 'forge-table slot', 'limit' => 1]));
        $this->assertStringContainsString('docs/CustomScreens.md § `forge-table`', (string) $response->content());
    }

    #[Test]
    public function the_tools_run_on_a_server_of_their_own_without_boost(): void
    {
        $this->app->register(McpServiceProvider::class);
        (fn () => $this->registerBoostTools())->call(new PtahServiceProvider($this->app));

        $this->assertNotNull($this->app->make(Registrar::class)->getLocalServer('ptah'));

        $tools = (new \ReflectionClass(PtahServer::class))->getProperty('tools');
        $server = (new \ReflectionClass(PtahServer::class))->newInstanceWithoutConstructor();
        (fn () => $this->boot())->call($server);
        $this->assertSame(PtahMcpTools::CLASSES, $tools->getValue($server));
    }
}

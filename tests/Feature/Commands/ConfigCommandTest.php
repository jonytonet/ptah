<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

// Stub model on the `items` test table (has name/status/amount columns).
class ConfigCmdStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * End-to-end tests for ptah:config declarative (--non-interactive) mode — the
 * path the scaffold skill and agents drive. Proves options are parsed and
 * persisted to crud_configs, dry-run is side-effect free, and bad input is
 * rejected.
 */
class ConfigCommandTest extends TestCase
{
    #[Test]
    public function a_positional_filter_operator_is_warned_not_silently_dropped(): void
    {
        // `LIKE` posicional era descartado e o filtro virava igualdade, calado.
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--filter' => ['name:text:LIKE:label=Nome'],
            '--non-interactive' => true,
        ])
            ->expectsOutputToContain('ignored "LIKE"')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_well_formed_filter_raises_no_warning(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--filter' => ['status:select:operator=LIKE:options=1:Ativo,0:Inativo'],
            '--non-interactive' => true,
        ])
            ->doesntExpectOutputToContain('ignored')
            ->assertExitCode(0);
    }

    #[Test]
    public function declarative_column_option_is_parsed_and_persisted(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome', 'status:text:label=Situação'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first();
        $this->assertNotNull($cfg);

        $fields = array_column($cfg->config['cols'], 'colsNomeFisico');
        $this->assertContains('name', $fields);
        $this->assertContains('status', $fields);

        $nameCol = collect($cfg->config['cols'])->firstWhere('colsNomeFisico', 'name');
        $this->assertSame('Nome', $nameCol['colsNomeLogico']);
        $this->assertSame('text', $nameCol['colsTipo']);
    }

    #[Test]
    public function set_option_casts_and_stores_general_settings(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--set' => ['itemsPerPage=15', 'exportEnabled=true', 'displayName=Itens'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first()->config;

        // Antes: gravado no topo como `itemsPerPage`, que o BaseCrud nunca leu.
        $this->assertSame(15, $cfg['uiPreferences']['perPage']); // numeric cast, onde o runtime le
        $this->assertTrue($cfg['exportConfig']['enabled']);      // 'true' → bool
        $this->assertSame('Itens', $cfg['displayName']);         // chave real passa direto
        $this->assertArrayNotHasKey('itemsPerPage', $cfg);
    }

    #[Test]
    public function items_per_page_from_the_cli_is_the_page_size_the_screen_uses(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--set' => ['itemsPerPage=15'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        Livewire::test(BaseCrud::class, ['model' => ModelKey::canonical(ConfigCmdStub::class)])
            ->assertSet('perPage', 15);
    }

    #[Test]
    public function settings_the_runtime_never_reads_are_refused_with_a_warning(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--set' => ['cacheEnabled=true'],
            '--non-interactive' => true,
        ])
            ->expectsOutputToContain('not read by BaseCrud')
            ->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first()->config;
        $this->assertArrayNotHasKey('cacheEnabled', $cfg);
    }

    #[Test]
    public function a_cli_action_is_a_button_on_the_screen(): void
    {
        // Antes: gravada numa secao `actions` que nada le — "Actions: 1",
        // "saved successfully", e nenhum botao na tela.
        $args = [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--action' => ['Abrir:link:/itens/%id%/abrir:icon=bx bx-show'],
            '--non-interactive' => true,
        ];
        $this->artisan('ptah:config', $args)->assertExitCode(0);
        $this->artisan('ptah:config', $args)->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first()->config;
        $actions = array_values(array_filter($cfg['cols'], fn ($c) => ($c['colsTipo'] ?? '') === 'action'));

        $this->assertArrayNotHasKey('actions', $cfg);
        $this->assertCount(1, $actions, 'Rodar de novo deveria atualizar a acao, nao duplicar.');
        $this->assertSame('/itens/%id%/abrir', $actions[0]['actionValue']);

        DB::table('items')->insert(['name' => 'linha-1']);
        $html = Livewire::test(BaseCrud::class, ['model' => ModelKey::canonical(ConfigCmdStub::class)])->html();
        $this->assertStringContainsString('/itens/', $html, 'A acao configurada pela CLI nao aparece na tela.');
    }

    #[Test]
    public function the_doctor_moves_dead_actions_and_legacy_settings_where_the_runtime_reads(): void
    {
        CrudConfig::create(['model' => ModelKey::canonical(ConfigCmdStub::class), 'route' => '', 'config' => [
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text']],
            'actions' => [['colsNomeLogico' => 'Abrir', 'colsTipo' => 'action', 'actionType' => 'link', 'actionValue' => '/a/%id%']],
            'itemsPerPage' => 50,
            'cacheEnabled' => true,
        ]]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('dead actions section')
            ->assertExitCode(1);

        $this->artisan('ptah:config:doctor', ['--fix' => true]);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first()->config;
        $this->assertArrayNotHasKey('actions', $cfg);
        $this->assertArrayNotHasKey('itemsPerPage', $cfg);
        $this->assertArrayNotHasKey('cacheEnabled', $cfg);
        $this->assertSame(50, $cfg['uiPreferences']['perPage']);
        $this->assertSame('/a/%id%', collect($cfg['cols'])->firstWhere('colsTipo', 'action')['actionValue']);
    }

    #[Test]
    public function stores_under_the_canonical_runtime_key_not_the_fqcn(): void
    {
        // Pass the FQCN (with backslashes) — the old footgun that produced orphan rows.
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $canonical = ModelKey::canonical(ConfigCmdStub::class); // forward-slash key
        $this->assertDatabaseHas('crud_configs', ['model' => $canonical]);
        // The raw FQCN key (backslashes) must NOT be what got stored.
        $this->assertDatabaseMissing('crud_configs', ['model' => ConfigCmdStub::class]);
        $this->assertStringContainsString('/', $canonical);
    }

    #[Test]
    public function dry_run_does_not_persist(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--non-interactive' => true,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('crud_configs', ['model' => ModelKey::canonical(ConfigCmdStub::class)]);
    }

    #[Test]
    public function invalid_model_is_rejected(): void
    {
        $this->artisan('ptah:config', [
            'model' => 'App\\Models\\DoesNotExist',
            '--non-interactive' => true,
        ])->assertExitCode(1);
    }

    #[Test]
    public function list_option_runs_on_an_existing_config(): void
    {
        // Seed a config first.
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--column' => ['name:text:label=Nome'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--list' => true,
        ])->assertExitCode(0);
    }

    // ── --style (Fase 2.5 Onda II): o gap de integracao apontado em revisao ──

    #[Test]
    public function style_option_persists_into_contition_styles_in_canonical_shape(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--style' => ['status:==:cancelled:background:#FEE2E2;color:#991B1B;'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first();

        $this->assertSame(
            [['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => 'background:#FEE2E2;color:#991B1B;']],
            $cfg->config['contitionStyles'] ?? null,
            'O --style tem de gravar na chave que o runtime le (contitionStyles), no shape canonico.'
        );
    }

    #[Test]
    public function style_option_preserves_pre_existing_rules(): void
    {
        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--style' => ['status:==:cancelled:color:red;'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--style' => ['stock:<:5:color:orange;'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first();
        $fields = array_column($cfg->config['contitionStyles'], 'field');

        $this->assertSame(['status', 'stock'], $fields, 'A segunda execucao nao pode apagar a regra da primeira.');
    }

    #[Test]
    public function an_invalid_style_operator_aborts_even_under_dry_run(): void
    {
        // Kernel::call() (usado pelo $this->artisan() de teste) desabilita o
        // catchExceptions do console — em uso real (php artisan) a mesma
        // excecao vira exit 1 via Kernel::handle(). Por isso o assert aqui e
        // expectException, nao assertExitCode (nota do revisor, Onda II).
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/==/');

        $this->artisan('ptah:config', [
            'model' => ConfigCmdStub::class,
            '--style' => ['status:LIKE:x:color:red;'],
            '--dry-run' => true,
            '--non-interactive' => true,
        ]);
    }
}

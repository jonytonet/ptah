<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
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
            '--set' => ['itemsPerPage=15', 'cacheEnabled=true'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(ConfigCmdStub::class))->first()->config;

        $this->assertSame(15, $cfg['itemsPerPage']); // numeric cast
        $this->assertTrue($cfg['cacheEnabled']);      // 'true' → bool
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
}

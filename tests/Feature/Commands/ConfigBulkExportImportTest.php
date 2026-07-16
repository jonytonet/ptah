<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

/**
 * Covers ptah:config:export-all / import-all — the git-friendly, whole-set config
 * lifecycle (snapshot to a versionable dir, rebuild on a fresh DB).
 */
class ConfigBulkExportImportTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/ptah-cfg-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function seedConfig(string $model, string $route, array $config): void
    {
        CrudConfig::create(['model' => $model, 'route' => $route, 'config' => $config]);
    }

    #[Test]
    public function export_writes_one_json_per_config(): void
    {
        $this->seedConfig('Catalog/Product', '', ['cols' => [['colsNomeFisico' => 'name']]]);
        $this->seedConfig('Catalog/Product', 'invoices', ['cols' => [['colsNomeFisico' => 'total']]]);

        $this->artisan('ptah:config:export-all', ['path' => $this->dir])->assertExitCode(0);

        $this->assertFileExists($this->dir.'/Catalog.Product.json');
        $this->assertFileExists($this->dir.'/Catalog.Product__invoices.json');

        $payload = json_decode((string) File::get($this->dir.'/Catalog.Product.json'), true);
        $this->assertSame('Catalog/Product', $payload['model']);
        $this->assertSame('', $payload['route']);
        $this->assertSame('name', $payload['config']['cols'][0]['colsNomeFisico']);
    }

    #[Test]
    public function import_rebuilds_configs_from_the_directory(): void
    {
        $this->seedConfig('Catalog/Product', '', ['cols' => [['colsNomeFisico' => 'name']]]);
        $this->seedConfig('Sales/Order', '', ['cols' => [['colsNomeFisico' => 'id']]]);

        $this->artisan('ptah:config:export-all', ['path' => $this->dir])->assertExitCode(0);

        // Wipe the table (disposable :memory: DB) and rebuild from the export.
        DB::table('crud_configs')->truncate();
        $this->assertSame(0, CrudConfig::count());

        $this->artisan('ptah:config:import-all', ['path' => $this->dir])->assertExitCode(0);

        $this->assertDatabaseHas('crud_configs', ['model' => 'Catalog/Product']);
        $this->assertDatabaseHas('crud_configs', ['model' => 'Sales/Order']);
        $this->assertSame(2, CrudConfig::count());
    }

    #[Test]
    public function import_canonicalises_a_legacy_fqcn_export(): void
    {
        // A hand-written / legacy export keyed by the FQCN must not reintroduce an orphan.
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/legacy.json', json_encode([
            'model' => 'App\\Models\\Catalog\\Product',
            'route' => '',
            'config' => ['cols' => [['colsNomeFisico' => 'name']]],
        ]));

        $this->artisan('ptah:config:import-all', ['path' => $this->dir])->assertExitCode(0);

        $this->assertDatabaseHas('crud_configs', ['model' => 'Catalog/Product']);
        $this->assertDatabaseMissing('crud_configs', ['model' => 'App\\Models\\Catalog\\Product']);
    }

    #[Test]
    public function import_from_a_missing_directory_fails_cleanly(): void
    {
        $this->artisan('ptah:config:import-all', ['path' => $this->dir.'/nope'])->assertExitCode(1);
    }
}

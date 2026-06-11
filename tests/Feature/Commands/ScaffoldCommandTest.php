<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

/**
 * End-to-end tests for ptah:forge — the heart of the product.
 *
 * Every write target (ptah.paths.*, routes, AppServiceProvider, migrations)
 * is redirected to a per-test temp dir; --no-menu avoids the MenuRegistry
 * dependency, which has its own command (ptah:menu-sync).
 */
class ScaffoldCommandTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-forge-'.uniqid();

        // Redirect every filesystem target the command touches.
        $this->app->setBasePath($this->tmpPath);           // routes/, database/
        $this->app->useAppPath($this->tmpPath.'/app');     // Providers/
        $this->app->useDatabasePath($this->tmpPath.'/database');

        config([
            'ptah.paths.models' => $this->tmpPath.'/app/Models',
            'ptah.paths.services' => $this->tmpPath.'/app/Services',
            'ptah.paths.repositories' => $this->tmpPath.'/app/Repositories',
            'ptah.paths.dtos' => $this->tmpPath.'/app/DTOs',
            'ptah.paths.requests' => $this->tmpPath.'/app/Http/Requests',
            'ptah.paths.resources' => $this->tmpPath.'/app/Http/Resources',
            'ptah.paths.controllers' => $this->tmpPath.'/app/Http/Controllers',
            'ptah.paths.views' => $this->tmpPath.'/resources/views',
        ]);

        $this->files->ensureDirectoryExists($this->tmpPath.'/routes');
        $this->files->ensureDirectoryExists($this->tmpPath.'/database/migrations');
        $this->files->ensureDirectoryExists($this->tmpPath.'/app/Providers');

        // getNamespace() resolves the root namespace from base_path('composer.json').
        $this->files->put($this->tmpPath.'/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ]));

        $this->files->put($this->tmpPath.'/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($this->tmpPath.'/routes/api.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($this->tmpPath.'/app/Providers/AppServiceProvider.php', <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
PHP);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    // ── Entity syntax validation ─────────────────────────────────────────────

    #[Test]
    public function it_rejects_an_entity_with_an_option_glued_to_it(): void
    {
        // Common mistake: -fields glued to the entity instead of --fields.
        $this->artisan('ptah:forge', [
            'entity' => 'Clients/Client-fields=name:string',
            '--no-menu' => true,
        ])->assertFailed();

        // Nothing may have been generated.
        $this->assertFileDoesNotExist($this->tmpPath.'/app/Models/Client.php');
    }

    // ── Full generation (web mode) ───────────────────────────────────────────

    #[Test]
    public function it_generates_the_full_web_artefact_set(): void
    {
        $this->artisan('ptah:forge', [
            'entity' => 'Gadget',
            '--fields' => 'name:string,price:decimal(10,2):nullable,category_id:unsignedBigInteger',
            '--no-menu' => true,
        ])->assertSuccessful();

        // Files on disk
        $this->assertFileExists($this->tmpPath.'/app/Models/Gadget.php');
        $this->assertFileExists($this->tmpPath.'/app/DTOs/GadgetDTO.php');
        $this->assertFileExists($this->tmpPath.'/app/Repositories/GadgetRepository.php');
        $this->assertFileExists($this->tmpPath.'/app/Repositories/Contracts/GadgetRepositoryInterface.php');
        $this->assertFileExists($this->tmpPath.'/app/Services/GadgetService.php');
        $this->assertFileExists($this->tmpPath.'/app/Http/Requests/StoreGadgetRequest.php');
        $this->assertFileExists($this->tmpPath.'/app/Http/Requests/UpdateGadgetRequest.php');
        $this->assertFileExists($this->tmpPath.'/app/Http/Resources/GadgetResource.php');
        $this->assertFileExists($this->tmpPath.'/resources/views/gadget/index.blade.php');

        // Migration created
        $migrations = glob($this->tmpPath.'/database/migrations/*create_gadgets_table*');
        $this->assertNotEmpty($migrations, 'Migration for gadgets table must be created');

        // Route appended
        $this->assertStringContainsString(
            "'gadget'",
            (string) file_get_contents($this->tmpPath.'/routes/web.php'),
        );

        // Binding injected
        $this->assertStringContainsString(
            'GadgetRepositoryInterface::class, GadgetRepository::class',
            (string) file_get_contents($this->tmpPath.'/app/Providers/AppServiceProvider.php'),
        );

        // CrudConfig row persisted
        $this->assertNotNull(CrudConfig::where('model', 'Gadget')->first());
    }

    #[Test]
    public function it_supports_subfolder_entities(): void
    {
        $this->artisan('ptah:forge', [
            'entity' => 'Catalog/Sku',
            '--fields' => 'code:string',
            '--no-menu' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->tmpPath.'/app/Models/Catalog/Sku.php');
        $this->assertFileExists($this->tmpPath.'/app/Services/Catalog/SkuService.php');

        $content = (string) file_get_contents($this->tmpPath.'/app/Models/Catalog/Sku.php');
        $this->assertStringContainsString('namespace App\Models\Catalog;', $content);

        // CrudConfig identifier includes the subfolder
        $this->assertNotNull(CrudConfig::where('model', 'Catalog/Sku')->first());
    }

    // ── Flags ────────────────────────────────────────────────────────────────

    #[Test]
    public function no_soft_deletes_flag_propagates_to_model_and_migration(): void
    {
        $this->artisan('ptah:forge', [
            'entity' => 'Plain',
            '--fields' => 'name:string',
            '--no-soft-deletes' => true,
            '--no-menu' => true,
        ])->assertSuccessful();

        $model = (string) file_get_contents($this->tmpPath.'/app/Models/Plain.php');
        $this->assertStringNotContainsString('SoftDeletes', $model);

        $migration = (string) file_get_contents((glob($this->tmpPath.'/database/migrations/*create_plains_table*'))[0]);
        $this->assertStringNotContainsString('softDeletes', $migration);
    }

    #[Test]
    public function api_only_mode_skips_views_and_crud_config(): void
    {
        $this->artisan('ptah:forge', [
            'entity' => 'Sensor',
            '--fields' => 'serial:string',
            '--api-only' => true,
            '--no-menu' => true,
        ])->assertSuccessful();

        // API artefacts present
        $this->assertFileExists($this->tmpPath.'/app/Http/Requests/API/CreateSensorApiRequest.php');
        $this->assertFileExists($this->tmpPath.'/app/Http/Requests/API/UpdateSensorApiRequest.php');

        // Web artefacts absent
        $this->assertFileDoesNotExist($this->tmpPath.'/resources/views/sensor/index.blade.php');
        $this->assertFileDoesNotExist($this->tmpPath.'/app/Http/Requests/StoreSensorRequest.php');
        $this->assertNull(CrudConfig::where('model', 'Sensor')->first());

        // API route in api.php
        $this->assertStringContainsString(
            "Route::apiResource('sensors'",
            (string) file_get_contents($this->tmpPath.'/routes/api.php'),
        );
    }

    #[Test]
    public function force_asks_for_confirmation_and_aborts_on_decline(): void
    {
        $this->artisan('ptah:forge', [
            'entity' => 'Risky',
            '--force' => true,
            '--no-menu' => true,
        ])
            ->expectsConfirmation('Continue?', 'no')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->tmpPath.'/app/Models/Risky.php');
    }

    #[Test]
    public function existing_files_are_skipped_without_force(): void
    {
        $args = ['entity' => 'Twice', '--fields' => 'name:string', '--no-menu' => true];

        $this->artisan('ptah:forge', $args)->assertSuccessful();

        // Mark the model so we can detect an overwrite.
        $modelPath = $this->tmpPath.'/app/Models/Twice.php';
        $this->files->append($modelPath, "\n// custom marker\n");

        $this->artisan('ptah:forge', $args)->assertSuccessful();

        $this->assertStringContainsString(
            '// custom marker',
            (string) file_get_contents($modelPath),
            'Without --force, existing files must never be overwritten',
        );
    }

    #[Test]
    public function acronyms_produce_a_single_snake_case_word(): void
    {
        $this->artisan('ptah:forge', [
            'entity' => 'POSSale',
            '--fields' => 'total:decimal(10,2)',
            '--no-menu' => true,
        ])->assertSuccessful();

        // pos_sale, not p_o_s_sale
        $migrations = glob($this->tmpPath.'/database/migrations/*create_pos_sales_table*');
        $this->assertNotEmpty($migrations, 'POSSale must map to pos_sales table');
    }
}

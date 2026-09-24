<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\EntityFieldEditor;
use Ptah\Tests\TestCase;

/**
 * `ptah:field` edits the files ptah:forge REALLY generates — each test forges
 * the entity first, so a change to a stub that moves an anchor breaks here,
 * not in a host project. And every edited file must still parse.
 */
class FieldCommandTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-field-'.uniqid();

        $this->app->setBasePath($this->tmpPath);
        $this->app->useAppPath($this->tmpPath.'/app');
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
        $this->files->put($this->tmpPath.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
        $this->files->put($this->tmpPath.'/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($this->tmpPath.'/routes/api.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($this->tmpPath.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n        //\n    }\n\n    public function boot(): void\n    {\n        //\n    }\n}\n");

        $this->artisan('ptah:forge', [
            'entity' => 'Catalog/Product',
            '--fields' => 'name:string,price:decimal,notes:text:nullable',
            '--no-menu' => true,
        ])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);

        parent::tearDown();
    }

    private function read(string $relative): string
    {
        return $this->files->get($this->tmpPath.'/'.$relative);
    }

    private function assertParses(string $relative): void
    {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($this->tmpPath.'/'.$relative).' 2>&1', $out, $code);

        $this->assertSame(0, $code, "{$relative} nao compila depois da edicao:\n".implode("\n", $out));
    }

    #[Test]
    public function add_edits_every_generated_file_and_they_still_parse(): void
    {
        $this->artisan('ptah:field', ['entity' => 'Catalog/Product', 'action' => 'add', 'definition' => ['discount:decimal(5,2)', 'nullable']])
            ->assertExitCode(0);

        $model = $this->read('app/Models/Catalog/Product.php');
        $this->assertMatchesRegularExpression("/'notes',\s*'discount',\s*'created_by'/", $model, 'discount deveria entrar no $fillable antes dos campos de auditoria.');
        $this->assertStringContainsString("'discount' => 'decimal:2'", $model);

        $this->assertStringContainsString("'discount' => 'nullable|numeric',", $this->read('app/Http/Requests/Catalog/StoreProductRequest.php'));
        $this->assertStringContainsString("'discount' => 'sometimes|nullable|numeric',", $this->read('app/Http/Requests/Catalog/UpdateProductRequest.php'));

        $dto = $this->read('app/DTOs/Catalog/ProductDTO.php');
        $this->assertStringContainsString('public readonly ?float $discount = null,', $dto);
        $this->assertStringContainsString("discount: \$data['discount'] ?? null,", $dto);

        $migrations = glob($this->tmpPath.'/database/migrations/*_add_discount_to_products_table.php');
        $this->assertCount(1, $migrations);
        $this->assertStringContainsString("\$table->decimal('discount', 5, 2)->nullable();", (string) file_get_contents($migrations[0]));

        foreach (['app/Models/Catalog/Product.php', 'app/Http/Requests/Catalog/StoreProductRequest.php', 'app/Http/Requests/Catalog/UpdateProductRequest.php', 'app/DTOs/Catalog/ProductDTO.php', 'database/migrations/'.basename($migrations[0])] as $file) {
            $this->assertParses($file);
        }

        $cols = array_column(CrudConfig::where('model', 'Catalog/Product')->first()->config['cols'], 'colsNomeFisico');
        $this->assertContains('discount', $cols, 'A coluna deveria entrar na crud_config da tela.');
    }

    #[Test]
    public function a_required_dto_property_goes_before_the_optional_ones(): void
    {
        $this->artisan('ptah:field', ['entity' => 'Catalog/Product', 'action' => 'add', 'definition' => ['sku:string(40)']])
            ->assertExitCode(0);

        $dto = $this->read('app/DTOs/Catalog/ProductDTO.php');
        $this->assertLessThan(strpos($dto, '$notes = null'), strpos($dto, 'string $sku,'), 'Parametro obrigatorio depois de opcional e deprecated no PHP 8.');
        $this->assertParses('app/DTOs/Catalog/ProductDTO.php');
        $this->assertStringContainsString("'sku' => 'required|string|max:40',", $this->read('app/Http/Requests/Catalog/StoreProductRequest.php'));
    }

    #[Test]
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $args = ['entity' => 'Catalog/Product', 'action' => 'add', 'definition' => ['discount:decimal', 'nullable']];
        $this->artisan('ptah:field', $args)->assertExitCode(0);
        $model = $this->read('app/Models/Catalog/Product.php');

        $this->artisan('ptah:field', $args)
            ->expectsOutputToContain('already')
            ->assertExitCode(0);

        $this->assertSame($model, $this->read('app/Models/Catalog/Product.php'));
        $this->assertCount(1, glob($this->tmpPath.'/database/migrations/*_add_discount_to_products_table.php'));
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $model = $this->read('app/Models/Catalog/Product.php');

        $this->artisan('ptah:field', ['entity' => 'Catalog/Product', 'action' => 'add', 'definition' => ['discount:decimal'], '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame($model, $this->read('app/Models/Catalog/Product.php'));
        $this->assertSame([], glob($this->tmpPath.'/database/migrations/*_add_discount_*') ?: []);
    }

    #[Test]
    public function only_add_is_accepted(): void
    {
        $this->artisan('ptah:field', ['entity' => 'Catalog/Product', 'action' => 'drop', 'definition' => ['notes']])
            ->expectsOutputToContain('Only "add" is supported')
            ->assertExitCode(1);
    }

    #[Test]
    public function an_unknown_entity_fails_loud(): void
    {
        $this->artisan('ptah:field', ['entity' => 'Nope', 'action' => 'add', 'definition' => ['x:string']])
            ->expectsOutputToContain('Model not found')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_hand_edited_file_is_reported_not_guessed(): void
    {
        $this->assertNull(EntityFieldEditor::addToFillable("<?php\nclass X { protected \$guarded = []; }", 'x'));
    }
}

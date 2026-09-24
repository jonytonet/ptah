<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\BlueprintPlan;
use Ptah\Tests\TestCase;

/**
 * `ptah:blueprint` — the plan is a pure function of the spec, and the run is
 * that plan executed. The order is what matters most: a parent after its
 * child breaks the FK import, the migration's constraint and the factory.
 */
class BlueprintCommandTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-blueprint-'.uniqid();
        $this->files->ensureDirectoryExists($this->tmpPath);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);

        parent::tearDown();
    }

    private function spec(array $spec): string
    {
        $path = $this->tmpPath.'/spec.json';
        $this->files->put($path, (string) json_encode($spec));

        return $path;
    }

    // ── The plan ──────────────────────────────────────────────────────────

    #[Test]
    public function parents_are_forged_before_their_children_whatever_the_spec_order(): void
    {
        $order = BlueprintPlan::order(BlueprintPlan::entities(['entities' => [
            'OrderItem' => ['fields' => 'order_id:unsignedBigInteger,product_id:unsignedBigInteger,qty:integer'],
            'Order' => ['fields' => 'customer_id:unsignedBigInteger'],
            'Product' => ['fields' => 'name:string'],
            'Customer' => ['fields' => 'name:string'],
        ]]));

        $pos = array_flip($order);
        $this->assertLessThan($pos['Order'], $pos['Customer']);
        $this->assertLessThan($pos['OrderItem'], $pos['Order']);
        $this->assertLessThan($pos['OrderItem'], $pos['Product']);
    }

    #[Test]
    public function a_foreign_key_cycle_is_named_not_half_built(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign-key cycle between entities: A → B → A');

        BlueprintPlan::order(BlueprintPlan::entities(['entities' => [
            'A' => ['fields' => 'b_id:unsignedBigInteger'],
            'B' => ['fields' => 'a_id:unsignedBigInteger'],
        ]]));
    }

    #[Test]
    public function the_plan_chains_forge_migrate_config_menu_permissions_and_seed(): void
    {
        $steps = BlueprintPlan::steps([
            'module' => 'Catalog',
            'role' => 'admin',
            'grant' => 'all',
            'seed' => true,
            'entities' => [
                'Product' => ['fields' => ['name:string', 'category_id:unsignedBigInteger'], 'columns' => ['name:text:label=Nome']],
                'Category' => ['fields' => 'name:string', 'factory' => false],
            ],
        ]);

        $this->assertSame([
            'forge Catalog/Category',
            'forge Catalog/Product',
            'migrate',
            'config Catalog/Product',
            'menu-sync',
            'permission:sync --role=admin',
            'seed Catalog/Product',
        ], array_column($steps, 'label'));

        $this->assertSame(['name:text:label=Nome'], $steps[3]['args']['--column']);
        $this->assertTrue($steps[1]['args']['--factory']);
        $this->assertArrayNotHasKey('--factory', $steps[0]['args']);
        $this->assertSame('Database\\Seeders\\Catalog\\ProductSeeder', $steps[6]['args']['--class']);
    }

    #[Test]
    public function an_invalid_spec_says_what_is_wrong(): void
    {
        foreach ([
            [[], '"entities"'],
            [['entities' => ['product' => ['fields' => 'x:string']]], 'PascalCase'],
            [['entities' => ['Product' => []]], '"fields" is empty'],
        ] as [$spec, $message]) {
            try {
                BlueprintPlan::steps($spec);
                $this->fail("Deveria rejeitar: {$message}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    // ── The command ───────────────────────────────────────────────────────

    #[Test]
    public function dry_run_prints_the_exact_commands(): void
    {
        $this->artisan('ptah:blueprint', ['spec' => $this->spec(['entities' => ['Tag' => ['fields' => 'name:string']]]), '--dry-run' => true])
            ->expectsOutputToContain('php artisan ptah:forge Tag --fields="name:string" --factory')
            ->assertExitCode(0);

        $this->assertSame(0, CrudConfig::count(), 'O dry-run executou algo.');
    }

    #[Test]
    public function it_refuses_production_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('ptah:blueprint', ['spec' => $this->spec(['entities' => ['Tag' => ['fields' => 'name:string']]])])
            ->expectsOutputToContain('refused in production')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_broken_spec_file_fails_loud(): void
    {
        $path = $this->tmpPath.'/bad.json';
        $this->files->put($path, '{"entities": ');

        $this->artisan('ptah:blueprint', ['spec' => $path])
            ->expectsOutputToContain('not valid JSON')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_real_run_builds_the_module(): void
    {
        $app = $this->tmpPath.'/app-root';
        $this->app->setBasePath($app);
        $this->app->useAppPath($app.'/app');
        $this->app->useDatabasePath($app.'/database');
        config([
            'ptah.paths.models' => $app.'/app/Models',
            'ptah.paths.services' => $app.'/app/Services',
            'ptah.paths.repositories' => $app.'/app/Repositories',
            'ptah.paths.dtos' => $app.'/app/DTOs',
            'ptah.paths.requests' => $app.'/app/Http/Requests',
            'ptah.paths.resources' => $app.'/app/Http/Resources',
            'ptah.paths.controllers' => $app.'/app/Http/Controllers',
            'ptah.paths.views' => $app.'/resources/views',
        ]);
        foreach (['routes', 'database/migrations', 'database/seeders', 'app/Providers'] as $dir) {
            $this->files->ensureDirectoryExists("{$app}/{$dir}");
        }
        $this->files->put($app.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
        $this->files->put($app.'/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($app.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n        //\n    }\n\n    public function boot(): void\n    {\n        //\n    }\n}\n");

        // O host carrega App\ e Database\ pelo Composer; aqui, o mesmo loader.
        $loader = new ClassLoader;
        $loader->addPsr4('App\\', $app.'/app');
        $loader->addPsr4('Database\\Factories\\', $app.'/database/factories');
        $loader->addPsr4('Database\\Seeders\\', $app.'/database/seeders');
        $loader->register(true);

        try {
            $this->artisan('ptah:blueprint', ['spec' => $this->spec([
                'module' => 'Bp',
                'seed' => true,
                'entities' => [
                    'BpItem' => ['fields' => 'title:string,bp_box_id:unsignedBigInteger', 'columns' => ['title:text:label=Titulo'], 'menu' => false],
                    'BpBox' => ['fields' => 'label:string(20)', 'menu' => false],
                ],
            ])])
                ->expectsOutputToContain('steps done')
                ->assertExitCode(0);
        } finally {
            $loader->unregister();
        }

        $this->assertTrue(Schema::hasTable('bp_boxes') && Schema::hasTable('bp_items'), 'As migrations nao rodaram.');
        $this->assertSame(10, \DB::table('bp_items')->count(), 'O seeder nao rodou.');
        $this->assertGreaterThanOrEqual(1, \DB::table('bp_boxes')->count(), 'A factory do filho deveria ter criado o pai.');

        $cols = collect(CrudConfig::where('model', 'Bp/BpItem')->first()->config['cols']);
        $this->assertSame('Titulo', $cols->firstWhere('colsNomeFisico', 'title')['colsNomeLogico'], 'O ptah:config do spec nao foi aplicado.');
    }
}

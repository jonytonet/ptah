<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use App\Models\Fab\FabShelf;
use Database\Seeders\Fab\FabBookSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\FieldDefinition;
use Ptah\Support\SchemaInspector;
use Ptah\Tests\TestCase;

/**
 * `ptah:forge --factory` — a factory that WORKS, proved by running it.
 *
 * A generated factory that only looks right fails the first time somebody
 * seeds: a string longer than the column, an enum value the column refuses, a
 * foreign key pointing at nothing. So this forges two related entities, runs
 * the migrations the forge wrote, loads the generated classes and creates
 * records through `Model::factory()` — the call a host project makes.
 */
class ForgeFactoryTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-factory-'.uniqid();

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
        $this->files->put($this->tmpPath.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n        //\n    }\n\n    public function boot(): void\n    {\n        //\n    }\n}\n");
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);

        parent::tearDown();
    }

    #[Test]
    public function the_generated_factories_create_valid_related_records(): void
    {
        $this->artisan('ptah:forge', ['entity' => 'Fab/FabShelf', '--fields' => 'title:string(12),code:string(8):unique', '--no-menu' => true, '--factory' => true])->assertExitCode(0);
        $this->artisan('ptah:forge', ['entity' => 'Fab/FabBook', '--fields' => 'title:string,status:enum(draft|published),price:decimal(6,2),in_stock:boolean,email:string:nullable,fab_shelf_id:unsignedBigInteger', '--no-menu' => true, '--factory' => true])->assertExitCode(0);

        foreach (['FabShelf', 'FabBook'] as $entity) {
            $this->assertFileExists("{$this->tmpPath}/database/factories/Fab/{$entity}Factory.php");
            $this->assertFileExists("{$this->tmpPath}/database/seeders/Fab/{$entity}Seeder.php");
        }

        // A FK resolveu para o model irmao, sem TODO.
        $bookFactory = (string) file_get_contents("{$this->tmpPath}/database/factories/Fab/FabBookFactory.php");
        $this->assertStringContainsString('\\App\\Models\\Fab\\FabShelf::factory()', $bookFactory);
        $this->assertStringNotContainsString('TODO', $bookFactory);

        // Roda as migrations que o forge escreveu, na ordem.
        $migrations = glob("{$this->tmpPath}/database/migrations/*.php");
        sort($migrations);
        foreach ($migrations as $migration) {
            (require $migration)->up();
        }

        foreach ([
            'app/Models/Fab/FabShelf.php', 'app/Models/Fab/FabBook.php',
            'database/factories/Fab/FabShelfFactory.php', 'database/factories/Fab/FabBookFactory.php',
            'database/seeders/Fab/FabBookSeeder.php',
        ] as $file) {
            require_once "{$this->tmpPath}/{$file}";
        }

        /** @var class-string<Model> $book */
        $book = 'App\\Models\\Fab\\FabBook';
        $book::factory()->count(3)->create();

        $this->assertSame(3, $book::query()->count());
        $this->assertSame(1, FabShelf::query()->count(), 'Sem estante, a primeira criada deveria ser reaproveitada pelas seguintes.');
        $this->assertContains($book::query()->first()->status, ['draft', 'published']);

        // O seeder gerado e o que o host chama.
        (new FabBookSeeder)->run();
        $this->assertSame(13, $book::query()->count());
    }

    #[Test]
    public function without_the_flag_nothing_is_generated(): void
    {
        $this->artisan('ptah:forge', ['entity' => 'Plain', '--fields' => 'name:string', '--no-menu' => true])->assertExitCode(0);

        $this->assertDirectoryDoesNotExist("{$this->tmpPath}/database/factories");
    }

    #[Test]
    public function values_respect_the_declared_column(): void
    {
        $parse = fn (string $f): FieldDefinition => (new SchemaInspector)->fromString($f)[0];

        $this->assertSame('substr(fake()->words(2, true), 0, 2)', $parse('uf:char(2)')->fakerExpression());
        $this->assertSame('fake()->unique()->safeEmail()', $parse('email:string:unique')->fakerExpression());
        $this->assertSame("fake()->randomElement(['a', 'b'])", $parse('kind:enum(a|b)')->fakerExpression());
        $this->assertSame('fake()->randomFloat(2, 1, 9999)', $parse('price:decimal(6,2)')->fakerExpression());
        $this->assertSame('null', $parse('owner_id:unsignedBigInteger:nullable')->fakerExpression());
        $this->assertStringContainsString('TODO', $parse('owner_id:unsignedBigInteger')->fakerExpression());
    }
}

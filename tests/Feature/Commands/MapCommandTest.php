<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\ProjectMap;
use Ptah\Tests\TestCase;
use Ptah\Traits\HasAuditFields;

class MapCategory extends Model
{
    protected $table = 'map_categories';

    public function products(): HasMany
    {
        return $this->hasMany(MapProduct::class, 'map_category_id');
    }
}

class MapProduct extends Model
{
    // HasAuditFields traz createdBy/updatedBy/deletedBy: estariam em toda
    // entidade e o mapa os omite (a asserção de relações exatas pega).
    use HasAuditFields, SoftDeletes;

    protected $table = 'map_products';

    public function category(): BelongsTo
    {
        return $this->belongsTo(MapCategory::class, 'map_category_id');
    }

    // Sem tipo de retorno: o mapa nao chama metodos para descobrir o que devolvem.
    public function sneaky()
    {
        throw new \LogicException('o mapa nao deveria ter chamado isto');
    }
}

/**
 * `ptah:map` is the read an agent does at the start of a session instead of
 * opening every model, migration and config. Each assertion is a fact the
 * agent would otherwise have had to open a file to learn.
 */
class MapCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('map_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('map_products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('price', 10, 2)->nullable();
            $t->foreignId('map_category_id');
            $t->timestamps();
            $t->softDeletes();
        });

        $this->dir = sys_get_temp_dir().'/ptah-map-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function map(): array
    {
        return ProjectMap::build([MapCategory::class, MapProduct::class]);
    }

    #[Test]
    public function fields_carry_type_nullability_and_fk_target(): void
    {
        $product = $this->map()['entities'][1];

        $this->assertSame('map_products', $product['table']);
        $this->assertContains('name:varchar', $product['fields']);
        $this->assertContains('price:numeric?', $product['fields']);
        $this->assertContains('map_category_id:integer→MapCategory', $product['fields'], 'A FK deveria apontar para o model da relacao.');
        $this->assertNotContains('created_at:datetime', $product['fields'], 'Colunas gerenciadas sao resumidas, nao listadas.');
        $this->assertTrue($product['soft_deletes']);
    }

    #[Test]
    public function relations_are_read_from_declared_return_types_only(): void
    {
        $entities = $this->map()['entities'];

        $this->assertSame(['products→MapProduct (hasMany)'], $entities[0]['relations']);
        // `sneaky()` sem tipo de retorno lancaria se fosse chamado.
        $this->assertSame(['category→MapCategory (belongsTo)'], $entities[1]['relations']);
    }

    #[Test]
    public function screens_show_route_permission_and_counts(): void
    {
        CrudConfig::create(['model' => 'Catalog/MapProduct', 'route' => 'catalog/products', 'config' => [
            'cols' => [['colsNomeFisico' => 'name'], ['colsNomeFisico' => 'price']],
            'customFilters' => [['field' => 'name']],
            'permissions' => ['permissionIdentifier' => 'catalog.product'],
        ]]);

        $text = ProjectMap::toText($this->map());

        $this->assertStringContainsString('Catalog/MapProduct  route=catalog/products  perm=catalog.product  cols=2  filters=1', $text);
    }

    #[Test]
    public function a_model_without_a_table_is_flagged(): void
    {
        Schema::drop('map_categories');

        $this->assertStringContainsString('map_categories  [NO TABLE]', ProjectMap::toText($this->map()));
    }

    #[Test]
    public function todos_left_by_the_generators_are_listed(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/Thing.php', "<?php\n// TODO: use App\\Models\\Supplier;\nclass Nope {}\n");

        $todos = ProjectMap::build([], $this->dir)['todos'];

        $this->assertCount(1, $todos);
        $this->assertStringContainsString('Thing.php:2  use App\\Models\\Supplier;', $todos[0]);
    }

    #[Test]
    public function models_are_discovered_from_their_namespace_declaration(): void
    {
        $ns = 'PtahMapProbe'.uniqid();
        File::ensureDirectoryExists($this->dir.'/Deep');
        File::put($this->dir.'/Deep/Widget.php', "<?php\nnamespace {$ns}\\Whatever;\nclass Widget extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
        File::put($this->dir.'/NotAModel.php', "<?php\nnamespace {$ns};\nclass NotAModel {}\n");

        $this->assertSame(["{$ns}\\Whatever\\Widget"], ProjectMap::discoverModels($this->dir));
    }

    #[Test]
    public function the_map_is_compact(): void
    {
        // O ponto do comando: caber no inicio de toda sessao.
        $text = ProjectMap::toText($this->map());

        $this->assertLessThan(800, strlen($text));
    }

    #[Test]
    public function the_command_prints_and_writes_the_map(): void
    {
        $target = base_path('.ptah/map.md');
        File::delete($target);

        try {
            $this->artisan('ptah:map', ['--models' => $this->dir, '--write' => true])
                ->expectsOutputToContain('## Entities')
                ->assertExitCode(0);

            $this->assertFileExists($target);
        } finally {
            File::deleteDirectory(base_path('.ptah'));
        }
    }
}

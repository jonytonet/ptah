<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Traits;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Base\BaseRepository;
use Ptah\Base\BaseService;
use Ptah\Tests\TestCase;
use Ptah\Traits\HasCrud;

// ─── Stubs ───────────────────────────────────────────────────────────────────
//
// Class names are prefixed with "Crud" to avoid conflicts with the stubs
// defined in HasAuditFieldsTest.php (same namespace).

/**
 * Minimal Eloquent model for the `items` stub table.
 */
class CrudStubModel extends Model
{
    protected $table    = 'items';
    protected $fillable = ['name', 'status', 'amount'];
    protected $casts    = ['amount' => 'integer'];
}

/** Concrete repository stub for HasCrud delegation tests. */
class CrudStubRepository extends BaseRepository {}

/**
 * Concrete service that uses the HasCrud trait.
 *
 * HasCrud requires a $repository property of type BaseRepositoryInterface.
 * BaseService already provides that property, so we simply extend it and
 * pull in the trait, which is the typical usage pattern in real services.
 */
class CrudStubService extends BaseService
{
    use HasCrud;
}

// ─── Test class ──────────────────────────────────────────────────────────────

/**
 * Unit tests for Ptah\Traits\HasCrud.
 *
 * HasCrud is a thin delegation layer: every method calls the equivalent
 * method on $this->repository. These tests verify end-to-end that the
 * delegation chain actually works, catching any method-name typos or
 * signature mismatches introduced during future refactorings.
 *
 * Coverage:
 *  all()        → delegates to repository→all()
 *  paginate()   → delegates to repository→paginate()
 *  find()       → delegates to repository→find()
 *  findOrFail() → delegates to repository→findOrFail()
 *  create()     → delegates to repository→create()
 *  update()     → delegates to repository→update()
 *  delete()     → delegates to repository→delete()
 */
class HasCrudTest extends TestCase
{
    private CrudStubService $service;

    // ── Environment overrides ─────────────────────────────────────────────────

    protected function getPackageProviders($app): array
    {
        return [];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CrudStubService(
            new CrudStubRepository(new CrudStubModel())
        );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function createItem(string $name = 'Item'): CrudStubModel
    {
        /** @var CrudStubModel $model */
        $model = CrudStubModel::create(['name' => $name, 'status' => 'active', 'amount' => 0]);

        return $model;
    }

    // ── Delegation tests ──────────────────────────────────────────────────────

    #[Test]
    public function all_delega_e_retorna_todos_os_registros(): void
    {
        $this->createItem('A');
        $this->createItem('B');

        $result = $this->service->all();

        $this->assertCount(2, $result);
    }

    #[Test]
    public function paginate_delega_e_retorna_paginator_correto(): void
    {
        foreach (range(1, 5) as $i) {
            $this->createItem("Item {$i}");
        }

        $paginator = $this->service->paginate(3);

        $this->assertSame(3, $paginator->perPage());
        $this->assertSame(5, $paginator->total());
    }

    #[Test]
    public function find_delega_e_retorna_model_ou_null(): void
    {
        $item = $this->createItem('FindMe');

        $this->assertSame('FindMe', $this->service->find($item->id)->name);
        $this->assertNull($this->service->find(9999));
    }

    #[Test]
    public function findOrFail_delega_e_lanca_excecao_para_id_inexistente(): void
    {
        $item = $this->createItem('FailMe');

        $this->assertSame('FailMe', $this->service->findOrFail($item->id)->name);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findOrFail(9999);
    }

    #[Test]
    public function create_delega_e_persiste_registro(): void
    {
        $item = $this->service->create(['name' => 'Criado', 'status' => 'active', 'amount' => 7]);

        $this->assertInstanceOf(Model::class, $item);
        $this->assertNotNull($item->id);
        $this->assertDatabaseHas('items', ['name' => 'Criado', 'amount' => 7]);
    }

    #[Test]
    public function update_delega_e_atualiza_registro(): void
    {
        $item    = $this->createItem('Original');
        $updated = $this->service->update($item->id, ['name' => 'Atualizado']);

        $this->assertSame('Atualizado', $updated->name);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Atualizado']);
    }

    #[Test]
    public function delete_delega_e_remove_registro(): void
    {
        $item   = $this->createItem('Deletar');
        $result = $this->service->delete($item->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }
}

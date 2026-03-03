<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Base\BaseRepository;
use Ptah\Base\BaseService;
use Ptah\Tests\TestCase;

// ─── Stubs ───────────────────────────────────────────────────────────────────
//
// Class names are prefixed with "Svc" to avoid conflicts with the "Repo"
// prefixed stubs defined in BaseRepositoryTest.php (same namespace).

/**
 * Minimal model for `items` table — mirrors RepoStubModel but with a
 * different class name so both test files can coexist in the same process.
 */
class SvcStubModel extends Model
{
    protected $table    = 'items';
    protected $fillable = ['name', 'status', 'amount'];
    protected $casts    = ['amount' => 'integer'];
}

/** Concrete repository stub. */
class SvcStubRepository extends BaseRepository {}

/** Concrete service stub — no extra logic beyond what BaseService provides. */
class SvcStubService extends BaseService {}

// ─── Test class ──────────────────────────────────────────────────────────────

/**
 * Unit tests for Ptah\Base\BaseService.
 *
 * Coverage:
 *  destroy() vs delete():
 *    - destroy() retorna false para ID inexistente (não lança exceção)
 *    - destroy() remove registro existente e retorna true
 *
 *  show():
 *    - retorna Model quando ID existe
 *    - retorna null quando ID não existe
 *
 *  getDados() routing:
 *    - usa advancedSearch quando param 'search' está preenchido (≠ 'Busca')
 *    - usa searchLike quando param 'searchLike' está preenchido (≠ 'Incremental')
 *    - usa findAllFieldsAnd como fallback padrão (sem search/searchLike)
 *    - respeita os parâmetros 'limit' e 'direction'
 *    - sentinel 'Relacao' em 'relations' resulta em array vazio (sem eager load)
 */
class BaseServiceTest extends TestCase
{
    private SvcStubService $service;

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

        $this->service = new SvcStubService(
            new SvcStubRepository(new SvcStubModel())
        );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function createItem(string $name = 'Item', string $status = 'active', int $amount = 0): SvcStubModel
    {
        /** @var SvcStubModel $model */
        $model = SvcStubModel::create(['name' => $name, 'status' => $status, 'amount' => $amount]);

        return $model;
    }

    // ── destroy() semântica ───────────────────────────────────────────────────

    #[Test]
    public function destroy_retorna_false_para_id_inexistente(): void
    {
        // destroy() must NOT throw — it returns false gracefully.
        // This is the key semantic difference from delete(), which throws.
        $result = $this->service->destroy(9999);

        $this->assertFalse($result);
    }

    #[Test]
    public function destroy_remove_registro_existente_e_retorna_true(): void
    {
        $item = $this->createItem('Para destruir');

        $result = $this->service->destroy($item->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    // ── show() ────────────────────────────────────────────────────────────────

    #[Test]
    public function show_retorna_model_quando_id_existe(): void
    {
        $item = $this->createItem('Show Me');

        $found = $this->service->show($item->id);

        $this->assertInstanceOf(Model::class, $found);
        $this->assertSame('Show Me', $found->name);
    }

    #[Test]
    public function show_retorna_null_quando_id_nao_existe(): void
    {
        $this->assertNull($this->service->show(9999));
    }

    // ── getDados() routing ────────────────────────────────────────────────────

    #[Test]
    public function getDados_usa_advancedSearch_quando_param_search_preenchido(): void
    {
        $this->createItem('Alpha');
        $this->createItem('Beta');

        // 'ph' only appears in 'Alpha' — advancedSearch does OR LIKE on all columns
        $request = Request::create('/', 'GET', ['search' => 'ph']);
        $result  = $this->service->getDados($request);

        $this->assertSame(1, $result->total());
        $this->assertSame('Alpha', $result->items()[0]->name);
    }

    #[Test]
    public function getDados_usa_searchLike_quando_param_searchLike_preenchido(): void
    {
        $this->createItem('Barato', 'active', 5);
        $this->createItem('Caro', 'active', 100);

        // searchLike=amount}50 → amount >= 50 → only 'Caro'
        $request = Request::create('/', 'GET', ['searchLike' => 'amount}50']);
        $result  = $this->service->getDados($request);

        $this->assertSame(1, $result->total());
        $this->assertSame('Caro', $result->items()[0]->name);
    }

    #[Test]
    public function getDados_usa_findAllFieldsAnd_como_fallback_padrao(): void
    {
        $this->createItem('Ativo', 'active');
        $this->createItem('Inativo', 'inactive');

        // No search/searchLike → falls back to findAllFieldsAnd
        // Passing status=active as a plain query param should filter
        $request = Request::create('/', 'GET', ['status' => 'active']);
        $result  = $this->service->getDados($request);

        $this->assertSame(1, $result->total());
        $this->assertSame('Ativo', $result->items()[0]->name);
    }

    #[Test]
    public function getDados_respeita_limit_e_direction(): void
    {
        $this->createItem('Z', 'active', 30);
        $this->createItem('A', 'active', 10);
        $this->createItem('M', 'active', 20);

        // limit=2 direction=ASC order=name → first two alphabetically
        $request = Request::create('/', 'GET', [
            'limit'     => '2',
            'order'     => 'name',
            'direction' => 'ASC',
        ]);
        $result = $this->service->getDados($request);

        $this->assertSame(2, $result->perPage());
        $this->assertSame(3, $result->total());
        $this->assertSame('A', $result->items()[0]->name);
        $this->assertSame('M', $result->items()[1]->name);
    }

    #[Test]
    public function getDados_sentinel_relacao_nao_gera_eager_load(): void
    {
        $this->createItem('Foo');

        // 'Relacao' is the UI sentinel — must be treated as no relations
        $request = Request::create('/', 'GET', ['relations' => 'Relacao']);
        $result  = $this->service->getDados($request);

        // No exception should be thrown (invalid relation name would throw)
        $this->assertSame(1, $result->total());
    }
}

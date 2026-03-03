<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Base\BaseRepository;
use Ptah\Tests\TestCase;

// ─── Stub Model ──────────────────────────────────────────────────────────────

/**
 * Minimal Eloquent model that targets the `items` stub table.
 * Named with the "Repo" prefix to avoid class-name clashes with other
 * test files that may define their own stubs in the same namespace.
 */
class RepoStubModel extends Model
{
    protected $table    = 'items';
    protected $fillable = ['name', 'status', 'amount'];
    protected $casts    = ['amount' => 'integer'];
}

// ─── Stub Repository ─────────────────────────────────────────────────────────

/**
 * Thin concrete subclass of BaseRepository so we can instantiate it.
 * No extra logic — every method is inherited from the abstract base.
 */
class RepoStubRepository extends BaseRepository {}

// ─── Test class ──────────────────────────────────────────────────────────────

/**
 * Unit tests for Ptah\Base\BaseRepository.
 *
 * Coverage:
 *  CRUD básico:
 *    - all()          → Collection vazia / com registros
 *    - paginate()     → LengthAwarePaginator com perPage correto
 *    - find()         → null para ID inexistente / Model correto
 *    - findOrFail()   → ModelNotFoundException / Model correto
 *    - create()       → persiste e retorna Model
 *    - update()       → fresh após atualização / ModelNotFoundException
 *    - delete()       → remove registro / ModelNotFoundException
 *
 *  findBy multi-assinatura:
 *    - string simples, array de wheres, Closure
 *
 *  findByIn:
 *    - filtra apenas registros cujo campo está no array
 *
 *  allQuery:
 *    - skip + limit respeitam o offset
 *
 *  searchLike parsers:
 *    - operador }  (>=)
 *    - operador {  (<=)
 *    - sentinel 'Incremental' não aplica filtros
 *    - param whereIn filtra por lista de valores
 *    - param additionalQueries aplica where extra
 *
 *  advancedSearch:
 *    - sentinel 'Busca' não aplica filtros
 *    - termo real faz OR LIKE em todas as colunas
 *
 *  Utilitários de escrita:
 *    - updateBatch() → int linhas afetadas
 *    - createQuietly() → sem disparar evento creating
 *    - updateQuietly() → sem disparar evento updating
 *    - replicate()    → cópia não persistida
 */
class BaseRepositoryTest extends TestCase
{
    private RepoStubRepository $repo;

    // ── Environment overrides ─────────────────────────────────────────────────

    /**
     * Only the minimal providers needed for BaseRepository.
     * PtahServiceProvider is excluded to avoid registering Ptah's own
     * migrations, which would create unnecessary tables and ordering issues.
     */
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

    /**
     * Only load the shared test migrations (users + items).
     * Ptah package migrations are not needed for BaseRepository tests.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new RepoStubRepository(new RepoStubModel());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createItem(string $name = 'Item', string $status = 'active', int $amount = 0): RepoStubModel
    {
        /** @var RepoStubModel $model */
        $model = $this->repo->create(['name' => $name, 'status' => $status, 'amount' => $amount]);

        return $model;
    }

    // ── CRUD básico ───────────────────────────────────────────────────────────

    #[Test]
    public function all_retorna_collection_vazia_quando_tabela_zerada(): void
    {
        $this->assertCount(0, $this->repo->all());
    }

    #[Test]
    public function all_retorna_todos_os_registros(): void
    {
        $this->createItem('A');
        $this->createItem('B');
        $this->createItem('C');

        $this->assertCount(3, $this->repo->all());
    }

    #[Test]
    public function paginate_retorna_instancia_com_per_page_correto(): void
    {
        foreach (range(1, 20) as $i) {
            $this->createItem("Item {$i}");
        }

        $paginator = $this->repo->paginate(7);

        $this->assertSame(7, $paginator->perPage());
        $this->assertSame(20, $paginator->total());
        $this->assertCount(7, $paginator->items());
    }

    #[Test]
    public function find_retorna_null_para_id_inexistente(): void
    {
        $this->assertNull($this->repo->find(9999));
    }

    #[Test]
    public function find_retorna_model_pelo_id(): void
    {
        $item = $this->createItem('Encontrar');

        $found = $this->repo->find($item->id);

        $this->assertInstanceOf(Model::class, $found);
        $this->assertSame('Encontrar', $found->name);
    }

    #[Test]
    public function findOrFail_lanca_ModelNotFoundException_para_id_inexistente(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->findOrFail(9999);
    }

    #[Test]
    public function findOrFail_retorna_model_existente(): void
    {
        $item = $this->createItem('Achar');

        $found = $this->repo->findOrFail($item->id);

        $this->assertSame('Achar', $found->name);
    }

    #[Test]
    public function create_persiste_registro_e_retorna_model_com_id(): void
    {
        $item = $this->createItem('Novo', 'active', 42);

        $this->assertNotNull($item->id);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Novo', 'amount' => 42]);
    }

    #[Test]
    public function update_persiste_alteracao_e_retorna_registro_fresh(): void
    {
        $item = $this->createItem('Antigo');

        $updated = $this->repo->update($item->id, ['name' => 'Novo Nome']);

        $this->assertSame('Novo Nome', $updated->name);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Novo Nome']);
    }

    #[Test]
    public function update_lanca_ModelNotFoundException_para_id_inexistente(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->update(9999, ['name' => 'X']);
    }

    #[Test]
    public function delete_remove_registro_existente(): void
    {
        $item = $this->createItem('Deletar');

        $result = $this->repo->delete($item->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    #[Test]
    public function delete_lanca_ModelNotFoundException_para_id_inexistente(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->delete(9999);
    }

    // ── findBy multi-assinatura ───────────────────────────────────────────────

    #[Test]
    public function findBy_string_simples_filtra_por_coluna_e_valor(): void
    {
        $this->createItem('Ativo', 'active');
        $this->createItem('Inativo', 'inactive');

        $results = $this->repo->findBy('status', 'active')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Ativo', $results->first()->name);
    }

    #[Test]
    public function findBy_array_filtra_multiplas_colunas_com_and(): void
    {
        $this->createItem('A', 'active', 10);
        $this->createItem('B', 'active', 20);
        $this->createItem('C', 'inactive', 10);

        $results = $this->repo->findBy(['status' => 'active', 'amount' => 10])->get();

        $this->assertCount(1, $results);
        $this->assertSame('A', $results->first()->name);
    }

    #[Test]
    public function findBy_closure_aplica_where_customizado(): void
    {
        $this->createItem('Alpha', 'active', 50);
        $this->createItem('Beta', 'active', 5);

        $results = $this->repo
            ->findBy(fn ($q) => $q->where('amount', '>', 10))
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results->first()->name);
    }

    // ── findByIn ──────────────────────────────────────────────────────────────

    #[Test]
    public function findByIn_retorna_apenas_registros_cujo_campo_esta_no_array(): void
    {
        $this->createItem('A1', 'active');
        $this->createItem('B1', 'inactive');
        $this->createItem('C1', 'deleted');

        $results = $this->repo->findByIn('status', ['active', 'inactive']);

        $this->assertCount(2, $results);
        $statuses = $results->pluck('status')->all();
        $this->assertContains('active', $statuses);
        $this->assertContains('inactive', $statuses);
        $this->assertNotContains('deleted', $statuses);
    }

    // ── allQuery ──────────────────────────────────────────────────────────────

    #[Test]
    public function allQuery_com_skip_e_limit_respeita_offset(): void
    {
        foreach (range(1, 5) as $i) {
            $this->createItem("Item {$i}", 'active', $i * 10);
        }

        // Skip 2, limit 2 → records 3 and 4 (ordered by primary key ascending)
        $results = $this->repo
            ->allQuery([], 2, 2)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $results);
        $this->assertSame('Item 3', $results->first()->name);
        $this->assertSame('Item 4', $results->last()->name);
    }

    // ── searchLike parsers ────────────────────────────────────────────────────

    #[Test]
    public function searchLike_operador_maior_igual_filtra_corretamente(): void
    {
        $this->createItem('Barato', 'active', 5);
        $this->createItem('Caro', 'active', 100);

        // Syntax: "amount}7" means amount >= 7
        $request = Request::create('/', 'GET', ['searchLike' => 'amount}7']);
        $results = $this->repo->searchLike($request)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Caro', $results->first()->name);
    }

    #[Test]
    public function searchLike_operador_menor_igual_filtra_corretamente(): void
    {
        $this->createItem('Barato', 'active', 5);
        $this->createItem('Caro', 'active', 100);

        // Syntax: "amount{6" means amount <= 6
        $request = Request::create('/', 'GET', ['searchLike' => 'amount{6']);
        $results = $this->repo->searchLike($request)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Barato', $results->first()->name);
    }

    #[Test]
    public function searchLike_sentinel_incremental_nao_aplica_filtros(): void
    {
        $this->createItem('X');
        $this->createItem('Y');

        // 'Incremental' is the UI default — must not filter anything
        $request = Request::create('/', 'GET', ['searchLike' => 'Incremental']);
        $results = $this->repo->searchLike($request)->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function searchLike_param_whereIn_filtra_por_lista_de_valores(): void
    {
        $this->createItem('A', 'active');
        $this->createItem('B', 'inactive');
        $this->createItem('C', 'archived');

        // whereIn=status:active,inactive filters status IN ('active', 'inactive')
        $request = Request::create('/', 'GET', [
            'searchLike' => 'Incremental',
            'whereIn'    => 'status:active,inactive',
        ]);
        $results = $this->repo->searchLike($request)->get();

        $this->assertCount(2, $results);
        $statuses = $results->pluck('status')->sort()->values()->all();
        $this->assertSame(['active', 'inactive'], $statuses);
    }

    #[Test]
    public function searchLike_param_additionalQueries_aplica_where_extra(): void
    {
        $this->createItem('P', 'active', 10);
        $this->createItem('Q', 'inactive', 10);
        $this->createItem('R', 'active', 1);

        // additionalQueries=status:=:active;amount:>:5  → active AND amount > 5
        $request = Request::create('/', 'GET', [
            'searchLike'        => 'Incremental',
            'additionalQueries' => 'status:=:active;amount:>:5',
        ]);
        $results = $this->repo->searchLike($request)->get();

        $this->assertCount(1, $results);
        $this->assertSame('P', $results->first()->name);
    }

    // ── advancedSearch ────────────────────────────────────────────────────────

    #[Test]
    public function advancedSearch_sentinel_busca_nao_aplica_filtros(): void
    {
        $this->createItem('X');
        $this->createItem('Y');

        // 'Busca' is the UI default — must return all records without WHERE
        $request = Request::create('/', 'GET', ['search' => 'Busca']);
        $results = $this->repo->advancedSearch($request)->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function advancedSearch_termo_real_filtra_com_or_like_em_colunas(): void
    {
        $this->createItem('Alpha', 'active');
        $this->createItem('Beta', 'active');
        $this->createItem('Gamma', 'active');

        // Search for 'ph' — only 'Alpha' contains it
        $request = Request::create('/', 'GET', ['search' => 'ph']);
        $results = $this->repo->advancedSearch($request)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results->first()->name);
    }

    // ── Utilitários de escrita ────────────────────────────────────────────────

    #[Test]
    public function updateBatch_atualiza_multiplos_registros_em_lote(): void
    {
        $a = $this->createItem('A', 'active');
        $b = $this->createItem('B', 'active');
        $this->createItem('C', 'active');

        $affected = $this->repo->updateBatch([$a->id, $b->id], ['status' => 'archived']);

        $this->assertSame(2, $affected);
        $this->assertDatabaseHas('items', ['id' => $a->id, 'status' => 'archived']);
        $this->assertDatabaseHas('items', ['id' => $b->id, 'status' => 'archived']);
        $this->assertDatabaseHas('items', ['name' => 'C', 'status' => 'active']); // C untouched
    }

    #[Test]
    public function createQuietly_cria_registro_sem_disparar_evento_creating(): void
    {
        $eventFired = false;

        RepoStubModel::creating(static function () use (&$eventFired): void {
            $eventFired = true;
        });

        $item = $this->repo->createQuietly(['name' => 'Quiet', 'status' => 'active', 'amount' => 1]);

        $this->assertFalse($eventFired, 'createQuietly must not fire the creating event');
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Quiet']);
    }

    #[Test]
    public function updateQuietly_atualiza_registro_sem_disparar_evento_updating(): void
    {
        $item       = $this->createItem('Antes');
        $eventFired = false;

        RepoStubModel::updating(static function () use (&$eventFired): void {
            $eventFired = true;
        });

        $result = $this->repo->updateQuietly(['name' => 'Depois'], $item->id);

        $this->assertTrue($result);
        $this->assertFalse($eventFired, 'updateQuietly must not fire the updating event');
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Depois']);
    }

    #[Test]
    public function replicate_retorna_instancia_nao_persistida_do_ultimo_registro(): void
    {
        $original = $this->createItem('Original', 'active', 99);

        $copy = $this->repo->replicate();

        $this->assertNull($copy->id); // not persisted
        $this->assertSame('Original', $copy->name);
        $this->assertSame(99, $copy->amount);
    }
}

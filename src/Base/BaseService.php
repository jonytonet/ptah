<?php

declare(strict_types=1);

namespace Ptah\Base;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service base abstrato que delega operações ao repositório.
 *
 * As classes de serviço devem estender esta classe e injetar
 * o repositório correspondente via construtor.
 */
abstract class BaseService
{
    protected BaseRepositoryInterface $repository;

    /** Chave primária resolvida do model — usada como ordenação padrão. */
    protected string $keyName = 'id';

    public function __construct(BaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->keyName    = $this->repository->getKeyName();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD básico
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna todos os registros.
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Retorna registros paginados.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Busca um registro pelo ID.
     */
    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    /**
     * Alias de find() — padrão esperado nos controllers API.
     */
    public function show(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    /**
     * Busca um registro pelo ID ou lança exceção.
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Cria um novo registro.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    /**
     * Atualiza um registro existente.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): Model
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Remove um registro — verifica existência antes de deletar.
     */
    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Remove um registro — alias semântico para controllers API.
     */
    public function destroy(int|string $id): bool
    {
        $record = $this->repository->find($id);

        if (! $record) {
            return false;
        }

        return $this->repository->delete($id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Busca inteligente (getDados) — orquestra os métodos avançados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ponto de entrada único para listagem via API/BaseCrud.
     * Orquestra advancedSearch, searchLike e findAllFieldsAnd
     * de acordo com os parâmetros da Request.
     *
     * Parâmetros reconhecidos:
     *   search         — OR em todos os campos (comma-separated)
     *   searchLike     — busca incremental com operadores
     *   relations      — eager load (comma-separated)
     *   order          — coluna de ordenação
     *   direction      — ASC ou DESC
     *   limit          — itens por página (paginate)
     *
     * @return LengthAwarePaginator<Model>
     */
    public function getDados(Request $request): LengthAwarePaginator
    {
        $relations   = $this->resolveRelations($request);
        $orderColumn = $request->get('order', $this->keyName);
        $direction   = strtoupper($request->get('direction', 'DESC'));
        $limit       = (int) ($request->get('limit', 15) ?: 15);

        if ($request->get('search', 'Busca') !== 'Busca') {
            // Busca OR por search
            $query = $relations
                ? $this->repository->advancedSearch($request, $relations)
                : $this->repository->advancedSearch($request);
        } elseif ($request->get('searchLike', 'Incremental') !== 'Incremental') {
            // Busca incremental por searchLike
            $query = $relations
                ? $this->repository->searchLike($request, $relations)
                : $this->repository->searchLike($request);
        } else {
            // Filtragem AND pelos campos da request
            $query = $relations
                ? $this->repository->findAllFieldsAnd($request, $relations)
                : $this->repository->findAllFieldsAnd($request);
        }

        return $query
            ->orderByRaw("{$orderColumn} {$direction}")
            ->paginate($limit);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delegações dos métodos avançados do repositório
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Busca OR por termo em todos os campos.
     *
     * @param array<int, string> $relations
     */
    public function advancedSearch(Request $request, array $relations = []): Builder
    {
        return $this->repository->advancedSearch($request, $relations);
    }

    /**
     * Busca incremental (like) com suporte a operadores.
     *
     * @param array<int, string> $relations
     */
    public function searchLike(Request $request, array $relations = []): Builder
    {
        return $this->repository->searchLike($request, $relations);
    }

    /**
     * Busca AND em todos os campos da request.
     *
     * @param array<int, string> $relations
     */
    public function findAllFieldsAnd(Request $request, array $relations = []): Builder
    {
        return $this->repository->findAllFieldsAnd($request, $relations);
    }

    /**
     * Autocomplete: select + conditions + limit(10).
     *
     * @param array<int, string>        $select
     * @param array<int, array<string>> $conditions
     */
    public function autocompleteSearch(Request $request, array $select, array $conditions): Builder
    {
        return $this->repository->autocompleteSearch($request, $select, $conditions);
    }

    /**
     * Busca registros por coluna e valor — aceita multi-assinatura.
     */
    public function findBy(
        string|array|Closure|Builder $column,
        mixed $value = null,
        string $operator = '=',
        string $boolean = 'and'
    ): Builder {
        return $this->repository->findBy($column, $value, $operator, $boolean);
    }

    /**
     * Busca registros cujo $column está em $values.
     *
     * @param array<int, mixed>  $values
     * @param array<int, string> $with
     */
    public function findByIn(string $column, array $values, array $with = []): Collection
    {
        return $this->repository->findByIn($column, $values, $with);
    }

    /**
     * Atualiza múltiplos registros pelos IDs em lote.
     *
     * @param array<int, int|string> $ids
     * @param array<string, mixed>   $data
     */
    public function updateBatch(array $ids, array $data): int
    {
        return $this->repository->updateBatch($ids, $data);
    }

    /**
     * Atualiza sem disparar eventos/observers.
     *
     * @param array<string, mixed> $data
     */
    public function updateQuietly(array $data, int|string $id): bool
    {
        return $this->repository->updateQuietly($data, $id);
    }

    /**
     * Cria sem disparar eventos/observers.
     *
     * @param array<string, mixed> $data
     */
    public function createQuietly(array $data): Model
    {
        return $this->repository->createQuietly($data);
    }

    /**
     * Trunca a tabela.
     */
    public function truncate(): void
    {
        $this->repository->truncate();
    }

    /**
     * Retorna instância replicada (não persistida) do último registro.
     */
    public function replicate(): Model
    {
        return $this->repository->replicate();
    }

    /**
     * Força uso de índice MySQL específico no repositório.
     */
    public function useIndex(string $indexName): Builder
    {
        return $this->repository->useIndex($indexName);
    }

    /**
     * Retorna o nome da chave primária.
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve o array de relations a partir da request.
     * Retorna [] se não houver param ou se for o valor padrão 'Relacao'.
     *
     * @return array<int, string>
     */
    private function resolveRelations(Request $request): array
    {
        $raw = $request->get('relations', 'Relacao');

        if ($raw === 'Relacao' || empty($raw)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }
}

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

    /** Primary key resolved from the model — used as default sort column. */
    protected string $keyName = 'id';

    /**
     * Allowlist of relation names that may be eager-loaded via the `relations`
     * request param. When empty, ALL requested relations are allowed (default
     * behaviour for backward compatibility). Override in concrete services to
     * restrict which relationships a caller can trigger:
     *
     *   protected array $allowedRelations = ['category', 'tags'];
     *
     * @var array<int, string>
     */
    protected array $allowedRelations = [];

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
     * Removes a record — API-controller semantic alias for delete().
     *
     * Unlike delete(), this method returns false when the record is not found
     * instead of throwing ModelNotFoundException. Uses a single DB lookup to
     * avoid the TOCTOU race condition (find-then-delete) of the old approach.
     */
    public function destroy(int|string $id): bool
    {
        try {
            $record = $this->repository->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return false;
        }

        return (bool) $record->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getData — single entry point for paginated listing
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Single entry point for API/CRUD listing.
     * Routes to advancedSearch, searchLike or findAllFieldsAnd based on the
     * params present in the request.
     *
     * Recognised params:
     *   search     — OR match across all columns (comma-separated terms)
     *   searchLike — incremental match with operator support (}, {, >, <)
     *   relations  — eager-loaded relations (comma-separated, filtered by
     *                $allowedRelations when non-empty)
     *   order      — sort column (validated against real column list to
     *                prevent SQL injection; falls back to primary key)
     *   direction  — ASC or DESC (validated; falls back to DESC)
     *   limit      — items per page (default 15)
     *
     * @return LengthAwarePaginator<Model>
     */
    public function getData(Request $request): LengthAwarePaginator
    {
        $relations = $this->resolveRelations($request);
        $limit     = (int) ($request->get('limit', 15) ?: 15);

        // Validate order column against the table schema to prevent SQL injection.
        $rawOrder    = $request->get('order', $this->keyName);
        $rawDir      = strtoupper($request->get('direction', 'DESC'));
        $validCols   = $this->repository->getTableColumns();
        $orderColumn = in_array($rawOrder, $validCols, true) ? $rawOrder : $this->keyName;
        $direction   = in_array($rawDir, ['ASC', 'DESC'], true) ? $rawDir : 'DESC';

        if ($request->get('search', 'Busca') !== 'Busca') {
            $query = $this->repository->advancedSearch($request, $relations);
        } elseif ($request->get('searchLike', 'Incremental') !== 'Incremental') {
            $query = $this->repository->searchLike($request, $relations);
        } else {
            $query = $this->repository->findAllFieldsAnd($request, $relations);
        }

        return $query
            ->orderBy($orderColumn, $direction)
            ->paginate($limit);
    }

    /**
     * @deprecated Use getData() instead. Will be removed in 1.0.
     * @return LengthAwarePaginator<Model>
     */
    public function getDados(Request $request): LengthAwarePaginator
    {
        return $this->getData($request);
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
     * Searches records by column and value — three signatures:
     *   findBy(string $column, mixed $value, string $operator = '=')
     *   findBy(array  $wheres)
     *   findBy(Closure $callback)
     */
    public function findBy(
        string|array|Closure $column,
        mixed $value = null,
        string $operator = '='
    ): Builder {
        return $this->repository->findBy($column, $value, $operator);
    }

    /**
     * Applies a where clause to an existing Builder (e.g. from useIndex()).
     */
    public function findByBuilder(
        Builder $query,
        string $column,
        string $operator = '=',
        mixed $value = null
    ): Builder {
        return $this->repository->findByBuilder($query, $column, $operator, $value);
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
     * Returns the primary key name.
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * Returns the column listing for the model's table.
     */
    public function getTableColumns(): array
    {
        return $this->repository->getTableColumns();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolves the relations array from the request.
     * Returns [] when the param is absent or the UI sentinel 'Relacao'.
     * When $allowedRelations is non-empty, the list is filtered against it
     * so callers cannot trigger arbitrary relation eager-loads.
     *
     * @return array<int, string>
     */
    private function resolveRelations(Request $request): array
    {
        $raw = $request->get('relations', 'Relacao');

        if ($raw === 'Relacao' || empty($raw)) {
            return [];
        }

        $requested = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if (! empty($this->allowedRelations)) {
            $requested = array_values(array_intersect($requested, $this->allowedRelations));
        }

        return $requested;
    }
}

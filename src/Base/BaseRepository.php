<?php

declare(strict_types=1);

namespace Ptah\Base;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repositório base abstrato com implementação padrão do CRUD.
 *
 * Todas as classes de repositório devem estender esta classe
 * e injetar o Model correspondente via construtor.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @param Model $model Instância do model Eloquent associado ao repositório.
     */
    public function __construct(protected Model $model) {}

    /**
     * Retorna todos os registros.
     */
    public function all(): Collection
    {
        return $this->model->newQuery()->get();
    }

    /**
     * Retorna registros paginados.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }

    /**
     * Busca um registro pelo ID.
     */
    public function find(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    /**
     * Busca um registro pelo ID ou lança ModelNotFoundException.
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /**
     * Cria um novo registro.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Atualiza um registro existente pelo ID.
     *
     * @param array<string, mixed> $data
     * @throws ModelNotFoundException
     */
    public function update(int|string $id, array $data): Model
    {
        $record = $this->findOrFail($id);
        $record->update($data);

        return $record->fresh();
    }

    /**
     * Remove um registro pelo ID.
     *
     * @throws ModelNotFoundException
     */
    public function delete(int|string $id): bool
    {
        $record = $this->findOrFail($id);

        return (bool) $record->delete();
    }

    /**
     * Busca registros por uma coluna e valor específicos.
     */
    public function findBy(string $column, mixed $value): Collection
    {
        return $this->model->newQuery()->where($column, $value)->get();
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Base;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service base abstrato que delega operações ao repositório.
 *
 * As classes de serviço devem estender esta classe e injetar
 * o repositório correspondente via construtor.
 */
abstract class BaseService
{
    /**
     * @param BaseRepositoryInterface $repository Repositório associado ao serviço.
     */
    public function __construct(protected BaseRepositoryInterface $repository) {}

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
     * Remove um registro pelo ID.
     */
    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Busca registros por coluna e valor.
     */
    public function findBy(string $column, mixed $value): Collection
    {
        return $this->repository->findBy($column, $value);
    }
}

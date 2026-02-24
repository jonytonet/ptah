<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Ptah\Base\BaseRepositoryInterface;

/**
 * Trait que adiciona operações CRUD a um controller ou service.
 *
 * Requer que a classe possua uma propriedade $repository do tipo BaseRepositoryInterface.
 */
trait HasCrud
{
    /**
     * Retorna todos os registros.
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Retorna registros paginados.
     *
     * @param int $perPage Número de registros por página (padrão: 15)
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
}

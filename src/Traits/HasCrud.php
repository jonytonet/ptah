<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Ptah\Base\BaseRepositoryInterface;

/**
 * Trait that adds CRUD operations to a controller or service.
 *
 * Requires the class to have a $repository property of type BaseRepositoryInterface.
 */
trait HasCrud
{
    /**
     * Returns all records.
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Returns paginated records.
     *
     * @param int $perPage Number of records per page (default: 15)
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Finds a record by ID.
     */
    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    /**
     * Finds a record by ID or throws an exception.
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Creates a new record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    /**
     * Updates an existing record.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): Model
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Removes a record by ID.
     */
    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }
}

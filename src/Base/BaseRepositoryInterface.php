<?php

declare(strict_types=1);

namespace Ptah\Base;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface base para todos os repositórios da aplicação.
 *
 * Define o contrato CRUD padrão seguindo os princípios SOLID.
 */
interface BaseRepositoryInterface
{
    /**
     * Retorna todos os registros.
     */
    public function all(): Collection;

    /**
     * Retorna registros paginados.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Busca um registro pelo ID.
     */
    public function find(int|string $id): ?Model;

    /**
     * Busca um registro pelo ID ou lança exceção.
     */
    public function findOrFail(int|string $id): Model;

    /**
     * Cria um novo registro.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model;

    /**
     * Atualiza um registro existente.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): Model;

    /**
     * Remove um registro pelo ID.
     */
    public function delete(int|string $id): bool;

    /**
     * Busca registros por coluna e valor.
     * Aceita multi-assinatura: string, array, Closure ou Builder.
     */
    public function findBy(string|array|Closure|Builder $reference, mixed $value = null, string $operator = '=', string $boolean = 'and'): Builder;

    /**
     * Retorna o nome da chave primária do model.
     */
    public function getKeyName(): string;
}

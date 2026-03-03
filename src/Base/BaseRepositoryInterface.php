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
 * Base interface for all application repositories.
 *
 * Defines the full CRUD contract plus the advanced-search and utility
 * methods provided by BaseRepository. Concrete repositories must
 * implement all methods either directly or via BaseRepository.
 */
interface BaseRepositoryInterface
{
    // ──────────────────────────────────────── CRUD ──────────────────────────────────

    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int|string $id): ?Model;

    /** @throws \Illuminate\Database\Eloquent\ModelNotFoundException */
    public function findOrFail(int|string $id): Model;

    /** @param array<string, mixed> $data */
    public function create(array $data): Model;

    /**
     * @param array<string, mixed> $data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(int|string $id, array $data): Model;

    /** @throws \Illuminate\Database\Eloquent\ModelNotFoundException */
    public function delete(int|string $id): bool;

    // ───────────────────────────────── Advanced search ────────────────────────

    /** @param array<int, string> $relations */
    public function advancedSearch(Request $request, array $relations = []): Builder;

    /** @param array<int, string> $relations */
    public function searchLike(Request $request, array $relations = []): Builder;

    /** @param array<int, string> $relations */
    public function findAllFieldsAnd(Request $request, array $relations = []): Builder;

    /**
     * @param array<int, string>        $select
     * @param array<int, array<string>> $conditions
     */
    public function autocompleteSearch(Request $request, array $select, array $conditions): Builder;

    public function allQuery(array $search = [], ?int $skip = null, ?int $limit = null): Builder;

    // ───────────────────────────────── findBy variants ────────────────────────

    /**
     * Accepts three signatures:
     *   findBy(string $column, mixed $value, string $operator = '=')
     *   findBy(array  $wheres)
     *   findBy(Closure $callback)
     */
    public function findBy(string|array|Closure $reference, mixed $value = null, string $operator = '='): Builder;

    /** Applies a where clause to an existing Builder (e.g. from useIndex()). */
    public function findByBuilder(Builder $query, string $column, string $operator = '=', mixed $value = null): Builder;

    /**
     * @param array<int, mixed>  $values
     * @param array<int, string> $with
     */
    public function findByIn(string $column, array $values, array $with = []): Collection;

    // ───────────────────────────────── Write utilities ────────────────────────

    /**
     * @param array<int, int|string> $ids
     * @param array<string, mixed>   $data
     */
    public function updateBatch(array $ids, array $data): int;

    /** @param array<string, mixed> $data */
    public function updateQuietly(array $data, int|string $id): bool;

    /** @param array<string, mixed> $data */
    public function createQuietly(array $data): Model;

    /** Driver-aware table truncation (MySQL, PostgreSQL, SQLite). */
    public function truncate(): void;

    /** Returns an unsaved replica of the last record. */
    public function replicate(): Model;

    // ───────────────────────────────── Utilities ──────────────────────────────

    /**
     * MySQL/MariaDB USE INDEX hint; degrades gracefully on other drivers.
     */
    public function useIndex(string $indexName): Builder;

    /** @return array<int, string> */
    public function buildSelectFields(Request $request): array;

    /** @return array<int, string> */
    public function getTableColumns(): array;

    public function getKeyName(): string;
}


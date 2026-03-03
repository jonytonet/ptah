<?php

declare(strict_types=1);

namespace Ptah\Base;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repositório base abstrato com implementação padrão do CRUD e métodos
 * avançados de busca compatíveis com o padrão de API do projeto.
 *
 * Todas as classes de repositório devem estender esta classe
 * e injetar o Model correspondente via construtor.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /** Campos reservados do Request que não devem ser usados como filtro de coluna. */
    protected array $reservedParams = [
        'limit', 'page', 'order', 'direction', 'fields',
        'relations', 'search', 'searchLike', 'searchLikeType',
        'whereIn', 'additionalQueries',
    ];

    /**
     * SQL operators accepted in `additionalQueries` request param.
     * Anything outside this list is silently discarded to prevent injection.
     *
     * @var array<int, string>
     */
    protected const ALLOWED_OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    /**
     * @param Model $model Eloquent model instance bound to this repository.
     */
    public function __construct(protected Model $model) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the column listing for the model's table.
     * Result is memoised per table name to avoid repeated schema queries.
     * Exposed publicly so services/controllers can validate user input.
     *
     * @return array<int, string>
     */
    public function getTableColumns(): array
    {
        static $cache = [];

        $table = $this->model->getTable();

        if (! isset($cache[$table])) {
            $cache[$table] = Schema::getColumnListing($table);
        }

        return $cache[$table];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD básico
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // Busca avançada (retornam Builder encadeável)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Constrói query base com suporte a skip/limit — ponto de entrada encadeável.
     */
    public function allQuery(array $search = [], ?int $skip = null, ?int $limit = null): Builder
    {
        $query = $this->model->newQuery();

        if (! empty($search)) {
            foreach ($search as $column => $value) {
                $query->where($column, $value);
            }
        }

        if ($skip !== null) {
            $query->skip($skip);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * Filtra com AND em todos os campos do Request que não sejam reservados.
     *
     * @param array<int, string> $relations
     */
    public function findAllFieldsAnd(Request $request, array $relations = []): Builder
    {
        $inputs = $request->except($this->reservedParams);

        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if ($request->has('fields')) {
            $query->select($this->buildSelectFields($request));
        }

        $validColumns = $this->getTableColumns();

        foreach ($inputs as $key => $value) {
            // Only allow columns that actually exist — prevents arbitrary column injection.
            if ($value !== null && $value !== '' && in_array($key, $validColumns, true)) {
                $query->where($key, $value);
            }
        }

        return $this->applyWhereHas($query, $request, $relations, 'AND');
    }

    /**
     * Busca OR pelo param `search` (lista separada por vírgula).
     * Ignora se `search == 'Busca'` (valor padrão da UI).
     *
     * @param array<int, string> $relations
     */
    public function advancedSearch(Request $request, array $relations = []): Builder
    {
        $input = $request->get('search', '');

        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if ($request->has('fields')) {
            $query->select($this->buildSelectFields($request));
        }

        if (! empty($input) && trim($input) !== 'Busca') {
            $terms   = explode(',', $input);
            $columns = Schema::getColumnListing($this->model->getTable());

            $query->where(function (Builder $q) use ($terms, $columns): void {
                foreach ($terms as $term) {
                    $term = trim($term);
                    if ($term === '') {
                        continue;
                    }
                    $q->orWhere(function (Builder $inner) use ($term, $columns): void {
                        foreach ($columns as $col) {
                            $inner->orWhere($col, 'LIKE', "%{$term}%");
                        }
                    });
                }
            });
        }

        return $this->applyWhereHas($query, $request, $relations, 'OR');
    }

    /**
     * Incremental search via the `searchLike` request param.
     * Supports comparison operators via custom tokens: `}` = >=, `{` = <=, `>`, `<`.
     * Also handles `whereIn` and `additionalQueries` params.
     * Column names and operators are validated against a whitelist.
     * Suporta operadores >, >=, <=, < (usando } e { como tokens),
     * whereIn e additionalQueries.
     *
     * @param array<int, string> $relations
     */
    public function searchLike(Request $request, array $relations = []): Builder
    {
        $input = $request->get('searchLike', '');

        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if ($request->has('fields')) {
            $query->select($this->buildSelectFields($request));
        }

        $type    = strtoupper($request->get('searchLikeType', 'AND')) === 'OR' ? 'orWhere' : 'where';
        $filters = [];

        if (! empty($input) && trim($input) !== 'Incremental') {
            $filters = explode(',', $input);
        }

        if (! empty($filters)) {
            $columns = Schema::getColumnListing($this->model->getTable());

            $query->where(function (Builder $q) use ($filters, $columns, $type): void {
                foreach ($filters as $term) {
                    $term = trim($term);
                    if ($term === '') {
                        continue;
                    }

                    // Operadores customizados: } = >= e { = <=  e > e <
                    if (preg_match('/^([^}>{<]+)([}>{<]{1,2})(.+)$/', $term, $m)) {
                        $col = trim($m[1]);
                        $raw = trim($m[2]);
                        $val = trim($m[3]);
                        $op  = match ($raw) {
                            '}'  => '>=',
                            '{'  => '<=',
                            '>'  => '>',
                            '<'  => '<',
                            '>>' => '>',
                            '<<' => '<',
                            default => '=',
                        };
                        $q->{$type}($col, $op, $val);
                    } else {
                        // LIKE em todas as colunas
                        $q->where(function (Builder $inner) use ($term, $columns): void {
                            foreach ($columns as $col) {
                                $inner->orWhere($col, 'LIKE', "%{$term}%");
                            }
                        });
                    }
                }
            });
        }

        // whereIn — column name is validated against the table schema to prevent injection.
        $whereInRaw   = $request->get('whereIn', '');
        $validColumns = $this->getTableColumns();

        if (! empty($whereInRaw) && $whereInRaw !== 'whereIn') {
            foreach (explode(';', $whereInRaw) as $segment) {
                $parts = explode(':', $segment, 2);
                if (count($parts) === 2) {
                    $col = trim($parts[0]);
                    if (in_array($col, $validColumns, true)) {
                        $query->whereIn($col, explode(',', $parts[1]));
                    }
                }
            }
        }

        // additionalQueries  ex: "col1:=:val;col2:>=:val2"
        // Column is validated against the schema; operator against ALLOWED_OPERATORS.
        $additionalRaw = $request->get('additionalQueries', '');
        if (! empty($additionalRaw)) {
            foreach (explode(';', $additionalRaw) as $segment) {
                $parts = explode(':', $segment, 3);
                if (count($parts) === 3) {
                    [$col, $op, $val] = $parts;
                    $col = trim($col);
                    $op  = strtoupper(trim($op));

                    if (
                        in_array($col, $validColumns, true)
                        && in_array($op, self::ALLOWED_OPERATORS, true)
                    ) {
                        $query->where($col, $op, trim($val));
                    }
                }
            }
        }

        return $this->applyWhereHas($query, $request, $relations, 'OR');
    }

    /**
     * Busca para autocomplete: select específico + conditions + limit(10).
     *
     * @param array<int, string>        $select
     * @param array<int, array<string>> $conditions  ex: [['col', 'LIKE', '%foo%']]
     */
    public function autocompleteSearch(Request $request, array $select, array $conditions): Builder
    {
        /** @var Builder $query */
        $query = $this->model->newQuery()->select($select);

        foreach ($conditions as $condition) {
            $query->where(...$condition);
        }

        $query->limit(10);

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilitários de busca
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Searches records by column and value.
     * Accepts three signatures:
     *
     *   findBy(string $column, mixed $value, string $operator = '=')
     *   findBy(array  $wheres)   — multiple AND conditions
     *   findBy(Closure $callback) — full query closure
     *
     * For index-hint enchaing use findByBuilder() instead.
     *
     * @example $this->findBy('status', 'active')->get()
     * @example $this->findBy(['status' => 'active', 'amount' => 10])->get()
     * @example $this->findBy(fn ($q) => $q->where('amount', '>', 5))->get()
     */
    public function findBy(
        string|array|Closure $reference,
        mixed $value = null,
        string $operator = '='
    ): Builder {
        $query = $this->model->newQuery();

        if ($reference instanceof Closure) {
            return $query->where($reference);
        }

        if (is_array($reference)) {
            return $query->where($reference);
        }

        return $query->where($reference, $operator, $value);
    }

    /**
     * Applies a simple where clause to an existing Builder (e.g. from useIndex()).
     * This is the clean replacement for the old Builder-branch of findBy().
     *
     * @example $this->findByBuilder($this->useIndex('idx_name'), 'name', '=', 'Jony')->get()
     */
    public function findByBuilder(
        Builder $query,
        string $column,
        string $operator = '=',
        mixed $value = null
    ): Builder {
        return $query->where($column, $operator, $value);
    }

    /**
     * Busca registros cujo $column está em $values.
     *
     * @param array<int, mixed>  $values
     * @param array<int, string> $with
     */
    public function findByIn(string $column, array $values, array $with = []): Collection
    {
        $query = $this->model->newQuery()->whereIn($column, $values);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Returns the SELECT column list from the `fields` query param.
     *
     * Only columns that actually exist in the table are accepted.
     * Unknown identifiers are silently dropped; when nothing valid remains,
     * ['*'] is returned to avoid an empty SELECT.
     *
     * @return array<int, string>
     */
    public function buildSelectFields(Request $request): array
    {
        $fields = $request->get('fields', '');

        if (empty($fields)) {
            return ['*'];
        }

        $requested = array_map('trim', explode(',', $fields));
        $valid     = array_values(
            array_intersect($requested, $this->getTableColumns())
        );

        return $valid ?: ['*'];
    }

    /**
     * @deprecated Use buildSelectFields() instead.
     * @return array<int, string>
     */
    public function mountFieldsToSelect(Request $request): array
    {
        return $this->buildSelectFields($request);
    }

    /**
     * Applies whereHas clauses for relations passed via Request (?relations=foo,bar).
     * Internal — used by the advanced-search methods.
     */
    protected function applyWhereHas(
        Builder $baseModel,
        Request $request,
        array $relations,
        string $type
    ): Builder {
        if (empty($relations)) {
            return $baseModel;
        }

        $search     = $request->get('search', '');
        $searchLike = $request->get('searchLike', '');
        $term       = $searchLike !== 'Incremental' && ! empty($searchLike)
            ? $searchLike
            : ($search !== 'Busca' && ! empty($search) ? $search : null);

        if ($term === null) {
            return $baseModel;
        }

        $method = $type === 'OR' ? 'orWhereHas' : 'whereHas';

        foreach ($relations as $relation) {
            $baseModel->{$method}($relation, function (Builder $q) use ($term): void {
                // Use the related model's column listing — stored per-table for efficiency.
                $columns = Schema::getColumnListing($q->getModel()->getTable());
                $q->where(function (Builder $inner) use ($term, $columns): void {
                    foreach ($columns as $col) {
                        $inner->orWhere($col, 'LIKE', "%{$term}%");
                    }
                });
            });
        }

        return $baseModel;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilitários de escrita
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Atualiza múltiplos registros pelos IDs em lote.
     *
     * @param array<int, int|string> $ids
     * @param array<string, mixed>   $data
     */
    public function updateBatch(array $ids, array $data): int
    {
        return $this->model->newQuery()->whereIn($this->model->getKeyName(), $ids)->update($data);
    }

    /**
     * Atualiza sem disparar eventos/observers.
     *
     * @param array<string, mixed> $data
     */
    public function updateQuietly(array $data, int|string $id): bool
    {
        return (bool) $this->model->newQuery()->whereKey($id)->update($data);
    }

    /**
     * Cria um registro sem disparar eventos/observers.
     *
     * @param array<string, mixed> $data
     */
    public function createQuietly(array $data): Model
    {
        $instance = $this->model->newInstance($data);
        $instance->saveQuietly();

        return $instance;
    }

    /**
     * Truncates the table, temporarily disabling FK checks where supported.
     *
     * Driver-aware:
     *  - MySQL/MariaDB : SET FOREIGN_KEY_CHECKS=0 ... =1
     *  - PostgreSQL    : TRUNCATE ... RESTART IDENTITY CASCADE
     *  - SQLite/others : plain truncate (no FK-check syntax needed)
     */
    public function truncate(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            $this->model->newQuery()->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'pgsql') {
            $table = $this->model->getTable();
            DB::statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
        } else {
            // SQLite and others: plain truncate (no FK-check syntax required)
            $this->model->newQuery()->truncate();
        }
    }

    /**
     * Retorna uma instância replicada (não persistida) do último registro encontrado.
     * Útil para duplicação de registros.
     */
    public function replicate(): Model
    {
        /** @var Model $record */
        $record = $this->model->newQuery()->firstOrFail();

        return $record->replicate();
    }

    /**
     * Hints MySQL/MariaDB to use a specific index (USE INDEX).
     * On other drivers (PostgreSQL, SQLite, etc.) the hint is silently omitted
     * and a plain Builder is returned so the query still executes correctly.
     *
     * @example $this->useIndex('idx_status')->where('status', 'active')->get()
     * @example $this->findByBuilder($this->useIndex('idx_name'), 'name', '=', 'Jony')->get()
     */
    public function useIndex(string $indexName): Builder
    {
        $query  = $this->model->newQuery();
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $table = $this->model->getTable();
            $query->getQuery()->fromRaw("`{$table}` USE INDEX (`{$indexName}`)");
        }

        return $query;
    }

    /**
     * Retorna o nome da chave primária do model.
     */
    public function getKeyName(): string
    {
        return $this->model->getKeyName();
    }
}

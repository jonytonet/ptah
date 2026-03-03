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
     * @param Model $model Instância do model Eloquent associado ao repositório.
     */
    public function __construct(protected Model $model) {}

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
            $query->select($this->mountFieldsToSelect($request));
        }

        foreach ($inputs as $key => $value) {
            if ($value !== null && $value !== '') {
                $query->where($key, $value);
            }
        }

        return $this->getWherehas($query, $request, $relations, 'AND');
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
            $query->select($this->mountFieldsToSelect($request));
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

        return $this->getWherehas($query, $request, $relations, 'OR');
    }

    /**
     * Busca incremental pelo param `searchLike`.
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
            $query->select($this->mountFieldsToSelect($request));
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

        // whereIn
        $whereInRaw = $request->get('whereIn', '');
        if (! empty($whereInRaw) && $whereInRaw !== 'whereIn') {
            foreach (explode(';', $whereInRaw) as $segment) {
                $parts = explode(':', $segment, 2);
                if (count($parts) === 2) {
                    $query->whereIn(trim($parts[0]), explode(',', $parts[1]));
                }
            }
        }

        // additionalQueries  ex: "col1:op:val;col2:=:val2"
        $additionalRaw = $request->get('additionalQueries', '');
        if (! empty($additionalRaw)) {
            foreach (explode(';', $additionalRaw) as $segment) {
                $parts = explode(':', $segment, 3);
                if (count($parts) === 3) {
                    [$col, $op, $val] = $parts;
                    $query->where(trim($col), trim($op), trim($val));
                }
            }
        }

        return $this->getWherehas($query, $request, $relations, 'OR');
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
     * Busca registros por uma coluna e valor específicos.
     * Aceita multi-assinatura: string simples, array de wheres,
     * Closure de query ou Builder pré-criado (para encadeamento com useIndex).
     *
     * Quando $reference é Builder (retorno de useIndex()), os próximos parâmetros
     * mapeiam para: $value=coluna, $operator=operador, $boolean=valor.
     * Exemplo correto com Builder:  findBy($this->useIndex('idx_name'), 'name', '=', 'Jony')
     *
     * @example $this->findBy('status', 'active')->get()
     * @example $this->findBy(['status' => 'active', 'is_active' => true])->get()
     * @example $this->findBy($this->useIndex('idx_status'), 'status', '=', 'active')->get()
     */
    public function findBy(
        string|array|Closure|Builder $reference,
        mixed $value = null,
        string $operator = '=',
        string $boolean = 'and'
    ): Builder {
        if ($reference instanceof Builder) {
            // Encadeamento com useIndex() — usa o Builder recebido como base
            return $reference->where($value, $operator, $boolean);
        }

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
     * Retorna array de colunas a partir do param `fields` da request.
     *
     * @return array<int, string>
     */
    public function mountFieldsToSelect(Request $request): array
    {
        $fields = $request->get('fields', '');

        if (empty($fields)) {
            return ['*'];
        }

        return array_map('trim', explode(',', $fields));
    }

    /**
     * Aplica whereHas para relacionamentos quando passados via Request (?relations=foo,bar).
     * Interno — usado pelos métodos de busca avançada.
     */
    protected function getWherehas(
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
     * Trunca a tabela desabilitando verificações de FK temporariamente.
     */
    public function truncate(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->model->newQuery()->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
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
     * Adiciona USE INDEX ao Builder para forçar uso de índice MySQL específico.
     * Pode ser encadeado com findBy() ou where().
     *
     * @example $this->useIndex('idx_status')->where('status', 'active')->get()
     * @example $this->findBy($this->useIndex('idx_name'), 'name', 'Jony')->get()
     */
    public function useIndex(string $indexName): Builder
    {
        $table = $this->model->getTable();

        $query = $this->model->newQuery();
        $query->getQuery()->fromRaw("`{$table}` USE INDEX (`{$indexName}`)");

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

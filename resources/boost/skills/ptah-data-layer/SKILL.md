---
name: ptah-data-layer
description: Use Ptah's BaseRepository / BaseService / BaseDTO base classes correctly — how to extend and wire a repository+service via contracts, the full method reference, and the getData(Request) listing contract (search / searchLike / AND filtering, operators, sentinels, order/direction validation, allowedRelations). Use whenever writing or reviewing a Service, Repository, DTO, or any data access in a Ptah project, or deciding where a query/business rule belongs.
---

# Ptah — Data layer (BaseRepository / BaseService / BaseDTO)

The companion **`ptah-development`** skill owns the layering philosophy (SOLID,
where logic belongs). This skill is the **practical API + wiring reference** for
the base classes in `Ptah\Base`.

## Golden rule

```
Livewire / Controller → Service (via Contract) → Repository → Model
```

- **All DB access lives in the Repository** (extends `Ptah\Base\BaseRepository`).
- **All business rules live in the Service** (extends `Ptah\Base\BaseService`).
- Livewire/Controllers **never** query Eloquent directly and **never** contain
  business rules — they call the Service through its Contract.
- Data crossing layers travels as a **DTO** (extends `Ptah\Base\BaseDTO`).

## Wiring a repository + service

```php
// 1. Repository — inject the concrete Model into the base constructor.
namespace App\Repositories;

use App\Models\Product;
use Ptah\Base\BaseRepository;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    // add only entity-specific queries here; generic CRUD is inherited.
    public function existsBySku(string $sku): bool
    {
        return $this->findBy('sku', $sku) !== null;
    }
}

// 2. Service — inject the repository CONTRACT (never the concrete class).
namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryContract;
use App\Exceptions\DuplicateSkuException;
use Ptah\Base\BaseService;

class ProductService extends BaseService
{
    public function __construct(private ProductRepositoryContract $products)
    {
        parent::__construct($products); // BaseService keeps it as $this->repository
    }

    public function create(array $data): \Illuminate\Database\Eloquent\Model
    {
        if ($this->products->existsBySku($data['sku'])) {
            throw new DuplicateSkuException("SKU {$data['sku']} já cadastrado.");
        }

        return parent::create($data);
    }
}

// 3. Bind the contract → implementation in a ServiceProvider:
$this->app->bind(ProductRepositoryContract::class, ProductRepository::class);
```

`ptah:forge` generates this repository/service/contract wiring for you — this
reference is for extending or reviewing it.

## BaseRepository — method reference

| Method | Purpose |
|---|---|
| `all(): Collection` | All records |
| `paginate(int $perPage = 15)` | Paginated |
| `find($id): ?Model` / `findOrFail($id): Model` | By primary key |
| `create(array $data): Model` | Insert (fires events) |
| `update($id, array $data): Model` | Update by id |
| `delete($id): bool` | Delete by id |
| `findBy($column, $value, ...)` | First match by column |
| `findByBuilder(Closure $cb)` | Custom builder, first/get |
| `findByIn(string $column, array $values, array $with = [])` | `whereIn` + eager load |
| `updateBatch(array $ids, array $data): int` | Mass update by ids |
| `updateQuietly(array $data, $id)` / `createQuietly(array $data)` | No model events (⚠️ skips HasAuditFields) |
| `replicate(): Model` | Duplicate a record |
| `truncate(): void` | ⚠️ empties the table — destructive, never as part of a normal flow |
| `useIndex(string $indexName): Builder` | Force an index (query-plan control) |
| `autocompleteSearch(Request, array $select, array $conditions): Builder` | Typeahead |
| `getTableColumns(): array` / `getKeyName(): string` | Schema helpers |
| `allQuery`, `findAllFieldsAnd`, `advancedSearch`, `searchLike` | Request-driven queries (see below) |

## BaseService — method reference

Mirrors the repository (`all`, `paginate`, `find`, `create`, `update`, `delete`,
`findBy…`, `updateBatch`, `useIndex`, …) plus:

- `show($id): ?Model` — alias of `find`.
- `destroy($id): bool` — `findOrFail` + `delete`; returns `false` if not found (no exception).
- `getData(Request): LengthAwarePaginator` — **the listing entry point** (below).

Override the base methods in the concrete service to add business rules (validation,
domain exceptions, events), then call `parent::…()`.

## getData(Request) — the listing contract

`getData()` is the single entry point for API/CRUD listings. It picks the query
strategy from the request params:

| Param present | Strategy | Behaviour |
|---|---|---|
| `search` (≠ sentinel `Search`) | `advancedSearch` | **OR** `LIKE %term%` across all columns; comma = multiple terms |
| `searchLike` (≠ sentinel `Incremental`) | `searchLike` | Incremental filter with operators (below) |
| neither | `findAllFieldsAnd` | **AND** exact match on every non-reserved param that is a real column |

Always applied on top:
- `order` — sort column, **validated against the real schema** (unknown → primary key). Anti-SQLi.
- `direction` — `ASC`/`DESC`, validated (else `DESC`).
- `limit` — page size (default 15). `page` — page number.
- `fields` — restrict the `SELECT` (comma-separated).
- `relations` — eager-load (comma-separated), **filtered by `$allowedRelations`** when set.

**searchLike operators** (prefix tokens in each `field:value`): `}` = `>=`, `{` = `<=`, `>`, `<`; also supports `whereIn` and `additionalQueries` params. `searchLikeType=OR` switches AND→OR.

**Reserved params** (never treated as column filters): `limit, page, order, direction, fields, relations, search, searchLike, searchLikeType, whereIn, additionalQueries`.

**Sentinels** (UI defaults that mean "no filter"): `search=Search`, `searchLike=Incremental`, `relations=Relation`.

```php
// Controller/Livewire stays this thin:
public function index(Request $request): JsonResponse
{
    return BaseResponse::paginated($this->service->getData($request));
}
```

## Security knobs (do not skip)

- **`$allowedRelations`** on the service — allowlist of eager-loadable relations.
  Empty = allow all (backward-compat). **Set it** on any service exposed to
  external input, so a caller can't `relations=` an arbitrary relation:
  ```php
  protected array $allowedRelations = ['category', 'tags'];
  ```
- Column names in `getData`/`findAllFieldsAnd`/`order` are validated against the
  real table schema — never bypass this by concatenating request input into raw SQL.
- `updateQuietly` / `createQuietly` skip Eloquent events → they **skip
  HasAuditFields** (`created_by`/`updated_by`). Use only when audit stamping is
  intentionally not wanted.

## DTOs (BaseDTO)

Concrete DTOs extend `Ptah\Base\BaseDTO` and implement `fromArray()`,
`fromRequest()`, `toArray()`. Use `readonly` properties; pass DTOs between
layers instead of loose arrays for typed, immutable data.

## Anti-patterns (reject / fix on sight)

```php
// ❌ query in Livewire/Controller
Product::where('sku', $sku)->exists();
// ✅ Service → Repository
$this->products->existsBySku($sku);

// ❌ business rule in the repository (belongs in the service)
// ❌ injecting the concrete Repository instead of its Contract
// ❌ ->whereIn($ids)->delete() on a HasAuditFields model (skips deleted_by)
//    use ->each(fn($r) => $r->delete())
```

For N+1, eager-load, chunking, caching and job rules, see **`ptah-development`**.

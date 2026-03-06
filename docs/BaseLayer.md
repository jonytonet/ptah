# Base Layer — Complete Documentation

**Namespace:** `Ptah\Base`  
**Files:** `BaseDTO`, `BaseRepository`, `BaseRepositoryInterface`, `BaseService`  
**CLI Generation:** `php artisan ptah:forge {Entity} --api`

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [BaseDTO](#basedto)
3. [BaseRepository](#baserepository)
   - [Basic CRUD](#basic-crud)
   - [Advanced Search](#advanced-search)
   - [Read Utilities](#read-utilities)
   - [Write Utilities](#write-utilities)
4. [BaseRepositoryInterface](#baserepositoryinterface)
5. [BaseService](#baseservice)
   - [CRUD via Service](#crud-via-service)
   - [getData — listing entry point](#getdata--listing-entry-point)
   - [Repository Delegations](#repository-delegations)
6. [API Layer](#api-layer)
   - [BaseApiController](#baseapicontroller)
   - [Generated Controller (ptah:forge --api)](#generated-controller-ptahforge---api)
   - [API Query Parameters](#api-query-parameters)
7. [Full Flow — end-to-end example](#full-flow--end-to-end-example)
8. [Security](#security)

---

## Architecture Overview

```
Request HTTP
    │
    ▼
ApiController          (gerado por ptah:forge --api)
    │ injeta
    ▼
Service                (estende BaseService)
    │ injeta via IoC
    ▼
Repository             (estende BaseRepository, implementa Interface)
    │ acessa
    ▼
Eloquent Model         (App\Models\*)
```

Each layer has a single responsibility:

| Layer | Responsibility |
|---|---|
| **Controller** | Receive HTTP, return JSON — no business logic |
| **Service** | Orchestrate business rules, validate, transform data |
| **Repository** | The only layer that queries the database |
| **DTO** | Transport structured data between layers |

---

## BaseDTO

`Ptah\Base\BaseDTO`

Abstract base class for all project DTOs. Defines the minimum contract for creating, converting and transporting data.

### Abstract Methods

| Method | Return | Description |
|---|---|---|
| `fromArray(array $data)` | `static` | Creates a DTO instance from an associative array |
| `fromRequest(Request $request)` | `static` | Creates a DTO instance from a Laravel `Request` |
| `toArray()` | `array<string, mixed>` | Converts the DTO to an associative array |

### Implementation Example

```php
namespace App\DTOs;

use Illuminate\Http\Request;
use Ptah\Base\BaseDTO;

class ProductDTO extends BaseDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly float   $price,
        public readonly bool    $active = true,
        public readonly ?int    $categoryId = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            name:       $data['name'],
            price:      (float) $data['price'],
            active:     (bool) ($data['active'] ?? true),
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
        );
    }

    public static function fromRequest(Request $request): static
    {
        return static::fromArray($request->validated());
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'price'       => $this->price,
            'active'      => $this->active,
            'category_id' => $this->categoryId,
        ];
    }
}
```

### Usage

```php
// In the Service — create from the validated request
$dto = ProductDTO::fromRequest($request);
$this->repository->create($dto->toArray());

// Create from array (e.g. seeds, tests)
$dto = ProductDTO::fromArray(['name' => 'Premium Feed', 'price' => 49.90]);
```

---

## BaseRepository

`Ptah\Base\BaseRepository`

Abstract repository implementation. All database queries live here.
Extends `BaseRepositoryInterface` and receives the Model via constructor.

```php
namespace App\Repositories;

use App\Models\Product;
use Ptah\Base\BaseRepository;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }
}
```

---

### Basic CRUD

#### `all(): Collection`
Returns all records in the table.

```php
$products = $this->productRepository->all();
```

---

#### `paginate(int $perPage = 15): LengthAwarePaginator`
Returns paginated records.

```php
$products = $this->productRepository->paginate(25);
```

---

#### `find(int|string $id): ?Model`
Finds a record by ID. Returns `null` if not found.

```php
$product = $this->productRepository->find(42);

if ($product === null) {
    // not found
}
```

---

#### `findOrFail(int|string $id): Model`
Finds by ID. Throws `ModelNotFoundException` if not found.

```php
$product = $this->productRepository->findOrFail(42);
// Throws ModelNotFoundException if id 42 does not exist
```

---

#### `create(array $data): Model`
Creates a new record and returns the persisted model.

```php
$product = $this->productRepository->create([
    'name'   => 'Premium Feed',
    'price'  => 49.90,
    'active' => true,
]);
// $product->id is filled
```

---

#### `update(int|string $id, array $data): Model`
Updates a record and returns the model with `fresh()` (reloaded from the database).  
Throws `ModelNotFoundException` if the ID does not exist.

```php
$product = $this->productRepository->update(42, ['price' => 59.90]);
// $product reflects the current state from the database
```

---

#### `delete(int|string $id): bool`
Removes a record. Respects `SoftDelete` if the model uses the trait.  
Throws `ModelNotFoundException` if the ID does not exist.

```php
$deleted = $this->productRepository->delete(42); // true
```

---

### Advanced Search

All methods below return a chainable `Builder` — the caller decides whether to call `.get()`, `.paginate()`, `.first()`, etc.

---

#### `allQuery(array $search = [], ?int $skip = null, ?int $limit = null): Builder`

Builds a base query with optional equality filters, skip and limit.  
Useful as a starting point for custom queries.

```php
// Returns all active records, skipping 10, limiting to 5
$builder = $this->productRepository->allQuery(
    search: ['active' => true],
    skip: 10,
    limit: 5,
);

$products = $builder->get();

// Chainable with more conditions
$products = $this->productRepository
    ->allQuery(['active' => true])
    ->where('price', '>', 100)
    ->orderBy('name')
    ->get();
```

---

#### `advancedSearch(Request $request, array $relations = []): Builder`

OR search via `search` param (comma-separated terms).  
Applies `LIKE %term%` on **all** table columns for each term.  
Ignored when `search == 'Search'` (UI sentinel).

```
GET /api/v1/products?search=premium,feed
→ WHERE (name LIKE '%premium%' OR price LIKE '%premium%' OR ...) AND (name LIKE '%feed%' OR ...)
```

```php
$builder = $this->productRepository->advancedSearch($request, ['category']);
$products = $builder->orderBy('name')->paginate(15);
```

---

#### `searchLike(Request $request, array $relations = []): Builder`

Incremental search via `searchLike` param (comma-separated terms).  
Supports **custom operators** via special tokens:

| Token | SQL operator |
|---|---|
| `col}val` | `col >= val` |
| `col{val` | `col <= val` |
| `col>val` | `col > val` |
| `col<val` | `col < val` |
| `term` (no token) | `LIKE %term%` on all columns |

The combination type between terms is controlled by `searchLikeType`:
- `AND` (default) — all terms must be satisfied
- `OR` — any of the terms

Also supports `whereIn` and `additionalQueries` (see [API Query Parameters](#api-query-parameters)).

```
GET /api/v1/products?searchLike=feed,price}50
→ WHERE (name LIKE '%feed%' OR ...) AND (price >= 50)
```

```php
$builder = $this->productRepository->searchLike($request);
```

---

#### `findAllFieldsAnd(Request $request, array $relations = []): Builder`

Filters using AND for each non-reserved request param as `column=value`.  
Columns are validated against the real schema before applying the filter (injection prevention).

```
GET /api/v1/products?active=1&category_id=3
→ WHERE active = 1 AND category_id = 3
```

```php
$builder = $this->productRepository->findAllFieldsAnd($request, ['category']);
```

---

#### `autocompleteSearch(Request $request, array $select, array $conditions): Builder`

Autocomplete search: specific SELECT + conditions + LIMIT 10.

```php
$builder = $this->productRepository->autocompleteSearch(
    request:    $request,
    select:     ['id', 'name'],
    conditions: [['name', 'LIKE', "%{$request->get('q')}%"]],
);

return $builder->get();
```

---

### Read Utilities

#### `findBy(string|array|Closure $reference, mixed $value = null, string $operator = '='): Builder`

Three signatures:

```php
// 1. Column + value (equality)
$builder = $this->productRepository->findBy('status', 'active');

// 2. Column + value + operator
$builder = $this->productRepository->findBy('price', 50, '>=');

// 3. Array of AND conditions
$builder = $this->productRepository->findBy([
    'status'      => 'active',
    'category_id' => 3,
]);

// 4. Closure for complex logic
$builder = $this->productRepository->findBy(
    fn ($q) => $q->where('price', '>', 50)->orWhere('featured', true)
);

// All return Builder — call get()/first() afterwards
$products = $builder->orderBy('name')->get();
```

---

#### `findByBuilder(Builder $query, string $column, string $operator = '=', mixed $value = null): Builder`

Applies a WHERE to an existing Builder. Useful when combined with `useIndex()`.

```php
$products = $this->productRepository
    ->findByBuilder(
        $this->productRepository->useIndex('idx_status'),
        'status', '=', 'active'
    )
    ->get();
```

---

#### `findByIn(string $column, array $values, array $with = []): Collection`

Returns records whose `$column` is in the `$values` list. Accepts eager-loading.

```php
$products = $this->productRepository->findByIn(
    column: 'status',
    values: ['active', 'featured'],
    with:   ['category'],
);
```

---

#### `buildSelectFields(Request $request): array`

Extracts columns from the `fields` param (csv), validated against the real schema.  
Returns `['*']` when no valid column is specified.

```
GET /api/v1/products?fields=id,name,price
→ SELECT id, name, price FROM products
```

```php
$select = $this->productRepository->buildSelectFields($request);
// ['id', 'name', 'price']
```

---

#### `getTableColumns(): array`

Returns the list of columns for the model's table. Result is memoised by table name.  
Exposed publicly for input validation in services/controllers.

```php
$columns = $this->productRepository->getTableColumns();
// ['id', 'name', 'price', 'active', 'category_id', 'created_at', 'updated_at']
```

---

#### `getKeyName(): string`

Returns the primary key name of the model.

```php
$key = $this->productRepository->getKeyName(); // 'id'
```

---

#### `useIndex(string $indexName): Builder`

`USE INDEX` hint for MySQL/MariaDB. On other drivers (PostgreSQL, SQLite) it is silently ignored.

```php
// MySQL: SELECT * FROM `products` USE INDEX (`idx_status`) WHERE status = ?
$builder = $this->productRepository
    ->useIndex('idx_status')
    ->where('status', 'active');

// Combined with findByBuilder
$products = $this->productRepository
    ->findByBuilder(
        $this->productRepository->useIndex('idx_name'),
        'name', 'LIKE', '%feed%'
    )
    ->get();
```

---

### Write Utilities

#### `updateBatch(array $ids, array $data): int`

Updates multiple records by ID in a single query. Returns the number of affected rows.  
**Note:** does not fire Eloquent events (`updating`/`updated`).

```php
$affected = $this->productRepository->updateBatch(
    ids:  [1, 2, 3, 4],
    data: ['active' => false],
);
// UPDATE products SET active = 0 WHERE id IN (1,2,3,4)
```

---

#### `updateQuietly(array $data, int|string $id): bool`

Updates without firing events or observers. Useful for internal audit fields.

```php
$this->productRepository->updateQuietly(['synced_at' => now()], 42);
```

---

#### `createQuietly(array $data): Model`

Creates a record without firing events or observers.

```php
$product = $this->productRepository->createQuietly([
    'name'  => 'Import product',
    'price' => 0,
]);
```

---

#### `replicate(): Model`

Returns an unsaved copy of the last record. Useful for duplicating entities.

```php
$copy = $this->productRepository->replicate();
$copy->name = 'Copy of ' . $copy->name;
$copy->save();
```

---

#### `truncate(): void`

Truncates the table with driver awareness:

| Driver | Behaviour |
|---|---|
| MySQL/MariaDB | `SET FOREIGN_KEY_CHECKS=0` → TRUNCATE → `=1` |
| PostgreSQL | `TRUNCATE ... RESTART IDENTITY CASCADE` |
| SQLite and others | Simple TRUNCATE |

```php
// Caution: irreversible
$this->productRepository->truncate();
```

---

## BaseRepositoryInterface

`Ptah\Base\BaseRepositoryInterface`

Defines the complete repository contract. Every concrete repository class must implement this interface (directly or via `BaseRepository`).

The benefit of using the interface is **container binding**:

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(
    \App\Contracts\ProductRepositoryInterface::class,
    \App\Repositories\ProductRepository::class,
);
```

This way the Service receives the interface via injection — it does not depend on the concrete implementation:

```php
class ProductService extends BaseService
{
    public function __construct(ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
```

---

## BaseService

`Ptah\Base\BaseService`

Orchestration layer. Delegates persistence to the repository and centralises business rules.  
All `BaseRepository` methods are available via delegation.

```php
namespace App\Services;

use App\Repositories\ProductRepository;
use Ptah\Base\BaseService;

class ProductService extends BaseService
{
    public function __construct(ProductRepository $repository)
    {
        parent::__construct($repository);
    }

    // Custom business methods go here
    public function activate(int $id): bool
    {
        $product = $this->findOrFail($id); // delegated to repository
        return (bool) $product->update(['active' => true]);
    }
}
```

### `$allowedRelations` Property

Controls which relations can be loaded via the `relations` request param.  
When empty (default), all requested relations are allowed.

```php
class ProductService extends BaseService
{
    // Only these relations can be eager-loaded via ?relations=
    protected array $allowedRelations = ['category', 'images'];
}
```

```
GET /api/v1/products?relations=category,images,secret_relation
→ loads only category and images (secret_relation is filtered out)
```

---

### CRUD via Service

CRUD methods are delegated directly to the repository:

| Method | Return | Description |
|---|---|---|
| `all()` | `Collection` | All records |
| `paginate(int $perPage)` | `LengthAwarePaginator` | Paginated records |
| `find(int\|string $id)` | `?Model` | By ID, null if not found |
| `show(int\|string $id)` | `?Model` | Alias of `find()` for controllers |
| `findOrFail(int\|string $id)` | `Model` | By ID, exception if not found |
| `create(array $data)` | `Model` | Creates and returns the model |
| `update(int\|string $id, array $data)` | `Model` | Updates and returns `fresh()` |
| `delete(int\|string $id)` | `bool` | Removes, throws exception if not found |
| `destroy(int\|string $id)` | `bool` | Removes, returns `false` if not found |

#### `delete()` vs `destroy()`

```php
// delete() — throws ModelNotFoundException if not found
try {
    $this->productService->delete(999); 
} catch (ModelNotFoundException $e) {
    // ...
}

// destroy() — returns false if not found (default for API controllers)
$deleted = $this->productService->destroy(999);
if (! $deleted) {
    return BaseResponse::notFound('Product not found');
}
```

---

### `getData` — listing entry point

`getData(Request $request): LengthAwarePaginator`

Single entry point for the paginated API listing. Automatically routes to the correct search method:

```
request has ?search=     → advancedSearch()
request has ?searchLike= → searchLike()
otherwise                → findAllFieldsAnd()
```

Internal validations:
- `order` is validated against real table columns (SQL injection prevention)
- `direction` accepts only `ASC` or `DESC`
- `relations` is filtered by `$allowedRelations` when defined

```php
// In the controller — single call
public function index(Request $request): JsonResponse
{
    $products = $this->service->getData($request);
    return BaseResponse::paginated($products, 'OK');
}

// Examples of handled requests:
// GET /api/v1/products                           → AND filter by fields
// GET /api/v1/products?search=feed               → OR LIKE on all columns
// GET /api/v1/products?searchLike=feed,price}50  → LIKE + price >= 50
// GET /api/v1/products?active=1&category_id=3    → exact AND
// GET /api/v1/products?order=name&direction=ASC  → ordering
// GET /api/v1/products?limit=50&page=2           → pagination
// GET /api/v1/products?relations=category        → eager-load
// GET /api/v1/products?fields=id,name,price      → partial SELECT
```

---

### Repository Delegations

All advanced repository methods are available in the service with the same signature:

```php
// Search by column/value
$this->productService->findBy('status', 'active')->get();

// Search with whereIn
$this->productService->findByIn('status', ['active', 'featured'], ['category']);

// Batch update
$this->productService->updateBatch([1, 2, 3], ['active' => false]);

// Without events
$this->productService->updateQuietly(['synced_at' => now()], 42);
$this->productService->createQuietly($data);

// MySQL index hint
$this->productService->useIndex('idx_status')->where('status', 'active')->get();
```

---

## API Layer

### BaseApiController

`App\Http\Controllers\API\BaseApiController`

Generated by `ptah:forge --api` in `app/Http/Controllers/API/`. Centralises the use of `BaseResponse` and provides response helpers for all API controllers.

```php
// Generated controllers extend this class
class ProductApiController extends BaseApiController { ... }
```

| Protected method | Description |
|---|---|
| `successResponse($data, $message, $status)` | Generic success response |
| `paginatedResponse($paginator, $message)` | Paginated response with meta |
| `errorResponse($message, $status, $errors)` | Error response with HTTP code |

---

### Generated Controller (`ptah:forge --api`)

The command `php artisan ptah:forge Product --api` generates a `ProductApiController` with the 5 standard REST endpoints. All use `BaseResponse` for a consistent response format.

#### `index(Request $request): JsonResponse`

```
GET /api/v1/products
```

Delegates to `$service->getData($request)` — supports all query params (see table below).

```json
{
  "message": "products listing returned successfully",
  "data": [...],
  "meta": { "current_page": 1, "per_page": 15, "total": 42 }
}
```

---

#### `store(Create{Entity}ApiRequest $request): JsonResponse`

```
POST /api/v1/products
Content-Type: application/json
```

Validates via `CreateProductApiRequest`, creates via service and returns HTTP 201.

```json
{
  "message": "Product created successfully",
  "data": { "id": 1, "name": "Premium Feed", "price": 49.90 }
}
```

---

#### `show(int|string $id): JsonResponse`

```
GET /api/v1/products/{id}
```

Returns HTTP 200 with the resource or HTTP 404 if not found.

```json
{
  "message": "Product returned successfully",
  "data": { "id": 1, "name": "Premium Feed" }
}
```

---

#### `update(Update{Entity}ApiRequest $request, int|string $id): JsonResponse`

```
PUT /api/v1/products/{id}
Content-Type: application/json
```

Validates, checks existence (HTTP 404 if not found), updates and returns HTTP 200.

```json
{
  "message": "Product updated successfully",
  "data": { "id": 1, "name": "Premium Feed Plus", "price": 59.90 }
}
```

---

#### `destroy(int|string $id): JsonResponse`

```
DELETE /api/v1/products/{id}
```

Returns HTTP 204 (no body) on success, HTTP 404 if not found.

---

### API Query Parameters

All available on `GET /api/v1/{resource}`:

| Parameter | Type | Example | Description |
|---|---|---|---|
| `limit` | `int` | `?limit=25` | Items per page (default: 15) |
| `page` | `int` | `?page=2` | Current page |
| `order` | `string` | `?order=name` | Sort column (validated against schema) |
| `direction` | `ASC\|DESC` | `?direction=ASC` | Sort direction (default: DESC) |
| `fields` | `string` (csv) | `?fields=id,name,price` | Columns to return — partial SELECT (validated) |
| `relations` | `string` (csv) | `?relations=category,images` | Relations for eager-loading |
| `search` | `string` (csv) | `?search=feed,premium` | OR LIKE on all columns for each term |
| `searchLike` | `string` (csv) | `?searchLike=feed,price}50` | Incremental LIKE with operator support |
| `searchLikeType` | `AND\|OR` | `?searchLikeType=OR` | Combination between searchLike terms (default: AND) |
| `whereIn` | `string` | `?whereIn=status:active,featured` | `WHERE status IN ('active','featured')` |
| `additionalQueries` | `string` | `?additionalQueries=price:>=:50;active:=:1` | Additional conditions with explicit operator |

#### `searchLike` — operator tokens

| Token | Generated SQL | Example |
|---|---|---|
| `col}val` | `col >= val` | `price}50` → `price >= 50` |
| `col{val` | `col <= val` | `price{100` → `price <= 100` |
| `col>val` | `col > val` | `quantity>0` → `quantity > 0` |
| `col<val` | `col < val` | `stock<5` → `stock < 5` |
| `term` | `LIKE %term%` on all columns | `feed` → searches all fields |

#### `whereIn` — format

```
?whereIn=status:active,featured;category_id:1,2,3
         └── col : csv values   └── second filter separated by ;
```

#### `additionalQueries` — format

```
?additionalQueries=price:>=:50;active:=:1;name:LIKE:%feed%
                   └── col:op:val separated by ;
```

Accepted operators: `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE`

---

## Full Flow — end-to-end example

### 1. Generate the files

```bash
php artisan ptah:forge Product --api
```

Generates:
- `app/Models/Product.php`
- `app/Repositories/ProductRepository.php`
- `app/Contracts/ProductRepositoryInterface.php`
- `app/Services/ProductService.php`
- `app/Http/Controllers/API/ProductApiController.php`
- `app/Http/Requests/API/CreateProductApiRequest.php`
- `app/Http/Requests/API/UpdateProductApiRequest.php`
- `app/Http/Resources/ProductResource.php`

### 2. Register the binding in `AppServiceProvider`

```php
$this->app->bind(
    \App\Contracts\ProductRepositoryInterface::class,
    \App\Repositories\ProductRepository::class,
);
```

### 3. Register the route

```php
// routes/api.php
Route::apiResource('products', ProductApiController::class);
```

### 4. Use — example requests

```bash
# List with search and pagination
GET /api/v1/products?search=feed&limit=20&order=name&direction=ASC

# List only active products in category 3
GET /api/v1/products?active=1&category_id=3&relations=category

# Incremental search: name contains 'feed' AND price >= 50
GET /api/v1/products?searchLike=feed,price}50

# Create
POST /api/v1/products
{ "name": "Premium Feed", "price": 49.90, "active": true }

# Update
PUT /api/v1/products/1
{ "price": 59.90 }

# Delete
DELETE /api/v1/products/1
```

### 5. Add business logic in the service

```php
class ProductService extends BaseService
{
    protected array $allowedRelations = ['category', 'images'];

    public function __construct(ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    // Custom method — uses only repository methods
    public function deactivateByCategory(int $categoryId): int
    {
        return $this->repository->updateBatch(
            ids:  $this->findBy('category_id', $categoryId)->pluck('id')->all(),
            data: ['active' => false],
        );
    }
}
```

---

## Security

| Risk | Mitigation implemented |
|---|---|
| SQL Injection via `order` | Column validated against `getTableColumns()` before applying |
| SQL Injection via `fields` | Columns validated against `getTableColumns()` — unknown ones are discarded |
| SQL Injection via `findAllFieldsAnd` | Request params validated against `getTableColumns()` |
| SQL Injection via `additionalQueries` | Column validated against schema; operator validated against `ALLOWED_OPERATORS` whitelist |
| SQL Injection via `whereIn` | Column validated against `getTableColumns()` |
| Arbitrary eager-load | `$allowedRelations` in the service filters out disallowed relations |
| Arbitrary operators | Only `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE` are accepted |

> 📄 See also: [BaseCrud.md](BaseCrud.md) · [SearchDropdown.md](SearchDropdown.md) · [Commands.md](Commands.md)

# Base Layer — Documentação Completa

**Namespace:** `Ptah\Base`  
**Arquivos:** `BaseDTO`, `BaseRepository`, `BaseRepositoryInterface`, `BaseService`  
**Geração via CLI:** `php artisan ptah:forge {Entity} --api`

---

## Sumário

1. [Visão Geral da Arquitetura](#visão-geral-da-arquitetura)
2. [BaseDTO](#basedto)
3. [BaseRepository](#baserepository)
   - [CRUD Básico](#crud-básico)
   - [Busca Avançada](#busca-avançada)
   - [Utilitários de Busca](#utilitários-de-busca)
   - [Utilitários de Escrita](#utilitários-de-escrita)
4. [BaseRepositoryInterface](#baserepositoryinterface)
5. [BaseService](#baseservice)
   - [CRUD via Service](#crud-via-service)
   - [getData — ponto de entrada da listagem](#getdata--ponto-de-entrada-da-listagem)
   - [Delegações para o Repository](#delegações-para-o-repository)
6. [Camada de API](#camada-de-api)
   - [BaseApiController](#baseapicontroller)
   - [Controller gerado (ptah:forge --api)](#controller-gerado-ptahforge---api)
   - [Parâmetros de query da API](#parâmetros-de-query-da-api)
7. [Fluxo completo — exemplo ponta a ponta](#fluxo-completo--exemplo-ponta-a-ponta)
8. [Segurança](#segurança)

---

## Visão Geral da Arquitetura

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

Cada camada tem responsabilidade única:

| Camada | Responsabilidade |
|---|---|
| **Controller** | Receber HTTP, devolver JSON — sem lógica de negócio |
| **Service** | Orquestrar regras de negócio, validar, transformar dados |
| **Repository** | Única camada que faz queries ao banco |
| **DTO** | Transportar dados estruturados entre camadas |

---

## BaseDTO

`Ptah\Base\BaseDTO`

Classe abstrata base para todos os DTOs do projeto. Define o contrato mínimo para criação, conversão e transporte de dados.

### Métodos abstratos

| Método | Retorno | Descrição |
|---|---|---|
| `fromArray(array $data)` | `static` | Cria uma instância DTO a partir de um array associativo |
| `fromRequest(Request $request)` | `static` | Cria uma instância DTO a partir de um `Request` Laravel |
| `toArray()` | `array<string, mixed>` | Converte o DTO para array associativo |

### Exemplo de implementação

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

### Uso

```php
// No Service — criar a partir do request validado
$dto = ProductDTO::fromRequest($request);
$this->repository->create($dto->toArray());

// Criar a partir de array (ex: seed, testes)
$dto = ProductDTO::fromArray(['name' => 'Ração Premium', 'price' => 49.90]);
```

---

## BaseRepository

`Ptah\Base\BaseRepository`

Implementação abstrata do repositório. Todas as queries ao banco ficam aqui.
Extende `BaseRepositoryInterface` e recebe o Model via construtor.

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

### CRUD Básico

#### `all(): Collection`
Retorna todos os registros da tabela.

```php
$products = $this->productRepository->all();
```

---

#### `paginate(int $perPage = 15): LengthAwarePaginator`
Retorna registros paginados.

```php
$products = $this->productRepository->paginate(25);
```

---

#### `find(int|string $id): ?Model`
Busca um registro pelo ID. Retorna `null` se não encontrado.

```php
$product = $this->productRepository->find(42);

if ($product === null) {
    // não encontrado
}
```

---

#### `findOrFail(int|string $id): Model`
Busca por ID. Lança `ModelNotFoundException` se não encontrado.

```php
$product = $this->productRepository->findOrFail(42);
// Lança ModelNotFoundException se id 42 não existir
```

---

#### `create(array $data): Model`
Cria um novo registro e retorna o model persistido.

```php
$product = $this->productRepository->create([
    'name'   => 'Ração Premium',
    'price'  => 49.90,
    'active' => true,
]);
// $product->id está preenchido
```

---

#### `update(int|string $id, array $data): Model`
Atualiza um registro e retorna o model com `fresh()` (recarregado do banco).  
Lança `ModelNotFoundException` se o ID não existir.

```php
$product = $this->productRepository->update(42, ['price' => 59.90]);
// $product reflete o estado atual do banco
```

---

#### `delete(int|string $id): bool`
Remove um registro. Respeita `SoftDelete` se o model usar a trait.  
Lança `ModelNotFoundException` se o ID não existir.

```php
$deleted = $this->productRepository->delete(42); // true
```

---

### Busca Avançada

Todos os métodos abaixo retornam um `Builder` chainável — o chamador decide se faz `.get()`, `.paginate()`, `.first()`, etc.

---

#### `allQuery(array $search = [], ?int $skip = null, ?int $limit = null): Builder`

Constrói uma query base com filtros de igualdade opcionais, skip e limit.  
Útil como ponto de partida para queries customizadas.

```php
// Retorna todos ativos, pulando 10, limitando a 5
$builder = $this->productRepository->allQuery(
    search: ['active' => true],
    skip: 10,
    limit: 5,
);

$products = $builder->get();

// Encadeável com mais condições
$products = $this->productRepository
    ->allQuery(['active' => true])
    ->where('price', '>', 100)
    ->orderBy('name')
    ->get();
```

---

#### `advancedSearch(Request $request, array $relations = []): Builder`

Busca OR via param `search` (termos separados por vírgula).  
Aplica `LIKE %term%` em **todas** as colunas da tabela para cada termo.  
Ignorado quando `search == 'Busca'` (sentinela de UI).

```
GET /api/v1/products?search=ração,premium
→ WHERE (name LIKE '%ração%' OR price LIKE '%ração%' OR ...) AND (name LIKE '%premium%' OR ...)
```

```php
$builder = $this->productRepository->advancedSearch($request, ['category']);
$products = $builder->orderBy('name')->paginate(15);
```

---

#### `searchLike(Request $request, array $relations = []): Builder`

Busca incremental via param `searchLike` (termos separados por vírgula).  
Suporta **operadores customizados** via tokens especiais:

| Token | Operador SQL |
|---|---|
| `col}val` | `col >= val` |
| `col{val` | `col <= val` |
| `col>val` | `col > val` |
| `col<val` | `col < val` |
| `termo` (sem token) | `LIKE %termo%` em todas as colunas |

O tipo de combinação entre termos é controlado por `searchLikeType`:
- `AND` (padrão) — todos os termos devem ser satisfeitos
- `OR` — qualquer um dos termos

Suporta também `whereIn` e `additionalQueries` (ver [Parâmetros de query da API](#parâmetros-de-query-da-api)).

```
GET /api/v1/products?searchLike=ração,price}50
→ WHERE (nome LIKE '%ração%' OR ...) AND (price >= 50)
```

```php
$builder = $this->productRepository->searchLike($request);
```

---

#### `findAllFieldsAnd(Request $request, array $relations = []): Builder`

Filtra usando AND para cada param de request não reservado como `coluna=valor`.  
Colunas são validadas contra o schema real antes de aplicar o filtro (prevenção de injeção).

```
GET /api/v1/products?active=1&category_id=3
→ WHERE active = 1 AND category_id = 3
```

```php
$builder = $this->productRepository->findAllFieldsAnd($request, ['category']);
```

---

#### `autocompleteSearch(Request $request, array $select, array $conditions): Builder`

Busca para autocomplete: SELECT específico + condições + LIMIT 10.

```php
$builder = $this->productRepository->autocompleteSearch(
    request:    $request,
    select:     ['id', 'name'],
    conditions: [['name', 'LIKE', "%{$request->get('q')}%"]],
);

return $builder->get();
```

---

### Utilitários de Busca

#### `findBy(string|array|Closure $reference, mixed $value = null, string $operator = '='): Builder`

Três assinaturas:

```php
// 1. Coluna + valor (igualdade)
$builder = $this->productRepository->findBy('status', 'active');

// 2. Coluna + valor + operador
$builder = $this->productRepository->findBy('price', 50, '>=');

// 3. Array de condições AND
$builder = $this->productRepository->findBy([
    'status'      => 'active',
    'category_id' => 3,
]);

// 4. Closure para lógica complexa
$builder = $this->productRepository->findBy(
    fn ($q) => $q->where('price', '>', 50)->orWhere('featured', true)
);

// Todos retornam Builder — fazer get()/first() depois
$products = $builder->orderBy('name')->get();
```

---

#### `findByBuilder(Builder $query, string $column, string $operator = '=', mixed $value = null): Builder`

Aplica um WHERE em um Builder existente. Útil quando combinado com `useIndex()`.

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

Retorna registros cujo `$column` está na lista `$values`. Aceita eager-load.

```php
$products = $this->productRepository->findByIn(
    column: 'status',
    values: ['active', 'featured'],
    with:   ['category'],
);
```

---

#### `buildSelectFields(Request $request): array`

Extrai as colunas do param `fields` (csv), validadas contra o schema real.  
Retorna `['*']` quando nenhuma coluna válida é especificada.

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

Retorna a lista de colunas da tabela do model. Resultado memoizado por nome de tabela.  
Exposto publicamente para validação de input em services/controllers.

```php
$columns = $this->productRepository->getTableColumns();
// ['id', 'name', 'price', 'active', 'category_id', 'created_at', 'updated_at']
```

---

#### `getKeyName(): string`

Retorna o nome da chave primária do model.

```php
$key = $this->productRepository->getKeyName(); // 'id'
```

---

#### `useIndex(string $indexName): Builder`

Hint `USE INDEX` para MySQL/MariaDB. Em outros drivers (PostgreSQL, SQLite) é ignorado silenciosamente.

```php
// MySQL: SELECT * FROM `products` USE INDEX (`idx_status`) WHERE status = ?
$builder = $this->productRepository
    ->useIndex('idx_status')
    ->where('status', 'active');

// Combinando com findByBuilder
$products = $this->productRepository
    ->findByBuilder(
        $this->productRepository->useIndex('idx_name'),
        'name', 'LIKE', '%ração%'
    )
    ->get();
```

---

### Utilitários de Escrita

#### `updateBatch(array $ids, array $data): int`

Atualiza múltiplos registros por ID em uma única query. Retorna o número de linhas afetadas.  
**Atenção:** não dispara eventos Eloquent (`updating`/`updated`).

```php
$affected = $this->productRepository->updateBatch(
    ids:  [1, 2, 3, 4],
    data: ['active' => false],
);
// UPDATE products SET active = 0 WHERE id IN (1,2,3,4)
```

---

#### `updateQuietly(array $data, int|string $id): bool`

Atualiza sem disparar eventos ou observers. Útil para campos de auditoria interna.

```php
$this->productRepository->updateQuietly(['synced_at' => now()], 42);
```

---

#### `createQuietly(array $data): Model`

Cria um registro sem disparar eventos ou observers.

```php
$product = $this->productRepository->createQuietly([
    'name'  => 'Import produto',
    'price' => 0,
]);
```

---

#### `replicate(): Model`

Retorna uma cópia não-salva do último registro. Útil para duplicar entidades.

```php
$copy = $this->productRepository->replicate();
$copy->name = 'Cópia de ' . $copy->name;
$copy->save();
```

---

#### `truncate(): void`

Trunca a tabela com ciência do driver:

| Driver | Comportamento |
|---|---|
| MySQL/MariaDB | `SET FOREIGN_KEY_CHECKS=0` → TRUNCATE → `=1` |
| PostgreSQL | `TRUNCATE ... RESTART IDENTITY CASCADE` |
| SQLite e outros | TRUNCATE simples |

```php
// Cuidado: irreversível
$this->productRepository->truncate();
```

---

## BaseRepositoryInterface

`Ptah\Base\BaseRepositoryInterface`

Define o contrato completo do repositório. Toda classe de repositório concreta deve implementar esta interface (diretamente ou via `BaseRepository`).

O benefício de usar a interface é o **bind no container**:

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(
    \App\Contracts\ProductRepositoryInterface::class,
    \App\Repositories\ProductRepository::class,
);
```

Com isso o Service recebe a interface via injeção — não depende da implementação concreta:

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

Camada de orquestração. Delega persistência ao repository e concentra regras de negócio.  
Todos os methods do `BaseRepository` estão disponíveis via delegação.

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

    // Métodos customizados de negócio ficam aqui
    public function activate(int $id): bool
    {
        $product = $this->findOrFail($id); // delegado ao repository
        return (bool) $product->update(['active' => true]);
    }
}
```

### Propriedade `$allowedRelations`

Controla quais relações podem ser carregadas via param `relations` da request.  
Quando vazio (padrão), todas as relações solicitadas são permitidas.

```php
class ProductService extends BaseService
{
    // Somente estas relações podem ser eager-loaded via ?relations=
    protected array $allowedRelations = ['category', 'images'];
}
```

```
GET /api/v1/products?relations=category,images,secret_relation
→ carrega apenas category e images (secret_relation é filtrada)
```

---

### CRUD via Service

Os métodos de CRUD são delegados diretamente ao repository:

| Método | Retorno | Descrição |
|---|---|---|
| `all()` | `Collection` | Todos os registros |
| `paginate(int $perPage)` | `LengthAwarePaginator` | Registros paginados |
| `find(int\|string $id)` | `?Model` | Por ID, null se não encontrado |
| `show(int\|string $id)` | `?Model` | Alias de `find()` para controllers |
| `findOrFail(int\|string $id)` | `Model` | Por ID, exceção se não encontrado |
| `create(array $data)` | `Model` | Cria e retorna o model |
| `update(int\|string $id, array $data)` | `Model` | Atualiza e retorna `fresh()` |
| `delete(int\|string $id)` | `bool` | Remove, lança exceção se não encontrado |
| `destroy(int\|string $id)` | `bool` | Remove, retorna `false` se não encontrado |

#### `delete()` vs `destroy()`

```php
// delete() — lança ModelNotFoundException se não existir
try {
    $this->productService->delete(999); 
} catch (ModelNotFoundException $e) {
    // ...
}

// destroy() — retorna false se não existir (padrão para controllers de API)
$deleted = $this->productService->destroy(999);
if (! $deleted) {
    return BaseResponse::notFound('Product not found');
}
```

---

### `getData` — ponto de entrada da listagem

`getData(Request $request): LengthAwarePaginator`

Ponto de entrada único para a listagem paginada da API. Roteia automaticamente para o método de busca correto:

```
request tem ?search=    → advancedSearch()
request tem ?searchLike= → searchLike()
caso contrário           → findAllFieldsAnd()
```

Validações internas:
- `order` é validado contra as colunas reais da tabela (prevenção de SQL injection)
- `direction` aceita apenas `ASC` ou `DESC`
- `relations` é filtrado por `$allowedRelations` quando definido

```php
// No controller — chamada única
public function index(Request $request): JsonResponse
{
    $products = $this->service->getData($request);
    return BaseResponse::paginated($products, 'OK');
}

// Exemplos de requests atendidos:
// GET /api/v1/products                          → AND filter por campos
// GET /api/v1/products?search=ração             → OR LIKE em todas as colunas
// GET /api/v1/products?searchLike=ração,price}50 → LIKE + price >= 50
// GET /api/v1/products?active=1&category_id=3   → AND exato
// GET /api/v1/products?order=name&direction=ASC → ordenação
// GET /api/v1/products?limit=50&page=2          → paginação
// GET /api/v1/products?relations=category       → eager-load
// GET /api/v1/products?fields=id,name,price     → SELECT parcial
```

---

### Delegações para o Repository

Todos os métodos avançados do repository estão disponíveis no service com a mesma assinatura:

```php
// Busca por coluna/valor
$this->productService->findBy('status', 'active')->get();

// Busca com whereIn
$this->productService->findByIn('status', ['active', 'featured'], ['category']);

// Update em lote
$this->productService->updateBatch([1, 2, 3], ['active' => false]);

// Sem eventos
$this->productService->updateQuietly(['synced_at' => now()], 42);
$this->productService->createQuietly($data);

// Hint de índice MySQL
$this->productService->useIndex('idx_status')->where('status', 'active')->get();
```

---

## Camada de API

### BaseApiController

`App\Http\Controllers\API\BaseApiController`

Gerado por `ptah:forge --api` em `app/Http/Controllers/API/`. Centraliza o uso de `BaseResponse` e fornece helpers de resposta para todos os controllers de API.

```php
// Os controllers gerados estendem esta classe
class ProductApiController extends BaseApiController { ... }
```

| Método protegido | Descrição |
|---|---|
| `successResponse($data, $message, $status)` | Resposta genérica de sucesso |
| `paginatedResponse($paginator, $message)` | Resposta paginada com meta |
| `errorResponse($message, $status, $errors)` | Resposta de erro com código HTTP |

---

### Controller gerado (`ptah:forge --api`)

O comando `php artisan ptah:forge Product --api` gera um `ProductApiController` com os 5 endpoints padrão REST. Todos usam `BaseResponse` para formato consistente de resposta.

#### `index(Request $request): JsonResponse`

```
GET /api/v1/products
```

Delega para `$service->getData($request)` — suporta todos os params de query (ver tabela abaixo).

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

Valida via `CreateProductApiRequest`, cria via service e retorna HTTP 201.

```json
{
  "message": "Product created successfully",
  "data": { "id": 1, "name": "Ração Premium", "price": 49.90 }
}
```

---

#### `show(int|string $id): JsonResponse`

```
GET /api/v1/products/{id}
```

Retorna HTTP 200 com o resource ou HTTP 404 se não encontrado.

```json
{
  "message": "Product returned successfully",
  "data": { "id": 1, "name": "Ração Premium" }
}
```

---

#### `update(Update{Entity}ApiRequest $request, int|string $id): JsonResponse`

```
PUT /api/v1/products/{id}
Content-Type: application/json
```

Valida, verifica existência (HTTP 404 se não encontrado), atualiza e retorna HTTP 200.

```json
{
  "message": "Product updated successfully",
  "data": { "id": 1, "name": "Ração Premium Plus", "price": 59.90 }
}
```

---

#### `destroy(int|string $id): JsonResponse`

```
DELETE /api/v1/products/{id}
```

Retorna HTTP 204 (sem corpo) em caso de sucesso, HTTP 404 se não encontrado.

---

### Parâmetros de query da API

Todos disponíveis em `GET /api/v1/{resource}`:

| Parâmetro | Tipo | Exemplo | Descrição |
|---|---|---|---|
| `limit` | `int` | `?limit=25` | Itens por página (padrão: 15) |
| `page` | `int` | `?page=2` | Página atual |
| `order` | `string` | `?order=name` | Coluna de ordenação (validada contra schema) |
| `direction` | `ASC\|DESC` | `?direction=ASC` | Direção de ordenação (padrão: DESC) |
| `fields` | `string` (csv) | `?fields=id,name,price` | Colunas a retornar — SELECT parcial (validadas) |
| `relations` | `string` (csv) | `?relations=category,images` | Relações para eager-load |
| `search` | `string` (csv) | `?search=ração,premium` | OR LIKE em todas as colunas para cada termo |
| `searchLike` | `string` (csv) | `?searchLike=ração,price}50` | LIKE incremental com suporte a operadores |
| `searchLikeType` | `AND\|OR` | `?searchLikeType=OR` | Combinação entre termos do searchLike (padrão: AND) |
| `whereIn` | `string` | `?whereIn=status:active,featured` | `WHERE status IN ('active','featured')` |
| `additionalQueries` | `string` | `?additionalQueries=price:>=:50;active:=:1` | Condições adicionais com operador explícito |

#### `searchLike` — tokens de operador

| Token | SQL gerado | Exemplo |
|---|---|---|
| `col}val` | `col >= val` | `price}50` → `price >= 50` |
| `col{val` | `col <= val` | `price{100` → `price <= 100` |
| `col>val` | `col > val` | `quantity>0` → `quantity > 0` |
| `col<val` | `col < val` | `stock<5` → `stock < 5` |
| `termo` | `LIKE %termo%` em todas as colunas | `ração` → busca em todos os campos |

#### `whereIn` — formato

```
?whereIn=status:active,featured;category_id:1,2,3
         └── col : valores csv  └── segundo filtro separado por ;
```

#### `additionalQueries` — formato

```
?additionalQueries=price:>=:50;active:=:1;name:LIKE:%ração%
                   └── col:op:val separados por ;
```

Operadores aceitos: `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE`

---

## Fluxo completo — exemplo ponta a ponta

### 1. Gerar os arquivos

```bash
php artisan ptah:forge Product --api
```

Gera:
- `app/Models/Product.php`
- `app/Repositories/ProductRepository.php`
- `app/Contracts/ProductRepositoryInterface.php`
- `app/Services/ProductService.php`
- `app/Http/Controllers/API/ProductApiController.php`
- `app/Http/Requests/API/CreateProductApiRequest.php`
- `app/Http/Requests/API/UpdateProductApiRequest.php`
- `app/Http/Resources/ProductResource.php`

### 2. Registrar o bind no `AppServiceProvider`

```php
$this->app->bind(
    \App\Contracts\ProductRepositoryInterface::class,
    \App\Repositories\ProductRepository::class,
);
```

### 3. Registrar a rota

```php
// routes/api.php
Route::apiResource('products', ProductApiController::class);
```

### 4. Usar — requests de exemplo

```bash
# Listar com busca e paginação
GET /api/v1/products?search=ração&limit=20&order=name&direction=ASC

# Listar apenas produtos ativos da categoria 3
GET /api/v1/products?active=1&category_id=3&relations=category

# Busca incremental: nome contém 'ração' E preço >= 50
GET /api/v1/products?searchLike=ração,price}50

# Criar
POST /api/v1/products
{ "name": "Ração Premium", "price": 49.90, "active": true }

# Atualizar
PUT /api/v1/products/1
{ "price": 59.90 }

# Remover
DELETE /api/v1/products/1
```

### 5. Adicionar lógica de negócio no service

```php
class ProductService extends BaseService
{
    protected array $allowedRelations = ['category', 'images'];

    public function __construct(ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    // Método customizado — usa apenas métodos do repository
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

## Segurança

| Risco | Mitigação implementada |
|---|---|
| SQL Injection via `order` | Coluna validada contra `getTableColumns()` antes de aplicar |
| SQL Injection via `fields` | Colunas validadas contra `getTableColumns()` — desconhecidas são descartadas |
| SQL Injection via `findAllFieldsAnd` | Params de request validados contra `getTableColumns()` |
| SQL Injection via `additionalQueries` | Coluna validada contra schema; operador validado contra `ALLOWED_OPERATORS` whitelist |
| SQL Injection via `whereIn` | Coluna validada contra `getTableColumns()` |
| Eager-load arbitrário | `$allowedRelations` no service filtra relações não permitidas |
| Operadores arbitrários | Apenas `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE` são aceitos |

> 📄 Ver também: [BaseCrud.md](BaseCrud.md) · [SearchDropdown.md](SearchDropdown.md) · [Commands.md](Commands.md)

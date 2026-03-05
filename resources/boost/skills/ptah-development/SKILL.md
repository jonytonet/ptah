---
name: ptah-development
description: Build and work with jonytonet/ptah — covering SOLID layered architecture, design tokens, scaffolding, BaseCrud configuration, Livewire conventions, optional modules, and tests. Use this skill whenever creating or modifying any entity, component or module in a Ptah-based project.
---

# Ptah Development Skill

## When to use this skill

Use this skill when:
- Creating or modifying entities (Model, Service, Repository, DTO, Livewire)
- Configuring BaseCrud columns, filters, modal or row styles
- Activating or building optional modules (auth, menu, company, permissions)
- Writing Livewire components, view files or CSS
- Writing tests (unit or feature)
- Deciding where business logic, queries or validation belong

---

## SOLID Architecture — Layer Rules (NEVER violate)

### Layer map

```
HTTP Request
     │
     ▼
FormRequest          → validates HTTP input only
     │
     ▼
Controller / Livewire → calls Service via Contract; NO queries, NO business logic
     │
     ▼
ServiceContract      → interface in app/Contracts/Services/
     │
     ▼
Service              → ALL business logic; calls RepositoryContract; throws domain exceptions
     │
     ▼
RepositoryContract   → interface in app/Contracts/Repositories/
     │
     ▼
Repository           → ALL database access; Eloquent queries, filters, pagination
     │
     ▼
Model                → schema, casts, scopes, relationships, boot hooks; NO logic
     │
     ▼
DTO                  → immutable value object; fromArray(); passed between layers
```

### Single Responsibility — what each layer owns

| Layer | Owns | Must NOT contain |
|---|---|---|
| Model | `$fillable`, `$casts`, scopes, relationships, `boot()` hooks | Business rules, DB queries in methods |
| DTO | `readonly` properties, `fromArray()`, `toArray()` | Persistence, validation |
| Repository | Eloquent queries, raw SQL, pagination, eager loads | Business rules, HTTP/session awareness |
| Service | Business rules, orchestration, events, domain exceptions | Eloquent queries, HTTP redirects |
| FormRequest | `rules()`, `authorize()`, `messages()` | Business logic |
| Livewire/Controller | Call Service, return view/response | Queries, business rules |

### Dependency Inversion — always inject Contracts

```php
// ✅ Correct
public function __construct(
    private readonly ProductServiceContract $products,
    private readonly CategoryRepositoryContract $categories,
) {}

// ❌ Wrong — never inject concrete classes
public function __construct(private readonly ProductService $products) {}
```

### Concrete example of correct layer separation

```php
// ❌ Anti-pattern: business logic leaking into Livewire
public function save(): void
{
    $exists = Product::where('sku', $this->sku)->exists(); // query in Livewire ❌
    if ($exists) { $this->addError('sku', 'Duplicado'); return; }
    Product::create([...]); // direct create in Livewire ❌
}

// ✅ Correct: Livewire → Service (via Contract) → Repository
// Livewire:
public function save(): void
{
    $this->validate();
    try {
        $this->products->create(ProductDTO::fromArray($this->only(['name','sku','price'])));
        $this->showModal = false;
    } catch (DuplicateSkuException $e) {
        $this->addError('sku', $e->getMessage());
    }
}

// Service:
public function create(ProductDTO $dto): Product
{
    if ($this->repo->existsBySku($dto->sku)) {
        throw new DuplicateSkuException("SKU {$dto->sku} já cadastrado.");
    }
    return $this->repo->create($dto->toArray());
}

// Repository:
public function existsBySku(string $sku): bool
{
    return Product::where('sku', $sku)->exists();
}
```

---

## Design Tokens — always use, never hardcode

| Token | Hex | Tailwind / component prop |
|---|---|---|
| `primary` | `#5b21b6` | `bg-primary` `text-primary` `color="primary"` |
| `success` | `#10b981` | `bg-success` `text-success` `color="success"` |
| `danger` | `#ef4444` | `bg-danger` `text-danger` `color="danger"` |
| `warn` | `#f59e0b` | `bg-warn` `text-warn` `color="warn"` |
| `dark` | `#1e293b` | `bg-dark` `text-dark` |
| `light` | `#f8fafc` | `bg-light` `color="light"` |

```blade
{{-- ✅ Always use color props --}}
<x-forge-button color="primary">Salvar</x-forge-button>
<x-forge-button color="danger" flat>Excluir</x-forge-button>
<x-forge-alert type="success">Salvo!</x-forge-alert>

{{-- ❌ Never hardcode --}}
<button style="background:#5b21b6">Salvar</button>
```

---

## Scaffolding New Entities

```bash
# Single entity
php artisan ptah:forge Product \
  --fields="name:string,sku:string,price:decimal,stock:integer,category_id:unsignedBigInteger,is_active:boolean" \
  --soft-delete

# Sub-folder (large projects)
php artisan ptah:forge Inventory/ProductStock \
  --fields="product_id:unsignedBigInteger,location:string,qty:integer"
# model key = 'Inventory/ProductStock'
# namespace = App\Models\Inventory\ProductStock

# With API (web + API in one command)
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal,category_id:unsignedBigInteger" \
  --api
```

### Menu Automático

Cada entidade gerada com subfolder **adiciona automaticamente** um link no menu da sidebar:

```bash
# Durante scaffolding
php artisan ptah:forge Health/VaccinationType --fields="..."
# → Adiciona entrada em database/seeders/MenuRegistry.php

# Após gerar todas as entidades, sincronizar menu:
php artisan ptah:menu-sync --fresh
# → Popula tabela 'menus' com todos os links
```

**Mapeamentos automáticos:**
- Módulo `Health` → grupo "Saúde" (ícone `bx bx-plus-medical`)
- Entidade `VaccinationType` → link "Tipos de Vacina" (ícone `bx bx-shield-plus`)

**Desabilitar menu de uma entidade:**
```bash
php artisan ptah:forge Health/Test --fields="..." --no-menu
```

---

## Post-scaffold Checklist (MANDATORY after every ptah:forge)

After running `ptah:forge` and `php artisan migrate`, **always** perform these steps:

### 1. Fix FK `use` imports in every generated Model

The generator intentionally leaves `// TODO:` comments for FK relationships
because it cannot know which sub-folder the related model lives in:

```php
// Generated (NEEDS to be fixed):
// TODO: use App\Models\Category; // verifique o namespace real — ajuste se Category estiver em sub-pasta

// ✅ If Category is in App\Models\Catalog\ :
use App\Models\Catalog\Category;

// ✅ If Category is in the root App\Models\ :
use App\Models\Category;
```

**Rule:** For every `// TODO: use` line in a generated model:
- Find where the related model file actually lives (`find app/Models -name 'Category.php'`)
- Replace the TODO comment with the correct `use` statement
- Never leave `// TODO:` lines in committed code

### 2. Run Pint to format all generated files

```bash
./vendor/bin/pint
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Clear views and config cache

```bash
php artisan view:clear
php artisan config:clear
```

---

## HasAuditFields — Automatic Audit Columns

Every model generated by `ptah:forge` automatically includes the `Ptah\Traits\HasAuditFields` trait and audit columns in its migration. All internal package models (Company, Department, Role, Menu, CrudConfig, PtahPage, PageObject, UserRole, RolePermission) also use this trait.

### What it does

| Column | Type | Filled when |
|---|---|---|
| `created_by` | `unsignedBigInteger` nullable | Eloquent `creating` event |
| `updated_by` | `unsignedBigInteger` nullable | Eloquent `creating` and `updating` events |
| `deleted_by` | `unsignedBigInteger` nullable | Eloquent `deleted` event (only when `--soft-delete`) |

### Critical rules for agents

```
✅ NEVER manually set created_by / updated_by in service, controller or Livewire
   The HasAuditFields trait fills them automatically via Eloquent events.
   BaseCrud::save() also injects them explicitly as a belt-and-suspenders safeguard.

✅ NEVER use ->whereIn()->delete() for bulk ops on models with HasAuditFields
   Use ->each(fn($r) => $r->delete()) so Eloquent fires the `deleted` event per record
   and deleted_by is stamped correctly on each row.

✅ deleted_by uses the `deleted` event (after soft-delete commits), NOT `deleting`
   This prevents stamping deleted_by on a record whose soft-delete later fails.

✅ Guard is === null (not empty()) so user ID 0 is not treated as "unset".
```

### Available relationships

```php
$record->createdBy  // BelongsTo → User (resolved via auth.providers.users.model)
$record->updatedBy  // BelongsTo → User
$record->deletedBy  // BelongsTo → User
```

### Required model setup

```php
use Ptah\Traits\HasAuditFields;

class Product extends Model
{
    use HasAuditFields;

    protected $fillable = [
        // ... your fields ...
        'created_by', 'updated_by', // always
        'deleted_by',               // only if model uses SoftDeletes
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer', // only if SoftDeletes
    ];
}
```

`ptah:forge` generates all of the above automatically — no manual setup needed for scaffolded entities.

---

## CSS Architecture Rules

1. **Never** add `<style>` blocks inside view files
2. All CSS (including dark overrides) lives in `forge-dashboard-layout.blade.php`
3. Dark mode always via `.ptah-dark` ancestor:

```css
/* ✅ Inside forge-dashboard-layout.blade.php <style> tag */
.my-component { background: #ffffff; color: #1e293b; }
.ptah-dark .my-component { background: #1e293b; color: #f8fafc; }
```

---

## Configuring BaseCrud (CLI)

### Via Command Line

```bash
# Configure complete CRUD in one command
php artisan ptah:config "App\Models\Product" \
  --column="id:text:label=ID:sortable=true:width=80" \
  --column="name:text:label=Nome:sortable=true:searchable=true" \
  --column="price:money:label=Preço:sortable=true" \
  --column="is_active:badge:label=Status:badgeMap=1:success:Ativo,0:danger:Inativo" \
  --style="is_active:eq:0:bg-red-50 text-red-700" \
  --style="stock:lt:5:bg-yellow-50 text-yellow-700" \
  --filter="is_active:boolean:eq:Ativos" \
  --action="duplicate:wire:duplicate:bx bx-copy:info:Duplicar?" \
  --set="itemsPerPage=15" \
  --set="cacheEnabled=true"

# List current configuration
php artisan ptah:config "App\Models\Product" --list

# Export to JSON
php artisan ptah:config "App\Models\Product" --export=product-config.json

# Import from JSON
php artisan ptah:config "App\Models\Product" --import=product-config.json

# Reset to defaults
php artisan ptah:config "App\Models\Product" --reset

# Dry-run (show changes without saving)
php artisan ptah:config "App\Models\Product" --column="..." --dry-run
```

### Option Formats

#### --column

Format: `field:type:modifier1:modifier2:option1=value1:option2=value2`

**Types:**
- `text` — Plain text
- `badge` — Colored badge (requires `badgeMap`)
- `boolean` — ✓/✗ icon
- `date` — Formatted date (DD/MM/YYYY)
- `datetime` — Date + time
- `money` — Currency (R$ 1.234,56)
- `numeric` — Formatted number
- `relation` — Relationship (requires `relation=model.field`)

**Modifiers:**
- `sortable=true` — Enable sorting
- `searchable=true` — Enable search
- `label=Text` — Column label
- `width=80` — Width in pixels
- `badgeMap=val1:color1:text1,val2:color2:text2` — Badge mapping
- `relation=model.field` — Relationship path

#### --style

Format: `field:operator:value:css_classes`

**Operators:** `eq`, `ne`, `lt`, `gt`, `lte`, `gte`

**Example:** `is_active:eq:0:bg-red-50 text-red-700`

#### --filter

Format: `field:type:operator:label[:default_value]`

**Types:** `boolean`, `select`, `numeric`, `date`

**Example:** `status:select:eq:Pendentes:pending`

#### --action

Format: `name:type:method:icon:color[:confirm_message]`

**Types:** `wire` (Livewire method), `route` (redirect), `url` (external)

**Colors:** `primary`, `success`, `danger`, `warning`, `info`

**Example:** `duplicate:wire:duplicate:bx bx-copy:info:Deseja duplicar?`

#### --set

Format: `key=value`

**Settings:** `itemsPerPage=15`, `cacheEnabled=true`, `cacheTime=30`, `paginationEnabled=true`, `exportEnabled=true`

---

## Configuring BaseCrud (JSON)

```json
{
  "model": "Product",
  "cols": [
    { "field": "name",      "label": "Nome",   "type": "text",    "sort": true, "search": true },
    { "field": "sku",       "label": "SKU",    "type": "badge",   "badgeColor": "primary" },
    { "field": "price",     "label": "Preço",  "type": "money",   "sort": true },
    { "field": "is_active", "label": "Ativo",  "type": "boolean" }
  ],
  "rowStyles": [
    { "field": "stock", "op": "<", "value": 5, "class": "bg-red-50 dark:bg-red-900/20" }
  ],
  "modal": {
    "width": "md",
    "fields": [
      { "field": "name",        "type": "text",           "label": "Nome",      "required": true },
      { "field": "sku",         "type": "text",           "label": "SKU" },
      { "field": "price",       "type": "number",         "label": "Preço" },
      { "field": "category_id", "type": "searchDropdown", "label": "Categoria", "relation": "category", "display": "name" },
      { "field": "is_active",   "type": "switch",         "label": "Ativo" }
    ]
  },
  "quickFilters": {
    "date_field": "created_at",
    "options": ["today", "week", "month", "year"]
  }
}
```

Badge enum mapping:
```json
{
  "field": "status",
  "type": "badge",
  "badgeMap": {
    "active":   { "label": "Ativo",    "color": "success" },
    "inactive": { "label": "Inativo",  "color": "danger"  },
    "pending":  { "label": "Pendente", "color": "warn"    }
  }
}
```

---

## Livewire Input Rules

```blade
{{-- Text, email, phone, tax → .blur (no re-renders while typing) --}}
<x-forge-input wire:model.blur="name"  name="name"  label="Nome" />
<x-forge-input wire:model.blur="email" name="email" label="E-mail" />

{{-- Switch / checkbox / select → .live (immediate UI feedback needed) --}}
<x-forge-switch wire:model.live="is_active" name="is_active" label="Ativo" />
```

Unique validation with self-exclusion:
```php
use Illuminate\Validation\Rule;

protected function rules(): array
{
    return [
        'sku' => [
            'required', 'string', 'max:50',
            Rule::unique('products', 'sku')->ignore($this->editingId),
        ],
    ];
}
```

---

## Optional Modules

```bash
php artisan ptah:module auth         # Login, 2FA TOTP+email, sessions, profile
php artisan ptah:module menu         # Dynamic sidebar (driver: config or database)
php artisan ptah:module company      # Multi-company + departments
php artisan ptah:module permissions  # RBAC: roles, page objects, CRUD + audit
php artisan ptah:module --list       # Status of all modules
php artisan ptah:install --demo      # Seed demo companies/roles/menu
```

---

## Writing Tests

```php
use Livewire\Livewire;
use Ptah\Tests\TestCase;  // Testbench + RefreshDatabase + SQLite :memory:
use Tests\Factories\ProductFactory;  // Custom factory — NO Eloquent Factory

class ProductListTest extends TestCase
{
    public function test_can_create(): void
    {
        Livewire::test(ProductList::class)
            ->call('create')
            ->set('name', 'Widget')->set('sku', 'WGT-001')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('products', ['sku' => 'WGT-001']);
    }

    public function test_sku_unique(): void
    {
        ProductFactory::new()->create(['sku' => 'DUP']);

        Livewire::test(ProductList::class)
            ->call('create')->set('sku', 'DUP')->call('save')
            ->assertHasErrors(['sku']);
    }

    public function test_can_edit_own_sku(): void
    {
        $p = ProductFactory::new()->create(['sku' => 'MINE']);

        Livewire::test(ProductList::class)
            ->call('edit', $p->id)->set('name', 'New Name')->call('save')
            ->assertHasNoErrors();
    }

    public function test_soft_delete(): void
    {
        $p = ProductFactory::new()->create();

        Livewire::test(ProductList::class)
            ->call('confirmDelete', $p->id)
            ->call('delete');

        $this->assertSoftDeleted('products', ['id' => $p->id]);
    }
}
```

Factory pattern:
```php
class ProductFactory
{
    public static function new(): static { return new static(); }

    public function create(array $attrs = []): Product
    {
        $m = new Product(array_merge([
            'name' => 'Product ' . \Str::random(4),
            'sku'  => strtoupper(\Str::random(6)),
            'price' => 49.90, 'is_active' => true,
        ], $attrs));
        $m->save();
        return $m->fresh();
    }
}
```

---

## API Module (`ptah:module api`)

### Activação

```bash
php artisan ptah:module api
```

Instala automaticamente `darkaonline/l5-swagger` e publica:
- `app/Responses/BaseResponse.php` — envelope padrão de resposta
- `app/Http/Controllers/API/BaseApiController.php` — controller base
- `app/Http/Controllers/API/SwaggerInfo.php` — metadados Swagger (`@OA\Info`, `@OA\SecurityScheme`)

### Gerando entidades com API

```bash
# Modo combinado (web + API em um único comando) — recomendado
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal,category_id:unsignedBigInteger,is_active:boolean" \
  --api
```

Gera automaticamente **web e API juntos**:
- `app/Http/Controllers/Catalog/ProductController.php` — controller web (Livewire)
- `resources/views/livewire/catalog/product/` — views
- `app/Http/Controllers/API/Catalog/ProductController.php` — Swagger `@OA\*` completo
- `app/Http/Requests/API/Catalog/CreateProductApiRequest.php`
- `app/Http/Requests/API/Catalog/UpdateProductApiRequest.php`
- `app/Models/Catalog/Product.php` — `@OA\Schema` gerado
- `routes/web/catalog/product.php` + `routes/api/catalog/product.php` — `Route::prefix('v1')`

> **Model preservado:** Se a entidade já existir, `--api` injeta apenas o bloco `@OA\Schema` na model
> sem sobrescrever `$fillable`, `$casts` ou relacionamentos.

> **Somente API (sem views):** use `--api-only` — comportamento legado do antigo `--api`.

### Workflow completo

```bash
# 1. Instalar módulo (uma vez por projeto)
php artisan ptah:module api

# 2. Gerar entidade (web + API juntos)
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal" \
  --api

# 3. Corrigir TODOs de imports nos arquivos gerados
# 4. Rodar pint
./vendor/bin/pint

# 5. Migrar
php artisan migrate

# 6. Gerar documentação Swagger
php artisan l5-swagger:generate

# 7. Acessar docs
# http://localhost/api/documentation
```

### BaseResponse — regras de uso

**SEMPRE** use `BaseResponse::` — **NUNCA** use `response()->json()` diretamente.

```php
use App\Responses\BaseResponse;

// index — paginado
return BaseResponse::paginated($this->service->getDados($request));

// show — individual
$item = $this->service->show($id);
return $item ? BaseResponse::ok($item) : BaseResponse::notFound('Produto não encontrado');

// store
return BaseResponse::created($this->service->create($request->validated()));

// update
return BaseResponse::ok($this->service->update($request->validated(), $id));

// destroy
return $this->service->destroy($id) ? BaseResponse::noContent() : BaseResponse::notFound();

// erro customizado
return BaseResponse::error('Mensagem', ['campo' => 'detalhe'], 422);
```

**Envelope de resposta:**
```json
{
  "success": true,
  "message": "OK",
  "data": { ... },
  "meta": { "current_page": 1, "total": 50, ... }
}
```

### getDados($request) — busca inteligente

O método `getDados(Request $request)` do `BaseService` orquestra automaticamente a busca com base nos parâmetros da request:

| Parâmetro | Comportamento |
|---|---|
| `search` | OR entre todos os `$fillable` |
| `searchLike` | Filtro incremental com operadores `>`, `>=`, `<=`, `<`, `whereIn` |
| nenhum deles | AND exato (`findAllFieldsAnd`) |
| `limit`, `page` | Paginação automática |
| `order`, `direction` | Ordenação |
| `fields` | Selecionar apenas colunas específicas |
| `relations` | Eager load (separados por vírgula) |

```php
// No controller, só isso:
public function index(Request $request): JsonResponse
{
    return BaseResponse::paginated($this->service->getDados($request));
}
```

### Namespaces e naming conventions

| Artefato | Caminho | Classe |
|---|---|---|
| Controller | `Http/Controllers/API/{Folder}/` | `{Entity}Controller` |
| Request criar | `Http/Requests/API/{Folder}/` | `Create{Entity}ApiRequest` |
| Request atualizar | `Http/Requests/API/{Folder}/` | `Update{Entity}ApiRequest` |
| Rotas | `routes/api/{folder}/` | prefixo `v1` |

### Anti-patterns proibidos

```php
// ❌ NUNCA — query no controller
public function index() {
    return Product::where('active', true)->get();
}

// ❌ NUNCA — response()->json() avulso
return response()->json(['data' => $data]);

// ❌ NUNCA — lógica de negócio no controller
public function store(Request $request) {
    if (Product::where('sku', $request->sku)->exists()) { ... }
}

// ✅ CERTO
public function index(Request $request): JsonResponse
{
    return BaseResponse::paginated($this->service->getDados($request));
}
```

---

## Performance & High Demand Architecture

> This project is designed for **high-performance, high-concurrency** workloads.
> Every code decision must consider scalability. Treat performance as a first-class requirement, not an afterthought.

### Cardinal Rules

| Rule | Detail |
|---|---|
| **Never nest foreach inside foreach** | Use `keyBy()`, `groupBy()` or a single keyed array lookup instead |
| **Never query inside a loop** | All IDs must be collected first, then fetched in one `whereIn()` — N+1 is a bug |
| **Eager-load always** | `with(['relation'])` on every query that accesses a relation |
| **Cache hot data** | Any data read more than once per request or unchanged for minutes belongs in cache |
| **Queue heavy work** | Email, PDF, export, external API calls, image processing → always a Job |
| **Index every FK and filter column** | No query without an index on filtered / joined columns |
| **Chunk large datasets** | Never `->get()` on unbounded result sets — use `->chunk()` or cursor |

---

### Cache — Mandatory Patterns

#### Tag-based cache (Redis)

```php
// ✅ Always use tags for grouped invalidation
Cache::tags(['products', 'catalog'])->remember(
    "product:{$id}",
    now()->addMinutes(30),
    fn () => $this->repo->findOrFail($id)
);

// Invalidate on write
public function update(int $id, array $data): Product
{
    $result = $this->repo->update($id, $data);
    Cache::tags(['products', 'catalog'])->flush();
    return $result;
}
```

#### Cache keys — naming convention

```php
// pattern: {entity}:{id|variant}:{context}
"product:{$id}"                  // single record
"products:active"                // list
"products:category:{$catId}"     // filtered list
"user:{$userId}:cart"            // user-scoped
```

#### What to cache (and for how long)

| Data | TTL | Tags |
|---|---|---|
| Reference/lookup tables (species, breeds, categories) | 24h | `['reference']` |
| Product catalog listing | 30 min | `['products', 'catalog']` |
| Individual product | 30 min | `['products', "product:{$id}"]` |
| User-specific data (cart, preferences) | session | `['user:{$id}']` |
| Dashboard aggregates | 5 min | `['reports']` |
| Auth / permissions | until logout | `['permissions', "user:{$id}"]` |

#### Never cache

```php
// ❌ Never cache mutable financial / stock data without explicit invalidation
// ❌ Never cache full paginated results (cache the data, not the paginator)
// ❌ Never hardcode TTL in Controller or Livewire — always in Service or Repository
```

---

### Jobs & Queues — Mandatory Patterns

#### What must be a Job

```
✅ Sending emails / SMS / WhatsApp notifications
✅ Generating PDF / Excel exports
✅ Syncing stock with external ERP/API
✅ Resizing / processing uploaded images
✅ Webhook dispatch
✅ Heavy aggregation / report generation
✅ Invalidating distributed cache across nodes
✅ Any operation > 200ms
```

#### Job structure

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Contracts\Services\OrderServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(private readonly int $orderId) {}

    public function handle(OrderServiceContract $service): void
    {
        $service->processOrder($this->orderId);
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("ProcessOrderJob failed for order {$this->orderId}: {$e->getMessage()}");
    }
}

// Dispatch from Service — never from Controller or Livewire directly
ProcessOrderJob::dispatch($order->id)->onQueue('orders');
```

#### Named queues — priority tiers

```
high    → auth, payments, critical notifications
default → order processing, stock movements
low     → reports, PDF exports, bulk emails, image processing
```

#### Required in production

```bash
# Laravel Horizon (Redis-backed queue dashboard)
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

---

### Database Indexes — Mandatory Rules

#### Every migration must include indexes on

```
✅ All _id foreign key columns (auto-handled by foreignId())
✅ All columns used in WHERE clauses (status, is_active, type)
✅ All columns used in ORDER BY frequently  
✅ Composite indexes for multi-column filters
✅ Unique indexes for natural keys (sku, slug, cpf, email, code)
```

#### Migration patterns

```php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('sku', 50)->unique();
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->integer('stock')->default(0);
        $table->boolean('is_active')->default(true)->index();
        $table->boolean('is_featured')->default(false);
        $table->foreignId('category_id')->constrained()->cascadeOnDelete();
        $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
        $table->softDeletes();
        $table->timestamps();

        // Composite indexes for common query patterns
        $table->index(['is_active', 'is_featured']);          // catalog listing
        $table->index(['category_id', 'is_active', 'price']); // filtered catalog
        $table->index(['deleted_at', 'is_active']);           // soft-delete fast filter
    });
}
```

#### Forced index in Repository (when query plan regression is detected)

```php
// Available via BaseRepository::useIndex()
public function getActiveFeatured(): Collection
{
    return $this->useIndex('products_is_active_is_featured_index')
        ->where('is_active', true)
        ->where('is_featured', true)
        ->with(['category', 'brand'])
        ->get();
}
```

---

### N+1 — Forbidden Query Patterns

```php
// ❌ CRITICAL BUG — N+1: 1 query for products + N queries for categories
$products = Product::all();
foreach ($products as $product) {
    echo $product->category->name; // query per iteration
}

// ✅ Correct — 2 queries total
$products = Product::with('category')->get();

// ❌ CRITICAL BUG — nested foreach O(n²)
foreach ($orders as $order) {
    foreach ($items as $item) {
        if ($item->order_id === $order->id) { ... } // O(n²)
    }
}

// ✅ Correct — O(n) using keyBy
$itemsByOrder = $items->groupBy('order_id'); // one pass
foreach ($orders as $order) {
    $orderItems = $itemsByOrder->get($order->id, collect());
}

// ❌ CRITICAL BUG — query inside loop
foreach ($productIds as $id) {
    $stock = Stock::where('product_id', $id)->sum('qty'); // N queries
}

// ✅ Correct — one query with groupBy
$stocks = Stock::whereIn('product_id', $productIds)
    ->selectRaw('product_id, SUM(qty) as total')
    ->groupBy('product_id')
    ->pluck('total', 'product_id');

foreach ($productIds as $id) {
    $stock = $stocks[$id] ?? 0;
}
```

---

### Large Datasets — Chunking & Cursor

```php
// ❌ Never — loads everything into memory
$all = Order::where('status', 'pending')->get();
foreach ($all as $order) { ... }

// ✅ chunk() — processes in batches (memory safe)
Order::where('status', 'pending')
    ->chunk(200, function (Collection $batch) {
        foreach ($batch as $order) {
            ProcessOrderJob::dispatch($order->id)->onQueue('orders');
        }
    });

// ✅ lazy() — generator-based cursor for read-only operations
Order::where('status', 'pending')->lazy()->each(function (Order $order) {
    // one record in memory at a time
    ReportJob::dispatch($order->id);
});

// ✅ chunkById() — safer than chunk() for delete/update within the loop
Order::where('status', 'cancelled')->chunkById(500, fn ($batch) => ...);
```

---

### Livewire Performance Rules

```php
// ❌ Computing expensive data on every Livewire render
public function render()
{
    return view('livewire.dashboard', [
        'stats' => $this->service->getFullStats(),    // heavy query on every interaction
        'chart' => $this->service->buildChartData(),  // heavy query on every interaction
    ]);
}

// ✅ Use #[Computed] with cache TTL
use Livewire\Attributes\Computed;

#[Computed(seconds: 300)]
public function stats(): array
{
    return $this->service->getFullStats();
}

#[Computed(seconds: 300)]
public function chartData(): array
{
    return $this->service->buildChartData();
}

// ✅ Lazy-load expensive sections
#[Lazy]
public function render() { ... }

// ✅ wire:model.blur on all text inputs — avoids a server round-trip per keystroke
<x-forge-input wire:model.blur="search" />
```

---
### Recommended Tools (Optional)

| Tool | Purpose | When to use |
|---|---|---|
| **Redis** | Primary cache + queue driver | Recommended for staging/production |
| **Laravel Horizon** | Queue dashboard + monitoring | Recommended for projects with jobs |
| **Laravel Telescope** (dev only) | Query/job/cache/request inspector | Development only (`--dev`) |
| **Laravel Octane** | App server (Swoole/FrankenPHP) | High-concurrency APIs (optional) |
| **Laravel Scout** | Full-text search (Meilisearch/Algolia) | Search on large text catalogs (optional) |
| **Clockwork** | Timeline profiler (browser devtools) | Browser-side profiling in dev (optional) |

```bash
# Essential installations
composer require laravel/horizon
composer require laravel/scout
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
composer require --dev laravel/telescope
composer require --dev itsgoingd/clockwork
```

---

### Performance Anti-Patterns (FORBIDDEN — agent must reject these patterns)

> **Critical:** These patterns cause production outages in high-traffic scenarios.
> Agents must **refuse** to generate code containing any of these patterns and **fix** them immediately when detected in existing code.

#### 1. N+1 Query Problem

```php
// ❌ CRITICAL BUG — 1 query for orders + N queries for clients
$orders = Order::all(); // 1 query
foreach ($orders as $order) {
    echo $order->client->name; // N queries (one per iteration)
}

// ✅ FIX: eager load — 2 queries total
$orders = Order::with('client')->get();
foreach ($orders as $order) {
    echo $order->client->name; // no extra query
}
```

#### 2. Nested foreach on Collections

```php
// ❌ CRITICAL BUG — O(n²) complexity
foreach ($orders as $order) {
    foreach ($items as $item) {
        if ($item->order_id === $order->id) { // n × m iterations
            $order->items[] = $item;
        }
    }
}

// ✅ FIX: groupBy() — O(n)
$itemsByOrder = $items->groupBy('order_id');
foreach ($orders as $order) {
    $order->items = $itemsByOrder->get($order->id, collect());
}
```

#### 3. Query Inside Loop

```php
// ❌ CRITICAL BUG — N queries
foreach ($productIds as $id) {
    $stock = Stock::where('product_id', $id)->sum('qty'); // query per iteration
}

// ✅ FIX: collect IDs, single whereIn()
$stocks = Stock::whereIn('product_id', $productIds)
    ->selectRaw('product_id, SUM(qty) as total')
    ->groupBy('product_id')
    ->pluck('total', 'product_id');

foreach ($productIds as $id) {
    $stock = $stocks[$id] ?? 0;
}
```

#### 4. Unbounded ->get() Without Pagination

```php
// ❌ CRITICAL BUG — loads 100k rows into memory
$all = Order::where('status', 'pending')->get();
foreach ($all as $order) { ... }

// ✅ FIX: chunk() for batch processing
Order::where('status', 'pending')->chunk(200, function ($batch) {
    foreach ($batch as $order) {
        ProcessOrderJob::dispatch($order->id);
    }
});

// ✅ FIX: lazy() for read-only iteration
Order::where('status', 'pending')->lazy()->each(function ($order) {
    // one record in memory at a time
});
```

#### 5. Missing Eager Load

```php
// ❌ CRITICAL BUG — relations accessed without with()
$products = Product::all(); // no ->with()
foreach ($products as $p) {
    echo $p->category->name;  // N queries
    echo $p->brand->name;     // N queries
}

// ✅ FIX: eager load all accessed relations
$products = Product::with(['category', 'brand'])->get();
```

#### 6. Synchronous External Calls in Web Request

```php
// ❌ CRITICAL BUG — blocks request for 2+ seconds
public function store(Request $request)
{
    $order = Order::create($request->all());
    Mail::send(new OrderConfirmation($order));        // blocks 1s
    Http::post('https://erp.com/api/sync', $order);   // blocks 1s
    return response()->json($order);
}

// ✅ FIX: queue everything async
public function store(Request $request)
{
    $order = Order::create($request->all());
    Mail::queue(new OrderConfirmation($order));       // instant
    SyncOrderJob::dispatch($order->id);               // instant
    return response()->json($order);
}
```

#### 7. Heavy Livewire Computation on Every Render

```php
// ❌ CRITICAL BUG — runs query on every keystroke
public function render()
{
    return view('livewire.dashboard', [
        'stats' => Order::selectRaw('COUNT(*), SUM(total)')->get(), // every render
    ]);
}

// ✅ FIX: #[Computed] with cache
use Livewire\Attributes\Computed;

#[Computed(seconds: 300)]
public function stats()
{
    return Order::selectRaw('COUNT(*), SUM(total)')->get();
}
```

#### 8. Cache Inside Loop

```php
// ❌ CRITICAL BUG — N cache reads
foreach ($productIds as $id) {
    $product = Cache::get("product:{$id}"); // cache hit/miss per iteration
}

// ✅ FIX: fetch all cache keys at once (Redis mget)
$keys = array_map(fn($id) => "product:{$id}", $productIds);
$cached = Cache::many($keys);

foreach ($productIds as $id) {
    $product = $cached["product:{$id}"] ?? null;
    if (!$product) {
        $product = Product::find($id);
        Cache::put("product:{$id}", $product, 1800);
    }
}
```

#### 9. SELECT * With Unneeded Columns

```php
// ❌ BAD — transfers 10 columns when only 2 are needed
$products = Product::all();
foreach ($products as $p) {
    echo $p->id . ' - ' . $p->name;
}

// ✅ FIX: select only needed columns
$products = Product::select(['id', 'name'])->get();
```

#### 10. Missing Database Index

```php
// ❌ CRITICAL BUG — migration without index on filtered column
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('status');  // ❌ no index
    $table->timestamps();
});

// Repository:
Order::where('status', 'pending')->get(); // full table scan

// ✅ FIX: add index
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('status')->index();  // ✅ indexed
    $table->timestamps();
});
```

#### 11. Individual Job Dispatch in Loop

```php
// ❌ BAD — dispatches 1000 jobs sequentially
foreach ($orderIds as $id) {
    ProcessOrderJob::dispatch($id); // 1000 Redis writes
}

// ✅ FIX: batch dispatch
Bus::batch(
    collect($orderIds)->map(fn($id) => new ProcessOrderJob($id))
)->dispatch();
```

#### 12. Direct Delete Without Eloquent Events (HasAuditFields)

```php
// ❌ CRITICAL BUG — bypasses deleted_by audit
Product::whereIn('id', $ids)->delete(); // no deleted event fired

// ✅ FIX: delete individually to fire events
Product::whereIn('id', $ids)->each(fn($p) => $p->delete());
```

---

### Performance Anti-Pattern Checklist (agent must enforce before generating code)

```
[ ] Does any Repository method have a query inside a loop?
    → FIX: Collect IDs first, then ->whereIn() in one query

[ ] Does any method call ->get() on an unbounded result set?
    → FIX: Use ->paginate() or ->chunk() or ->lazy()

[ ] Are all relations eager-loaded with ->with([]) before accessing them?
    → FIX: Add ->with(['relation']) to the query

[ ] Is any heavy operation (email, PDF, API call) synchronous?
    → FIX: Dispatch a Job with ->onQueue()

[ ] Is frequently read data computed fresh on every request?
    → FIX: Use Cache::tags()->remember() with appropriate TTL

[ ] Are new filter columns missing an index in the migration?
    → FIX: Add $table->index() or ->index() after column definition

[ ] Does any Livewire render() run expensive queries on every interaction?
    → FIX: Use #[Computed(seconds: X)]

[ ] Are there nested foreach loops on Eloquent collections?
    → FIX: Use ->keyBy() or ->groupBy()

[ ] Is any query selecting all columns when only a few are needed?
    → FIX: Use ->select(['id', 'name', ...])

[ ] Does any bulk delete skip Eloquent events (HasAuditFields)?
    → FIX: Use ->each(fn($r) => $r->delete())
```

---

## Commit Convention

> ⚠️ **ALWAYS run Pint before any commit.** Never commit unformatted PHP code.

```bash
# REQUIRED before every git commit:
./vendor/bin/pint

# Then commit:
git add .
git commit -m "feat: ..."
```

```
feat:     nova funcionalidade
fix:      correção de bug
docs:     apenas documentação
refactor: sem feat/fix
test:     testes
chore:    manutenção (deps, config)
```

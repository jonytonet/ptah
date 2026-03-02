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
  --fields="name:string,sku:string,price:decimal,stock:integer,category_id:foreign,is_active:boolean" \
  --soft-delete

# Sub-folder (large projects)
php artisan ptah:forge Inventory/ProductStock \
  --fields="product_id:foreign,location:string,qty:integer"
# model key = 'Inventory/ProductStock'
# namespace = App\Models\Inventory\ProductStock
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

## Configuring BaseCrud

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

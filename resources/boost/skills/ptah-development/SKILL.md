---
name: ptah-development
description: Build and work with jonytonet/ptah features, including scaffolding new entities, configuring BaseCrud, activating modules, writing Livewire components, and running tests — all following Ptah's SOLID architecture.
---

# Ptah Development

## When to use this skill

Use this skill when:
- Generating new entities with `ptah:forge`
- Configuring BaseCrud columns, filters, or modal via `crud_configs`
- Activating optional modules (auth, menu, company, permissions)
- Writing or extending Livewire components that follow Ptah conventions
- Writing tests for Ptah components or models

## Package Context

```
Package:   jonytonet/ptah
Laravel:   12.x | PHP 8.3 | Livewire 3 | Tailwind v4 | Alpine.js 3
Icons:     Boxicons 2.1.4 + FontAwesome 6.7.2 (CDN, no inline SVG)
Dark mode: .ptah-dark class on root element — CSS in forge-dashboard-layout only
Tests:     Orchestra Testbench + PHPUnit 11 + SQLite :memory:
```

---

## Scaffolding a New Entity

Always use `ptah:forge` — never create files manually:

```bash
php artisan ptah:forge Product \
  --fields="name:string,sku:string,price:decimal,stock:integer,category_id:foreign,is_active:boolean" \
  --soft-delete
```

For sub-folder organization (large projects):

```bash
php artisan ptah:forge Inventory/ProductStock --fields="..."
# Registers as 'Inventory/ProductStock' in crud_configs.model
# Resolves to App\Models\Inventory\ProductStock namespace
```

Generated files:
- `app/Models/Product.php` — with `$fillable`, `$casts`, auto-slug on `creating`
- `app/DTO/Product/ProductDTO.php` — readonly properties, `fromArray()`
- `app/Contracts/Repositories/ProductRepositoryContract.php`
- `app/Repositories/ProductRepository.php`
- `app/Contracts/Services/ProductServiceContract.php`
- `app/Services/ProductService.php`
- `app/Http/Requests/Product/{Store,Update}ProductRequest.php`
- `app/Http/Resources/Product/ProductResource.php`
- `database/migrations/..._create_products_table.php`
- `resources/views/product/index.blade.php`

---

## Configuring BaseCrud

BaseCrud reads from `crud_configs` table. Minimum required record:

```json
{
  "model": "Product",
  "cols": [
    { "field": "name",  "label": "Nome",   "type": "text",    "sort": true, "search": true },
    { "field": "price", "label": "Preço",  "type": "money",   "sort": true },
    { "field": "is_active", "label": "Ativo", "type": "boolean" }
  ]
}
```

Column types: `text`, `badge`, `boolean`, `money`, `date`, `datetime`, `image`, `method`

Badge with conditional colors:
```json
{
  "field": "status",
  "type": "badge",
  "badgeMap": {
    "active":   { "label": "Ativo",    "color": "success" },
    "inactive": { "label": "Inativo",  "color": "danger"  }
  }
}
```

Row styles based on field values:
```json
{
  "rowStyles": [
    { "field": "stock", "op": "<", "value": 5, "class": "bg-red-50 dark:bg-red-900/20" }
  ]
}
```

---

## Livewire Component Conventions

**Input binding:** Always use `wire:model.blur` for text/email/phone in modals:
```blade
<x-forge-input wire:model.blur="email" name="email" label="E-mail" />
```

**Uniqueness validation** (required pattern for label-like fields):
```php
use Illuminate\Validation\Rule;

protected function rules(): array
{
    return [
        'label' => [
            'nullable', 'string', 'max:4',
            Rule::unique('ptah_companies', 'label')->ignore($this->editingId),
        ],
    ];
}
```

**CSS rules:**
- Never add `<style>` blocks inside view files
- All CSS overrides go in `forge-dashboard-layout.blade.php` under the existing `<style>` block
- Use `.ptah-dark .your-class { }` pattern for dark mode variants

**Icons — always CSS classes:**
```blade
{{-- Correct ✅ --}}
<i class="bx bx-trash text-danger"></i>
<i class="fas fa-edit"></i>

{{-- Wrong ❌ --}}
<svg>...</svg>
```

---

## Optional Modules

```bash
php artisan ptah:module auth         # Login, 2FA, sessions, profile
php artisan ptah:module menu         # Dynamic sidebar (config or database driver)
php artisan ptah:module company      # Multi-company management
php artisan ptah:module permissions  # RBAC: roles, objects, CRUD permissions, audit
```

Check module status:
```bash
php artisan ptah:module --list
```

`permissions` depends on `company`. All other modules are independent.

With demo data:
```bash
php artisan ptah:install --demo
# Creates: 2 companies (BETA, CORP), departments (TI, Comercial, Financeiro),
# roles (Editor, Viewer), menu items
```

---

## Writing Tests

```php
namespace Ptah\Tests\Feature\Livewire;

use Livewire\Livewire;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Tests\Factories\CompanyFactory;  // Use this, not Company::factory()
use Ptah\Tests\TestCase;

class CompanyListTest extends TestCase
{
    /** @test */
    public function can_create_company(): void
    {
        Livewire::test(CompanyList::class)
            ->call('create')
            ->set('name', 'Acme Ltda')
            ->set('label', 'ACME')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ptah_companies', ['name' => 'Acme Ltda']);
    }

    /** @test */
    public function label_must_be_unique(): void
    {
        CompanyFactory::new()->create(['label' => 'DUPL']);

        Livewire::test(CompanyList::class)
            ->call('create')
            ->set('name', 'Another')
            ->set('label', 'DUPL')
            ->call('save')
            ->assertHasErrors(['label']);
    }
}
```

Key points:
- Always extend `Ptah\Tests\TestCase` (Testbench + RefreshDatabase + SQLite :memory:)
- Use `CompanyFactory::new()->make/create()` — there is **no** Eloquent Factory
- `PtahServiceProvider` is loaded automatically — all binds (CompanyService, etc.) are available
- No manual `$this->app->bind(...)` needed

---

## Commit Message Convention

```
feat:     new feature
fix:      bug fix
docs:     documentation only
refactor: code change, no feature/fix
test:     adding or fixing tests
```

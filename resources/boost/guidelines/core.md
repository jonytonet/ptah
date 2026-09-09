## Ptah — Laravel Scaffolding, SOLID Architecture & Component Library

`jonytonet/ptah` is a Laravel package (Laravel 12, PHP 8.3, Livewire 4, Tailwind v4, Alpine.js 3) that enforces a **strict SOLID layered architecture** through scaffolding automation and runtime components.

---

## SOLID — Layer Responsibilities (CRITICAL)

Every entity in a Ptah project is split into exactly these layers. **Never put logic in the wrong layer.**

| Layer | File | Single Responsibility |
|---|---|---|
| **Model** | `app/Models/{Entity}.php` | Schema, casts, scopes, relationships, boot hooks. **No business logic.** |
| **DTO** | `app/DTO/{Entity}/{Entity}DTO.php` | Immutable data transfer object. `readonly` properties. `fromArray()` factory. **No persistence.** |
| **Repository** | `app/Repositories/{Entity}Repository.php` | **All database access.** Eloquent queries, filters, pagination. **No business rules.** |
| **Service** | `app/Services/{Entity}Service.php` | **All business logic.** Orchestrates repository calls. Throws domain exceptions. **No HTTP/Livewire awareness.** |
| **FormRequest** | `app/Http/Requests/{Entity}/` | HTTP validation only. Rules depend on route context (store vs update). |
| **Controller / Livewire** | `app/Http/` or `app/Livewire/` | HTTP / UI layer only. Calls Service, returns response/view. **No queries, no business logic.** |

### Dependency Inversion (D in SOLID) — always inject Contracts

```php
// ✅ Correct — inject by Contract (interface)
public function __construct(
    private readonly ProductServiceContract $products,
) {}

// ❌ Wrong — inject by concrete class
public function __construct(
    private readonly ProductService $products,
) {}
```

Contracts are always in `app/Contracts/{Repositories,Services}/`. The service provider binds concrete → contract automatically.

### Open/Closed — extend, never modify generated bases

```php
// ✅ Correct: extend to add behaviour
class ProductService extends \App\Services\BaseService implements ProductServiceContract
{
    public function deactivate(int $id): void
    {
        $product = $this->products->findOrFail($id);
        $product->update(['is_active' => false]);
    }
}

// ❌ Wrong: add unrelated responsibilities to existing service
```

---

## Architecture Overview

Three subsystems — always use the right one:

| Subsystem | Tag / Command | Purpose |
|---|---|---|
| **Ptah Forge** | `<x-forge-*>` | Blade UI components (Tailwind v4 + Alpine.js) |
| **ptah:forge** | `php artisan ptah:forge {Entity}` | SOLID scaffolding — generates all layers at once |
| **BaseCrud** | `@livewire('ptah-base-crud', ['model'=>'...'])` | Dynamic Livewire table+modal, configured via `crud_configs` table |

---

## Design Tokens (use these values — never hardcode colors)

| Token | Value | Usage |
|---|---|---|
| `primary` | `#5b21b6` | Main actions, focus rings, active states |
| `success` | `#10b981` | Confirmations, active badges, positive status |
| `danger` | `#ef4444` | Errors, destructive actions, alert states |
| `warn` | `#f59e0b` | Warnings, pending states |
| `dark` | `#1e293b` | Text, dark backgrounds |
| `light` | `#f8fafc` | Backgrounds, cards in light mode |

In Tailwind classes, reference these as `text-primary`, `bg-success`, `border-danger`, etc.  
In `forge-*` components, use `color="primary"`, `color="danger"`, `type="success"` props.

---

## Scaffolding — Always use ptah:forge

<code-snippet name="Generate entity scaffolding" lang="bash">
php artisan ptah:forge Product \
  --fields="name:string,price:decimal,is_active:boolean,category_id:unsignedBigInteger" \
  --soft-delete
</code-snippet>

Generates ALL layers at once. Never create these files manually:

```
app/
├── DTO/Product/ProductDTO.php                          ← immutable, readonly
├── Contracts/Repositories/ProductRepositoryContract.php
├── Contracts/Services/ProductServiceContract.php
├── Repositories/ProductRepository.php                  ← only DB access
├── Services/ProductService.php                         ← only business logic
└── Models/Product.php                                  ← only schema/scopes
database/migrations/..._create_products_table.php
resources/views/product/index.blade.php
```

---

## Visual Conventions (non-negotiable)

**Icons — CSS classes only, never SVG inline:**
```blade
{{-- ✅ Correct --}}
<i class="bx bx-home-alt"></i>
<i class="fas fa-chart-bar"></i>

{{-- ❌ Wrong --}}
<svg>...</svg>
```
Libraries auto-loaded by `forge-dashboard-layout`: Boxicons 2.1.4 + FontAwesome 6.7.2 (CDN).

**Dark mode and theming — never write a colour, read a token:**

Where new CSS goes depends on which side you are on, and this guideline is for
the HOST side:

| You are… | New CSS goes | How it follows the theme |
|---|---|---|
| Writing a screen in **your app** | `resources/css/app.css`, or the element itself | `var(--ptah-surface)`, `var(--ptah-text-strong)`, … |
| Contributing to **the ptah package** | `resources/css/ptah-components.css`, as a `.ptah-c-*` class | same tokens |

```blade
{{-- ✅ Correct — follows every appearance axis the user picks --}}
<div style="background: var(--ptah-surface); color: var(--ptah-text-strong)">

{{-- ❌ Wrong — stays this colour when the user switches tone --}}
<div style="background: #1e293b">
```

- **Never** add `<style>` blocks inside view components.
- **Never** write CSS into `forge-dashboard-layout.blade.php`. It carries a
  legacy inline block that is being **dismantled, not extended**:
  `LayoutStyleBaselineTest` fails the build if it gains a single rule or colour
  literal, and `HardcodedPaletteCeilingTest` is a per-file ratchet that only
  goes down. In a consumer app that file lives inside `vendor/` anyway — editing
  it means publishing the view, and then you own it and stop receiving updates.
- A component does **not** branch on `.ptah-dark`. The token is redefined by the
  active preset; that is what the token is for. Reach for `.ptah-dark .my-thing`
  only for something a token genuinely cannot express, and then define BOTH
  scopes.
- The six per-user axes (light/dark tone, accent, text weight, density, font
  size) are `data-ptah-*` attributes on `<html>`, resolved by
  `Ptah\Support\AppearancePresets`. Full recipe: `docs/CustomScreens.md`.

**Forge component color convention:**
```blade
<x-forge-button color="primary">Save</x-forge-button>
<x-forge-button color="danger" flat>Delete</x-forge-button>
<x-forge-alert type="success">Saved!</x-forge-alert>
<x-forge-badge color="warn">Pending</x-forge-badge>
```

---

## Livewire Rules

- `wire:model.blur` → text, email, phone, tax fields (validate on blur, not on each keystroke)
- `wire:model.live` → only for checkboxes, selects, switches that trigger immediate UI feedback
- Uniqueness: always `Rule::unique('table', 'col')->ignore($this->editingId)`
- Service calls from Livewire: inject by Contract, never instantiate directly

---

## Optional Modules

<code-snippet name="Activate optional modules" lang="bash">
php artisan ptah:module auth         # Login, 2FA TOTP/email, sessions, profile
php artisan ptah:module menu         # Dynamic sidebar (driver: config or database)
php artisan ptah:module company      # Multi-company + department management
php artisan ptah:module permissions  # RBAC: roles, page objects, CRUD + audit
</code-snippet>

Module flags: `.env` → `PTAH_MODULE_*=true`, read via the `ptah.modules` config key (e.g. `ptah.modules.company`, `ptah.modules.auth`).  
`permissions` requires `company`. All others are independent.  
Never enable modules by editing PHP — always use `ptah:module`.

---

## Anti-Patterns — What AI Must Never Generate

| ❌ Anti-pattern | ✅ Correct alternative |
|---|---|
| Eloquent query inside a Service | Move to Repository method |
| Business logic inside Livewire/Controller | Move to Service layer |
| `new ProductService()` or `new ProductRepository()` | Inject `ProductServiceContract` via constructor |
| `<style>` block inside a view | `resources/css/app.css` (host) or `ptah-components.css` (package) — never the layout |
| Inline SVG as icon | `<i class="bx bx-...">` or `<i class="fas fa-...">` |
| `wire:model.live` on text input | `wire:model.blur` |
| Hardcoded colors like `#5b21b6` in Blade/CSS | Design token classes (`text-primary`, `bg-success`) or `var(--ptah-*)` |
| `php artisan ptah:module company` skipped, manually set in config | Always run the Artisan command |
| Creating Model/Service/Repository files manually | Always run `ptah:forge` |

---

## Testing

Extends `Ptah\Tests\TestCase` (Testbench + RefreshDatabase + SQLite `:memory:`).  
**No Eloquent Factory** — use `{Entity}Factory::new()->create([...])` from `tests/Factories/`.  
`PtahServiceProvider` auto-registers all binds. No manual `$this->app->bind()` needed in tests.

<code-snippet name="Feature test example" lang="php">
use Livewire\Livewire;
use Ptah\Tests\Factories\CompanyFactory;
use Ptah\Tests\TestCase;

class CompanyListTest extends TestCase
{
    public function test_label_must_be_unique(): void
    {
        CompanyFactory::new()->create(['label' => 'DUPL']);

        Livewire::test(\Ptah\Livewire\Company\CompanyList::class)
            ->call('create')
            ->set('name', 'Another')->set('label', 'DUPL')
            ->call('save')
            ->assertHasErrors(['label']);
    }
}
</code-snippet>

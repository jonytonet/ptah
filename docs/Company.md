# Company Module — Complete Documentation

**Pacote:** `jonytonet/ptah`  
**Namespace:** `Ptah\Services\Company`, `Ptah\Livewire\Company`  
**Livewire:** 4.x | **Laravel:** 11+

---

## Summary

1. [Overview](#overview)
2. [Activation](#activation)
3. [Configuration](#configuration)
4. [Database](#database)
   - [ptah_companies](#ptah_companies)
   - [ptah_departments](#ptah_departments)
5. [Models](#models)
   - [Company](#company)
   - [Department](#department)
6. [CompanyService](#companyservice)
7. [Componente Livewire — CompanyList](#componente-livewire--companylist)
8. [Componente Livewire — CompanySwitcher](#componente-livewire--companyswitcher)
9. [Route](#route)
10. [Seeder](#seeder)
    - [DefaultCompanySeeder](#defaultcompanyseeder)
    - [PtahDemoSeeder](#ptahdemoseeder)
11. [Multi-company vs Single-tenant](#multi-company-vs-single-tenant)
12. [Integration with Permissions](#integration-with-permissions)
13. [Customizing Views](#customizing-views)

---

## Overview

The **company** module provides complete company and department management for the system. It is the foundation for multi-company scenarios (holding, franchises, SaaS) and also works in single-company installations, where it serves only as an organizational context for departments.

| Feature | Description |
|---|---|
| Companies | CRUD screen for name, abbreviation, e-mail, phone, tax data and status. The table also has `logo_path`, `address` and `settings` columns, but the screen has no field for them |
| Departments | Hierarchical grouping to organize roles/access profiles |
| Session context | Active company saved in session, with fallback to default company |
| Cache | Default company and user companies cached automatically |
| Protection | Blocks deletion of the company marked as default |

**Principle:** independent module. Can be activated without the `permissions` module. The `permissions` module, however, requires `company` as a dependency.

---

## Activation

### Via command (recommended)

```bash
php artisan ptah:module company
```

O comando:
1. Publica as migrations `ptah_companies` e `ptah_departments`
2. Executa `php artisan migrate`
3. Creates the default company via `DefaultCompanySeeder`
4. Define `PTAH_MODULE_COMPANY=true` no `.env`
5. Displays next steps

### Via `.env` (manual)

```dotenv
PTAH_MODULE_COMPANY=true
```

### Via `config/ptah.php`

```php
'modules' => [
    'company' => env('PTAH_MODULE_COMPANY', false),
],
```

---

## Configuration

In `config/ptah.php`, section `company`:

```php
'company' => [
    // Filesystem disk used by Company::getLogoUrl() to build the logo URL
    'logo_disk' => env('PTAH_LOGO_DISK', 'public'),
],
```

`logo_disk` is the only key of the section (the old `logo_path` and
`address_fields` keys were never read and have been removed; the logo path
lives in the `logo_path` column of each company).

> **Model & table are fixed.** The package references `Ptah\Models\Company` and
> the `ptah_companies` table directly, so neither is configurable (the old
> `config('ptah.company.model')` / `.table` keys were never read and have been
> removed). To add methods/relationships, extend `Ptah\Models\Company` in your
> app and use your subclass in your own code.

---

## Database

### ptah_companies

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `name` | string | — | Legal name / Trade name |
| `slug` | string unique | — | Automatically generated from the name |
| `label` | string(4) | ✓ | Abbreviation up to 4 characters — displayed in the company switcher badge |
| `logo_path` | string(2048) | ✓ | Relative path on the configured disk |
| `email` | string | ✓ | E-mail de contato |
| `phone` | string | ✓ | Telefone |
| `tax_id` | string | ✓ | CNPJ, CPF, EIN, VAT, etc. |
| `tax_type` | string | ✓ | Tipo do documento (`cnpj`, `cpf`, `ein`, `vat`, `other`) |
| `address` | json | ✓ | Free-form address JSON (read with `getAddressField()`) |
| `settings` | json | ✓ | Arbitrary settings in JSON |
| `is_default` | boolean | — | System default company (only 1) |
| `is_active` | boolean | — | Visibility / availability |
| `created_by`, `updated_by`, `deleted_by` | bigint | ✓ | Audit fields (`HasAuditFields`) |
| `deleted_at` | timestamp | ✓ | SoftDelete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

### ptah_departments

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `name` | string | — | Department name |
| `description` | string | ✓ | Free description |
| `is_active` | boolean | — | Visibility |
| `created_by`, `updated_by`, `deleted_by` | bigint | ✓ | Audit fields (`HasAuditFields`) |
| `deleted_at` | timestamp | ✓ | SoftDelete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

> **Why don't departments have company_id?** Departments are global in the standard model — they group roles that can be global or per company. If your application needs departments per company, add the column via a custom migration.

---

## Models

### Company

**Namespace:** `Ptah\Models\Company`  
**Tabela:** `ptah_companies` (fixa)  
**Traits:** `SoftDeletes`

#### Automatic Cast

```php
protected $casts = [
    'address'    => 'array',
    'settings'   => 'array',
    'is_default' => 'boolean',
    'is_active'  => 'boolean',
];
```

#### Boot — auto-slug

The `Company` generates the `slug` on the `creating` event, from the `name`, using `Str::slug()`, only when no `slug` was given. There is no numeric suffix and no regeneration on `updating`: the column is `unique`, so a second company whose name slugs to the same value fails on insert.

#### Instance Methods

| Method | Return | Description |
|---|---|---|
| `getLogoUrl(): string` | string | Full logo URL via `Storage::disk(config('ptah.company.logo_disk'))->url()`. If no logo, returns the URL of an avatar generated by the `ui-avatars.com` service from the company name |
| `getAddressField(string $key, mixed $default = null)` | mixed | Safe access to a field in the `address` JSON |
| `getSetting(string $key, mixed $default = null)` | mixed | Safe access to a field in the `settings` JSON |

#### Scopes

```php
Company::active()->get();           // WHERE is_active = 1 AND deleted_at IS NULL
Company::default()->first();        // WHERE is_default = 1
Company::withTrashed()->find($id);  // includes soft-deleted (for idempotent seeders)
```

---

### Department

**Namespace:** `Ptah\Models\Department`  
**Tabela:** `ptah_departments`  
**Traits:** `SoftDeletes`

#### Relacionamentos

```php
$department->roles; // HasMany(Role) — roles belonging to the department
```

#### Scopes

```php
Department::active()->get();   // WHERE is_active = 1 AND deleted_at IS NULL
```

---

## CompanyService

**Namespace:** `Ptah\Services\Company\CompanyService`  
**Contract:** `Ptah\Contracts\CompanyServiceContract`  
**Binding:** `CompanyService` is a singleton in `PtahServiceProvider`; the contract is bound to it

### Interface

```php
interface CompanyServiceContract
{
    public function getDefault(bool $createIfMissing = false): ?Company;
    public function getById(int $id): ?Company;
    public function getUserCompanies(mixed $user): Collection;
    public function getCurrentCompanyId(): ?int;
    public function setCurrentCompany(int $companyId): void;
    public function clearCache(mixed $user = null): void;
}
```

### Methods

#### `getDefault(bool $createIfMissing = false): ?Company`

Returns the company with `is_default = true` **and** `is_active = true`. The result is cached for 1 hour under the key `ptah_company_default`.

If `$createIfMissing = true` and no default company exists, it creates one (a protected `createDefaultCompany()` on the service, named after `config('app.name')`).

```php
$company = app(CompanyServiceContract::class)->getDefault();
```

---

#### `getUserCompanies(mixed $user): Collection`

Returns the active companies in which the user holds an active role (`ptah_user_roles` with a non-null `company_id`).

- `$user` accepts an id, a `User` model or `null`; `null` resolves by `Auth::id()` or from session (key `config('ptah.permissions.user_session_key')`). With no user resolved, returns an empty collection
- Cached for 1 hour under the key `ptah_user_companies:{userId}`
- It does **not** special-case MASTER users or global roles (`company_id` null) — the company switcher uses `switchableCompanies()` for that

```php
$companies = app(CompanyServiceContract::class)->getUserCompanies();
// Collection of Company
```

---

#### `getCurrentCompanyId(): ?int`

Determines the active company in the current context:

1. Reads `Session::get(config('ptah.permissions.company_session_key'))`
2. If empty, uses `getDefault()`

```php
$companyId = app(CompanyServiceContract::class)->getCurrentCompanyId();
```

---

#### `setCurrentCompany(int $companyId): void`

Saves the ID in the session, without any check. Use in company selectors in the navbar. (The `CompanySwitcher` goes through `setActive()` instead, which only accepts a company the user may switch to.)

```php
app(CompanyServiceContract::class)->setCurrentCompany($companyId);
```

---

#### `clearCache(mixed $user = null): void`

Invalidates the default company cache. If `$user` is provided (id or model), also invalidates that user's companies cache.

---

### Dependency Injection

```php
// Via contrato (recomendado)
use Ptah\Contracts\CompanyServiceContract;

public function __construct(
    private readonly CompanyServiceContract $companies,
) {}

// Via facade (no global helpers for company — use the service directly)
$companies = app(CompanyServiceContract::class);
```

---

## Componente Livewire — CompanyList

**Namespace:** `Ptah\Livewire\Company\CompanyList`  
**View:** `ptah::livewire.company.company-list`  
**Layout:** `ptah::layouts.forge-dashboard`

### Properties

| Property | Type | Description |
|---|---|---|
| `search` | string | Texto de busca (nome, e-mail, tax_id) |
| `sort` | string | Sort column (default: `name`) |
| `direction` | string | `asc` ou `desc` |
| `showModal` | bool | Controle do modal criar/editar |
| `showDeleteModal` | bool | Delete confirmation modal control |
| `isEditing` | bool | Edit vs create mode |
| `editingId` | int\|null | ID being edited |
| `deleteId` | int\|null | ID targeted by the delete confirmation |
| `perPage` | int | Rows per page (default: `20`) |
| `name` | string | Form field |
| `label` | string | Form field — abbreviation up to 4 chars, displayed in the switcher badge |
| `email` | string | Form field |
| `phone` | string | Form field |
| `tax_id` | string | Form field |
| `tax_type` | string | Form field |
| `is_active` | bool | Form field |
| `is_default` | bool | Form field |
| `address`, `settings` | array | Loaded by `edit()`; `save()` does not write them |

### Methods

| Method | Description |
|---|---|
| `create()` | Opens modal in create mode |
| `edit(int $id)` | Loads data and opens modal in edit mode |
| `save()` | Validates and persists (create or update) |
| `confirmDelete(int $id)` | Opens confirmation modal with target ID |
| `delete()` | Executes soft-delete; blocks `is_default` company |
| `sort(string $column)` | Toggles table sorting |

### Computed Property

```php
$this->rows // Ptah\Models\Company paginated (`$perPage`, default 20), filtered and sorted
```

### Validation Rules

```php
'name'     => ['required', 'string', 'max:255'],
'label'    => [
    'nullable',
    'string',
    'max:4',
    Rule::unique('ptah_companies', 'label')->ignore($this->editingId),
],
'email'    => ['nullable', 'email', 'max:255'],
'phone'    => ['nullable', 'string', 'max:30'],
'tax_id'   => ['nullable', 'string', 'max:50'],
'tax_type' => ['nullable', 'in:cnpj,cpf,ein,vat,other'],
'is_active' => ['boolean'],
```

The component re-checks `ptah_can_manage_structure()` on every request (mount
and each action): with the `permissions` module on, only MASTER users pass;
with it off, the screen answers 403 unless `PTAH_STRUCTURE_EDITOR=true`.

> **Label uniqueness:** the `Rule::unique(...)->ignore($editingId)` rule ensures no two companies share the same abbreviation (e.g., two `BETA`), but allows saving an edit without changing the company's own label.

---

## Componente Livewire — CompanySwitcher

**Namespace:** `Ptah\Livewire\Company\CompanySwitcher`  
**View:** `ptah::livewire.company.company-switcher`  
**Usage:** automatically embedded in `forge-navbar`

Displays a horizontal bar in the navbar with the active company and other available companies.

### Behavior

| Situation | What appears |
|---|---|
| Fewer than 2 companies the user may switch to | Component renders nothing |
| 2 or more | Full name of the active one + labels of all (as tabs) |

### Visual Layout

```
[ Laravel ]  |  [ LAR ]  [ SLP ]
  ↑                  ↑       ↑
Active name   Label of each company (clickable button)
```

- The active company tab has a primary color background (`--ptah-primary`)
- Clicking another label switches the active company and reloads the current page
- In dark mode, colors adapt via `.ptah-dark` on the ancestor element

### On a phone: one menu instead of two controls

The horizontal bar competed with the bell, the avatar and the settings gear for
the same ~60px on a phone, and the companies came out overlapping and
unreadable. Below the `md` breakpoint the inline bar is therefore hidden and the
switcher reappears as a vertical section at the top of the navbar's **admin
menu** — so the right-hand side is one menu rather than two controls colliding.

That is the `$layout` property:

| Value | Renders |
|---|---|
| `inline` *(default)* | The horizontal bar above — active company name, separator, one tab per company. |
| `stacked` | A vertical list of menu items, for use inside a dropdown panel. |

```blade
{{-- What forge-navbar does --}}
@livewire('ptah-company-switcher', ['layout' => 'stacked'], key('ptah-company-switcher-mobile'))
```

Three details worth knowing if you embed the switcher yourself:

- **It is a second component instance, not copied markup.** Switching company
  stays owned by `CompanySwitcher`, so there is no duplicated logic to fall out
  of sync. A distinct `key` is required — two instances of the same component on
  one page collide in Livewire's morph without one.
- **The active row is marked by an icon, not only by colour** (`aria-current`
  plus a check glyph). The dark navbar panel does not give two text colours
  enough separation to rely on, and WCAG 1.4.1 forbids colour as the sole
  carrier of information regardless.
- **If no admin menu exists** — every module that generates it is off — the
  inline bar stays visible on mobile instead. Cramped beats absent: hiding it
  there would leave a phone user with no way to change company at all.

### `getLabelDisplay()`

Method of the `Company` model. Returns in priority order:

1. `$company->label` (if filled), uppercased
2. The initials of the name's words, up to 4 (e.g., `Acme Corp` → `AC`; a single word such as `Laravel` → `L`)

Used by CompanySwitcher and the company table badge.

### Livewire Properties

| Property | Type | Description |
|---|---|---|
| `activeId` | int | ID of the active company in session (`0` if none) |
| `companies` | Collection | Companies the user may switch to (`switchableCompanies()`) |
| `layout` | string | `inline` (default) or `stacked` (locked) |
| `pageUrl` | string | URL captured in `mount()` for redirect after switching |

### Company Switch

```php
// Internally calls:
$this->companyService->setActive($id);
$this->redirect($this->pageUrl);
```

`setActive()` ignores (silently) an id the user may not switch to: with the
`permissions` module off, any active company; with it on, every company for a
MASTER or a holder of a global role, otherwise only the companies of the user's
own active roles. `initSession()` (called in `mount()`) picks the default
company, or the first one available, when the session holds none or one the
user cannot use.

> **Why capture the URL in `mount()`?** Inside a Livewire AJAX request (`/livewire/update`), `request()->fullUrl()` returns the internal endpoint URL — not the page URL. That's why the URL is captured in `mount()` via `url()->current()`, when the component is still in the normal web request context.

---

## Route

Registered automatically when `ptah.modules.company = true`:

| Method | URI | Name | Protection |
|---|---|---|---|
| `GET` | `/ptah-companies` | `ptah.company.index` | `web`, `auth` |

The route file is not publishable and the URL has no config key. To serve the
screen elsewhere, define your own route in the application's `routes/web.php`
(the module route stays registered as long as the module is on):

```php
Route::get('/admin/empresas', \Ptah\Livewire\Company\CompanyList::class)
    ->middleware(['web', 'auth'])
    ->name('admin.companies');
```

---

## Seeder

### DefaultCompanySeeder

**Namespace:** `Ptah\Seeders\DefaultCompanySeeder`

Creates the default company in an **idempotent** way (safe to run multiple times):

```php
// Manual execution
php artisan db:seed --class="Ptah\Seeders\DefaultCompanySeeder"
```

**Logic:**

```php
// Search including soft-deleted to avoid duplicates
$company = Company::withTrashed()->where('is_default', true)->first();

if (!$company) {
    Company::create([
        'name'       => config('app.name', 'Company'),
        'slug'       => Str::slug($name),
        'label'      => $autoLabel, // first 4 uppercase ASCII letters of the name
        'is_default' => true,
        'is_active'  => true,
    ]);
}
```

---

### PtahDemoSeeder

**Namespace:** `Ptah\Seeders\PtahDemoSeeder`

Creates demonstration data ready for exploration. All operations are **idempotent** — safe to run multiple times.

```bash
# Run manually
php artisan db:seed --class="Ptah\Seeders\PtahDemoSeeder"

# Or activate during package installation
php artisan ptah:install --demo
```

**What is created:**

| Type | Items |
|---|---|
| Companies | `BETA` (Beta Tecnologia Ltda) and `CORP` (Corp Solutions S/A) |
| Departments | IT, Commercial, Financial |
| Roles | Editor, Viewer |
| Menu items | Users, Products, Reports (only if `menu.driver = database`) |

---

## Multi-company vs Single-tenant

The module works in both scenarios without additional configuration:

### Scenario 1 — Single company

```dotenv
PTAH_MODULE_COMPANY=true
PTAH_MULTI_COMPANY=false  # permissions ignore the company context
```

In this case, the `/ptah-companies` screen is used to complete the company's registration data (name, abbreviation, e-mail, phone, tax ID). Departments organize the access profiles. Without the `permissions` module the screen is only reachable with `PTAH_STRUCTURE_EDITOR=true`.

### Scenario 2 — Multi-company

```dotenv
PTAH_MODULE_COMPANY=true
PTAH_MULTI_COMPANY=true  # config('ptah.permissions.multi_company') — already the default
```

With it on (the default), the permissions resolve the active company from the session key `ptah.permissions.company_session_key`; with it off they never scope by company.

In the `permissions` module, `CompanyService::getCurrentCompanyId()` is used to filter permissions by company. Users can have different roles in each company.

**Custom company selector in the navbar:**

```blade
{{-- Example of a custom Livewire component --}}
<select wire:change="switchCompany($event.target.value)">
    @foreach ($userCompanies as $company)
        <option value="{{ $company->id }}" {{ $current == $company->id ? 'selected' : '' }}>
            {{ $company->name }}
        </option>
    @endforeach
</select>
```

```php
public function switchCompany(int $id): void
{
    app(\Ptah\Contracts\CompanyServiceContract::class)->setCurrentCompany($id);
    $this->redirect(request()->header('Referer') ?? '/dashboard');
}
```

---

## Integration with Permissions

When the `permissions` module is active, departments appear as a category for roles in `RoleList`. The current user's company is automatically used by `PermissionService` to filter permissions.

See complete documentation in [Permissions.md](Permissions.md).

---

## Customizing Views

```bash
# Publishes all package views
php artisan vendor:publish --tag=ptah-views --force
```

Relevant files after publishing:

```
resources/views/vendor/ptah/
└── livewire/
    └── company/
        └── company-list.blade.php   ← Company CRUD
```

`--tag=ptah-views` copies **all** package views and freezes them (your copy wins over the package's from then on); copy just `company-list.blade.php` by hand if that is all you need.

`CompanyList` has no hook for extra fields: `save()` builds its payload inline. To add one (e.g., a `website` field), extend the component, add the property and override `edit()`/`save()`, then register your class on your own route (see [Route](#route)):

```php
// app/Livewire/Company/CompanyList.php
class CompanyList extends \Ptah\Livewire\Company\CompanyList
{
    public string $website = '';
}
```

# Company Module — Complete Documentation

**Pacote:** `jonytonet/ptah`  
**Namespace:** `Ptah\Services\Company`, `Ptah\Livewire\Company`  
**Livewire:** 3.x | **Laravel:** 11+

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
    - [PtahDemoSeeder](#ptahdemoaseeder)
11. [Multi-company vs Single-tenant](#multi-company-vs-single-tenant)
12. [Integration with Permissions](#integration-with-permissions)
13. [Customizing Views](#customizing-views)

---

## Overview

The **company** module provides complete company and department management for the system. It is the foundation for multi-company scenarios (holding, franchises, SaaS) and also works in single-company installations, where it serves only as an organizational context for departments.

| Feature | Description |
|---|---|
| Companies | Complete CRUD with logo, tax data, address and arbitrary settings |
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
    // Model principal. Substitua por \App\Models\Company::class se quiser
    // use a custom model that extends the application model.
    'model'      => \Ptah\Models\Company::class,

    // Database table name
    'table'      => 'ptah_companies',

    // Filesystem disk for logo uploads
    'logo_disk'  => 'public',

    // Base path within the disk to store logos
    'logo_path'  => 'companies/logos',

    // Address fields to be saved in the 'address' JSON
    'address_fields' => ['street', 'number', 'complement', 'district', 'city', 'state', 'country', 'zip'],
],
```

> **Using a custom model:** if your application needs to add methods or relationships to `Company`, create `app/Models/Company.php` extending `Ptah\Models\Company` and point `config('ptah.company.model')` to it.

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
| `address` | json | ✓ | Address fields (according to `address_fields`) |
| `settings` | json | ✓ | Arbitrary settings in JSON |
| `is_default` | boolean | — | System default company (only 1) |
| `is_active` | boolean | — | Visibility / availability |
| `deleted_at` | timestamp | ✓ | SoftDelete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

### ptah_departments

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `name` | string | — | Department name |
| `description` | text | ✓ | Free description |
| `is_active` | boolean | — | Visibility |
| `deleted_at` | timestamp | ✓ | SoftDelete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

> **Why don't departments have company_id?** Departments are global in the standard model — they group roles that can be global or per company. If your application needs departments per company, add the column via a custom migration.

---

## Models

### Company

**Namespace:** `Ptah\Models\Company`  
**Tabela:** `ptah_companies` (ou conforme `config('ptah.company.table')`)  
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

The `Company` automatically generates the `slug` on the `creating` event, from the `name`, using `Str::slug()`. If the slug already exists, a numeric suffix is added (`company`, `company-2`, `company-3`, …). On `updating`, the slug is only regenerated if the `name` field was changed.

#### Instance Methods

| Method | Return | Description |
|---|---|---|
| `getLogoUrl(): string` | string | Full logo URL via `Storage::url()`. If no logo, returns a URL of an avatar generated by the `ui-avatars.com` service with the name initials |
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
**Binding:** singleton in `PtahServiceProvider`

### Interface

```php
interface CompanyServiceContract
{
    public function getDefault(bool $createIfMissing = false): ?Company;
    public function getUserCompanies(mixed $userId = null): Collection;
    public function getCurrentCompanyId(mixed $userId = null): ?int;
    public function setCurrentCompany(int $companyId): void;
    public function createDefaultCompany(): Company;
    public function clearCache(?int $userId = null): void;
}
```

### Methods

#### `getDefault(bool $createIfMissing = false): ?Company`

Returns the company with `is_default = true`. The result is cached under the key `ptah_company_default`.

If `$createIfMissing = true` and no default company exists, calls `createDefaultCompany()` automatically.

```php
$company = app(CompanyServiceContract::class)->getDefault();
```

---

#### `getUserCompanies(mixed $userId = null): Collection`

Returns all companies the user has access to, via join with `ptah_user_roles`.

- If `$userId = null`, resolves by `Auth::id()` or from session (key `config('ptah.permissions.user_session_key')`)
- Cached under the key `ptah_user_companies:{userId}`
- If the `permissions` module is not active or the user is MASTER, returns `Company::active()->get()`

```php
$companies = app(CompanyServiceContract::class)->getUserCompanies();
// Collection of Company
```

---

#### `getCurrentCompanyId(mixed $userId = null): ?int`

Determines the active company in the current context:

1. Reads `Session::get(config('ptah.permissions.company_session_key'))`
2. If empty, uses `getDefault()`
3. If `config('ptah.company.multi_company') = false`, always returns the default company

```php
$companyId = app(CompanyServiceContract::class)->getCurrentCompanyId();
```

---

#### `setCurrentCompany(int $companyId): void`

Saves the ID in the session. Use in company selectors in the navbar.

```php
app(CompanyServiceContract::class)->setCurrentCompany($companyId);
```

---

#### `createDefaultCompany(): Company`

Creates a default company using `config('app.name')` as the name. Called automatically by `DefaultCompanySeeder` and `ptah:module company`.

---

#### `clearCache(?int $userId = null): void`

Invalidates the default company cache. If `$userId` is provided, also invalidates the user companies cache.

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
| `editingId` | int\|null | ID being edited ||null | ID being edited ||| `editingId` | int\|null | ID being edited ||null | ID being edited |
| `name` | string | Form field |
| `label` | string | Form field — abbreviation up to 4 chars, displayed in the switcher badge |
| `email` | string | Form field |
| `phone` | string | Form field |
| `tax_id` | string | Form field |
| `tax_type` | string | Form field |
| `is_active` | bool | Form field |
| `is_default` | bool | Form field |

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
$this->rows // Ptah\Models\Company paginated (15 per page), filtered and sorted
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
'tax_id'   => ['nullable', 'string', 'max:50'],
'tax_type' => ['nullable', 'string', 'in:cnpj,cpf,ein,vat,other'],
```

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
| 1 registered company | Component renders nothing |
| 2 or more companies | Full name of the active one + labels of all (as tabs) |

### Visual Layout

```
[ Laravel ]  |  [ LAR ]  [ SLP ]
  ↑                  ↑       ↑
Active name   Label of each company (clickable button)
```

- The active company tab has a primary color background (`#5b21b6`)
- Clicking another label switches the active company and reloads the current page
- In dark mode, colors adapt via `.ptah-dark` on the ancestor element

### `getLabelDisplay()`

Method of the `Company` model. Returns in priority order:

1. `$company->label` (if filled)
2. First 2 letters of the name (e.g., `Laravel` → `LA`)

Used by CompanySwitcher and the company table badge.

### Livewire Properties

| Property | Type | Description |
|---|---|---|
| `activeId` | int\|null | ID of the active company in session |
| `pageUrl` | string | URL captured in `mount()` for redirect after switching |

### Company Switch

```php
// Internally calls:
$this->companyService->initSession($companyId);
$this->redirect($this->pageUrl);
```

> **Why capture the URL in `mount()`?** Inside a Livewire AJAX request (`/livewire/update`), `request()->fullUrl()` returns the internal endpoint URL — not the page URL. That's why the URL is captured in `mount()` via `url()->current()`, when the component is still in the normal web request context.

---

## Route

Registered automatically when `ptah.modules.company = true`:

| Method | URI | Name | Protection |
|---|---|---|---|
| `GET` | `/ptah-companies` | `ptah.company.index` | `web`, `auth` |

You can override the URL prefix by publishing the routes and editing the file:

```bash
php artisan vendor:publish --tag=ptah-views --force
```

Or by defining custom routes in the application's `routes/web.php`:

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
        'name'       => config('app.name', 'Default Company'),
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
| Departments | IT, Commercial, Finance |
| Roles | Editor, Viewer |
| Menu items | Users, Products, Reports (only if `menu.driver = database`) |

---

## Multi-company vs Single-tenant

The module works in both scenarios without additional configuration:

### Scenario 1 — Single company

```dotenv
PTAH_MODULE_COMPANY=true
# No additional changes
```

In this case, the `/ptah-companies` screen is used to complete the company's registration data (tax ID, logo, address). Departments organize the access profiles.

### Scenario 2 — Multi-company

```dotenv
PTAH_MODULE_COMPANY=true
PTAH_MULTI_COMPANY=true  # Enables session-based resolution
```

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

To override only the company card layout or add extra fields (e.g., `website` field), edit the published file and add the corresponding property in your extended `CompanyList`:

```php
// app/Livewire/Company/CompanyList.php
class CompanyList extends \Ptah\Livewire\Company\CompanyList
{
    public string $website = '';

    protected function extraFillable(): array
    {
        return ['website' => $this->website];
    }
}
```

## Ptah — Laravel Scaffolding & Component Package

`jonytonet/ptah` is a Laravel package that combines **code scaffolding** with a **visual component library** and **optional feature modules**.

### Architecture

The package has three subsystems:

- **Ptah Forge** — Blade component library with Tailwind v4 + Alpine.js (`<x-forge-*>` tags)
- **ptah:forge** — SOLID scaffolding generator that creates a full entity structure in one command
- **BaseCrud** — Dynamic Livewire 3 CRUD component configured via JSON in the database

### Core Conventions

**Scaffolding:** Always generate new entities using `ptah:forge`, never create files manually:

<code-snippet name="Generate entity scaffolding" lang="bash">
php artisan ptah:forge Product \
  --fields="name:string,price:decimal,is_active:boolean,category_id:foreign" \
  --soft-delete
</code-snippet>

This generates: Model, DTO, Repository + Contract, Service + Contract, FormRequests, API Resource, Migration, and Livewire view with BaseCrud.

**Generated layer structure:**
```
app/
├── DTO/{Entity}/{Entity}DTO.php
├── Contracts/Repositories/{Entity}RepositoryContract.php
├── Contracts/Services/{Entity}ServiceContract.php
├── Repositories/{Entity}Repository.php
├── Services/{Entity}Service.php
└── Models/{Entity}.php
database/migrations/...create_{entities}_table.php
resources/views/{entity}/index.blade.php
```

**Icons:** Use CSS classes only — never SVG inline:
- Boxicons: `bx bx-home-alt`, `bx bx-user`, `bx bx-cog`
- FontAwesome: `fas fa-chart-bar`, `fas fa-trash`
- Both libraries are loaded via CDN by `forge-dashboard-layout`

**Dark mode:** Controlled by class `.ptah-dark` on the root element. All CSS overrides must live in `forge-dashboard-layout.blade.php` — never in local `<style>` blocks inside views.

**Livewire inputs:** Use `wire:model.blur` for text, email, phone fields in modals. Never use `wire:model.live` for text inputs.

**Validation uniqueness:** Use `Rule::unique('table', 'column')->ignore($this->editingId)` to prevent duplicate entries while allowing edits.

### Optional Modules

Activate modules via Artisan — never enable manually:

<code-snippet name="Activate optional modules" lang="bash">
php artisan ptah:module auth         # Authentication with 2FA
php artisan ptah:module menu         # Dynamic sidebar menu
php artisan ptah:module company      # Multi-company management
php artisan ptah:module permissions  # RBAC access control
</code-snippet>

Module flags are stored in `.env` (`PTAH_MODULE_AUTH`, `PTAH_MODULE_MENU`, etc.) and read via `config('ptah.modules.*')`.

**Module dependency:** `permissions` requires `company`. All other modules are independent.

### BaseCrud Configuration

BaseCrud reads its config from the `crud_configs` database table (field `model`). Configure columns, filters and modal via JSON — not PHP code.

<code-snippet name="Minimal BaseCrud view" lang="blade">
@extends('ptah::layouts.forge-dashboard')
@section('title', 'Products')
@section('content')
    @livewire('ptah::base-crud', ['model' => 'Product'])
@endsection
</code-snippet>

Column types: `text`, `badge`, `boolean`, `money`, `date`, `datetime`, `image`, `method`.

### Testing

The package uses Orchestra Testbench with SQLite `:memory:`. Tests extend `Ptah\Tests\TestCase`.

**Important:** The package does not use Eloquent Factory. Use `CompanyFactory::new()->create([...])` from `tests/Factories/`. The `PtahServiceProvider` automatically registers all service binds — no manual binding needed in tests.

<code-snippet name="Base test class usage" lang="php">
use Ptah\Tests\TestCase;
use Ptah\Tests\Factories\CompanyFactory;

class MyTest extends TestCase
{
    public function test_something(): void
    {
        $company = CompanyFactory::new()->create(['name' => 'Acme']);
        // ...
    }
}
</code-snippet>

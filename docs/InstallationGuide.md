# 🚀 Installation Guide — jonytonet/ptah

> **Laravel 11 / 12 · PHP 8.2+**  
> This guide documents every step to install ptah in a **fresh Laravel project** — including real terminal output collected during the process.

---

## Summary

- [Prerequisites](#prerequisites)
- [Step 1 — Create the Laravel project](#step-1--create-the-laravel-project)
- [Step 2 — Install jonytonet/ptah](#step-2--install-jonytonetptah)
- [Step 3 — Configure the environment](#step-3--configure-the-environment)
- [Step 4 — Run ptah:install](#step-4--run-ptahinstall)
- [Step 5 — Generate your first entity](#step-5--generate-your-first-entity)
- [Step 6 — Start the development server](#step-6--start-the-development-server)
- [Optional modules](#optional-modules)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

| Tool | Minimum version |
|------|----------------|
| PHP | 8.2 |
| Composer | 2.x |
| Node.js + npm | 18+ |
| Laravel | 11 or 12 |
| Database | SQLite, MySQL 8+, PostgreSQL 15+ |

> **Note:** ptah ships Livewire v4 as a dependency. No additional installation is required.

### Verify your environment

Before starting, confirm all tools meet the minimum versions:

```bash
php -v
```
```bash
composer -V
```
```bash
node -v
```
```bash
npm -v
```

Expected output (example):
```
PHP 8.2.x (cli) ...
Composer version 2.x.x ...
v20.x.x
10.x.x
```

> If any version is below the minimum, update it before proceeding.
>
> - **PHP:** [php.net/downloads](https://www.php.net/downloads)
> - **Composer:** [getcomposer.org](https://getcomposer.org)
> - **Node.js / npm:** [nodejs.org](https://nodejs.org)

---

## Step 1 — Create the Laravel project

```bash
composer create-project laravel/laravel ptah-app
cd ptah-app
```

**Real output:**

```
Creating a "laravel/laravel" project at "./ptah-app"
Installing laravel/laravel (v12.1.0)
  - Installing laravel/laravel (v12.1.0): Extracting archive
Created project in C:\...\ptah-app
...
Application key set successfully.
```

After completion you will have a clean **Laravel 12** project ready.

---

## Step 2 — Install jonytonet/ptah

### From Packagist (stable — recommended)

```bash
composer require jonytonet/ptah
```

### From local path (local development / testing)

If you are developing ptah itself, add a path repository to `composer.json` before requiring:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../ptah",
            "options": { "symlink": true }
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then:

```bash
composer require jonytonet/ptah:@dev
```

**Real output (path install):**

```
./composer.json has been updated
Running composer update jonytonet/ptah
  - Junctioning jonytonet/ptah (../ptah)
  - Installing livewire/livewire (v4.2.1): Extracting archive
...
Package manifest generated successfully.
```

After this step the following packages are added to your project:

| Package | Version |
|---------|---------|
| `jonytonet/ptah` | 1.0.0 |
| `livewire/livewire` | v4.2.1 |

---

## Step 3 — Configure the environment

ptah works with any database supported by Laravel. Set `DB_CONNECTION` in your `.env` to the driver of your choice before running migrations.

### SQLite *(quick start / local development)*

Laravel 12 ships with SQLite pre-configured. If you want to use it, no changes are needed — just make sure `database/database.sqlite` exists:

```bash
touch database/database.sqlite
```

```env
DB_CONNECTION=sqlite
```

### MySQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ptah_app
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database first:

```bash
mysql -u root -p -e "CREATE DATABASE ptah_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### PostgreSQL

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ptah_app
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Create the database first:

```bash
psql -U postgres -c "CREATE DATABASE ptah_app ENCODING 'UTF8';"
```

### Language / locale

> ⚠️ **ptah does not officially support pt_BR.** This guide was fully written and validated with `APP_LOCALE=en`. The package ships translation files for both `en` and `pt_BR` under `lang/vendor/ptah/`, but the **pt_BR strings are not maintained** and may be incomplete or outdated.

The default Laravel 12 `.env` ships with English already set:

```env
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
```

**Leave these values as-is.** Changing to `pt_BR` is possible but not supported — some labels, error messages and UI strings inside ptah will remain in English regardless.

---

## Step 4 — Run ptah:install

This is the main command that bootstraps everything:

```bash
php artisan ptah:install
```

The installer will ask whether it should run migrations automatically:

```
Would you like to run the database migrations now? (yes/no) [yes]:
> yes
```

**Real output — full session:**

```
 ____  _        _
|  _ \| |_ __ _| |__
| |_) | __/ _` | '_ \
|  __/| || (_| | | | |
|_|    \__\__,_|_| |_|

 Ptah Installer — Laravel Scaffold System

 Publishing config...
  INFO  Publishing [ptah-config] assets.
  Copying File [.../config/ptah.php] to [config/ptah.php] ........................ DONE

 Publishing stubs...
  INFO  Publishing [ptah-stubs] assets.
  Copying File [...] to [stubs/ptah/controller.stub] ............................. DONE
  Copying File [...] to [stubs/ptah/model.stub] .................................. DONE
  [... 21 stub files in total ...]

 Publishing migrations...
  INFO  Publishing [ptah-migrations] assets.
  [... 13 migration files ...]

 Publishing language files...
  INFO  Publishing [ptah-lang] assets.
  [... en / pt_BR ...]

 Configuring Tailwind CSS...
  INFO  Tailwind CSS configured in resources/css/app.css.

 Running migrations...
  INFO  Running migrations.
  2024_01_01_000000_create_user_preferences_table ............... 17.28ms DONE
  2024_01_01_000001_create_crud_configs_table ................... 12.11ms DONE
  2024_01_03_000000_create_menus_table .......................... 18.55ms DONE
  2024_01_03_000001_add_two_factor_columns_to_users_table ....... 10.04ms DONE
  2024_01_04_000000_create_ptah_companies_table ................. 14.99ms DONE
  2024_01_04_000001_create_ptah_departments_table ............... 11.77ms DONE
  2024_01_04_000002_create_ptah_roles_table ..................... 13.28ms DONE
  2024_01_04_000003_create_ptah_pages_table ..................... 16.72ms DONE
  2024_01_04_000004_create_ptah_page_objects_table .............. 18.51ms DONE
  2024_01_04_000005_create_ptah_role_permissions_table .......... 11.85ms DONE
  2024_01_04_000006_create_ptah_user_roles_table ................ 12.34ms DONE
  2024_01_04_000007_create_ptah_permission_audits_table ......... 12.98ms DONE
  2024_01_05_000000_add_audit_fields_to_ptah_tables ............. 10.55ms DONE

 Creating default data...
  ✔ Company "Laravel" created.
  ✔ Department "Administration" created.
  ✔ Role "MASTER" created.
  ✔ User [admin@admin.com] created.

 Creating storage link...
  INFO  The [public/storage] link has been connected to [storage/app/public].

 Running npm install...
  [npm install output — this may take a minute]

  ✔  Ptah installed successfully. Happy scaffolding!
```

### What gets published

| Artifact | Location |
|----------|----------|
| Configuration | `config/ptah.php` |
| Stub files (21) | `stubs/ptah/` |
| Migrations (13) | `database/migrations/` |
| Language files | `lang/vendor/ptah/en/` and `lang/vendor/ptah/pt_BR/` |

### Default data created

| Entity | Value |
|--------|-------|
| Company | Laravel |
| Department | Administration |
| Role | MASTER |
| Admin user | admin@admin.com |

> **Important:** Change the admin password immediately in production. Run `php artisan tinker` and use `User::find(1)->update(['password' => bcrypt('your-password')]);`.

### Tables created

All ptah tables are prefixed with `ptah_` to avoid conflicts with your application tables:

```
ptah_companies · ptah_departments · ptah_roles · ptah_pages
ptah_page_objects · ptah_role_permissions · ptah_user_roles
ptah_permission_audits · user_preferences · crud_configs · menus
```

---

## Step 5 — Generate your first entity

Use `ptah:forge` to scaffold a complete CRUD entity in seconds:

```bash
php artisan ptah:forge Product \
  --fields="name:string,description:text:nullable,price:decimal(10,2),is_active:boolean,category_id:unsignedBigInteger:nullable"
```

**Real output:**

```
   INFO  Ptah Forge — Generating: Product.

  Table: products | Fields: 5 | Modo: Web

+----------------------------------------+--------+
| Artifact                               | Status |
+----------------------------------------+--------+
| Model [Product]                        | DONE   |
| Migration [create_products_table]      | DONE   |
| Binding [AppServiceProvider]           | DONE   |
| DTO [ProductDTO]                       | DONE   |
| Interface [ProductRepositoryInterface] | DONE   |
| Repository [ProductRepository]         | DONE   |
| Service [ProductService]               | DONE   |
| Controller [ProductController]         | DONE   |
| Request [StoreProduct]                 | DONE   |
| Request [UpdateProduct]                | DONE   |
| Resource [ProductResource]             | DONE   |
| CrudConfig [Product]                   | DONE   |
| View [product/index]                   | DONE   |
| Routes [web.php]                       | DONE   |
+----------------------------------------+--------+

  14 created · 0 skipped · 0 error(s)

  Next steps:

  ✔ Binding automatically registered in AppServiceProvider.

  1. Run the migration:
     php artisan migrate

  2. Review the validation rules in the generated Requests.

  Access: /product

  → The screen uses Livewire BaseCrud. Configuration saved in crud_configs
    and can be adjusted directly in the database.
```

Then run the migration:

```bash
php artisan migrate
```

```
   INFO  Running migrations.
  2026_03_04_115024_create_products_table ....................... 34.30ms DONE
```

### Files generated by ptah:forge

| File | Purpose |
|------|---------|
| `app/Models/Product.php` | Eloquent model |
| `app/DTO/ProductDTO.php` | Data Transfer Object |
| `app/Contracts/Repositories/ProductRepositoryInterface.php` | Repository interface |
| `app/Repositories/ProductRepository.php` | Eloquent repository |
| `app/Services/ProductService.php` | Business logic |
| `app/Http/Controllers/ProductController.php` | Web controller |
| `app/Http/Requests/StoreProduct.php` | Store validation |
| `app/Http/Requests/UpdateProduct.php` | Update validation |
| `app/Http/Resources/ProductResource.php` | API resource |
| `resources/views/product/index.blade.php` | Livewire BaseCrud view |
| `database/migrations/..._create_products_table.php` | Migration |
| *(route entry in `routes/web.php`)* | Route registered |
| *(binding in `AppServiceProvider`)* | Interface → implementation |
| *(row in `crud_configs`)* | Column/field configuration |

### Field type syntax

```
fieldName:type[:modifier]
```

| Modifier | Effect |
|----------|--------|
| `nullable` | Column accepts NULL |
| *(none)* | NOT NULL column |

Common types: `string`, `text`, `integer`, `unsignedBigInteger`, `boolean`, `decimal(8,2)`, `date`, `datetime`, `json`, `enum`.

---

## Step 6 — Start the development server

```bash
# Terminal 1 — PHP dev server
php artisan serve

# Terminal 2 — Vite (CSS / JS hot reload)
npm run dev
```

Navigate to [http://localhost:8000](http://localhost:8000).

| Route | Screen |
|-------|--------|
| `/login` | Login (default Laravel auth) |
| `/product` | Generated Product CRUD |

Login with:
- E-mail: `admin@admin.com`
- Password: *(set via tinker — see Step 4 note)*

---

## Optional modules

ptah ships optional modules activated via Artisan. Each module writes its `.env` flag to `true` and runs its own migrations.

```bash
# See current status of all modules
php artisan ptah:module --list
```

```
+-------------+-------------------------+------------+
| Module      | .env Variable           | Status     |
+-------------+-------------------------+------------+
| auth        | PTAH_MODULE_AUTH        | ✘ inactive |
| menu        | PTAH_MODULE_MENU        | ✘ inactive |
| company     | PTAH_MODULE_COMPANY     | ✘ inactive |
| permissions | PTAH_MODULE_PERMISSIONS | ✘ inactive |
| api         | PTAH_MODULE_API         | ✘ inactive |
+-------------+-------------------------+------------+
```

---

### Module: auth

Activates login, logout, remember-me, session protection and optional TOTP two-factor authentication via Livewire pages managed by ptah.

```bash
php artisan ptah:module auth
```

**Real output:**

```
   INFO  Enabling module: auth.

  Publishing 2FA migration

   INFO  Publishing [ptah-auth] assets.

  File [...2024_01_03_000001_add_two_factor_columns_to_users_table.php] already exists  SKIPPED
  ...................................................... 8.21ms DONE
  Running migrations

   INFO  Nothing to migrate.

  ...................................................... 29.61ms DONE

   INFO  Module 'auth' enabled successfully!

  Next steps:
  1. Make sure your User model uses HasUserPreferences
  2. Configure config/ptah.php (auth section)
  3. Add the authentication middleware to desired routes
  4. For TOTP 2FA install: composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

> The migration `add_two_factor_columns_to_users_table` adds `two_factor_secret`, `two_factor_recovery_codes` and `two_factor_confirmed_at` to the `users` table. If you ran `ptah:install` before enabling this module, the migration is already in the database and will be skipped.

**What is set in `.env`:**

```env
PTAH_MODULE_AUTH=true
```

**`config/ptah.php` section to review:**

```php
'auth' => [
    'guard'               => 'web',
    'home'                => '/dashboard',
    'register_enabled'    => false,
    'two_factor'          => true,
    'remember_me'         => true,
    'session_protection'  => true,
],
```

**Add trait to User model** (`app/Models/User.php`):

```php
use Ptah\Traits\HasUserPreferences;

class User extends Authenticatable
{
    use HasUserPreferences;
    // ...
}
```

**For TOTP two-factor (optional):**

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

---

### Module: menu

Activates a dynamic sidebar menu, configurable via the `/ptah-menu` admin screen (database driver) or via `config/ptah.php` (config driver).

```bash
php artisan ptah:module menu
```

**Real output:**

```
   INFO  Enabling module: menu.

  Publishing menu migration

   INFO  Publishing [ptah-menu] assets.

  File [...2024_01_03_000000_create_menus_table.php] already exists  SKIPPED
  .................................................... 6.27ms DONE
  Running migrations

   INFO  Nothing to migrate.

  .................................................... 36.98ms DONE

   INFO  Module 'menu' enabled successfully!

  Next steps:
  1. Set PTAH_MENU_DRIVER=database in .env (default: config)
  2. Manage menu items at /ptah-menu (requires the auth module)
```

**What is set in `.env`:**

```env
PTAH_MODULE_MENU=true
```

**Choose the menu driver** (add to `.env`):

```env
# Use the database-driven menu manager (recommended for production)
PTAH_MENU_DRIVER=database

# Or keep the default static config-driven menu
# PTAH_MENU_DRIVER=config
```

**`config/ptah.php` section (config driver):**

```php
'menu' => [
    'driver'       => env('PTAH_MENU_DRIVER', 'config'),
    'sidebar_items' => [
        // ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'],
    ],
],
```

---

### Module: company

Activates multi-company / multi-tenant support. Provides a company switcher, department management and `CompanyService` helpers.

```bash
php artisan ptah:module company
```

**Real output:**

```
   INFO  Enabling module: company.

  Publishing company migrations

   INFO  Publishing [ptah-company] assets.

  File [...2024_01_04_000000_create_ptah_companies_table.php] already exists  SKIPPED
  File [...2024_01_04_000001_create_ptah_departments_table.php] already exists  SKIPPED
  ................................................ 5.03ms DONE
  Running migrations

   INFO  Nothing to migrate.

  ................................................ 28.77ms DONE
  Seeding default company

   INFO  Seeding database.

  → Default company already exists: Laravel
  ..................................................... 15.48ms DONE

   INFO  Module 'company' enabled successfully!

  Next steps:
  1. Visit /ptah-companies to manage companies
  2. Configure ptah.company in config/ptah.php to customise behaviour
  3. Use CompanyService::getCurrentCompanyId() in your queries
```

**What is set in `.env`:**

```env
PTAH_MODULE_COMPANY=true
```

**Using CompanyService in queries:**

```php
use Ptah\Services\CompanyService;

$companyId = CompanyService::getCurrentCompanyId();
$products = Product::where('company_id', $companyId)->get();
```

**Available routes:**

| Route | Description |
|-------|-------------|
| `/ptah-companies` | Manage companies |
| `/ptah-departments` | Manage departments |

---

### Module: permissions

Activates full RBAC — roles, pages, page objects, middleware guards and Blade directives. This module also seeds the default admin user.

```bash
php artisan ptah:module permissions
```

**Real output:**

```
   INFO  Enabling module: permissions.

  Publishing permissions migrations

   INFO  Publishing [ptah-permissions] assets.

  File [...create_ptah_roles_table.php] already exists  SKIPPED
  File [...create_ptah_pages_table.php] already exists  SKIPPED
  File [...create_ptah_page_objects_table.php] already exists  SKIPPED
  File [...create_ptah_role_permissions_table.php] already exists  SKIPPED
  File [...create_ptah_user_roles_table.php] already exists  SKIPPED
  File [...create_ptah_permission_audits_table.php] already exists  SKIPPED
  ............................................. 12.33ms DONE
  Running migrations

   INFO  Nothing to migrate.

  ............................................. 37.89ms DONE
  Seeding default admin

   INFO  Seeding database.

  → Default company: Laravel
  → Department: Administration
  → MASTER role: MASTER
  → Admin user: admin@admin.com
  → Binding already exists.
  ....................................................... 52.83ms DONE

  ╔══════════════════════════════════════════╗
  ║  Admin created successfully!             ║
  ║  E-mail   :  admin@admin.com             ║
  ║  Password :  (set via PTAH_ADMIN_PASSWORD or reset via tinker)
  ║  ⚠ Change your password on first login!  ║
  ╚══════════════════════════════════════════╝

   INFO  Module 'permissions' enabled successfully!

  Next steps:
  1. Visit /ptah-pages and register the system pages and objects
  2. In /ptah-roles create roles and configure permissions per object
  3. In /ptah-users-acl assign roles to users
  4. Use @ptahCan('key', 'action') in Blade views
  5. Use Route::middleware('ptah.can:resource,action') on routes
  6. For audit logging set PTAH_PERMISSION_AUDIT=true in .env
```

**What is set in `.env`:**

```env
PTAH_MODULE_PERMISSIONS=true
```

> **Important — admin password:** The admin password is read from `config('ptah.permissions.admin_password')`, which maps to `env('PTAH_ADMIN_PASSWORD')`. Since this variable isn't set by default, the displayed password will be blank. **Always define `PTAH_ADMIN_PASSWORD` in `.env` before running this module**, or reset the password immediately after:
>
> ```bash
> php artisan tinker
> >>> \App\Models\User::where('email','admin@admin.com')->first()->update(['password' => bcrypt('your-password')]);
> ```
>
> Add to `.env` before enabling:
> ```env
> PTAH_ADMIN_PASSWORD=YourSecurePassword123
> ```

**Blade directive:**

```blade
@ptahCan('products', 'edit')
    <a href="{{ route('product.edit', $product) }}">Edit</a>
@endptahCan
```

**Route middleware:**

```php
Route::middleware(['auth', 'ptah.can:products,edit'])->group(function () {
    Route::resource('products', ProductController::class);
});
```

**Audit logging:**

```env
PTAH_PERMISSION_AUDIT=true
```

**Available routes:**

| Route | Description |
|-------|-------------|
| `/ptah-pages` | Register system pages and objects |
| `/ptah-roles` | Create roles and configure permissions |
| `/ptah-users-acl` | Assign roles to users |

---

### Module: api

Installs `darkaonline/l5-swagger` via Composer and publishes three base classes: `BaseApiController`, `BaseResponse`, and `SwaggerInfo`.

> **Note:** This module runs `composer require darkaonline/l5-swagger`. Make sure your proxy/network allows it, or install manually first then run `ptah:module api`.

```bash
php artisan ptah:module api
```

**Real output:**

```
   INFO  Enabling module: api.

  Instalando darkaonline/l5-swagger ......................................... 1m DONE
  Publishing API base classes

   INFO  Publishing [ptah-api] assets.

  Copying file [...base-response.stub] to [...app/Responses/BaseResponse.php]  DONE
  Copying file [...base-api-controller.stub] to [...app/Http/Controllers/API/BaseApiController.php]  DONE
  Copying file [...swagger-info.stub] to [...app/Http/Controllers/API/SwaggerInfo.php]  DONE

  ................................................. 31.95ms DONE
  Publishing L5-Swagger config ................................................ 0.02ms DONE

   INFO  Module 'api' enabled successfully!

  Next steps:
  1. Visit /api/documentation to see the Swagger UI
  2. Regenerate docs: php artisan l5-swagger:generate
  3. Scaffold entities with: php artisan ptah:forge Catalog/Product --api
  4. ⚠  NEVER use response()->json() — use BaseResponse::
  5. Configure the scan path in config/l5-swagger.php if needed
```

**What is set in `.env`:**

```env
PTAH_MODULE_API=true
```

**Files published:**

| File | Purpose |
|------|---------|
| `app/Responses/BaseResponse.php` | Standard API response wrapper |
| `app/Http/Controllers/API/BaseApiController.php` | Base controller for all API controllers |
| `app/Http/Controllers/API/SwaggerInfo.php` | Swagger `@OA\Info` annotation |
| `config/l5-swagger.php` | L5-Swagger configuration |

**Example — using BaseResponse:**

```php
use App\Responses\BaseResponse;

public function index(): JsonResponse
{
    $items = ProductResource::collection(Product::paginate());
    return BaseResponse::success($items);
}
```

**Generate Swagger docs:**

```bash
php artisan l5-swagger:generate
```

Then visit [http://localhost:8000/api/documentation](http://localhost:8000/api/documentation).

**Scaffold an API entity with ptah:forge:**

```bash
php artisan ptah:forge Product --api \
  --fields="name:string,price:decimal(10,2),is_active:boolean"
```

---

## Laravel Boost — AI agent integration

[Laravel Boost](https://laravel.com/docs/boost) wires AI agents (GitHub Copilot, Claude, Cursor, Gemini, etc.) with your project via **guidelines**, **skills** and an **MCP server**. When ptah is installed, its own guidelines are automatically offered at install time.

```bash
php artisan ptah:install --boost
```

> If ptah is already installed, you can add Boost later with the same command — all existing resources are skipped and only Boost is installed.

**What `--boost` does:**

1. Runs `composer require laravel/boost --dev` (installs `laravel/boost v2+`, `laravel/mcp`, `laravel/roster`)
2. Runs `php artisan boost:install` (interactive — see below)

**Real output (Composer step):**

```
   INFO  Installing Laravel Boost for AI agent integration...

  Installing laravel/boost via Composer
  - Locking laravel/boost (v2.2.2)
  - Locking laravel/mcp (v0.6.0)
  - Locking laravel/roster (v0.5.0)
  ...
  - Installing laravel/boost (v2.2.2): Extracting archive
  ......................................................... 29s DONE
```

> **Note:** `boost:install` is an interactive command. Because it runs in a spawned process from `ptah:install`, the interactive prompts may not be available. If you see `"The boost:install command is not available in this session"`, run it manually:

```bash
php artisan boost:install
```

**Real output (boost:install):**

```
██████╗   ██████╗   ██████╗  ███████╗ ████████╗
...
 ✦ Laravel Boost :: Install :: We Must Ship ✦

  Which Boost features would you like to configure? [guidelines,skills,mcp]
  ❯ AI Guidelines ..................... guidelines
    Agent Skills ........................ skills
    Boost MCP Server Configuration .......... mcp

  Which third-party AI guidelines/skills would you like to install? [None]
  ❯ None
    jonytonet/ptah (guidelines, skills) .. jonytonet/ptah

  Which AI agents would you like to configure? [Claude Code]
  ❯ Amp · Claude Code · Codex · Cursor · Gemini CLI · GitHub Copilot · Junie · OpenCode


Adding 8 guidelines to your selected agents

 ┌───────┬──────────────┬──────────────┬──────────────────┐
 │ boost │ foundation   │ laravel/core │ laravel/v12      │
 ├───────┼──────────────┼──────────────┼──────────────────┤
 │ php   │ phpunit/core │ pint/core    │ tailwindcss/core │
 └───────┴──────────────┴──────────────┴──────────────────┘

  Claude Code... ✓

Syncing 1 skills for skills-capable agents

 ┌─────────────────────────┐
 │ tailwindcss-development │
 └─────────────────────────┘

  Claude Code... ✓

Installing MCP servers to your selected Agents

  Claude Code... ✓

              Enjoy the boost 🚀
```

**Packages installed by Boost:**

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/boost` | v2.2.2 | Core guidelines + agent integration |
| `laravel/mcp` | v0.6.0 | MCP server protocol |
| `laravel/roster` | v0.5.0 | Skills roster for AI agents |

**What Boost configures:**

- **Guidelines** — Markdown files in `.github/` (Copilot), `CLAUDE.md` (Claude), `.cursor/` (Cursor), etc., containing project-specific instructions for AI agents
- **Skills** — reusable agent skills (e.g. `tailwindcss-development`)
- **MCP Server** — exposes your Laravel app as an MCP-compatible server so AI agents can introspect routes, models and more

When `jonytonet/ptah` is selected as a third-party guideline provider, Boost copies ptah's guidelines (from `vendor/jonytonet/ptah/resources/boost/guidelines/`) into your project's agent config, giving every AI agent deep knowledge of ptah conventions.

---

## Final state — all modules active

After installing all modules, running `php artisan ptah:module --list` should show:

```
+-------------+-------------------------+----------+
| Module      | .env Variable           | Status   |
+-------------+-------------------------+----------+
| auth        | PTAH_MODULE_AUTH        | ✔ active |
| menu        | PTAH_MODULE_MENU        | ✔ active |
| company     | PTAH_MODULE_COMPANY     | ✔ active |
| permissions | PTAH_MODULE_PERMISSIONS | ✔ active |
| api         | PTAH_MODULE_API         | ✔ active |
+-------------+-------------------------+----------+
```

And `composer show | grep -E "boost|ptah|livewire|swagger"` should list:

```
darkaonline/l5-swagger   8.x
jonytonet/ptah           1.0.0
laravel/boost            v2.2.2
laravel/mcp              v0.6.0
laravel/roster           v0.5.0
livewire/livewire        v4.2.1
```

---

## Troubleshooting

### Proxy blocks Composer / npm

If your environment routes traffic through a corporate proxy that is unavailable:

```powershell
# PowerShell — clear proxy before any Composer/npm/git command
$env:http_proxy=""; $env:https_proxy=""; composer require jonytonet/ptah
```

On Linux/macOS:

```bash
unset http_proxy https_proxy HTTP_PROXY HTTPS_PROXY
composer require jonytonet/ptah
```

### Windows: symlink requires admin / Developer Mode

When using a local path repository with `"symlink": true`, Composer falls back to a **junction** on Windows if you are not running as Administrator.  
Enable **Developer Mode** in *Settings → System → For developers* to allow symlinks without elevation.

### `ptah:forge` skips existing files

If you run `ptah:forge` on an entity that was already generated, existing files are **skipped** (status: `SKIPPED`). Use `--force` to overwrite:

```bash
php artisan ptah:forge Product --fields="..." --force
```

### Tailwind classes not building

Make sure `npm run dev` (or `npm run build`) is running so Vite processes the Blade views.  
If ptah views are not included in Tailwind's content scan, add to `tailwind.config.js`:

```js
content: [
    './resources/views/**/*.blade.php',
    './vendor/jonytonet/ptah/resources/views/**/*.blade.php',
],
```

### Admin password unknown after install

The default admin user is created by ptah's seeder without printing the password in the terminal. Reset it via tinker:

```bash
php artisan tinker
>>> \App\Models\User::find(1)->update(['password' => bcrypt('secret123')]);
```

---

> 📄 Back to [README](../README.md) · See also [BaseCrud.md](BaseCrud.md) · [Modules.md](Modules.md)

# 🚀 Installation Guide — jonytonet/ptah

> **Laravel 11 / 12 · PHP 8.2+**  
> This guide documents every step to install ptah in a **fresh Laravel project** — including real terminal output collected during the process.

---

## Sumário

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
| Database | SQLite *(default)*, MySQL 8+, PostgreSQL 15+ |

> **Note:** ptah ships Livewire v4 as a dependency. No additional installation is required.

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

Laravel 12 uses **SQLite by default**. The `.env` already contains:

```env
DB_CONNECTION=sqlite
```

And `database/database.sqlite` is created automatically. **No further database configuration is needed for a quick start.**

### If you prefer MySQL

Change `.env` to:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ptah_app
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database:

```bash
mysql -u root -p -e "CREATE DATABASE ptah_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

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

ptah ships optional modules that can be enabled in `config/ptah.php` and activated via Artisan commands. See [Modules.md](Modules.md) for full documentation.

### Available modules

| Module | Command | Description |
|--------|---------|-------------|
| Auth | `php artisan ptah:module auth` | 2FA, session control |
| Menu | `php artisan ptah:module menu` | Dynamic sidebar menu |
| Company | `php artisan ptah:module company` | Multi-company / multi-tenant |
| Permissions | `php artisan ptah:module permissions` | Role-based access control |
| API | `php artisan ptah:module api` | REST API with Swagger |

Each module adds its own routes, views, and migrations. Enable them one at a time and run `php artisan migrate` after each.

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

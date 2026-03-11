# Ptah Commands

This document lists all Artisan commands available in the Ptah package.

---

## Table of Contents

1. [ptah:install](#ptahinstall)
2. [ptah:forge](#ptahforge)
3. [ptah:module](#ptahmodule)
4. [ptah:config](#ptahconfig)
5. [ptah:hooks](#ptahhooks)
6. [vendor:publish (tags ptah)](#vendorpublish-tags-ptah)

---

## ptah:install

**Description:** Installs the Ptah package in the Laravel project.

**Usage:**
```bash
php artisan ptah:install
php artisan ptah:install --force
php artisan ptah:install --skip-npm
php artisan ptah:install --demo
php artisan ptah:install --boost
```

**Options:**
- ``--force`` — Overwrites existing files without asking
- ``--skip-npm`` — Does not run ``npm install`` and ``npm run build``
- ``--demo`` — Installs demo data (companies, departments, roles, menu)
- ``--boost`` — Installs Laravel Boost for AI agent integration (Copilot, Claude, Cursor)

**What it does:**

1. **Publishes configurations** → ``config/ptah.php``
2. **Publishes stubs** → ``stubs/ptah/`` (for customization)
3. **Publishes migrations** → ``database/migrations/``
4. **Publishes translations** → ``lang/vendor/ptah/``
5. **Configures Tailwind CSS** → Injects design tokens into ``resources/css/app.css``
6. **Runs migrations** → Creates ``ptah_*`` tables
7. **Creates storage symlink** → ``php artisan storage:link``
8. **Default admin seed** → Creates company and admin user (if migrations ran)
9. **Demo seed** → Creates sample data (if ``--demo``)
10. **Installs Boost** → ``composer require laravel/boost --dev`` + ``boost:install`` (if ``--boost``)
11. **Installs Node dependencies** → ``npm install && npm run build`` (except with ``--skip-npm``)

**Default credentials:** (configurable in ``config/ptah.php``)
- E-mail: ``admin@admin.com``
- Password: ``admin@123``

**Next steps after installation:**

1. Review ``config/ptah.php``
2. Add the ``HasUserPreferences`` trait to the User model
3. Enable required modules:
   - ``php artisan ptah:module auth`` — Login, 2FA, profile
   - ``php artisan ptah:module menu`` — Dynamic sidebar
   - ``php artisan ptah:module company`` — Multi-company
   - ``php artisan ptah:module permissions`` — RBAC
4. Log in with default credentials
5. Scaffold entities with ``php artisan ptah:forge {Entity}``
6. *(Optional)* Publish Docker environment:
   ```bash
   php artisan vendor:publish --tag=ptah-docker
   ```

---

## ptah:forge

**Description:** Generates complete structure for an entity (SOLID scaffolding).

**Usage:**
```bash
# Basic
php artisan ptah:forge Product

# Com subdirectory (Product/ProductStock)
php artisan ptah:forge Product/ProductStock

# Specify custom table
php artisan ptah:forge Product --table=custom_products

# Define fields manually
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2):nullable,status:enum(active|inactive)"

# Read fields from database
php artisan ptah:forge Product --db

# Generate Web + API together
php artisan ptah:forge Product --api

# Generate ONLY API (without web views)
php artisan ptah:forge Product --api-only

# Without soft deletes
php artisan ptah:forge Product --no-soft-deletes

# Overwrite existing files
php artisan ptah:forge Product --force
```

**Options:**
- ``--table=`` — Table name in the database (default: plural snake_case of entity)
- ``--fields=`` — Field definition: ``"field:type:modifiers"``
- ``--db`` — Read fields directly from the database (via INFORMATION_SCHEMA)
- ``--api`` — Generates web + API together (API Controller, API Requests, Swagger, v1 routes)
- ``--api-only`` — Generates ONLY API, without web views (Livewire not created)
- ``--no-soft-deletes`` — Does not add SoftDeletes to the model
- ``--force`` — Overwrites existing files without confirmation

**Arquivos gerados:**

### Web Mode (default)
| Type | Path | Description |
|------|---------|-----------|
| Model | ``app/Models/{Entity}.php`` | Eloquent model with SoftDeletes |
| Migration | ``database/migrations/{timestamp}_create_{entities}_table.php`` | Table schema |
| DTO | ``app/DTOs/{Entity}DTO.php`` | Data Transfer Object |
| Repository Interface | ``app/Repositories/Contracts/{Entity}RepositoryInterface.php`` | Repository contract |
| Repository | ``app/Repositories/{Entity}Repository.php`` | Repository implementation |
| Service | ``app/Services/{Entity}Service.php`` | Business logic |
| Controller | ``app/Http/Controllers/{Entity}Controller.php`` | Web controller (BaseCrud Livewire) |
| Request Store | ``app/Http/Requests/Store{Entity}Request.php`` | Create validation |
| Request Update | ``app/Http/Requests/Update{Entity}Request.php`` | Update validation |
| Resource | ``app/Http/Resources/{Entity}Resource.php`` | API Resource (also used in web) |
| View | ``resources/views/{entity}/index.blade.php`` | Index view with BaseCrud |
| Route | ``routes/web.php`` | Web route for CRUD |
| CrudConfig | ``crud_configs`` table | BaseCrud JSON configuration |
| Binding | ``app/Providers/AppServiceProvider.php`` | Injected repository binding |

### API Mode (--api or --api-only)
| Type | Path | Description |
|------|---------|-----------|
| Controller API | ``app/Http/Controllers/API/{Entity}Controller.php`` | API Controller with Swagger annotations |
| Request Create API | ``app/Http/Requests/Create{Entity}Request.php`` | API create validation |
| Request Update API | ``app/Http/Requests/Update{Entity}Request.php`` | API update validation |
| Route API | ``routes/api.php`` | API v1 routes |

> **Note:** With ``--api``, generates **web + API**. With ``--api-only``, generates **only API** (no views, no Livewire).

**Fields syntax:**

```bash
--fields="campo1:tipo:modificador1:modificador2,campo2:tipo"
```

**Available types:**
- ``string``, ``text``, ``integer``, ``bigInteger``, ``unsignedBigInteger``
- ``decimal(10,2)``, ``float``, ``double``
- ``boolean``, ``date``, ``datetime``, ``timestamp``
- ``enum(value1|value2|value3)``
- ``json``, ``jsonb``

**Modifiers:**
- ``nullable`` — Allows NULL
- ``unique`` — Unique index
- ``index`` — Simple index
- ``default(value)`` — Default value

**Examples:**

```bash
# Basic e-commerce
php artisan ptah:forge Product --fields="name:string,description:text:nullable,price:decimal(10,2),stock:integer:default(0),is_active:boolean:default(true)"

# Foreign key
php artisan ptah:forge ProductStock --fields="product_id:unsignedBigInteger:index,quantity:decimal(12,3),location:string:nullable"

# Enum with nullable
php artisan ptah:forge Order --fields="status:enum(pending|processing|shipped|delivered):default(pending),total:decimal(10,2)"

# Read from existing database
php artisan ptah:forge Customer --db --table=customers

# Generate complete API without web
php artisan ptah:forge Product --api-only --fields="name:string,price:decimal(10,2)"
```

**Next steps after scaffold:**

1. Run migration: ``php artisan migrate``
2. Adjust the CRUD JSON configuration in the ``crud_configs`` table
3. Implement business rules in ``{Entity}Service.php``
4. Add custom validations in Requests
5. Write tests in ``tests/Feature/{Entity}Test.php``

---

## ptah:module

**Description:** Enables optional Ptah modules.

**Usage:**
```bash
# Interactive (selection menu)
php artisan ptah:module

# Direct
php artisan ptah:module auth
php artisan ptah:module menu
php artisan ptah:module company
php artisan ptah:module permissions
php artisan ptah:module api

# List available modules and states
php artisan ptah:module --list

# Force overwrite
php artisan ptah:module auth --force
```

**Options:**
- ``--list`` — Lists available modules and their states (enabled/disabled)
- ``--force`` — Overwrites existing files when publishing

**Available modules:**

### 1. auth
**What it does:**
- Publishes the 2FA migration
- Runs migrations
- Activates authentication with login, password recovery, 2FA (TOTP and Email)

**ENV:**
```env
PTAH_MODULE_AUTH=true
```

**Created routes:**
- ``/auth/login`` — Login
- ``/auth/forgot-password`` — Password recovery
- ``/auth/reset-password/{token}`` — Reset password
- ``/auth/two-factor-challenge`` — 2FA verification
- ``/auth/profile`` — User profile

**Published files:**
- ``database/migrations/*_add_two_factor_fields_to_users_table.php``

---

### 2. menu
**What it does:**
- Publishes the menus migration
- Runs migrations
- Activates dynamic sidebar (menu configurable via database)

**ENV:**
```env
PTAH_MODULE_MENU=true
```

**Created table:**
- ``ptah_menu_items`` — Hierarchical menu items

**Components:**
- Livewire: ``MenuList`` — Menu item management
- Blade component: ``<x-ptah::menu />`` — Renders the menu in the sidebar

---

### 3. company
**What it does:**
- Publishes company migrations
- Runs migrations
- Default company seeders
- Activates multi-company system (multi-tenancy)

**ENV:**
```env
PTAH_MODULE_COMPANY=true
```

**Created tables:**
- ``ptah_companies`` — Companies
- ``ptah_company_user`` — User-company pivot

**Components:**
- Livewire: ``CompanyList`` — Company management
- Livewire: ``CompanySwitcher`` — Active company switcher (header dropdown)

**Published files:**
- ``database/migrations/*_create_ptah_companies_table.php``
- ``database/migrations/*_create_ptah_company_user_table.php``

---

### 4. permissions
**What it does:**
- Publishes permissions migrations
- Runs migrations
- Default admin seed with MASTER role
- Activates RBAC (Role-Based Access Control)

**Dependency:** Requires the ``company`` module enabled (activates automatically if not)

**ENV:**
```env
PTAH_MODULE_PERMISSIONS=true
```

**Created tables:**
- ``ptah_roles`` — Access profiles
- ``ptah_role_user`` — User-role pivot
- ``ptah_departments`` — Departments
- ``ptah_pages`` — System pages/objects
- ``ptah_page_role`` — Permissions (CRUD per page and role)
- ``ptah_user_permissions`` — User-specific permissions
- ``ptah_audit_logs`` — Audit logs

**Admin credentials:**
- E-mail: ``admin@admin.com`` (configurable in ``config/ptah.php``)
- Password: ``admin@123`` (configurable in ``config/ptah.php``)
- Role: MASTER (all permissions)

**Components:**
- Livewire: ``RoleList`` — Role management
- Livewire: ``DepartmentList`` — Department management
- Livewire: ``PageList`` — Page management
- Livewire: ``UserPermissionList`` — User permissions
- Livewire: ``AuditList`` — Audit logs
- Livewire: ``PermissionGuide`` — Interactive permissions guide

**Helpers:**
- ``ptah_can($page, $action, $user, $companyId)`` — Checks permission
- ``ptah_is_master($user)`` — Checks if user is MASTER
- ``@ptahCan('sales', 'create')`` — Blade directive
- ``@ptahMaster`` — Blade directive

**Published files:**
- ``database/migrations/*_create_ptah_permissions_tables.php``

---

### 5. api
**What it does:**
- Installs ``darkaonline/l5-swagger`` via Composer
- Publishes API base classes
- Publishes the L5-Swagger configuration
- Configures Swagger UI at ``/api/documentation``

**ENV:**
```env
PTAH_MODULE_API=true
```

**Published files:**
- ``app/Responses/BaseResponse.php`` — Standardized API response
- ``app/Http/Controllers/API/BaseApiController.php`` — Base controller with helpers
- ``app/Http/Controllers/API/SwaggerInfo.php`` — Swagger ``@OA\Info`` annotations
- ``config/l5-swagger.php`` — L5-Swagger configuration

**Created routes:**
- ``GET /api/documentation`` — Interactive Swagger UI
- ``GET /api/documentation.json`` — OpenAPI specification

**Usage after installation:**

```bash
# Generate Swagger documentation
php artisan l5-swagger:generate

# Access UI
http://localhost/api/documentation
```

**Next steps:**
1. Visit ``/api/documentation`` to see the Swagger UI
2. Regenerate docs after creating APIs: ``php artisan l5-swagger:generate``
3. Adjust scan path in ``config/l5-swagger.php`` if needed

---

## ptah:config

**Description:** Configures CRUD settings for a model via command line (alternative to the visual modal).

> 📘 **Full Documentation:** For a detailed configuration guide (visual modal + CLI), practical examples, comparisons and troubleshooting, see [**Configuration.md**](Configuration.md).

**Usage:**
```bash
# Interactive mode (wizard with questions)
php artisan ptah:config "App\Models\Product"

# Declarative mode (inline syntax)
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name:validation=required|max:255" \
  --column="price:number:required:label=Price:mask=money_brl:renderer=money" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red" \
  --action="approve:livewire:approve(%id%):icon=bx-check:color=success" \
  --filter="status:select:=:options=active,inactive" \
  --set="cacheEnabled=true" \
  --set="itemsPerPage=25"

# List current configuration
php artisan ptah:config "App\Models\Product" --list

# Reset configuration to defaults
php artisan ptah:config "App\Models\Product" --reset

# Import from JSON file
php artisan ptah:config "App\Models\Product" --import=config.json

# Export to JSON file
php artisan ptah:config "App\Models\Product" --export=product-config.json

# Non-interactive mode (skip wizard)
php artisan ptah:config "App\Models\Product" --non-interactive \
  --column="name:text:required"

# Dry-run (show changes without saving)
php artisan ptah:config "App\Models\Product" \
  --column="name:text" \
  --dry-run

# Process only specific sections
php artisan ptah:config "App\Models\Product" \
  --only=columns,actions \
  --column="name:text"

# Skip specific sections
php artisan ptah:config "App\Models\Product" \
  --skip=styles,joins \
  --column="name:text"

# Force overwrite existing config
php artisan ptah:config "App\Models\Product" \
  --column="name:text" \
  --force
```

**Options:**
- ``{model}`` — Full model class name (e.g., ``App\Models\Product``)
- ``--column=*`` — Add/update column: ``field:type:modifier:option=value``
- ``--action=*`` — Add custom action: ``name:type:value:icon=icon:color=color``
- ``--filter=*`` — Add custom filter: ``field:type:operator:label=Label``
- ``--style=*`` — Add style rule: ``field:operator:value:css``
- ``--join=*`` — Add table join: ``type:table:on:select=field1,field2``
- ``--set=*`` — Set general config: ``key=value``
- ``--permission=*`` — Set permission: ``action=permission``
- ``--route=`` — Route path to scope this config (empty = global/default). When provided, the config is saved for that specific URL path only. `CrudConfigService` falls back to the global config if no route-specific entry is found. See [Multi-Config per Route](BaseCrud.md#multi-config-per-route)
- ``--list`` — List current configuration (beautiful table format)
- ``--reset`` — Reset configuration to defaults
- ``--import=`` — Import configuration from JSON file
- ``--export=`` — Export configuration to JSON file
- ``--non-interactive`` — Skip wizard questions, use only provided options
- ``--force`` — Force overwrite existing configuration
- ``--dry-run`` — Show what would be changed without saving
- ``--only=*`` — Process only specific sections (columns,actions,filters,styles,joins,general,permissions)
- ``--skip=*`` — Skip specific sections

**Column Syntax (--column):**

```bash
# Basic format
field:type:modifier:option=value

# Examples
name:text:required:label=Name
email:text:required:validation=email|max:255
price:number:label=Price:mask=money_brl:renderer=money:rendererDecimals=2
status:select:options=active,inactive:renderer=badge:badges=active:green,inactive:red
user_id:searchdropdown:relation=user:sdSelectColumn=name:sdValueColumn=id
description:textarea:optional:placeholder=Enter description
active:boolean:default=true
created_at:datetime:readonly:renderer=datetime:rendererFormat=d/m/Y H:i:s

# Modifiers (shorthands)
required    → colsRequired = true
optional    → colsRequired = false
readonly    → colsEditableForm = false
hidden      → colsVisibleList = false
noFilter    → colsIsFilterable = false
noSave      → colsGravar = false
total       → colsTotal = true (add to totalizer)

# Column options (option=value)
label           → colsNomeLogico (display label)
help            → colsHelpText (help text below field)
placeholder     → colsPlaceholder
default         → colsDefaultValue
align           → colsAlign (text-start, text-center, text-end)
width           → colsWidth (120px, 20%, auto)
renderer        → colsRenderer (text, badge, pill, boolean, money, date, datetime, link, image, etc.)
rendererLink    → colsRendererLink (URL pattern for link renderer)
rendererTarget  → colsRendererTarget (_self, _blank)
rendererCurrency→ colsRendererCurrency (BRL, USD, EUR)
rendererDecimals→ colsRendererDecimals (2, 0)
rendererPrefix  → colsRendererPrefix (prefix for number renderer)
rendererSuffix  → colsRendererSuffix (suffix for number renderer)
badges          → colsRendererBadges (value:color pairs, e.g., active:green,inactive:red)
mask            → colsMask (money_brl, cpf, cnpj, phone, cep, date, etc.)
maskTransform   → colsMaskTransform (money_to_float, digits_only, etc.)
validation      → colsValidation (Laravel validation rules: required|email|max:255)
options         → colsOptions (for select: value1:Label1,value2:Label2 or value1,value2)
relation        → colsRelation (relation method name)
sdTable         → colsSdTable (table for searchdropdown)
sdSelectColumn  → colsSdSelectColumn (display column for searchdropdown)
sdValueColumn   → colsSdValueColumn (value column for searchdropdown)
uploadPath      → colsUploadPath (path for file uploads)
totalizer       → colsTotal (add to totalizer)
totalizadorType → totalizadorType (sum, avg, count, min, max)
```

**Action Syntax (--action):**

```bash
# Format
name:type:value:icon=icon:color=color

# Examples
approve:livewire:approve(%id%):icon=bx-check:color=success
reject:livewire:reject(%id%):icon=bx-x:color=danger
view:link:https://example.com/view/%id%:icon=bx-show:color=primary
export:javascript:exportData():icon=bx-download:color=info
```

**Filter Syntax (--filter):**

```bash
# Format
field:type:operator:label=Label

# Examples
status:select:=:label=Status:options=active,inactive
price:number:>=:label=Minimum Price
created_at:date:>=:label=From Date
user_id:searchdropdown:=:sdTable=users:sdSelectColumn=name
```

**Style Syntax (--style):**

```bash
# Format
field:operator:value:background=color:color=textColor

# Examples
status:==:cancelled:background=#FEE:color=#C00
priority:>:5:background=#FFE:fontWeight=bold
```

**Join Syntax (--join):**

```bash
# Format
type:table:leftColumn=rightColumn:select=field1,field2

# Examples
left:users:products.user_id=users.id:select=name,email
inner:categories:products.category_id=categories.id:select=name
```

**General Settings (--set):**

```bash
# Examples
--set="cacheEnabled=true"
--set="cacheTime=60"
--set="paginationEnabled=true"
--set="itemsPerPage=25"
--set="searchEnabled=true"
--set="exportEnabled=true"
--set="softDeletes=true"
--set="theme=dark"
--set="compactMode=false"
```

**Permissions (--permission):**

```bash
# Examples
--permission="list=product.index"
--permission="create=product.create"
--permission="edit=product.update"
--permission="delete=product.destroy"
```

**Workflow Examples:**

```bash
# 1. Interactive wizard (recommended for first-time config)
php artisan ptah:config "App\Models\Product"
# Answer questions step-by-step with smart suggestions

# 2. Quick declarative setup
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name" \
  --column="sku:text:required:label=SKU:validation=required|unique:products,sku" \
  --column="price:number:required:mask=money_brl:renderer=money" \
  --column="stock:number:label=Stock:renderer=number:rendererDecimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red" \
  --column="category_id:searchdropdown:relation=category:sdSelectColumn=name" \
  --set="itemsPerPage=25" \
  --set="cacheEnabled=true"

# 3. View current config
php artisan ptah:config "App\Models\Product" --list

# 4. Add more columns later
php artisan ptah:config "App\Models\Product" \
  --column="description:textarea:optional" \
  --column="image:file:uploadPath=products"

# 5. Export for backup or sharing
php artisan ptah:config "App\Models\Product" --export=product-config.json

# 6. Import in another environment
php artisan ptah:config "App\Models\Product" --import=product-config.json

# 7. Reset to defaults
php artisan ptah:config "App\Models\Product" --reset

# 8. Create a route-specific config (same model, different columns per URL path)
php artisan ptah:config "App\Models\Product" \
  --route="admin/products" \
  --column="name:text:required" \
  --column="price:number:required" \
  --column="status:select:options=active,inactive:renderer=badge"

# 9. Read-only variant for another route
php artisan ptah:config "App\Models\Product" \
  --route="sales/products" \
  --column="name:text:readonly" \
  --column="price:number:readonly"
```

**Benefits of CLI Configuration:**

✅ **Automation** — Integrate with CI/CD pipelines  
✅ **Version Control** — Export configs to JSON and commit  
✅ **Batch Operations** — Configure multiple models via scripts  
✅ **Reproducibility** — Share configs across teams/environments  
✅ **Speed** — Faster than clicking through modal UI  
✅ **Testability** — Script config changes with --dry-run  
✅ **Introspection** — Smart suggestions based on model metadata  

**Where configs are stored:**

Configurations are saved in ``crud_configs`` table:
- ``model`` — Full model class name
- ``config`` — JSON configuration
- ``updated_at`` — Last modified timestamp

Cache is automatically cleared after saving.

**Next Steps:**

1. Configure your first model: ``php artisan ptah:config "App\Models\YourModel"``
2. View configuration: ``php artisan ptah:config "App\Models\YourModel" --list``
3. Refresh browser to see changes in CRUD interface
4. Export for backup: ``php artisan ptah:config "App\Models\YourModel" --export=backup.json``

---

## vendor:publish (tags ptah)

Ptah exposes several groups of publishable files via ``vendor:publish``. Each tag is independent and optional — publish only what you need.

| Tag | What it publishes | Destination |
|-----|--------------|--------|
| ``ptah-config`` | Configuration file | ``config/ptah.php`` |
| ``ptah-stubs`` | Customizable scaffold stubs | ``stubs/ptah/`` |
| ``ptah-migrations`` | All package migrations | ``database/migrations/`` |
| ``ptah-lang`` | Translations (pt_BR and en) | ``lang/vendor/ptah/`` |
| ``ptah-views`` | Blade views (for customization) | ``resources/views/vendor/ptah/`` |
| ``ptah-assets`` | Forge CSS | ``resources/css/vendor/ptah/`` |
| ``ptah-menu-registry`` | MenuRegistry.php (auto-menu) | ``database/seeders/MenuRegistry.php`` |
| ``ptah-api`` | BaseResponse, BaseApiController, SwaggerInfo | ``app/Responses/``, ``app/Http/Controllers/API/`` |
| ``ptah-auth`` | 2FA migration | ``database/migrations/`` |
| ``ptah-menu`` | Menus migration | ``database/migrations/`` |
| ``ptah-company`` | Company migrations | ``database/migrations/`` |
| ``ptah-permissions`` | Permissions migrations | ``database/migrations/`` |
| ``ptah-docker`` | Complete Docker environment | project root |

**Usage:**

```bash
# Publish specific group
php artisan vendor:publish --tag=ptah-config
php artisan vendor:publish --tag=ptah-stubs
php artisan vendor:publish --tag=ptah-docker

# Force overwrite de arquivos existentes
php artisan vendor:publish --tag=ptah-config --force

# View all package publishables
php artisan vendor:publish --list | grep ptah
```

### ptah-docker — Details

Publishes a ready-to-use Docker structure with PHP 8.3, Nginx, MySQL 8, Redis and Mailpit:

```bash
php artisan vendor:publish --tag=ptah-docker
```

**Published files:**

```
├── docker-compose.yml           # 5 orchestrated services
├── .env.docker                  # pre-configured .env for Docker
├── .dockerignore                # Optimized build context
└── docker/
    ├── php/
    │   ├── Dockerfile           # PHP 8.3-FPM Alpine + Node.js + Redis ext
    │   └── php.ini              # Settings (timezone BR, limits, opcache)
    └── nginx/
        └── default.conf         # Virtual host with gzip + PHP-FPM
```

**Services available after `docker compose up`:**

| Service | Default access | Description |
|---------|-------------|----------|
| App (PHP-FPM) | — | PHP 8.3 + Node.js |
| Nginx | ``http://localhost:8080`` | Web server |
| MySQL 8 | ``localhost:3307`` | Database |
| Redis 7 | ``localhost:6380`` | Cache / queues / sessions |
| Mailpit | ``http://localhost:8025`` | Dev email capture |

**Customizable ports via variables in `.env.docker`:**

```env
NGINX_PORT=8080
DB_PORT_HOST=3307
REDIS_PORT_HOST=6380
MAIL_UI_PORT=8025
MAIL_SMTP_PORT=1025
```

> **Note:** Docker is entirely optional. Ptah works normally without it — on Herd, Valet, Sail or any PHP 8.2+ server.

---

## Recommended Installation Order

### Without Docker (Herd, Valet, XAMPP)

```bash
# 1. Install basic package
composer require jonytonet/ptah
php artisan ptah:install

# 2. Enable required modules
php artisan ptah:module company
php artisan ptah:module permissions
php artisan ptah:module auth
php artisan ptah:module menu

# 3. (Optional) Enable API module
php artisan ptah:module api

# 4. (Optional) Demo data to explore
php artisan ptah:install --demo

# 5. Scaffold first entity
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"

# 6. Run migration
php artisan migrate

# 7. Access system
# http://localhost/products
```

### With Docker

```bash
# 1. Install package
composer require jonytonet/ptah
php artisan ptah:install --skip-npm  # skip npm since it will be run inside the container

# 2. Publish Docker environment
php artisan vendor:publish --tag=ptah-docker

# 3. Copy .env.docker as .env and adjust if needed
cp .env.docker .env

# 4. Start containers
docker compose up -d

# 5. Install dependencies and configure app inside container
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan ptah:install --force --skip-npm
docker compose exec app npm install
docker compose exec app npm run build

# 6. Enable modules
docker compose exec app php artisan ptah:module company
docker compose exec app php artisan ptah:module permissions
docker compose exec app php artisan ptah:module auth
docker compose exec app php artisan ptah:module menu

# 7. Access system
# http://localhost:8080
# Mailpit: http://localhost:8025
```

---

## Usage Tips

### Incremental Scaffolding

```bash
# Web first
php artisan ptah:forge Product

# Then add API (does not overwrite existing files)
php artisan ptah:forge Product --api --force
```

### Subfolders (organization)

```bash
# Structure: Purchase/Order, Purchase/OrderItem
php artisan ptah:forge Purchase/Order
php artisan ptah:forge Purchase/OrderItem

# Result:
# app/Models/Purchase/Order.php
# app/Services/Purchase/OrderService.php
# resources/views/purchase/order/index.blade.php
```

### Reading from existing database

```bash
# If the table already exists in the database
php artisan ptah:forge Customer --db --table=customers
```

This inspects the structure via ``INFORMATION_SCHEMA`` and generates compatible Models/DTOs/Migrations.

---

## Troubleshooting

### Error: "Model not found"
**Cause:** Repository binding not registered.

**Solution:** Add to ``AppServiceProvider::boot()``:
```php
$this->app->bind(
    \App\Repositories\Contracts\ProductRepositoryInterface::class,
    \App\Repositories\ProductRepository::class
);
```

### Error: "Class not found" after scaffold
**Cause:** Autoload not updated.

**Solution:**
```bash
composer dump-autoload
```

### Error: npm/yarn not found
**Cause:** Node.js not installed or not in PATH.

**Solution:**
1. Install Node.js: https://nodejs.org
2. Or use ``--skip-npm`` and run manually afterwards:
```bash
npm install
npm run build
```

### Duplicate Migrations
**Cause:** Re-running ``ptah:install`` or ``ptah:forge``.

**Solution:** Use ``--force`` only when you really want to overwrite. For modules, check with ``ptah:module --list`` first.

---

## Command History

### Removed Commands (V2.2+)

These commands were discontinued and replaced by ``ptah:forge``:

| Removed command | Replacement |
|------------------|--------------|
| ``ptah:make-api {Entity}`` | ``ptah:forge {Entity} --api-only`` |
| ``ptah:docs {Entity}`` | Swagger gerado automaticamente via ``ptah:forge --api`` |

**Migration:**

```bash
# ❌ Before (V2.1)
php artisan ptah:make Product        # Web
php artisan ptah:make-api Product    # API
php artisan ptah:docs Product        # Manual Swagger

# ✅ Now (V2.2+)
php artisan ptah:forge Product              # Web
php artisan ptah:forge Product --api        # Web + API
php artisan ptah:forge Product --api-only   # Only API
# Swagger generated automatically
```

---

## Performance

### Slow command: ptah:install --boost
**Cause:** ``composer require laravel/boost`` can take 1-2 minutes.

**Solution:** This is normal. Laravel Boost installs heavy dependencies (AST parsers). Use ``--skip-npm`` to skip Node if you already have built assets.

### Slow command: ptah:forge --db
**Cause:** INFORMATION_SCHEMA query can be slow on large databases.

**Solution:** Use manual ``--fields`` for known tables:
```bash
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"
```

---

## ptah:hooks

**Description:** Generates a Lifecycle Hooks class for the BaseCrud.

**Usage:**
```bash
# Basic
php artisan ptah:hooks ProductHooks

# With subdirectory
php artisan ptah:hooks Inventory/StockHooks

# Overwrite existing file
php artisan ptah:hooks ProductHooks --force
```

**Options:**
- `--force` — Overwrites the existing file without asking for confirmation

**What it does:**

Creates `app/CrudHooks/{Name}.php` implementing `Ptah\Contracts\CrudHooksInterface` with the 4 pre-filled lifecycle methods:

```php
namespace App\CrudHooks;

use Ptah\Contracts\CrudHooksInterface;
use Illuminate\Database\Eloquent\Model;

class ProductHooks implements CrudHooksInterface
{
    public function beforeCreate(array &$data, ?Model $record, object $component): void
    {
        // Executed before creating the record
    }

    public function afterCreate(array &$data, Model $record, object $component): void
    {
        // Executed after creating the record
    }

    public function beforeUpdate(array &$data, Model $record, object $component): void
    {
        // Executed before updating the record
    }

    public function afterUpdate(array &$data, Model $record, object $component): void
    {
        // Executed after updating the record
    }
}
```

**Next steps:**

1. Implement the desired logic in the methods in `app/CrudHooks/{Name}.php`
2. In CrudConfig, associate the hook to a field using the `@ProductHooks` syntax
3. See [Configuration.md](Configuration.md) for details on Lifecycle Hooks

> ⚠️ **Warning:** The `$component` parameter exposes the full Livewire component. Use it only for reading properties, never for dispatching arbitrary actions from external data.

---

## References

- [InstallationGuide.md](InstallationGuide.md) — Complete installation guide
- [BaseCrud.md](BaseCrud.md) — BaseCrud reference
- [Modules.md](Modules.md) — Module details
- [AI_Guide.md](AI_Guide.md) — Prompts for AI agents
- [Permissions.md](Permissions.md) — Detailed RBAC system

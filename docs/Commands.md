# Ptah Commands

This document lists all Artisan commands available in the Ptah package.

---

## Table of Contents

1. [ptah:install](#ptahinstall)
2. [ptah:forge](#ptahforge)
3. [ptah:module](#ptahmodule)
4. [ptah:config](#ptahconfig)
5. [ptah:config:doctor](#ptahconfigdoctor)
6. [ptah:config:export-all / import-all](#ptahconfigexport-all--import-all)
7. [ptah:permission:sync](#ptahpermissionsync)
8. [ptah:permission:why](#ptahpermissionwhy)
9. [ptah:config:relabel](#ptahconfigrelabel)
10. [ptah:export-prune](#ptahexport-prune)
11. [ptah:audit-prune](#ptahaudit-prune)
12. [ptah:hooks](#ptahhooks)
13. [ptah:menu-sync](#ptahmenu-sync)
14. [Opt-in installers (ptah:history:install, ptah:attachments:install, ptah:settings:install, ptah:preferences:realign)](#opt-in-installers)
15. [vendor:publish (tags ptah)](#vendorpublish-tags-ptah)
16. [Agent tools (ptah:map, ptah:check, ptah:field, ptah:blueprint, ptah:last-error, ptah:upgrade-check, ptah:docs)](#agent-tools)

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
- Password: set ``PTAH_ADMIN_PASSWORD`` in ``.env``. If left unset, a strong random password is generated and printed **once** during installation — copy it then. There is no fixed default password.

**Next steps after installation:**

1. Review ``config/ptah.php``
2. *(Optional)* Add the ``HasUserPreferences`` trait to the User model for the ``$user->getPreference()``/``setPreference()`` API — BaseCrud persists preferences without it
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
- ``--db`` — Read fields directly from the database (via ``Schema::getColumns()``)
- ``--api`` — Generates web + API together (API Controller, API Requests, Swagger, v1 routes)
- ``--api-only`` — Generates ONLY API, without web views (Livewire not created)
- ``--no-soft-deletes`` — Does not add SoftDeletes to the model. Without it, ``ptah.crud.soft_deletes`` (default ``true``) decides
- ``--no-menu`` — Does not add the entity to ``MenuRegistry.php``
- ``--factory`` — Also generates a model factory and a demo seeder from the field types
- ``--force`` — Overwrites existing files (all artifacts except an existing migration). In an interactive session it asks for confirmation first; in scripts it does not

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
| Controller API | ``app/Http/Controllers/API/{Entity}ApiController.php`` | API Controller with Swagger annotations |
| Request Create API | ``app/Http/Requests/API/Create{Entity}ApiRequest.php`` | API create validation |
| Request Update API | ``app/Http/Requests/API/Update{Entity}ApiRequest.php`` | API update validation |
| Route API | ``routes/api.php`` | API v1 routes |

> **Note:** With ``--api``, generates **web + API**. With ``--api-only``, generates **only API** (no views, no Livewire).

**Fields syntax:**

```bash
--fields="campo1:tipo:modificador1:modificador2,campo2:tipo"
```

> **PowerShell:** always quote the whole value. Unquoted, PowerShell splits
> the argument at every comma — `decimal(5,2)` arrives cut in two and the
> migration gets `decimal('x', 0, 2)`. With quotes, ptah splits only on the
> commas outside parentheses.

**Available types:**
- ``string(60)``, ``char(2)``, ``text``, ``longText``
- ``integer``, ``bigInteger``, ``mediumInteger``, ``smallInteger``, ``tinyInteger``
- ``unsignedInteger``, ``unsignedBigInteger``, ``unsignedMediumInteger``, ``unsignedSmallInteger``, ``unsignedTinyInteger``, ``foreignId``
- ``decimal(10,2)``, ``float``, ``double``
- ``boolean``, ``date``, ``datetime``, ``timestamp``
- ``enum(value1|value2|value3)``
- ``json`` (alias ``jsonb``)

Case does not matter. **An unknown type is refused** (1.47.0) — the command
stops and lists the valid ones. It used to become a ``string`` column, a
``string`` cast and a ``string`` DTO property without a word.

**Modifiers:**
- ``nullable`` — Allows NULL
- ``unique`` — Unique index
- ``index`` — Simple index (documented for long, honoured since 1.43.3; skipped with ``unique``, which indexes already)
- ``default(value)`` — Default value
- ``surname=Label`` (alias: ``label=Label``) — Display label shown in the BaseCrud screen for this column. Without it, the label is derived from the field name.

**Examples:**

```bash
# Basic e-commerce
php artisan ptah:forge Product --fields="name:string,description:text:nullable,price:decimal(10,2),stock:integer:default(0),is_active:boolean:default(true)"

# With custom display labels (surname=)
php artisan ptah:forge Product --fields="name:string:surname=Product name,price:decimal(10,2):surname=Unit price"

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
php artisan ptah:module ai_agent

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

**Created routes** (under ``ptah.auth.route_prefix`` — env ``PTAH_AUTH_ROUTE_PREFIX``, empty by default):
- ``/login`` — Login
- ``/forgot-password`` — Password recovery
- ``/reset-password/{token}`` — Reset password
- ``/two-factor-challenge`` — 2FA verification
- ``/profile`` — User profile

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
- ``menus`` — Hierarchical menu items

**Components:**
- Livewire: ``MenuList`` — Menu item management
- Blade component: ``<x-forge-sidebar />`` — Renders the menu in the sidebar (used by ``forge-dashboard-layout``)

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
- ``ptah_departments`` — Departments

**Components:**
- Livewire: ``CompanyList`` — Company management
- Livewire: ``CompanySwitcher`` — Active company switcher (header dropdown)

**Published files:**
- ``database/migrations/*_create_ptah_companies_table.php``
- ``database/migrations/*_create_ptah_departments_table.php``

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
- ``ptah_pages`` — System pages
- ``ptah_page_objects`` — Objects (``obj_key``) registered under each page
- ``ptah_role_permissions`` — Permissions (create/read/update/delete flags per object and role)
- ``ptah_user_roles`` — User-role pivot
- ``ptah_permission_audits`` — Permission audit log

**Admin credentials:**
- E-mail: ``admin@admin.com`` (configurable in ``config/ptah.php``)
- Password: from ``PTAH_ADMIN_PASSWORD``; if unset, a strong random password is generated and shown once at install time (no fixed default)
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

**Published files:** the 6 migrations of the ``ptah-permissions`` tag
(``*_create_ptah_roles_table.php``, ``*_create_ptah_pages_table.php``,
``*_create_ptah_page_objects_table.php``, ``*_create_ptah_role_permissions_table.php``,
``*_create_ptah_user_roles_table.php``, ``*_create_ptah_permission_audits_table.php``)

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
- ``app/Http/Controllers/API/SwaggerInfo.php`` — Swagger ``#[OA\Info]`` attributes (PHP attributes since 1.44.0 — swagger-php 4+)
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

### 6. ai_agent
**What it does:**
- Installs ``prism-php/prism`` via Composer (skipped if already present)
- Publishes the AI Agent migrations (tag ``ptah-ai-agent``)
- Runs migrations

**ENV:**
```env
PTAH_MODULE_AI_AGENT=true
```

**Next steps:** configure the provider and model at ``/ptah-ai/models``; the chat widget activates once a configuration is set as default. Full reference: [AiAgent.md](AiAgent.md).

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
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --action="approve:livewire:approve(%id%):icon=bx-check:color=success" \
  --filter="status:select:label=Status:operator==:options=active,inactive" \
  --set="itemsPerPage=25"

# List current configuration
php artisan ptah:config "App\Models\Product" --list

# DELETE the stored configuration (asks first, no undo)
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

# Process only specific sections (comma-separated or repeated: --only=columns --only=actions)
php artisan ptah:config "App\Models\Product" \
  --only=columns,actions \
  --column="name:text"

# Skip specific sections
php artisan ptah:config "App\Models\Product" \
  --skip=styles,joins \
  --column="name:text"
```

**Options:**
- ``{model}`` — Full model class name (e.g., ``App\Models\Product``)
- ``--column=*`` — Add/update column: ``field:type:modifier:option=value``
- ``--action=*`` — Add custom action: ``name:type:value:icon=icon:color=color``
- ``--column=*`` — Add **or update** a column. Matching is by field name, so
  re-running it for a field that already has a column MERGES into that column
  instead of appending a second one (which is what it did before 1.30.2, leaving
  two entries for the same field and letting iteration order decide which won).
  A merge only applies the keys the definition actually names, so a label set
  through the visual editor survives a CLI call that changes only the type. The
  command reports ``added`` or ``updated`` per column.

  **``options=`` for a select** accepts either form, and both normalise to the
  ``colsSelect`` map (``label => value``) that the modal form and the filter
  panel read:

  | Form | Result |
  |---|---|
  | ``options=open:Aberto,closed:Fechado`` | ``{"Aberto":"open","Fechado":"closed"}`` |
  | ``options=open,in_progress,resolved`` | ``{"Open":"open","In Progress":"in_progress",…}`` — label humanised |

  Only the FIRST colon in an entry splits, so a label may contain one
  (``urgent:Urgente: agora``). ``|`` is **not** an options separator — that
  character belongs to ``badges=``, where it means ``value|color|label``. Until
  1.30.2 the raw string was stored instead of the map, so a CLI-configured
  select rendered a single option labelled ``0``; ``ptah:config:doctor --fix``
  migrates configs saved before that.

- ``--filter=*`` — Add custom filter: ``field:type:label=Label:operator==:options=value``  (no positional operator — everything after ``field:type`` is ``key=value``). Persisted under ``customFilters``, the section ``FilterService::processCustomFilters()`` reads; every writer is normalised through ``Ptah\Support\FilterRule``. Configs still carrying the pre-1.28.0 ``filters`` section are inert until ``ptah:config:doctor --fix`` migrates them.
- ``--style=*`` — Add style rule: ``field:condition:value:style`` (``condition`` is ``==``/``!=``/``>``/``<``/``>=``/``<=`` or the aliases ``eq``/``ne``/``lt``/``gt``/``lte``/``gte``/``=``; ``style`` is an inline CSS declaration list, e.g. ``background:#FEE2E2;color:#991B1B;``). **If the VALUE contains a ``:``**, use the long form ``field:condition:value:style=<css>`` — the ``style=`` marker ends the value explicitly, e.g. ``start_at:==:12:30:style=background:#eee;``. Without it the value is cut at its first colon and the remainder leaks into the CSS, silently.
- ``--join=*`` — Add table join: ``type:table:first=second:select=col:alias,col2 as alias2:distinct`` (see Join Syntax below). Persisted as ``{type, table, first, second, distinct, select:[{column, alias}]}``
- ``--set=*`` — Set general config: ``key=value``
- ``--permission=*`` — Set permission: ``action=permission``
- ``--route=`` — Route path to scope this config (empty = global/default). When provided, the config is saved for that specific URL path only. `CrudConfigService` falls back to the global config if no route-specific entry is found. See [Multi-Config per Route](BaseCrud.md#multi-config-per-route)
- ``--list`` — List current configuration (beautiful table format)
- ``--reset`` — **Deletes** the stored configuration row (global, or the one of ``--route``) after a confirmation. There is no undo — ``--export`` first
- ``--import=`` — Import configuration from JSON file
- ``--export=`` — Export configuration to JSON file
- ``--non-interactive`` — Skip wizard questions, use only provided options
- ``--force`` — No effect (kept so existing scripts do not break); the command warns when it is passed. It always merges into the existing config, and ``--reset`` always asks
- ``--dry-run`` — Show what would be changed without saving
- ``--only=*`` — Process only specific sections (columns,actions,filters,styles,joins,general,permissions). Comma-separated or repeated; an unknown section name is an error
- ``--skip=*`` — Skip specific sections (same format as ``--only``)

> **Model key (since v1.5.0):** the `{model}` argument accepts either the FQCN
> (`App\Models\Catalog\Product`) **or** the runtime key (`Catalog/Product`). The
> config is always stored under the **canonical** key BaseCrud reads
> (`Catalog/Product`) — so passing the FQCN no longer creates an orphan row the
> runtime never loads. Run `ptah:config:doctor` to detect/repair legacy orphans.

## ptah:config:doctor

Audits **all** `crud_configs` and reports the silent failures the per-model
tooling can't see. Exit code is non-zero when any error is found (CI-friendly).

```bash
php artisan ptah:config:doctor          # report only
php artisan ptah:config:doctor --fix    # also rewrite orphan (non-canonical) keys
```

Detects: **orphan keys** (stored under a non-canonical key — e.g. an FQCN — that
the runtime never reads; `--fix` rewrites them), **unresolved models**, **malformed
configs** (via `ConfigSchemaValidator`), **empty screens** (0 columns),
**route ambiguity** (a model with both a global and a route-specific config), and
**legacy RBAC key** — a config whose gate identifier is stored under `permissions.identifier`
while the runtime reads `permissions.permissionIdentifier`, so the screen is
**silently ungated**; `--fix` migrates the key (see the security note below).

Also checks (1.50.0): a `cols*` column key nothing reads; an invalid `colsHelper`;
a `unique:` validation rule with no matching model or table (duplicates are accepted);
a class-based hook outside `ptah.crud.hook_namespaces` (**error** — it never runs);
a service-mode searchdropdown whose class does not implement `SearchDropdownSource`
(**error**); a bulk action whose handler does not implement `BulkActionHandler` (**error**).

**A config that is not saved yet (1.52.0)** — the same checks on one JSON, without
touching the database. For a config written by hand (or by an agent) before it is
imported; `--fix` is refused in this mode.

```bash
php artisan ptah:config:doctor --file=product.json --model=Product
php artisan ptah:config:doctor --file=database/ptah/crud-configs/Product.json   # export envelope: model comes from it
php artisan ptah:config:doctor --config='{"cols":[...]}' --model=Product --route=admin.products
```

## ptah:config:export-all / import-all

Version and rebuild the **whole** config set (the configs live only in the DB).

```bash
# Snapshot every config to a versionable directory (one JSON per model/route)
php artisan ptah:config:export-all              # → database/ptah/crud-configs
php artisan ptah:config:export-all storage/cfg  # custom path

# Rebuild them (idempotent — doubles as a seeding step on a fresh DB)
php artisan ptah:config:import-all
```

Each file carries its own `model` / `route` (the filename is only for git
readability), and keys are canonicalised on import — a legacy FQCN export can't
reintroduce an orphan. Commit `database/ptah/crud-configs/` to review config
changes in diffs.

## ptah:permission:sync

Registers the RBAC objects (`PtahPage` + `PageObject`) that the permission engine
matches against, **derived from the existing `crud_configs`** — so delegating access
to non-master roles no longer requires seeding `page_object` rows by hand, screen by
screen. Idempotent (re-runs never duplicate).

```bash
# Preview what would be created (read-only)
php artisan ptah:permission:sync --dry-run

# Create the page objects for every configured screen
php artisan ptah:permission:sync

# …and grant a role access in one step
php artisan ptah:permission:sync --role="Estoquista" --grant=read,update
php artisan ptah:permission:sync --role="Gerente"    --grant=all

# …only on specific screens (by permission key; default: every synced screen)
php artisan ptah:permission:sync --role="Estoquista" --grant=read --key=products.index --key=stock.index
```

For each config it resolves the canonical RBAC key (`permissions.permissionIdentifier`,
falling back to the legacy `identifier`), creates the `PageObject` whose `obj_key`
matches that key, and — with `--role`/`--grant` — calls `RoleService::bindPageObject`
(which invalidates the permission cache). `--grant` accepts `create,read,update,delete`
or `all`; `--role` and `--grant` are required together. The whole batch runs in a
single transaction.

`--grant` **adds** to the flags the role already has on each object and never revokes:
`--grant=read,update` on a role that holds `delete` keeps `delete`. Without `--key`
the grant applies to every synced screen; `--key=` (repeatable) limits it to the
screens whose permission key matches. To remove access, use the roles screen.

## ptah:permission:why

Explains WHY `ptah_can($objKey, $action, $user)` grants or denies access, without
reimplementing the permission engine — the granted/denied verdict itself always
comes from `PermissionService::check()`. Diagnostic tool: forces
`ptah.permissions.audit` off for the duration of the command, so running it never
pollutes `ptah_permission_audits`.

```bash
php artisan ptah:permission:why 42 users.store --action=create
php artisan ptah:permission:why admin@admin.com sales::export --action=read --company=3
```

**Arguments:**
- `{user}` — numeric user ID, or an e-mail looked up via `config('ptah.permissions.user_model')`.
- `{objKey}` — bare or qualified (`page::obj_key` / `page::section::obj_key` — see
  ["Qualified key"](Permissions.md#qualified-key-disambiguating-an-obj_key-collision)).

**Options:**
- `--action=read` — action to evaluate (`create|read|update|delete`).
- `--company=` — company context. Omit to use the console context: with no HTTP
  session, `PermissionService::resolveCompanyId()` resolves to `null`, so only
  global (`company_id IS NULL`) grants are considered — the command prints this
  explicitly.

Prints, in order: the user's `UserRole` bindings (with the reason each one does
or doesn't count), every `PageObject` registered under the requested `obj_key`
(an `obj_key` collision across pages prints all of them), the `RolePermission`
rows crossing the two (including trashed ones and all 4 `can_*` flags), and the
result for every CRUD action via `PermissionService::check()`. When the
requested `--action` is denied, prints the single most specific missing piece,
in this precedence order: nonexistent object → inactive object → inactive page
→ no active role → no bind at all → `can_{action}=false` → bind trashed → grant
scoped to a different company → grant only on a different page (suggesting the
qualified key to use instead).

Exit code: `0` when granted, `1` when denied (or when the user/action can't be
resolved).

## ptah:config:relabel

Re-humanises the column labels (`colsNomeLogico`) of existing configs through the same
`LabelHumanizer` used by the scaffold — for configs generated before the humaniser (or
by external tooling) that ended up with unaccented pt-BR labels (`Situacao`, `Descricao`).

```bash
php artisan ptah:config:relabel --dry-run   # preview before/after (default-safe)
php artisan ptah:config:relabel             # apply (asks for confirmation)
php artisan ptah:config:relabel --all       # ignore the guard (see below)
```

By default it only relabels when the current label is the **unaccented form** of the
humanised label (`Str::ascii(current) === Str::ascii(new) && current !== new`) — so it
fixes accents (`Situacao` → `Situação`) but **never overwrites a custom label**. `--all`
bypasses that guard (still asks for confirmation). Persists via `CrudConfigService::save()`
(re-validates + clears cache) inside a transaction; idempotent.

> ⚠️ **Security note — the `permissionIdentifier` fix (v1.7.0).** The RBAC gate is read
> from `permissions.permissionIdentifier`, but older configs (from `ptah:forge` or the
> visual editor before v1.7.0) stored the key under `permissions.identifier` — a screen
> with the permissions module **on** was therefore *silently ungated* (fail-open when the
> key is absent). Upgrade sequence, **in this order, before deploying**:
>
> ```bash
> php artisan ptah:config:doctor            # 1. find configs with the legacy key
> php artisan ptah:config:doctor --fix      # 2. migrate identifier → permissionIdentifier
> php artisan ptah:permission:sync --role="…" --grant=… [--key=…]   # 3. grant the roles that need each screen
> ```
>
> Step 2 flips those screens from fail-open to fail-closed: non-master users **lose access
> until** step 3 grants it. Run steps 2 and 3 together (staging first). Master users are
> unaffected (short-circuit).

**Column Syntax (--column):** parsed by `ColumnParser` (`src/Commands/Config/Parsers/ColumnParser.php`).

```bash
# Basic format
field:type:modifier:modifier:option=value:option=value

# Examples
name:text:required:label=Name
email:text:required:validation=email|max:255
price:number:label=Price:mask=money_brl:renderer=money:decimals=2
status:select:options=active,inactive:renderer=badge:badges=active|green,inactive|red
user_id:searchdropdown:sd_model=App\Models\User:sd_value=id:sd_label=name
description:textarea:nullable:placeholder=Enter description
active:boolean:required
created_at:datetime:readonly:renderer=datetime
```

**`colsTipo` values (`type`, the 2nd token):** `text`, `textarea`, `number`, `date`,
`datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image`, `color`, `time`,
`datetime-local`, `range`, `email`, `url`, `tel`, `password` (the full list is
`CrudConfigEnums::COLUMN_TYPES`). `colsTipo` is
the FORM INPUT type — it is independent from `renderer=`, which controls how the
value is DISPLAYED in the listing table. See [KnownLimitations.md §5](KnownLimitations.md#5-ptahconfig-cli--column-types-and-renderers).

**Modifiers (bare tokens — never `modifier=true`):**

| Modifier | Effect |
|---|---|
| `required` | `colsRequired = true` |
| `nullable` | `colsRequired = false` |
| `readonly` | `colsGravar = false` (not saved to DB) |
| `hidden` | `colsVisibleList = false` |
| `sortable` | `colsOrderBy = <field>` |
| `filterable` | `colsIsFilterable = true` |
| `not_filterable` | `colsIsFilterable = false` |

**Column options (`option=value`) — full `$keyMap`:**

| Option | Mapping | Example |
|---|---|---|
| `label` | `colsNomeLogico` | `label=Product Name` |
| `placeholder` | `colsPlaceholder` | `placeholder=Type here...` |
| `default` | `colsDefaultValue` | `default=1` — pre-filled when the user clicks **New** (boolean: `1`/`0`) |
| `align` | `colsAlign` | `align=text-end` |
| `renderer` | `colsRenderer` | `renderer=money` (`text`,`badge`,`pill`,`boolean`,`money`,`date`,`datetime`,`link`,`image`,`truncate`,`number`,`filesize`,`duration`,`code`,`color`,`progress`,`rating`,`qrcode`) |
| `mask` | `colsMask` | `mask=money_brl` |
| `relation` | `colsRelacao` | `relation=category` |
| `relation_display` | `colsRelacaoExibe` | `relation_display=name` |
| `relation_nested` | `colsRelacaoNested` | `relation_nested=category.parent.name` |
| `min_width` | `colsMinWidth` | `min_width=120px` (there is no `width=`) |
| `cell_style` | `colsCellStyle` | `cell_style=background:#FEE;` |
| `cell_class` | `colsCellClass` | `cell_class=font-bold` |
| `cell_icon` | `colsCellIcon` | `cell_icon=bx-user` |
| `source` | `colsSource` | `source=JOIN categories` |
| `method` | `colsMetodoCustom` | `method=formatCustom` |
| `method_raw` | `colsMetodoRaw` | — |
| `order_by` | `colsOrderBy` | `order_by=name` |
| `permission` | `colsPermission` | `permission=page::viewCost` — column-level visibility gate, see [Permissions.md § Column-level permissions](Permissions.md#column-level-permissions) |
| `sd_mode` | `colsSDMode` | — |
| `sd_model` | `colsSDModel` | `sd_model=App\Models\Category` |
| `sd_service` | `colsSDService` | — |
| `sd_service_method` | `colsSDServiceMethod` | — |
| `sd_value` | `colsSDValor` | `sd_value=id` |
| `sd_label` | `colsSDLabel` | `sd_label=name` |
| `sd_label_two` | `colsSDLabelTwo` | — |
| `sd_order_by` | `colsSDOrder` | — |
| `sd_limit` | `colsSDLimit` | `sd_limit=15` |
| `sd_placeholder` | `colsSDPlaceholder` | — |
| `sd_filters` | `colsSDFilters` | — |
| `sd_init_with_data` | `colsSDInitWithData` | `sd_init_with_data=false` |
| `sd_label_three` | `colsSDLabelThree` | — |
| `sd_mask_one` | `colsSDMaskOne` | `sd_mask_one=cnpj` |
| `sd_mask_two` | `colsSDMaskTwo` | — |
| `sd_mask_three` | `colsSDMaskThree` | — |
| `sd_array_search` | `colsSDArraySearch` (special: comma-separated column list) | `sd_array_search=cnpj,email` |
| `sd_start_list` | `colsSDStartList` | `sd_start_list=top` |
| `sd_depends_on` | `colsSDDependsOn` | `sd_depends_on=state_id` |
| `sd_filter_column` | `colsSDFilterColumn` | `sd_filter_column=state_id` |
| `currency` | `colsRendererCurrency` | `currency=BRL` |
| `decimals` | `colsRendererDecimals` | `decimals=2` |
| `bool_true` | `colsRendererBoolTrue` | — |
| `bool_false` | `colsRendererBoolFalse` | — |
| `link_template` | `colsRendererLinkTemplate` | `link_template=/products/%id%` |
| `link_label` | `colsRendererLinkLabel` | — |
| `link_new_tab` | `colsRendererLinkNewTab` | `link_new_tab=true` |
| `image_width` | `colsRendererImageWidth` | — |
| `image_height` | `colsRendererImageHeight` | — |
| `upload_path` | `colsUploadPath` | `upload_path=products/images` |
| `upload_max_size` | `colsUploadMaxSize` | `upload_max_size=2048` (KB) |
| `upload_allowed_types` | `colsUploadAllowedTypes` | `upload_allowed_types=jpg,png,webp` (comma-separated) |
| `max_chars` | `colsRendererMaxChars` | `max_chars=50` |
| `locale` | `colsRendererLocale` | — |
| `progress_max` | `colsRendererMax` | — |
| `progress_color` | `colsRendererColor` | — |
| `rating_max` | `colsRendererMax` | — |
| `duration_unit` | `colsRendererDurationUnit` | — |
| `qr_size` | `colsRendererQrSize` | — |
| `mask_transform` | `colsMaskTransform` | `mask_transform=money_to_float` |
| `totalizer` | `totalizadorType` → moved by the command into the screen-level `totalizadores.columns` (`{field, aggregate}`) with `totalizadores.enabled` on (1.50.0) | `totalizer=sum` |
| `totalizer_format` | `totalizadorFormat` — ignored with a warning (the footer uses the column renderer) | — |
| `totalizer_label` | `totalizadorLabel` — ignored with a warning (the footer uses the column renderer) | — |
| `totalizer_enabled` | `totalizadorEnabled` — dropped (`totalizer=` already enables the totals) | — |
| `validation` | `colsValidations` (special: `\|`-split rules) | `validation=required\|max:255` |
| `options` | `colsSelect` (special: normalised to a `label => value` map, see above) | `options=active:Active,inactive:Inactive` |
| `badges` | `colsRendererBadges` (special: parsed into `value/color/label` triples) | `badges=active\|green\|Ativo,inactive\|gray\|Inativo` — **`\|`-separated**, never `:` (collides with the `field:type:modifier` syntax) |

> Any `option=value` key **not** in this list is stored verbatim under that raw
> key on the column array, but the command warns (`unknown option … did you mean
> …`): a typo like `rendererDecimals=2` sets a `rendererDecimals` key nothing
> reads, instead of `colsRendererDecimals` — use `decimals=2`.
>
> `sd_value`/`sd_label`/`sd_order_by` write `colsSDValor`/`colsSDLabel`/
> `colsSDOrder` — these ARE the canonical keys the BaseCrud inline widget
> (`HasCrudSearchDropdown::sdSettings()`) reads. `sd_mode`/`sd_service`/
> `sd_service_method` are also read directly by `sdSettings()`: `sd_mode`
> selects `colsSDTipo` (only `model`|`service` are recognised as an alias —
> any other value falls back to `model`), and in service mode `sdSettings()`
> composes the runtime's `Service\Class\methodName` string from
> `sd_service`+`sd_service_method` itself (the CLI never needs to write a
> composed `colsSDModel` for that case). See
> [SearchDropdown.md](SearchDropdown.md#basecrud-inline-widget-configuration-surface)
> for the full BaseCrud searchdropdown key reference (masks, arraySearch,
> filters, initWithData, cascading).

**Examples:**

```bash
# Price with mask and renderer
--column="price:number:required:mask=money_brl:renderer=money:currency=BRL:decimals=2"

# Status with select and badges
--column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red,pending|yellow"

# Readonly datetime
--column="created_at:datetime:readonly:renderer=datetime"

# Image with upload
--column="image:image:upload_path=products:upload_max_size=2048:upload_allowed_types=jpg,png,webp"

# Number with totalizer
--column="quantity:number:totalizer=sum"
```

**Action Syntax (--action):** parsed by `ActionParser`. Only `link`, `livewire`
and `javascript` are executed by the table renderer
(`resources/views/livewire/base-crud/partials/_table.blade.php`) — any other
`actionType` value is rejected by `ConfigSchemaValidator`.

```bash
# Format
name:type:value:icon=icon:color=color:confirm=bool:confirm_message=text:permission=key

# Examples
approve:livewire:approve(%id%):icon=bx-check:color=success:confirm=true
reject:livewire:reject(%id%):icon=bx-x:color=danger
view:link:https://example.com/view/%id%:icon=bx-show:color=primary
export:javascript:exportData():icon=bx-download:color=info
```

**Filter Syntax (--filter):** parsed by `FilterParser` — **there is no
positional operator token**; everything after `field:type` is `key=value`.

```bash
# Format
field:type:label=Label:operator==:options=opt1,opt2

# Examples
status:select:label=Status:operator==:options=active,inactive
price:number:label=Minimum Price:operator=>=
created_at:date:label=From Date:operator=>=
```

**Style Syntax (--style):**

```bash
# Format
field:condition:value:style

# Examples
status:==:cancelled:background:#FEE;color:#C00;
priority:>:5:background:#FFE;font-weight:bold;
```

**Join Syntax (--join):**

```bash
# Format
type:table:first=second:select=col:alias,col2 as alias2:distinct

# Examples
left:users:products.user_id=users.id:select=users.name:user_name,users.email as user_email
inner:categories:products.category_id=categories.id:select=categories.name:distinct
```

Stored as `{type, table, first, second, distinct, select:[{column, alias}]}`.
`select=` accepts `column:alias` or `column as alias` (the alias defaults to the
column name); `distinct` / `distinct=true` is optional. `where=` and any other
`key=value` option are **refused** — a join has no WHERE of its own, so filter the
screen instead.

**General Settings (--set):** any config path, dotted for nested keys
(`--set="exportConfig.maxRows=5000"`). The short names below are translated
to the paths BaseCrud reads; keys it never reads (`cacheEnabled`,
`paginationEnabled`, `searchEnabled`, `softDeletes`…) are refused with a
warning. Full list: [Configuration.md § General Settings](Configuration.md#general-settings).

```bash
# Examples
--set="itemsPerPage=25"
--set="exportEnabled=true"
--set="compactMode=false"
--set="exportFormats=excel,pdf"   # list-valued keys are stored as a list
```

**Permissions (--permission):**

The flag writes two kinds of value, and the difference matters when reading the
examples below: five keys hold a **gate name** (a string), and four hold a
**boolean** that hides a button outright.

```bash
# Gate names — the permission checked before the action is allowed
--permission="create=product.create"
--permission="edit=product.update"
--permission="delete=product.destroy"
--permission="export=product.export"
--permission="restore=product.restore"

# Buttons — hidden regardless of any gate
--permission="showCreateButton=false"
--permission="showEditButton=false"
--permission="showDeleteButton=false"
--permission="showTrashButton=false"
```

`true` and `false` are stored as booleans, `null` as null, and a number as a
number — the same casting `--set` uses. Until 1.34.2 the value was stored as a
raw string, which made the four boolean keys unreachable from the CLI: `(bool)
"false"` is `true` in PHP, so the command reported success and the button stayed
on screen. The five gate names were unaffected, which is why a flag that worked
in five of nine cases looked like a flag that worked.

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
  --column="stock:number:label=Stock:renderer=number:decimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --column="category_id:searchdropdown:sd_model=App\Models\Category" \
  --set="itemsPerPage=25"

# 3. View current config
php artisan ptah:config "App\Models\Product" --list

# 4. Add more columns later
php artisan ptah:config "App\Models\Product" \
  --column="description:textarea:nullable" \
  --column="image:image:upload_path=products/photos:upload_max_size=2048:upload_allowed_types=jpg,png,webp"

# 5. Export for backup or sharing
php artisan ptah:config "App\Models\Product" --export=product-config.json

# 6. Import in another environment
php artisan ptah:config "App\Models\Product" --import=product-config.json

# 7. Delete the stored config (asks first; --export beforehand if you may want it back)
php artisan ptah:config "App\Models\Product" --reset

# 8. Create a route-specific config (same model, different columns per URL path)
php artisan ptah:config "App\Models\Product" \
  --route="admin/products" \
  --column="name:text:required" \
  --column="price:number:required" \
  --column="status:select:options=active,inactive:renderer=badge:badges=active|green|Active,inactive|gray|Inactive"

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
- ``model`` — Canonical model key (``ModelKey::canonical``, e.g. ``Catalog/Product``), not the FQCN
- ``route`` — Route path scope (empty string = global config)
- ``config`` — JSON configuration
- ``updated_at`` — Last modified timestamp

Cache is automatically cleared after saving.

**Next Steps:**

1. Configure your first model: ``php artisan ptah:config "App\Models\YourModel"``
2. View configuration: ``php artisan ptah:config "App\Models\YourModel" --list``
3. Refresh browser to see changes in CRUD interface
4. Export for backup: ``php artisan ptah:config "App\Models\YourModel" --export=backup.json``

---

## Agent tools

Commands that replace reading files — each prints a short answer and what is
left to do. Full reference, options and sample output: **[AgentTools.md](AgentTools.md)**.

| Command | Purpose |
|---|---|
| `ptah:docs {topic} [--json]` | `ptah:config` option reference generated from the parser (column, filter, style, action, join, mask) |
| `ptah:map [--json] [--write] [--models=]` | Entities, typed fields, FK targets, relations, screens, menu, TODOs |
| `ptah:screen {model} [--route=] [--json]` | One screen summarized: columns, filters, actions, styles, joins, hooks, permission |
| `ptah:why-empty {model} [--as=] [--guard=] [--route=] [--json]` | Row count after each listing layer for a user, final SQL, failing query |
| `ptah:check [model] [--as=] [--guard=] [--write] [--force] [--json]` | Render every screen + config vs model/table; `--write` round-trip rolled back. Without `model`, also lists unregistered permission keys and AI tools with no permission gate |
| `ptah:field {Entity} add {field…} [--dry-run] [--no-migration] [--no-config]` | One field into migration, model, requests, DTO, crud configs |
| `ptah:forge … --factory` | Also a model factory + demo seeder from field types |
| `ptah:blueprint {spec} [--dry-run] [--no-migrate] [--force]` | Module from a JSON spec: forge (FK order), migrate, config, menu-sync, permission:sync, seed |
| `ptah:last-error [--file=] [--frames=6] [--json]` | Last log error: exception, SQL, app frames only |
| `ptah:upgrade-check [--strict] [--json]` | Post-update actions for this host |

---

## vendor:publish (tags ptah)

Ptah exposes several groups of publishable files via ``vendor:publish``. Each tag is independent and optional — publish only what you need.

| Tag | What it publishes | Destination |
|-----|--------------|--------|
| ``ptah-config`` | Configuration files (also ``ptah-masks.php``, ``ptah-settings.php``, ``ptah-dashboard.php``) | ``config/ptah.php``, ``config/ptah-masks.php``, ``config/ptah-settings.php``, ``config/ptah-dashboard.php`` |
| ``ptah-masks`` | Input masks only, without republishing ``ptah.php`` | ``config/ptah-masks.php`` |
| ``ptah-stubs`` | Customizable scaffold stubs | ``stubs/ptah/`` |
| ``ptah-migrations`` | All package migrations | ``database/migrations/`` |
| ``ptah-lang`` | **Full** translations (pt_BR and en) — ⚠ freezes every key | ``lang/vendor/ptah/`` |
| ``ptah-lang-overrides`` | Minimal override starter (change strings without freezing) | ``lang/vendor/ptah/pt_BR/ui.php`` |
| ``ptah-views`` | Blade views (for customization) — ⚠ publishing a view means you own it; prefer the granular tags below | ``resources/views/vendor/ptah/`` |
| ``ptah-views-components`` | ``<x-forge-*>`` components only | ``resources/views/vendor/ptah/components/`` |
| ``ptah-views-base-crud`` | BaseCrud views only | ``resources/views/vendor/ptah/livewire/base-crud/`` |
| ``ptah-views-auth`` | Auth views and the ``forge-auth`` layout | ``resources/views/vendor/ptah/livewire/auth/``, ``…/layouts/forge-auth.blade.php`` |
| ``ptah-views-ai`` | AI agent views only | ``resources/views/vendor/ptah/livewire/ai/`` |
| ``ptah-assets`` | Forge CSS | ``resources/css/vendor/ptah/`` |
| ``ptah-menu-registry`` | MenuRegistry.php (auto-menu) | ``database/seeders/MenuRegistry.php`` |
| ``ptah-api`` | BaseResponse, BaseApiController, SwaggerInfo | ``app/Responses/``, ``app/Http/Controllers/API/`` |
| ``ptah-auth`` | 2FA migration | ``database/migrations/`` |
| ``ptah-menu`` | Menus migration | ``database/migrations/`` |
| ``ptah-company`` | Company migrations | ``database/migrations/`` |
| ``ptah-permissions`` | Permissions migrations | ``database/migrations/`` |
| ``ptah-ai-agent`` | AI Agent migrations (what ``ptah:module ai_agent`` publishes) | ``database/migrations/`` |
| ``ptah-notifications`` | Notifications migration (opt-in schema) | ``database/migrations/`` |
| ``ptah-errors`` | Error views (403, 404, 405, 419, 429, 500, 503) and their layout | ``resources/views/errors/`` |
| ``ptah-skills`` | Agent skills for Claude Code (re-publish with ``--force`` after upgrading ptah) | ``.claude/skills/`` |
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

### Overriding translations without freezing

To change a few UI strings, **do not** publish `ptah-lang` (it copies all 1400+
keys, pinning every one to today's wording — you stop receiving upstream fixes and
still get new keys, but the published ones are frozen).

Instead publish the minimal override and list only what you change:

```bash
php artisan vendor:publish --tag=ptah-lang-overrides
# → lang/vendor/ptah/pt_BR/ui.php  (starts as an empty array)
```

```php
// lang/vendor/ptah/pt_BR/ui.php
return [
    'btn_new'            => 'Adicionar',
    'search_placeholder' => 'Pesquisar...',
];
```

Laravel merges this file **over** the package's (`array_replace_recursive`), so every
key you don't list — and every key added by future ptah versions — still comes from
the package. For another locale, copy the file to `lang/vendor/ptah/{locale}/ui.php`.

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
# http://localhost/product
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

# Then add API: without --force existing files are skipped, so only the API artifacts are created
php artisan ptah:forge Product --api
```

> Do **not** add `--force` here: it overwrites every existing artifact of the entity
> (model, service, views…) — everything except an existing migration.

### Subfolders (organization)

```bash
# Structure: Purchase/Order, Purchase/OrderItem
php artisan ptah:forge Purchase/Order
php artisan ptah:forge Purchase/OrderItem

# Result:
# app/Models/Purchase/Order.php
# app/Services/Purchase/OrderService.php
# resources/views/order/index.blade.php   (the subfolder is ignored for views)
```

### Reading from existing database

```bash
# If the table already exists in the database
php artisan ptah:forge Customer --db --table=customers
```

This inspects the structure via ``Schema::getColumns()`` and generates compatible Models/DTOs/Migrations.

---

## Troubleshooting

### Error: "Model not found"
**Cause:** Repository binding not registered.

**Solution:** ``ptah:forge`` injects it into ``AppServiceProvider::register()``; if it did not (no ``register()`` method, or the file was missing), add it there:
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
**Cause:** Re-running ``ptah:install``. ``ptah:forge`` never recreates a migration when a ``*_create_{table}_table.php`` already exists (even with ``--force``).

**Solution:** Use ``--force`` only when you really want to overwrite. For modules, check with ``ptah:module --list`` first.

---

## Command History

### Removed Commands (V2.2+)

These commands were discontinued and replaced by ``ptah:forge``:

| Removed command | Replacement |
|------------------|--------------|
| ``ptah:make-api {Entity}`` | ``ptah:forge {Entity} --api-only`` |
| ``ptah:docs {Entity}`` (Swagger) | Swagger gerado automaticamente via ``ptah:forge --api``. Since 1.35.0 the name ``ptah:docs`` is the ``ptah:config`` option reference — see [Agent Tools](AgentTools.md) |

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
**Cause:** Reading the table structure (``Schema::getColumns()``) can be slow on large databases.

**Solution:** Use manual ``--fields`` for known tables:
```bash
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"
```

---

## ptah:export-prune

Removes finished queued exports past their TTL — and stale `queued`/`processing`/
`failed` rows older than the TTL — deleting both the `ptah_exports` row and its file.
For the large-volume (queued) export mode; schedule it (e.g. daily).

```bash
php artisan ptah:export-prune
```

TTL comes from `config('ptah.export.ttl_hours')` (default 48). Safe to run repeatedly
(only removes what is already expired/orphaned).

## ptah:audit-prune

> ⚠️ **DESTRUTIVO** — permanently deletes rows from `ptah_permission_audits`
> (no `SoftDeletes`; the log is immutable by design — this command is the only
> path to shrinking it). Meant to be scheduled (e.g. daily/weekly) once
> `ptah.permissions.audit = true` is on, since that table otherwise grows
> forever.

```bash
php artisan ptah:audit-prune                # uses config('ptah.permissions.audit_retention_days', 90)
php artisan ptah:audit-prune --days=30
php artisan ptah:audit-prune --dry-run      # count only, no deletion
php artisan ptah:audit-prune --chunk=500    # delete window size (portable across drivers, incl. sqlite)
```

**Options:**
- `--days=` — retention window in days; defaults to
  `config('ptah.permissions.audit_retention_days', 90)`. Rejected (exit ≠ 0)
  when `< 1`, as a guard against wiping the whole table by accident.
- `--chunk=1000` — rows deleted per batch. Portable across every driver Ptah
  supports (including sqlite, which has no fast conditional truncate): pulls a
  page of ids ordered by PK, deletes exactly those ids, repeats until a page
  comes back smaller than the chunk size.
- `--dry-run` — only counts the rows that would be deleted; no writes.

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

Creates `app/CrudHooks/{Name}.php` implementing `Ptah\Contracts\CrudHooksInterface` with the 5 pre-filled lifecycle methods (`beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`):

```php
namespace App\CrudHooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Ptah\Contracts\CrudHooksInterface;
use Ptah\Exceptions\CrudHookAbort;

/**
 * A hook that throws is LOGGED AND THE SAVE CONTINUES — which is what you want
 * for a notification, and not what you want for a barrier. To make a failure
 * abort the save, throw CrudHookAbort (its message reaches the user), or throw
 * a ValidationException to get a field error. An after* hook cannot roll the
 * record back: there is no transaction around the save.
 */
class ProductHooks implements CrudHooksInterface
{
    public function beforeCreate(array &$data, ?Model $record, object $component): void
    {
        // Example: set a default value
        // $data['status'] = 'pending';

        // Example: refuse the save (never swallowed)
        // throw new CrudHookAbort('Não foi possível gerar a senha temporária.');
    }

    public function afterCreate(array &$data, Model $record, object $component): void
    {
        // Example: dispatch an event
        Log::info('ProductHooks: afterCreate', ['id' => $record->getKey()]);
    }

    public function beforeUpdate(array &$data, Model $record, object $component): void
    {
        // Example: track who changed the record
        // $data['updated_by'] = auth()->id();
    }

    public function afterUpdate(array &$data, Model $record, object $component): void
    {
        // Example: clear cache
    }

    /**
     * Runs before a record is deleted (single, bulk or force). Unlike the save
     * hooks, ANY failure here refuses the delete; throw CrudHookAbort to show
     * the user why.
     */
    public function beforeDelete(array &$data, Model $record, object $component): void
    {
        // Example: refuse while the record is in use
        // if ($record->products()->exists()) {
        //     throw new CrudHookAbort('Em uso por produtos ativos.');
        // }
    }
}
```

(The generated file also carries a docblock on each method; trimmed here.)

**Next steps:**

1. Implement the desired logic in the methods in `app/CrudHooks/{Name}.php`
2. In CrudConfig, associate the hook to a field using the `@ProductHooks` syntax
3. See [Configuration.md](Configuration.md) for details on Lifecycle Hooks

> ⚠️ **Warning:** The `$component` parameter exposes the full Livewire component. Use it only for reading properties, never for dispatching arbitrary actions from external data.

> **Inline hooks (no class):** besides class-based hooks, a CrudConfig field may hold a single inline *expression* (Symfony ExpressionLanguage). It is evaluated in a sandbox — it does **not** run arbitrary PHP (no `eval`). The expression receives `data`, `record` and `user` (the last two as fields only — no method can be called on them); if it returns an array, that array becomes the form data. Safe functions: `merge()`, `now()`, `upper()`, `lower()`, `slug()`, `uuid()`. Example: `merge(data, {'status': 'pending'})`. For anything beyond a one-liner, use a hook class.

---

## ptah:menu-sync

**Description:** Syncs `database/seeders/MenuRegistry.php` (generated/updated by `ptah:forge`) into the `menus` database table that drives the dynamic sidebar.

**Usage:**
```bash
# Add new menu entries and refresh the ones in the registry (idempotent)
php artisan ptah:menu-sync

# List the active items that are no longer in the registry (writes nothing)
php artisan ptah:menu-sync --prune --dry-run

# ...and deactivate them (is_active = 0 — never deleted)
php artisan ptah:menu-sync --prune

# DELETE every menu item (including editor-created ones) and recreate from the registry — asks first
php artisan ptah:menu-sync --fresh

# Same, without the prompt (scripts)
php artisan ptah:menu-sync --fresh --force
```

Items already in the registry are **not** left untouched: a link has its text, icon, order and `is_active` rewritten from the registry on every run (a group, matched by its text, gets icon, order and `is_active`) — so a label or order changed in the menu editor on a registry item is overwritten, and a deactivated one is reactivated.

**Options:**
- `--prune` — After syncing, sets `is_active = 0` on every active item (link or group) that is not in the registry, and lists them. Nothing is deleted: set `is_active = 1` (or use the menu editor) to bring one back. **Note:** items created in the menu editor are not in the registry either, so `--prune` deactivates them too — run `--prune --dry-run` first and read the list.
- `--dry-run` — With `--prune`, only lists what would be deactivated.
- `--fresh` — Deletes **every** row of the `menus` table before syncing (**destructive** — including items created in the menu editor; they are not in the registry and are not rebuilt). Asks for confirmation; the answer defaults to no. Prefer plain `ptah:menu-sync`, or `--prune`.
- `--force` — With `--fresh`, skips the confirmation (for scripts).

Every run clears the sidebar cache (`ptah_menu_tree`), so the change shows on the next page load.

**Requirements:**
- The **menu** module must be enabled (`php artisan ptah:module menu`).
- `MenuRegistry.php` must exist — it is created by `ptah:install` and updated automatically by `ptah:forge` (unless `--no-menu` is passed).

**Typical flow:** run it after scaffolding entities so the new links appear in the sidebar:
```bash
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"
php artisan migrate
php artisan ptah:menu-sync
```

## ptah:menu-cache-clear

Clears the cached sidebar tree. Changes through the `Menu` model, the menu editor and `ptah:menu-sync` already clear it; run this after changing the `menus` table by hand (raw SQL, another application).

```bash
php artisan ptah:menu-cache-clear
```

---

## Opt-in installers

Package migrations are auto-discovered, so a new table would run on the host's next `php artisan migrate` unasked. These opt-in features therefore ship their migration through a command that copies it into `database/migrations/` — you review it, commit it and run `php artisan migrate` when you decide to. Each is idempotent: an existing migration is left alone (`Already installed: …`). None takes options.

| Command | Writes | Then |
|---|---|---|
| `ptah:history:install` | `*_create_ptah_record_history_table.php` | `use \Ptah\Traits\RecordsHistory;` in each model to record; its BaseCrud edit modal then shows a History button |
| `ptah:attachments:install` | `*_create_ptah_attachments_table.php` | `use \Ptah\Traits\HasAttachments;` in each model that takes files; disk from `PTAH_ATTACHMENTS_DISK` (default `local`, private) |
| `ptah:settings:install` | `*_create_ptah_settings_table.php` and `config/ptah-settings.php` (if absent) | Declare the settings in `config/ptah-settings.php`; edit at `/ptah-settings`, read with `ptah_setting('key')` — see [Settings.md](Settings.md) |

### ptah:preferences:realign

Realigns the `user_preferences.user_id` foreign key to the configured identity table (see `UserIdentity`). It changes the FK only — it never deletes rows and never changes the column type.

```bash
php artisan ptah:preferences:realign --dry-run   # show the current vs. wanted FK and exit
php artisan ptah:preferences:realign             # apply (asks for confirmation)
php artisan ptah:preferences:realign --force     # apply without the prompt (deploy scripts)
```

- Does nothing when the FK already points to the right place.
- Refuses to run (exit ≠ 0) while any `user_id` has no matching row in the identity table, and prints a `select` to inspect them — clean those up manually first.
- Fails if the `user_preferences` table does not exist (run `php artisan migrate` first).
- It alters the schema of a table that may be in production: run `--dry-run` first and take a backup.

---

## References

- [InstallationGuide.md](InstallationGuide.md) — Complete installation guide
- [BaseCrud.md](BaseCrud.md) — BaseCrud reference
- [Modules.md](Modules.md) — Module details
- [AI_Guide.md](AI_Guide.md) — Prompts for AI agents
- [Permissions.md](Permissions.md) — Detailed RBAC system
- [KnownLimitations.md](KnownLimitations.md) — Developer checklist: decimal precision, FK constraints, composite indexes, post-forge responsibilities

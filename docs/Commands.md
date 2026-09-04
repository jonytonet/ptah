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
14. [vendor:publish (tags ptah)](#vendorpublish-tags-ptah)

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
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --action="approve:livewire:approve(%id%):icon=bx-check:color=success" \
  --filter="status:select:label=Status:operator==:options=active,inactive" \
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
```

For each config it resolves the canonical RBAC key (`permissions.permissionIdentifier`,
falling back to the legacy `identifier`), creates the `PageObject` whose `obj_key`
matches that key, and — with `--role`/`--grant` — calls `RoleService::bindPageObject`
(which invalidates the permission cache). `--grant` accepts `create,read,update,delete`
or `all`; `--role` and `--grant` are required together. The whole batch runs in a
single transaction.

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
> php artisan ptah:permission:sync --role="…" --grant=…   # 3. grant the roles that need each screen
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
`datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image`. `colsTipo` is
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
| `mask_regex` | `colsMaskRegex` | — |
| `mask_transform` | `colsMaskTransform` | `mask_transform=money_to_float` |
| `totalizer` | `totalizadorType` (also sets `totalizadorEnabled=true`) | `totalizer=sum` |
| `totalizer_format` | `totalizadorFormat` | `totalizer_format=currency` |
| `totalizer_label` | `totalizadorLabel` | — |
| `totalizer_enabled` | `totalizadorEnabled` | — |
| `validation` | `colsValidations` (special: `\|`-split rules) | `validation=required\|max:255` |
| `options` | `colsSelect` (special: kept as raw string) | `options=active:Active,inactive:Inactive` |
| `badges` | `colsRendererBadges` (special: parsed into `value/color/label` triples) | `badges=active\|green\|Ativo,inactive\|gray\|Inativo` — **`\|`-separated**, never `:` (collides with the `field:type:modifier` syntax) |

> Any `option=value` key **not** in this list is stored verbatim under that raw
> key on the column array (e.g. a typo like `rendererDecimals=2` silently sets a
> `rendererDecimals` key nothing reads, instead of `colsRendererDecimals` — use
> `decimals=2`).
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
--column="quantity:number:totalizer=sum:totalizer_format=number"
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
  --column="stock:number:label=Stock:renderer=number:decimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --column="category_id:searchdropdown:sd_model=App\Models\Category" \
  --set="itemsPerPage=25" \
  --set="cacheEnabled=true"

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
| ``ptah-lang`` | **Full** translations (pt_BR and en) — ⚠ freezes every key | ``lang/vendor/ptah/`` |
| ``ptah-lang-overrides`` | Minimal override starter (change strings without freezing) | ``lang/vendor/ptah/pt_BR/ui.php`` |
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

> **Inline hooks (no class):** besides class-based hooks, a CrudConfig field may hold a single inline *expression* (Symfony ExpressionLanguage). It is evaluated in a sandbox — it does **not** run arbitrary PHP (no `eval`). The expression receives `data`, `record` and `user`; if it returns an array, that array becomes the form data. Safe functions: `merge()`, `now()`, `upper()`, `lower()`, `slug()`, `uuid()`. Example: `merge(data, {'status': 'pending'})`. For anything beyond a one-liner, use a hook class.

---

## ptah:menu-sync

**Description:** Syncs `database/seeders/MenuRegistry.php` (generated/updated by `ptah:forge`) into the `menus` database table that drives the dynamic sidebar.

**Usage:**
```bash
# Add new menu entries, preserving existing ones (idempotent)
php artisan ptah:menu-sync

# Clear the menus table and recreate everything from the registry
php artisan ptah:menu-sync --fresh
```

**Options:**
- `--fresh` — Truncates the `menus` table before syncing (**destructive** — current menu rows are removed and rebuilt from the registry).

**Requirements:**
- The **menu** module must be enabled (`php artisan ptah:module menu`).
- `MenuRegistry.php` must exist — it is created by `ptah:install` and updated automatically by `ptah:forge` (unless `--no-menu` is passed).

**Typical flow:** run it after scaffolding entities so the new links appear in the sidebar:
```bash
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"
php artisan migrate
php artisan ptah:menu-sync --fresh
```

---

## References

- [InstallationGuide.md](InstallationGuide.md) — Complete installation guide
- [BaseCrud.md](BaseCrud.md) — BaseCrud reference
- [Modules.md](Modules.md) — Module details
- [AI_Guide.md](AI_Guide.md) — Prompts for AI agents
- [Permissions.md](Permissions.md) — Detailed RBAC system
- [KnownLimitations.md](KnownLimitations.md) — Developer checklist: decimal precision, FK constraints, composite indexes, post-forge responsibilities

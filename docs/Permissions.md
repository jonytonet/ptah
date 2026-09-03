# Permissions Module — Complete Documentation

**Package:** `jonytonet/ptah`  
**Namespace:** `Ptah\Services\Permission`, `Ptah\Livewire\Permission`  
**Livewire:** 4.x | **Laravel:** 11 · 12 · 13

---

## Table of Contents

1. [Overview](#overview)
2. [Core Concepts](#core-concepts)
3. [Activation](#activation)
4. [Configuration](#configuration)
5. [Database](#database)
6. [Models](#models)
   - [Role](#role)
   - [PtahPage](#ptahpage)
   - [PageObject](#pageobject)
   - [RolePermission](#rolepermission)
   - [UserRole](#userrole)
   - [PermissionAudit](#permissionaudit)
7. [PermissionService](#permissionservice)
8. [RoleService](#roleservice)
9. [Global Helpers](#global-helpers)
10. [Permission Facade](#permission-facade)
11. [Blade Directives](#blade-directives)
12. [Middleware ptah.can](#middleware-ptahcan)
13. [Administration Screens](#administration-screens)
    - [DepartmentList](#departmentlist)
    - [RoleList](#rolelist)
    - [PageList](#pagelist)
    - [UserPermissionList](#userpermissionlist)
    - [Filtering Users in the Permissions Screen](#filtering-users-in-the-permissions-screen)
    - [AuditList](#auditlist)
    - [PermissionGuide](#permissionguide)
14. [Routes](#routes)
15. [Seeders](#seeders)
16. [Verification Flow](#verification-flow)
17. [Audit](#audit)
18. [Cache](#cache)
19. [Integration with Auth and BaseCrud](#integration-with-auth-and-basecrud)
20. [Column-level Permissions](#column-level-permissions)
21. [Practical Examples](#practical-examples)
22. [Configuration Reference](#configuration-reference)

---

## Overview

The **permissions** module implements a hierarchical and granular access control system based on **RBAC** (Role-Based Access Control) concepts with optional multi-company support.

**What the module provides:**

| Feature | Description |
|---|---|
| Roles/Profiles | Permission groupings, optionally associated with a department |
| MASTER Role | Complete bypass of all permission checks |
| Pages and Objects | Registration of system resources (buttons, fields, sections, APIs…) |
| CRUD Permissions | Per object: `can_create`, `can_read`, `can_update`, `can_delete` |
| Extra permissions | JSON `extra` field for actions beyond standard CRUD |
| Company assignment | User can have different roles in each company |
| Global roles | Assignment without a specific company — valid across all companies |
| Cache | Permissions cached per user+company+object+action |
| Audit | Log of granted and denied accesses with JSON context |
| Middleware | `ptah.can:object,action` for route protection |
| Blade directives | `@ptahCan('key', 'action')` and `@ptahMaster` |
| Global helpers | `ptah_can()`, `ptah_is_master()`, `ptah_permissions()`, `ptah_has_role()`, `ptah_can_manage_config()` |
| 6 admin screens | Departments, Roles, Pages/Objects, Users, Audit, Permissions Guide |

---

## Core Concepts

### Access hierarchy

```
System
└── Company (optional — when multi_company = true)
    └── Role (access profile)
        └── RolePermission (object + can_* actions)
            └── UserRole (user ↔ role ↔ company)
```

### Page vs Page Object

- **Page (`PtahPage`):** represents a screen or system context (e.g.: `admin.users`, `finance.accounts-payable`)
- **Page Object (`PageObject`):** controllable resource within a page (e.g.: "Create user" button, "Salary" field, "Financial history" tab)
- **Permission:** a `Role` has permission for certain actions on a specific `PageObject`

### Object key (`obj_key`)

The `obj_key` is the identifier used in checks. The `page.action` or `module.resource` pattern is recommended:

```
users.store        → "New User" button
users.salary_field → Salary field
finance.margin_report → margin report
api.products.export → export endpoint
```

### Qualified key (disambiguating an `obj_key` collision)

`obj_key` resolution in the main (bare) map is **global** — `ptah:config:doctor`
flags two `PageObject`s on *different* pages sharing the same `obj_key` as an
"obj_key collision", because granting access to one silently grants it to the
other too (`buildPermissionMap()` ORs across every matching row, retro-compat,
unchanged).

Since v1.8.0, `check()` (and therefore `ptah_can()`, the `ptah.can` middleware
and `@ptahCan`) also accepts a **qualified key** to disambiguate that exact
case:

```
{page.slug}::{obj_key}              e.g. "sales::export"
{page.slug}::{section}::{obj_key}   e.g. "sales::toolbar::export"  (same page, two sections)
```

Resolution order in `check()`: the **bare** key is always tried first (so an
`obj_key` that literally contains `::` — unusual, but not forbidden — still
resolves via the bare map, never falling through by accident); the qualified
map is only consulted when the bare lookup misses **and** the requested key
actually contains `::` (`PermissionService::KEY_QUALIFIER`). MASTER still
short-circuits before either lookup. `getCompaniesForResource()` also accepts
a qualified key, decomposing it into the same page/section restriction.

```php
// Both objects share obj_key "export" — one on "sales", one on "finance".
ptah_can('export', 'read');              // bare — ORs both pages' grants (legacy behaviour)
ptah_can('sales::export', 'read');       // qualified — only the "sales" page's grant
ptah_can('finance::toolbar::export', 'read'); // qualified + section, when the same page repeats the key
```

`ptah:config:doctor`'s "obj_key collision" warning now also prints the
qualified form to use for each colliding page, e.g.
`use a chave qualificada: sales::export, finance::export`. See
[`getRoleNames`/`hasRole`](#getrolenamesmixed-user--null-int-companyid--null-array--hasrolemixed-user-stringarray-roles-int-companyid--null-bool)
above for an unrelated, identity-only mechanism — do not confuse the two.

> The `ptah.can` middleware parameter syntax is unaffected: `ptah.can:sales::export,read`
> still works, because Laravel's middleware pipeline splits parameters on the
> first `:` and then on `,` — `::` never collides with that.

### MASTER Role

The system allows exactly **1 role** with `is_master = true`. Users with this role have unrestricted access to all resources without going through the permission check. Ideal for system administrators.

> ⚠️ **MASTER is global, not company-scoped.** A user assigned the MASTER role
> passes every check in **every** company, regardless of which `company_id` the
> `UserRole` was bound to. There is intentionally no "master of company X only".
> Grant it sparingly.

### Cross-tenant roles (`company_id = null`)

A `UserRole` with `company_id = null` is a **global assignment**: it grants the
role's permissions in **every** company (`scopeForCompany` matches the company
*or* `NULL`). Combined with the OR logic across roles, a single global `UserRole`
is enough to bypass multi-company isolation for that user. Use `company_id = null`
only for genuinely cross-tenant access (e.g. a support role); for per-company
access, always bind the `UserRole` to a concrete `company_id`.

### Action whitelist

Only `create`, `read`, `update` and `delete` are valid actions
(`PermissionService::ACTIONS`). Any other string passed to `check()` /
`getCompaniesForResource()` is rejected before it reaches a query — the action
name is interpolated into a `can_{action}` column, so this guard prevents both
typos and SQL-injection attempts.

This whitelist is intentionally small and closed: because each entry is
interpolated into a `can_{action}` **column name**, adding a verb means adding
a column — a migration. `ptah`'s migrations are auto-discovered
(`loadMigrationsFrom` in the service provider), so a new one is **not**
opt-in for a consuming app: it fires on the very next `php artisan migrate`
that app runs, for any reason. Since the package ships to projects already in
production, a new verb is not something this module adds casually. See
"Capability as an object" below for how to grant a one-off action without
touching the schema.

### Capability as an object (no verb, no migration)

Sometimes you need to gate a single, one-off action that isn't part of the
CRUD quartet — e.g. "may open and save the in-app CRUD config editor", or
"may manage AI provider credentials". The tempting fix is a new verb
(`manage`, `configure`...), but that requires a new `can_*` column, which
requires a migration this package cannot ship as a side effect of someone
else's `php artisan migrate`.

The pattern this package uses instead: **the capability is the object, not
the verb.** Register a dedicated `PageObject` for the capability (its
`obj_key` names the capability itself, e.g. `crud.config`), and grant `read`
on it. `read` here doesn't mean "may view" — it means "has this capability" —
because for a single-purpose object there is nothing else to grant. The
CRUD quartet, the OR-across-roles logic and the MASTER bypass all keep
working exactly as they do for any other object; nothing about the action
whitelist needs to change.

**Example — `crud.config` (gates the in-app CRUD configuration editor):**

```php
// 1. Register the object once (e.g. via the /ptah-pages screen, or a seeder):
$page = PtahPage::firstOrCreate(['slug' => 'crud-config'], ['name' => 'CRUD Config']);
$object = PageObject::firstOrCreate(
    ['page_id' => $page->id, 'section' => 'main', 'obj_key' => 'crud.config'],
    ['obj_label' => 'Configure CRUD', 'obj_type' => 'page'] // label reads well as "Read = may configure"
);

// 2. Grant a role the capability — `can_read`, not a dedicated verb:
app(RoleService::class)->bindPageObject($role, $object->id, ['can_read' => true]);

// 3. Check it exactly like any other object:
ptah_can('crud.config', 'read', $user); // true for that role, false otherwise
```

`ptah_can_manage_config()` (`src/helpers.php`) and `AiModelConfigList`'s
`authorizeAiConfig()` both follow this pattern for `crud.config` and
`ai.config` respectively — see the comment on each call site for the full
rationale, and don't "fix" either one back to a dedicated verb without adding
the column via a migration a human reviews and runs by hand.

---

## Activation

### Via command (recommended)

```bash
php artisan ptah:module permissions
```

The command:
1. Activates the `company` module if not yet active (required dependency)
2. Publishes the 6 permission migrations
3. Runs `php artisan migrate`
4. Runs `DefaultAdminSeeder` (creates default company → department → MASTER role → admin user → link)
5. Displays the created admin credentials
6. Sets `PTAH_MODULE_PERMISSIONS=true` in `.env`

**Terminal output:**

```
  ╔══════════════════════════════════════════╗
  ║  Admin created successfully!             ║
  ║  E-mail  : admin@admin.com               ║
  ║  Password: <from PTAH_ADMIN_PASSWORD, or  ║
  ║            random — shown once here>      ║
  ║  ⚠ Change the password on first access! ║
  ╚══════════════════════════════════════════╝
```

### Via `.env`

```dotenv
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true
```

---

## Configuration

In `config/ptah.php`, `permissions` section:

```php
'permissions' => [
    // Permission cache on/off
    'cache'     => env('PTAH_PERMISSION_CACHE', true),
    'cache_ttl' => env('PTAH_PERMISSION_CACHE_TTL', 3600),   // seconds

    // User model of the host application
    // Does not need to extend any Ptah class
    'user_model'       => env('PTAH_USER_MODEL', \App\Models\User::class),

    // User PK field (default: 'id')
    'user_id_field'    => 'id',

    // Session key to identify user (when not using Auth::)
    'user_session_key' => 'ptah_user_id',

    // Session key for current company
    'company_session_key' => 'ptah_company_id',

    // Audit: master switch. When false, NOTHING is logged.
    'audit'         => env('PTAH_PERMISSION_AUDIT', false),
    // When audit is on: granted accesses are logged; audit_denied ALSO logs denials.
    'audit_denied'  => env('PTAH_PERMISSION_AUDIT_DENIED', true),
    'audit_master'  => env('PTAH_PERMISSION_AUDIT_MASTER', false),  // also log MASTER bypass grants
    // Retention window (days) for `ptah:audit-prune` — read with a `?? 90`
    // inline fallback in the command too, so a config file published before
    // this key existed still works.
    'audit_retention_days' => env('PTAH_PERMISSION_AUDIT_RETENTION_DAYS', 90),

    // Multi-company: uses company_session_key to filter permissions (default: true)
    'multi_company' => env('PTAH_MULTI_COMPANY', true),

    // Allow guest access (unauthenticated)
    'allow_guest'   => false,

    // Admin credentials for DefaultAdminSeeder
    'admin_name'     => env('PTAH_ADMIN_NAME', 'Administrator'),
    'admin_email'    => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    // No insecure default: when unset, a strong random password is generated and shown once at install.
    'admin_password' => env('PTAH_ADMIN_PASSWORD'),
],
```

---

## Database

### ptah_roles

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `department_id` | bigint FK | ✓ | Associated department |
| `name` | string | — | Profile name |
| `description` | text | ✓ | Free description |
| `color` | string | ✓ | Hex color for visual identification |
| `is_master` | boolean | — | Total permission bypass (max. 1) |
| `is_active` | boolean | — | Active/inactive |
| `deleted_at` | timestamp | ✓ | SoftDelete |

### ptah_pages

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `slug` | string unique | — | Page identifier (e.g.: `admin.users`) |
| `name` | string | — | Human-readable name |
| `description` | text | ✓ | — |
| `route` | string | ✓ | Laravel route name |
| `icon` | string | ✓ | Icon (emoji or class) |
| `is_active` | boolean | — | — |
| `sort_order` | integer | — | Display order |

> `ptah_pages` does **not** have `deleted_at` — pages are permanently deleted.

### ptah_page_objects

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `page_id` | bigint FK | — | Parent page |
| `section` | string | — | Section within the page (e.g.: `toolbar`, `form`, `tabs`) |
| `obj_key` | string | — | Unique verification key (e.g.: `users.store`) |
| `obj_label` | string | — | Human-readable label (e.g.: `Create user`) |
| `obj_type` | enum | — | `page` `button` `field` `link` `section` `api` `report` `tab` |
| `is_active` | boolean | — | — |
| `obj_order` | integer | — | Order within the section |

**Unique index:** `(page_id, section, obj_key)` — prevents duplicate objects per section.

### ptah_role_permissions

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `role_id` | bigint FK | — | Role |
| `page_object_id` | bigint FK | — | Page object |
| `can_create` | boolean | — | Create permission |
| `can_read` | boolean | — | Read permission |
| `can_update` | boolean | — | Edit permission |
| `can_delete` | boolean | — | Delete permission |
| `extra` | json | ✓ | Custom permissions beyond CRUD |
| `deleted_at` | timestamp | ✓ | SoftDelete |

### ptah_user_roles

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `user_id` | bigint | — | User ID (no FK — model agnostic) |
| `role_id` | bigint FK | — | Role |
| `company_id` | bigint | ✓ | Company (null = global role) |
| `is_active` | boolean | — | — |
| `deleted_at` | timestamp | ✓ | SoftDelete |

**Unique index:** `(user_id, role_id, company_id)`.

> `company_id = null` means **global role** — valid in any company. A user with `company_id = 5` on a role only has that role in company 5.

### ptah_permission_audits

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `user_id` | bigint | ✓ | User ID |
| `company_id` | bigint | ✓ | Company in context |
| `resource_key` | string | — | `obj_key` checked |
| `action` | string | — | `create` `read` `update` `delete` |
| `result` | enum | — | `granted` or `denied` |
| `ip_address` | string | ✓ | Request IP |
| `user_agent` | string | ✓ | User-Agent |
| `context` | json | ✓ | Extra context data |
| `created_at` | timestamp | — | No `updated_at` |

---

## Models

### Role

**Namespace:** `Ptah\Models\Role`  
**Traits:** `SoftDeletes`

```php
// Relationships
$role->department;   // BelongsTo(Department)
$role->permissions;  // HasMany(RolePermission)
$role->userRoles;    // HasMany(UserRole)
```

**Instance methods:**

| Method | Return | Description |
|---|---|---|
| `getBadgeLabel(): string` | string | `'👑 MASTER'` if `is_master`, otherwise the name |
| `getDisplayColor(): string` | string | Defined hex color or `'#6b7280'` (default gray) |

**Scopes:**

```php
Role::active()->get();   // WHERE is_active = 1
Role::master()->first(); // WHERE is_master = 1
```

> **Deactivating a role revokes it.** Setting `is_active = false` immediately stops
> the role from granting anything on the next `check()` (as of v1.4.1 the permission
> map filters inactive roles, consistent with the master/point-query paths). Soft-deleting
> the role or the `UserRole` assignment has the same effect.

---

### PtahPage

**Namespace:** `Ptah\Models\PtahPage`  
**No SoftDeletes**

```php
$page->pageObjects; // HasMany(PageObject)
```

**Scopes:**

```php
PtahPage::active()->get();   // WHERE is_active = 1
PtahPage::ordered()->get();  // ORDER BY sort_order ASC
```

---

### PageObject

**Namespace:** `Ptah\Models\PageObject`

```php
// Available types
PageObject::TYPES; // ['page','button','field','link','section','api','report','tab']

$obj->page; // BelongsTo(PtahPage)
```

**Scopes:**

```php
PageObject::active()->get();
PageObject::byKey('users.store')->first();
PageObject::byType('button')->get();
```

---

### RolePermission

**Namespace:** `Ptah\Models\RolePermission`  
**Traits:** `SoftDeletes`

**Methods:**

| Method | Return | Description |
|---|---|---|
| `allows(string $action): bool` | bool | Checks if `can_{action}` is `true`. Accepted actions: `create`, `read`, `update`, `delete` |
| `toCrudArray(): array` | array | Returns `['create'=>bool, 'read'=>bool, 'update'=>bool, 'delete'=>bool]` |

---

### UserRole

**Namespace:** `Ptah\Models\UserRole`  
**Traits:** `SoftDeletes`

```php
$userRole->role;    // BelongsTo(Role)
$userRole->company; // BelongsTo(Company)
```

**`forCompany` scope:**

```php
// global roles (company_id IS NULL)
UserRole::forCompany(null)->get();

// roles for company 5 + global roles
UserRole::forCompany(5)->get();
// WHERE (company_id = 5 OR company_id IS NULL)
```

---

### PermissionAudit

**Namespace:** `Ptah\Models\PermissionAudit`  
**No updated_at** (`UPDATED_AT = null`)

**Scopes:**

```php
PermissionAudit::granted()->get();
PermissionAudit::denied()->get();
PermissionAudit::forUser($userId)->get();
PermissionAudit::forResource('users.store')->get();
PermissionAudit::recent(50)->get();  // last 50 records
```

---

## PermissionService

**Namespace:** `Ptah\Services\Permission\PermissionService`  
**Contract:** `Ptah\Contracts\PermissionServiceContract`  
**Binding:** singleton

### Interface

```php
interface PermissionServiceContract
{
    public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool;
    public function isMaster(mixed $user = null): bool;
    public function getPermissions(mixed $user = null, ?int $companyId = null): array;
    public function getCompaniesForResource(mixed $user, string $objectKey, string $action): array;
    public function syncRole(mixed $user, int $roleId, array $companyIds = []): void;
    public function detachRole(mixed $user, int $roleId, ?int $companyId = null): void;
    public function clearCache(mixed $user = null, ?int $companyId = null): void;
}
```

> **Note the argument order:** on the contract, `$user` comes **first**
> (`check($user, $objectKey, $action)`). The global helper `ptah_can()` is the
> ergonomic inverse — object first (`ptah_can($objectKey, $action, $user)`).

### Methods

#### `check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool`

Checks whether the user has permission to perform `$action` on the `$objectKey` object.

**Internal flow:**

```
1. Resolve userId (Auth::id() / session / passed $user)
2. If allow_guest = false and userId = null → return false
3. isMaster(user)? → return true (no audit, unless audit_master = true)
4. Check cache: ptah_perm:{userId}:{companyId}:{objectKey}:{action}
5. Cache hit? → return cached value
6. DB: UserRole → RolePermission → can_{action}
   (OR across all roles: only 1 role needs to have the permission)
7. Write to cache
8. Write audit (if configured)
9. return $result
```

```php
// Contract: $user first (null = current auth / session). Prefer the ptah_can()
// helper in app code — it reads object-first.
$ok = app(PermissionServiceContract::class)->check(null, 'users.store', 'create');

// With explicit user and company
$ok = app(PermissionServiceContract::class)->check($user, 'finance.export', 'read', 3);

// Ergonomic helper (object-first):
$ok = ptah_can('users.store', 'create');
```

**`$user` parameter accepts:**

| Type | Behavior |
|---|---|
| `null` | Uses `Auth::id()` → fallback to `Session::get(user_session_key)` |
| `int` / `string` | Treated as user ID |
| `Authenticatable` / Model | Uses `$user->{user_id_field}` |

---

#### `isMaster(mixed $user = null): bool`

Checks whether the user has a role with `is_master = true`. Cached at `ptah_is_master:{userId}`.

```php
if (ptah_is_master()) {
    // full access
}
```

---

#### `getPermissions(mixed $user = null, ?int $companyId = null): array`

Returns the user's complete permissions map. Useful for loading permissions in the frontend.

```php
$perms = ptah_permissions();
// [
//   'users.store'      => ['can_create'=>true, 'can_read'=>true, 'can_update'=>false, 'can_delete'=>false],
//   'users.salary_field' => ['can_create'=>false, 'can_read'=>false, ...],
//   ...
// ]
```

For MASTER users, all objects return all flags as `true`.

---

#### `syncRole(mixed $user, int $roleId, array $companyIds = []): void`

Assigns a role to the user — one `UserRole` per company (or a single global one
when `$companyIds` is empty). A previously soft-deleted assignment is **restored**
via the SoftDeletes API. Invalidates the user's cache.

```php
$svc = app(PermissionServiceContract::class);

// Global role (valid in every company):
$svc->syncRole($user, $role->id);

// Role scoped to companies 5 and 9:
$svc->syncRole($user, $role->id, [5, 9]);
```

---

#### `detachRole(mixed $user, int $roleId, ?int $companyId = null): void`

Removes the user↔role assignment (soft-delete). With `$companyId`, only that
company's assignment is removed; without it, every company for that role. Invalidates the user's cache.

---

#### `clearCache(mixed $user = null, ?int $companyId = null): void`

Invalidates the permissions cache via **generation counters** (see [Cache](#cache)):
`clearCache($user)` bumps that user's counter (all companies at once);
`clearCache()` bumps the global counter (every user). Works on any cache driver —
no dependency on tag support. The `$companyId` argument is no longer needed.

---

#### `getRoleNames(mixed $user = null, ?int $companyId = null): array` / `hasRole(mixed $user, string|array $roles, ?int $companyId = null): bool`

**Not part of `PermissionServiceContract`** — these two live only on the
concrete `PermissionService` (and the `ptah_has_role()` helper below). They
answer **"which role(s) does this user hold"** — IDENTITY, not a GATE. Use
`ptah_can()` / `check()` to authorize an action; use `hasRole()` only for
role-name-based branching that isn't really a permission check (e.g. a
welcome banner that reads differently for a "Vendas" role).

```php
$service = app(\Ptah\Services\Permission\PermissionService::class);

$service->getRoleNames($user);              // ['Vendas Externas', 'Estoquista']
$service->hasRole($user, 'Vendas Externas'); // true
$service->hasRole($user, ['RH', 'Vendas Externas']); // true — array is an OR
```

Match is tolerant: case-insensitive and trimmed — and nothing looser.
Separators are **not** an equivalence class: `'Vendas Externas'` and
`'VENDAS EXTERNAS'` match each other, but `'vendas-externas'` does **not**
match either of them (an earlier version matched via `Str::slug()`, which
collapsed separators into an equivalence class and could make two distinct
role names collide — since `hasRole()` is identity that host apps branch on,
that was fixed). Only an active `UserRole` (in the given/resolved company
scope) bound to an active `Role` counts — the same activity rule the
permission maps apply.

> ⚠️ **MASTER does not satisfy `hasRole()` for an unrelated role name.**
> Unlike `check()`, there is deliberately **no MASTER short-circuit** here —
> a master user only bypasses permission checks; they don't "hold" every
> role name that happens to exist. `hasRole($masterUser, 'Vendas')` is
> `false` unless the master user is *also* literally assigned a role named
> "Vendas".

#### `ptah_has_role(string|array $roles, mixed $user = null, ?int $companyId = null): bool`

Global helper mirroring `hasRole()`, with the same object/role-first argument
order as the other `ptah_*` helpers (role(s) first, `$user` optional last):

```php
if (ptah_has_role('Vendas Externas')) {
    // …
}
```

---

## RoleService

**Namespace:** `Ptah\Services\Permission\RoleService`  
**Binding:** singleton

### Methods

| Method | Description |
|---|---|
| `create(array $data): Role` | Creates role. Validates no other MASTER exists if `is_master = true` |
| `update(Role $role, array $data): Role` | Updates role. Blocks `is_master` change if another already exists |
| `delete(Role $role): void` | Soft-delete. Throws `ValidationException` for MASTER role |
| `bindPageObject(Role $role, int $pageObjectId, array $perms): RolePermission` | Upsert permission for an object (uses `withTrashed`) |
| `syncPageBindings(Role $role, array $bindings): void` | Replaces all role permissions. Removes objects not present in the array; creates/updates those present |
| `getWithPermissions(Role $role): Role` | Eager loads: `permissions.pageObject.page` + `department` |

### `syncPageBindings` — array format

```php
$bindings = [
    [
        'page_object_id' => 12,
        'can_create'     => true,
        'can_read'       => true,
        'can_update'     => false,
        'can_delete'     => false,
    ],
    // ...
];

app(RoleService::class)->syncPageBindings($role, $bindings);
```

Objects with all flags set to `false` are ignored (no permission is created).

---

## Global Helpers

Defined in `src/helpers.php` — available globally via `autoload.files` in `composer.json`.

### `ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool`

Checks a permission. Shorthand for `PermissionService::check()`.

```php
if (ptah_can('users.store', 'create')) {
    // render "New user" button
}
```

### `ptah_is_master(mixed $user = null): bool`

Checks whether the user is MASTER.

```php
if (ptah_is_master()) {
    // show full administration panel
}
```

### `ptah_permissions(mixed $user = null, ?int $companyId = null): array`

Returns the user's complete permissions map.

```php
$perms = ptah_permissions();
// Pass to frontend via JavaScript:
// window.userPermissions = @json(ptah_permissions())
```

---

## Permission Facade

**Namespace:** `Ptah\Facades\Permission`

```php
use Ptah\Facades\Permission;

Permission::check('users.store', 'create');
Permission::isMaster();
Permission::getPermissions();
Permission::syncRole($userId, $roleId, $companyId);
Permission::detachRole($userRoleId);
Permission::clearCache();
```

---

## Blade Directives

Registered automatically in `PtahServiceProvider`:

### `@ptahCan / @endPtahCan`

```blade
@ptahCan('users.store', 'create')
    <x-forge-button wire:click="create" color="primary">New user</x-forge-button>
@endPtahCan
```

With explicit user and company:

```blade
@ptahCan('finance.export', 'read', $user, $companyId)
    <a href="/export">Export</a>
@endPtahCan
```

### `@ptahMaster / @endPtahMaster`

```blade
@ptahMaster
    <div class="admin-only-panel">...</div>
@endPtahMaster
```

---

## Middleware ptah.can

**Namespace:** `Ptah\Http\Middleware\PtahPermission`  
**Alias:** `ptah.can` (registered automatically)

### Usage in routes

```php
// routes/web.php
Route::get('/users/create', UserController::class . '@create')
    ->middleware('ptah.can:users.store,create');

Route::delete('/users/{id}', UserController::class . '@destroy')
    ->middleware('ptah.can:users.store,delete');

// With explicit company (optional — normally resolved from session)
Route::get('/finance/export', FinanceController::class . '@export')
    ->middleware('ptah.can:finance.export,read');
```

### Behavior

| Context | Response on denial |
|---|---|
| Request accepts JSON (`Accept: application/json`) | `HTTP 403` with `{"message":"Access denied.","object":"...","action":"..."}` |
| Web request | `abort(403)` — rendered by Ptah's themed 403 page (see [Configuration.md](Configuration.md#error-pages-errors)); the host's own `resources/views/errors/403.blade.php` wins if it exists |

### Parameter syntax

```
ptah.can:{objectKey},{action}[,{companyId}]
```

| Parameter | Required | Description |
|---|---|---|
| `objectKey` | ✓ | Object key (e.g.: `users.store`) |
| `action` | ✓ | `create`, `read`, `update` or `delete` |
| `companyId` | — | If omitted, resolved from session |

---

## Middleware ptah.master

**Namespace:** `Ptah\Http\Middleware\PtahMaster`
**Alias:** `ptah.master` (registered automatically) · since **v1.4.0**

Restricts a route to **MASTER** users — for screens that administer access
control itself. A non-master (even authenticated) request is refused with `403`
(JSON `{"error":"permission_denied"}` for API requests). No parameters.

```php
Route::get('/admin/roles', RoleController::class)
    ->middleware(['auth', 'ptah.master']);
```

The bundled ACL screens (`/ptah-roles`, `/ptah-pages`, `/ptah-users-acl`,
`/ptah-audit`, `/ptah-departments`, `/ptah-permission-guide`) already use it.

> Related: the in-app CRUD config editor is gated by the `@ptahCanManageConfig`
> Blade directive / `ptah_can_manage_config()` helper — MASTER, or a non-MASTER
> user with a **`read`** grant on the `crud.config` object (`can_read = true`
> on the role's `ptah_role_permissions` row), when the permissions module is
> on; otherwise the `PTAH_CONFIG_EDITOR` opt-in. See [BaseCrud.md](BaseCrud.md)
> and ["Capability as an object"](#capability-as-an-object-no-verb-no-migration)
> above for why it's `read` and not a dedicated verb. To grant it: register a
> `PageObject` with `obj_key = 'crud.config'` and bind the role with
> `RoleService::bindPageObject($role, $pageObjectId, ['can_read' => true])`.

---

## Administration Screens

### DepartmentList

**URL:** `/ptah-departments`  
**Component:** `Ptah\Livewire\Permission\DepartmentList`

Simple department CRUD. Displays linked roles count.

| Column | Description |
|---|---|
| Name | Department name |
| Description | Free text |
| Roles | Number of linked roles |
| Status | Active/Inactive |

---

### RoleList

**URL:** `/ptah-roles`  
**Component:** `Ptah\Livewire\Permission\RoleList`

Role CRUD + **object permissions modal**.

**Permissions Modal (Bind):**

Displays all `PageObject` items grouped by page/section. For each object, independent checkboxes for `can_read`, `can_create`, `can_update`, `can_delete`. On save, calls `RoleService::syncPageBindings()`.

```
┌─ Page: admin.users — Section: toolbar ────────────────────────────┐
│ New user (users.store) [button]   Read ✓ | Create ✓ | Edit ✗ | Delete ✗ │
│ Export (users.export)  [button]   Read ✓ | Create ✗ | Edit ✗ | Delete ✗ │
├─ Page: admin.users — Section: form ───────────────────────────────┤
│ Salary field (users.salary_field) [field]   Read ✓ | Create ✓ | Edit ✗ | Delete ✗ │
└────────────────────────────────────────────────────────────────────┘
```

**Protections:**
- MASTER role cannot be deleted
- Delete button does not appear for MASTER roles

---

### PageList

**URL:** `/ptah-pages`  
**Component:** `Ptah\Livewire\Permission\PageList`

Two-column interface:
- **Left:** list of pages (`PtahPage`) with object counter
- **Right:** objects of the selected page

**Recommended registration flow:**

```
1. Identify the screens in your system
2. Register each screen with a unique slug (e.g.: admin.users, finance.accounts)
3. For each screen, register the controllable objects:
   - Action buttons (create, export, approve)
   - Sensitive fields (salary, margin, maximum discount)
   - Tabs (financial history, personal data)
   - Reports (P&L, balance sheet)
   - API endpoints (/api/export)
4. Go to /ptah-roles and configure permissions for each role
```

---

### UserPermissionList

**URL:** `/ptah-users-acl`  
**Component:** `Ptah\Livewire\Permission\UserPermissionList`

Lists all users from the configured model (`config('ptah.permissions.user_model')`). For each user, displays assigned roles as badges.

**Access management modal:**
- Lists assigned roles with a "Remove" button (except MASTER roles)
- Form to add a new role (role select + company select)
- "Global" company = `company_id = null` (role valid in all companies)

**Filter by role:** select of all roles to filter the user list.

---

### Filtering Users in the Permissions Screen

By default, `UserPermissionList` queries **all records** from the configured `user_model`. In projects with multiple user types (e.g. an e-commerce with both customers and admin users), you may want to restrict which users appear on this screen.

Use `user_query_scope` to apply an [Eloquent Scope](https://laravel.com/docs/eloquent#global-scopes) to that query — without touching vendor files or creating model inheritance chains.

**1. Create the scope** in `app/Scopes/AdminUsersScope.php`:

```php
namespace App\Scopes;

use Illuminate\Database\Eloquent\{Builder, Model, Scope};

class AdminUsersScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Example: restrict to users with type = 'admin'
        $builder->where('type', 'admin');
    }
}
```

**2. Register it in `config/ptah.php`** (or via `.env`):

```php
// config/ptah.php
'permissions' => [
    'user_query_scope' => App\Scopes\AdminUsersScope::class,
    // ... other keys
],
```

or in `.env`:

```env
PTAH_USER_QUERY_SCOPE=App\Scopes\AdminUsersScope
```

**How it works:** the scope is applied via `withGlobalScope('ptah_user_scope', new $scopeClass)` — only for the permissions screen query. It does **not** affect any other part of the application. If the class does not exist, the key is silently ignored.

> **Note:** the scope must implement `\Illuminate\Database\Eloquent\Scope`. Any `where`, `join`, `orderBy` or other Eloquent constraint is valid.

---

### AuditList

**URL:** `/ptah-audit`  
**Component:** `Ptah\Livewire\Permission\AuditList`

Read-only. Available filters:

| Filter | Options |
|---|---|
| Text search | resource_key, ip_address, user_id |
| Result | All / Granted / Denied |
| Action | All / Create / Read / Edit / Delete |
| Start date | Date picker |
| End date | Date picker |

---

### PermissionGuide

**URL:** `/ptah-permission-guide`  
**Component:** `Ptah\Livewire\Permission\PermissionGuide`  
**Route:** `ptah.acl.guide`

Interactive documentation screen for the permissions system. Displayed in the navbar (link "Permissions guide") when `config('ptah.modules.permissions')` is active and the route exists.

**Available tabs:**

| Tab | `$activeTab` | Content |
|---|---|---|
| Overview | `overview` | Architecture diagram, core concepts (Role, Page, Object, MASTER, Company, Audit) and the real 6-node decision flow |
| Step by Step | `setup` | 5 guided steps with direct links to each ACL module screen, plus `colsPermission` |
| Code Examples | `code` | Plain, escaped snippets: `ptah_can()` in Blade (including a qualified key), `ptah.can` middleware, `PermissionServiceContract::check()`, a plain Livewire component (no dedicated trait exists) and the real `.env` audit/cache variables |
| FAQ | `faq` | 10 Alpine accordions with frequently asked questions, sourced from `guide_faq_*` in both locales |

**Livewire property:**

| Property | Type | Default | Description |
|---|---|---|---|
| `$activeTab` | string | `'overview'` | Currently selected tab |

**File:** `src/Livewire/Permission/PermissionGuide.php`  
**View:** `resources/views/livewire/permission/permission-guide.blade.php`

> **Blade escaping in code examples:** when including Blade directives or `{{ }}` expressions as literal text inside spans/code examples, use `&#64;if(...)` to escape `@` and `@{{ $var }}` to escape `{{ }}` — otherwise Blade evaluates the expressions normally, generating ParseError or ErrorException.

---

## Routes

Registered automatically when `ptah.modules.permissions = true`:

| Method | URI | Name | Protection |
|---|---|---|---|
| `GET` | `/ptah-departments` | `ptah.acl.departments` | `web`, `auth`, `ptah.master` |
| `GET` | `/ptah-roles` | `ptah.acl.roles` | `web`, `auth`, `ptah.master` |
| `GET` | `/ptah-pages` | `ptah.acl.pages` | `web`, `auth`, `ptah.master` |
| `GET` | `/ptah-users-acl` | `ptah.acl.users` | `web`, `auth`, `ptah.master` |
| `GET` | `/ptah-audit` | `ptah.acl.audit` | `web`, `auth`, `ptah.master` |
| `GET` | `/ptah-permission-guide` | `ptah.acl.guide` | `web`, `auth`, `ptah.master` |

> Since **v1.4.0** these screens administer the access-control system itself and
> are therefore **master-only** — guarded by the `ptah.master` middleware (below).
> A non-master authenticated user gets `403`.

---

## Seeders

### DefaultCompanySeeder

Creates the default company (idempotent). See [Company.md](Company.md) for details.

### DefaultAdminSeeder

**Namespace:** `Ptah\Seeders\DefaultAdminSeeder`

Creates the entire chain idempotently:

```
1. Default company (via DefaultCompanySeeder)
2. "Administration" department (firstOrCreate)
3. MASTER role (firstOrCreate via department + name)
4. Admin user (firstOrCreate via email, reads from config('ptah.permissions.admin_*'))
5. UserRole: admin → MASTER → default company
```

```php
// Manual execution
php artisan db:seed --class="Ptah\Seeders\DefaultAdminSeeder"
```

**Credentials configuration** (`.env`):

```dotenv
PTAH_ADMIN_NAME="Administrator"
PTAH_ADMIN_EMAIL="admin@myapp.com"
PTAH_ADMIN_PASSWORD="secure-password-here"
```

---

## Verification Flow

```
ptah_can('users.store', 'create')
  │
  ├─ resolve userId
  │     Auth::id() → Session::get('ptah_user_id') → null
  │
  ├─ userId null?
  │     allow_guest = false → return false
  │
  ├─ isMaster(userId)?
  │     Cache: ptah_is_master:{userId}
  │     DB: UserRole join Role WHERE is_master=1
  │     true → return true  (+ audit if audit_master)
  │
  ├─ Cache hit?
  │     ptah_perm:{userId}:{companyId}:users.store:create
  │     true/false → return cached
  │
  ├─ DB query
  │     UserRole (forCompany)
  │       → RolePermission (page_object.obj_key = 'users.store')
  │           → OR across all roles: can_create
  │
  ├─ Cache write (TTL: cache_ttl seconds)
  │
  ├─ Audit (if audit=true: log granted; also log denied when audit_denied)
  │
  └─ return bool
```

---

## Audit

Enabled via `.env`. **`audit` is the master switch — when it is off, nothing is
logged at all**, regardless of the other two flags.

```dotenv
PTAH_PERMISSION_AUDIT=true           # ON: log granted accesses
PTAH_PERMISSION_AUDIT_DENIED=true    # + also log denied accesses (default: true)
PTAH_PERMISSION_AUDIT_MASTER=false   # + also log MASTER bypass grants (default: false)
```

Resulting behaviour:

| `audit` | `audit_denied` | Logged |
|---|---|---|
| `false` | (any) | nothing |
| `true` | `false` | granted only |
| `true` | `true` | granted **and** denied |

`audit_master` is independent: with `audit=true`, it additionally logs the grants
of MASTER users (off by default, since master passes everything).

**Recommendations:**
- To capture only unauthorized attempts, you still need `PTAH_PERMISSION_AUDIT=true` (the switch) — there is no denied-only-without-audit mode.
- For full compliance, `PTAH_PERMISSION_AUDIT=true` (+ `audit_master=true` if master activity must be traceable).
- For debugging, add custom context via `PermissionAudit::create([..., 'context' => ['request_id' => ...]])`.

The audit screen (`/ptah-audit`) displays records with filters and pagination.

---

## Cache

The permission map and MASTER flag are cached per user (and per company) for
`cache_ttl` seconds. The check path reads a single cached map per `(user,
company)` — the **single source of truth** — so there is no risk of an individual
check disagreeing with the full map.

### Generation-based invalidation (works on every driver)

Each cache key embeds two **generation counters**:

```
ptah_perms_map:g{global}:u{user}:{userId}:{companyId}
ptah_is_master:g{global}:u{user}:{userId}
ptah_master_map:g{global}
```

Invalidation just **increments a counter**, which instantly orphans every key of
that generation — O(1), no key enumeration and **no dependency on cache tags**
(so it behaves identically on the `file`, `database`, `redis` and `memcached`
drivers):

| Counter | Bumped when | Scope cleared |
|---|---|---|
| `ptah_perm_gver` (global) | any **Role** or **RolePermission** changes | every user, every company |
| `ptah_perm_uver:{userId}` | a user's **UserRole** assignments change | that user, all companies |

This is wired automatically:

- **Model observers** (registered by the service provider) bump the counters on
  `saved` / `deleted` / `restored` of `Role`, `RolePermission` and `UserRole`.
- **`RoleService`** also bumps the global counter explicitly after `create`,
  `update`, `delete`, `bindPageObject`, `unbindPageObject` and
  `syncPageBindings` — because query-builder mass deletes don't fire model
  events. Belt and suspenders.
- **`syncRole()` / `detachRole()`** bump the affected user's counter.

The practical guarantee: **revoking a permission takes effect on the very next
check**, never after the TTL. (Regression test:
`PermissionServiceTest::revoking_a_permission_takes_effect_immediately`.)

**Manual invalidation:**

```php
Permission::clearCache($user);   // one user, all companies (bumps the user counter)
Permission::clearCache();        // everyone (bumps the global counter)
```

> The `$companyId` argument of `clearCache()` is no longer needed — a single
> per-user bump clears every company-scoped map for that user at once.

### Request-scoped memo (since v1.8.0)

On top of the byte-level cache above, `PermissionService` keeps a **request-scoped
memo** (a plain PHP array on the instance) for `isMasterById()`, `getPermissions()`,
`getQualifiedPermissions()` and `getRoleNames()`. Repeating the same lookup within
one request/instance skips the database (and, when `cache=true`, even the cache
store round-trip) entirely on the 2nd+ call — this matters most with
`ptah.permissions.cache = false`, where every `check()` would otherwise hit the
database again for the very same user/company/action within the same request.

The memo key is the *same* generation-versioned string `cacheKey()` already
computes fresh on every call — so a mid-request revocation (e.g. via `RoleService`,
which bumps the shared generation counter) changes the key on the very next call
and is picked up immediately, exactly like the cache-based invalidation already
guarantees. `bumpGlobalVersion()` / `bumpUserVersion()` also wipe the memo array
outright, as a second line of defense. Bounded at 100 entries (`MEMO_MAX`) to
guard against unbounded growth on a long-running worker (Octane) — once
exceeded, the memo is wiped rather than left to grow.

> **Limitation:** a generation bump from a genuinely different OS process/worker
> is only visible on THIS instance's *next* request — there is nothing to
> memoize across processes. Within a single request/instance (including the
> normal one-singleton-per-HTTP-request wiring), revocation stays immediate.

---

## Integration with Auth and BaseCrud

### Integration with the Auth module

When both `auth` and `permissions` are active, `PermissionService` resolves the user via `Auth::id()` automatically — no additional configuration needed.

### Integration with BaseCrud

To add permission control to a BaseCrud screen, use the `readOnly` parameter combined with view-level checks:

```blade
@livewire('ptah-base-crud', [
    'model'    => 'Product',
    'canCreate' => ptah_can('products.store', 'create'),
    'canEdit'   => ptah_can('products.store', 'update'),
    'canDelete' => ptah_can('products.store', 'delete'),
    'canExport' => ptah_can('products.export', 'read'),
])
```

Or full control via `readOnly` for read-only screens:

```blade
@livewire('ptah-base-crud', [
    'model'    => 'Product',
    'readOnly' => !ptah_can('products.store', 'update'),
])
```

---

## Column-level Permissions

Beyond gating whole screens/actions (`ptah_can()`), a single BaseCrud **column**
can be hidden from every user who lacks a specific grant — the same
`ptah_page_objects` / `ptah_role_permissions` machinery, applied one column at
a time instead of one screen at a time.

### How it works

A column opts into the gate via the `colsPermission` tag in its `cols[]`
entry (see [Configuration.md § Column Configuration](Configuration.md#column-configuration)):

```json
{ "colsNomeFisico": "cost", "colsNomeLogico": "Cost", "colsTipo": "number", "colsPermission": "purchase.view_cost" }
```

- **Empty / absent `colsPermission`** (the default for every column) → the
  column is **public** — no gate, byte-identical to a screen that never used
  this feature.
- **Non-empty `colsPermission`** → `ColumnPermissionService` (`Ptah\Services\Permission\ColumnPermissionService`)
  filters the column out of the header, the data cells, the card view, the
  column-visibility dropdown, the export, and the async export job — for
  every user who does not hold a `read` grant on that `obj_key` — **before**
  the column ever reaches the Livewire component's public state, the query
  builder or the view.
- A colliding `obj_key` (registered on more than one page — see
  `ptah:config:doctor`'s "obj_key collision" check) is disambiguated with the
  qualified form `{page.slug}::{obj_key}` (or `{page.slug}::{section}::{obj_key}`),
  exactly like a regular `ptah_can()` check (see [§ Qualified key](#qualified-key-disambiguating-an-obj_key-collision)).
- This is a **READ** gate only. Create/update/delete authorization for the
  CRUD as a whole is unaffected.

### Setting it up

1. **Register the `PageObject`** the column will be gated by — via the
   PageList administration screen, or directly:
   ```php
   $page = PtahPage::firstOrCreate(['slug' => 'purchase-orders'], ['name' => 'Purchase Orders']);
   PageObject::create([
       'page_id' => $page->id, 'section' => 'main',
       'obj_key' => 'purchase.view_cost', 'obj_label' => 'View cost column',
       'obj_type' => 'field', 'is_active' => true,
   ]);
   ```
   Or, equivalently, the raw SQL for the initial keys — **human execution
   only**, never run automatically by an agent/script:
   ```sql
   -- human execution — review page_id/section before running
   INSERT INTO ptah_pages (slug, name, is_active, created_at, updated_at)
   VALUES ('purchase-orders', 'Purchase Orders', 1, NOW(), NOW());

   INSERT INTO ptah_page_objects (page_id, section, obj_key, obj_label, obj_type, is_active, created_at, updated_at)
   VALUES (LAST_INSERT_ID(), 'main', 'purchase.view_cost', 'View cost column', 'field', 1, NOW(), NOW());
   ```
2. **Tag the column** with that `obj_key` — either:
   - visually, in the BaseCrud config editor: Columns tab → edit the column →
     "Visibility permission" select (only rendered when the `permissions`
     module is on) — a `<select>` of every active `PageObject` on an active
     page, never free text (a typo in a text field would mean "nobody sees
     it", silently); or
   - via the CLI: `php artisan ptah:config Product --column="cost:number:permission=purchase.view_cost"`.
3. **Grant `read`** on that `obj_key` to the roles that should see the column
   — RoleList administration screen, or `RolePermission::create([... 'can_read' => true])`.

### Default-closed

Tagging a column that is **today public** immediately hides it from
**everyone** — including users who already saw it — until the grant above is
made for their role. There is no "grandfather" period. The editor's hint text
next to the select restates this.

### Cache invalidation

Like every other permission check, this gate reads the same generation-versioned
cache `PermissionService` already maintains (see [§ Cache](#cache)) — granting
or revoking a role's `read` flag bumps the global/user generation counter via
the existing model observers, which instantly invalidates every cached
permission map. **No `cache:clear` (or any cache command) is ever needed** for
a column-permission change to take effect on the next request.

### No audit trail for column checks

Unlike `ptah_can()`/`check()`, the column-level gate **does not** write to
`ptah_permission_audits` — a conscious decision, not an oversight. A single
list render can touch `N` columns; auditing every column check on every
render (`N` columns × `N` renders) would flood the audit table with rows no
one queries for insight. The screen-level `ptah_can()` audit (when
`ptah.permissions.audit` is on) still covers the CRUD as a whole.

### Diagnostics: `ptah:config:doctor`

`ptah:config:doctor` warns (never errors — a column simply not yet wired to a
real object is a normal work-in-progress state) when a `colsPermission` tag
names no registered `PageObject` at all:

```
🟡 unknown column permission key [Product]: 'purchase.view_cost' não corresponde a nenhum ptah_page_objects registrado — a coluna fica invisível para todos, exceto MASTER, até que a chave seja cadastrada
```

---

## Practical Examples

### Example 1 — Conditional button in view

```blade
{{-- resources/views/users/index.blade.php --}}
@ptahCan('users.store', 'create')
    <x-forge-button wire:click="create">New User</x-forge-button>
@endPtahCan

@ptahCan('users.salary_field', 'read')
    <td>{{ $user->salary }}</td>
@else
    <td>***</td>
@endPtahCan
```

### Example 2 — Protected route

```php
// routes/web.php
Route::get('/admin/users', UserController::class . '@index')
    ->middleware(['auth', 'ptah.can:users.index,read'])
    ->name('admin.users.index');

Route::post('/admin/users', UserController::class . '@store')
    ->middleware(['auth', 'ptah.can:users.store,create'])
    ->name('admin.users.store');
```

### Example 3 — Check in Service

```php
// app/Services/UserService.php
use Ptah\Contracts\PermissionServiceContract;

class UserService
{
    public function __construct(
        private PermissionServiceContract $permissions,
    ) {}

    public function updateSalary(int $userId, float $salary): void
    {
        if (!$this->permissions->check('users.salary_field', 'update')) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        User::findOrFail($userId)->update(['salary' => $salary]);
    }
}
```

### Example 4 — Pass to frontend via JavaScript

```blade
{{-- In the layout --}}
<script>
    window.PtahUser = {
        isMaster: @json(ptah_is_master()),
        permissions: @json(ptah_permissions()),
    };
</script>
```

```js
// In JavaScript
if (window.PtahUser.permissions['users.store']?.can_create) {
    showCreateButton();
}
```

### Example 5 — Company selector in navbar

```php
// app/Livewire/CompanySwitcher.php
class CompanySwitcher extends Component
{
    public function switch(int $companyId): void
    {
        app(\Ptah\Contracts\CompanyServiceContract::class)->setCurrentCompany($companyId);
        app(\Ptah\Contracts\PermissionServiceContract::class)->clearCache();
        $this->redirect(request()->header('Referer') ?? '/dashboard');
    }
}
```

---

## Configuration Reference

```php
// config/ptah.php

'modules' => [
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
],

'permissions' => [
    'cache'              => env('PTAH_PERMISSION_CACHE', true),
    'cache_ttl'          => env('PTAH_PERMISSION_CACHE_TTL', 3600),
    'user_model'         => env('PTAH_USER_MODEL', \App\Models\User::class),
    'user_id_field'      => 'id',
    // Optional: Eloquent Scope class to filter users shown in the
    // Permissions screen (e.g. only admin users in a multi-type project).
    'user_query_scope'   => env('PTAH_USER_QUERY_SCOPE', null),
    'user_session_key'   => 'ptah_user_id',
    'company_session_key'=> 'ptah_company_id',
    'audit'              => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'       => env('PTAH_PERMISSION_AUDIT_DENIED', true),
    'audit_master'       => env('PTAH_PERMISSION_AUDIT_MASTER', false),
    'audit_retention_days' => env('PTAH_PERMISSION_AUDIT_RETENTION_DAYS', 90),
    'multi_company'      => env('PTAH_MULTI_COMPANY', true),
    'allow_guest'        => env('PTAH_PERMISSION_ALLOW_GUEST', false),
    'admin_name'         => env('PTAH_ADMIN_NAME', 'Administrator'),
    'admin_email'        => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    // No insecure default: when unset, a strong random password is generated and shown once at install.
    'admin_password'     => env('PTAH_ADMIN_PASSWORD'),
],
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `PTAH_MODULE_COMPANY` | `false` | Activates the company module |
| `PTAH_MODULE_PERMISSIONS` | `false` | Activates the permissions module |
| `PTAH_USER_MODEL` | `App\Models\User` | FQCN of the users model |
| `PTAH_USER_QUERY_SCOPE` | `null` | FQCN of an Eloquent Scope to filter users shown in the Permissions screen |
| `PTAH_PERMISSION_CACHE` | `true` | Enables permissions cache |
| `PTAH_PERMISSION_CACHE_TTL` | `3600` | Cache TTL in seconds |
| `PTAH_PERMISSION_AUDIT` | `false` | Audits all accesses |
| `PTAH_PERMISSION_AUDIT_DENIED` | `true` | Audits only denied accesses |
| `PTAH_PERMISSION_AUDIT_MASTER` | `false` | Audits MASTER bypass |
| `PTAH_PERMISSION_AUDIT_RETENTION_DAYS` | `90` | Default `--days` window for `ptah:audit-prune` |
| `PTAH_MULTI_COMPANY` | `true` | Permissions filtered by company |
| `PTAH_ADMIN_EMAIL` | `admin@admin.com` | Default admin e-mail |
| `PTAH_ADMIN_PASSWORD` | *(none)* | Admin password. If unset, a strong random one is generated and shown once at install — no fixed default |

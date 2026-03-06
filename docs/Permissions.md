# Permissions Module — Complete Documentation

**Package:** `jonytonet/ptah`  
**Namespace:** `Ptah\Services\Permission`, `Ptah\Livewire\Permission`  
**Livewire:** 3.x | **Laravel:** 11+

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
    - [AuditList](#auditlist)
    - [PermissionGuide](#permissionguide)
14. [Routes](#routes)
15. [Seeders](#seeders)
16. [Verification Flow](#verification-flow)
17. [Audit](#audit)
18. [Cache](#cache)
19. [Integration with Auth and BaseCrud](#integration-with-auth-and-basecrud)
20. [Practical Examples](#practical-examples)
21. [Configuration Reference](#configuration-reference)

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
| Global helpers | `ptah_can()`, `ptah_is_master()`, `ptah_permissions()` |
| 5 admin screens | Departments, Roles, Pages/Objects, Users, Audit |

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

### MASTER Role

The system allows exactly **1 role** with `is_master = true`. Users with this role have unrestricted access to all resources without going through the permission check. Ideal for system administrators.

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
  ║  Password: admin@123                     ║
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
    'cache_ttl' => env('PTAH_PERMISSION_CACHE_TTL', 300),   // seconds

    // User model of the host application
    // Does not need to extend any Ptah class
    'user_model'       => env('PTAH_USER_MODEL', \App\Models\User::class),

    // User PK field (default: 'id')
    'user_id_field'    => 'id',

    // Session key to identify user (when not using Auth::)
    'user_session_key' => 'ptah_user_id',

    // Session key for current company
    'company_session_key' => 'ptah_company_id',

    // Audit: record verification logs
    'audit'         => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'  => env('PTAH_PERMISSION_AUDIT_DENIED', true),   // always audits denied
    'audit_master'  => env('PTAH_PERMISSION_AUDIT_MASTER', false),  // audits MASTER bypass

    // Multi-company: uses company_session_key to filter permissions
    'multi_company' => env('PTAH_MULTI_COMPANY', false),

    // Allow guest access (unauthenticated)
    'allow_guest'   => false,

    // Admin credentials for DefaultAdminSeeder
    'admin_name'     => env('PTAH_ADMIN_NAME', 'Administrator'),
    'admin_email'    => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    'admin_password' => env('PTAH_ADMIN_PASSWORD', 'admin@123'),
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
| `toCrudArray(): array` | array | Returns `['can_create'=>bool, 'can_read'=>bool, 'can_update'=>bool, 'can_delete'=>bool]` |

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
    public function check(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool;
    public function isMaster(mixed $user = null): bool;
    public function getPermissions(mixed $user = null, ?int $companyId = null): array;
    public function syncRole(int $userId, int $roleId, ?int $companyId = null): UserRole;
    public function detachRole(int $userRoleId): void;
    public function clearCache(mixed $user = null, ?int $companyId = null): void;
}
```

### Methods

#### `check(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool`

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
// Simple check (uses Auth::id() and CompanyService::getCurrentCompanyId())
$ok = app(PermissionServiceContract::class)->check('users.store', 'create');

// With explicit user and company
$ok = app(PermissionServiceContract::class)->check('finance.export', 'read', $user, 3);
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

#### `syncRole(int $userId, int $roleId, ?int $companyId = null): UserRole`

Assigns a role to the user. Uses `firstOrCreate` with `withTrashed()` — safe for reactivating deleted links.

```php
$userRole = app(PermissionServiceContract::class)->syncRole(
    userId: $user->id,
    roleId: $role->id,
    companyId: 5  // null for global role
);
```

---

#### `detachRole(int $userRoleId): void`

Removes a user-role link (soft-delete). Automatically invalidates the user's cache.

---

#### `clearCache(mixed $user = null, ?int $companyId = null): void`

Invalidates the permissions cache. Tries to use `Cache::tags(['ptah_permissions'])` when available (Redis/Memcached); falls back to individual key removal on drivers without tag support.

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
| Web request | `abort(403)` — Laravel's default error page |

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
| Overview | `overview` | Architecture diagram, core concepts (Role, Page, Object, MASTER, Company, Audit) and visual decision flow |
| Step by Step | `setup` | 5 guided steps with direct links to each ACL module screen |
| Code Examples | `code` | Highlighted snippets: `ptah_can()` in Blade, `ptah.can` middleware, `PermissionService`, `HasPermission` in Livewire and `.env` variables |
| FAQ | `faq` | 8 Alpine accordions with frequently asked questions |

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
| `GET` | `/ptah-departments` | `ptah.acl.departments` | `web`, `auth` |
| `GET` | `/ptah-roles` | `ptah.acl.roles` | `web`, `auth` |
| `GET` | `/ptah-pages` | `ptah.acl.pages` | `web`, `auth` |
| `GET` | `/ptah-users-acl` | `ptah.acl.users` | `web`, `auth` |
| `GET` | `/ptah-audit` | `ptah.acl.audit` | `web`, `auth` |
| `GET` | `/ptah-permission-guide` | `ptah.acl.guide` | `web`, `auth` |

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
  ├─ Audit (if audit=true or audit_denied=true and result=false)
  │
  └─ return bool
```

---

## Audit

Enabled via `.env`:

```dotenv
PTAH_PERMISSION_AUDIT=true           # audits ALL (granted + denied)
PTAH_PERMISSION_AUDIT_DENIED=true    # audits only denied (default: true)
PTAH_PERMISSION_AUDIT_MASTER=false   # audits MASTER bypass (default: false)
```

**Recommendations:**
- In production with high volume, use `PTAH_PERMISSION_AUDIT=false` + `PTAH_PERMISSION_AUDIT_DENIED=true` — only logs what was denied (unauthorized attempts)
- For full compliance, use `PTAH_PERMISSION_AUDIT=true`
- For debugging, add custom context via `PermissionAudit::create([..., 'context' => ['request_id' => ...]])`

The audit screen (`/ptah-audit`) displays records with filters and pagination.

---

## Cache

### Cache keys

| Key | Content | TTL |
|---|---|---|
| `ptah_perm:{userId}:{companyId}:{objectKey}:{action}` | `bool` | `cache_ttl` (default 300s) |
| `ptah_is_master:{userId}` | `bool` | `cache_ttl` |
| `ptah_company_default` | `Company` | `cache_ttl` |
| `ptah_user_companies:{userId}` | `Collection` | `cache_ttl` |

### Invalidation

Cache is automatically invalidated when:
- `detachRole()` is called (invalidates the user)
- `syncRole()` is called (invalidates the user)  
- `syncPageBindings()` is called (invalidates all users with that role)

**Manual invalidation:**

```php
// Invalidate cache for a user
Permission::clearCache($user);

// Invalidate cache for a user in a specific company
Permission::clearCache($user, $companyId);

// Invalidate everything (tags — requires Redis/Memcached driver)
Cache::tags(['ptah_permissions'])->flush();
```

### Cache tags

When the cache driver supports tags (Redis, Memcached), all keys are stored with the `ptah_permissions` tag. This allows atomic `flush()` of the entire module. On drivers without tag support (file, database), `clearCache()` removes keys individually.

---

## Integration with Auth and BaseCrud

### Integration with the Auth module

When both `auth` and `permissions` are active, `PermissionService` resolves the user via `Auth::id()` automatically — no additional configuration needed.

### Integration with BaseCrud

To add permission control to a BaseCrud screen, use the `readOnly` parameter combined with view-level checks:

```blade
@livewire('ptah::base-crud', [
    'model'    => 'Product',
    'canCreate' => ptah_can('products.store', 'create'),
    'canEdit'   => ptah_can('products.store', 'update'),
    'canDelete' => ptah_can('products.store', 'delete'),
    'canExport' => ptah_can('products.export', 'read'),
])
```

Or full control via `readOnly` for read-only screens:

```blade
@livewire('ptah::base-crud', [
    'model'    => 'Product',
    'readOnly' => !ptah_can('products.store', 'update'),
])
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
    'cache_ttl'          => env('PTAH_PERMISSION_CACHE_TTL', 300),
    'user_model'         => env('PTAH_USER_MODEL', \App\Models\User::class),
    'user_id_field'      => 'id',
    'user_session_key'   => 'ptah_user_id',
    'company_session_key'=> 'ptah_company_id',
    'audit'              => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'       => env('PTAH_PERMISSION_AUDIT_DENIED', true),
    'audit_master'       => env('PTAH_PERMISSION_AUDIT_MASTER', false),
    'multi_company'      => env('PTAH_MULTI_COMPANY', false),
    'allow_guest'        => false,
    'admin_name'         => env('PTAH_ADMIN_NAME', 'Administrator'),
    'admin_email'        => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    'admin_password'     => env('PTAH_ADMIN_PASSWORD', 'admin@123'),
],
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `PTAH_MODULE_COMPANY` | `false` | Activates the company module |
| `PTAH_MODULE_PERMISSIONS` | `false` | Activates the permissions module |
| `PTAH_USER_MODEL` | `App\Models\User` | FQCN of the users model |
| `PTAH_PERMISSION_CACHE` | `true` | Enables permissions cache |
| `PTAH_PERMISSION_CACHE_TTL` | `300` | Cache TTL in seconds |
| `PTAH_PERMISSION_AUDIT` | `false` | Audits all accesses |
| `PTAH_PERMISSION_AUDIT_DENIED` | `true` | Audits only denied accesses |
| `PTAH_PERMISSION_AUDIT_MASTER` | `false` | Audits MASTER bypass |
| `PTAH_MULTI_COMPANY` | `false` | Permissions filtered by company |
| `PTAH_ADMIN_EMAIL` | `admin@admin.com` | Default admin e-mail |
| `PTAH_ADMIN_PASSWORD` | `admin@123` | Default admin password |

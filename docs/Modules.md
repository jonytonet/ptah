# Optional Modules — Auth, Menu, Company & Permissions

**Pacote:** `jonytonet/ptah`  
**Minimum version:** see tags in the repository  
**Laravel:** 11+ | **Livewire:** 3.x

---

## Summary

1. [Overview](#overview)
2. [Activating the Modules](#activating-the-modules)
3. [Auth Module](#auth-module)
   - [Configuration](#configuration-auth)
   - [Routes](#routes)
   - [Livewire Components](#livewire-components)
   - [LoginPage](#loginpage)
   - [ForgotPasswordPage](#forgotpasswordpage)
   - [ResetPasswordPage](#resetpasswordpage)
   - [TwoFactorChallengePage](#twofactorchallengepage)
   - [ProfilePage](#profilepage)
   - [Dashboard](#dashboard)
4. [2FA Authentication](#2fa-authentication)
   - [TOTP (Authenticator App)](#totp-authenticator-app)
   - [E-mail OTP](#e-mail-otp)
   - [Recovery Codes](#recovery-codes)
   - [TwoFactorService](#twofactorservice)
5. [Session Management](#session-management)
   - [SessionService](#sessionservice)
6. [Menu Module](#menu-module)
   - [Configuration](#configuration-menu)
   - [Driver `config`](#driver-config)
   - [Driver `database`](#driver-database)
   - [Model Menu](#model-menu)
   - [MenuService](#menuservice)
   - [Menu Management Screen](#menu-management-screen)
   - [Sidebar — Icons and Accordion Groups](#sidebar--icons-and-accordion-groups)
7. [Company Module](#company-module)
8. [Permissions Module](#permissions-module)
9. [ptah:module Command](#ptahmodule-command)
10. [Optional Dependencies](#optional-dependencies)
11. [Configuration Reference](#configuration-reference)
12. [Customizing Views](#customizing-views)

---

## Overview

The **Auth** and **Menu** modules are optional Ptah subsystems that can be activated independently in any project.

| Module | Features |
|---|---|
| **auth** | Login with rate limit, password recovery, 2FA (TOTP + email), active sessions, profile with photo |
| **menu** | Dynamic sidebar menu loaded from database, with cache and tree structure |
| **company** | Company and department management, multi-tenant context per session |
| **permissions** | Hierarchical ACL: roles, page objects, CRUD permissions, middleware, Blade directives, auditing |

**Non-breaking principle:** all modules are `false` by default. A project using only the `ptah:forge` scaffolding and `BaseCrud` remains 100% functional without any changes.

**Module dependencies:**

```
permissions → requer company
company     → independente
auth        → independente
menu        → independent (optional: requires auth for the management screen)
```

---

## Activating the Modules

### Via command (recommended)

```bash
# Activate authentication
php artisan ptah:module auth

# Activate dynamic menu
php artisan ptah:module menu

# Activate company management
php artisan ptah:module company

# Activate ACL / permissions (activates company automatically if needed)
php artisan ptah:module permissions

# View state of all modules
php artisan ptah:module --list
```

O comando:
1. Publishes the required migrations
2. Runs `php artisan migrate`
3. Automatically sets the environment variable in `.env`

### Via `.env` (manual)

```dotenv
PTAH_MODULE_AUTH=true
PTAH_MODULE_MENU=true
PTAH_MENU_DRIVER=database   # 'config' (default) or 'database'
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true
```

### Via `config/ptah.php` (manual)

```php
'modules' => [
    'auth'        => env('PTAH_MODULE_AUTH', false),
    'menu'        => env('PTAH_MODULE_MENU', false),
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
],
```

---

## Auth Module

### Auth Configuration

In `config/ptah.php`, section `auth`:

```php
'auth' => [
    'guard'              => 'web',
    'home'               => '/dashboard',    // redirect after login
    'register_enabled'   => false,           // no public registration
    'two_factor'         => true,            // enables 2FA
    'remember_me'        => true,            // shows "remember me" in login
    'session_protection' => true,            // manage active sessions
    'route_prefix'       => '',              // URL prefix (e.g. 'admin')
    'middleware'         => ['web'],
],
```

### Routes

Registered automatically when `ptah.modules.auth = true`:

| Method | URI | Name | Protection |
|---|---|---|---|
| `GET` | `/login` | `ptah.auth.login` | public |
| `POST` | `/logout` | `ptah.auth.logout` | public |
| `GET` | `/forgot-password` | `ptah.auth.forgot-password` | public |
| `GET` | `/reset-password/{token}` | `password.reset` | public |
| `GET` | `/two-factor-challenge` | `ptah.auth.two-factor` | public |
| `GET` | `/dashboard` | `ptah.dashboard` | `auth` |
| `GET` | `/profile` | `ptah.profile` | `auth` |

> Use `route_prefix` to mount all routes under a prefix. E.g.: `'route_prefix' => 'admin'` generates `/admin/login`, `/admin/dashboard`, etc.

### Livewire Components

Registered under the namespace `Ptah\Livewire\Auth`:

| Tag | Class | Layout |
|---|---|---|
| `ptah::auth.login` | `LoginPage` | `ptah::layouts.forge-auth` |
| `ptah::auth.forgot-password` | `ForgotPasswordPage` | `ptah::layouts.forge-auth` |
| `ptah::auth.reset-password` | `ResetPasswordPage` | `ptah::layouts.forge-auth` |
| `ptah::auth.two-factor` | `TwoFactorChallengePage` | `ptah::layouts.forge-auth` |
| `ptah::auth.profile` | `ProfilePage` | `ptah::layouts.forge-dashboard` |

---

### LoginPage

**Arquivo:** `src/Livewire/Auth/LoginPage.php`  
**View:** `resources/views/livewire/auth/login.blade.php`

Funcionalidades:
- Authentication via `Auth::attempt()`
- **Rate Limit:** 5 tentativas por `email|ip`, bloqueio por 60 segundos
- "Remember me" field (configurable via `ptah.auth.remember_me`)
- Active 2FA detection: instead of logging in, saves `ptah.2fa.user_id` in session and redirects to `/two-factor-challenge`
- Without 2FA: `Session::regenerate()` + redirect to `ptah.auth.home`

**Livewire Properties:**

| Property | Type | Description |
|---|---|---|
| `email` | string | Email field |
| `password` | string | Password field |
| `remember` | bool | Check "remember me" |
| `errorMessage` | string | Error message displayed in the alert |

---

### ForgotPasswordPage

**Arquivo:** `src/Livewire/Auth/ForgotPasswordPage.php`  
**View:** `resources/views/livewire/auth/forgot-password.blade.php`

Uses the default Laravel broker (`Password::sendResetLink()`). Displays success/error feedback without revealing whether the email exists in the database.

---

### ResetPasswordPage

**Arquivo:** `src/Livewire/Auth/ResetPasswordPage.php`  
**View:** `resources/views/livewire/auth/reset-password.blade.php`

- `mount(string $token)` — recebe token e e-mail via query string
- `Password::reset()` → dispara evento `PasswordReset` → redirect para login com `status`

---

### TwoFactorChallengePage

**Arquivo:** `src/Livewire/Auth/TwoFactorChallengePage.php`  
**View:** `resources/views/livewire/auth/two-factor-challenge.blade.php`

2FA verification flow post-login:

```
LoginPage saves ptah.2fa.user_id in session
  ↓
TwoFactorChallengePage::mount() verifica session
  ↓
User enters code
  ↓
verify() → TwoFactorService::verify*()
  ↓
Sucesso → Auth::loginUsingId() + Session::regenerate() + evento Login
```

**Properties:**

| Property | Type | Description |
|---|---|---|
| `code` | string | Entered code |
| `usingRecovery` | bool | Toggle to use recovery code |

**Methods:**

| Method | Description |
|---|---|
| `verify()` | Verifies the code (recovery / email OTP / TOTP) |
| `sendEmailCode()` | Re-sends OTP code by email |

---

### ProfilePage

**Arquivo:** `src/Livewire/Auth/ProfilePage.php`  
**View:** `resources/views/livewire/auth/profile.blade.php`

Profile page with 5 tabs:

| Tab (`activeTab`) | Functionality |
|---|---|
| `profile` | Edit name and email |
| `password` | Change password (validates current password) |
| `two_factor` | Configure / disable 2FA (TOTP + email) |
| `sessions` | View and revoke active sessions |
| `photo` | Upload profile photo (`WithFileUploads`) |

**Main methods:**

| Method | Description |
|---|---|
| `saveProfile()` | Persists name and email |
| `savePassword()` | Validates current + saves new password |
| `initTotp()` | Generates TOTP secret + QR code SVG, shows confirmation form |
| `confirmTotp()` | Verifies code and activates TOTP 2FA |
| `enableEmailTwoFactor()` | Activates email 2FA immediately |
| `disableTwoFactor()` | Deactivates and removes 2FA data |
| `regenerateRecoveryCodes()` | Generates 8 new recovery codes |
| `loadSessions()` | Loads active sessions via `SessionService` |
| `revokeSession($id)` | Revokes specific session |
| `revokeOtherSessions()` | Revokes all except the current one |
| `savePhoto()` | Saves photo to `profile-photos` disk |
| `removePhoto()` | Removes photo and clears field in database |

---

### Dashboard

**View:** `resources/views/livewire/auth/dashboard.blade.php`

Static view served by the `ptah.dashboard` route. Uses the `forge-dashboard` layout and displays 4 example `<x-forge-stat-card>` components with system information (user name, app, environment, Laravel version).

To customize, publish the views:

```bash
php artisan vendor:publish --tag=ptah-views --force
# ou apenas para auth:
php artisan vendor:publish --tag=ptah-auth --force
```

Depois edite `resources/views/vendor/ptah/livewire/auth/dashboard.blade.php`.

---

## 2FA Authentication

The system supports two simultaneous methods that the user chooses in the `two_factor` tab of the profile.

### TOTP (Authenticator App)

Uses the `pragmarx/google2fa-laravel` library (optional installation — see [Optional Dependencies](#optional-dependencies)).

**Activation flow:**

```
ProfilePage::initTotp()
  → TwoFactorService::enableTotp()
      → Generates secret (Google2FA::generateSecretKey())
      → Saves encrypted in two_factor_secret
      → Returns [secret, qrCodeSvg, recoveryCodes]
  → Displays QR Code + confirmation field

User scans and enters code → ProfilePage::confirmTotp()
  → TwoFactorService::confirmTotp()
      → Verifies code with Google2FA::verifyKey()
      → Sets two_factor_confirmed_at = now()
      → Sets two_factor_type = 'totp'
```

**QR Code:** generated via `bacon/bacon-qr-code` in SVG. If the library is not installed, uses the Google Charts API as fallback.

**Columns added to the `users` table:**

| Column | Type | Description |
|---|---|---|
| `two_factor_secret` | text nullable | Secret TOTP (criptografado) |
| `two_factor_recovery_codes` | text nullable | JSON with 8 codes |
| `two_factor_confirmed_at` | timestamp nullable | Confirmation date; `null` = not active |
| `two_factor_type` | string nullable | `'totp'` ou `'email'` |
| `profile_photo_path` | string(2048) nullable | Profile photo path |

### Email OTP

Does not require an additional library. Uses the Laravel `Cache`.

**Fluxo:**

```
TwoFactorService::sendEmailCode($user)
  → Generates a 6-digit code
  → Cache::put("ptah_2fa_email_{userId}", $code, 600)
  → Envia TwoFactorCodeMail
  → Returns the code (for testing)

TwoFactorService::verifyEmailCode($user, $code)
  → Cache::get("ptah_2fa_email_{userId}")
  → Compara com hash_equals para timing safety
  → Se correto: Cache::forget()
```

**TTL:** 600 segundos (10 minutos).

### Recovery Codes

8 codes in the format `xxxxx-xxxxx`, generated with `Str::random()`.

- Stored in `two_factor_recovery_codes` as encrypted JSON
- **Each code is single-use** — once verified, it is removed from the array
- The user can regenerate them in the `two_factor` tab of the profile

### TwoFactorService

**Namespace:** `Ptah\Services\Auth\TwoFactorService`  
**Singleton** registrado no `PtahServiceProvider`.

| Method | Return | Description |
|---|---|---|
| `enableTotp(User $user)` | array | Generates secret + QR + recovery codes |
| `confirmTotp(User $user, string $code)` | bool | Confirms and activates TOTP |
| `verifyTotp(User $user, string $code)` | bool | Verifies code at login |
| `sendEmailCode(User $user)` | string | Sends email OTP; returns code |
| `verifyEmailCode(User $user, string $code)` | bool | Verifies email OTP |
| `verifyRecoveryCode(User $user, string $code)` | bool | Uses and consumes recovery code |
| `isEnabled(User $user)` | bool | `true` if `two_factor_confirmed_at` is not `null` |
| `disable(User $user)` | void | Clears all 2FA fields |

---

## Session Management

Requires session driver `database` (`SESSION_DRIVER=database`). The `SessionService` silently checks if the `sessions` table exists before any query.

### SessionService

**Namespace:** `Ptah\Services\Auth\SessionService`  
**Singleton** registrado no `PtahServiceProvider`.

| Method | Return | Description |
|---|---|---|
| `getActiveSessions(User $user)` | array | Lists active sessions with device details |
| `revokeSession(string $sessionId)` | void | Removes session by ID |
| `revokeOtherSessions(User $user, string $currentId)` | void | Removes all except the current one |

**Structure of each returned session:**

```php
[
    'id'                  => 'abc123...',
    'ip_address'          => '192.168.0.1',
    'user_agent'          => 'Mozilla/5.0...',
    'browser'             => 'Chrome',        // detected via parseAgent()
    'platform'            => 'Windows',       // detected via parseAgent()
    'last_activity'       => 1709000000,
    'last_activity_human' => '3 minutes ago', // Carbon::diffForHumans()
    'is_current'          => true,
]
```

**Browsers detectados:** Edge, Opera, Chrome, Firefox, Safari, IE  
**Plataformas detectadas:** Windows, macOS, Linux, Android, iPhone, iPad

---

## Menu Module

### Menu Configuration

In `config/ptah.php`, section `menu`:

```php
'menu' => [
    'driver'    => env('PTAH_MENU_DRIVER', 'config'),   // 'config' or 'database'
    'cache'     => true,
    'cache_ttl' => 300,    // seconds
    'max_depth' => 4,      // maximum tree depth
],
```

### Driver `config`

**Default — no migration required.** Menu items are read from `ptah.forge.sidebar_items`, exactly as before. Existing projects continue working without any changes.

```php
// config/ptah.php
'forge' => [
    'sidebar_items' => [
        ['icon' => 'home',  'label' => 'Dashboard', 'url' => '/dashboard', 'match' => 'dashboard'],
        ['icon' => 'users', 'label' => 'Users',    'url' => '/users',     'match' => 'users*'],
    ],
],
```

### Driver `database`

Activated with `PTAH_MENU_DRIVER=database`. Items are read from the `menus` table with automatic cache.

**Resolution priority in `forge-sidebar`:**

```
prop :items (explicit)
  ↓ (if null)
MenuService::getTree()  ← when driver = 'database'
  ↓ (if driver = 'config' or menu module inactive)
config('ptah.forge.sidebar_items')
  ↓ (if empty)
itens de demo hardcoded
```

### Model Menu

**Namespace:** `Ptah\Models\Menu`  
**Tabela:** `menus`  
**SoftDeletes:** sim

**Schema da tabela:**

| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | — |
| `parent_id` | bigint FK nullable | Self-reference (groups) |
| `text` | string | Texto interno/legado |
| `label` | virtual (alias of `text`) | Displayed label — the sidebar reads `label` with fallback to `text` |
| `url` | string(2048) | URL do link |
| `icon` | string nullable | Classe CSS (`bx bx-home`, `fas fa-user`) ou nome SVG legado (`home`) |
| `type` | enum | `menuLink` ou `menuGroup` |
| `target` | enum | `_self` ou `_blank` |
| `link_order` | integer | Display order |
| `is_active` | boolean | Visibility |
| `deleted_at` | timestamp | SoftDelete |

**Relacionamentos:**

```php
$menu->parent   // BelongsTo(Menu)
$menu->children // HasMany(Menu)
```

**Static methods:**

| Method | Return | Description |
|---|---|---|
| `Menu::getTreeForSidebar()` | array | Cached tree ready for the sidebar |
| `Menu::clearCache()` | void | Manually invalidates the cache |
| `Menu::buildTree()` | array | Builds tree without cache |

**Output format** (compatible with `forge-sidebar`):

```php
[
    [
        'label'    => 'Dashboard',
        'url'      => '/dashboard',
        'icon'     => 'bx bx-home-alt',   // CSS class Boxicons
        'type'     => 'menuLink',
        'target'   => '_self',
        'match'    => 'dashboard',
        'children' => [],
    ],
    [
        'label'    => 'Records',
        'icon'     => 'bx bx-folder',
        'type'     => 'menuGroup',         // group -> accordion in sidebar
        'children' => [
            [
                'label'  => 'Products',
                'url'    => '/products',
                'icon'   => 'bx bx-cube',
                'type'   => 'menuLink',
                'target' => '_self',
                'match'  => 'products*',
            ],
        ],
    ],
]
```

### MenuService

**Namespace:** `Ptah\Services\Menu\MenuService`  
**Singleton** registered in `PtahServiceProvider`.

| Method | Return | Description |
|---|---|---|
| `getTree()` | array | Menu items according to the configured driver |
| `getFromConfig()` | array | Reads and normalizes `ptah.forge.sidebar_items` |
| `clearCache()` | void | Invalidates the cache (`ptah_menu_tree`) |
| `allForAdmin()` | Collection | All items (including inactive) for the management screen |
| `listForSelect()` | array | Groups for the `parent_id` field in the form |

**Cache:**

- Key: `ptah_menu_tree`  
- TTL: `config('ptah.menu.cache_ttl', 300)` seconds  
- Automatic invalidation: the `Menu` model Observer calls `Menu::clearCache()` on `saved`, `deleted` and `restored` events

---

### Menu Management Screen

When the menu module is active, Ptah automatically registers the Livewire CRUD screen for items:

| Route | Component | Access |
|---|---|---|
| `/ptah-menu` | `Ptah\Livewire\Menu\MenuList` | `ptah.menu.manage` |

The screen appears automatically in the **Administration dropdown** of the navbar (`forge-navbar`) as soon as the route exists.

**Features:**
- Creation and editing of **links** (`menuLink`) and **groups** (`menuGroup`)
- **Parent** field — allows nesting links inside a group
- Real-time icon preview in the form
- Display **Order** (`link_order`)
- Toggle de status (ativo/inativo) diretamente na tabela
- Group deletion automatically detaches children (prevents orphans)
- **Search** and filter by type in the toolbar
- Invalidates `ptah_menu_tree` cache after each operation

**Supported icons:**

| Formato | Exemplo | Resultado |
|---|---|---|
| CSS class with space | `bx bx-home-alt` | `<i class="bx bx-home-alt">` (Boxicons) |
| CSS class with space | `fas fa-user` | `<i class="fas fa-user">` (Font Awesome) |
| Simple name (legacy) | `home` | Inline SVG from legacy map |

> The Boxicons 2.1.4 and Font Awesome 6.7.2 libraries are already loaded via CDN by `forge-dashboard-layout`.

---

### Sidebar — Icons and Accordion Groups

The `forge-sidebar` was updated to support three behaviors depending on the `type` of each item:

| `type` | Comportamento |
|---|---|
| `menuLink` | Link direto — `<a href>` com highlight de rota ativa |
| `menuGroup` + children | Group header with **Alpine.js accordion** (`x-collapse`) — animated arrow indicates open/closed |
| `menuGroup` without children | Disabled label (div, no click) |

**`database` Driver — Fixed Dashboard:**

When `PTAH_MENU_DRIVER=database`, the **Dashboard** item is automatically injected at the top of the menu (before database items) with icon `bx bx-home-alt`. Other items come from the database.

**Icon rendering:**

```php
// Logic in forge-sidebar.blade.php
if (str_contains($icon, ' ')) {
    // Boxicons / Font Awesome → <i class>
    return '<i class="' . $icon . '">';
}
// Name without space -> inline SVG (legacy)
return $svgIcons[$icon] ?? $svgIcons['cube'];
```

**Logout:**  
The logout button uses `<i class="bx bx-log-out">` instead of inline SVG.

**Estado accordion:**  
Groups containing the active route start **open** automatically (`x-data="{ open: true }"`). The state is not persisted between sessions.

---

## Company Module

The **company** module adds complete company and department management to Ptah.

**Features:**
- Company CRUD with logo, tax data (CNPJ/CPF/EIN/VAT), address in JSON and arbitrary settings
- Department CRUD (role groupers)
- `CompanyService` with session context, cache and multi-company support
- `/ptah-companies` screen with Livewire 4
- Idempotent `DefaultCompanySeeder`
- All models (`Company`, `Department`) use the `HasAuditFields` trait — `created_by`, `updated_by` and `deleted_by` filled automatically via Eloquent events

**Quick activation:**

```bash
php artisan ptah:module company
```

> See the **complete documentation** in [Company.md](Company.md).

---

## Permissions Module

The **permissions** module implements granular hierarchical ACL based on roles.

**Features:**
- Roles/Profiles with department and identifier color
- MASTER role — total bypass of all checks
- Pages and Page Objects (button, field, section, api, report, tab, link, page)
- Per-object permissions: `can_create`, `can_read`, `can_update`, `can_delete` + JSON `extra`
- `PermissionService` with cache (Redis tag support), auditing and automatic user resolution
- `RoleService` with MASTER protection and batch permission synchronization
- Global helpers: `ptah_can()`, `ptah_is_master()`, `ptah_permissions()`
- `Permission::check()` Facade
- Blade directives: `@ptahCan` / `@ptahMaster`
- Middleware: `ptah.can:object,action`
- 5 Livewire admin screens: Departments, Roles, Pages/Objects, Users ACL, Audit
- Idempotent `DefaultAdminSeeder` that creates the entire admin chain
- All models (`Role`, `PtahPage`, `PageObject`, `UserRole`, `RolePermission`) use the `HasAuditFields` trait — automatic tracking of who created, edited and deleted each record

**Quick activation:**

```bash
php artisan ptah:module permissions
# Activates company automatically if needed
```

> See the **complete documentation** in [Permissions.md](Permissions.md).

---

## ptah:module Command

```
php artisan ptah:module {module?} {--list} {--force}
```

| Argument/Option | Description |
|---|---|
| `module` | `auth`, `menu`, `company` ou `permissions`. Se omitido, exibe seletor interativo |
| `--list` | Displays a table with the state of each module |
| `--force` | Overwrites existing published files |

**Example output of `--list`:**

```
  Available modules in Ptah:

  ┌─────────────┬────────────────────────────┬───────────┐
  | Module      | .env Variable              | State     |
  ├─────────────┼────────────────────────────┼───────────┤
  | auth        | PTAH_MODULE_AUTH           | ✔ active  |
  | menu        | PTAH_MODULE_MENU           | ✘ inactive|
  | company     | PTAH_MODULE_COMPANY        | ✔ active  |
  | permissions | PTAH_MODULE_PERMISSIONS    | ✔ active  |
  └─────────────┴────────────────────────────┴───────────┘

  To activate: php artisan ptah:module {module}
```

**What `ptah:module auth` does:**

1. Publishes the `add_two_factor_columns_to_users_table` migration
2. Runs `php artisan migrate`
3. Adds `PTAH_MODULE_AUTH=true` to `.env`
4. Displays next steps

**What `ptah:module menu` does:**

1. Publishes the `create_menus_table` migration
2. Runs `php artisan migrate`
3. Adds `PTAH_MODULE_MENU=true` to `.env`
4. Displays next steps

**What `ptah:module company` does:**

1. Publishes the `ptah_companies` and `ptah_departments` migrations
2. Runs `php artisan migrate`
3. Runs `DefaultCompanySeeder` (idempotent default company)
4. Adds `PTAH_MODULE_COMPANY=true` to `.env`
5. Displays next steps

**What `ptah:module permissions` does:**

1. Activates `company` if not yet active
2. Publishes the 6 permissions migrations
3. Executa `php artisan migrate`
4. Runs `DefaultAdminSeeder` (company → department → MASTER role → admin → association)
5. Adds `PTAH_MODULE_PERMISSIONS=true` to `.env`
6. Displays box with credentials of the created admin

---

## Optional Dependencies

By default, the package does not install any extra dependencies for the modules. 2FA TOTP uses automatic fallback.

### For full 2FA TOTP

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

| Pacote | Finalidade |
|---|---|
| `pragmarx/google2fa-laravel` | Generation and verification of TOTP codes |
| `bacon/bacon-qr-code` | QR Code generation in SVG (TOTP setup) |

**Without these packages:**
- TOTP **does not work** — the option will not be shown in the profile (check with `class_exists(\PragmaRX\Google2FA\Google2FA::class)`)
- QR Code uses the Google Charts API as a visual fallback

### For active sessions

```bash
php artisan session:table
php artisan migrate
```

E no `.env`:

```dotenv
SESSION_DRIVER=database
```

The `SessionService` silently checks if the table exists — without this configuration, the Sessions tab will display an empty list with no error.

---

## Configuration Reference

Full section added to `config/ptah.php`:

```php
/*
|--------------------------------------------------------------------------
| Optional Modules
|--------------------------------------------------------------------------
*/
'modules' => [
    'auth'        => env('PTAH_MODULE_AUTH', false),
    'menu'        => env('PTAH_MODULE_MENU', false),
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
],

/*
|--------------------------------------------------------------------------
| Authentication Settings
|--------------------------------------------------------------------------
*/
'auth' => [
    'guard'              => 'web',
    'home'               => '/dashboard',
    'register_enabled'   => false,
    'two_factor'         => true,
    'remember_me'        => true,
    'session_protection' => true,
    'route_prefix'       => '',
    'middleware'         => ['web'],
],

/*
|--------------------------------------------------------------------------
| Menu Settings
|--------------------------------------------------------------------------
*/
'menu' => [
    'driver'    => env('PTAH_MENU_DRIVER', 'config'),
    'cache'     => true,
    'cache_ttl' => 300,
    'max_depth' => 4,
],

/*
|--------------------------------------------------------------------------
| Company Settings
|--------------------------------------------------------------------------
*/
'company' => [
    'table_prefix'       => 'ptah_',
    'default_name'       => env('PTAH_COMPANY_NAME', 'My Company'),
    'default_slug'       => env('PTAH_COMPANY_SLUG', 'my-company'),
    'allow_multiple'     => env('PTAH_COMPANY_MULTIPLE', false),
    'require_department' => env('PTAH_COMPANY_REQUIRE_DEPT', false),
    'route_prefix'       => 'ptah-companies',
    'middleware'         => ['web', 'auth'],
],

/*
|--------------------------------------------------------------------------
| Permissions Settings
|--------------------------------------------------------------------------
*/
'permissions' => [
    'cache_enabled'  => env('PTAH_PERM_CACHE', true),
    'cache_ttl'      => env('PTAH_PERM_CACHE_TTL', 300),
    'audit_enabled'  => env('PTAH_PERM_AUDIT', false),
    'master_role'    => 'MASTER',
    'route_prefix'   => 'ptah-admin',
    'middleware'      => ['web', 'auth'],
    'admin_name'     => env('PTAH_ADMIN_NAME', 'Admin'),
    'admin_email'    => env('PTAH_ADMIN_EMAIL', 'admin@ptah.test'),
    'admin_password' => env('PTAH_ADMIN_PASSWORD', 'ptah@admin'),
],
```

---

## Customizing Views

Module views are part of the `ptah::` namespace. To customize, publish and edit locally:

```bash
# Publishes ALL views (includes auth + Forge components)
php artisan vendor:publish --tag=ptah-views --force
```

The views will be copied to:

```
resources/views/vendor/ptah/
├── layouts/
│   ├── forge-auth.blade.php
│   └── forge-dashboard.blade.php
├── livewire/
│   └── auth/
│       ├── login.blade.php
│       ├── forgot-password.blade.php
│       ├── reset-password.blade.php
│       ├── two-factor-challenge.blade.php
│       ├── profile.blade.php
│       └── dashboard.blade.php
├── mail/
│   └── two-factor-code.blade.php
└── components/
    └── ...Forge components...
```

Laravel automatically loads views from the `vendor/ptah` directory with precedence over those in the package.

> **Note:** after publishing, future package updates will not affect the published views. Re-publish with `--force` when you want to receive visual updates.

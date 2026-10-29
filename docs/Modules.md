# Optional Modules — Auth, Menu, Company & Permissions

**Pacote:** `jonytonet/ptah`  
**Minimum version:** see tags in the repository  
**Laravel:** 11+ | **Livewire:** 4

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
| **api** | `BaseResponse`, `BaseApiController` and Swagger base classes used by `ptah:forge --api` |
| **ai_agent** | Conversational AI widget (requires `prism-php/prism`) — see [AiAgent.md](AiAgent.md) |

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

# Activate API base classes + Swagger
php artisan ptah:module api

# Activate the AI agent widget
php artisan ptah:module ai_agent

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
PTAH_MENU_DRIVER=database   # 'database' (default) or 'config'
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true
PTAH_MODULE_API=true
PTAH_MODULE_AI_AGENT=true
```

### Via `config/ptah.php` (manual)

```php
'modules' => [
    'auth'        => env('PTAH_MODULE_AUTH', false),
    'menu'        => env('PTAH_MODULE_MENU', false),
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
    'api'         => env('PTAH_MODULE_API', false),
    'ai_agent'    => env('PTAH_MODULE_AI_AGENT', false),
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
    'two_factor'         => true,            // false hides the 2FA setup (an account that already has 2FA is still challenged)
    'remember_me'        => true,            // false hides "remember me" on the login screen
    'session_protection' => true,            // false hides the active-sessions tab
    'route_prefix'       => env('PTAH_AUTH_ROUTE_PREFIX', ''), // URL prefix (e.g. 'admin')
    'guest_redirect'     => null,            // see "Guest redirect" below
],
```

The route group middleware is read from `ptah.auth.middleware` (default `['web']`, listed in the config since 1.50.1).

**`guard` is the guard the whole module uses (1.53.0).** Login, the 2FA challenge,
the profile, the active sessions, "forgot password" and the reset all work on
`ptah.auth.guard` — its own provider (so its own model) and the password broker
of that provider in `config/auth.php`. Before 1.53.0 only the logout read it:
the rest used the default guard and `auth.providers.users.model`. For staff on
an `admin` guard:

```php
// config/auth.php
'guards'    => ['admin' => ['driver' => 'session', 'provider' => 'admins']],
'providers' => ['admins' => ['driver' => 'eloquent', 'model' => App\Models\Admin::class]],
'passwords' => ['admins' => ['provider' => 'admins', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]],

// config/ptah.php
'auth' => ['guard' => 'admin', /* … */],
```

The protected routes below use `auth:<guard>`. The active-sessions tab lists only
sessions signed in on that guard: the `sessions` table has no guard column, so a
customer of another guard with the same id no longer appears there (or gets
revoked from it).

### Routes

Registered automatically when `ptah.modules.auth = true`:

| Method | URI | Name | Protection |
|---|---|---|---|
| `GET` | `/login` | `ptah.auth.login` | public |
| `POST` | `/logout` | `ptah.auth.logout` | public |
| `GET` | `/forgot-password` | `ptah.auth.forgot-password` | public |
| `GET` | `/reset-password/{token}` | `ptah.auth.password.reset` (was `password.reset` before 1.53.0) | public |
| `GET` | `/two-factor-challenge` | `ptah.auth.two-factor` | public |
| `GET` | `/dashboard` | `ptah.dashboard` | `auth:<guard>` |
| `GET` | `/profile` | `ptah.profile` | `auth:<guard>` |
| `POST` | `/appearance/theme-mode` | `ptah.appearance.theme-mode` | `auth:<guard>` |

**The reset page is `ptah.auth.password.reset` (1.53.0)** — it was named
`password.reset`, which Fortify/Jetstream register too. The links ptah sends
("forgot password", the users screen) point at it. A reset link sent by other
code (`Password::sendResetLink`) keeps Laravel's target, `password.reset`, when
the host has one, and falls back to the ptah page when nothing answers that
name — unless the host set its own `ResetPassword::createUrlUsing()`.

> Use `route_prefix` to mount all routes under a prefix. E.g.: `'route_prefix' => 'admin'` generates `/admin/login`, `/admin/dashboard`, etc.

**Guest redirect with more than one guard (1.46.5).** A guest is sent to `ptah.auth.login` only when the route requires the Ptah guard (`ptah.auth.guard`). A route of another guard (`auth:portal`, say) goes to `ptah.auth.guest_redirect` — a route name or a URL, or a map per guard — and, when that is null, to Laravel's own fallback (the `login` route):

```php
'auth' => [
    'guard' => 'web',
    'guest_redirect' => ['portal' => 'portal.login'],
],
```

### Livewire Components

Registered under the namespace `Ptah\Livewire\Auth`:

| Alias | Class | Layout |
|---|---|---|
| `ptah-auth-login` | `LoginPage` | `ptah::layouts.forge-auth` |
| `ptah-auth-forgot-password` | `ForgotPasswordPage` | `ptah::layouts.forge-auth` |
| `ptah-auth-reset-password` | `ResetPasswordPage` | `ptah::layouts.forge-auth` |
| `ptah-auth-two-factor` | `TwoFactorChallengePage` | `ptah::layouts.forge-auth` |
| `ptah-auth-profile` | `ProfilePage` | `ptah::layouts.forge-dashboard` |

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
| `errorMsg` | string | Error message displayed in the alert |

**Methods:**

| Method | Description |
|---|---|
| `verify()` | Verifies the code (recovery / email OTP / TOTP) |
| `sendEmailCode()` | Re-sends OTP code by email |

---

### ProfilePage

**Arquivo:** `src/Livewire/Auth/ProfilePage.php`  
**View:** `resources/views/livewire/auth/profile.blade.php`

Profile page with 6 tabs:

| Tab (`activeTab`) | Functionality |
|---|---|
| `profile` | Edit name and email |
| `password` | Change password (validates current password) |
| `two_factor` | Configure / disable 2FA (TOTP + email) |
| `sessions` | View and revoke active sessions |
| `photo` | Upload profile photo (`WithFileUploads`) |
| `appearance` | Per-user Appearance — 6 axes (light/dark tone, accent, text weight, density, font size), see [Configuration.md — Per-user Appearance](Configuration.md#per-user-appearance-profile--6th-tab) |

The `two_factor` tab is hidden when `ptah.auth.two_factor` is `false` (unless the user already has 2FA active), and the `sessions` tab when `ptah.auth.session_protection` is `false`.

**Main methods:**

| Method | Description |
|---|---|
| `saveProfile()` | Persists name and email; a new e-mail needs the current password (`email_password`) and must be unique (1.41.7) |
| `savePassword()` | Validates current + saves new password; ends the other sessions and rotates the remember-me token (1.41.8) |
| `initTotp()` | Generates a PENDING TOTP secret + QR code (held in the locked `pendingTotpSecret`, nothing written until a code confirms it) |
| `confirmTotp()` | Verifies the code against the pending secret and only then saves and activates it |
| `enableEmailTwoFactor()` | Sends an OTP e-mail and activates email 2FA immediately |
| `disableTwoFactor()` | Deactivates and removes 2FA data |
| `regenerateRecoveryCodes()` | Generates 8 new recovery codes |
| `loadSessions()` | Loads active sessions via `SessionService` |
| `revokeSession(string $sessionId, SessionService $sessionService)` | Revokes specific session and rotates the remember-me token, so the revoked device's cookie stops working (Livewire method-injects the service) |
| `revokeOtherSessions()` | Revokes all except the current one |
| `savePhoto()` | Stores the photo under `profile-photos/` on the `public` disk |
| `removePhoto()` | Removes photo and clears field in database |

Every 2FA change — `initTotp()`, `enableEmailTwoFactor()`, `disableTwoFactor()`,
`loadRecoveryCodes()`, `regenerateRecoveryCodes()` — requires the current
password in `twofa_password` (since 1.41.8): with a stolen session, turning 2FA
off, swapping the secret or reading the recovery codes made the theft permanent.
`TwoFactorService::startTotp()` / `confirmPendingTotp()` are the non-writing
pair behind it; `enableTotp()` / `confirmTotp()` keep working for hosts that
call them, but write the secret before it is confirmed.

---

### Dashboard

**View:** `resources/views/livewire/auth/dashboard.blade.php`

View served by the `ptah.dashboard` route. Uses the `forge-dashboard` layout. When `DashboardService` has widgets registered it renders them (see [Dashboard.md](Dashboard.md)); otherwise it displays 4 example `<x-forge-stat-card>` components with system information (user name, app, environment, Laravel version).

To customize, publish the views:

```bash
php artisan vendor:publish --tag=ptah-views --force
# ou apenas para auth:
php artisan vendor:publish --tag=ptah-views-auth --force
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
  → TwoFactorService::startTotp()
      → Generates secret (Google2FA::generateSecretKey()) — nothing is written
      → Returns [secret, qr_image_uri, recovery_codes]
  → Displays QR Code + confirmation field (secret held in the locked pendingTotpSecret)

User scans and enters code → ProfilePage::confirmTotp()
  → TwoFactorService::confirmPendingTotp()
      → Verifies code against the pending secret with Google2FA::verifyKey()
      → Saves the secret (encrypted) and the recovery codes
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
  → Sets two_factor_type = 'email' (and two_factor_confirmed_at) when the user had no 2FA yet

TwoFactorService::verifyEmailCode($user, $code)
  → Cache::get("ptah_2fa_email_{userId}")
  → Compara com hash_equals para timing safety
  → Se correto: Cache::forget()
```

**TTL:** 600 segundos (10 minutos).

### Recovery Codes

8 codes in the format `xxxxxxxxxx-xxxxxxxxxx` (two blocks of 10 characters), generated with `Str::random()`.

- Stored in `two_factor_recovery_codes` as encrypted JSON
- **Each code is single-use** — once verified, it is removed from the array
- The user can regenerate them in the `two_factor` tab of the profile

### TwoFactorService

**Namespace:** `Ptah\Services\Auth\TwoFactorService`  
**Singleton** registrado no `PtahServiceProvider`.

| Method | Return | Description |
|---|---|---|
| `startTotp(Authenticatable $user)` | array | Generates secret + QR (`qr_image_uri`) + recovery codes, without writing to the user |
| `confirmPendingTotp(Authenticatable $user, string $secret, string $code, array $recoveryCodes)` | bool | Verifies the code against the pending secret and only then saves and activates TOTP |
| `enableTotp(Authenticatable $user)` | array | Legacy: like `startTotp()` but writes the secret before it is confirmed |
| `confirmTotp(Authenticatable $user, string $code, array $recoveryCodes)` | bool | Legacy: confirms and activates a secret written by `enableTotp()` |
| `verifyTotp(Authenticatable $user, string $code)` | bool | Verifies code at login |
| `sendEmailCode(Authenticatable $user)` | void | Sends email OTP |
| `verifyEmailCode(Authenticatable $user, string $code)` | bool | Verifies email OTP |
| `forgetEmailCode(Authenticatable $user)` | void | Invalidates a pending email OTP |
| `verifyRecoveryCode(Authenticatable $user, string $code)` | bool | Uses and consumes recovery code |
| `getRecoveryCodes(Authenticatable $user)` | array | Decrypted recovery codes |
| `regenerateRecoveryCodes(Authenticatable $user)` | array | Generates and saves 8 new codes |
| `isEnabled(Authenticatable $user)` | bool | `true` if `two_factor_confirmed_at` is not `null` |
| `disable(Authenticatable $user)` | void | Clears all 2FA fields |

---

## Session Management

Requires session driver `database` (`SESSION_DRIVER=database`). The `SessionService` silently checks if the `sessions` table exists before any query.

### SessionService

**Namespace:** `Ptah\Services\Auth\SessionService`  
**Singleton** registrado no `PtahServiceProvider`.

| Method | Return | Description |
|---|---|---|
| `getActiveSessions(Authenticatable $user)` | Collection | Lists active sessions with device details |
| `revokeSession(string $sessionId, Authenticatable $user)` | void | Removes session by ID (scoped to `$user`, IDOR guard) |
| `revokeOtherSessions(Authenticatable $user, string $currentSessionId)` | int | Removes all except the current one; returns the number removed |

**Structure of each returned session:**

```php
[
    'id'                  => 'abc123...',
    'ip_address'          => '192.168.0.1',
    'user_agent'          => 'Mozilla/5.0...',
    'browser'             => 'Chrome',        // detected via parseAgent()
    'platform'            => 'Windows',       // detected via parseAgent()
    'last_activity_human' => '3 minutes ago', // Carbon::diffForHumans()
    'is_current'          => true,
]
```

**Browsers detectados:** Edge, Opera, Chrome, Firefox, Safari, Internet Explorer  
**Plataformas detectadas:** Windows, macOS, Linux, Android, iPhone, iPad

---

## Menu Module

### Menu Configuration

In `config/ptah.php`, section `menu`:

```php
'menu' => [
    'driver'    => env('PTAH_MENU_DRIVER', 'database'),   // 'database' (default) or 'config'
    'cache'     => true,
    'cache_ttl' => 300,    // seconds
    'max_depth' => 4,      // maximum tree depth
],
```

### Driver `config`

**No migration required.** Menu items are read from `ptah.forge.sidebar_items`, exactly as before. Existing projects continue working without any changes. Note: `PTAH_MENU_DRIVER` now defaults to `database` (see above) — set it explicitly to `config` to use this driver.

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
| `text` | string | Item text (the only stored label column) |
| `url` | string nullable | URL do link |
| `icon` | string nullable | Classe CSS (`bx bx-home`, `fas fa-user`); default `bx bx-circle` |
| `type` | enum | `menuLink` ou `menuGroup` |
| `target` | enum | `_self` ou `_blank` |
| `link_order` | integer | Display order |
| `is_active` | boolean | Visibility |
| `created_by`, `updated_by`, `deleted_by` | bigint nullable | Audit fields (`HasAuditFields`) |
| `created_at`, `updated_at` | timestamp | — |
| `deleted_at` | timestamp | SoftDelete |

`label` is not a column: the tree builder adds a `label` key (copy of `text`) to every item, and the sidebar reads `label` with fallback to `text`.

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

`Menu::buildTree()` (builds the tree without cache) is private — set `ptah.menu.cache` to `false` to bypass the cache.

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
| `clearCache()` | void | Invalidates the cache (`ptah_menu_tree`) |
| `allForAdmin()` | Collection | All items (including inactive) for the management screen |
| `listForSelect()` | array | Groups for the `parent_id` field in the form |

**Cache:**

- Key: `ptah_menu_tree`  
- TTL: `config('ptah.menu.cache_ttl', 300)` seconds  
- Automatic invalidation: the `Menu` model calls `Menu::clearCache()` on its `saved`, `deleted` and `restored` events (raw SQL changes still need `php artisan ptah:menu-cache-clear`)

---

### Menu Management Screen

When the menu module is active, Ptah automatically registers the Livewire CRUD screen for items:

| Route | Name | Component | Access |
|---|---|---|---|
| `/ptah-menu` | `ptah.menu.manage` | `Ptah\Livewire\Menu\MenuList` | `web` + `auth`, and `ptah_can_manage_structure()` (master users when the permissions module is active; otherwise denied unless `PTAH_STRUCTURE_EDITOR=true`) |

The screen appears automatically in the **Administration dropdown** of the navbar (`forge-navbar`) as soon as the route exists.

**Features:**
- Creation and editing of **links** (`menuLink`) and **groups** (`menuGroup`)
- **Parent** field — allows nesting links inside a group
- Real-time icon preview in the form
- Display **Order** (`link_order`)
- Toggle de status (ativo/inativo) diretamente na tabela
- Group deletion automatically detaches children (their `parent_id` becomes `null`, so they move to the root)
- **Search** and filter by type in the toolbar
- Invalidates `ptah_menu_tree` cache after each operation

**Supported icons:**

| Formato | Exemplo | Resultado |
|---|---|---|
| CSS class with space | `bx bx-home-alt` | `<i class="bx bx-home-alt">` (Boxicons) |
| CSS class with space | `fas fa-user` | `<i class="fas fa-user">` (Font Awesome) |
| Empty value | — | Falls back to `<i class="bx bx-circle">` |

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
// Logic in forge-sidebar.blade.php — the value is used as the CSS class
$cls = (trim($icon) !== '') ? e($icon) : 'bx bx-circle';
return '<i class="' . $cls . ' ...">';
```

**Logout:**  
The logout button uses `<i class="bx bx-log-out">` instead of inline SVG.

**Estado accordion:**  
Groups containing the active route start **open** automatically (`x-data="{ open: true }"` is rendered only for them; the others start closed). The state is not persisted between sessions.

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
- `PermissionService` with cache (generation-based keys, works on any cache store), auditing and automatic user resolution
- `RoleService` with MASTER protection and batch permission synchronization
- Global helpers: `ptah_can()`, `ptah_is_master()`, `ptah_permissions()`, `ptah_has_role()`, `ptah_can_manage_config()`
- `Permission::check()` Facade
- Blade directives: `@ptahCan` / `@ptahMaster`
- Middleware: `ptah.can:object,action`
- 6 Livewire admin screens: Departments, Roles, Pages/Objects, Users ACL, Audit, Permissions Guide
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
| `module` | `auth`, `menu`, `company`, `permissions`, `api` ou `ai_agent`. Se omitido, exibe seletor interativo |
| `--list` | Displays a table with the state of each module |
| `--force` | Overwrites existing published files |

**Example output of `--list`:**

```
  Available Ptah modules:

  ┌─────────────┬────────────────────────────┬───────────┐
  | Module      | .env Variable              | Status    |
  ├─────────────┼────────────────────────────┼───────────┤
  | auth        | PTAH_MODULE_AUTH           | ✔ active  |
  | menu        | PTAH_MODULE_MENU           | ✘ inactive|
  | company     | PTAH_MODULE_COMPANY        | ✔ active  |
  | permissions | PTAH_MODULE_PERMISSIONS    | ✔ active  |
  | api         | PTAH_MODULE_API            | ✘ inactive|
  | ai_agent    | PTAH_MODULE_AI_AGENT       | ✘ inactive|
  └─────────────┴────────────────────────────┴───────────┘

  To enable: php artisan ptah:module {module}
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
6. Displays box with credentials of the created admin (when `PTAH_ADMIN_PASSWORD` is unset, a random password is generated and shown once)

**What `ptah:module api` does:**

1. Runs `composer require darkaonline/l5-swagger` when it is not installed
2. Publishes `BaseResponse`, `BaseApiController` and `SwaggerInfo` (`--tag=ptah-api`) and the L5-Swagger config
3. Fills the `SwaggerInfo` placeholders and adds `PTAH_MODULE_API=true` to `.env`

**What `ptah:module ai_agent` does:**

1. Runs `composer require prism-php/prism` when it is not installed
2. Publishes the AI Agent migrations (`--tag=ptah-ai-agent`) and runs `php artisan migrate`
3. Adds `PTAH_MODULE_AI_AGENT=true` to `.env`

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
- TOTP **does not work** — `TwoFactorService::startTotp()` throws a `RuntimeException` and `verifyTotp()` returns `false` (check with `class_exists(\PragmaRX\Google2FA\Google2FA::class)`); the profile still shows the TOTP option
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

Module sections of `config/ptah.php`:

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
    'api'         => env('PTAH_MODULE_API', false),
    'ai_agent'    => env('PTAH_MODULE_AI_AGENT', false),
],

/*
|--------------------------------------------------------------------------
| Authentication Settings
|--------------------------------------------------------------------------
*/
'auth' => [
    'guard'              => 'web',
    'home'               => '/dashboard',
    'two_factor'         => true,
    'remember_me'        => true,
    'session_protection' => true,
    'route_prefix'       => env('PTAH_AUTH_ROUTE_PREFIX', ''),
    'guest_redirect'     => null,
],

/*
|--------------------------------------------------------------------------
| Menu Settings
|--------------------------------------------------------------------------
*/
'menu' => [
    'driver'    => env('PTAH_MENU_DRIVER', 'database'),
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
    'logo_disk' => env('PTAH_LOGO_DISK', 'public'),
],

/*
|--------------------------------------------------------------------------
| Permissions Settings
|--------------------------------------------------------------------------
*/
'permissions' => [
    'cache'                => env('PTAH_PERMISSION_CACHE', true),
    'cache_ttl'            => (int) env('PTAH_PERMISSION_CACHE_TTL', 3600),
    'user_model'           => env('PTAH_USER_MODEL', 'App\Models\User'),
    'user_id_field'        => env('PTAH_USER_ID_FIELD', 'id'),
    'user_query_scope'     => env('PTAH_USER_QUERY_SCOPE', null),
    'company_session_key'  => env('PTAH_COMPANY_SESSION_KEY', 'ptah_company_id'),
    'user_session_key'     => env('PTAH_USER_SESSION_KEY', null),
    'audit'                => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'         => env('PTAH_PERMISSION_AUDIT_DENIED', true),
    'audit_master'         => env('PTAH_PERMISSION_AUDIT_MASTER', false),
    'audit_retention_days' => (int) env('PTAH_PERMISSION_AUDIT_RETENTION_DAYS', 90),
    'multi_company'        => env('PTAH_MULTI_COMPANY', true),
    'allow_guest'          => env('PTAH_PERMISSION_ALLOW_GUEST', false),
    'admin_name'           => env('PTAH_ADMIN_NAME', 'Administrador'),
    'admin_email'          => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    // No insecure default: when unset, a strong random password is generated and shown once at install.
    'admin_password'       => env('PTAH_ADMIN_PASSWORD'),
],
```

---

## Customizing Views

Module views are part of the `ptah::` namespace. To customize, publish and edit locally:

```bash
# Publishes ALL views (includes auth + Forge components)
php artisan vendor:publish --tag=ptah-views --force

# Or only the auth views (+ forge-auth layout)
php artisan vendor:publish --tag=ptah-views-auth --force
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

# CRUD Configuration Guide

**Package:** `jonytonet/ptah`  
**Component:** BaseCrud Configuration  
**Laravel:** 11+

> 🎯 **Quick Start: Lifecycle Hooks**  
> Copy the complete template: [`ProductHooks.example.php`](ProductHooks.example.php) → `app/CrudHooks/ProductHooks.php`  
> Configure in the modal: `@ProductHooks` → Done!

---

## Table of Contents

1. [Overview](#overview)
2. [Package configuration (`config/ptah.php`)](#package-configuration-configptahphp)
3. [Configuration Methods](#configuration-methods)
4. [Visual Configuration Modal](#visual-configuration-modal)
5. [CLI Command (ptah:config)](#cli-command-ptahconfig)
6. [Comparison: Modal vs CLI](#comparison-modal-vs-cli)
7. [CrudConfig Structure (JSON)](#crudconfig-structure-json)
8. [Column Configuration](#column-configuration)
9. [Action Configuration](#action-configuration)
10. [Filter Configuration](#filter-configuration)
11. [Style Configuration](#style-configuration)
12. [JOIN Configuration](#join-configuration)
13. [General Settings](#general-settings)
14. [Permissions Configuration](#permissions-configuration)
15. [Notifications Configuration](#notifications-configuration)
16. [Lifecycle Hooks (Dynamic Code)](#lifecycle-hooks-dynamic-code)
17. [Complete Practical Examples](#complete-practical-examples)
18. [Recommended Workflow](#recommended-workflow)
19. [Import/Export of Configurations](#importexport-of-configurations)
20. [Troubleshooting](#troubleshooting)

---

## Overview

The Ptah CRUD system offers **two complementary methods** for configuring columns, filters, actions, styles and other options:

| Method | Interface | Best For | Requires |
|--------|-----------|----------|---------|
| **Visual Modal** | GUI with drag-and-drop | Initial setup, visual adjustments, exploration | Web browser, authentication |
| **CLI Command** | Terminal via `ptah:config` | Automation, CI/CD, versioning, batch operations | Terminal, server access |

Both methods **save to the same table** (`crud_configs`) and **produce the same final result**.

### Where Configurations Are Stored

```sql
SELECT * FROM crud_configs WHERE model = 'Product';
```

| Column | Type | Description |
|--------|------|-------------|
| `id` | `int` | Primary key |
| `model` | `string` | Model identifier (e.g. `Product`, `Product/ProductStock`) |
| `route` | `string` | Screen route (request path); empty = global config for the model. `(model, route)` is unique |
| `config` | `json` | Complete configuration in JSON |
| `created_by` | `bigint` (nullable) | Who created the row |
| `updated_by` | `bigint` (nullable) | Who last changed it |
| `created_at` | `timestamp` | Creation date |
| `updated_at` | `timestamp` | Last modification |

### Cache and Invalidation

Configurations are cached automatically:
- Cache key: `ptah.crud.{model}` with `/` and `\` replaced by `.` (e.g. `ptah.crud.Product`, `ptah.crud.Product.ProductStock`), plus `.{route}` for a screen-specific config
- Enabled by `cacheStrategy.enabled`, falling back to `ptah.crud.cache_enabled` (default `true`)
- TTL: `cacheStrategy.ttl`, falling back to `ptah.crud.cache_ttl` (default: 3600s)
- What is cached is the config **lookup** (the row id), not the screen's data
- Automatic invalidation when saving via modal or CLI

---

## Package configuration (`config/ptah.php`)

> **Scope note:** the rest of this document covers the per-entity CrudConfig
> JSON stored in `crud_configs` (columns, actions, filters, styles, JOINs...).
> The keys below are **package-wide** settings that live in `config/ptah.php`
> — after `php artisan vendor:publish --tag=ptah-config` — and its `.env`
> overrides. They configure Ptah itself, not an individual entity's CRUD.

Defaults below are taken directly from `config/ptah.php` — `— ` in the ENV
column means the key has no `.env` override (edit the published file instead).

> Removed in 1.50.0 because nothing read them: `paths.actions`, `paths.livewire`,
> `api.docs`, `forge.prefix`, `forge.tailwind`, `scaffold.layout`,
> `scaffold.auth_layout`, `crud.confirm_delete`, `auth.register_enabled`,
> `company.logo_path` and `company.address_fields`. A published `config/ptah.php`
> that still carries them is harmless — they are ignored.

### Locale & runtime

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `force_locale` | `PTAH_FORCE_LOCALE` | `false` | [InstallationGuide.md — Language / locale](InstallationGuide.md#language--locale) |
| `locale` | `PTAH_LOCALE` | `en` | [InstallationGuide.md — Language / locale](InstallationGuide.md#language--locale) |
| `process_timeout` | `PTAH_PROCESS_TIMEOUT` | `300` | [Commands.md — ptah:install / ptah:module](Commands.md) (timeout for `composer require` / `npm install`/`build`) |

### Theme colors (`theme.colors.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `theme.colors.primary` | `PTAH_COLOR_PRIMARY` | `#5b21b6` | `config/ptah.php` comments (injected as CSS custom properties, no view edits needed) |
| `brand.logo` | `PTAH_BRAND_LOGO` | `null` | Logo on the login, sidebar and navbar (a URL, or a path under `public/`). A `logoUrl` prop still wins. Since 1.43.3 |
| `brand.logo_dark` | `PTAH_BRAND_LOGO_DARK` | `null` | Optional dark-theme variant of the logo |
| `theme.colors.success` | `PTAH_COLOR_SUCCESS` | `#10b981` | `config/ptah.php` comments |
| `theme.colors.danger` | `PTAH_COLOR_DANGER` | `#ef4444` | `config/ptah.php` comments |
| `theme.colors.warn` | `PTAH_COLOR_WARN` | `#f59e0b` | `config/ptah.php` comments |
| `theme.colors.dark` | `PTAH_COLOR_DARK` | `#1e293b` | `config/ptah.php` comments |

### Neutral CSS tokens (`resources/css/ptah-components.css`)

Besides the brand/semantic colors above (config-driven, injected via `theme-colors` partial), the BaseCrud UI's own neutral chrome (backgrounds, borders, text) is tokenized as CSS custom properties, declared once in `:root` (light defaults) with dark-mode overrides in a `.ptah-dark` block right after it. These are **not** exposed through `config/ptah.php` — they are pure CSS, overridable by any host app that loads its own stylesheet after Ptah's `@import`.

| Token | Light | Dark | Used for |
|-------|-------|------|----------|
| `--ptah-canvas` | `#ffffff` | `#0f172a` | Chrome flush with the page: toolbar, filter-panel card |
| `--ptah-surface` | `#ffffff` | `#1e293b` | Default card-like surface: buttons, sticky cell, modal, delete-confirm |
| `--ptah-surface-raised` | `#ffffff` | `#1e293b` | Shadow-elevated floating surface: dropdown menus, modal chrome |
| `--ptah-surface-sunken` | `#f8fafc` | `#0f172a` | Recessed panel: modal body tint, master/detail row |
| `--ptah-surface-hover` | `#f8fafc` | `#334155` | Generic interactive hover tint: buttons, rows, sticky cell |
| `--ptah-menu-hover` | `#f9fafb` | `#334155` | Dropdown/list item hover |
| `--ptah-panel` | `#f8fafc` | `#1e293b` | Header/footer banding: thead, tfoot, filter-panel header/footer |
| `--ptah-field` | `#ffffff` | `#0f172a` | Active/focused input background |
| `--ptah-field-muted` | `#f8fafc` | `#1e293b` | Resting (unfocused) input background |
| `--ptah-control-ghost` | `#f1f5f9` | `#334155` | Ghost-filled control chrome (e.g. column-visibility show/hide-all buttons) |
| `--ptah-control-ghost-hover` | `#e2e8f0` | `#475569` | Hover state of the above |
| `--ptah-line` | `#f1f5f9` | `#334155` | Hairline divider: dropdowns, filter-panel header/footer, modal header, row bottom |
| `--ptah-line-strong` | `#e2e8f0` | `#334155` | More visible border: outer containers, thead/tfoot rule |
| `--ptah-line-control` | `#cbd5e1` | `#475569` | Scrollbar track/thumb, save-filter input border |
| `--ptah-line-field` | `#8f95a0` | `#6d7278` | Resting form-field border |
| `--ptah-line-field-hover` | `#64748b` | `#64748b` | Hover border for the above (same in both scopes) |
| `--ptah-text-strong` | `#1e293b` | `#e2e8f0` | Darkest text: search input value, modal title |
| `--ptah-text-field` | `#1f2937` | `#e2e8f0` | Text typed/selected inside inputs and selects |
| `--ptah-text` | `#374151` | `#cbd5e1` | Default body text |
| `--ptah-text-secondary` | `#475569` | `#cbd5e1` | Icon-button text, form labels |
| `--ptah-text-muted` | `#5a5f65` | `#a3b0c0` | Muted text: helper copy, idle sort arrows, subtitles |
| `--ptah-text-faint` | `#5e636a` | `#a3b0c0` | Faintest text: pagination summary, small field labels |
| `--ptah-icon-muted` | `#64748b` | `#64748b` | Toolbar icon glyphs (same in both scopes) |
| `--ptah-text-on-accent` | `#ffffff` | `#ffffff` | White ink on solid accent/strong backgrounds |

**Override contract:** to change any of these, redeclare **both** blocks in your host app's `app.css`, *after* the `@import` of `ptah-components.css`:

```css
@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';

:root {
    --ptah-surface: #fafafa;
}

.ptah-dark {
    --ptah-surface: #17202e;
}
```

Redeclaring only the `:root` block is a common mistake: both `:root` and `.ptah-dark` match `<html>` with the same specificity (0,1,0), so the cascade falls back to source order — Ptah's own `.ptah-dark` block (declared right after its `:root`) still wins in dark mode, and your override silently only applies to light mode.

This is a pure restyle layer (no new options in `config/ptah.php`); the color/border/text *roles* above may be split further in a future release without breaking existing overrides that only touch the tokens they need.

### Per-user Appearance (`/profile` → 6th tab)

On top of the host-level tokens above, every authenticated user can pick their own
combination of **6 independent axes** from a 6th tab at `/profile` ("Aparência"):

| Axis | Options (stored slug) |
|---|---|
| Light tone | `puro`, `papel`, `nevoa` |
| Dark tone | `carvao`, `grafite`, `meianoite` |
| Accent | `azul`, `violeta`, `ciano`, `verde`, `teal`, `ambar`, `vermelho`, `rosa`, `cinza` — plus `marca` (the host's own `theme.colors.primary`), offered and used as the default only when the host set a primary other than the package's `#5b21b6` |
| Font colour | `suave`, `neutra`, `forte` |
| Density | `compacta`, `confortavel`, `espacosa` |
| Font size | `pequena`, `normal`, `grande` |

Slugs are plain ASCII by design (`nevoa`, `carvao`, `meianoite` — no accented
characters), since they round-trip through a `data-ptah-*` HTML attribute and a
JSON-cast preference value.

**Persistence & rendering.** The choice is stored via `UserPreference` (key `theme`,
group `appearance`) and sanitized against the whitelist in
`Ptah\Support\AppearancePresets` before every write and every render — a value outside
the whitelist is never trusted, since it would leave a `var(--ptah-*)` with no matching
CSS block invalid at computed-value time. The six resulting values are rendered as
`data-ptah-light`, `data-ptah-dark`, `data-ptah-accent`, `data-ptah-text`,
`data-ptah-density` and `data-ptah-fontsize` attributes directly on `<html>` **by the
server** (`forge-dashboard-layout`), so there is no flash of the default theme for an
authenticated user. Each pill in the tab previews the option it represents (its own
tone/scale/colour/spacing), not the currently active theme.

**Density.** Reuses, byte-for-byte, the 3-recipe scale (`--ptah-control-h/px/fs`,
`--ptah-row-py`) that already existed per-screen inside every BaseCrud toolbar
(`viewDensity`), now also driven globally from `:root`/`html[data-ptah-density="…"]`. Each
BaseCrud screen's own density dropdown (`HasCrudFilters::setViewDensity()`) offers **4
options**: **Profile default** (`global` — inherits the global preset above), **Compact**,
**Comfortable** and **Spacious**. Picking any of the 4 explicitly — including
**Comfortable** (since 1.50.0) — pins that choice for the screen, regardless of the global preset.
Preference blobs saved before this dropdown existed persisted `'comfortable'` implicitly (it was
the old, silent default, not a deliberate choice); `HasCrudPreferences::loadPreferences()`
migrates that legacy value to `'global'` on load — **only for blobs older than schema
version 2.3.0** — so those screens fall back under the profile's global density, while an
explicitly-persisted `'compact'`/`'spacious'` (a real prior choice) is left untouched. Since
1.50.0 a **Comfortable** picked today is saved with the current version and stays pinned
(before, the migration ran on every load and turned it back into the profile's density). `forge-input`, `forge-button` (`sm`/`md`), `forge-select`
and the 7 module-screen toolbars/tables also scale with this axis.

**Font size.** Scales `<html>`'s `font-size` (87.5% / 100% / 112.5%) — since most of the
package is sized in `rem`, this scales controls, tables, modals and chrome (sidebar,
navbar) from one root. A few literal-`px` sites do NOT follow, on purpose (not converted
by this pass — only listed here): the `@media print` table font (`11px`), the fixed
28×28px `.ptah-row-action` icon buttons, the sidebar's `max-width:200px` inline style used
during the collapse/hover label transition, and the many `w-[Npx]`/`h-[Npx]`/`text-[Npx]`
Tailwind arbitrary values scattered across the module screens and BaseCrud partials
(fixed-width selects, dropdown panels, small badge text — e.g. `_filter-panel.blade.php`'s
`w-[58px]` operator select, `_toolbar.blade.php`'s `text-[10px]` chips).

> **Override precedence:** a user-chosen preset **always wins** over a host's
> `:root`/`.ptah-dark` override (the contract described above) for the tokens the
> preset axis touches — not because of declaration order, but because the preset
> selectors carry higher specificity: `html[data-ptah-light="…"]:not(.ptah-dark)` /
> `html.ptah-dark[data-ptah-dark="…"]` resolve to (0,2,1), against (0,1,0) for a plain
> `:root`/`.ptah-dark` block. A host that needs a fixed brand regardless of user choice
> should not rely on `:root`/`.ptah-dark` alone; restrict or disable the Appearance tab
> instead (there is currently no config flag for that — see `docs/KnownLimitations.md`).

**Auth screens (login, 2FA, forgot/reset password).** There is no authenticated user on
these pages, so the same 6 axes plus the light/dark mode are mirrored into an `httpOnly`
`ptah_appearance` cookie (1 year, `SameSite=Lax`, written by the server whenever the
preference is saved via `/profile`, the navbar toggle, or a successful login) and read
back — sanitized the same way — to render the attributes on `forge-auth`'s `<html>`. On a
shared computer this means the login screen shows the *last* user's theme until someone
else logs in and refreshes the cookie, a minor preference leak between people on the same
device/browser; it never leaks credentials or any other account data.

### File generation paths (`paths.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `paths.models` | — | `app_path('Models')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.services` | — | `app_path('Services')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.repositories` | — | `app_path('Repositories')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.dtos` | — | `app_path('DTOs')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.requests` | — | `app_path('Http/Requests')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.resources` | — | `app_path('Http/Resources')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.controllers` | — | `app_path('Http/Controllers')` | [Commands.md — ptah:forge](Commands.md) |
| `paths.views` | — | `resource_path('views')` | [Commands.md — ptah:forge](Commands.md) |

### API (`api.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `api.prefix` | — | `api` | [Modules.md — API Module](Modules.md) |
| `api.middleware` | — | `['api', 'auth:sanctum']` | [Modules.md — API Module](Modules.md) |

### Ptah Forge (`forge.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `forge.external_pointer_prefixes` | — | `[]` | Prefixes of `*_id` columns that hold an id from ANOTHER system (e.g. `['yelll_']`). `ptah:forge` generates no `belongsTo`/`constrained()` for them — only a comment in the model and an indexed integer column. |
| `forge.sidebar_items` | — | `[]` | [Modules.md — Menu Module, driver `config`](Modules.md#menu-module) |

### BaseCrud defaults (`crud.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `crud.cache_enabled` | — | `true` | This document — [General Settings](#general-settings) |
| `crud.cache_ttl` | — | `3600` | This document — [General Settings](#general-settings) |
| `crud.per_page` | — | `25` | This document — [General Settings](#general-settings) |
| `crud.max_per_page` | — | `200` | Ceiling for the client-writable `perPage` (1.41.11) |
| `crud.soft_deletes` | — | `true` | Default of `ptah:forge` for new entities (`--no-soft-deletes` overrides it). Does not touch a screen that already exists — that follows the model's `SoftDeletes` trait. Since 1.50.0 |
| `crud.responsive_cards` | `PTAH_CRUD_RESPONSIVE_CARDS` | `true` | `true`: a screen whose `viewMode` is `auto` shows the table on wide screens and cards on phones; `false`: `auto` behaves like `table` |
| `crud.config_editor` | `PTAH_CONFIG_EDITOR` | `false` | [Permissions.md](Permissions.md) (ignored while the permissions module is active) |
| `structure_editor` | `PTAH_STRUCTURE_EDITOR` | `false` | Who may administer `/ptah-menu` and `/ptah-companies`. With the permissions module ACTIVE this is ignored and only masters may; with it OFF the screens are denied unless this is `true`. Until 1.34.8 both were open to any authenticated user |
| `crud.hook_namespaces` | — | `['App\CrudHooks']` | This document — § "Lifecycle Hooks" (PHP Classes syntax, `@Class::method`). A hook class outside these prefixes is refused (logged, and the save goes on without the hook); `ptah:config:doctor` reports it as an error |
| `crud.label_dictionary` | — | `[]` | `config/ptah.php` comments (overrides the built-in pt-BR label dictionary) |
| `crud.sd_service_namespaces` | — | `['App\Services']` | Diagnostic only: `ptah:config:doctor` warns when a searchdropdown `colsSDService` falls outside these namespaces. The request-time resolver does not enforce it |

### Export — async / large volume (`export.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `export.disk` | `PTAH_EXPORT_DISK` | `local` | [BaseCrud.md](BaseCrud.md) |
| `export.path` | `PTAH_EXPORT_PATH` | `ptah-exports` | [BaseCrud.md](BaseCrud.md) |
| `export.ttl_hours` | `PTAH_EXPORT_TTL_HOURS` | `48` | [BaseCrud.md](BaseCrud.md) |
| `export.async_max_rows` | `PTAH_EXPORT_ASYNC_MAX_ROWS` | `0` (unlimited) | [BaseCrud.md](BaseCrud.md) |

### User preferences (`preferences.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `preferences.foreign_key` | `PTAH_PREFERENCES_FK` | `auto` | this section |

`user_preferences.user_id` points at **the identity the host configured**, not
at `users`. The table is resolved from `ptah.permissions.user_model`, then
`auth.providers.users.model`, then the historical default — the same chain the
rest of the package already used — and the column takes the type of that
model's key: `bigint`, `uuid`, `ulid`, or `string` for a key scheme of your own.

| Value | Behaviour |
|---|---|
| `auto` (default) | creates the constraint when the resolved table exists on the same connection |
| `true` | demands it, and fails the migration loudly when it cannot be made |
| `false` | never creates it — only an index |

Set `PTAH_PREFERENCES_FK=false` when **more than one identity** writes
preferences (an internal panel and a customer area, say): no single table is a
valid target, and a constraint would lock the column to whichever one migrated
first. `auto` already declines by itself when the identity lives on another
connection, where a foreign key cannot reach.

> **What `false` does not solve.** Dropping the constraint lets two identities
> share the table; it does not let the table tell them apart. The unique key is
> `['user_id', 'key']`, so user 1 of one identity and user 1 of the other
> compete for the same row — **the last write wins, with no error** — and the
> cleanup on delete matches on `user_id` alone, so removing user 1 from one
> identity takes the other's preferences with it.
>
> It is only safe when the id spaces are **disjoint**. The complete answer is a
> polymorphic owner (`user_id` + `user_type`, with the unique becoming
> `['user_type', 'user_id', 'key']`), which is a schema change too large for a
> patch release and is recorded rather than done.
>
> In practice the second owner arrives by accident: a host that switched
> identity leaves `HasUserPreferences` on the `App\Models\User` that came with
> the scaffold, alongside the model it actually uses. Nothing collides while
> `users` is empty — and one row in it is enough to start. **Apply the trait to
> one identity only.**

**Deleting a user still clears their preferences**, with or without the
constraint: `HasUserPreferences` does it on the model's `deleted` event. A soft
delete does not — the row is coming back, and so should the theme the person
chose. Doing it in the model rather than in the engine also makes it visible to
the application; a database cascade passes underneath Eloquent and fires no
events at all.

**On a fresh install the defect cannot happen** — the column always gets the
type of the configured identity's key, so the insert that used to fail no longer
can. Whether the CONSTRAINT comes with it depends on migration order, because
package migrations join the host's queue sorted by timestamp and this one is
dated `2024_01_01_000000`:

| Fresh install | Column | Foreign key |
|---|---|---|
| Identity is Laravel's `users` | correct | created |
| `PTAH_USER_MODEL` set before `migrate`, identity table created by a migration dated **before** `2024_01_01_000000` | correct | created |
| Identity table created by a migration dated **after** it — the ordinary case for a new table | correct | **not created**: an index instead |
| `migrate` run before `PTAH_USER_MODEL` was set | `bigint` | points at `users` |

Row three is the one to know about: the identity table does not exist yet when
the package's migration runs, so `auto` declines the constraint rather than
failing. Nothing breaks — preferences save correctly — but there is no
referential integrity until you run the realign command below, which is one
extra line in an install script. Row four takes the same cure.

Renaming the migration to a later timestamp would fix the ordering and is not
available: it is a removal plus an addition, and `SchemaIsFrozenTest` blocks
both — rightly, since it would break every installation made from that point on.

**Upgrading an existing installation.** Databases built before 1.34.6 carry a
constraint to `users`, inferred from the column name. Point it at the configured
identity with:

```bash
php artisan ptah:preferences:realign --dry-run   # relatório, sem alterar nada
php artisan ptah:preferences:realign             # pede confirmação
```

A command and not a migration on purpose: a package migration is auto-discovered
and would run during the next `php artisan migrate` you happen to execute — and
this operation can legitimately refuse, which as a migration would mean failing
a deploy you did not intend to involve it in.

It deletes nothing. Preferences whose user no longer exists in the resolved
table stop the run with a count and the query to inspect them. It also leaves
the column type alone: moving a populated `bigint` column to `uuid` is a data
migration, not a schema tweak.

> **`driver`, `cache` and `ttl` used to live in this block and were never read
> by anything** — `UserPreference` goes straight to the database, always. They
> were removed in 1.34.6 rather than left promising a way out that did not
> exist. If preference caching is built one day, the keys come back with the
> code that reads them.

### Other package keys

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `mcp_tools` | `PTAH_MCP_TOOLS` | `true` | Adds ptah's read-only agent tools to Laravel Boost's MCP server when `laravel/boost` is installed; nothing happens without it — [AgentTools.md](AgentTools.md) |
| `intentional_overrides` | — | `[]` | Published views you keep on purpose (relative to `resources/views/vendor/ptah`); `ptah:upgrade-check` lists them as information instead of an action |
| `attachments.disk` | `PTAH_ATTACHMENTS_DISK` | `local` | Disk of record attachments (`HasAttachments`) — keep it private. See [BaseCrud.md](BaseCrud.md) |
| `attachments.max_kb` | `PTAH_ATTACHMENTS_MAX_KB` | `10240` | Per-file size limit |
| `attachments.mimes` | — | `pdf jpg jpeg png webp txt csv xlsx xls doc docx zip` | Accepted extensions |
| `navbar.notifications` | `PTAH_NAVBAR_NOTIFICATIONS` | `null` | `null`/`''` keeps the static bell; `none`/`off`/`hidden`/`false`/`0` hides it; any other string is a Livewire component alias (e.g. `ptah-notification-bell`) |
| `notifications.enabled` | `PTAH_MODULE_NOTIFICATIONS` | `false` | Notification centre — [Notifications.md](Notifications.md) |
| `notifications.poll` | `PTAH_NOTIFICATIONS_POLL` | `60s` | Bell polling interval |
| `notifications.dropdown_limit` | — | `20` | Items shown in the bell dropdown |
| `notifications.broadcast` | `PTAH_NOTIFICATIONS_BROADCAST` | `false` | Near-instant delivery over Echo/Reverb |
| `notifications.queue_connection` | `PTAH_NOTIFICATIONS_QUEUE_CONNECTION` | `null` | Queue connection of the CRUD notification jobs (`null` = the app default; `sync` delivers inline when there is no worker) |

### Error pages (`errors.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `errors.enabled` | `PTAH_ERROR_PAGES` | `true` | this section |
| `errors.themed_500_in_debug` | `PTAH_ERROR_500_IN_DEBUG` | `false` | this section |

Ptah ships themed pages for **403, 404, 405, 419, 429, 500 and 503**. They follow all
six appearance axes, because their colours read `var(--ptah-token, literal)`:
the token wins when `ptah-components.css` is loaded, and the literal keeps the
page readable when it is not — which is the likely state during a 500.

Each page steps aside in three situations:

| Situation | Why Ptah does not render | Configurable |
|---|---|---|
| The host has `resources/views/errors/{code}.blade.php` | Laravel's convention is that a view there is the last word. | No — it would be a bug. |
| The request expects JSON | An API must not be answered with HTML. | No — it would be a bug. |
| 500 while `APP_DEBUG=true` | A developer needs the stack trace, not a pretty page hiding it. | `errors.themed_500_in_debug` |

**Why that last one became configurable.** The rule is right — when something has
just blown up, the trace is worth more than the design — but it had a
consequence nobody had accounted for: there was no way to *see* your own 500
page in development without turning `APP_DEBUG` off, and a dozen other
behaviours with it. A deliberate behaviour that looks exactly like a bug, with
nothing anywhere saying why. Set `PTAH_ERROR_500_IN_DEBUG=true` while you are
looking at the page, and leave it off otherwise.

It applies to the 500 alone. The other statuses never depended on `APP_DEBUG`:
they arrive as `HttpException` — "this request cannot have that" rather than
"the application broke" — so there is no trace to preserve, and a 404 in
development is the themed page either way.

403 is additionally gated behind `modules.permissions`, since it is that
module's own denial screen. Set `PTAH_ERROR_PAGES=false` to disable the other
six and fall back to Laravel's defaults.

**What the themed 500 will NEVER claim.** The 500 is registered on its own
callback because it is not an `HttpException` — it is whatever broke. That
callback is typed `Throwable`, so Laravel offers it *every* exception, and it
must step aside for the ones the framework answers itself:

| Exception | What Laravel does with it |
|---|---|
| `AuthenticationException` | redirects to the login screen |
| `ValidationException` | redirects back with the errors in the session |
| `HttpResponseException` | returns the response it carries |
| `HttpException` and everything `prepareException()` converts into one — `AuthorizationException`, `ModelNotFoundException`, `TokenMismatchException` | the themed page for that status |

Until **1.34.6** only the last row was excluded, so the first two came out as a
themed 500 instead: a logged-out visitor got an error page instead of the login
screen, and a wrong password got one instead of "invalid credentials". Both
only with `APP_DEBUG=false` — the 500 steps aside in debug, so the defect
existed in production alone — and both are in Laravel's `dontReport` list, so
the 500 went out with nothing in the log.

The exclusion list lives in `PtahServiceProvider::FRAMEWORK_RENDERED_EXCEPTIONS`
and is checked against the framework's own `render()` by
`ExceptionRenderableScopeTest`, which fails if Laravel gains an arm the list
does not cover.

**How the pages follow the chosen theme.** The dashboard and auth layouts paint
`.ptah-dark` from a blocking script in `<head>`
(`ptah::partials.appearance-boot`). The error pages carry no JS at all, so they
stamp the class and the six `data-ptah-*` attributes **server-side**, straight
into the `<html>` tag — which also makes a flash structurally impossible, since
there is nothing to resolve after first paint.

Precedence is the same as everywhere else: the database for an authenticated
user, then the `ptah_appearance` cookie (the theme-mode endpoint writes both).
A visitor whose only record is `localStorage` is not reachable without JS and
falls back to `prefers-color-scheme`.

One subtlety these pages cannot ignore: **an error page is not always inside the
`web` middleware group.** An unmatched URI (404), maintenance mode (503) and any
exception thrown early never enter it, so there is no session and
`request()->cookie()` still holds the raw *encrypted* value. That is why
resolution goes through `AppearancePresets::resolveForStandalonePage()`, which
performs the decryption `EncryptCookies` would have done — prefix validation
included, so a cookie encrypted under a rotated or foreign key is rejected
rather than half-trusted. Reading the cookie the way the other layouts do made
the 403 follow the theme while the 404 silently did not. If reading the preference fails for any
reason — and a 500 may be rendering precisely because the database is gone —
the page drops silently to that same media-query fallback rather than throwing
while trying to explain an error.

**The reference on the 500 page.** The page shows a short id the user can hand
to whoever reads the logs. It is minted inside the exception handler's
`report()` (via `buildContextUsing`), so it is written to the log record for
that same exception **before** the page renders — grep the id and the entry is
there. It is never the exception message, which can carry a query, a filesystem
path or a credential. When an exception is not reported at all the page omits
the line entirely rather than showing an id that correlates with nothing.

To take the pages over, publish and edit them:

```bash
php artisan vendor:publish --tag=ptah-errors
```

### Optional modules (`modules.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `modules.auth` | `PTAH_MODULE_AUTH` | `false` | [Modules.md](Modules.md) |
| `modules.menu` | `PTAH_MODULE_MENU` | `false` | [Modules.md](Modules.md) |
| `modules.company` | `PTAH_MODULE_COMPANY` | `false` | [Modules.md](Modules.md) |
| `modules.permissions` | `PTAH_MODULE_PERMISSIONS` | `false` | [Modules.md](Modules.md) |
| `modules.api` | `PTAH_MODULE_API` | `false` | [Modules.md](Modules.md) |
| `modules.ai_agent` | `PTAH_MODULE_AI_AGENT` | `false` | [Modules.md](Modules.md) |

### Auth module (`auth.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `auth.guard` | — | `web` | [Modules.md — Auth Module](Modules.md) |
| `auth.home` | — | `/dashboard` | [Modules.md — Auth Module](Modules.md) |
| `auth.two_factor` | — | `true` | `false` hides and blocks the **activation** of 2FA on the profile. An account that already has 2FA is still challenged at login and can turn it off. Since 1.50.0 |
| `auth.remember_me` | — | `true` | `false` hides "remember me" on the login screen. Since 1.50.0 |
| `auth.session_protection` | — | `true` | `false` hides the active-sessions tab on the profile. Since 1.50.0 |
| `auth.route_prefix` | `PTAH_AUTH_ROUTE_PREFIX` | `''` | Prefix of the auth routes (`/login`, `/logout`…): `erp` → `/erp/login`, for a host whose storefront already owns `/login` |
| `auth.guest_redirect` | — | `null` | Where a guest goes when the route requires a guard OTHER than `auth.guard`: a route name, a URL, or a map `['portal' => 'portal.login']`. `null` leaves it to Laravel (the `login` route). Routes of `auth.guard` always go to the Ptah login |

### Menu module (`menu.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `menu.driver` | `PTAH_MENU_DRIVER` | `database` | [Modules.md — Menu Module](Modules.md#menu-module) |
| `menu.cache` | — | `true` | [Modules.md — Menu Module](Modules.md#menu-module) |
| `menu.cache_ttl` | — | `300` | [Modules.md — Menu Module](Modules.md#menu-module) |
| `menu.max_depth` | — | `4` | [Modules.md — Menu Module](Modules.md#menu-module) |

### Company module (`company.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `company.logo_disk` | `PTAH_LOGO_DISK` | `public` | [Company.md](Company.md) |

### Permissions module (`permissions.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `permissions.cache` | `PTAH_PERMISSION_CACHE` | `true` | [Permissions.md](Permissions.md) |
| `permissions.cache_ttl` | `PTAH_PERMISSION_CACHE_TTL` | `3600` | [Permissions.md](Permissions.md) |
| `permissions.user_model` | `PTAH_USER_MODEL` | `App\Models\User` | [Permissions.md](Permissions.md) |
| `permissions.user_id_field` | `PTAH_USER_ID_FIELD` | `id` | [Permissions.md](Permissions.md) |
| `permissions.user_query_scope` | `PTAH_USER_QUERY_SCOPE` | `null` | [Permissions.md](Permissions.md) |
| `permissions.company_session_key` | `PTAH_COMPANY_SESSION_KEY` | `ptah_company_id` | [Permissions.md](Permissions.md) |
| `permissions.user_session_key` | `PTAH_USER_SESSION_KEY` | `null` | [Permissions.md](Permissions.md) |
| `permissions.audit` | `PTAH_PERMISSION_AUDIT` | `false` | [Permissions.md](Permissions.md) |
| `permissions.audit_denied` | `PTAH_PERMISSION_AUDIT_DENIED` | `true` | [Permissions.md](Permissions.md) |
| `permissions.audit_master` | `PTAH_PERMISSION_AUDIT_MASTER` | `false` | [Permissions.md](Permissions.md) |
| `permissions.audit_retention_days` | `PTAH_PERMISSION_AUDIT_RETENTION_DAYS` | `90` | default `--days` window for `ptah:audit-prune` — see [Commands.md](Commands.md) |
| `permissions.multi_company` | `PTAH_MULTI_COMPANY` | `true` | [Permissions.md](Permissions.md) |
| `permissions.allow_guest` | `PTAH_PERMISSION_ALLOW_GUEST` | `false` | [Permissions.md](Permissions.md) |
| `permissions.admin_name` | `PTAH_ADMIN_NAME` | `Administrador` | [Permissions.md](Permissions.md) |
| `permissions.admin_email` | `PTAH_ADMIN_EMAIL` | `admin@admin.com` | [Permissions.md](Permissions.md) |
| `permissions.admin_password` | `PTAH_ADMIN_PASSWORD` | `null` (a strong random password is generated and shown once if unset — no insecure hardcoded fallback) | [Permissions.md](Permissions.md) |

### Field masks (`config/ptah-masks.php`)

A **separate file**, not a key inside `ptah.php`, for two reasons that reinforce
each other. `mergeConfigFrom` is shallow, so a host that published `ptah.php`
owns that whole array and a nested key added later never reaches it — a trap this
package has fallen into twice. And masks are the part of ptah most likely to
change from outside it, because document formats change by law; a dedicated file
is smaller to review in a diff.

```bash
php artisan vendor:publish --tag=ptah-masks
```

| Key | Default | Meaning |
|-----|---------|---------|
| `presets` | `[]` | Shipped sets to activate. Available: `br` |
| *(any other key)* | — | One mask definition, which overrides a preset |

The package ships only `digits` and `decimal` — the two masks that are nobody's
law. Everything with a check digit or a legal format is yours, either through a
preset or written out. Full reference, including the pattern language and the
`store` rules, in [BaseCrud.md](BaseCrud.md#masks-colsmask).

**Presets are not activated by the locale**, deliberately: a locale is an
interface language, not a jurisdiction, and it changes per request. See the
comment at the top of the published file for the one-line form if you want the
language to decide.

---

### Sidebar jump box (`forge.sidebar_jump*`)

Type part of a screen's name in the sidebar and go straight to it. It renders at
the **top**, between the logo and the navigation.

It appears for either of two reasons, and **nesting is the stronger one**: a
screen inside a closed group is not visible to someone scanning the menu, however
few entries there are, and that is exactly where typing wins. The other reason is
the total number of links reaching `sidebar_jump_min_items` (default 8). Below
three links it never appears — there it would be decoration.

It does **not** key off the presence of a scrollbar. Scroll depends on window
height, so a scroll-driven control would appear and disappear as you resize the
window.

Filtering happens in the browser. The links are already on the page — the sidebar
rendered them — so a request per keystroke would be searching the browser's own
memory the slow way. It reads `Ptah\Support\MenuResolver`, the same source that
feeds the AI agent's `find_menu` tool, so the assistant can never describe a menu
different from the one on screen.

When the sidebar is collapsed to icons the field becomes a magnifier that expands
the bar and focuses the input — the same gesture the menu groups already use.

---

### AI Agent module (`ai_agent.*`)

| Key | ENV | Default | Reference |
|-----|-----|---------|-----------|
| `ai_agent.system_prompt` | `PTAH_AI_SYSTEM_PROMPT` | `You are a helpful assistant.` | [AiAgent.md](AiAgent.md) |
| `ai_agent.max_history` | `PTAH_AI_MAX_HISTORY` | `20` | [AiAgent.md](AiAgent.md) |
| `ai_agent.max_stored_messages` | `PTAH_AI_MAX_STORED_MESSAGES` | `500` (`0` = no limit) | [AiAgent.md](AiAgent.md) |
| `ai_agent.tool_result_history_bytes` | `PTAH_AI_TOOL_RESULT_HISTORY_BYTES` | `8000` (`0` = keep none) | [AiAgent.md](AiAgent.md) |
| `ai_agent.rate_limit` | `PTAH_AI_RATE_LIMIT` | `30` | [AiAgent.md](AiAgent.md) |
| `ai_agent.stream` | `PTAH_AI_STREAM` | `true` | [AiAgent.md](AiAgent.md) |
| `ai_agent.max_steps` | `PTAH_AI_MAX_STEPS` | `5` | [AiAgent.md](AiAgent.md) |
| `ai_agent.max_message_chars` | `PTAH_AI_MAX_MESSAGE_CHARS` | `8000` | [AiAgent.md](AiAgent.md) |
| `ai_agent.confirmation_ttl` | `PTAH_AI_CONFIRMATION_TTL` | `30` | How long a confirmable tool's preview card can still be confirmed — [AiAgent.md](AiAgent.md) |
| `ai_agent.max_preview_rows` | `PTAH_AI_MAX_PREVIEW_ROWS` | `50` | Rows of the preview card |
| `ai_agent.commands` | — | `[]` | `/commands` typed in the chat that run a tool directly (`tool`, `arguments`, `description`, optional `permission`) |
| `ai_agent.daily_token_limit` | `PTAH_AI_DAILY_TOKEN_LIMIT` | `0` (disabled) | [AiAgent.md](AiAgent.md) |
| `ai_agent.allow_guests` | `PTAH_AI_ALLOW_GUESTS` | `false` | [AiAgent.md](AiAgent.md) |
| `ai_agent.expose_system_details` | `PTAH_AI_EXPOSE_SYSTEM_DETAILS` | `false` | [AiAgent.md](AiAgent.md) |
| `ai_agent.tools` | — | `[]` | [AiAgent.md](AiAgent.md) |
| `forge.sidebar_jump` | `PTAH_SIDEBAR_JUMP` | `true` | this section |
| `forge.sidebar_jump_min_items` | `PTAH_SIDEBAR_JUMP_MIN` | `8` | this section |
| `ai_agent.normalize_tool_schema` | `PTAH_AI_NORMALIZE_TOOL_SCHEMA` | `true` | [AiAgent.md](AiAgent.md) |
| `ai_agent.attachments.enabled` | `PTAH_AI_ATTACHMENTS` | `true` | [AiAgent.md](AiAgent.md) |
| `ai_agent.attachments.max_size_kb` | `PTAH_AI_ATTACH_MAX_KB` | `8192` | [AiAgent.md](AiAgent.md) |
| `ai_agent.attachments.max_files` | `PTAH_AI_ATTACH_MAX_FILES` | `4` | [AiAgent.md](AiAgent.md) |
| `ai_agent.attachments.allowed_extensions` | — | `png jpg jpeg gif webp pdf txt md csv json docx` (the published `config/ptah.php`) | [AiAgent.md](AiAgent.md) |
| `ai_agent.attachments.max_extracted_chars` | `PTAH_AI_ATTACH_MAX_CHARS` | `60000` | [AiAgent.md](AiAgent.md) |

> The list above is what `config/ptah.php` ships. The service's own fallback
> (`AiAttachmentService::DEFAULT_EXTENSIONS`: `png jpg jpeg gif webp bmp pdf docx
> txt md csv tsv json log`) only applies when the `attachments` block is **absent**
> — a host with a published config that carries the block above gets exactly that
> list, so `tsv`, `log` and `bmp` need adding there.
>
> The `attachments.*` defaults live in `Ptah\Services\AI\AiAttachmentService`,
> not only in `config/ptah.php`. `mergeConfigFrom` is shallow, so a host that
> published the config file owns the whole `ptah.ai_agent` array and would never
> receive a nested key a later version adds — reading `allowed_extensions` with
> `[]` as the fallback made an absent block indistinguishable from "allow
> nothing", and hid the whole feature. Nothing to add to a published config
> unless you want to restrict something.


---

## Configuration Methods

### 1. Via Visual Modal (Graphical Interface)

Access the configuration button (⚙️) at the top of the CRUD screen:

```blade
{{-- Rendered automatically by BaseCrud's toolbar, only for who may manage the config --}}
@ptahCanManageConfig
    @livewire('ptah-crud-config', ['model' => $model])
@endptahCanManageConfig
```

The `ptah-crud-config` component renders the button itself (it calls its own
`openModal`); there is no `openConfig` event to dispatch.

**Advantages:**
✅ Intuitive visual interface  
✅ Drag-and-drop to reorder columns  
✅ Real-time preview  
✅ Integrated color picker for badges  
✅ No technical knowledge required  
✅ Automatically detects relations  

**Disadvantages:**
❌ Not versionable via Git  
❌ Not automatable  
❌ Requires browser authentication  
❌ One configuration at a time  

### 2. Via Comando CLI (Terminal)

Execute o comando `ptah:config` no terminal:

```bash
# Modo interativo (wizard)
php artisan ptah:config "App\Models\Product"

# Modo declarativo (inline)
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name" \
  --column="price:number:mask=money_brl:renderer=money"
```

**Advantages:**
✅ Automatable via scripts  
✅ Versionable (export JSON → commit)  
✅ Batch operations (configure multiple models)  
✅ CI/CD friendly  
✅ Reproducible across environments  
✅ Smart suggestions based on the model  

**Disadvantages:**
❌ Learning curve (syntax)  
❌ No visual preview  
❌ Requires terminal access  

---

## Visual Configuration Modal

### How to Access

The modal is opened automatically when clicking the **⚙️ Config** button at the top of the BaseCrud. The button is only rendered under `@ptahCanManageConfig` (`ptah_can_manage_config()`): a master user, a `crud.config` read grant, or — with the permissions module off — `ptah.crud.config_editor`.

### Tab Structure

The modal has **9 main tabs**:

#### 1️⃣ Columns

**What it does:** Manages all CRUD columns (listing + form)

**Interface:**
- **Left sidebar:** Drag-and-drop list of all columns
- **Right panel:** sub-tabs to edit the selected column (7 — the SearchDropdown one only shows for `searchdropdown` columns)

**Available actions:**
- 🔄 Reorder via drag-and-drop
- ➕ Add new column
- ✏️ Edit existing column
- 🗑️ Delete column
- 👁️ Toggle visibility

**Sub-tabs per column:**

| Sub-tab | Fields Edited | Description |
|---------|---------------|-------------|
| **Basic** | `colsNomeFisico`, `colsNomeLogico`, `colsTipo`, `colsGravar`, `colsRequired`, `colsReadonly`, `colsIsFilterable`, `colsAlign`, `colsMinWidth`, `colsCellStyle`, `colsCellClass`, `colsCellIcon`, `colsSource`, `colsOrderBy`, `colsMetodoCustom` / `colsMetodoRaw`, `colsPermission`, `colsSelect`, `colsDefaultValue`, `colsFormBlock`, `colsOnChange` | Main column info, cell style, SQL source (for JOIN columns), ordering, custom method, select options |
| **Display** | `colsRenderer` and its options (`colsRendererCurrency`, `colsRendererDecimals`, `colsRendererMaxChars`, link, image, boolean, duration, QR options…), `colsRendererBadges` | How the column is rendered in the table. For the `badge`/`pill` renderer the same sub-tab holds the badge list (value, label, colour with hex picker + quick swatches, icon) |
| **Mask** | `colsMask`, `colsMaskTransform` | Input mask and the transform applied on save |
| **Validation** | `colsValidations` | Form validation rules (toggles for the common ones + a custom list) |
| **Relation** | `colsRelacao`, `colsRelacaoExibe`, `colsRelacaoNested` | Eloquent relationship shown in the listing |
| **SearchDropdown** | `colsSDModel`, `colsSDTipo`, `colsSDLabel`, `colsSDValor`, `colsSDOrder`, `colsSDLimit`, `colsSDInitWithData`, and the rest of the SD surface | Only shown for `searchdropdown` columns |
| **Totalizer** | `totalizadorEnabled`, `totalizadorType`, `totalizadorFormat`, `totalizadorLabel` | Writes `totalizador*` keys into the column, which the runtime does not read — footer totals come from the screen-level `totalizadores` (see [Totalizer](#totalizer)) |

**Example flow:**

1. Click a column in the sidebar (e.g. `status`)
2. Go to the **"Display"** sub-tab
3. Change `colsRenderer` to `badge`
4. Scroll to the badge list that appears in the same **"Display"** sub-tab
5. Configure colours:
   - `active` → green (`#10B981`)
   - `inactive` → red (`#EF4444`)
   - `pending` → yellow (`#F59E0B`)
6. Click **"Save"** (top right corner)

#### 2️⃣ Actions

**What it does:** Configures permissions and custom actions in the CRUD

**Sections:**

1. **Default Action Permissions:**
   - ✅ Create (create new record)
   - ✅ Edit (edit record)
   - ✅ Delete (delete record)
   - ✅ Export (export data)

2. **Custom Actions:**
   - List of extra actions (e.g. "Approve", "Reject", "Send Email")
   - Each action has: name, type (livewire/link/javascript), value, icon, colour, confirmation

**Example of custom action** — a row action is an entry of `cols` with
`colsTipo: "action"`; its label is `colsNomeLogico` (there is no top-level
`actions` list):

```json
{
  "colsNomeFisico": "approve",
  "colsNomeLogico": "Approve",
  "colsTipo": "action",
  "actionType": "livewire",
  "actionValue": "approve(%id%)",
  "actionIcon": "bx bx-check",
  "actionColor": "success",
  "actionConfirm": true,
  "actionConfirmMessage": "Are you sure you want to approve this record?"
}
```

#### 3️⃣ Filtros

**What it does:** Configures custom filters and the quick date filter column

**Sections:**

1. **Date Column for Quick Filter:**
   - Selects which date column to use for "Today/Week/Month/Quarter/Year"
   - Default: `created_at`

2. **Custom Filters:**
   - List of additional filters for the toolbar
   - Each filter has: field, label, type (text/number/date/select), operator (=, !=, >, <, >=, <=, LIKE)

**Example of custom filter:**

```json
{
  "field": "status",
  "label": "Status",
  "colsFilterType": "select",
  "operator": "=",
  "colsSelect": {
    "Active": "active",
    "Inactive": "inactive",
    "Pending": "pending"
  }
}
```

#### 4️⃣ Estilos

**What it does:** Defines conditional row styles

**Interface:**
- Card per style rule
- Each rule: `field`, `condition`, `value`, `style` (inline CSS declaration list)

**Example rule (canonical shape, persisted under `contitionStyles`):**

```json
{
  "field": "status",
  "condition": "==",
  "value": "cancelled",
  "style": "background:#FEE2E2;color:#991B1B;font-weight:bold;"
}
```

**Result:** Rows with `status = cancelled` get a light red background and dark red text.

#### 5️⃣ JOINs

**What it does:** Manages table JOINs (LEFT/INNER)

**Interface:**
- Visual cards of active JOINs
- Duplicate table detection
- Create/edit form
- Optional `DISTINCT` toggle

**Example JOIN:**

```json
{
  "type": "left",
  "table": "categories",
  "first": "products.category_id",
  "second": "categories.id",
  "distinct": false,
  "select": [{ "column": "categories.name", "alias": "category_name" }]
}
```

**Visual in the modal:**
```
┌─────────────────────────────────────┐
│ LEFT JOIN categories               │
│ ON products.category_id = categories.id │
│ SELECT: categories.name AS category_name │
│ DISTINCT: No                       │
│ [Edit] [Delete]                    │
└─────────────────────────────────────┘
```

A JOIN has no `WHERE` of its own (`joinWhere` and the old `join*` key names were
never read) — filter the screen with a custom filter instead.

#### 6️⃣ Geral

**What it does:** Global CRUD settings

**Sections:**

1. **Identification:**
   - `displayName` — Display name (e.g. "Products")

2. **Appearance:**
   - `tableClass` / `theadClass` — Classes of the `<table>` / `<thead>` (they replace the defaults)
   - `companyField` is **not** in this tab: set it in the JSON (`companyField`, default `company_id`)

3. **Cache:**
   - Stored in `cacheStrategy.enabled` / `cacheStrategy.ttl`. Read by
     `CrudConfigService` for the cache of the **config lookup** (see
     [Cache and Invalidation](#cache-and-invalidation)) — not for the screen's
     data, which is never cached by it.

4. **Export:**
   - `exportConfig.maxRows` — Maximum exportable rows
   - `exportConfig.orientation` — PDF orientation (landscape/portrait)

5. **Broadcast (Real-time):**
   - `broadcast.enabled` — Enable Echo listener
   - `broadcast.channel` — Channel name (default: `page-{model}-observer`)
   - `broadcast.event` — Event name (default: `.page{Model}Observer`)

#### 7️⃣ Permissions

**What it does:** Maps Laravel **gate names** to the actions a screen offers, and turns buttons off

**Interface:**
- A gate-name field for each of `create`, `edit`, `delete`, `restore`, `export`, `import`, `history` and `attachments` (e.g. `product.create`, `view-products`). There is no gate for listing or viewing: the listing is protected by `permissions.permissionIdentifier` plus the permissions module
- The `show*Button` flags (`showCreateButton`, `showEditButton`, `showDeleteButton`, `showTrashButton`) — off hides the button **and** refuses the action on the server

**Example:**

```json
{
  "permissions": {
    "permissionIdentifier": "product",
    "create": "product.create",
    "edit": "product.update",
    "delete": "product.destroy",
    "export": "product.export"
  }
}
```

**What BaseCrud does with it** (`HasCrudForm::crudConfigAllows()`, called by the
interface and by every action, so a forged call is refused too):

```php
// 1. a show*Button flag that is off  -> false
// 2. a gate name set and the user is a guest, or !$user->can($gate) -> false
// 3. otherwise -> true   (no gate name means no gate: there is no default gate)
```

It returns `false` — the action does nothing and the button is hidden — it does
not `abort(403)`.

#### 8️⃣ Lifecycle Hooks

**What it does:** Allows running custom PHP code at specific points in the record lifecycle

> 📁 **Full example:** See [ProductHooks.example.php](ProductHooks.example.php) — Template with 300+ lines of practical, documented examples.

**Hybrid System:** Supports **two syntaxes**:

1. **Inline expression (sandboxed):** For simple data shaping. A single Symfony
   ExpressionLanguage expression — **not** arbitrary PHP. It receives `data`,
   `record` and `user`; if it returns an array, that array becomes the form data.
   ```text
   merge(data, {'status': 'pending', 'uuid': uuid()})
   ```

2. **PHP Classes (recommended):** For complex logic, side effects (events, cache,
   logging), testable code with autocomplete.
   ```php
   @ProductHooks::beforeCreate
   @App\CrudHooks\ProductHooks
   ```

> ⚠️ **Breaking change (security):** inline hooks no longer run arbitrary PHP via
> `eval()`. They are now sandboxed expressions, eliminating the RCE risk. Inline
> can only **reshape `data`**; anything with side effects (dispatching events,
> writing to cache/logs, loading relations) must move to a **hook class**. Existing
> inline PHP from older versions will fail safely and be logged — migrate it to a class.

**Interface:**
- 5 textareas with code editor (`beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`)
- Practical examples of both syntaxes
- Info box explaining available variables
- Security warning

**Available hooks:**

| Hook | When it Runs | Available Variables | Can Modify |
|------|--------------|---------------------|------------|
| **beforeCreate** | Before INSERT | `$data` (array) | ✅ Yes (`$data` by reference) |
| **afterCreate** | After INSERT | `$record` (Model), `$data` (array) | ❌ No |
| **beforeUpdate** | Before UPDATE | `$data` (array), `$record` (Model) | ✅ Yes (`$data` by reference) |
| **afterUpdate** | After UPDATE | `$record` (Model), `$data` (array) | ❌ No |
| **beforeDelete** | Before a record is deleted (single, bulk or force) | `$record` (Model), `$data` (the record's attributes) | ❌ No — it is a barrier: **critical by default**, so any failure refuses the delete (throw `CrudHookAbort` to tell the user why) |

---

### 📝 **Syntax 1: Inline expression (sandboxed)**

Write a single expression in the modal. Ideal for setting/normalizing values on
the `before*` hooks. The expression is evaluated with Symfony ExpressionLanguage —
it cannot call arbitrary PHP. Available variables: `data`, `record`, `user`.
Available functions: `merge()`, `now()`, `upper()`, `lower()`, `slug()`, `uuid()`.
If the expression returns an array, it becomes the new form data.

**beforeCreate** — Set default values:
```text
merge(data, {'status': 'pending', 'uuid': uuid()})
```

**beforeCreate** — Derive a slug from another field:
```text
merge(data, {'slug': slug(data['name'])})
```

**beforeUpdate** — Conditional value (ternary + array access supported):
```text
record.status == 'draft' and data['status'] == 'published'
    ? merge(data, {'published_at': now()})
    : data
```

> **`after*` hooks and side effects:** dispatching events, writing to cache/logs or
> loading relations cannot be done inline anymore. Use a **hook class** (Syntax 2)
> for those — see the examples below.

---

### 🏗️ **Syntax 2: PHP Classes (Recommended)**

For complex logic, create real PHP classes in `app/CrudHooks/`.

**Advantages:**
✅ Autocomplete and static analysis (PHPStan/Psalm)  
✅ Testable with PHPUnit  
✅ Git-friendly and versionable  
✅ Reusable across CRUDs  
✅ No eval() syntax risk  

**Supported syntaxes:**

| Syntax in Modal | Result |
|-----------------|--------|
| `@ProductHooks::beforeCreate` | Calls `App\CrudHooks\ProductHooks::beforeCreate()` |
| `@ProductHooks` | Uses hook name as method (e.g. `beforeCreate()`) |
| `@App\CrudHooks\MyHooks::customMethod` | Uses full namespace |
| `@App\CrudHooks\MyHooks@customMethod` | `@` separator also works |

> **The class must live under `ptah.crud.hook_namespaces`** (default
> `App\CrudHooks`; a short name like `@ProductHooks` resolves under the first
> entry). A hook outside it — `@App\Services\MyHooks::run`, say — is refused:
> the failure is logged and the save goes on **without** the hook (a hook
> declared critical aborts instead). `ptah:config:doctor` reports it as an
> error. Move the class, or add its namespace to `crud.hook_namespaces`.

**Full example:**

**1. Create the hooks class:**

> 📋 **Full template available:** Copy [ProductHooks.example.php](ProductHooks.example.php) to `app/CrudHooks/ProductHooks.php` and customise as needed.

**Simplified example:**

```php
<?php

namespace App\CrudHooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ProductHooks
{
    /**
     * Hook executado antes de criar um produto.
     * 
     * @param array &$data Form data (mutable by reference)
     * @param Model|null $record Always null in this hook
     * @param mixed $component Livewire component (HasCrudForm trait)
     */
    public function beforeCreate(array &$data, ?Model $record, $component): void
    {
        $data['status'] = 'pending';
        $data['uuid'] = \Illuminate\Support\Str::uuid();
        Log::info('ProductHooks: beforeCreate');
    }

    public function afterCreate(array &$data, Model $record, $component): void
    {
        event(new \App\Events\ProductCreated($record));
        cache()->put('latest_product', $record->id, 3600);
    }

    public function beforeUpdate(array &$data, Model $record, $component): void
    {
        if ($record->isDirty('price')) {
            $data['price_updated_at'] = now();
        }
    }

    public function afterUpdate(array &$data, Model $record, $component): void
    {
        cache()->forget('product_' . $record->id);
        $record->load('category');
    }
}
```

> 💡 **See more examples in [ProductHooks.example.php](ProductHooks.example.php):**
> - Generate unique codes
> - Status transitions with validation
> - Synchronisation with external APIs
> - Complex notifications
> - Change history
> - 20+ documented use cases

**2. Configure in the modal:**

In the "Before Create" field, simply write:
```
@ProductHooks
```

Or specify the method:
```
@ProductHooks::beforeCreate
```

Or with full namespace:
```
@App\CrudHooks\ProductHooks::beforeCreate
```

**3. Result:**

✅ BaseCrud detects the `@` syntax and instantiates the class  
✅ Calls the method with the correct parameters  
✅ Errors are logged without breaking the save()  

---

### 🔒 **Security and Error Handling**

**Inline expression (sandboxed):**

✅ **Implemented protections:**
- Evaluated with Symfony ExpressionLanguage — **no `eval()`, no arbitrary PHP, no RCE**
- Only the whitelisted variables (`data`, `record`, `user`) and functions (`merge`, `now`, `upper`, `lower`, `slug`, `uuid`) are reachable
- `record` and `user` are **data, not objects** (since 1.41.7): the record's
  attributes and the user's `id`/`name`/`email` in a method-less object, so
  `record.status` reads and `record.save()` / `record.newQuery()` have nothing
  to call. Before 1.41.7 they were the Eloquent models, and ExpressionLanguage
  calls methods on objects — `record.newQuery().update({...})` ran SQL.
  `now()` returns `Y-m-d H:i:s` text, not a Carbon object, for the same reason.
- Automatic try-catch — errors do not break the save()
- Detailed error logging in `storage/logs/laravel.log`
- Modal access restricted by `ptah_can_manage_config()`: a master user, a `crud.config` read grant, or — with the permissions module off — `ptah.crud.config_editor`

**PHP Classes:**

✅ **Best for anything non-trivial:**
- Full PHP — side effects (events, cache, logging), validation, queries
- Syntax validation by IDE and CI/CD
- Testable with PHPUnit
- class-not-found and method-not-found handled automatically

**Example error log (inline expression):**

```
[2026-03-04 15:30:45] local.ERROR: [BaseCrud] Lifecycle hook 'beforeCreate' failed for model App\Models\Product
{
    "hook": "beforeCreate",
    "model": "App\\Models\\Product",
    "error": "Variable \"foo\" is not valid around position 1 ...",
    "code": "merge(data, {'x': foo})"
}
```

**Example error log (class):**

```
[2026-03-04 15:35:10] local.ERROR: [BaseCrud] Lifecycle hook 'beforeCreate' failed for model App\Models\Product
{
    "hook": "beforeCreate",
    "model": "App\\Models\\Product",
    "error": "Hook class not found: App\\CrudHooks\\ProductHooks",
    "file": "/path/to/HasCrudForm.php",
    "line": 290,
    "code": "@ProductHooks"
}
```

**Restrictions (inline expression):**

❌ **Does not work (use a hook class instead):**
- Calling Laravel facades or any function other than the whitelisted helpers
- Dispatching events, jobs, notifications
- Writing to cache/logs or executing SQL queries
- Statements, assignments, loops, `return` — it is a single expression

✅ **Works:**
- Reshape the form data on `before*` hooks: return an array (use `merge()`)
- Read `record` and `user` fields (e.g. `record.status`, `user.id`, `user.name`) — fields only, no methods
- Conditionals via the ternary operator and `and`/`or`
- Helpers: `merge()`, `now()`, `upper()`, `lower()`, `slug()`, `uuid()`

---

### 💾 **Saved JSON**

**Inline expression:**

```json
{
  "lifecycleHooks": {
    "beforeCreate": "merge(data, {'status': 'pending', 'uuid': uuid()})",
    "beforeUpdate": "data['status'] == 'published' ? merge(data, {'published_at': now()}) : data"
  }
}
```

> `after*` hooks need side effects, so they belong in a hook class, not inline.

**PHP classes:**

```json
{
  "lifecycleHooks": {
    "beforeCreate": "@ProductHooks",
    "afterCreate": "@ProductHooks::afterCreate",
    "beforeUpdate": "@App\\CrudHooks\\ProductHooks",
    "afterUpdate": "@ProductHooks::afterUpdate"
  }
}
```

**Hybrid (mixed):**

```json
{
  "lifecycleHooks": {
    "beforeCreate": "merge(data, {'status': 'pending'})",
    "afterCreate": "@ProductHooks::afterCreate",
    "beforeUpdate": "@App\\CrudHooks\\ComplexValidator::validate",
    "afterUpdate": "@ProductHooks::afterUpdate"
  }
}
```

---

### ⛔ **When a hook fails — swallowed by default, abortable on demand**

A hook that throws is **logged and the save continues**. That is deliberate: a
hook that sends a notification or writes an audit line must not take the whole
form down with it.

It is the wrong default for a hook that is a **barrier**. A `beforeUpdate` that
stops a password hash from being re-hashed, or a `beforeCreate` that generates
a temporary password, fails and the row is written anyway — without its guard,
or with an empty password — while the user is told it worked. Since **1.34.3**
there are three ways to say *not this one*:

**1. Throw `CrudHookAbort` (no configuration).** Never swallowed.

```php
use Ptah\Exceptions\CrudHookAbort;

public function beforeCreate(array &$data, ?Model $record, object $component): void
{
    if (! $this->vault->available()) {
        // A mensagem chega ao usuário, não só ao log.
        throw new CrudHookAbort('A senha temporária não pôde ser gerada.');
    }
}
```

**2. Throw `ValidationException`.** Laravel's own way to reject input, and the
one exception swallowing can never be right for — a hook that validates and is
ignored lets the save proceed with the data it refused. It reaches Livewire
untouched and renders as a **field** error:

```php
throw ValidationException::withMessages(['sku' => 'Este SKU já existe.']);
```

**3. Declare the hook critical** — for a hook whose code you cannot change, or
one that throws a plain exception. Both shapes work:

```json
{
  "lifecycleHooks": { "beforeCreate": "@ProductHooks::beforeCreate" },
  "lifecycleHooksCritical": { "beforeCreate": true }
}
```

```json
{ "lifecycleHooksCritical": ["beforeCreate", "beforeUpdate"] }
```

> **Why a sibling key and not `{"handler": …, "critical": true}` inside the
> hook?** The object form is accepted, but the visual config editor rebuilds
> `lifecycleHooks` as plain strings whenever it saves, so criticality declared
> *inside* the hook is lost on the next edit from the UI.
> `lifecycleHooksCritical` is a top-level key the editor leaves untouched.

**`before*` and `after*` abort differently.** A `before*` hook runs before
anything is written, so aborting leaves nothing behind. An `after*` hook runs
after the row is committed and there is **no transaction** around the save — so
the abort reports the failure and the record **stays**. The message says which
happened (`crud_hook_aborted` vs `crud_hook_after_failed`); put anything that
must be able to refuse the write in a `before*` hook.

Every failure is logged either way, with the hook name, the model and the hook
source.

---

### 🚀 **Quick Start: Creating your first hooks class**

**Option A — Artisan (recommended):**

```bash
php artisan ptah:hooks ProductHooks
```

Creates `app/CrudHooks/ProductHooks.php` with the 5 pre-filled methods (`beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`), ready to edit.

With subfolder:
```bash
php artisan ptah:hooks Inventory/StockHooks
```

**Option B — Copy the example template:**

```bash
cp vendor/jonytonet/ptah/docs/ProductHooks.example.php app/CrudHooks/ProductHooks.php
```

**Generated structure:**

```php
<?php

namespace App\CrudHooks;

use Illuminate\Database\Eloquent\Model;
use Ptah\Contracts\CrudHooksInterface;

class ProductHooks implements CrudHooksInterface
{
    public function beforeCreate(array &$data, ?Model $record, object $component): void
    {
        // $data['status'] = 'pending';
    }

    public function afterCreate(array &$data, Model $record, object $component): void
    {
        // event(new \App\Events\ProductCreated($record));
    }

    public function beforeUpdate(array &$data, Model $record, object $component): void
    {
        // $data['updated_by'] = auth()->id();
    }

    public function afterUpdate(array &$data, Model $record, object $component): void
    {
        // cache()->forget('product_' . $record->getKey());
    }

    public function beforeDelete(array &$data, Model $record, object $component): void
    {
        // throw new \Ptah\Exceptions\CrudHookAbort('In use by active products.');
    }
}
```

> 💡 Implementing `Ptah\Contracts\CrudHooksInterface` ensures autocomplete and validation by PHPStan/Psalm.

> ⚠️ **About `$component`:** It is the full instance of the Livewire component. Prefer to only modify `$data` (in `before*` hooks) and dispatch events in `after*` hooks — avoid directly changing the component's internal state.

**2. Configure in the modal:**

In the "Before Create" field, write only:
```
@ProductHooks
```

Or specifying the method:
```
@ProductHooks::beforeCreate
```

**3. Done!** The hooks will be executed automatically on create/update.

> 📁 **Reference file:** [ProductHooks.example.php](ProductHooks.example.php)  
> - 300+ lines of documented code  
> - 20+ practical use cases  
> - Reusable helper methods  
> - Ready to copy and adapt  

---

### Saving the Configuration

1. Click the **"Save"** button (top right corner of the modal)
2. The system:
   - Validates the data
   - Saves to the `crud_configs` table
   - Invalidates the cache
   - Dispatches the event `ptah:crud-config-updated`
3. BaseCrud automatically reloads the config

### Dispatched Events

| Event | When | Payload |
|--------|--------|---------|
| `ptah:crud-config-updated` | After saving the modal | none — dispatched without payload |

**Listening in BaseCrud** (`HasCrudLifecycle`):

```php
#[On('ptah:crud-config-updated')]
public function reloadCrudConfig(): void
{
    // forgets the cached lookup, re-reads the config and refreshes the form columns
}
```

---

#### 9️⃣ Notifications

**What it does:** Configures rules that turn this model's `created` /
`updated` / `deleted` events into notifications through the always-on
notification centre — no code, see [docs/Notifications.md](Notifications.md#config-driven-crud-notifications)
for the full behaviour (placeholders, precedence, delivery, why there is no
`dedupe_key` here).

**Requirements:**
- `ptah.notifications.enabled` must be on — otherwise the tab shows a
  step-by-step card (env, publish, migrate) instead of the rule list.
- The model must `use Ptah\Traits\SendsCrudNotifications;` — the tab warns
  when it does not, since rules configured for a model without the trait
  never fire.

**Interface:**
- List of configured rules (event, audience, title) with edit/remove
- Form: event, type, audience + audience value (with a live estimated
  recipient count), title, body, available `%column%` placeholders, url,
  action label, category, icon, and a "notify the actor too" toggle

**Example rule (one entry of `notifications.rules`):**

```json
{
  "event": "created",
  "audience": "role",
  "audienceValue": "Finance",
  "title": "New invoice: %code% (#%id%)",
  "body": "Total: %total%",
  "url": "/invoices/%id%",
  "type": "info",
  "category": "billing",
  "icon": "bx bx-receipt",
  "actionLabel": "Open invoice",
  "notifySelf": false
}
```

---

## Comando CLI (ptah:config)

### Overview

The `ptah:config` command allows you to configure CRUDs via terminal, offering two modes:

| Mode | Syntax | Best For |
|------|---------|-------------|
| **Interactive** | `ptah:config "App\Models\Product"` | First configuration, exploration |
| **Declarative** | `ptah:config "App\Models\Product" --column="..." --action="..."` | Automation, scripts, CI/CD |

### Basic Syntax

```bash
php artisan ptah:config {model} [options]
```

### Available Options

| Option | Type | Description |
|-------|------|-------------|
| `--column=*` | Array | Add/update column |
| `--action=*` | Array | Add custom action |
| `--filter=*` | Array | Add custom filter |
| `--style=*` | Array | Add style rule |
| `--join=*` | Array | Add JOIN |
| `--set=*` | Array | Define general setting |
| `--permission=*` | Array | Define permission |
| `--list` | Flag | List current configuration |
| `--reset` | Flag | Delete the saved config of the screen (always asks for confirmation) |
| `--import=` | String | Import from JSON (always overwrites the saved config) |
| `--export=` | String | Export to JSON |
| `--non-interactive` | Flag | Skip wizard |
| `--route=` | String | Route path to scope the config to (empty = global config of the model); `--list`, `--reset`, `--import` and `--export` honour it |
| `--force` | Flag | No effect — kept so existing scripts do not break (the command warns) |
| `--dry-run` | Flag | Simulate without saving |
| `--only=*` | Array | Process only these sections — comma-separated or repeated; an unknown name is an error |
| `--skip=*` | Array | Skip these sections — same syntax |

### Interactive Mode (Wizard)

Run without options to start the wizard:

```bash
php artisan ptah:config "App\Models\Product"
```

**Wizard flow:**

```
=== Column Configuration Wizard ===

1. Basic Information
   Field name (physical column): price
   Label (display name): Price
   Column type: [text, textarea, number, date, datetime, select, searchdropdown, boolean, file, image, color, time, datetime-local, range, email, url, tel]
   ├─ Selected: number
   Text alignment: [text-start, text-center, text-end]
   ├─ Selected: text-end
   Column width: 120px
   Placeholder text: 0.00
   Help text: Enter the product price
   Default value: 0
   Save to database? [yes]
   Required field? [no] (yes)
   Filterable? [yes]
   Visible in list? [yes]
   Editable in form? [yes]

2. Configure renderer options? [yes]
   Renderer type: [text, badge, pill, boolean, money, date, datetime, link, image, truncate, number, filesize, duration, code, color, progress, rating, qrcode]
   ├─ Selected: money
   Currency: [BRL, USD, EUR]
   ├─ Selected: BRL
   Decimal places: 2

3. Configure input mask? [no] (yes)
   Input mask: [none, money_brl, money_usd, percent, cpf, cnpj, rg, pis, ncm, ean13, phone, cep, plate, credit_card, date, datetime, time, integer, uppercase]
   ├─ Selected: money_brl
   Decimal places: 2
   Transform on save: [money_to_float, digits_only, ...]
   ├─ Selected: money_to_float

4. Add validation rules? [yes]
   Select validation rules:
   ├─ [x] Required field
   ├─ [x] Must be a valid number
   ├─ [ ] Valid email
   ├─ [ ] Valid URL
   └─ [ ] Valid CPF
   Add custom validation rules? [no] (yes)
   └─ Enter custom Laravel validation rules: min:0|max:999999

=== Column Preview ===
┌─────────────────────┬──────────────────────────┐
│ Property            │ Value                    │
├─────────────────────┼──────────────────────────┤
│ colsNomeFisico      │ price                    │
│ colsNomeLogico      │ Price                    │
│ colsTipo            │ number                   │
│ colsRenderer        │ money                    │
│ colsRendererCurrency│ BRL                      │
│ colsRendererDecimals│ 2                        │
│ colsMask            │ money_brl                │
│ colsMaskTransform   │ money_to_float           │
│ colsValidations     │ ["numeric","min:0","max:999999"]            │
│ colsRequired        │ true                     │
│ colsAlign           │ text-end                 │
└─────────────────────┴──────────────────────────┘

Save this column configuration? [yes]

> Since 1.50.1 the wizard writes only keys the runtime reads
> (`colsValidations`, `colsRelacao`/`colsRelacaoExibe`,
> `colsRendererLinkTemplate`/`colsRendererLinkNewTab`, and the totalizer into
> `totalizadores.columns`). "Required" is the `colsRequired` answer, not a rule.
✓ Column 'price' added.

Configure a column? [yes] (no)
```

**Wizard advantages:**
- ✅ Step-by-step guide
- ✅ Intelligent suggestions based on the model
- ✅ Preview before saving
- ✅ Real-time validation
- ✅ Option to redo before confirming

### Declarative Mode (Inline)

Run with options to configure directly:

```bash
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name:validation=maxLength:255" \
  --column="sku:text:required:validation=unique:products,sku" \
  --column="price:number:required:mask=money_brl:renderer=money:currency=BRL:decimals=2" \
  --column="stock:number:label=Stock:renderer=number:decimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --column="category_id:searchdropdown:sd_model=App\Models\Category" \
  --set="itemsPerPage=25"
```

### Option Syntax

#### --column (Columns)

**Format:**
```
field:type:modifier:option=value:option=value...
```

**Parts:**
1. `field` — Physical column name (required)
2. `type` — Column type (required): `text`, `textarea`, `number`, `date`, `datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image`
3. `modifier` — Optional bare modifier (never `modifier=true`): `required`, `nullable`, `readonly`, `hidden`, `sortable`, `filterable`, `not_filterable`
4. `option=value` — Key=value pairs for additional settings

**Modifiers (bare tokens):**

| Modifier | Equivalent to | Description |
|----------|--------------|-------------|
| `required` | `colsRequired = true` | Required field |
| `nullable` | `colsRequired = false` | Optional field |
| `readonly` | `colsGravar = false` | Not saved to database |
| `hidden` | `colsVisibleList = false` | Not shown in list |
| `sortable` | `colsOrderBy = <field>` | Sortable by its own column |
| `filterable` | `colsIsFilterable = true` | Filterable |
| `not_filterable` | `colsIsFilterable = false` | Not filterable |

**Options (key=value) — full `ColumnParser::$keyMap`:**

| Option | Mapping | Type | Example |
|--------|------------|------|---------|
| `label` | `colsNomeLogico` | string | `label=Product Name` |
| `placeholder` | `colsPlaceholder` | string | `placeholder=Type here...` |
| `align` | `colsAlign` | string | `align=text-end` |
| `min_width` | `colsMinWidth` | string | `min_width=120px` (there is no `width=`) |
| `renderer` | `colsRenderer` | string | `renderer=money` (`text`,`badge`,`pill`,`boolean`,`money`,`date`,`datetime`,`link`,`image`,`truncate`,`number`,`filesize`,`duration`,`code`,`color`,`progress`,`rating`,`qrcode`) |
| `cell_style` | `colsCellStyle` | string | `cell_style=background:#FEE;` |
| `cell_class` | `colsCellClass` | string | `cell_class=font-bold` |
| `cell_icon` | `colsCellIcon` | string | `cell_icon=bx bx-user` — shown in the cell, before the value |
| `source` | `colsSource` | string | `source=categories.name` — the qualified SQL column used in `WHERE`/`ORDER BY`/search for a column that comes from a JOIN |
| `method` | `colsMetodoCustom` | string | `method=ProductFormatter\price(%price%)` — `Class\method(%field%)`, relative to `App\Services\` (see [Advanced](#advanced)) |
| `order_by` | `colsOrderBy` | string | `order_by=name` — a column identifier, not an expression |
| `link_template` | `colsRendererLinkTemplate` | string | `link_template=/products/%id%` |
| `link_label` | `colsRendererLinkLabel` | string | `link_label=Ver` |
| `link_new_tab` | `colsRendererLinkNewTab` | bool | `link_new_tab=true` |
| `currency` | `colsRendererCurrency` | string | `currency=BRL` |
| `decimals` | `colsRendererDecimals` | int | `decimals=2` |
| `max_chars` | `colsRendererMaxChars` | int | `max_chars=50` |
| `badges` | `colsRendererBadges` | array | `badges=active\|green,inactive\|red` — `\|`-separated, not `:` |
| `mask` | `colsMask` | string | `mask=money_brl` |
| `mask_transform` | `colsMaskTransform` | string | `mask_transform=money_to_float` |
| `validation` | `colsValidations` | array | `validation=email\|maxLength:255` — `\|`-separated; see [Validation](#validation) for the rules (`required` is not one: use the `required` modifier) |
| `options` | `colsSelect` | string | `options=yes:Yes,no:No` |
| `relation` | `colsRelacao` | string | `relation=category` |
| `relation_display` | `colsRelacaoExibe` | string | `relation_display=name` |
| `relation_nested` | `colsRelacaoNested` | string | `relation_nested=category.parent.name` |
| `sd_model` | `colsSDModel` | string | `sd_model=App\Models\Category` |
| `sd_value` | `colsSDValor` | string | `sd_value=id` |
| `sd_label` | `colsSDLabel` | string | `sd_label=name` — see the caveat in [Commands.md](Commands.md#ptahconfig) about the keys the SearchDropdown renderer actually reads (`colsSDValor`/`colsSDLabel`) |
| `upload_path` | `colsUploadPath` | string | `upload_path=products/images` — overrides the auto-derived path |
| `upload_max_size` | `colsUploadMaxSize` | int | `upload_max_size=2048` — max size in KB (default: 2048) |
| `upload_allowed_types` | `colsUploadAllowedTypes` | string | `upload_allowed_types=jpg,png,webp` — comma-separated extensions |
| `image_width` | `colsRendererImageWidth` | int | `image_width=64` |
| `image_height` | `colsRendererImageHeight` | int | `image_height=64` |
| `totalizer` | `totalizadores.columns` (`{field, aggregate}`; also turns `totalizadores.enabled` on) | string | `totalizer=sum` (`sum`,`avg`,`count`,`min`,`max`) — screen-level, not stored in the column (since 1.50.0) |
| `totalizer_format` / `totalizer_label` | — | string | No effect: the command warns and ignores them — the total uses the column's own renderer |
| `permission` | `colsPermission` | string | `permission=page::viewCost` — column-level visibility gate, see [Permissions.md § Column-level permissions](Permissions.md#column-level-permissions) |

> Any `option=value` key not in `ColumnParser::$keyMap` is stored verbatim under
> that raw key instead of being translated — e.g. `rendererDecimals=2` (not in
> the map) silently sets a `rendererDecimals` key nothing reads; use `decimals=2`.

**Examples:**

```bash
# Simple required text
--column="name:text:required:label=Product Name"

# Email with validation
--column="email:text:required:validation=email|maxLength:255"

# Price with mask and renderer
--column="price:number:required:mask=money_brl:renderer=money:currency=BRL:decimals=2"

# Status with select and badges
--column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red,pending|yellow"

# SearchDropdown for category
--column="category_id:searchdropdown:sd_model=App\Models\Category"

# Readonly date
--column="created_at:datetime:readonly:renderer=datetime"

# Boolean
--column="active:boolean:required"

# Description with textarea
--column="description:textarea:nullable:placeholder=Enter description"

# Image with upload — auto path: storage/app/public/images/product/
--column="image:image:upload_path=products:upload_max_size=2048:upload_allowed_types=jpg,png,webp"

# Image with custom path and strict types
--column="photo:image:upload_path=avatars:upload_max_size=1024:upload_allowed_types=jpg,png"

# Number with totalizer (adds {field: quantity, aggregate: sum} to totalizadores.columns)
--column="quantity:number:totalizer=sum"
```

#### --action (Actions)

**Format:**
```
name:type:value:icon=icon:color=color:confirm=bool
```

**Examples:**

```bash
# Livewire action with confirmation
--action="approve:livewire:approve(%id%):icon=bx bx-check:color=success:confirm=true"

# External link (%field% works in link actions only)
--action="view:link:https://example.com/products/%id%:icon=bx bx-show:color=primary"

# JavaScript action
--action="export:javascript:exportData():icon=bx bx-download:color=info"

# Action with custom confirmation
--action="delete:livewire:deleteCustom(%id%):icon=bx bx-trash:color=danger:confirm=true:confirmMessage=Are you sure?"
```

#### --filter (Filters)

**Format:**
```
field:type:key=value:key=value
```

Everything after `field:type` is `key=value` — **there is no positional operator**:
`name:text:LIKE:label=…` stores nothing for `LIKE` and filters by equality.
Write `operator=LIKE`. Types: `text`, `number`, `date`, `select`,
`searchdropdown` (a boolean is a `select` with `options=1:Yes,0:No`). Run
`php artisan ptah:docs filter` for the options the parser reads.

**Examples:**

```bash
# Simple select
--filter="status:select:label=Status:options=active,inactive,pending"

# Number with minimum
--filter="price:number:operator=>=:label=Minimum Price"

# Date
--filter="created_at:date:operator=>=:label=From Date"

# SearchDropdown: not a custom-filter type. Make the COLUMN a filterable
# searchdropdown and the panel offers it (docs/BaseCrud.md § searchdropdown).

# Text with LIKE
--filter="name:text:operator=LIKE:label=Search Name"
```

#### --style (Styles)

Persisted under `contitionStyles` in the saved config (the correctly-spelled
`conditionStyles` is accepted as a read alias, but never written). This is also
the key the published JSON schema (`JsonSchemaBuilder`) documents — it used to
name `styles`, which nothing reads at render time.

**Format:**
```
field:condition:value:style
```

**Long form — required when the value itself contains a `:`**
```
field:condition:value:style=<css>
```

The style segment is colon-rich by nature (`background:#eee;color:#111`), so the
short form gives it everything after the third colon. That means a *value*
containing a colon is cut at that colon and the remainder ends up inside the
CSS, producing a rule that never matches — with no error. The `style=` marker
ends the value explicitly:

```bash
# WRONG — value becomes "12", style becomes "30:background:#eee;"
--style="start_at:==:12:30:background:#eee;"

# RIGHT — value stays "12:30"
--style="start_at:==:12:30:style=background:#eee;"
```

Every call without the marker parses exactly as it always did; the marker is
purely additive.

`condition` is one of `==`, `!=`, `>`, `<`, `>=`, `<=`, `*` (or the aliases `eq`,
`ne`, `lt`, `gt`, `lte`, `gte`, `=`, and `always`/`any`/`true` for `*`) — these
are the only operators `HasCrudRenderers::getRowStyle()` evaluates at render
time; anything else (including `LIKE`) is rejected. `*` means "no test": the
rule applies to every row. `style` is an inline CSS declaration list —
everything from the 4th colon onward, so it may contain its own colons and
semicolons.

**Examples:**

```bash
# Cancelled status in red
--style="status:==:cancelled:background:#FEE2E2;color:#991B1B;font-weight:bold;"

# High priority in yellow
--style="priority:>:5:background:#FEF3C7;color:#92400E;"

# Low stock
--style="stock:<:10:background:#DBEAFE;color:#1E40AF;"
```

#### --join (JOINs)

**Format:**
```
type:table:leftCol=rightCol:select=col:alias,col2 as alias2:distinct
```

Saved in the shape the runtime reads — `{type, table, first, second, distinct,
select: [{column, alias}]}` (see [JOIN Configuration](#join-configuration)):

- `select=` takes `column:alias` or `column as alias`, comma-separated. Without
  an alias the alias is the column name (`users.name` → `name`).
- `distinct` as a bare token, or `distinct=true`.
- Any other `key=value` option — `where=` included — is **refused with an
  error**: a JOIN has no `WHERE`, and nothing was ever applied for it.

**Examples:**

```bash
# LEFT JOIN with users
--join="left:users:products.user_id=users.id:select=users.name,users.email"

# INNER JOIN with categories, aliased column
--join="inner:categories:products.category_id=categories.id:select=categories.name as category_name"

# JOIN with DISTINCT
--join="left:suppliers:products.supplier_id=suppliers.id:select=suppliers.name:supplier_name:distinct"
```

The interactive JOIN wizard writes the same shape.

#### --set (General Settings)

**Format:**
```
key=value
```

**Examples:**

```bash
--set="itemsPerPage=25"
--set="exportEnabled=true"
--set="exportMaxRows=10000"
--set="compactMode=false"
--set="displayName=Products"
```

#### --permission (Permissions)

**Format:**
```
key=gate_name
```

The keys are those of [Permissions Configuration](#permissions-configuration):
`create`, `edit`, `delete`, `restore`, `export`, `import`, `history`,
`attachments` (a gate name) and the `show*Button` flags (`true`/`false`).

**Examples:**

```bash
--permission="create=product.create"
--permission="edit=product.update"
--permission="delete=product.destroy"
--permission="export=product.export"
--permission="showTrashButton=false"
```

### Special Commands

#### --list (List Configuration)

Displays the current configuration in table format:

```bash
php artisan ptah:config "App\Models\Product" --list
```

**Output:**

```
Configuration for: App\Models\Product

=== GENERAL SETTINGS ===
+-----------------------+-----------+
| Setting               | Value     |
+-----------------------+-----------+
| Display Name          | Products  |
| Permission Identifier | product   |
| Cache Enabled         | Yes       |
| Cache TTL             | 300s      |
| Export Max Rows       | 10000     |
| Orientation           | landscape |
| Paper Size            | N/A       |
+-----------------------+-----------+

=== COLUMNS (3) ===
+--------+--------+--------------+---------+------+----------+------------+----------+-----------+
| Field  | Type   | Label        | In list | Form | Required | Filterable | Renderer | Mask      |
+--------+--------+--------------+---------+------+----------+------------+----------+-----------+
| name   | text   | Product Name | Yes     | Yes  | Yes      | Yes        | text     | -         |
| price  | number | Price        | Yes     | Yes  | Yes      | Yes        | money    | money_brl |
| status | select | Status       | Yes     | Yes  | Yes      | Yes        | badge    | -         |
+--------+--------+--------------+---------+------+----------+------------+----------+-----------+

=== CUSTOM ACTIONS (1) ===
+---------+----------+---------------+-------------+---------+------------+
| Name    | Type     | Value         | Icon        | Color   | Permission |
+---------+----------+---------------+-------------+---------+------------+
| Approve | livewire | approve(%id%) | bx bx-check | success | -          |
+---------+----------+---------------+-------------+---------+------------+

=== PERMISSIONS ===
+---------+-----------------+----------------+
| Action  | Gate            | Button Visible |
+---------+-----------------+----------------+
| Create  | product.create  | Yes            |
| Edit    | product.update  | Yes            |
| Delete  | product.destroy | Yes            |
| Export  | product.export  | -              |
| Restore | -               | Yes            |
+---------+-----------------+----------------+
```

`FILTERS` (filterable columns plus custom filters; the `Kind` column says which),
`CONDITIONAL STYLES` and `JOINS` tables appear between the actions and the
permissions when there is something to list. There are no pagination, search,
soft-delete or theme rows: none of those is a config key (see
[General Settings](#general-settings)).

#### --reset (Reset Configuration)

**Deletes** the saved config of the screen (the `crud_configs` row for that model
and `--route`) and forgets its cache. It does **not** write defaults back. It always asks for
confirmation — `--force` does not skip it — and it cannot be undone, so
`--export` first.

```bash
php artisan ptah:config "App\Models\Product" --reset
```

**Prompt:**
```
Are you sure you want to reset configuration for App\Models\Product (global config)? (yes/no) [no]
> yes
✓ Configuration reset successfully!
```

#### --import (Import from JSON)

Imports configuration from a JSON file:

```bash
php artisan ptah:config "App\Models\Product" --import=product-config.json
```

**JSON format:**

```json
{
  "cols": [...],
  "customFilters": [...],
  "contitionStyles": [...],
  "joins": [...],
  "permissions": {...},
  "uiPreferences": { "perPage": 25 },
  "exportConfig": { "enabled": true }
}
```

#### --export (Export to JSON)

Exports current configuration to a JSON file:

```bash
php artisan ptah:config "App\Models\Product" --export=product-config.json
```

**Result:**
```
✓ Configuration exported successfully to product-config.json
```

**Typical use:**

```bash
# 1. Export production config
php artisan ptah:config "App\Models\Product" --export=product-config.json

# 2. Commit no Git
git add product-config.json
git commit -m "chore: export Product CRUD config"
git push

# 3. Deploy em homolog/dev
git pull
php artisan ptah:config "App\Models\Product" --import=product-config.json
```

#### --dry-run (Simulate without Saving)

Shows the changes that would be applied without saving:

```bash
php artisan ptah:config "App\Models\Product" \
  --column="discount:number:label=Discount:renderer=number" \
  --dry-run
```

**Output:**
```
Processing declarative configuration for App\Models\Product...
Processing columns...

Configuration Summary:
- Columns: 6
- Actions: 2
- Filters: 2
- Styles: 0
- Joins: 0

⚠️  Dry-run mode: No changes were saved.
```

#### --only / --skip (Process Specific Sections)

Limits or excludes sections from the configuration. Both take a comma-separated
list, or the option repeated (`--only=columns --only=actions`); a name outside
the list below is an error. (Before 1.50.0 `--only=columns,actions` matched no
section, so nothing was processed.)

```bash
# Configure only columns and actions
php artisan ptah:config "App\Models\Product" \
  --only=columns,actions \
  --column="name:text:required"

# Configurar tudo exceto JOINs e estilos
php artisan ptah:config "App\Models\Product" \
  --skip=joins,styles \
  --column="price:number"
```

**Available sections:**
- `columns`
- `actions`
- `filters`
- `styles`
- `joins`
- `general`
- `permissions`

---

## Comparison: Modal vs CLI

| Criteria | Visual Modal | CLI Command |
|----------|--------------|-------------|
| **Interface** | Graphical, intuitive | Terminal, text |
| **Learning Curve** | Low | Medium |
| **Speed (first time)** | Slow | Medium |
| **Speed (repeated)** | Medium | Fast |
| **Versioning** | ❌ No | ✅ Yes (export JSON) |
| **Automation** | ❌ No | ✅ Yes (scripts) |
| **CI/CD** | ❌ No | ✅ Yes |
| **Batch Operations** | ❌ One at a time | ✅ Loop multiple models |
| **Visual Preview** | ✅ Yes | ❌ No |
| **Drag-and-Drop** | ✅ Yes | ❌ No |
| **Color Picker** | ✅ Yes | ❌ Manual hex |
| **Smart Suggestions** | ⚠️ Limited | ✅ Yes (AI-based) |
| **Requires Auth** | ✅ Yes | ❌ No |
| **Requires Browser** | ✅ Yes | ❌ No |
| **Offline** | ❌ No | ✅ Yes |
| **Undo/Redo** | ⚠️ Manual | ✅ Git revert |
| **Documentation** | ⚠️ Tooltips | ✅ `--help` |

**Recommendation:**

- 🎨 **Visual Modal:** Best for first configuration, one-off adjustments, exploring options
- 💻 **CLI Command:** Best for automation, multiple models, versioning, CI/CD, reproducibility

**Ideal Workflow:**

1. Configure the first model via **Visual Modal** (exploration)
2. Export to JSON: `php artisan ptah:config "App\Models\Product" --export=product.json`
3. Version the JSON in Git
4. Create a script for other models based on the JSON
5. Use CLI for batch adjustments

---

## CrudConfig Structure (JSON)

Full configuration saved in the `crud_configs.config` table:

```json
{
  "displayName": "Products",
  "cols": [
    {
      "colsNomeFisico": "id",
      "colsNomeLogico": "ID",
      "colsTipo": "number",
      "colsRenderer": "number",
      "colsGravar": false,
      "colsRequired": false,
      "colsIsFilterable": false,
      "colsVisibleList": true,
      "colsEditableForm": false,
      "colsAlign": "text-start"
    },
    {
      "colsNomeFisico": "name",
      "colsNomeLogico": "Product Name",
      "colsTipo": "text",
      "colsRenderer": "text",
      "colsGravar": true,
      "colsRequired": true,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsValidations": ["maxLength:255"],
      "colsAlign": "text-start"
    },
    {
      "colsNomeFisico": "price",
      "colsNomeLogico": "Price",
      "colsTipo": "number",
      "colsRenderer": "money",
      "colsRendererCurrency": "BRL",
      "colsRendererDecimals": 2,
      "colsMask": "money_brl",
      "colsMaskTransform": "money_to_float",
      "colsGravar": true,
      "colsRequired": true,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsValidations": ["numeric", "min:0"],
      "colsAlign": "text-end"
    },
    {
      "colsNomeFisico": "status",
      "colsNomeLogico": "Status",
      "colsTipo": "select",
      "colsRenderer": "badge",
      "colsRendererBadges": [
        { "value": "active", "label": "Active", "color": "green" },
        { "value": "inactive", "label": "Inactive", "color": "red" },
        { "value": "pending", "label": "Pending", "color": "yellow" }
      ],
      "colsSelect": {
        "Active": "active",
        "Inactive": "inactive",
        "Pending": "pending"
      },
      "colsGravar": true,
      "colsRequired": true,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsValidations": ["in:active,inactive,pending"],
      "colsAlign": "text-center"
    },
    {
      "colsNomeFisico": "category_id",
      "colsNomeLogico": "Category",
      "colsTipo": "searchdropdown",
      "colsRelacao": "category",
      "colsRelacaoExibe": "name",
      "colsSDModel": "App\\Models\\Category",
      "colsSDLabel": "name",
      "colsSDValor": "id",
      "colsSDOrder": "name ASC",
      "colsSDTipo": "model",
      "colsGravar": true,
      "colsRequired": false,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsAlign": "text-start"
    },
    {
      "colsNomeFisico": "approve",
      "colsNomeLogico": "Approve",
      "colsTipo": "action",
      "actionType": "livewire",
      "actionValue": "approve(%id%)",
      "actionIcon": "bx bx-check",
      "actionColor": "success",
      "actionConfirm": true,
      "actionConfirmMessage": "Are you sure you want to approve this product?",
      "actionPermission": "product.approve"
    },
    {
      "colsNomeFisico": "reject",
      "colsNomeLogico": "Reject",
      "colsTipo": "action",
      "actionType": "livewire",
      "actionValue": "reject(%id%)",
      "actionIcon": "bx bx-x",
      "actionColor": "danger",
      "actionConfirm": true,
      "actionConfirmMessage": "Are you sure you want to reject this product?",
      "actionPermission": "product.reject"
    }
  ],
  "customFilters": [
    {
      "field": "status",
      "label": "Status",
      "colsFilterType": "select",
      "operator": "=",
      "colsSelect": {
        "Active": "active",
        "Inactive": "inactive",
        "Pending": "pending"
      }
    },
    {
      "field": "created_at",
      "label": "Creation Date",
      "colsFilterType": "date",
      "operator": ">="
    }
  ],
  "contitionStyles": [
    {
      "field": "status",
      "condition": "==",
      "value": "inactive",
      "style": "background:#FEE2E2;color:#991B1B;"
    },
    {
      "field": "stock",
      "condition": "<",
      "value": "10",
      "style": "background:#FEF3C7;color:#92400E;font-weight:bold;"
    }
  ],
  "joins": [
    {
      "type": "left",
      "table": "categories",
      "first": "products.category_id",
      "second": "categories.id",
      "distinct": false,
      "select": [{ "column": "categories.name", "alias": "category_name" }]
    }
  ],
  "permissions": {
    "permissionIdentifier": "product",
    "create": "product.create",
    "edit": "product.update",
    "delete": "product.destroy",
    "export": "product.export",
    "restore": "product.restore"
  },
  "notifications": {
    "rules": [
      {
        "event": "created",
        "audience": "role",
        "audienceValue": "Finance",
        "title": "New invoice: %code% (#%id%)",
        "body": "Total: %total%",
        "url": "/invoices/%id%",
        "type": "info",
        "category": "billing",
        "icon": "bx bx-receipt",
        "actionLabel": "Open invoice",
        "notifySelf": false
      }
    ]
  },
  "displayName": "Products",
  "companyField": "company_id",
  "quickDateColumn": "created_at",
  "uiPreferences": { "perPage": 25, "compactMode": false },
  "exportConfig": { "enabled": true, "maxRows": 10000, "orientation": "landscape", "formats": ["excel", "pdf"] },
  "broadcast": { "enabled": false, "channel": "page-product-observer", "event": ".pageProductObserver" },
  "tableClass": ""
}
```

---

## Column Configuration

Properties of each column in `cols[]`:

### Basic Properties

| Property | Type | Default | Description |
|-------------|------|--------|-----------|
| `colsNomeFisico` | string | — | Physical column name in the database (required) |
| `colsNomeLogico` | string | `ucfirst(colsNomeFisico)` | Display label |
| `colsTipo` | string | `'text'` | Input type: `text`, `textarea`, `number`, `date`, `datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image`, `color`, `time`, `datetime-local`, `range`, `email`, `url`, `tel`, `password` (`CrudConfigEnums::COLUMN_TYPES`). `boolean` is an on/off switch when the column is NOT NULL, and a Yes/No select when it is nullable (a switch cannot say "not informed"). `password`: never filled when editing, empty means keep, saved hashed (`Hash::make`, or Laravel's `hashed` cast when the model has it); the listing shows `••••••` and the export leaves the column out. |
| `colsGravar` | bool | `true` | Save value to database |
| `colsRequired` | bool | `false` | Required field |
| `colsIsFilterable` | bool | `true` | Allows filtering by this column |
| `colsPermission` | string | `''` | Column-level visibility gate (requires the `permissions` module). An `obj_key` registered in `ptah_page_objects` — or, when that key collides across pages, its qualified form `{page.slug}::{obj_key}` (see [Permissions.md § Column-level permissions](Permissions.md#column-level-permissions)). Empty means public (no gate) — this is the default for every column. |
| `colsVisibleList` | bool | `true` | Show in list |
| `colsEditableForm` | bool | `true` | Shown in the form. `false` removes the field from the form |
| `colsReadonly` | bool | `false` | Shown in the form as text, never editable and never saved — even a forged value is dropped (1.48.0). Unlike `colsEditableForm: false`, the person sees the value |
| `colsAlign` | string | `'text-start'` | Alignment: `text-start`, `text-center`, `text-end` |
| `colsSource` | string | `''` | Qualified SQL column (e.g.: `categories.name`) used in `WHERE`, `ORDER BY` and search for a column that comes from a JOIN — it takes precedence over `colsOrderBy` for ordering |

### Cell Style

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsCellStyle` | string | `''` | Inline CSS for the cell (e.g.: `background: #FEE;`) |
| `colsCellClass` | string | `''` | CSS classes for the cell |
| `colsCellIcon` | string | `''` | Icon shown in the cell, before the value (not in the header), with its base class (e.g.: `bx bx-user`, `fa fa-user`) |
| `colsMinWidth` | string | `''` | Minimum width (e.g.: `100px`) |

### Display and Rendering

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsHelpText` | string | `''` | Help text below the field in the create/edit form |
| `colsHelper` | string | `''` | **Legacy formatting helper**, not help text: `dateFormat`, `dateTimeFormat`, `currencyFormat`, `yesOrNot`, `flagChannel` — prefer `colsRenderer`. `ptah:config:doctor` warns on any other value. |
| `colsRenderer` | string | `'text'` | Renderer type: `text`, `badge`, `pill`, `boolean`, `money`, `date`, `datetime`, `link`, `image`, `truncate`, `number`, `filesize`, `duration`, `code`, `color`, `progress`, `rating`, `qrcode` |
| `colsRendererLinkTemplate` | string | `''` | URL pattern for `link` renderer (e.g.: `/products/%id%`) |
| `colsRendererLinkNewTab` | bool | `false` | Open the link in a new tab |
| `colsRendererCurrency` | string | `'BRL'` | Currency for `money` renderer: `BRL`, `USD`, `EUR` |
| `colsRendererDecimals` | int | `2` | Decimal places for `money` or `number` renderer |
| `colsRendererFormat` | string | `''` | Format for `date`/`datetime` renderer (e.g.: `d/m/Y H:i:s`) |
| `colsRendererMaxChars` | int | `50` | Maximum characters for `truncate` renderer |
| `colsRendererBadges` | list | `[]` | List of `{"value", "label", "color"}` entries for the `badge`/`pill` renderers (`color`: `green`, `yellow`, `red`, `blue`, `indigo`, `purple`, `pink`, `gray`; optional `icon`) |

### Relation

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsRelacao` | string | `''` | Eloquent relation method name |
| `colsRelacaoExibe` | string | `''` | Field to display from the relation (no default — set it) |
| `colsRelacaoNested` | string | `''` | Nested relation (e.g.: `category.parent.name`) |
| `colsSDModel` | string | `''` | Model class for SearchDropdown (e.g.: `App\Models\Category`), or `Namespace\Class\method` in service mode |
| `colsSDTipo` | `'model'\|'service'` | `'model'` | SearchDropdown search mode |
| `colsSDLabel` | string | `'name'` | Display field in SearchDropdown (dot-notation supported for a relation column) |
| `colsSDValor` | string | `'id'` | Value field in SearchDropdown |
| `colsSDOrder` | string | `'{label} ASC'` | SearchDropdown sort order (raw `ORDER BY`) |
| `colsSDLimit` | int | `15` | Result limit |
| `colsSDInitWithData` | bool | `true` | When `false`, the dropdown stays empty on focus until a term is typed |

> The full SearchDropdown surface (`colsSDLabelTwo`/`colsSDLabelThree`,
> `colsSDMaskOne`/`Two`/`Three`, `colsSDArraySearch`, `colsSDFilters`,
> `colsSDPlaceholder`, `colsSDStartList`, `colsSDDependsOn`/`colsSDFilterColumn`
> for cascading dropdowns, and `colsSDService`/`colsSDServiceMethod` for
> service mode) is documented in full in
> [SearchDropdown.md § BaseCrud inline widget configuration surface](SearchDropdown.md#basecrud-inline-widget-configuration-surface) —
> including the read-alias resolution a config written in an older dialect
> still relies on.

### Input Mask

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsMask` | string | `''` | Input mask: `money_brl`, `money_usd`, `percent`, `cpf`, `cnpj`, `rg`, `pis`, `ncm`, `ean13`, `phone`, `cep`, `plate`, `credit_card`, `date`, `datetime`, `time`, `integer`, `uppercase` |
| `colsMaskTransform` | string | `''` | Transform on save: `money_to_float`, `digits_only`, `plate_clean`, `date_br_to_iso`, `date_iso_to_br`, `uppercase`, `lowercase`, `trim` |

A field left empty is saved as `null` when the column is nullable in the
database, and as `''` otherwise (1.47.0). There is no per-column key for it.

### Validation

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsValidations` | array | `[]` | Array of validation rules: `["email", "maxLength:255"]`. Applied only to a non-empty value |

Rules (`FormValidatorService`): `email`, `url`, `integer`, `numeric`, `alpha`,
`alphaNum`, `cpf`, `cnpj`, `phone`, `ncm`, `min:X`, `max:X`, `between:X,Y`,
`minLength:X`, `maxLength:X`, `digits:N`, `digitsBetween:N,M`, `regex:pattern`,
`in:a,b`, `notIn:a,b`, `after:date|today`, `before:date|today`,
`confirmed:field`, `dateFormat:d/m/Y`, `unique:Model,field`.

- **`required` is not a rule** — it matches nothing. Mark the column with `colsRequired` (or the `required` modifier in `--column`).
- **`min` / `max` are numeric**: they compare the value, and are skipped when it is not a number. A text length limit is `minLength:N` / `maxLength:N`.
- **`unique:Target,field[,ignoreId]`**: `Target` is a model class (`App\Models\Product`, or `Product` under `App\Models\`) **or a table name** (`unique:products,sku`). The record being edited is ignored. A target that is neither is **not enforced** — duplicates are accepted: it is logged as a warning and `ptah:config:doctor` reports the rule, but the save is not blocked.

### Select Options

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsSelect` | object | `{}` | Options for `<select>`: `{"Label": "value"}` — label first. (`colsOptions`, shown here before 1.43.2, is not read by ptah and is rejected by the import validator.) |

### File Upload

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsUploadPath` | string | `''` | Upload path (e.g.: `products/images`) |
| `colsUploadMaxSize` | int | `2048` | Maximum size in KB |
| `colsUploadAllowedTypes` | array | `[]` | Allowed extensions: `["jpg", "png", "webp"]` |

#### Image field — how upload works

The `image` column type uses `Livewire\WithFileUploads` to transfer the file from the browser to the server. Understanding the full flow helps avoid common mistakes.

**Flow:**

1. The user selects a file — `FileReader.readAsDataURL()` generates an instant client-side preview.
2. Livewire sends the file in chunks to `livewire/upload-file`, storing it as a `TemporaryUploadedFile` on the `local` disk (`livewire-tmp/`).
3. On `save()`, `BaseCrud` calls `$upload->store($path, 'public')`, moving the file to `storage/app/public/{path}/` and returning the relative path (e.g. `images/product/AbCdEf123.jpg`).
4. That relative path is saved to the database column.
5. The renderer (`renderer=image`) resolves the relative path with `asset('storage/{path}')` for display.

**Prerequisites:**

```bash
php artisan storage:link   # creates public/storage → storage/app/public symlink
```

**Auto-derived path (when `colsUploadPath` is empty):**

| Model | Auto path |
|---|---|
| `App\Models\Product` | `images/product` |
| `App\Models\Product\ProductSupplier` | `images/product/product-supplier` |
| `App\Models\Nfe\NfeItem` | `images/nfe/nfe-item` |

Strip `App\Models\`, split by `\`/`/`, apply `Str::kebab()` to each segment, prefix with `images/`.

**Validation errors:**

If `colsUploadMaxSize` or `colsUploadAllowedTypes` is violated, the error is injected into `$formErrors[field]` before the record is persisted — the save is aborted and the user sees the error inline.

**Edit behaviour:** when editing, the existing value is displayed as the initial preview. If the user does not select a new file, the existing value is preserved. Old files are **not** deleted automatically when replaced.

**URL fallback:** the user can also paste an external URL in the text input instead of uploading a file. In that case the URL string is saved directly and no file is stored.

### Totalizer

Footer totals are not a column key: they live in the screen-level
`totalizadores.columns` list — see [BaseCrud.md § Totalisers](BaseCrud.md#totalisers).
(Before 1.47.0 this section listed per-column `colsTotal` / `totalizador*`
keys that the runtime does not read.)

### Advanced

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsOrderBy` | string | `''` | Column the header sorts by instead of the column itself — a column identifier (e.g.: `categories.name`), **not** an expression: `FIELD(status, ...)` is not accepted |
| `colsReverse` | bool | `false` | Reverse value order (for arrays) |
| `colsMetodoCustom` | string | `''` | `Class\method(%field%, 'literal')` — calls `method` on `App\Services\Class` (the `App\Services\` prefix is always added; `/` also separates namespaces) with the row's field values; the result is shown as text (`colsMetodoRaw: true` for HTML). A column with it cannot be sorted |
| `colsPlaceholder` | string | `''` | Input placeholder |
| `colsDefaultValue` | mixed | `null` | Default value on create |

---

## Action Configuration

A row action is an entry of `cols` with `colsTipo: "action"` (there is no
top-level `actions` list). Its label is `colsNomeLogico`; the rest is read from these keys:

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsNomeLogico` | string | — | Label (button tooltip, and the text shown when there is no icon) |
| `actionType` | string | `'javascript'` | Type: `livewire`, `link`, `javascript` |
| `actionValue` | string | — | Action value/command (required) |
| `actionIcon` | string | `''` | Button icon with its base class (e.g.: `bx bx-check`, `fa fa-check`). Since 1.50.0 a bare Boxicons glyph (`bx-check`) gets the `bx` base when rendered, but the editor's preview does not |
| `actionColor` | string | `'primary'` | Button color: `primary`, `success`, `danger`, `warning`, `info`, `secondary` |
| `actionConfirm` | bool | `false` | Ask before running (`wire:confirm` on livewire actions, `confirm()` on link/javascript). Read since 1.41.1 |
| `actionConfirmMessage` | string | `''` | Confirmation text; blank uses `row_action_confirm` ("Are you sure?") |
| `actionPermission` | string | `''` | Who may SEE the button (read since 1.41.1): a Gate name, or — with the Permissions module on — a ptah ACL key `object:action` (`agenda:update` → `ptah_can('agenda', 'update')`, since 1.43.0). A `livewire` action still reaches your method — check there too |

**Supported placeholders in `actionValue`:**
- `%id%` → Record ID (all action types)
- `%field%` → Value of any field of the row (e.g.: `%email%`, `%name%`) — **`link` actions only** (since 1.50.0), URL-encoded; a column denied to the user or a model `$hidden` attribute becomes an empty string. In `livewire` and `javascript` actions only `%id%` is replaced, because the value would land inside an expression

**Examples:**

```json
{
  "colsNomeFisico": "approve",
  "colsNomeLogico": "Approve",
  "colsTipo": "action",
  "actionType": "livewire",
  "actionValue": "approve(%id%)",
  "actionIcon": "bx bx-check",
  "actionColor": "success",
  "actionConfirm": true,
  "actionConfirmMessage": "Approve this record?",
  "actionPermission": "product.approve"
}
```

```json
{
  "colsNomeFisico": "viewExternal",
  "colsNomeLogico": "View",
  "colsTipo": "action",
  "actionType": "link",
  "actionValue": "https://external.com/products/%id%",
  "actionIcon": "bx bx-link-external",
  "actionColor": "info"
}
```

```json
{
  "colsNomeFisico": "export",
  "colsNomeLogico": "Export",
  "colsTipo": "action",
  "actionType": "javascript",
  "actionValue": "exportData()",
  "actionIcon": "bx bx-download",
  "actionColor": "secondary"
}
```

---

## Filter Configuration

Properties of each filter in `customFilters[]` — the keys the filter panel
and `FilterService` read. (Before 1.41.4 this table listed a `colsFilter*`
vocabulary the runtime never read; `ptah:config` and the wizard normalise the
old names on write, but a hand-written config must use these.)

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `field` | string | — | Field to filter (required) |
| `label` | string | `ucfirst(field)` | Filter label |
| `colsFilterType` | string | `'text'` | Type: `text`, `number`, `date`, `select` |
| `operator` | string | `'='` | Operator: `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE` |
| `colsSelect` | object | `{}` | Options for `select`: `{"Label": "value"}` |
| `whereHas` | string | `''` | Relation name for `whereHas` |
| `field_relation` | string | `''` | Field in the related table |
| `aggregate` | string | `''` | Aggregate over the relation: `SUM`, `COUNT`, `AVG`, `MAX`, `MIN` |
| `logic` | string | `'AND'` | `AND` / `OR` with the other filters |

There is no `searchdropdown` custom filter: a filterable column of type
`searchdropdown` already gets one in the panel. (The custom-filter variant was
offered until 1.41.4 and never worked; one saved with it renders as the text
filter.)

**Examples:**

```json
{
  "field": "status",
  "label": "Status",
  "colsFilterType": "select",
  "operator": "=",
  "colsSelect": {
    "Active": "active",
    "Inactive": "inactive"
  }
}
```

```json
{
  "field": "price",
  "label": "Minimum Price",
  "colsFilterType": "number",
  "operator": ">="
}
```

```json
{
  "field": "orders_total",
  "label": "Total Orders",
  "colsFilterType": "number",
  "operator": ">",
  "whereHas": "orders",
  "field_relation": "total",
  "aggregate": "SUM"
}
```

---

## Style Configuration

Properties of each style rule in `contitionStyles[]` — the only key
`HasCrudRenderers::getRowStyle()` reads (the correctly-spelled
`conditionStyles` is accepted as a read-only alias, never written):

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `field` | string | — | Field to check (required). Legacy alias: `colsNomeFisico` |
| `condition` | string | `'=='` | Operator: `==`, `!=`, `>`, `<`, `>=`, `<=`, `*` (aliases: `eq`, `ne`, `lt`, `gt`, `lte`, `gte`, `=`; `always`, `any`, `true` for `*`). `*` applies the rule to every row (no test). `LIKE` is **not** valid — the renderer's `match()` has no arm for it |
| `value` | mixed | — | Comparison value (required) |
| `style` | string | — | Inline CSS declaration list applied to the `<tr style="...">` (required), e.g. `"background:#FEE2E2;color:#991B1B;font-weight:bold;"` |

**Example:**

```json
{
  "field": "status",
  "condition": "==",
  "value": "cancelled",
  "style": "background:#FEE2E2;color:#991B1B;font-weight:bold;"
}
```

**Result:** Rows where `status === 'cancelled'` will have:
- Light red background
- Dark red text
- Bold font

---

## JOIN Configuration

Properties of each JOIN in `joins[]`:

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `table` | string | — | Table to join (required) |
| `type` | string | `'left'` | JOIN type: `left`, `inner` |
| `first` | string | — | Column from the main table, qualified (required) |
| `second` | string | — | Column from the joined table, qualified (required) |
| `select` | list | `[]` | Extra columns: `[{"column": "table.field", "alias": "alias"}]` — both must be plain identifiers, an unsafe one is skipped |
| `distinct` | bool | `false` | Use `DISTINCT` |

An entry missing `table`, `first` or `second` is ignored, and a second JOIN on
the same table is skipped. There is no `WHERE` on a JOIN (the `joinTable`/`joinType`/
`joinLeftColumn`/`joinRightColumn`/`joinSelect`/`joinDistinct`/`joinWhere` names
older versions of this guide showed are not read by anything). The same JOINs
apply to the export.

**Example:**

```json
{
  "type": "left",
  "table": "categories",
  "first": "products.category_id",
  "second": "categories.id",
  "distinct": false,
  "select": [
    { "column": "categories.name", "alias": "category_name" },
    { "column": "categories.slug", "alias": "category_slug" }
  ]
}
```

**Resulting SQL:**

```sql
SELECT products.*, categories.name AS category_name, categories.slug AS category_slug
FROM products
LEFT JOIN categories ON products.category_id = categories.id
```

---

## General Settings

What BaseCrud reads outside `cols`, `customFilters`, `contitionStyles`,
`joins`, `permissions` and `notifications`. Anything else in the JSON is
ignored by the screen.

| Path | Type | Default | Description |
|------|------|---------|-------------|
| `displayName` | string | `class_basename($model)` | Screen title |
| `displayNameSingular` / `displayNameGender` | string / `m`\|`f` | — / `m` | Modal title for one record; `f` → "Nova" (BaseCrud.md § Modal title) |
| `companyField` | string | `'company_id'` | Column for the multi-tenant filter |
| `quickDateColumn` | string | `'created_at'` | Column the quick date filter uses |
| `tableClass` / `theadClass` | string | `'table'` / `'ptah-c-thead'` | Classes of the `<table>` / `<thead>`. They **replace** the defaults (they do not add to them); an empty string removes the default class |
| `configLinkLinha` | string | — | Row link template (`/products/%id%`) |
| `groupBy` / `groupBreak` | string | `null` | Group rows / group break with subtotals |
| `totalizadores` | object | disabled | Footer totals |
| `masterDetail` | list | `null` | Detail grid under a row |
| `bulkActions` | list | `[]` | Custom bulk actions |
| `lifecycleHooks` | object | `{}` | `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete` |
| `uiPreferences.perPage` | int | `ptah.crud.per_page` (25) | Default rows per page (until the user picks one) |
| `uiPreferences.compactMode` | bool | `false` | Compact rows by default |
| `exportConfig.enabled` | bool | `false` | Export button |
| `exportConfig.maxRows` | int | `5000` | Maximum exportable rows |
| `exportConfig.orientation` | string | `'landscape'` | PDF orientation: `landscape`, `portrait` |
| `exportConfig.formats` | list | `['excel']` | Formats offered in the export menu (`excel`, `pdf`) |
| `exportConfig.asyncThreshold` | int | `0` (the editor writes `1000`) | Since 1.50.0, rows above which an export goes to the queue on its own. Needs `asyncExport.enabled`, a real queue (not `sync`) and the `ptah_exports` table; otherwise the export stays synchronous. `0` = never |
| `exportConfig.asyncExport.enabled` / `.excel` / `.pdf` | bool | `false` / `true` / `false` | Opt-in to queued export; `excel` (default on) and `pdf` (default off) say which formats may be queued (since 1.50.0) |
| `importConfig.enabled` | bool | `false` | Spreadsheet import button (see BaseCrud.md § Import) |
| `importConfig.mode` / `importConfig.key` | string | `create` / — | `upsert` updates the record whose `key` matches |
| `importConfig.maxRows` / `importConfig.maxKb` | int | `2000` / `10240` | Limits per file |
| `kanbanConfig.field` / `.title` / `.limit` | string / string / int | — / — / `50` | Board view by a select column (BaseCrud.md § Board and Calendar) |
| `kanbanConfig.locked` / `.transitions` / `.lockedMessage` | list / object / string | — | Columns with no drop and no exit; origin → allowed destinations; toast and column hint (BaseCrud.md § Workflow statuses) |
| `kanbanConfig.with` / `calendarConfig.with` | list | `[]` | Relations eager-loaded for an accessor `title` |
| `formDraft.enabled` / `.exclude` / `.ttlDays` | bool / list / int | `false` / `[]` / `7` | Keep the modal's typed values in the browser (BaseCrud.md § Form draft) |
| `calendarConfig.start` / `.end` / `.title` | string | — | Calendar view by a date column |
| `history.enabled` | bool | `true` | History button (model with RecordsHistory) |
| `attachments.enabled` | bool | `true` | Attachments button (model with HasAttachments) |
| `broadcast.enabled` | bool | `false` | Echo listener that refreshes the screen |
| `broadcast.channel` | string | `'page-{model}-observer'` | Channel name |
| `broadcast.event` | string | `'.page{Model}Observer'` | Event name |

Pagination and global search are always on; soft deletes come from the
model's `SoftDeletes` trait, not from the config. `cacheStrategy`
(`enabled`, `ttl`) is read for the cache of the config lookup only — never for
the screen's data (see [Cache and Invalidation](#cache-and-invalidation)).

`ptah:config --set` writes any of these paths (`--set="uiPreferences.perPage=50"`)
and still accepts the older names it used to document, translating them:
`itemsPerPage`/`perPage` → `uiPreferences.perPage`, `compactMode` →
`uiPreferences.compactMode`, `exportEnabled`/`exportMaxRows`/`exportFormats`/
`pdfOrientation` → `exportConfig.*` (a list key takes a comma-separated value:
`--set="exportFormats=excel,pdf"` is stored as `["excel", "pdf"]`), `broadcastEnabled`/`Channel`/`Event` →
`broadcast.*`. Keys no code reads (`cacheEnabled`, `cacheTtl`,
`paginationEnabled`, `searchEnabled`, `softDeletes`, `striped`, `hover`…) are
refused with a warning. `ptah:config:doctor --fix` moves the old names out of
configs saved before 1.36.0.

**Usage example:**

```bash
php artisan ptah:config "App\Models\Product" \
  --set="displayName=Products" \
  --set="itemsPerPage=50" \
  --set="exportMaxRows=50000" \
  --set="compactMode=true"
```

---

## Permissions Configuration

Properties in `permissions`:

| Key | Type | Default | Description |
|-------|------|--------|----------|
| `permissionIdentifier` | string | `'page{Entity}'` | Page object key checked by the Permissions module (`ptah_can`) |
| `create` | string | `''` | Gate to create a record |
| `edit` | string | `''` | Gate to edit a record |
| `delete` | string | `''` | Gate to delete a record |
| `export` | string | `''` | Gate to export |
| `restore` | string | `''` | Gate to restore a soft-deleted record |
| `import` | string | `''` | Gate to import a spreadsheet (on top of what **New** requires) |
| `history` | string | `''` | Gate to open a record's change history |
| `attachments` | string | `''` | Gate to list and download a record's attachments |
| `showCreateButton` / `showEditButton` / `showDeleteButton` / `showTrashButton` | bool | `true` | Hide a button — and refuse the action server-side |

A gate name is checked with `Auth::user()->can()` (guests are refused); an empty value means no gate.
The flags are enforced on the server too (`crudConfigAllows()`), not only as
hidden buttons.

**Example:**

```json
{
  "permissions": {
    "permissionIdentifier": "product",
    "create": "product.create",
    "edit": "product.update",
    "delete": "product.destroy",
    "export": "product.export",
    "restore": "product.restore",
    "showTrashButton": false
  }
}
```

---

## Notifications Configuration

Properties in `notifications.rules[]` — configured through the modal's
Notifications tab (see [Tab Structure](#tab-structure) above) or hand-edited
in the JSON. Full behaviour (placeholders, precedence, delivery, security) is
documented in [docs/Notifications.md § Config-driven CRUD notifications](Notifications.md#config-driven-crud-notifications);
this table is the field reference only.

| Key | Type | Default | Description |
|-------|------|--------|----------|
| `event` | string | — | `created` \| `updated` \| `deleted` |
| `audience` | string | `'user'` | `user` \| `role` \| `staff` |
| `audienceValue` | string | `''` | user id or role name; unused for `staff` |
| `title` | string | — | required; template, truncated to 180 chars |
| `body` | string | `''` | template |
| `url` | string | `''` | template |
| `type` | string | `'info'` | `info` \| `success` \| `warning` \| `danger` |
| `category` | string | `''` | free grouping key |
| `icon` | string | `''` | icon class |
| `actionLabel` | string | `''` | button label |
| `notifySelf` | bool | `false` | include the user who triggered the event |

This feature also requires `ptah.notifications.enabled` (see
[config/ptah.php](../config/ptah.php)) and the model using
`Ptah\Traits\SendsCrudNotifications` — without either, rules configured here
are never fired.

---

## Complete Practical Examples

### Example 1: E-commerce Product

**Via Modal:**
1. Open the configuration modal
2. Configure columns:
   - `name` → text, required
   - `sku` → text, required, unique
   - `price` → number, money renderer, mask money_brl
   - `cost` → number, money renderer, mask money_brl
   - `stock` → number, number renderer
   - `status` → select (active/inactive), badge renderer
   - `category_id` → searchdropdown
3. Add custom action "Duplicate"
4. Configure status and category filters
5. Add conditional style for low stock
6. Configure totalizer for price and cost
7. Save

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name:validation=maxLength:255" \
  --column="sku:text:required:label=SKU:validation=unique:products,sku" \
  --column="price:number:required:label=Price:mask=money_brl:renderer=money:currency=BRL:decimals=2:validation=numeric|min:0:totalizer=sum" \
  --column="cost:number:label=Cost:mask=money_brl:renderer=money:currency=BRL:decimals=2:validation=numeric|min:0:totalizer=sum" \
  --column="stock:number:label=Stock:renderer=number:decimals=0:validation=integer|min:0" \
  --column="status:select:required:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --column="category_id:searchdropdown:label=Category:relation=category:sd_model=App\Models\Category:sd_value=id:sd_label=name" \
  --action="duplicate:livewire:duplicate(%id%):icon=bx bx-copy:color=info" \
  --filter="status:select:options=active,inactive" \
  --style="stock:<:10:background:#FEF3C7;color:#92400E;font-weight:bold;" \
  --set="displayName=Products" \
  --set="itemsPerPage=25" \
  --set="exportEnabled=true"
```

### Example 2: CRM Contacts

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Contact" \
  --column="name:text:required:label=Full Name:validation=maxLength:255" \
  --column="email:text:required:label=Email:validation=email|unique:contacts,email" \
  --column="phone:text:label=Phone:mask=phone:validation=phone" \
  --column="company:text:label=Company" \
  --column="position:text:label=Position" \
  --column="lead_status:select:required:options=new:New,contacted:Contacted,qualified:Qualified,lost:Lost:renderer=badge:badges=new|blue,contacted|yellow,qualified|green,lost|red" \
  --column="lead_score:number:label=Lead Score:renderer=number:decimals=0:validation=integer|min:0|max:100" \
  --column="last_contact_at:datetime:label=Last Contact:renderer=datetime" \
  --column="notes:textarea:label=Notes" \
  --action="sendEmail:livewire:sendEmail(%id%):icon=bx bx-envelope:color=primary" \
  --action="scheduleCall:livewire:scheduleCall(%id%):icon=bx bx-phone:color=info" \
  --filter="lead_status:select:options=new,contacted,qualified,lost" \
  --filter="lead_score:number:operator=>=:label=Minimum Score" \
  --style="lead_status:==:lost:background:#FEE2E2;color:#991B1B;" \
  --style="lead_score:>:80:background:#D1FAE5;color:#065F46;font-weight:bold;" \
  --set="displayName=Contacts" \
  --set="itemsPerPage=50" \
  --set="quickDateColumn=last_contact_at"
```

### Example 3: Blog Posts

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Post" \
  --column="title:text:required:label=Title:validation=maxLength:255" \
  --column="slug:text:required:label=Slug:validation=unique:posts,slug" \
  --column="excerpt:textarea:label=Excerpt:validation=maxLength:500" \
  --column="content:textarea:required:label=Content" \
  --column="featured_image:image:label=Featured Image:upload_path=posts:upload_max_size=2048:upload_allowed_types=jpg,png,webp" \
  --column="author_id:searchdropdown:required:label=Author:relation=author:sd_model=App\Models\User:sd_value=id:sd_label=name" \
  --column="category_id:searchdropdown:label=Category:relation=category:sd_model=App\Models\Category:sd_value=id:sd_label=name" \
  --column="status:select:required:options=draft:Draft,published:Published,scheduled:Scheduled:renderer=badge:badges=draft|gray,published|green,scheduled|blue" \
  --column="published_at:datetime:label=Publish Date:renderer=datetime" \
  --column="views:number:readonly:label=Views:renderer=number:decimals=0" \
  --action="preview:link:https://blog.com/posts/%slug%:icon=bx bx-show:color=info" \
  --action="duplicate:livewire:duplicate(%id%):icon=bx bx-copy:color=secondary" \
  --filter="status:select:options=draft,published,scheduled" \
  --style="status:==:draft:background:#F3F4F6;color:#6B7280;" \
  --set="displayName=Blog Posts" \
  --set="itemsPerPage=20" \
  --set="exportEnabled=true"
```

---

## Recommended Workflow

### Workflow 1: Initial Development

```bash
# 1. Generate CRUD with ptah:forge
php artisan ptah:forge Post --fields="title:string,content:text,status:enum(draft|published)"
php artisan migrate

# 2. Configure via Visual Modal (first time)
# - Open the browser → /posts
# - Click the ⚙️ Config button
# - Configure columns, actions, filters visually
# - Save

# 3. Export configuration for versioning
php artisan ptah:config "App\Models\Post" --export=config/cruds/post.json

# 4. Commit to Git
git add config/cruds/post.json
git commit -m "feat: add Post CRUD config"
```

### Workflow 2: Replicate to Other Environments

```bash
# 1. Pull from Git
git pull origin main

# 2. Import configuration
php artisan ptah:config "App\Models\Post" --import=config/cruds/post.json

# 3. Verify it worked
php artisan ptah:config "App\Models\Post" --list
```

### Workflow 3: Configure Multiple Models

```bash
# 1. Create configuration script
cat > config-all-cruds.sh << 'EOF'
#!/bin/bash

# Products
php artisan ptah:config "App\Models\Product" \
  --import=config/cruds/product.json

# Categories
php artisan ptah:config "App\Models\Category" \
  --import=config/cruds/category.json

# Users
php artisan ptah:config "App\Models\User" \
  --import=config/cruds/user.json

echo "✓ All CRUDs configured!"
EOF

chmod +x config-all-cruds.sh

# 2. Run
./config-all-cruds.sh
```

### Workflow 4: CI/CD Pipeline

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Run migrations
        run: php artisan migrate --force

      - name: Import CRUD configs
        run: |
          php artisan ptah:config "App\Models\Product" --import=config/cruds/product.json
          php artisan ptah:config "App\Models\Category" --import=config/cruds/category.json
          php artisan ptah:config "App\Models\User" --import=config/cruds/user.json

      - name: Clear cache
        run: php artisan cache:clear
```

### Workflow 5: Backup and Restore

```bash
# Backup all configurations
mkdir -p backups/cruds/$(date +%Y-%m-%d)

php artisan ptah:config "App\Models\Product" --export=backups/cruds/$(date +%Y-%m-%d)/product.json
php artisan ptah:config "App\Models\Category" --export=backups/cruds/$(date +%Y-%m-%d)/category.json
php artisan ptah:config "App\Models\User" --export=backups/cruds/$(date +%Y-%m-%d)/user.json

# Restore from backup
BACKUP_DATE="2026-03-01"
php artisan ptah:config "App\Models\Product" --import=backups/cruds/${BACKUP_DATE}/product.json
php artisan ptah:config "App\Models\Category" --import=backups/cruds/${BACKUP_DATE}/category.json
php artisan ptah:config "App\Models\User" --import=backups/cruds/${BACKUP_DATE}/user.json
```

---

## Import/Export of Configurations

### Recommended Directory Structure

```
project/
├── config/
│   └── cruds/
│       ├── product.json
│       ├── category.json
│       ├── user.json
│       └── order.json
├── backups/
│   └── cruds/
│       ├── 2026-03-01/
│       │   ├── product.json
│       │   └── category.json
│       └── 2026-03-02/
│           └── product.json
```

### Full Export / Import (since v1.5.0)

Use the built-in bulk commands — no per-model script, no hardcoded model list, and
keys are canonicalised automatically:

```bash
# Snapshot every config to a versionable directory (one JSON per model/route)
php artisan ptah:config:export-all              # → database/ptah/crud-configs
git add database/ptah/crud-configs && git commit -m "chore: snapshot CRUD configs"

# Rebuild them on any environment (idempotent — doubles as a seeding step)
php artisan ptah:config:import-all

# Audit the set before/after (orphan keys, malformed, empty, route ambiguity)
php artisan ptah:config:doctor          # report
php artisan ptah:config:doctor --fix    # repair orphan (non-canonical) keys
```

Each file carries its own `model`/`route`, so the directory is git-diffable and the
import can't reintroduce an orphan key. See [Commands.md](Commands.md#ptahconfigexport-all--import-all).

> **Legacy per-model scripts** (`ptah:config "App\Models\X" --export=…`) still work,
> but a bash loop over a hardcoded model list is no longer needed.

---

## Troubleshooting

### Problem 1: Config does not appear in the CRUD

**Symptoms:**
- Modal saved but CRUD does not reflect changes
- Command executed but table does not change

**Cause:** Cache not invalidated

**Solution:**

```bash
# Clear cache manually
php artisan cache:forget "ptah.crud.Product"

# Or clear all cache
php artisan cache:clear

# Verify if config is in the database
php artisan ptah:config "App\Models\Product" --list
```

### Problem 2: Command not found

**Symptoms:**
```
Command "ptah:config" is not defined.
```

**Cause:** Command not registered in ServiceProvider

**Solution:**

```bash
# 1. Verify if command is registered
grep -r "ConfigCommand" vendor/jonytonet/ptah/src/

# 2. Clear command cache
php artisan optimize:clear

# 3. Re-run
php artisan ptah:config "App\Models\Product"
```

### Problem 3: Import fails with JSON error

**Symptoms:**
```
Invalid JSON file: Syntax error
```

**Cause:** Malformed JSON

**Solution:**

```bash
# Validate JSON
cat config/cruds/product.json | jq .

# If error, fix manually or:
php artisan ptah:config "App\Models\Product" --reset
php artisan ptah:config "App\Models\Product" --export=config/cruds/product.json
```

### Problem 4: Permissions not working

**Symptoms:**
- Gates configured but user can still access

**Cause:** Gates not defined in AuthServiceProvider

**Solution:**

```php
// app/Providers/AuthServiceProvider.php

use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::define('product.create', function ($user) {
        return $user->hasPermission('product.create');
    });

    Gate::define('product.update', function ($user) {
        return $user->hasPermission('product.update');
    });

    // etc...
}
```

### Problem 5: Syntax error in CLI command

**Symptoms:**
```
Parse error in column syntax
```

**Cause:** Incorrect syntax in the `--column` option

**Solution:**

```bash
# ❌ Wrong (missing quotes)
php artisan ptah:config App\Models\Product --column=name:text

# ✅ Correct
php artisan ptah:config "App\Models\Product" --column="name:text"

# ✅ Correct — inside quotes (double or single) the pipe needs no escape
--column="email:text:validation=email|maxLength:255"
--column='email:text:validation=email|maxLength:255'

# ❌ Wrong (no quotes: the shell reads | as a pipe)
--column=email:text:validation=email|maxLength:255
```

### Problem 6: Modal does not save changes

**Symptoms:**
- Clicking "Save" but changes do not persist

**Cause:** JavaScript error or validation failing

**Solution:**

```bash
# 1. Check browser console (F12)
# Look for JavaScript errors

# 2. Check Laravel logs
tail -f storage/logs/laravel.log

# 3. Check crud_configs table permissions
php artisan tinker
>>> DB::table('crud_configs')->where('model', 'Product')->first()
```

### Problem 7: Import does not seem to change the screen

**Symptoms:**
- Import executed but old config remains

**Cause:** `--import` always overwrites the saved config — `--force` is not needed and has no effect. The usual suspect is a different scope: the import went to the global config while the screen has a route-specific one, or the other way round.

**Solution:**

```bash
# Import into the right scope — the file's own route (an export-all envelope) or --route
php artisan ptah:config "App\Models\Product" --import=config/cruds/product.json --route=products

# Check what is saved for that scope
php artisan ptah:config "App\Models\Product" --list --route=products
```

---

## Related Links

- [BaseCrud Documentation](BaseCrud.md) — Complete documentation for the BaseCrud component
- [Commands Documentation](Commands.md) — All Artisan commands for Ptah
- [Migration Guide](MigrationGuide.md) — Migration guide between versions

---

**Last updated:** March 4, 2026  
**Version:** 2.2.0

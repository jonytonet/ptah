# Known Limitations & Developer Checklist

> This document describes **ptah's design boundaries** — things the package
> intentionally does not generate automatically, and that are the **developer's
> responsibility** after every `ptah:forge` run.
>
> These are not bugs. They are cases where ptah cannot make a correct decision
> without domain knowledge that only the developer has.

---

## Table of Contents

1. [Decimal Precision](#1-decimal-precision)
2. [Composite Indexes](#2-composite-indexes)
3. [Namespace Imports in Generated Models](#3-namespace-imports-in-generated-models)
4. [FK Fields With Non-Standard Table Names](#4-fk-fields-with-non-standard-table-names)
5. [`ptah:config` CLI — Column Types and Renderers](#5-ptahconfig-cli--column-types-and-renderers)
6. [Theming — Partial Coverage (1.15.0)](#6-theming--partial-coverage-1150)
7. [Post-Forge Checklist](#post-forge-checklist)

---

## 1. Decimal Precision

### What ptah generates

When `decimal` is passed without explicit precision (e.g. `price:decimal`), ptah
defaults to `decimal(10,2)` — suitable for standard monetary values.

```php
// Command:
ptah:forge Product --fields="price:decimal"

// Generated migration:
$table->decimal('price', 10, 2);
```

### How to specify a custom precision

Pass the full precision in parentheses — works in both terminal and `.ps1` scripts:

```bash
ptah:forge Product --fields="price:decimal(10,2)"
```

### Developer responsibility

After every `ptah:forge`, **verify and correct decimal precision** according to
the domain:

| Field type | Correct precision |
|---|---|
| Price / monetary value | `decimal(10,2)` — ptah's default |
| Historical totals / large amounts | `decimal(12,2)` |
| Tax rate / percentage (0–100%) | `decimal(5,2)` |
| PIS/COFINS rates (0–99.9999%) | `decimal(5,4)` |
| Animal weight (kg) | `decimal(5,2)` |
| Temperature (°C) | `decimal(4,1)` |
| GPS latitude | `decimal(10,8)` |
| GPS longitude | `decimal(11,8)` |
| Rating / score (0–5) | `decimal(3,2)` |

```php
// After forge, correct in the migration:
$table->decimal('commission_percent', 5, 2);  // not (10,2)
$table->decimal('latitude',          10, 8);  // not (10,2)
$table->decimal('temperature',        4, 1);  // not (10,2)
```

---

## 2. Composite Indexes

### What ptah generates

ptah generates **single-column indexes** for:
- `->unique()` — when `:unique` modifier is passed
- `->index()` — automatically added for `unsignedBigInteger`/`bigInteger` fields
  ending in `_id` (FK-like columns)

ptah **never generates composite indexes**. It cannot know which query patterns
the application will use.

### Developer responsibility

Add composite indexes manually in the migration after `ptah:forge`:

```php
// Single-column (ptah generates automatically for _id columns)
$table->index('status');

// Composite — always manual
$table->index(['company_id', 'status', 'created_at']);
$table->index(['user_id', 'type', 'processed_at']);

// Composite with long name → use explicit short name (MySQL: max 64 chars)
$table->index(
    ['company_id', 'operation_type', 'tax_regime', 'is_active', 'priority'],
    'fr_company_op_regime_active_priority'
);
```

> **MySQL index name limit:** 64 characters. Laravel auto-generates names as
> `{table}_{columns}_index` — composite indexes on long table names easily exceed
> this limit. **Always use an explicit short name (< 60 chars) for indexes with
> 3+ columns or long table names.**

---

## 3. Namespace Imports in Generated Models

### What ptah generates

When a field ends in `_id` with type `unsignedBigInteger`, `bigInteger`, or
`foreignId`, ptah generates a `belongsTo` relationship method in the Model.
However, because the related model may be in a different subfolder/module,
ptah **cannot determine the correct namespace automatically**.

Instead, it generates a `// TODO:` comment:

```php
// Generated model:
// TODO: use App\Models\ServiceCategory;
public function serviceCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(ServiceCategory::class, 'service_category_id');
}
```

### Developer responsibility

After `ptah:forge`, **replace every `// TODO:` comment** with the correct `use`
import matching the actual location of the related model:

```php
// Before (generated):
// TODO: use App\Models\ServiceCategory;

// After (you write):
use App\Models\Scheduling\ServiceCategory;
```

**Checklist per model:**

1. Open `app/Models/{Entity}.php`
2. Find all `// TODO: use ...` lines
3. Replace with the correct `use` statement using the real subfolder path
4. Confirm the related model class in the `belongsTo()` call matches

---

## 4. FK Fields With Non-Standard Table Names

### What ptah generates

ptah offers two FK types with different behaviour:

| Type | Migration output | When to use |
|---|---|---|
| `foreignId` | `->constrained('{inferred_table}')->cascadeOnDelete()` | Field name exactly matches `{singular_of_table}_id` |
| `unsignedBigInteger` | `->index()` only — **no FK constraint** | Any other case |

The `foreignId` type derives the target table from the field name:
`service_category_id` → `service_categories`. This works for standard naming
but **fails** for contextual fields like `applied_by_user_id`, `veterinarian_id`,
or `fiscal_cfop_sale_id`.

### Developer responsibility

**Use `unsignedBigInteger` for any FK field whose name does not directly map
to the target table.** Then add the constraint manually in the migration:

```php
// In --fields:
// applied_by_user_id:unsignedBigInteger:nullable
// veterinarian_id:unsignedBigInteger:nullable

// In the generated migration, add FK manually:
$table->foreign('applied_by_user_id')->references('id')->on('users')->nullOnDelete();
$table->foreign('veterinarian_id')->references('id')->on('employees')->nullOnDelete();
```

**For FK targets that do not yet exist** (table created in a future phase):

```php
// Leave as raw column — no foreign() call:
$table->unsignedBigInteger('order_id')->nullable();
// Add a separate migration when the target table is ready.
```

**Common patterns that require manual FK:**

| Field | Inferred (wrong) | Correct target |
|---|---|---|
| `applied_by_user_id` | `applied_by_users` | `users` |
| `veterinarian_id` | `veterinarians` | `employees` |
| `fiscal_cfop_sale_id` | `fiscal_cfop_sales` | `fiscal_cfops` |
| `referred_by_client_id` | `referred_by_clients` | `clients` (self-ref) |
| `parent_record_id` | `parent_records` | self-ref on current table |

---

## 5. `ptah:config` CLI — Column Types and Renderers

### `colsTipo` vs `renderer` — two separate concepts

`colsTipo` is the **form input type** for the create/edit modal.  
`renderer` (via `renderer=`) is **how the value is displayed in the listing table**.  
They are independent and both can be set on the same column.

```bash
# WRONG — badge and money are renderers, not input types:
php artisan ptah:config "App\Models\Product" --column="status:badge"
php artisan ptah:config "App\Models\Product" --column="price:money"

# CORRECT — use a valid colsTipo and set the renderer separately:
php artisan ptah:config "App\Models\Product" \
  --column="status:select:renderer=badge:badges=active|green|Ativo,inactive|gray|Inativo"
php artisan ptah:config "App\Models\Product" \
  --column="price:number:renderer=money"
```

**Valid `colsTipo` values:** `text`, `textarea`, `number`, `date`, `datetime`,
`select`, `searchdropdown`, `boolean`, `file`, `image`

**Valid `renderer` values (all 19 supported via CLI):** `text`, `badge`, `pill`,
`boolean`, `money`, `date`, `datetime`, `link`, `image`, `truncate`, `number`,
`filesize`, `duration`, `code`, `color`, `progress`, `rating`, `qrcode`

### Badge entries use `|` as internal separator

The `badges=` option uses `|` to separate `value|color|label` within each entry,
and `,` to separate multiple badge definitions. Do **not** use `:` inside badge entries
— it conflicts with the `field:type:modifier` definition syntax.

```bash
# Correct badge format (use | within each entry):
--column="status:select:renderer=badge:badges=active|green|Ativo,inactive|gray|Inativo,pending|yellow|Pendente"

# Also valid — omitting the label defaults to title-cased value:
--column="status:select:renderer=badge:badges=active|green,inactive|gray"
```

### `options=` values support `:` as separator

`ColumnParser` uses a smart tokenizer, so option values that contain `:` are
preserved correctly:

```bash
# Both separators work for options:
--column="status:select:options=active:Active,inactive:Inactive"
--column="status:select:options=active|Active,inactive|Inactive"
```

### `--filter` and `--style` via CLI

These flags now work correctly via CLI:

```bash
# Filter
php artisan ptah:config "App\Models\Product" --filter="is_active:boolean:label=Active"
php artisan ptah:config "App\Models\Product" --filter="status:select:options=active,inactive:operator=="

# Style
php artisan ptah:config "App\Models\Product" --style="status:==:inactive:background:#FEE2E2;color:#991B1B;"
```

### `--action` via CLI — valid action types

Valid `actionType` values are: **`link`**, **`livewire`**, **`javascript`**

```bash
# Row action — navigate to detail page (link type):
php artisan ptah:config "App\Models\Product" \
  --action="view:link:https://app.example.com/products/%id%:icon=bx-show:color=info"

# Livewire method call:
php artisan ptah:config "App\Models\Product" \
  --action="approve:livewire:approve(%id%):icon=bx-check:color=success:confirm=true"

# For whole-row clickable navigation, --set is simpler:
php artisan ptah:config "App\Models\Product" --set="configLinkLinha=/products/%id%"
```

> **Note:** type aliases `url`, `wire`, `route`, `modal` are **not** valid —
> use `link`, `livewire`, or `javascript`.

### CLI parity status

| Feature | CLI flag | Status |
|---|---|---|
| columns | `--column` | ✅ full support |
| filters | `--filter` | ✅ fixed (2026-03-20) |
| conditional styles | `--style` | ✅ fixed (Fase 2.5 Onda II)† |
| actions | `--action` | ✅ fixed (2026-03-20) |
| joins | `--join` | ✅ supported |
| general settings | `--set` | ✅ supported |
| option values with `:` | `options=k:v` | ✅ fixed (2026-03-20) |

† Before Fase 2.5 Onda II, `--style` was **inoperative**, not merely
incomplete: four different persisted shapes coexisted across the codebase
(`styles` vs `contitionStyles`, `field`/`condition`/`value`/`style` vs
`colsNomeFisico`/`colsOperator`/`colsValue`/`colsCss`), and only the shape
`HasCrudRenderers::getRowStyle()` actually reads (`contitionStyles` with
`field`/`condition`/`value`/`style`) ever took effect at render time. A style
rule configured via `--style` was silently saved to a key nothing ever read.
`StyleRule::normalize()` is now the single place that canonicalises every
shape, used by the CLI parser, the wizard, the schema validator, the doctor
command and the renderer itself.

---

## 6. Theming — Partial Coverage (1.15.0)

### What ptah covers

As of `1.15.0`, the per-user Appearance preset (see the "Per-user Appearance" section in
[`Configuration.md`](Configuration.md)) and the underlying `--ptah-*` neutral tokens
reach BaseCrud screens, the Forge components (`<x-forge-*>`) and most of the dashboard
chrome (sidebar, navbar, company switcher, page header, tabs).

### What ptah does not cover yet

Two areas are known to be **outside** the tokenised surface, measured directly against
this release's sources rather than estimated:

- **The dashboard layout's inline `<style>` block**
  (`resources/views/components/forge-dashboard-layout.blade.php`) still carries
  **84 hardcoded color literals across 79 CSS rules** (down from 153/127 when the
  tokenisation work started; `LayoutStyleBaselineTest`'s ceiling caps it at 89/79 and
  only ever shrinks). These rules retrofit dark mode onto a handful of remaining
  screens (module toolbars/tables, generic modal text, stat cards, badges/alerts) and
  do not react to the user's chosen tone.
- **Views still hardcode Tailwind text-color utilities** (`text-gray-*`,
  `text-slate-*`, `text-zinc-*`, `text-neutral-*`, `text-stone-*`, `text-white`,
  `text-black`, including their `dark:` variants) instead of the `--ptah-text-*`
  tokens — **1,057 occurrences across 56 Blade files**, measured with
  `grep -rEo "text-(gray|slate|zinc|neutral|stone|white|black)(-[0-9]+)?"` over
  `resources/views`. Two screens concentrate the largest share by far:
  - `resources/views/livewire/base-crud/crud-config.blade.php` (the visual CrudConfig
    editor) — 259 occurrences.
  - `resources/views/livewire/permission/permission-guide.blade.php` — 144
    occurrences.

  Together these two views alone account for ~38% of all remaining hardcoded
  text-color classes. Every other module screen (`page-list`, `menu-list`,
  `audit-list`, `role-list`, `company-list`, `user-permission-list`,
  `department-list`, `exports-panel`, `ai-model-config-list`, …) has a smaller but
  non-trivial share. A user who picks a non-default font-colour preset will see it
  applied almost everywhere on a BaseCrud screen, but largely **not** on `crud-config`
  or `permission-guide`.

- **The whole Appearance feature is inert under the Tailwind CDN fallback.** The
  layout only loads `resources/css/ptah-components.css` (via `app.css`'s `@import`,
  injected by `ptah:install`) when a Vite build exists
  (`public/build/manifest.json`); otherwise it falls back to the
  `cdn.tailwindcss.com` script, which never loads that stylesheet at all. Every
  `--ptah-*` token, every `data-ptah-*` preset block, and therefore the entire
  Appearance tab, has **no visual effect** on a host running without a Vite build —
  it silently does nothing rather than erroring.

### Developer responsibility

- Treat `crud-config` and `permission-guide` as **not yet theme-aware**: a custom dark
  or light-tone preset will look inconsistent there until they are migrated.
- If your host cannot guarantee a Vite build in every environment (e.g. a `sync`/no-op
  deployment step), do not advertise the Appearance tab to end users — it will appear
  to do nothing.
- Do not add new hardcoded `text-*`/`bg-*` color utilities to package views; use the
  `--ptah-*` tokens (see the token table earlier in `Configuration.md`) so new code
  does not add to the counts above.

---

Apply this checklist **immediately after each `ptah:forge`**, before running
`php artisan migrate`:

```
[ ] decimal precision      — verify each decimal field uses the correct (p,s)
[ ] FK constraints         — unsignedBigInteger/_id has index() only, no constrained()
                             → add foreign key constraint manually if required,
                               only AFTER the referenced table has been created
[ ] composite indexes      — required indexes for search/filter queries
[ ] long index names       — composite index names < 60 chars (MySQL limit: 64)
[ ] boolean defaults       — add ->default(true/false) where applicable
                             or use :default(true) in --fields
[ ] integer defaults       — add ->default(0) for counters/sort fields
                             or use :default(0) in --fields
[ ] status/enum defaults   — add ->default('pending') or similar where applicable
[ ] softDeletes in ledgers — if migration pre-existed and --no-soft-deletes was passed:
                             use --force to strip softDeletes() automatically, OR remove manually.
                             Also remove `use SoftDeletes` from the Model.
[ ] TODO namespaces        — replace all `// TODO: use ...` in Models with correct imports
[ ] FK non-standard names  — fields like applied_by_user_id, veterinarian_id, parent_record_id:
                             use unsignedBigInteger type + add ->foreign() manually
[ ] unique constraints     — add ->unique() for natural keys (email, slug, code, etc.)
```

---

## Summary: What ptah does vs. what the developer does

| Concern | ptah | Developer |
|---|---|---|
| `foreignId` → constrained FK (standard name) | ✅ automatic | — |
| `foreignId` → FK with non-standard name | ⚠ infers wrong table | use `unsignedBigInteger` + manual `->foreign()` |
| `unsignedBigInteger/_id` → FK constraint | ✅ adds `->index()` only | add `->foreign()` manually when ready |
| `decimal` default precision | ✅ `(10,2)` fallback | correct to domain precision |
| `decimal` custom precision via `--fields` | ✅ parses `decimal(10,2)` | specify `decimal(N,D)` in `--fields` |
| `boolean`/`integer` default via `--fields` | ✅ parses `:default(true)` | use `:default(val)` or add manually |
| Single-column `_id` index | ✅ auto-added | — |
| Composite indexes | ❌ not generated | always manual |
| Index name length (MySQL) | ❌ not enforced | keep names < 60 chars |
| `belongsTo` relationships | ✅ generated with TODO | fix `use` namespace |
| Acronym table names (POS, NF…) | ✅ fixed — `POSSale` → `pos_sales` | — |
| `--no-soft-deletes` on existing migration | ✅ fixed — use `--force` to auto-strip | remove `use SoftDeletes` from Model manually |
| `created_at` auto-added to CrudConfig | ✅ fixed — not added anymore | add via config modal when needed |
| `--filter` CLI flag | ✅ fixed (2026-03-20) | works directly via CLI |
| `--style` CLI flag | ✅ fixed (Fase 2.5 Onda II) — was inoperative before (wrote to a key/shape the runtime never read) | works directly via CLI |
| `--action` CLI flag (URL values) | ✅ fixed (2026-03-20) | use `link`/`livewire`/`javascript` as type |
| `options=k:v` with `:` in values | ✅ fixed (2026-03-20) | smart tokenizer preserves value |

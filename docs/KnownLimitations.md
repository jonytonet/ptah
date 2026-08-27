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
6. [Theming — Partial Coverage (1.26.0)](#6-theming--partial-coverage-1260)
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

**Valid `renderer` values (all 18 supported via CLI):** `text`, `badge`, `pill`,
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

### `--style` — a value containing `:` needs the long form

The style segment is colon-rich by nature, so the positional form
`field:condition:value:style` cannot also let the value hold a colon. Use the
`style=` marker to end the value explicitly:

```bash
# Silently wrong: value "12", style "30:background:#eee;" — never matches
php artisan ptah:config "App\Models\Slot" --style="start_at:==:12:30:background:#eee;"

# Correct: value "12:30"
php artisan ptah:config "App\Models\Slot" --style="start_at:==:12:30:style=background:#eee;"
```

Why not detect it automatically: nothing distinguishes `30:background:#eee;`
from a legitimate style string, so any auto-detection would be a heuristic that
mis-parses valid input. The marker is explicit, and the short form is kept
byte-for-byte compatible.

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

## 6. Theming — Partial Coverage (1.26.0)

### What ptah covers

The per-user Appearance preset (see "Per-user Appearance" in
[`Configuration.md`](Configuration.md)) and the `--ptah-*` neutral tokens reach
BaseCrud screens (table, cards, modal, pagination, sort bar), the Forge
components (button, table, stepper, switch, progress, avatar, badge, select,
chart-card, stat-card, page-header, pagination and the rest), the dashboard
chrome (sidebar, navbar, company switcher, tabs), the module/admin screens
(audit, menu, pages, departments, roles, users, company, exports, AI providers,
profile, AI chat widget) and — since 1.21 — the visual CrudConfig editor.

**Correction to the 1.15.0–1.25.0 text of this section:** it claimed
`crud-config` was "not yet theme-aware". That has been wrong since 1.21.
`crud-config.blade.php` and `partials/_config-form-preview.blade.php` do carry
439 fixed-palette utilities, but every one of them is *repainted* through a
token by a scoped rule in `resources/css/ptah-components.css`
(`.ptah-cfg .bg-white`, `.ptah-cfg-content .text-slate-500`, and ~90 more),
which works because that stylesheet is unlayered and therefore beats Tailwind
utilities. **A raw grep count of `bg-*`/`text-*` in the package views is not a
measure of theming debt** — it counts both the debt and the keys of the very
mechanism that fixes it. Use `HardcodedPaletteCeilingTest` (below) for the
ratchet and this section for the qualitative picture.

**Correction: `permission-guide.blade.php` is no longer excluded from either
axis of this section.** It used to carry 279 fixed-palette utilities with
zero `dark:` pairs (the manual screen behind `/ptah-permission-guide`) and was
scheduled for "its own wave" — this is that wave. It now uses the same
`ptah-c-*` component classes as every other module screen (`forge-page-header`,
`forge-tabs`, `forge-card`, `forge-alert`, plus 8 new classes:
`.ptah-c-code`/`.ptah-c-code_cap` for the code-examples tab,
`.ptah-c-step_num` for the setup steps, and
`.ptah-c-guide_node`/`_q`/`_ok`/`_no`/`.ptah-c-guide_conn` for the architecture
and decision-flow diagrams — no new `--ptah-*` token). Its own
hardcoded-palette count reached 0 and its fixture entry was removed. The same
wave also corrected the screen's TEXT: it used to teach a nonexistent trait
(`Ptah\Traits\HasPermission`), a nonexistent contract method
(`PermissionServiceContract::can()`), a nonexistent model
(`Ptah\Models\Page`), a nonexistent env var (`PTAH_AUDIT_MAX_RECORDS`), and
said nothing about qualified keys (v1.19) or `colsPermission` (v1.20). See
`tests/Unit/Support/PermissionGuideClaimsTest.php`.

### What ptah does not cover yet

Measured after the permission-guide truth+theme wave, normalised to exclude
Blade/HTML comments and `<style>` blocks: **818 occurrences across 45 Blade
files** (down from 1416 across 52 at the start of the 1.26.0 wave; the
permission-guide wave alone removed 279 sites — see below). Of these:

- **439 are not debt** — the CrudConfig editor repaint keys described above.
- **~112 faint-glyph utilities** (`text-gray-400`, `text-slate-400` and
  neighbours). No `--ptah-*` token has those hexes as its **light** value; the
  closest, `--ptah-icon-muted` (#64748b), is two tiers darker, and routing them
  there would change every icon glyph globally. This is a decision about a
  *missing token tier* (see the golden rule in
  [CustomScreens.md §1](CustomScreens.md)), not a mechanical conversion.
- **~70 `text-white` / `bg-white/NN` on accent or permanently-dark surfaces.**
  Correct as-is: `--ptah-text-on-accent` is #ffffff in every preset, so
  converting them would not change a pixel. These will never reach zero, and
  that is the intended end state.
- **`forge-alert` dark backgrounds** (4 sites). `AlertContrastTest` proves
  WCAG AA by compositing the literal #1e293b at 60% over #0f172a; tokenising
  the background invalidates that proof and requires re-deriving it across the
  3 dark tone presets. Deferred deliberately, not overlooked.
- **The dashboard layout inline `<style>` block** is down to **20 color
  literals across 28 rules** (from 36/39 at the start of the wave;
  `LayoutStyleBaselineTest` caps it and it only ever shrinks). Every rule left
  is either dark-only or repaints a utility class from a distance; a rule
  leaves the block only when the view it repaints is migrated.
- **The whole Appearance feature is inert under the Tailwind CDN fallback.**
  The layout only loads `resources/css/ptah-components.css` when a Vite build
  exists (`public/build/manifest.json`); otherwise it falls back to
  `cdn.tailwindcss.com`, which never loads that stylesheet. **1.26.0 makes this
  stricter, on purpose:** views that used to paint via `bg-white` now delegate
  to a `ptah-c-*` rule in that stylesheet, so on a host with no Vite build
  those surfaces render with **no background at all** instead of white. If you
  cannot guarantee a Vite build in every environment, do not upgrade past
  1.25.0 without testing.

### The ratchet

`tests/Unit/Support/HardcodedPaletteCeilingTest.php` plus
`tests/Fixtures/hardcoded-palette-ceiling.json` hold a **per-file ceiling**
that may only ever go down, and require the fixture to be tightened in the
same commit that reduces a count. It exists because between 1.15.0 and 1.25.0
the `text-*` count **grew** (999 → 1019) while this document already forbade
it: a doc rule alone does not hold a line. A new view shipping fixed-palette
utilities fails the fixture-coverage test instead of passing unnoticed.

The wave's contrast lesson is pinned too: `AppearancePresetContrastTest` now
reads the token **actually declared** in each migrated rule and proves the
resulting text/background pairs across all 6 tone presets — the gap that let
a 1.00:1 chart title and a 1.9:1 switch track ship with a green suite.

### Developer responsibility

- Do not add fixed-palette `text-*`/`bg-*` utilities to package views. Put the
  colour in a `ptah-c-*` class in `resources/css/ptah-components.css` using
  `var(--ptah-*)` — that is the package's single convention. The
  `style="... var(--ptah-*) ..."` recipe in
  [CustomScreens.md §6](CustomScreens.md) is guidance for **host** projects,
  which cannot edit the package stylesheet. `HardcodedPaletteCeilingTest`
  enforces this.
- Do not patch over package views with host CSS: your selectors break the day
  the package migrates the view.

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

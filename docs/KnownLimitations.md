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
5. [Post-Forge Checklist](#post-forge-checklist)

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

## Post-Forge Checklist

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

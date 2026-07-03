---
name: ptah-scaffold
description: Scaffold a complete Ptah CRUD from an entity/table description. Orchestrates ptah:forge (fields or --db from an existing table, optional --api), migrate, the mandatory post-scaffold fixes, then ptah:config for the BaseCrud listing. Use whenever the user wants to create/generate a new entity, model, or CRUD with Ptah ("gerar entidade", "novo CRUD", "scaffold X", "cria um CRUD de …"), or hands over a table/fields to turn into a full screen.
---

# Ptah — Scaffold a CRUD end-to-end

Turn "here is the entity/table" into a working web CRUD (Model + Service +
Repository + DTO + Livewire + migration + views) plus a configured BaseCrud
listing. This skill is the **runbook + guardrails**; for deep conventions
(SOLID layers, design tokens, performance rules, API, tests) defer to the
companion **`ptah-development`** skill (also shipped in the package at
`vendor/jonytonet/ptah/resources/boost/skills/ptah-development/SKILL.md`).

## Where the commands run

`ptah:*` are artisan commands of the **consuming Laravel app** (the app that
`composer require`d ptah), NOT the package itself. Run artisan from the app root
(the directory that contains the `artisan` file):
- working dir is the app root → `php artisan ptah:...`
- working dir is a parent workspace → `cd <app-dir> && php artisan ptah:...`
- **never** run `ptah:*` inside the ptah package / `vendor` directory (no `artisan` there).

If you edited the ptah package and the change isn't visible, run `composer dump-autoload` in the app.

## Step 0 — Collect the inputs (ask only what's missing)

- **Entity name** in PascalCase, optional subfolder → `Product` or `Catalog/Product`
  (subfolder = namespace `App\Models\Catalog\Product` and menu group).
- **Source of fields** — one of:
  - explicit fields: `name:string,price:decimal(10,2):nullable,category_id:unsignedBigInteger,is_active:boolean`
  - an **existing DB table** → use `--db` (ptah reads the columns for you).
- **Flags**: `--api` (web+API), `--no-soft-deletes`, `--no-menu`, `--table=` if it
  differs from the plural snake_case default.

If the user only gives a table that already exists, prefer `--db` — do not invent a `--fields` list.

## Step 1 — Generate

New entity from fields (SoftDeletes is ON by default — pass `--no-soft-deletes` to disable):
```bash
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal(10,2),category_id:unsignedBigInteger,is_active:boolean"
```

From an existing table (introspects the DB — matches "just give the table"):
```bash
php artisan ptah:forge Catalog/Product --db --table=products
```

Web + API together:
```bash
php artisan ptah:forge Catalog/Product --fields="..." --api
```

## Step 2 — Post-scaffold fixes (MANDATORY, in order)

1. **Fix FK `use` imports.** The generator leaves `// TODO: use App\Models\...`
   lines for FK relationships because it can't know the related model's subfolder.
   For every `// TODO: use` in a generated model: locate the real model
   (`find app/Models -name 'Category.php'`), replace with the correct `use`, and
   remove the TODO. Never commit `// TODO:` lines.
2. **Format:** `./vendor/bin/pint`
3. **Migrate:** `php artisan migrate`  ⚠️ plain `migrate` only — see Guardrails.
4. **Clear caches:** `php artisan view:clear && php artisan config:clear`
5. If the entity has a subfolder and a menu entry, sync the sidebar:
   `php artisan ptah:menu-sync` (see Guardrails re: `--fresh`).

## Step 3 — Configure the BaseCrud listing

`ptah:config` builds the listing config (columns/filters/actions/styles). It can
introspect the model/table to infer column types. Always pass `--non-interactive`
when driving it as an agent, and prefer `--dry-run` first to preview.

```bash
php artisan ptah:config "App\Models\Catalog\Product" --non-interactive \
  --column="name:text:label=Nome:sortable=true:searchable=true" \
  --column="price:money:label=Preço:sortable=true" \
  --column="is_active:badge:label=Status:badgeMap=1:success:Ativo,0:danger:Inativo" \
  --filter="is_active:boolean:eq:Ativos" \
  --set="itemsPerPage=15"

# preview / inspect:
php artisan ptah:config "App\Models\Catalog\Product" --dry-run --column="..."
php artisan ptah:config "App\Models\Catalog\Product" --list
```

Column/filter/action/style formats are documented in the package skill — reuse
them verbatim rather than guessing.

## Relationships

For an FK column (e.g. `category_id`), configure it as a relation/searchdropdown
so the form shows a picker and the listing shows the related label:
`--column="category_id:relation:label=Categoria:relation=category.name"`.
Confirm the relation name and display column against the actual model before
committing — the inference is heuristic.

## Guardrails (organization security rules apply)

- **Never run destructive DB commands** as part of scaffolding: no `migrate:fresh`,
  `migrate:reset`, `db:wipe`, `migrate:rollback` on shared/prod. Use plain
  `php artisan migrate`. If a migration must be undone, a human runs it.
- **`ptah:menu-sync --fresh` rewrites the `menus` table** — treat as destructive:
  run plain `ptah:menu-sync`, and only use `--fresh` after explicit confirmation.
- **Confirm the environment** before migrating. Assume production unless told
  otherwise; back up first for anything beyond local.
- **Pint before any commit.** Commit/push only when the user asks.
- Secrets always via `.env`, never hardcoded.

## Quick end-to-end example

> "Cria um CRUD de Fornecedor (Catalog/Supplier) com nome, cnpj, ativo."

```bash
cd petplace
php artisan ptah:forge Catalog/Supplier --fields="name:string,cnpj:string,is_active:boolean"
# fix FK TODO imports (none here) → pint → migrate → clears
./vendor/bin/pint && php artisan migrate && php artisan view:clear && php artisan config:clear
php artisan ptah:config "App\Models\Catalog\Supplier" --non-interactive \
  --column="name:text:label=Nome:sortable=true:searchable=true" \
  --column="cnpj:text:label=CNPJ:searchable=true" \
  --column="is_active:badge:label=Status:badgeMap=1:success:Ativo,0:danger:Inativo" \
  --filter="is_active:boolean:eq:Ativos"
```

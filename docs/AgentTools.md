# Agent Tools

Commands built for the moment an AI agent (or a person) would otherwise read
files to find something out. Each one replaces a sequence of reads, greps and
guesses with one call whose output is short enough to sit in the context
window, and says what is still left to do instead of leaving it to be noticed.

| Instead of… | Run | Typical output |
|---|---|---|
| reading `docs/BaseCrud.md` / `docs/Configuration.md` for one option | `ptah:docs <topic>` | 60–760 tokens |
| opening every model, migration and config at the start of a session | `ptah:map` | ~1–2k tokens for a module |
| `tail -300 storage/logs/laravel.log` | `ptah:last-error` | ~100 tokens |
| opening each screen in a browser after a change | `ptah:check` | one line per screen |
| five hand edits to add a column | `ptah:field <Entity> add <field>` | one line per file |
| a dozen commands in the right order for a module | `ptah:blueprint spec.json` | one line per step |
| reading the CHANGELOG to guess what an update needs here | `ptah:upgrade-check` | one line per action |

All of them are read-only unless the name says otherwise (`field`, `blueprint`,
`check --write`), and every one that reports takes `--json`.

---

## ptah:docs

The option reference for `ptah:config`, generated from the parser's own
constants — so it cannot drift from what the parser accepts.

```bash
php artisan ptah:docs            # list of topics
php artisan ptah:docs column     # types, renderers, modifiers, option → key map
php artisan ptah:docs filter
php artisan ptah:docs mask       # includes the masks THIS app registered
php artisan ptah:docs join --json
```

Topics: `column`, `filter`, `style`, `action`, `join`, `mask`.

---

## ptah:map

The project in one read: entities (table, typed fields, FK targets,
relations), configured screens (route, permission, column and filter counts),
the database menu, and the `TODO:` lines the generators left.

```bash
php artisan ptah:map
php artisan ptah:map --write     # also saves .ptah/map.md
php artisan ptah:map --json
php artisan ptah:map --models=app/Domain/Models
```

```
## Entities (2)
Catalog/Product  products  [soft,audit]
  name:varchar price:decimal? category_id:bigint→Category
  rel: category→Category (belongsTo)

## Screens (1)
Catalog/Product  route=(global)  perm=pageProduct  cols=6  filters=1
```

- A field ends in `?` when nullable; `→Model` marks a `belongsTo` foreign key.
- `id`, timestamps, `deleted_at` and the audit columns are summarized as
  `[soft,audit]`, not listed.
- A relation is listed only when its method **declares** a Relation return
  type. Calling an undeclared method to see what it returns is a side effect
  the map refuses to cause.
- `[NO TABLE]` marks a model whose migration has not run.

---

## ptah:last-error

The last `ERROR`-or-worse entry of the most recent log, compact: exception,
message, SQL (separated from the message), where it was thrown, and only the
application's frames — the framework pipeline is counted, not printed.

```bash
php artisan ptah:last-error
php artisan ptah:last-error --frames=10
php artisan ptah:last-error --file=storage/logs/laravel-2026-09-23.log --json
```

```
[2026-09-23 10:00:00] ERROR  Illuminate\Database\QueryException (42S02)
SQLSTATE[42S02]: Base table or view not found
SQL: select * from `produtcs` where `id` = 1
at vendor/laravel/framework/src/Illuminate/Database/Connection.php:825
app frames (2 shown, 41 vendor hidden):
  #12 app/Services/ProductService.php:40  App\Repositories\ProductRepository->find()
  #13 app/Livewire/ProductList.php:22  App\Services\ProductService->show()
ptahErrorId: 3f9c…
```

`ptahErrorId` is the id the 500 page shows the user — the one support is
given. Only the last 1 MB of the file is read, so a multi-GB log costs the
same as a small one.

---

## ptah:check

Every configured BaseCrud screen, smoke-tested: each one is **rendered**
through Livewire exactly as a page mounts it (listing query, eager loads,
Blade), and its config is compared with the model and the table.

```bash
php artisan ptah:check
php artisan ptah:check Product           # one screen (class or short name)
php artisan ptah:check --as=1 --guard=web # screens behind permissions
php artisan ptah:check --write           # + create/update/delete, rolled back
php artisan ptah:check --json
```

```
✔ Catalog/Category
⚠ Catalog/Product
    warn   col "discount" is not a column of products nor an accessor — the cell renders empty
    warn   form field "sku" is not fillable on Product — it is silently not saved
✖ Sales/Order
    error  render: RelationNotFoundException: Call to undefined relationship [custmer] … at app/Models/Sales/Order.php:1

3 screens: 1 ok, 1 with warnings, 1 failing
```

What it catches that a render alone does not — because none of it throws:

| Finding | Why it matters |
|---|---|
| column not in the table and not an accessor | the cell renders empty |
| form field outside `$fillable` | silently not saved |
| NOT NULL column without default missing from the form | every "New" fails in the database |
| `colsRelacao` / filter relation that is not a relationship | error on render or on filter |
| `colsOrderBy` / `colsSource` / filter field not in the table | error when used |

`--write` creates, updates and deletes one record per screen inside a
transaction that is **always rolled back**, with model events muted (no
observer sends the mail a real save would). It answers what the database
accepts — NOT NULL, FK, enum, length. It is refused in production without
`--force`. Exit code is 1 when any screen fails, so it fits CI.

---

## ptah:field

Adds one field to an entity `ptah:forge` generated, in every place it has to
go. Same field syntax as `--fields`; tokens after the first are modifiers.

```bash
php artisan ptah:field Catalog/Product add discount:decimal(5,2) nullable
php artisan ptah:field Product add status:enum(draft|published):default(draft)
php artisan ptah:field Product add sku:string(40):unique --dry-run
```

| Target | Edit |
|---|---|
| migration | `add_<field>_to_<table>_table` with `Schema::table` |
| model | `$fillable` (before the audit fields) and `$casts` |
| Store/Update requests (web and API) | the same rules forge would write |
| DTO | constructor property (required ones before optional) + `fromArray()` |
| crud_configs of the entity | a column, as forge builds it |

- Idempotent: what is already there is reported as `already there`.
- A file hand-edited past recognition is reported with ⚠ and left alone —
  the command never guesses where a line goes.
- `down()` drops the column; the generated file says so, because a rollback
  in production loses that column's data.
- Only `add`. Renaming or dropping a column destroys data; write that
  migration by hand.

---

## ptah:forge --factory

Generates a model factory and a demo seeder from the field types.

```bash
php artisan ptah:forge Catalog/Product --fields="name:string,price:decimal(10,2),category_id:unsignedBigInteger" --factory
```

- The factory goes where `HasFactory` looks for it
  (`Database\Factories\Catalog\ProductFactory`), so `Product::factory()`
  works with no override.
- Values fit the column: within the declared length, inside the enum, a
  `decimal(p,s)` that fits `p`. The name refines the type: `email`, `phone`,
  `cpf`, `cnpj`, `cep`, `url`, `code`/`sku`, `name`, `title`…
- A foreign key takes an existing row of the related model, or creates one
  through its factory. When the related model does not exist yet the line is
  `null` (nullable FK) or a `TODO` naming it.
- The seeder creates 10 records: `Database\Seeders\Catalog\ProductSeeder`.

---

## ptah:blueprint

A whole module from one JSON spec.

```json
{
  "module": "Catalog",
  "role": "admin",
  "grant": "all",
  "seed": true,
  "entities": {
    "Product": {
      "fields": ["name:string", "price:decimal(10,2)", "category_id:unsignedBigInteger"],
      "columns": ["price:money:label=Preço"],
      "filters": ["category_id:searchdropdown:label=Categoria"]
    },
    "Category": { "fields": "name:string(80),is_active:boolean:default(true)" }
  }
}
```

```bash
php artisan ptah:blueprint catalog.json --dry-run   # the exact commands, nothing run
php artisan ptah:blueprint catalog.json
php artisan ptah:blueprint catalog.json --no-migrate
```

The plan, in order:

1. `ptah:forge` for each entity, **parents first** — sorted by foreign keys,
   whatever the order in the spec. A cycle is an error naming its members.
2. `migrate`.
3. `ptah:config` per entity with its `columns`, `filters`, `actions`,
   `styles`, `set` and `permissions` (same syntax as the options).
4. `ptah:menu-sync` (skipped when no `MenuRegistry.php` exists).
5. `ptah:permission:sync --role=… --grant=…` when `role` is given.
6. The generated seeders, parents first, when `seed` is true.

Per entity, optional: `api` (false), `factory` (true), `menu` (true),
`soft_deletes` (true), `depends` (extra ordering).

Each step's own output is captured: the run prints one line per step and,
for a failing step, only its last lines. The first failure stops the run and
lists what did not run. Re-running is safe — `ptah:forge` skips files that
exist. Refused in production without `--force`.

---

## ptah:upgrade-check

After `composer update jonytonet/ptah`: what **this** project has to do.
The CHANGELOG says what changed in the package; this reads the host.

```bash
php artisan ptah:upgrade-check
php artisan ptah:upgrade-check --strict    # exit 1 when there is something to act on
php artisan ptah:upgrade-check --json
```

| Check | Finds |
|---|---|
| config | nested keys missing from a published `config/ptah.php` (`mergeConfigFrom` is shallow, so they are **not** defaulted); keys the package no longer reads |
| views | published views in `resources/views/vendor/ptah` that differ (they shadow fixes), are identical (they only freeze updates), or no longer exist |
| stubs | published `stubs/ptah/*.stub` that differ — forge keeps generating the old code |
| preferences | `user_preferences.user_id` FK pointing somewhere other than the configured identity → `ptah:preferences:realign` |
| migrations | migrations not run yet |
| structure_editor | menu/company screens closed because `PTAH_STRUCTURE_EDITOR` is off |

---

## Recommended session flow for an agent

```bash
php artisan ptah:upgrade-check     # after an update
php artisan ptah:map               # what exists
php artisan ptah:docs column       # before writing a --column
# … ptah:blueprint / ptah:forge / ptah:field / ptah:config …
php artisan ptah:check             # does every screen still work?
php artisan ptah:last-error        # when something broke
```

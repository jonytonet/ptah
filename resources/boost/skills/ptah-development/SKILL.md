---
name: ptah-development
description: Build and work with jonytonet/ptah — covering SOLID layered architecture, design tokens, scaffolding, BaseCrud configuration, Livewire conventions, optional modules, and tests. Use this skill whenever creating or modifying any entity, component or module in a Ptah-based project.
---

# Ptah Development Skill

## When to use this skill

Use this skill when:
- Creating or modifying entities (Model, Service, Repository, DTO, Livewire)
- Configuring BaseCrud columns, filters, modal or row styles
- Activating or building optional modules (auth, menu, company, permissions)
- Writing Livewire components, view files or CSS
- Writing tests (unit or feature)
- Deciding where business logic, queries or validation belong

---

## Decision map — configure before you code

The most expensive mistake an agent makes here is **rebuilding something the
package already ships**. Read this table before writing any file; each row
names the ready-made path and what NOT to do.

| You need | Do this | Do NOT |
|---|---|---|
| A new entity + full CRUD screen | `php artisan ptah:forge Name --fields="..."` then configure via `ptah:config` or the visual editor (gear icon on the screen) | Hand-write a Livewire listing/form component |
| Columns, filters, badges, row styles, actions, modal fields on an existing CRUD | `ptah:config` flags or the visual editor — it is all JSON in `crud_configs` | Edit Blade views or create custom components |
| A listing of an existing table | A `crud_configs` row is enough — BaseCrud renders from config; no view, no component, no controller beyond the thin generated one | Build a table view by hand |
| Data access / business rules outside a CRUD screen | Extend `BaseRepository` / `BaseService` via contracts (see the `ptah-data-layer` skill — `getData(Request)` already does search/filter/sort/paginate, and the bases ship the full CRUD method set: find/create/update/destroy/restore) | Write Eloquent queries in Livewire/controllers |
| A REST API for an entity | `php artisan ptah:module api` once, then `ptah:forge Name --fields="..." --api` — controller with full Swagger `@OA\*` annotations, Create/Update API requests, versioned `Route::prefix('v1')` routes and the `BaseResponse` envelope, all generated and working | Hand-write API controllers, resources or response envelopes |
| Notify users when records change | CrudConfig editor → Notifications tab + `SendsCrudNotifications` trait on the model | Write observers/listeners that insert notifications |
| Permissions per screen / column | Permissions module: page objects + grants; column tag `colsPermission` | if() checks scattered in views |
| A screen that is genuinely not a CRUD | [CustomScreens.md](../../../../docs/CustomScreens.md): `<x-forge-*>` components + `--ptah-*` tokens only | Raw HTML with Tailwind palette colors |

### What BaseCrud already does (do not rebuild any of this)

Global search + per-column search · custom filters with operators · date-range
and quick-date filters · saved filters · sorting (table headers + card-view
sort bar) · pagination with per-page control · export XLSX/CSV/PDF + print ·
bulk actions (delete/restore/export + custom, permission-gated) · row styles
by condition · badges with value→color maps · relation columns via JOIN ·
SearchDropdown fields (model or service mode, masks, filters) · master-detail ·
group break with subtotals · totalizers · soft deletes + trash view + restore ·
audit fields (created/updated/deleted_by) · column-level permissions · ACL
integration · per-CRUD event notifications · responsive card layout (auto on
mobile) with its own sorting · per-user preferences (columns, widths, order,
view mode, density) · keyboard shortcuts · CSV/JSON config import/export ·
lifecycle hooks for custom logic around save/delete.

If the request maps to anything above, the answer is **configuration**, not
code. When in doubt: `php artisan ptah:config "App\Models\X" --list` shows
what a screen already has.

### Where to read more (token budget guide)

Read the SMALLEST document that answers the question — in this order:

| Question | Read |
|---|---|
| Any config flag / column type / option syntax | This skill's "Configuring BaseCrud" sections below |
| Full BaseCrud runtime behaviour | `docs/BaseCrud.md` |
| Every `ptah:*` command | `docs/Commands.md` |
| Repository/Service/DTO contracts | `ptah-data-layer` skill, then `docs/BaseLayer.md` |
| Custom screens, tokens, theming | `docs/CustomScreens.md` |
| Permissions/ACL | `docs/Permissions.md` |
| Notifications | `docs/Notifications.md` |
| What is known-broken or deliberate | `docs/KnownLimitations.md` |

---

## SOLID Architecture — Layer Rules (NEVER violate)

### Layer map

```
HTTP Request
     │
     ▼
FormRequest          → validates HTTP input only
     │
     ▼
Controller / Livewire → calls Service via Contract; NO queries, NO business logic
     │
     ▼
ServiceContract      → interface in app/Contracts/Services/
     │
     ▼
Service              → ALL business logic; calls RepositoryContract; throws domain exceptions
     │
     ▼
RepositoryContract   → interface in app/Contracts/Repositories/
     │
     ▼
Repository           → ALL database access; Eloquent queries, filters, pagination
     │
     ▼
Model                → schema, casts, scopes, relationships, boot hooks; NO logic
     │
     ▼
DTO                  → immutable value object; fromArray(); passed between layers
```

### Single Responsibility — what each layer owns

| Layer | Owns | Must NOT contain |
|---|---|---|
| Model | `$fillable`, `$casts`, scopes, relationships, `boot()` hooks | Business rules, DB queries in methods |
| DTO | `readonly` properties, `fromArray()`, `toArray()` | Persistence, validation |
| Repository | Eloquent queries, raw SQL, pagination, eager loads | Business rules, HTTP/session awareness |
| Service | Business rules, orchestration, events, domain exceptions | Eloquent queries, HTTP redirects |
| FormRequest | `rules()`, `authorize()`, `messages()` | Business logic |
| Livewire/Controller | Call Service, return view/response | Queries, business rules |

### Dependency Inversion — always inject Contracts

```php
// ✅ Correct
public function __construct(
    private readonly ProductServiceContract $products,
    private readonly CategoryRepositoryContract $categories,
) {}

// ❌ Wrong — never inject concrete classes
public function __construct(private readonly ProductService $products) {}
```

### Concrete example of correct layer separation

```php
// ❌ Anti-pattern: business logic leaking into Livewire
public function save(): void
{
    $exists = Product::where('sku', $this->sku)->exists(); // query in Livewire ❌
    if ($exists) { $this->addError('sku', 'Duplicado'); return; }
    Product::create([...]); // direct create in Livewire ❌
}

// ✅ Correct: Livewire → Service (via Contract) → Repository
// Livewire:
public function save(): void
{
    $this->validate();
    try {
        $this->products->create(ProductDTO::fromArray($this->only(['name','sku','price'])));
        $this->showModal = false;
    } catch (DuplicateSkuException $e) {
        $this->addError('sku', $e->getMessage());
    }
}

// Service:
public function create(ProductDTO $dto): Product
{
    if ($this->repo->existsBySku($dto->sku)) {
        throw new DuplicateSkuException("SKU {$dto->sku} already registered.");
    }
    return $this->repo->create($dto->toArray());
}

// Repository:
public function existsBySku(string $sku): bool
{
    return Product::where('sku', $sku)->exists();
}
```

---

## Colors & Theming — the rule that keeps screens on-theme

Ptah has 6 per-user appearance axes (light tone, dark tone, accent, text
weight, density, font size). The user can switch the light tone to **papel**
(warm paper) or **nevoa** at any time, and every ptah screen follows. A screen
follows the theme **only** if every color in it resolves through a CSS custom
property the presets rewrite. A fixed Tailwind palette class does not.

**The failure this rule prevents, seen in production:** a screen built with
`bg-white` / `bg-light` stays literally white when the user switches the tone
to *papel* — it is the one white rectangle on a warm-paper page.

### The 3 layers, in order of preference

1. **`<x-forge-*>` component props** — zero theming work, always safe:

```blade
<x-forge-button color="primary">Salvar</x-forge-button>
<x-forge-button color="danger" flat>Excluir</x-forge-button>
<x-forge-alert type="success">Salvo!</x-forge-alert>
```

2. **Semantic Tailwind classes that are theme-safe** — safe because their
   `--color-*` variable is rewritten by a preset (accent axis) or is a
   deliberate constant (status colors mean the same thing in every theme):

   `bg-primary` `text-primary` · `bg-success` `text-success` ·
   `bg-danger` `text-danger` · `bg-warn` `text-warn`

3. **`var(--ptah-*)` tokens for any custom surface/text/border** — the full
   contract (~30 tokens with usage notes) is
   [CustomScreens.md §1](../../../../docs/CustomScreens.md); the ones you will
   need constantly:

| Need | Token |
|---|---|
| Page-flush background (toolbar, filter panel) | `--ptah-canvas` |
| Card / button / modal surface | `--ptah-surface` |
| Elevated surface (dropdown menu) | `--ptah-surface-raised` |
| Recessed panel (modal body) | `--ptah-surface-sunken` |
| Hover tint | `--ptah-surface-hover` |
| Default / secondary / faint text | `--ptah-text` / `--ptah-text-secondary` / `--ptah-text-faint` |
| Border / stronger border | `--ptah-line` / `--ptah-line-strong` |
| Control height & font (density axis) | `--ptah-control-h` / `--ptah-control-fs` |

### Forbidden in any view or CSS you write

- `bg-white`, `bg-light`, `bg-dark`, `bg-slate-*`, `bg-gray-*`, `text-slate-*`
  and every other fixed-palette class used for **surfaces or text** — they
  ignore the tone presets.
- `dark:` Tailwind variants — dark mode here is `.ptah-dark` redefining the
  SAME tokens; markup never branches on the mode.
- Hex/rgb literals in views or in per-view `<style>` blocks (which are
  themselves forbidden — see CSS Architecture Rules).

```blade
{{-- ❌ stays white under the papel tone --}}
<div class="bg-white dark:bg-slate-800 border rounded-lg p-4">

{{-- ✅ follows every axis with zero extra work --}}
<div class="rounded-lg p-4 border"
     style="background: var(--ptah-surface); border-color: var(--ptah-line);">
```

To find offenders in an existing project:

```bash
grep -rnE 'bg-(white|light|slate|gray)|dark:|#[0-9a-fA-F]{3,6}' resources/views --include='*.blade.php'
```

---

## Scaffolding New Entities

```bash
# Single entity (SoftDeletes is ON by default — pass --no-soft-deletes to disable)
php artisan ptah:forge Product \
  --fields="name:string,sku:string,price:decimal,stock:integer,category_id:unsignedBigInteger,is_active:boolean"

# Sub-folder (large projects)
php artisan ptah:forge Inventory/ProductStock \
  --fields="product_id:unsignedBigInteger,location:string,qty:integer"
# model key = 'Inventory/ProductStock'
# namespace = App\Models\Inventory\ProductStock

# With API (web + API in one command)
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal,category_id:unsignedBigInteger" \
  --api
```

### Automatic menu

Every entity generated with a subfolder **automatically adds** a sidebar menu link:

```bash
# During scaffolding
php artisan ptah:forge Health/VaccinationType --fields="..."
# → Adds an entry to database/seeders/MenuRegistry.php

# After generating all entities, sync the menu:
php artisan ptah:menu-sync --fresh
# → Populates the 'menus' table with every link
```

**Automatic mappings:**
- Module `Health` → group "Saúde" (icon `bx bx-plus-medical`)
- Entity `VaccinationType` → link "Tipos de Vacina" (icon `bx bx-shield-plus`)

**Disabling the menu for one entity:**
```bash
php artisan ptah:forge Health/Test --fields="..." --no-menu
```

---

## Post-scaffold Checklist (MANDATORY after every ptah:forge)

After running `ptah:forge` and `php artisan migrate`, **always** perform these steps:

### 1. Fix FK `use` imports in every generated Model

The generator intentionally leaves `// TODO:` comments for FK relationships
because it cannot know which sub-folder the related model lives in:

```php
// Generated (NEEDS to be fixed):
// TODO: use App\Models\Category; // verifique o namespace real — ajuste se Category estiver em sub-pasta

// ✅ If Category is in App\Models\Catalog\ :
use App\Models\Catalog\Category;

// ✅ If Category is in the root App\Models\ :
use App\Models\Category;
```

**Rule:** For every `// TODO: use` line in a generated model:
- Find where the related model file actually lives (`find app/Models -name 'Category.php'`)
- Replace the TODO comment with the correct `use` statement
- Never leave `// TODO:` lines in committed code

### 2. Run Pint to format all generated files

```bash
./vendor/bin/pint
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Clear views and config cache

```bash
php artisan view:clear
php artisan config:clear
```

---

## HasAuditFields — Automatic Audit Columns

Every model generated by `ptah:forge` automatically includes the `Ptah\Traits\HasAuditFields` trait and audit columns in its migration. All internal package models (Company, Department, Role, Menu, CrudConfig, PtahPage, PageObject, UserRole, RolePermission) also use this trait.

### What it does

| Column | Type | Filled when |
|---|---|---|
| `created_by` | `unsignedBigInteger` nullable | Eloquent `creating` event |
| `updated_by` | `unsignedBigInteger` nullable | Eloquent `creating` and `updating` events |
| `deleted_by` | `unsignedBigInteger` nullable | Eloquent `deleted` event (only when SoftDeletes is enabled — i.e. not `--no-soft-deletes`) |

### Critical rules for agents

```
✅ NEVER manually set created_by / updated_by in service, controller or Livewire
   The HasAuditFields trait fills them automatically via Eloquent events.
   BaseCrud::save() also injects them explicitly as a belt-and-suspenders safeguard.

✅ NEVER use ->whereIn()->delete() for bulk ops on models with HasAuditFields
   Use ->each(fn($r) => $r->delete()) so Eloquent fires the `deleted` event per record
   and deleted_by is stamped correctly on each row.

✅ deleted_by uses the `deleted` event (after soft-delete commits), NOT `deleting`
   This prevents stamping deleted_by on a record whose soft-delete later fails.

✅ Guard is === null (not empty()) so user ID 0 is not treated as "unset".
```

### Available relationships

```php
$record->createdBy  // BelongsTo → User (resolved via auth.providers.users.model)
$record->updatedBy  // BelongsTo → User
$record->deletedBy  // BelongsTo → User
```

### Required model setup

```php
use Ptah\Traits\HasAuditFields;

class Product extends Model
{
    use HasAuditFields;

    protected $fillable = [
        // ... your fields ...
        'created_by', 'updated_by', // always
        'deleted_by',               // only if model uses SoftDeletes
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer', // only if SoftDeletes
    ];
}
```

`ptah:forge` generates all of the above automatically — no manual setup needed for scaffolded entities.

---

## CSS Architecture Rules

1. **Never** add `<style>` blocks inside view files
2. New CSS lives in `resources/css/ptah-components.css`, built on `--ptah-*` design
   tokens (`--ptah-primary`, `--ptah-surface`, `--ptah-text-*`, …) — never a bare hex
   literal. `forge-dashboard-layout.blade.php` still carries a legacy inline `<style>`
   block for a shrinking set of chrome rules; it is being dismantled, not extended —
   `LayoutStyleBaselineTest` fails the build if it gains a single color literal or rule
   (see [KnownLimitations.md §6](../../../../docs/KnownLimitations.md#6-theming--partial-coverage-1260))
3. Dark mode always via a `.ptah-dark` ancestor
4. The 6 per-user appearance axes (light/dark tone, accent, text weight, density, font
   size) are selected via `data-ptah-*` attributes on `<html>`, resolved by
   `Ptah\Support\AppearancePresets` — see [CustomScreens.md](../../../../docs/CustomScreens.md)

```css
/* ✅ Inside resources/css/ptah-components.css — .ptah-dark redefines the SAME
   token names in its own block, components never branch on .ptah-dark themselves */
:root      { --ptah-surface: #ffffff; }
.ptah-dark { --ptah-surface: #1e293b; }
.my-component { background: var(--ptah-surface); }
```

---

## Configuring BaseCrud (CLI)

### Via Command Line

```bash
# Configure complete CRUD in one command
php artisan ptah:config "App\Models\Product" \
  --column="id:number:label=ID:sortable:min_width=80px" \
  --column="name:text:label=Nome:required:sortable" \
  --column="price:number:label=Price:renderer=money:sortable" \
  --column="is_active:select:label=Status:renderer=badge:badges=1|success|Ativo,0|danger|Inativo" \
  --style="is_active:==:0:background:#FEF2F2;color:#B91C1C;" \
  --style="stock:<:5:background:#FEFCE8;color:#A16207;" \
  --filter="is_active:select:label=Ativos:operator==:options=1:Ativo,0:Inativo" \
  --action="duplicate:livewire:duplicate(%id%):icon=bx-copy:color=info:confirm=true" \
  --set="itemsPerPage=15" \
  --set="cacheEnabled=true"

# List current configuration
php artisan ptah:config "App\Models\Product" --list

# Export to JSON
php artisan ptah:config "App\Models\Product" --export=product-config.json

# Import from JSON
php artisan ptah:config "App\Models\Product" --import=product-config.json

# Reset to defaults
php artisan ptah:config "App\Models\Product" --reset

# Dry-run (show changes without saving)
php artisan ptah:config "App\Models\Product" --column="..." --dry-run
```

### Option Formats

Full option reference (every key each parser accepts): [Commands.md](../../../../docs/Commands.md#ptahconfig). What follows is what an agent needs day to day — **verify against `src/Commands/Config/Parsers/*.php` before trusting any doc, including this one, if behaviour looks off.**

#### --column

Format: `field:type:modifier:modifier:option=value:option=value` (`ColumnParser`)

**`colsTipo` values** (the FORM INPUT type — independent from the display renderer, see [KnownLimitations.md §5](../../../../docs/KnownLimitations.md#5-ptahconfig-cli--column-types-and-renderers)):
`text`, `textarea`, `number`, `date`, `datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image`

**Bare modifiers** (no `=value` — presence alone flips the flag, never `sortable=true`):
`required`, `nullable`, `readonly` (→ `colsGravar=false`), `hidden` (→ `colsVisibleList=false`), `sortable` (→ `colsOrderBy=<field>`), `filterable`, `not_filterable`

**Common `key=value` options:**
- `label=` — display label
- `renderer=` — how the value is DISPLAYED in the list table (`badge`, `pill`, `boolean`, `money`, `date`, `datetime`, `link`, `image`, `truncate`, `number`, `filesize`, `duration`, `code`, `color`, `progress`, `rating`, `qrcode`) — a separate concept from `colsTipo`
- `min_width=` — e.g. `min_width=120px` (there is no `width=`)
- `badges=value|color|label,value2|color2|label2` — **pipe**-separated inside each entry (`:` is the field:type:modifier separator and would collide)
- `options=value:Label,value2:Label2` — for `select`/`searchdropdown`
- `mask=`, `mask_transform=` — input mask
- `sd_model=`, `sd_value=`, `sd_label=` — searchdropdown source
- `link_template=`, `link_label=`, `link_new_tab=` — for `renderer=link`
- `upload_path=`, `upload_max_size=`, `upload_allowed_types=` — for `colsTipo=file|image`
- `permission=` — gates the column via `colsPermission` (see [Permissions.md](../../../../docs/Permissions.md))

**Example:** `is_active:select:label=Status:renderer=badge:badges=1|success|Ativo,0|danger|Inativo`

#### --style

Format: `field:condition:value:style` — `style` is an **inline CSS declaration
list** (it is rendered as `style="{{ ... }}"` on the row), not Tailwind
classes.

**Conditions:** `==`, `!=`, `>`, `<`, `>=`, `<=` (or the aliases `eq`, `ne`,
`lt`, `gt`, `lte`, `gte`, `=`)

**Example:** `is_active:==:0:background:#FEF2F2;color:#B91C1C;`

#### --filter

Format: `field:type:option=value:option=value` (`FilterParser`) — **no positional
operator**; everything after `field:type` is `key=value`.

**Types:** `text`, `number`, `date`, `select`, `searchdropdown`

**Common options:** `label=`, `operator=` (`=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE`), `options=`

**Example:** `status:select:label=Status:operator==:options=active:Active,inactive:Inactive`

#### --action

Format: `name:type:value:option=value:option=value` (`ActionParser`)

**Types (the only ones the table renderer executes — `_table.blade.php`):** `link`, `livewire`, `javascript`

**Common options:** `icon=`, `color=` (`primary`, `success`, `danger`, `warning`, `info`, `secondary`), `confirm=true`, `confirm_message=`, `permission=`

**Example:** `approve:livewire:approve(%id%):icon=bx-check:color=success:confirm=true`

#### --set

Format: `key=value`

**Settings:** `itemsPerPage=15`, `cacheEnabled=true`, `cacheTime=30`, `paginationEnabled=true`, `exportEnabled=true`

### UI helpers agents reach for often

- **Toast:** `$this->dispatch('ptah-toast', title: 'Salvo com sucesso.', color: 'success');` — `color` is `success`/`danger`/`warn`. An undo toast (`ptah-toast-undo`) is dispatched by the delete flow and wired to `restoreRecord()` — see [BaseCrud.md](../../../../docs/BaseCrud.md).
- **Empty/loading states:** use `<x-forge-empty>` / `<x-forge-skeleton>` instead of ad-hoc markup.
- **Appearance tokens/eixos** (density, tone, font, radius, motion, contrast): see [CustomScreens.md](../../../../docs/CustomScreens.md) and [Configuration.md](../../../../docs/Configuration.md).

---

## Configuring BaseCrud (JSON)

The persisted config shape (`crud_configs.config`) uses the SAME property names the
runtime reads directly — `colsNomeFisico`, `colsTipo`, etc. — not a `field`/`label`/`type`
shorthand. Full property reference: [Configuration.md § Column Configuration](../../../../docs/Configuration.md#column-configuration).

```json
{
  "cols": [
    { "colsNomeFisico": "name", "colsNomeLogico": "Nome", "colsTipo": "text",
      "colsGravar": true, "colsRequired": true, "colsIsFilterable": true,
      "colsVisibleList": true, "colsEditableForm": true },
    { "colsNomeFisico": "price", "colsNomeLogico": "Price", "colsTipo": "number",
      "colsRenderer": "money", "colsRendererCurrency": "BRL", "colsRendererDecimals": 2 },
    { "colsNomeFisico": "is_active", "colsNomeLogico": "Status", "colsTipo": "select",
      "colsRenderer": "badge",
      "colsRendererBadges": [
        { "value": "1", "color": "success", "label": "Ativo" },
        { "value": "0", "color": "danger",  "label": "Inativo" }
      ] }
  ],
  "actions": [
    { "colsNomeLogico": "Aprovar", "colsTipo": "action", "actionType": "livewire",
      "actionValue": "approve(%id%)", "actionIcon": "bx-check", "actionColor": "success",
      "actionConfirm": true }
  ],
  "filters": [
    { "field": "is_active", "label": "Status", "colsFilterType": "select",
      "defaultOperator": "=", "options": "1:Ativo,0:Inativo" }
  ],
  "contitionStyles": [
    { "field": "stock", "condition": "<", "value": "5",
      "style": "background:#FEFCE8;color:#A16207;" }
  ],
  "joins": [
    { "type": "left", "table": "categories",
      "first": "products.category_id", "second": "categories.id" }
  ],
  "permissions": { "permissionIdentifier": "products.index" },
  "itemsPerPage": 25,
  "cacheEnabled": true
}
```

> Note the misspelling `contitionStyles` (not `conditionStyles`) — that is the key
> `HasCrudRenderers::getRowStyle()` actually reads; `StyleRule::normalize()` is the
> single place that canonicalises every legacy shape into this one. Never invent a
> `rowStyles`/`badgeMap`/`modal.fields` shape — the runtime does not read it.

---

## Livewire Input Rules

```blade
{{-- Text, email, phone, tax → .blur (no re-renders while typing) --}}
<x-forge-input wire:model.blur="name"  name="name"  label="Nome" />
<x-forge-input wire:model.blur="email" name="email" label="E-mail" />

{{-- Switch / checkbox / select → .live (immediate UI feedback needed) --}}
<x-forge-switch wire:model.live="is_active" name="is_active" label="Ativo" />
```

Unique validation with self-exclusion:
```php
use Illuminate\Validation\Rule;

protected function rules(): array
{
    return [
        'sku' => [
            'required', 'string', 'max:50',
            Rule::unique('products', 'sku')->ignore($this->editingId),
        ],
    ];
}
```

---

## Optional Modules

```bash
php artisan ptah:module auth         # Login, 2FA TOTP+email, sessions, profile
php artisan ptah:module menu         # Dynamic sidebar (driver: config or database)
php artisan ptah:module company      # Multi-company + departments
php artisan ptah:module permissions  # RBAC: roles, page objects, CRUD + audit
php artisan ptah:module --list       # Status of all modules
php artisan ptah:install --demo      # Seed demo companies/roles/menu
```

---

## Writing Tests

```php
use Livewire\Livewire;
// `Ptah\Tests\TestCase` (Testbench + RefreshDatabase + SQLite :memory:) is the
// PACKAGE's own dev-only base class, registered under ptah's `autoload-dev` —
// it is NOT available to a consuming app. In a host app, extend the host's own
// `Tests\TestCase` (Laravel's default, `tests/TestCase.php`) instead.
use Tests\TestCase;
use Tests\Factories\ProductFactory;  // Custom factory — NO Eloquent Factory

class ProductListTest extends TestCase
{
    public function test_can_create(): void
    {
        Livewire::test(ProductList::class)
            ->call('create')
            ->set('name', 'Widget')->set('sku', 'WGT-001')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('products', ['sku' => 'WGT-001']);
    }

    public function test_sku_unique(): void
    {
        ProductFactory::new()->create(['sku' => 'DUP']);

        Livewire::test(ProductList::class)
            ->call('create')->set('sku', 'DUP')->call('save')
            ->assertHasErrors(['sku']);
    }

    public function test_can_edit_own_sku(): void
    {
        $p = ProductFactory::new()->create(['sku' => 'MINE']);

        Livewire::test(ProductList::class)
            ->call('edit', $p->id)->set('name', 'New Name')->call('save')
            ->assertHasNoErrors();
    }

    public function test_soft_delete(): void
    {
        $p = ProductFactory::new()->create();

        Livewire::test(ProductList::class)
            ->call('confirmDelete', $p->id)
            ->call('delete');

        $this->assertSoftDeleted('products', ['id' => $p->id]);
    }
}
```

Factory pattern:
```php
class ProductFactory
{
    public static function new(): static { return new static(); }

    public function create(array $attrs = []): Product
    {
        $m = new Product(array_merge([
            'name' => 'Product ' . \Str::random(4),
            'sku'  => strtoupper(\Str::random(6)),
            'price' => 49.90, 'is_active' => true,
        ], $attrs));
        $m->save();
        return $m->fresh();
    }
}
```

---

## API Module (`ptah:module api`)

### Activation

```bash
php artisan ptah:module api
```

Automatically installs `darkaonline/l5-swagger` and publishes:
- `app/Responses/BaseResponse.php` — the standard response envelope
- `app/Http/Controllers/API/BaseApiController.php` — base controller
- `app/Http/Controllers/API/SwaggerInfo.php` — Swagger metadata (`@OA\Info`, `@OA\SecurityScheme`)

### Generating entities with an API

```bash
# Combined mode (web + API in a single command) — recommended
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal,category_id:unsignedBigInteger,is_active:boolean" \
  --api
```

Automatically generates **web and API together**:
- `app/Http/Controllers/Catalog/ProductController.php` — controller web (Livewire)
- `resources/views/livewire/catalog/product/` — views
- `app/Http/Controllers/API/Catalog/ProductController.php` — Swagger `@OA\*` completo
- `app/Http/Requests/API/Catalog/CreateProductApiRequest.php`
- `app/Http/Requests/API/Catalog/UpdateProductApiRequest.php`
- `app/Models/Catalog/Product.php` — `@OA\Schema` gerado
- `routes/web.php` (web route, with `auth` when the module is active) + `routes/api.php` (`Route::prefix('v1')->middleware(config('ptah.api.middleware'))` group, only with `--api`; requires an existing `routes/api.php`)

> **Model preserved:** if the entity already exists, `--api` only injects the `@OA\Schema`
> block into the model, never overwriting `$fillable`, `$casts` or relationships.

> **API only (no views):** use `--api-only` — the legacy behaviour of the old `--api`.

### Workflow completo

```bash
# 1. Install the module (once per project)
php artisan ptah:module api

# 2. Generate the entity (web + API together)
php artisan ptah:forge Catalog/Product \
  --fields="name:string,price:decimal" \
  --api

# 3. Fix the import TODOs in the generated files
# 4. Run pint
./vendor/bin/pint

# 5. Migrate
php artisan migrate

# 6. Generate the Swagger docs
php artisan l5-swagger:generate

# 7. Open the docs
# http://localhost/api/documentation
```

### BaseResponse — usage rules

**ALWAYS** use `BaseResponse::` — **NEVER** call `response()->json()` directly.

```php
use App\Responses\BaseResponse;

// index — paginated
return BaseResponse::paginated($this->service->getData($request));

// show — single record
$item = $this->service->show($id);
return $item ? BaseResponse::ok($item) : BaseResponse::notFound('Product not found');

// store
return BaseResponse::created($this->service->create($request->validated()));

// update
return BaseResponse::ok($this->service->update($request->validated(), $id));

// destroy
return $this->service->destroy($id) ? BaseResponse::noContent() : BaseResponse::notFound();

// custom error
return BaseResponse::error('Message', ['field' => 'detail'], 422);
```

**Response envelope:**
```json
{
  "success": true,
  "message": "OK",
  "data": { ... },
  "meta": { "current_page": 1, "total": 50, ... }
}
```

### getData($request) — smart listing

`BaseService::getData(Request $request)` orchestrates the whole listing from request parameters:

| Parameter | Behaviour |
|---|---|
| `search` | OR across every `$fillable` |
| `searchLike` | Incremental filter with `>`, `>=`, `<=`, `<`, `whereIn` operators |
| neither of them | Exact AND (`findAllFieldsAnd`) |
| `limit`, `page` | Automatic pagination |
| `order`, `direction` | Sorting |
| `fields` | Select only specific columns |
| `relations` | Eager load (comma-separated) |

```php
// In the controller, this is all of it:
public function index(Request $request): JsonResponse
{
    return BaseResponse::paginated($this->service->getData($request));
}
```

### Namespaces e naming conventions

| Artefato | Caminho | Classe |
|---|---|---|
| Controller | `Http/Controllers/API/{Folder}/` | `{Entity}Controller` |
| Request criar | `Http/Requests/API/{Folder}/` | `Create{Entity}ApiRequest` |
| Request atualizar | `Http/Requests/API/{Folder}/` | `Update{Entity}ApiRequest` |
| Rotas | `routes/api.php` | prefixo `v1` + middleware da config |

### Anti-patterns proibidos

```php
// ❌ NUNCA — query no controller
public function index() {
    return Product::where('active', true)->get();
}

// ❌ NUNCA — response()->json() avulso
return response()->json(['data' => $data]);

// ❌ NEVER — business logic in the controller
public function store(Request $request) {
    if (Product::where('sku', $request->sku)->exists()) { ... }
}

// ✅ CERTO
public function index(Request $request): JsonResponse
{
    return BaseResponse::paginated($this->service->getData($request));
}
```

---

## Performance & high-demand architecture

Throughput work — caching, queues, indexes, N+1, chunking, Livewire payload —
lives in a reference file so it does not cost you tokens on every task:

**`references/performance.md`** (read it when the task is actually about
performance; it opens with a measurement of what the package's own layers
cost, so you do not optimise on suspicion).

The three rules worth carrying without reading it:

1. **Never** loop a query — eager-load or `chunkById`.
2. Cache with a key that carries what invalidates it; the package invalidates
   by generation, never by `cache:clear`.
3. A job that touches rows written in the same request needs
   `$afterCommit = true`, honoured even by the `sync` driver.

## Commit Convention

> ⚠️ **ALWAYS run Pint before any commit.** Never commit unformatted PHP code.

```bash
# REQUIRED before every git commit:
./vendor/bin/pint

# Then commit:
git add .
git commit -m "feat: ..."
```

```
feat:     new feature
fix:      bug fix
docs:     documentation only
refactor: no feat/fix
test:     tests
chore:    maintenance (deps, config)
```

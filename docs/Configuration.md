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
2. [Configuration Methods](#configuration-methods)
3. [Visual Configuration Modal](#visual-configuration-modal)
4. [CLI Command (ptah:config)](#cli-command-ptahconfig)
5. [Comparison: Modal vs CLI](#comparison-modal-vs-cli)
6. [CrudConfig Structure (JSON)](#crudconfig-structure-json)
7. [Column Configuration](#column-configuration)
8. [Action Configuration](#action-configuration)
9. [Filter Configuration](#filter-configuration)
10. [Style Configuration](#style-configuration)
11. [JOIN Configuration](#join-configuration)
12. [General Settings](#general-settings)
13. [Permissions Configuration](#permissions-configuration)
14. [Lifecycle Hooks (Dynamic Code)](#lifecycle-hooks-dynamic-code)
15. [Complete Practical Examples](#complete-practical-examples)
16. [Recommended Workflow](#recommended-workflow)
17. [Import/Export of Configurations](#importexport-of-configurations)
18. [Troubleshooting](#troubleshooting)

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
| `config` | `json` | Complete configuration in JSON |
| `created_at` | `timestamp` | Creation date |
| `updated_at` | `timestamp` | Last modification |

### Cache and Invalidation

Configurations are cached automatically:
- Cache key: `crud_config_{model}`
- TTL: Configurable in `crud_configs.cacheTtl` (default: 3600s)
- Automatic invalidation when saving via modal or CLI

---

## Configuration Methods

### 1. Via Visual Modal (Graphical Interface)

Access the configuration button (⚙️) at the top of the CRUD screen:

```blade
{{-- The button is rendered automatically by BaseCrud --}}
<button wire:click="$dispatch('openConfig', { model: '{{ $model }}' })">
    ⚙️ Config
</button>
```

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

The modal is opened automatically when clicking the **⚙️ Config** button at the top of the BaseCrud (usually restricted to administrators via `@can('admin')` or similar).

### Tab Structure

The modal has **7 main tabs**:

#### 1️⃣ Columns

**What it does:** Manages all CRUD columns (listing + form)

**Interface:**
- **Left sidebar:** Drag-and-drop list of all columns
- **Right panel:** 6 sub-tabs to edit the selected column

**Available actions:**
- 🔄 Reorder via drag-and-drop
- ➕ Add new column
- ✏️ Edit existing column
- 🗑️ Delete column
- 👁️ Toggle visibility

**Sub-tabs per column:**

| Sub-tab | Fields Edited | Description |
|---------|---------------|-------------|
| **Basic** | `colsNomeFisico`, `colsNomeLogico`, `colsTipo`, `colsGravar`, `colsRequired`, `colsIsFilterable`, `colsCellStyle`, `colsCellClass`, `colsCellIcon`, `colsMinWidth`, `colsSource` | Main column info + cell style + SQL source (JOIN badge) |
| **Display** | `colsHelper`, `colsRenderer`, `colsRelacaoNested`, `colsMask`, `colsMaskTransform` | How the column is rendered in the table and formatted in the form |
| **Badges** | `colsRendererBadges` | Value→colour map for `badge`/`pill` renderer with hex picker + 8 quick swatches |
| **Relation** | `colsRelacao`, `colsRelacaoExibe`, `colsSDModel`, `colsSDLabel`, `colsSDValor`, `colsSDOrder`, `colsSDTipo`, `colsSDMode` | Eloquent relationship and SearchDropdown configuration |
| **Validation** | `colsValidations`, `colsRequired` | Form validation rules |
| **Advanced** | `colsOrderBy`, `colsReverse`, `colsMetodoCustom`, `colsAlign` | Custom ordering, accessor methods and alignment |

**Example flow:**

1. Click a column in the sidebar (e.g. `status`)
2. Go to the **"Display"** sub-tab
3. Change `colsRenderer` to `badge`
4. Go to the **"Badges"** sub-tab
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

**Example of custom action:**

```json
{
  "actionName": "approve",
  "actionLabel": "Approve",
  "actionType": "livewire",
  "actionValue": "approve(%id%)",
  "actionIcon": "bx-check",
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
   - Each filter has: field, label, type (text/number/date/select/searchdropdown), operator (=, !=, >, <, >=, <=, LIKE)

**Example of custom filter:**

```json
{
  "colsFilterField": "status",
  "colsFilterLabel": "Status",
  "colsFilterType": "select",
  "colsFilterOperator": "=",
  "colsFilterOptions": {
    "active": "Active",
    "inactive": "Inactive",
    "pending": "Pending"
  }
}
```

#### 4️⃣ Estilos

**What it does:** Defines conditional row styles

**Interface:**
- Card per style rule
- Each rule: field, operator, value, CSS (background, color, fontWeight, custom)

**Example rule:**

```json
{
  "styleField": "status",
  "styleOperator": "==",
  "styleValue": "cancelled",
  "styleBackgroundColor": "#FEE2E2",
  "styleColor": "#991B1B",
  "styleFontWeight": "bold"
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
  "joinTable": "categories",
  "joinType": "left",
  "joinLeftColumn": "products.category_id",
  "joinRightColumn": "categories.id",
  "joinSelect": ["categories.name as category_name"],
  "joinDistinct": false,
  "joinWhere": "categories.active = 1"
}
```

**Visual in the modal:**
```
┌─────────────────────────────────────┐
│ LEFT JOIN categories               │
│ ON products.category_id = categories.id │
│ SELECT: name as category_name      │
│ WHERE: active = 1                  │
│ DISTINCT: No                       │
│ [Edit] [Delete]                    │
└─────────────────────────────────────┘
```

#### 6️⃣ Geral

**What it does:** Global CRUD settings

**Sections:**

1. **Identification:**
   - `displayName` — Display name (e.g. "Products")

2. **Appearance:**
   - `companyField` — Multi-tenancy field
   - `tableClass` — Extra CSS classes for the table

3. **Cache:**
   - `cacheEnabled` — Enable cache (true/false)
   - `cacheTtl` — Cache time-to-live (seconds)

4. **Export:**
   - `exportMaxRows` — Maximum exportable rows
   - `pdfOrientation` — PDF orientation (landscape/portrait)
   - `pdfPaperSize` — Paper size (A4, Letter, etc)

5. **Broadcast (Real-time):**
   - `broadcastEnabled` — Enable Echo listener
   - `broadcastChannel` — Channel name (default: `page-{model}-observer`)
   - `broadcastEvent` — Event name (default: `.page{Model}Observer`)

6. **Visual Theme:**
   - `theme` — light/dark

#### 7️⃣ Permissions

**What it does:** Maps Laravel gates/abilities per action

**Interface:**
- List of actions (list, view, create, edit, delete, export, import, restore, forceDelete)
- Text field for each action (e.g. `product.create`, `view-products`)

**Example:**

```json
{
  "permissions": {
    "list": "product.index",
    "create": "product.create",
    "edit": "product.update",
    "delete": "product.destroy",
    "export": "product.export"
  }
}
```

**Usage in code:**

```php
// BaseCrud verifica automaticamente:
if (!Gate::allows($this->crudConfig['permissions']['create'] ?? 'create')) {
    abort(403);
}
```

#### 8️⃣ Lifecycle Hooks

**What it does:** Allows running custom PHP code at specific points in the record lifecycle

> 📁 **Full example:** See [ProductHooks.example.php](ProductHooks.example.php) — Template with 300+ lines of practical, documented examples.

**Hybrid System:** Supports **two syntaxes**:

1. **Inline Code (eval):** For simple, fast logic
   ```php
   $data['status'] = 'pending';
   Log::info('Creating product');
   ```

2. **PHP Classes (recommended):** For complex, testable logic with autocomplete
   ```php
   @ProductHooks::beforeCreate
   @App\CrudHooks\ProductHooks
   ```

**Interface:**
- 4 textareas with code editor
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

---

### 📝 **Syntax 1: Inline Code (eval)**

Write PHP code directly in the modal. Ideal for simple logic.

**beforeCreate** — Set default values:
```php
$data['status'] = 'pending';
$data['uuid'] = \Illuminate\Support\Str::uuid();
Log::info('Creating new product');
```

**afterCreate** — Dispatch events:
```php
Log::info('Product created: ' . $record->id);
event(new \App\Events\ProductCreated($record));
cache()->put('latest_product', $record->id, 3600);
```

**beforeUpdate** — Custom validation:
```php
if ($record->status === 'draft' && isset($data['status']) && $data['status'] === 'published') {
    $data['published_at'] = now();
    $data['published_by'] = auth()->id();
}
```

**afterUpdate** — Invalidate cache:
```php
cache()->forget('product_' . $record->id);
cache()->tags(['products'])->flush();
$record->load('category', 'tags');
```

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
| `@App\Services\MyHooks::customMethod` | Uses full namespace |
| `@App\Services\MyHooks@customMethod` | `@` separator also works |

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

**Inline Code (eval):**

⚠️ **IMPORTANT:** The code is executed with `eval()` in an isolated closure.

✅ **Implemented protections:**
- Automatic try-catch — errors do not break the save()
- Detailed error logging in `storage/logs/laravel.log`
- Modal access restricted via `@ptahCan('configCrud', 'read')`
- Isolated context — variables limited to the hook scope

**PHP Classes:**

✅ **More secure:**
- No eval() — code compiled by PHP
- Syntax validation by IDE and CI/CD
- Errors detected at development time
- class-not-found and method-not-found handled automatically

**Example error log (inline):**

```
[2026-03-04 15:30:45] local.ERROR: [BaseCrud] Lifecycle hook 'beforeCreate' failed for model App\Models\Product
{
    "hook": "beforeCreate",
    "model": "App\\Models\\Product",
    "error": "Undefined variable $foo",
    "file": "eval()'d code",
    "line": 1,
    "trace": "...",
    "code": "$data['test'] = $foo;"
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

**Restrictions (inline code):**

❌ **Does not work:**
- `return` to interrupt execution (use exceptions if needed)
- Access to undocumented external variables
- Declaration of classes or functions

✅ **Works:**
- Modify `$data` by reference in `before*` hooks
- Access `$record` to read record data
- Use Laravel facades (`Log`, `Cache`, `DB`, etc)
- Dispatch events, jobs, notifications
- Execute additional SQL queries

---

### 💾 **Saved JSON**

**Inline code:**

```json
{
  "lifecycleHooks": {
    "beforeCreate": "$data['status'] = 'pending';",
    "afterCreate": "Log::info('Created: ' . $record->id);",
    "beforeUpdate": "if ($record->isDirty('price')) { $data['price_updated_at'] = now(); }",
    "afterUpdate": "cache()->forget('product_' . $record->id);"
  }
}
```

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
    "beforeCreate": "@ProductHooks",
    "afterCreate": "Log::info('Simple log');",
    "beforeUpdate": "@App\\Services\\ComplexValidator::validate",
    "afterUpdate": "cache()->flush();"
  }
}
```

---

### 🚀 **Quick Start: Creating your first hooks class**

**Option A — Artisan (recommended):**

```bash
php artisan ptah:hooks ProductHooks
```

Creates `app/CrudHooks/ProductHooks.php` with the 4 pre-filled methods, ready to edit.

With subfolder:
```bash
php artisan ptah:hooks Inventory/StockHooks
```

**Option B — Copy the example template:**

```bash
cp vendor/ptah/ptah/docs/ProductHooks.example.php app/CrudHooks/ProductHooks.php
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
| `ptah:crud-config-updated` | After saving the modal | `{ model: 'Product' }` |

**Listening in BaseCrud:**

```php
protected $listeners = [
    'ptah:crud-config-updated' => 'reloadConfig',
];

public function reloadConfig($data)
{
    if ($data['model'] === $this->model) {
        $this->crudConfig = $this->loadCrudConfig();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Configuration reloaded!'
        ]);
    }
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
| `--reset` | Flag | Reset to default |
| `--import=` | String | Import from JSON |
| `--export=` | String | Export to JSON |
| `--non-interactive` | Flag | Skip wizard |
| `--force` | Flag | Overwrite without confirmation |
| `--dry-run` | Flag | Simulate without saving |
| `--only=*` | Array | Process only specific sections |
| `--skip=*` | Array | Skip specific sections |

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
   Column type: [text, textarea, number, date, datetime, select, searchdropdown, boolean, file, image]
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
   Input mask: [none, money_brl, money_usd, percent, cpf, cnpj, rg, pis, ncm, ean13, phone, cep, plate, credit_card, date, datetime, time, integer, uppercase, custom_regex]
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
│ colsValidation      │ ["required","numeric","min:0","max:999999"] │
│ colsRequired        │ true                     │
│ colsAlign           │ text-end                 │
└─────────────────────┴──────────────────────────┘

Save this column configuration? [yes]
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
  --column="name:text:required:label=Product Name:validation=required|max:255" \
  --column="sku:text:required:validation=required|unique:products,sku" \
  --column="price:number:required:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2" \
  --column="stock:number:label=Stock:renderer=number:rendererDecimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --column="category_id:searchdropdown:relation=category:sdSelectColumn=name:sdValueColumn=id" \
  --set="itemsPerPage=25" \
  --set="cacheEnabled=true" \
  --set="cacheTtl=3600"
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
3. `modifier` — Optional modifier (shorthand): `required`, `optional`, `readonly`, `hidden`, `noFilter`, `noSave`, `total`
4. `option=value` — Key=value pairs for additional settings

**Modifiers (shorthands):**

| Modifier | Equivalent to | Description |
|----------|--------------|-------------|
| `required` | `colsRequired = true` | Required field |
| `optional` | `colsRequired = false` | Optional field |
| `readonly` | `colsEditableForm = false` | Read-only in form |
| `hidden` | `colsVisibleList = false` | Not shown in list |
| `noFilter` | `colsIsFilterable = false` | Not filterable |
| `noSave` | `colsGravar = false` | Not saved to database |
| `total` | `colsTotal = true` | Add to totalizer |

**Options (key=value):**

| Option | Mapping | Type | Example |
|--------|------------|------|---------|
| `label` | `colsNomeLogico` | string | `label=Product Name` |
| `help` | `colsHelpText` | string | `help=Enter product name` |
| `placeholder` | `colsPlaceholder` | string | `placeholder=Type here...` |
| `default` | `colsDefaultValue` | mixed | `default=0` |
| `align` | `colsAlign` | string | `align=text-end` |
| `width` | `colsWidth` | string | `width=120px` |
| `renderer` | `colsRenderer` | string | `renderer=money` |
| `rendererLink` | `colsRendererLink` | string | `rendererLink=/products/%id%` |
| `rendererTarget` | `colsRendererTarget` | string | `rendererTarget=_blank` |
| `rendererCurrency` | `colsRendererCurrency` | string | `rendererCurrency=BRL` |
| `rendererDecimals` | `colsRendererDecimals` | int | `rendererDecimals=2` |
| `rendererPrefix` | `colsRendererPrefix` | string | `rendererPrefix=$` |
| `rendererSuffix` | `colsRendererSuffix` | string | `rendererSuffix=%` |
| `rendererFormat` | `colsRendererFormat` | string | `rendererFormat=d/m/Y` |
| `rendererMaxChars` | `colsRendererMaxChars` | int | `rendererMaxChars=50` |
| `badges` | `colsRendererBadges` | array | `badges=active|green,inactive|red` |
| `mask` | `colsMask` | string | `mask=money_brl` |
| `maskTransform` | `colsMaskTransform` | string | `maskTransform=money_to_float` |
| `maskDecimalPlaces` | `colsMaskDecimalPlaces` | int | `maskDecimalPlaces=2` |
| `validation` | `colsValidation` | array | `validation=required\|email` |
| `options` | `colsOptions` | array | `options=yes:Yes,no:No` |
| `relation` | `colsRelation` | string | `relation=category` |
| `sdTable` | `colsSdTable` | string | `sdTable=categories` |
| `sdSelectColumn` | `colsSdSelectColumn` | string | `sdSelectColumn=name` |
| `sdValueColumn` | `colsSdValueColumn` | string | `sdValueColumn=id` |
| `uploadPath` | `colsUploadPath` | string | `uploadPath=products/images` — overrides the auto-derived path |
| `uploadMaxSize` | `colsUploadMaxSize` | int | `uploadMaxSize=2048` — max size in KB (default: 2048) |
| `uploadAllowedTypes` | `colsUploadAllowedTypes` | string | `uploadAllowedTypes=jpg,png,webp` — comma-separated extensions |
| `totalizer` | `colsTotal` | bool | `totalizer=true` |
| `totalizadorType` | `totalizadorType` | string | `totalizadorType=sum` |

**Examples:**

```bash
# Simple required text
--column="name:text:required:label=Product Name"

# Email with validation
--column="email:text:required:validation=required|email|max:255"

# Price with mask and renderer
--column="price:number:required:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2"

# Status with select and badges
--column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red,pending|yellow"

# SearchDropdown for category
--column="category_id:searchdropdown:relation=category:sdSelectColumn=name:sdValueColumn=id"

# Readonly date
--column="created_at:datetime:readonly:renderer=datetime:rendererFormat=d/m/Y H:i:s"

# Boolean
--column="active:boolean:default=true"

# Description with textarea
--column="description:textarea:optional:placeholder=Enter description"

# Image with upload — auto path: storage/app/public/images/product/
--column="image:image:uploadPath=products:uploadMaxSize=2048:uploadAllowedTypes=jpg,png,webp"

# Image with custom path and strict types
--column="photo:image:upload_path=avatars:upload_max_size=1024:upload_allowed_types=jpg,png"

# Number with totalizer
--column="quantity:number:total:totalizadorType=sum:totalizadorFormat=number"
```

#### --action (Actions)

**Format:**
```
name:type:value:icon=icon:color=color:confirm=bool
```

**Examples:**

```bash
# Livewire action with confirmation
--action="approve:livewire:approve(%id%):icon=bx-check:color=success:confirm=true"

# External link
--action="view:link:https://example.com/products/%id%:icon=bx-show:color=primary"

# JavaScript action
--action="export:javascript:exportData():icon=bx-download:color=info"

# Action with custom confirmation
--action="delete:livewire:deleteCustom(%id%):icon=bx-trash:color=danger:confirm=true:confirmMessage=Are you sure?"
```

#### --filter (Filters)

**Format:**
```
field:type:operator:label=Label:options=opt1,opt2
```

**Examples:**

```bash
# Simple select
--filter="status:select:=:label=Status:options=active,inactive,pending"

# Number with minimum
--filter="price:number:>=:label=Minimum Price"

# Date
--filter="created_at:date:>=:label=From Date"

# SearchDropdown
--filter="user_id:searchdropdown:=:label=User:sdTable=users:sdSelectColumn=name:sdValueColumn=id"

# Text with LIKE
--filter="name:text:LIKE:label=Search Name"
```

#### --style (Styles)

**Format:**
```
field:operator:value:background=color:color=textColor:fontWeight=weight
```

**Examples:**

```bash
# Cancelled status in red
--style="status:==:cancelled:background=#FEE2E2:color=#991B1B:fontWeight=bold"

# High priority in yellow
--style="priority:>:5:background=#FEF3C7:color=#92400E"

# Low stock
--style="stock:<:10:background=#DBEAFE:color=#1E40AF:fontWeight=normal"
```

#### --join (JOINs)

**Format:**
```
type:table:leftCol=rightCol:select=field1,field2:where=condition
```

**Examples:**

```bash
# LEFT JOIN with users
--join="left:users:products.user_id=users.id:select=name,email"

# INNER JOIN with categories
--join="inner:categories:products.category_id=categories.id:select=name as category_name:where=categories.active=1"

# JOIN with DISTINCT
--join="left:suppliers:products.supplier_id=suppliers.id:select=name:distinct=true"
```

#### --set (General Settings)

**Format:**
```
key=value
```

**Examples:**

```bash
--set="cacheEnabled=true"
--set="cacheTtl=3600"
--set="itemsPerPage=25"
--set="paginationEnabled=true"
--set="searchEnabled=true"
--set="exportEnabled=true"
--set="exportMaxRows=10000"
--set="softDeletes=true"
--set="theme=dark"
--set="compactMode=false"
--set="displayName=Products"
```

#### --permission (Permissions)

**Formato:**
```
action=permission_string
```

**Exemplos:**

```bash
--permission="list=product.index"
--permission="create=product.create"
--permission="edit=product.update"
--permission="delete=product.destroy"
--permission="export=product.export"
```

### Special Commands

#### --list (List Configuration)

Displays the current configuration in table format:

```bash
php artisan ptah:config "App\Models\Product" --list
```

**Output:**

```
═══════════════════════════════════════════════════════
  Configuration for: App\Models\Product
═══════════════════════════════════════════════════════

📋 Columns (6)
───────────────────────────────────────────────────────
+-------------+---------------+--------+----------+-----+---------+----------+
| Field       | Label         | Type   | Renderer | Req | Visible | Editable |
+-------------+---------------+--------+----------+-----+---------+----------+
| id          | ID            | number | number   | ✗   | ✓       | ✗        |
| name        | Product Name  | text   | text     | ✓   | ✓       | ✓        |
| sku         | SKU           | text   | text     | ✓   | ✓       | ✓        |
| price       | Price         | number | money    | ✓   | ✓       | ✓        |
| stock       | Stock         | number | number   | ✗   | ✓       | ✓        |
| status      | Status        | select | badge    | ✓   | ✓       | ✓        |
+-------------+---------------+--------+----------+-----+---------+----------+

⚡ Actions (2)
───────────────────────────────────────────────────────
+----------+----------+----------+---------+----------+---------+
| Name     | Label    | Type     | Color   | Icon     | Confirm |
+----------+----------+----------+---------+----------+---------+
| approve  | Approve  | livewire | success | bx-check | ✓       |
| reject   | Reject   | livewire | danger  | bx-x     | ✓       |
+----------+----------+----------+---------+----------+---------+

🔍 Filters (2)
───────────────────────────────────────────────────────
+------------+--------+--------+----------+
| Field      | Label  | Type   | Operator |
+------------+--------+--------+----------+
| status     | Status | select | =        |
| created_at | Date   | date   | >=       |
+------------+--------+--------+----------+

⚙️  General Settings
───────────────────────────────────────────────────────
+--------------------+-------+
| Setting            | Value |
+--------------------+-------+
| Cache Enabled      | ✓     |
| Cache Time         | 60 min|
| Pagination Enabled | ✓     |
| Items Per Page     | 25    |
| Search Enabled     | ✓     |
| Export Enabled     | ✓     |
| Soft Deletes       | ✓     |
| Theme              | light |
| Compact Mode       | ✗     |
+--------------------+-------+
```

#### --reset (Reset Configuration)

Removes all configuration and returns to default:

```bash
php artisan ptah:config "App\Models\Product" --reset
```

**Prompt:**
```
Are you sure you want to reset all configuration for App\Models\Product? [yes/no]
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
  "actions": [...],
  "filters": [...],
  "styles": [...],
  "joins": [...],
  "permissions": {...},
  "cacheEnabled": true,
  "cacheTtl": 3600,
  "itemsPerPage": 25
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

Limits or excludes sections from the configuration:

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
      "colsValidations": ["required", "maxLength:255"],
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
      "colsMaskDecimalPlaces": 2,
      "colsGravar": true,
      "colsRequired": true,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsValidations": ["required", "numeric", "min:0"],
      "colsAlign": "text-end",
      "colsTotal": true,
      "totalizadorType": "sum",
      "totalizadorFormat": "currency",
      "totalizadorCurrency": "BRL",
      "totalizadorDecimals": 2
    },
    {
      "colsNomeFisico": "status",
      "colsNomeLogico": "Status",
      "colsTipo": "select",
      "colsRenderer": "badge",
      "colsRendererBadges": {
        "active": "green",
        "inactive": "red",
        "pending": "yellow"
      },
      "colsOptions": {
        "active": "Active",
        "inactive": "Inactive",
        "pending": "Pending"
      },
      "colsGravar": true,
      "colsRequired": true,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsValidations": ["required", "in:active,inactive,pending"],
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
      "colsSDTipo": "searchdropdown",
      "colsGravar": true,
      "colsRequired": false,
      "colsIsFilterable": true,
      "colsVisibleList": true,
      "colsEditableForm": true,
      "colsAlign": "text-start"
    }
  ],
  "actions": [
    {
      "actionName": "approve",
      "actionLabel": "Approve",
      "actionType": "livewire",
      "actionValue": "approve(%id%)",
      "actionIcon": "bx-check",
      "actionColor": "success",
      "actionPosition": "row",
      "actionConfirm": true,
      "actionConfirmMessage": "Are you sure you want to approve this product?",
      "actionPermission": "product.approve"
    },
    {
      "actionName": "reject",
      "actionLabel": "Reject",
      "actionType": "livewire",
      "actionValue": "reject(%id%)",
      "actionIcon": "bx-x",
      "actionColor": "danger",
      "actionPosition": "row",
      "actionConfirm": true,
      "actionConfirmMessage": "Are you sure you want to reject this product?",
      "actionPermission": "product.reject"
    }
  ],
  "filters": [
    {
      "colsFilterField": "status",
      "colsFilterLabel": "Status",
      "colsFilterType": "select",
      "colsFilterOperator": "=",
      "colsFilterOptions": {
        "active": "Active",
        "inactive": "Inactive",
        "pending": "Pending"
      }
    },
    {
      "colsFilterField": "created_at",
      "colsFilterLabel": "Creation Date",
      "colsFilterType": "date",
      "colsFilterOperator": ">=",
      "colsFilterPlaceholder": "From date"
    }
  ],
  "styles": [
    {
      "styleField": "status",
      "styleOperator": "==",
      "styleValue": "inactive",
      "styleBackgroundColor": "#FEE2E2",
      "styleColor": "#991B1B",
      "styleFontWeight": "normal"
    },
    {
      "styleField": "stock",
      "styleOperator": "<",
      "styleValue": "10",
      "styleBackgroundColor": "#FEF3C7",
      "styleColor": "#92400E",
      "styleFontWeight": "bold"
    }
  ],
  "joins": [
    {
      "joinTable": "categories",
      "joinType": "left",
      "joinLeftColumn": "products.category_id",
      "joinRightColumn": "categories.id",
      "joinSelect": ["categories.name as category_name"],
      "joinDistinct": false,
      "joinWhere": "categories.active = 1"
    }
  ],
  "permissions": {
    "list": "product.index",
    "view": "product.show",
    "create": "product.create",
    "edit": "product.update",
    "delete": "product.destroy",
    "export": "product.export",
    "import": "product.import"
  },
  "cacheEnabled": true,
  "cacheTtl": 3600,
  "paginationEnabled": true,
  "itemsPerPage": 25,
  "searchEnabled": true,
  "exportEnabled": true,
  "exportMaxRows": 10000,
  "softDeletes": true,
  "showTrashed": false,
  "companyField": "company_id",
  "theme": "light",
  "compactMode": false,
  "striped": true,
  "hover": true,
  "showRowNumbers": true,
  "quickDateColumn": "created_at",
  "broadcastEnabled": false,
  "broadcastChannel": "page-product-observer",
  "broadcastEvent": ".pageProductObserver",
  "pdfOrientation": "landscape",
  "pdfPaperSize": "A4",
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
| `colsTipo` | string | `'text'` | Input type: `text`, `textarea`, `number`, `date`, `datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image` |
| `colsGravar` | bool | `true` | Save value to database |
| `colsRequired` | bool | `false` | Required field |
| `colsIsFilterable` | bool | `true` | Allows filtering by this column |
| `colsVisibleList` | bool | `true` | Show in list |
| `colsEditableForm` | bool | `true` | Editable in form |
| `colsAlign` | string | `'text-start'` | Alignment: `text-start`, `text-center`, `text-end` |
| `colsWidth` | string | `'auto'` | Column width: `120px`, `20%`, `auto` |
| `colsSource` | string | `''` | Badge indicating SQL source (e.g.: `JOIN categories`) |

### Cell Style

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsCellStyle` | string | `''` | Inline CSS for the cell (e.g.: `background: #FEE;`) |
| `colsCellClass` | string | `''` | CSS classes for the cell |
| `colsCellIcon` | string | `''` | Icon in the header (e.g.: `bx-user`, `fa-user`) |
| `colsMinWidth` | string | `''` | Minimum width (e.g.: `100px`) |

### Display and Rendering

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsHelper` | string | `''` | Help text below the field |
| `colsRenderer` | string | `'text'` | Renderer type: `text`, `badge`, `pill`, `boolean`, `money`, `date`, `datetime`, `link`, `image`, `truncate`, `number`, `filesize`, `duration`, `code`, `color`, `progress`, `rating`, `qrcode` |
| `colsRendererLink` | string | `''` | URL pattern for `link` renderer (e.g.: `/products/%id%`) |
| `colsRendererTarget` | string | `'_self'` | Link target: `_self`, `_blank` |
| `colsRendererCurrency` | string | `'BRL'` | Currency for `money` renderer: `BRL`, `USD`, `EUR` |
| `colsRendererDecimals` | int | `2` | Decimal places for `money` or `number` renderer |
| `colsRendererPrefix` | string | `''` | Prefix for `number` renderer |
| `colsRendererSuffix` | string | `''` | Suffix for `number` renderer |
| `colsRendererFormat` | string | `''` | Format for `date`/`datetime` renderer (e.g.: `d/m/Y H:i:s`) |
| `colsRendererMaxChars` | int | `50` | Maximum characters for `truncate` renderer |
| `colsRendererBadges` | array | `[]` | Value→color map for `badge`/`pill` renderers |

### Relation

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsRelacao` | string | `''` | Eloquent relation method name |
| `colsRelacaoExibe` | string | `'name'` | Field to display from the relation |
| `colsRelacaoNested` | string | `''` | Nested relation (e.g.: `category.parent.name`) |
| `colsSDModel` | string | `''` | Full model for SearchDropdown (e.g.: `App\Models\Category`) |
| `colsSDLabel` | string | `'name'` | Display field in SearchDropdown |
| `colsSDValor` | string | `'id'` | Value field in SearchDropdown |
| `colsSDOrder` | string | `'name ASC'` | SearchDropdown sort order |
| `colsSDTipo` | string | `'searchdropdown'` | SearchDropdown type |
| `colsSDMode` | string | `'single'` | Mode: `single`, `multiple` |

### Input Mask

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsMask` | string | `''` | Input mask: `money_brl`, `money_usd`, `percent`, `cpf`, `cnpj`, `rg`, `pis`, `ncm`, `ean13`, `phone`, `cep`, `plate`, `credit_card`, `date`, `datetime`, `time`, `integer`, `uppercase`, `custom_regex` |
| `colsMaskTransform` | string | `''` | Transform on save: `money_to_float`, `digits_only`, `plate_clean`, `date_br_to_iso`, `date_iso_to_br`, `uppercase`, `lowercase`, `trim` |
| `colsMaskDecimalPlaces` | int | `2` | Decimal places for currency masks |
| `colsMaskEmptyValue` | string | `''` | Value saved when field is empty |

### Validation

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsValidations` | array | `[]` | Array of validation rules: `["required", "email", "max:255"]` |
| `colsValidationMessage` | string | `''` | Custom error message |

### Select Options

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsOptions` | array | `[]` | Options for `<select>`: `{"value": "Label"}` |
| `colsOptionsFrom` | string | `''` | Method that returns options dynamically |

### File Upload

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsUploadPath` | string | `''` | Upload path (e.g.: `products/images`) |
| `colsUploadDisk` | string | `'public'` | Storage disk: `public`, `s3`, etc |
| `colsUploadMaxSize` | int | `2048` | Maximum size in KB |
| `colsUploadAllowedTypes` | array | `[]` | Allowed extensions: `["jpg", "png", "webp"]` |
| `colsUploadMultiple` | bool | `false` | Multiple upload |

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

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsTotal` | bool | `false` | Add to totalizer |
| `totalizadorType` | string | `'sum'` | Type: `sum`, `count`, `avg`, `max`, `min` |
| `totalizadorFormat` | string | `'number'` | Format: `currency`, `number`, `integer` |
| `totalizadorCurrency` | string | `'BRL'` | Currency for currency format |
| `totalizadorDecimals` | int | `2` | Decimal places |
| `totalizadorPrefix` | string | `''` | Prefix (e.g.: `$`) |
| `totalizadorSuffix` | string | `''` | Suffix (e.g.: `un`) |

### Advanced

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsOrderBy` | string | `''` | Custom sort order (e.g.: `FIELD(status, 'pending', 'active', 'inactive')`) |
| `colsReverse` | bool | `false` | Reverse value order (for arrays) |
| `colsMetodoCustom` | string | `''` | Custom accessor method in the model |
| `colsPlaceholder` | string | `''` | Input placeholder |
| `colsDefaultValue` | mixed | `null` | Default value on create |

---

## Action Configuration

Properties of each action in `actions[]`:

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `actionName` | string | — | Unique action name (required) |
| `actionLabel` | string | `ucfirst(actionName)` | Display label on the button |
| `actionType` | string | `'livewire'` | Type: `livewire`, `link`, `javascript` |
| `actionValue` | string | — | Action value/command (required) |
| `actionIcon` | string | `''` | Button icon (e.g.: `bx-check`, `fa-check`) |
| `actionColor` | string | `'primary'` | Button color: `primary`, `success`, `danger`, `warning`, `info`, `secondary` |
| `actionPosition` | string | `'row'` | Position: `row` (per row), `bulk` (bulk action), `both` |
| `actionConfirm` | bool | `false` | Require confirmation |
| `actionConfirmMessage` | string | `''` | Confirmation message |
| `actionPermission` | string | `''` | Required gate/ability |

**Supported placeholders in `actionValue`:**
- `%id%` → Record ID
- `%field%` → Value of any field (e.g.: `%email%`, `%name%`)

**Examples:**

```json
{
  "actionName": "approve",
  "actionLabel": "Approve",
  "actionType": "livewire",
  "actionValue": "approve(%id%)",
  "actionIcon": "bx-check",
  "actionColor": "success",
  "actionConfirm": true,
  "actionConfirmMessage": "Approve this record?",
  "actionPermission": "product.approve"
}
```

```json
{
  "actionName": "viewExternal",
  "actionLabel": "View",
  "actionType": "link",
  "actionValue": "https://external.com/products/%id%",
  "actionIcon": "bx-link-external",
  "actionColor": "info"
}
```

```json
{
  "actionName": "export",
  "actionLabel": "Export",
  "actionType": "javascript",
  "actionValue": "exportData()",
  "actionIcon": "bx-download",
  "actionColor": "secondary"
}
```

---

## Filter Configuration

Properties of each filter in `filters[]`:

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `colsFilterField` | string | — | Field to filter (required) |
| `colsFilterLabel` | string | `ucfirst(field)` | Filter label |
| `colsFilterType` | string | `'text'` | Type: `text`, `number`, `date`, `select`, `searchdropdown` |
| `colsFilterOperator` | string | `'='` | Operator: `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE` |
| `colsFilterPlaceholder` | string | `''` | Input placeholder |
| `colsFilterOptions` | array | `[]` | Options for `select` type: `{"value": "Label"}` |
| `colsFilterWhereHas` | string | `''` | Relation name for `whereHas` |
| `colsFilterRelationField` | string | `''` | Field in relation for `whereHas` |
| `colsFilterAggregate` | string | `''` | Aggregate function: `SUM`, `COUNT`, `AVG`, `MAX`, `MIN` |
| `colsFilterSdTable` | string | `''` | Table for SearchDropdown |
| `colsFilterSdSelectColumn` | string | `'name'` | Display column for SearchDropdown |
| `colsFilterSdValueColumn` | string | `'id'` | Value column for SearchDropdown |

**Examples:**

```json
{
  "colsFilterField": "status",
  "colsFilterLabel": "Status",
  "colsFilterType": "select",
  "colsFilterOperator": "=",
  "colsFilterOptions": {
    "active": "Active",
    "inactive": "Inactive"
  }
}
```

```json
{
  "colsFilterField": "price",
  "colsFilterLabel": "Minimum Price",
  "colsFilterType": "number",
  "colsFilterOperator": ">=",
  "colsFilterPlaceholder": "0.00"
}
```

```json
{
  "colsFilterField": "user_id",
  "colsFilterLabel": "User",
  "colsFilterType": "searchdropdown",
  "colsFilterOperator": "=",
  "colsFilterSdTable": "users",
  "colsFilterSdSelectColumn": "name",
  "colsFilterSdValueColumn": "id"
}
```

```json
{
  "colsFilterField": "orders.total",
  "colsFilterLabel": "Total Orders",
  "colsFilterType": "number",
  "colsFilterOperator": ">",
  "colsFilterWhereHas": "orders",
  "colsFilterRelationField": "total",
  "colsFilterAggregate": "SUM"
}
```

---

## Style Configuration

Properties of each style rule in `styles[]`:

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `styleField` | string | — | Field to check (required) |
| `styleOperator` | string | `'=='` | Operator: `==`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE` |
| `styleValue` | mixed | — | Comparison value (required) |
| `styleBackgroundColor` | string | `''` | Background color (e.g.: `#FEE2E2`) |
| `styleColor` | string | `''` | Text color (e.g.: `#991B1B`) |
| `styleFontWeight` | string | `'normal'` | Font weight: `normal`, `bold`, `lighter`, `bolder` |
| `styleCustom` | string | `''` | Custom CSS (e.g.: `border: 2px solid red;`) |

**Example:**

```json
{
  "styleField": "status",
  "styleOperator": "==",
  "styleValue": "cancelled",
  "styleBackgroundColor": "#FEE2E2",
  "styleColor": "#991B1B",
  "styleFontWeight": "bold"
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
| `joinTable` | string | — | Table to join (required) |
| `joinType` | string | `'left'` | JOIN type: `left`, `inner` |
| `joinLeftColumn` | string | — | Column from the main table (required) |
| `joinRightColumn` | string | — | Column from the joined table (required) |
| `joinSelect` | array | `[]` | Fields to select: `["table.field as alias"]` |
| `joinDistinct` | bool | `false` | Use `DISTINCT` |
| `joinWhere` | string | `''` | Additional `WHERE` condition (e.g.: `table.active = 1`) |

**Example:**

```json
{
  "joinTable": "categories",
  "joinType": "left",
  "joinLeftColumn": "products.category_id",
  "joinRightColumn": "categories.id",
  "joinSelect": ["categories.name as category_name", "categories.slug as category_slug"],
  "joinDistinct": false,
  "joinWhere": "categories.active = 1"
}
```

**Resulting SQL:**

```sql
LEFT JOIN categories 
  ON products.category_id = categories.id 
  AND categories.active = 1
SELECT categories.name as category_name, categories.slug as category_slug
```

---

## General Settings

Properties at the root level of the config:

### Identification

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `displayName` | string | `class_basename($model)` | CRUD display name |

### Cache

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `cacheEnabled` | bool | `true` | Enable cache |
| `cacheTtl` | int | `3600` | Cache lifetime (seconds) |

### Pagination

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `paginationEnabled` | bool | `true` | Enable pagination |
| `itemsPerPage` | int | `25` | Items per page |
| `paginationOptions` | array | `[10, 25, 50, 100]` | Items per page options |

### Search

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `searchEnabled` | bool | `true` | Enable global search |
| `searchPlaceholder` | string | `'Search...'` | Search field placeholder |

### Export

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `exportEnabled` | bool | `true` | Enable export |
| `exportMaxRows` | int | `10000` | Maximum exportable rows |
| `exportFormats` | array | `['pdf', 'excel', 'csv']` | Enabled formats |
| `pdfOrientation` | string | `'landscape'` | PDF orientation: `landscape`, `portrait` |
| `pdfPaperSize` | string | `'A4'` | Paper size: `A4`, `Letter`, etc |

### Appearance

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `theme` | string | `'light'` | Visual theme: `light`, `dark` |
| `compactMode` | bool | `false` | Compact mode |
| `striped` | bool | `true` | Striped rows |
| `hover` | bool | `true` | Hover effect on rows |
| `showRowNumbers` | bool | `true` | Show row number |
| `tableClass` | string | `''` | Extra CSS classes for the table |

### Multi-tenant

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `companyField` | string | `'company_id'` | Company field for multi-tenant filter |

### Soft Deletes

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `softDeletes` | bool | `false` | Use soft deletes |
| `showTrashed` | bool | `false` | Show deleted records by default |

### Quick Date Filter

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `quickDateColumn` | string | `'created_at'` | Column for quick date filter |

### Broadcast (Real-time)

| Property | Type | Default | Description |
|-------------|------|--------|----------|
| `broadcastEnabled` | bool | `false` | Enable Echo listener |
| `broadcastChannel` | string | `'page-{model}-observer'` | Channel name |
| `broadcastEvent` | string | `'.page{Model}Observer'` | Event name |

**Usage example:**

```bash
php artisan ptah:config "App\Models\Product" \
  --set="displayName=Products" \
  --set="cacheEnabled=true" \
  --set="cacheTtl=7200" \
  --set="itemsPerPage=50" \
  --set="exportMaxRows=50000" \
  --set="theme=dark" \
  --set="compactMode=true" \
  --set="softDeletes=true"
```

---

## Permissions Configuration

Properties in `permissions`:

| Key | Type | Default | Description |
|-------|------|--------|----------|
| `list` | string | `''` | Gate to list records |
| `view` | string | `''` | Gate to view a record |
| `create` | string | `''` | Gate to create record |
| `edit` | string | `''` | Gate to edit record |
| `delete` | string | `''` | Gate to delete record |
| `export` | string | `''` | Gate to export |
| `import` | string | `''` | Gate to import |
| `restore` | string | `''` | Gate to restore soft-deleted |
| `forceDelete` | string | `''` | Gate to permanently delete |

**Example:**

```json
{
  "permissions": {
    "list": "product.index",
    "view": "product.show",
    "create": "product.create",
    "edit": "product.update",
    "delete": "product.destroy",
    "export": "product.export",
    "restore": "product.restore"
  }
}
```

**Usage in BaseCrud:**

```php
if (!Gate::allows($this->crudConfig['permissions']['create'] ?? 'create')) {
    abort(403, 'Unauthorized');
}
```

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
  --column="name:text:required:label=Product Name:validation=required|max:255" \
  --column="sku:text:required:label=SKU:validation=required|unique:products,sku" \
  --column="price:number:required:label=Price:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2:validation=required|numeric|min:0:totalizer=true:totalizadorType=sum" \
  --column="cost:number:label=Cost:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2:validation=numeric|min:0:totalizer=true:totalizadorType=sum" \
  --column="stock:number:label=Stock:renderer=number:rendererDecimals=0:validation=integer|min:0" \
  --column="status:select:required:options=active:Active,inactive:Inactive:renderer=badge:badges=active|green,inactive|red" \
  --column="category_id:searchdropdown:label=Category:relation=category:sdSelectColumn=name:sdValueColumn=id" \
  --action="duplicate:livewire:duplicate(%id%):icon=bx-copy:color=info" \
  --filter="status:select:=:options=active,inactive" \
  --filter="category_id:searchdropdown:=:sdTable=categories:sdSelectColumn=name:sdValueColumn=id" \
  --style="stock:<:10:background=#FEF3C7:color=#92400E:fontWeight=bold" \
  --set="displayName=Products" \
  --set="itemsPerPage=25" \
  --set="cacheEnabled=true" \
  --set="exportEnabled=true"
```

### Example 2: CRM Contacts

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Contact" \
  --column="name:text:required:label=Full Name:validation=required|max:255" \
  --column="email:text:required:label=Email:validation=required|email|unique:contacts,email" \
  --column="phone:text:label=Phone:mask=phone:validation=phone" \
  --column="company:text:label=Company" \
  --column="position:text:label=Position" \
  --column="lead_status:select:required:options=new:New,contacted:Contacted,qualified:Qualified,lost:Lost:renderer=badge:badges=new|blue,contacted|yellow,qualified|green,lost|red" \
  --column="lead_score:number:label=Lead Score:renderer=number:rendererDecimals=0:validation=integer|min:0|max:100" \
  --column="last_contact_at:datetime:label=Last Contact:renderer=datetime:rendererFormat=d/m/Y H:i:s" \
  --column="notes:textarea:label=Notes" \
  --action="sendEmail:livewire:sendEmail(%id%):icon=bx-envelope:color=primary" \
  --action="scheduleCall:livewire:scheduleCall(%id%):icon=bx-phone:color=info" \
  --filter="lead_status:select:=:options=new,contacted,qualified,lost" \
  --filter="lead_score:number:>=:label=Minimum Score" \
  --style="lead_status:==:lost:background=#FEE2E2:color=#991B1B" \
  --style="lead_score:>:80:background=#D1FAE5:color=#065F46:fontWeight=bold" \
  --set="displayName=Contacts" \
  --set="itemsPerPage=50" \
  --set="quickDateColumn=last_contact_at"
```

### Example 3: Blog Posts

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Post" \
  --column="title:text:required:label=Title:validation=required|max:255" \
  --column="slug:text:required:label=Slug:validation=required|unique:posts,slug" \
  --column="excerpt:textarea:label=Excerpt:validation=max:500" \
  --column="content:textarea:required:label=Content:validation=required" \
  --column="featured_image:image:label=Featured Image:uploadPath=posts:uploadMaxSize=2048:uploadAllowedTypes=jpg,png,webp" \
  --column="author_id:searchdropdown:required:label=Author:relation=author:sdSelectColumn=name:sdValueColumn=id" \
  --column="category_id:searchdropdown:label=Category:relation=category:sdSelectColumn=name:sdValueColumn=id" \
  --column="status:select:required:options=draft:Draft,published:Published,scheduled:Scheduled:renderer=badge:badges=draft|gray,published|green,scheduled|blue" \
  --column="published_at:datetime:label=Publish Date:renderer=datetime:rendererFormat=d/m/Y H:i:s" \
  --column="views:number:readonly:label=Views:renderer=number:rendererDecimals=0" \
  --action="preview:link:https://blog.com/posts/%slug%:icon=bx-show:color=info" \
  --action="duplicate:livewire:duplicate(%id%):icon=bx-copy:color=secondary" \
  --filter="status:select:=:options=draft,published,scheduled" \
  --filter="category_id:searchdropdown:=:sdTable=categories:sdSelectColumn=name:sdValueColumn=id" \
  --filter="author_id:searchdropdown:=:sdTable=users:sdSelectColumn=name:sdValueColumn=id" \
  --style="status:==:draft:background=#F3F4F6:color=#6B7280" \
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

### Full Export

```bash
#!/bin/bash
# export-all-configs.sh

EXPORT_DIR="config/cruds"
mkdir -p $EXPORT_DIR

MODELS=(
  "App\Models\Product"
  "App\Models\Category"
  "App\Models\User"
  "App\Models\Order"
  "App\Models\Customer"
)

for MODEL in "${MODELS[@]}"; do
  FILENAME=$(echo $MODEL | sed 's/.*\\//' | tr '[:upper:]' '[:lower:]')
  php artisan ptah:config "$MODEL" --export="$EXPORT_DIR/$FILENAME.json"
  echo "✓ Exported $MODEL → $EXPORT_DIR/$FILENAME.json"
done

echo ""
echo "✓ All configurations exported!"
echo "Commit with: git add $EXPORT_DIR && git commit -m 'chore: export CRUD configs'"
```

### Full Import

```bash
#!/bin/bash
# import-all-configs.sh

IMPORT_DIR="config/cruds"

if [ ! -d "$IMPORT_DIR" ]; then
  echo "❌ Directory $IMPORT_DIR not found"
  exit 1
fi

for FILE in $IMPORT_DIR/*.json; do
  BASENAME=$(basename $FILE .json)
  MODEL="App\Models\$(echo $BASENAME | sed 's/^./\u&/')"
  
  php artisan ptah:config "$MODEL" --import="$FILE"
  echo "✓ Imported $FILE → $MODEL"
done

echo ""
echo "✓ All configurations imported!"
```

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
php artisan cache:forget "crud_config_Product"

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

# ❌ Wrong (pipe not escaped)
--column="email:text:validation=required|email"

# ✅ Correct (escaped pipe or single quotes)
--column="email:text:validation=required\|email"
--column='email:text:validation=required|email'
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

### Problem 7: Import does not overwrite existing config

**Symptoms:**
- Import executed but old config remains

**Cause:** Need to use `--force`

**Solution:**

```bash
php artisan ptah:config "App\Models\Product" --import=config/cruds/product.json --force
```

---

## Related Links

- [BaseCrud Documentation](BaseCrud.md) — Complete documentation for the BaseCrud component
- [Commands Documentation](Commands.md) — All Artisan commands for Ptah
- [Migration Guide](MigrationGuide.md) — Migration guide between versions

---

**Last updated:** March 4, 2026  
**Version:** 2.2.0

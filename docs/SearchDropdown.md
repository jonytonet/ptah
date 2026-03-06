# SearchDropdown — Complete Documentation

Ptah offers **two flavours** of SearchDropdown:

| Flavour | When to use |
|---|---|
| [Standalone component](#standalone-component) | In any view, outside BaseCrud |
| [Inline in BaseCrud](#inline-in-basecrud) | Form field or filter managed by BaseCrud |

---

## Table of Contents

1. [Standalone Component](#standalone-component)
   - [Available props](#available-props)
   - [Return event](#return-event)
   - [Via direct model](#via-direct-model)
   - [Via custom service](#via-custom-service-standalone)
   - [Format masks](#format-masks)
   - [Multiple labels](#multiple-labels)
   - [SearchDropdownDTO](#searchdropdowndto)
2. [Inline in BaseCrud](#inline-in-basecrud)
   - [Configuration keys](#configuration-keys)
   - [Via model](#via-model)
   - [Via custom service](#via-custom-service-basecrud)
   - [Filter in the side panel](#filter-in-the-side-panel)
   - [Internal flow](#internal-flow)
   - [Internal state (properties)](#internal-state-properties)
3. [Service return contract](#service-return-contract)
4. [Full examples](#full-examples)

---

## Standalone Component

The `ptah-search-dropdown` component is an independent Livewire component that can be used **anywhere in the project** — dashboards, custom forms, sidebars, etc.

```blade
@livewire('ptah-search-dropdown', [
    'model'   => 'Product',
    'label'   => 'name',
    'listens' => 'onProductSelected',
])
```

### Available props

#### Field configuration

| Prop | Type | Default | Description |
|---|---|---|---|
| `model` | `string` | `''` | Model name (e.g. `Product`) or sub-directory (`Purchase/Order`) |
| `value` | `string` | `'id'` | Column whose value is returned in the event |
| `label` | `string` | `'name'` | Column displayed as the main label |
| `labelTwo` | `string\|null` | `null` | Column displayed as the second label |
| `labelThree` | `string\|null` | `null` | Column displayed as the third label |
| `arraySearch` | `array` | `[]` | Extra columns included in the LIKE besides the labels |

#### Data and filters

| Prop | Type | Default | Description |
|---|---|---|---|
| `dataFilter` | `array` | `[]` | Extra WHERE filters: `[['col', 'op', 'val']]` or `['col' => 'val']` |
| `limit` | `int` | `10` | Limit of returned results |
| `orderByRaw` | `string` | `'id asc'` | Raw ORDER BY clause |

#### Custom service

| Prop | Type | Default | Description |
|---|---|---|---|
| `useService` | `string\|null` | `null` | Name of the method in the service to call. When set, uses service instead of direct model |

#### UI

| Prop | Type | Default | Description |
|---|---|---|---|
| `key` | `string` | `''` | Unique component key for `wire:key` |
| `placeholder` | `string` | `'Select'` | Input placeholder text |
| `startList` | `string` | `'bottom'` | List position: `'top'` or `'bottom'` |
| `initWithData` | `bool` | `true` | If `true`, loads items without needing to type |

#### Event

| Prop | Type | Default | Description |
|---|---|---|---|
| `listens` | `string` | `'searchDropdownResult'` | Name of the Livewire 4 event dispatched when an item is selected |
| `coringa` | `string` | `''` | Extra value passed in the event payload (useful to identify which SD fired) |

#### Masks

| Prop | Type | Default | Description |
|---|---|---|---|
| `maskOne` | `string` | `'defaultMask'` | Mask applied to `label` |
| `maskTwo` | `string` | `'defaultMask'` | Mask applied to `labelTwo` |
| `maskThree` | `string` | `'defaultMask'` | Mask applied to `labelThree` |

---

### Return event

When the user selects an item, the component dispatches:

```php
$this->dispatch($this->listens, [
    'useService' => $this->useService,   // string|null
    'value'      => $item[$this->value], // valor da coluna $value (normalmente o id)
    'label'      => $item[$this->label], // valor da coluna $label
    'searchTerm' => $this->searchTerm,   // texto exibido no input após seleção
    'coringa'    => $this->coringa,      // valor extra configurado no componente
]);
```

To capture in the parent component:

```php
use Livewire\Attributes\On;

#[On('onProductSelected')]
public function onProductSelected(array $data): void
{
    $this->productId   = $data['value'];
    $this->productName = $data['label'];
}
```

Or in JavaScript/Alpine:

```js
window.addEventListener('onProductSelected', (event) => {
    console.log(event.detail[0]); // { value, label, searchTerm, coringa, useService }
});
```

---

### Via direct model

The default mode. The component queries `App\Models\{model}` with `LOWER(label) LIKE ?`:

```blade
@livewire('ptah-search-dropdown', [
    'model'    => 'BusinessPartner',
    'value'    => 'id',
    'label'    => 'name',
    'listens'  => 'onPartnerSelected',
    'limit'    => 20,
    'orderByRaw' => 'name ASC',
])
```

**Sub-directory:**

```blade
@livewire('ptah-search-dropdown', [
    'model'   => 'Purchase/Supplier',  // → App\Models\Purchase\Supplier
    'label'   => 'trade_name',
    'listens' => 'onSupplierSelected',
])
```

**With extra filter (e.g. active only):**

```blade
@livewire('ptah-search-dropdown', [
    'model'      => 'Product',
    'label'      => 'name',
    'listens'    => 'onProductSelected',
    'dataFilter' => [['status', '=', 'active']],
])
```

---

### Via custom service (standalone)

When the search logic is more complex (JOINs, scopes, multi-tenant, business rules), use the **Interface → Repository → Service** architecture, which correctly separates responsibilities:

- **Repository** — the only layer that queries the database;
- **Service** — receives the Repository via injection, calls the query and processes/transforms the data;
- **SearchDropdown** — calls the Service via `useService`, without knowing anything about persistence.

#### 1. Interface (Contract)

```php
// app/Contracts/BusinessPartnerRepositoryInterface.php
namespace App\Contracts;

use Illuminate\Support\Collection;
use Ptah\DTO\SearchDropdownDTO;

interface BusinessPartnerRepositoryInterface
{
    public function searchDropDown(SearchDropdownDTO $dto): Collection;
}
```

#### 2. Repository (database access)

```php
// app/Repositories/BusinessPartnerRepository.php
namespace App\Repositories;

use App\Contracts\BusinessPartnerRepositoryInterface;
use App\Models\BusinessPartner;
use Illuminate\Support\Collection;
use Ptah\DTO\SearchDropdownDTO;

class BusinessPartnerRepository implements BusinessPartnerRepositoryInterface
{
    public function __construct(private readonly BusinessPartner $model) {}

    public function searchDropDown(SearchDropdownDTO $dto): Collection
    {
        $cols = array_filter([$dto->value, $dto->label, $dto->labelTwo]);

        $query = $this->model
            ->select(array_values($cols))
            ->where('active', true)
            ->whereHas('businessPartnerParams', function ($q) {
                $q->where('flag_purchase_trade', '!=', true);
            });

        if (!empty($dto->searchTerm)) {
            if (is_numeric($dto->searchTerm) && strlen($dto->searchTerm) <= 5) {
                $query->where($dto->value, $dto->searchTerm);
            } else {
                $query->where(function ($q) use ($dto) {
                    $q->where($dto->label, 'LIKE', "%{$dto->searchTerm}%")
                      ->orWhere($dto->value, $dto->searchTerm);

                    if ($dto->labelTwo) {
                        $q->orWhere($dto->labelTwo, 'LIKE', "%{$dto->searchTerm}%");
                    }
                });
            }
        }

        return $query
            ->orderByRaw($dto->orderByRaw)
            ->limit($dto->limit)
            ->get();
    }
}
```

#### 3. Service (business logic and data transformation)

```php
// app/Services/BusinessPartnerService.php
namespace App\Services;

use App\Contracts\BusinessPartnerRepositoryInterface;
use Illuminate\Support\Collection;
use Ptah\DTO\SearchDropdownDTO;

class BusinessPartnerService
{
    public function __construct(
        private readonly BusinessPartnerRepositoryInterface $repository
    ) {}

    /**
     * The service is responsible for transforming data before delivering
     * it to the component — here it strips the CNPJ mask (leaves only digits)
     * so the maskTwo='cnpj' prop formats it visually in the dropdown.
     */
    public function searchDropDown(SearchDropdownDTO $dto): Collection
    {
        return $this->repository
            ->searchDropDown($dto)
            ->map(fn ($partner) => [
                ...$partner->toArray(),
                'cnpj' => preg_replace('/\D/', '', (string) ($partner['cnpj'] ?? '')),
            ]);
    }
}
```

#### 4. Blade

```blade
@livewire('ptah-search-dropdown', [
    'model'      => 'BusinessPartner',   // resolves App\Services\BusinessPartnerService
    'useService' => 'searchDropDown',
    'value'      => 'id',
    'label'      => 'name',
    'labelTwo'   => 'cnpj',
    'maskTwo'    => 'cnpj',              // formats CNPJ digits on display
    'listens'    => 'onPartnerSelected',
])
```

#### 5. Bind no AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(
    \App\Contracts\BusinessPartnerRepositoryInterface::class,
    \App\Repositories\BusinessPartnerRepository::class,
);
```

> The Service is resolved via IoC with `app()->make('App\Services\BusinessPartnerService')`, so Repository injection happens automatically.

---

### Format masks

Applied when displaying labels in the results list:

| Mask | Format |
|---|---|
| `defaultMask` | No transformation |
| `cnpj` | `00.000.000/0000-00` |
| `cpf` | `000.000.000-00` |
| `money` | `R$ 1.234,56` |
| `phone` | `(11) 9 9999-9999` or `(11) 9999-9999` |
| `date` | `dd/mm/yyyy` (via `Carbon::parse`) |
| `App\Helpers\Masks::format` | Static call — `Class::method($value)` |
| `App\Services\MaskService@format` | IoC instance — `app(Class)->method($value)` |
| `nomeMetodo` | Public method on the component itself |

**Dynamic masks** allow using any formatting helper or service without relying on built-ins:

```blade
{{-- Static mask --}}
@livewire('ptah-search-dropdown', [
    'model'   => 'Supplier',
    'label'   => 'trade_name',
    'labelTwo' => 'cnpj_number',
    'maskTwo' => 'App\Helpers\DocumentMasks::cnpj',
    'listens' => 'onSupplierSelected',
])

{{-- IoC mask --}}
@livewire('ptah-search-dropdown', [
    'model'   => 'Product',
    'label'   => 'name',
    'labelTwo' => 'price',
    'maskTwo' => 'App\Services\CurrencyService@formatBrl',
    'listens' => 'onProductSelected',
])
```

---

### Multiple labels

Displays up to 3 columns in the list item — `value` always in bold, followed by the labels:

```
[id em negrito] - [label] - [labelTwo] - [labelThree]
```

```blade
@livewire('ptah-search-dropdown', [
    'model'      => 'Product',
    'value'      => 'id',
    'label'      => 'name',
    'labelTwo'   => 'sku',
    'labelThree' => 'sale_price',
    'maskThree'  => 'money',
    'listens'    => 'onProductSelected',
])
```

---

### SearchDropdownDTO

Structure passed to the service when `useService` is defined:

```php
namespace Ptah\DTO;

readonly class SearchDropdownDTO
{
    public function __construct(
        public ?string $searchTerm,   // Typed term (null = no filter)
        public string  $value,        // Value column (e.g. 'id')
        public string  $label,        // Main label column (e.g. 'name')
        public ?string $labelTwo   = null, // Second label column (optional)
        public ?string $labelThree = null, // Third label column (optional)
        public string  $orderByRaw = 'id asc',
        public int     $limit      = 10,
        public array   $arraySearch = [],
        public array   $dataFilter  = [],
    ) {}
}
```

---

## Inline in BaseCrud

SearchDropdown also works embedded in BaseCrud forms and the filter panel, managed by the `HasCrudSearchDropdown` trait.

No component instantiation is needed — just configure the column with `colsTipo: searchdropdown`.

### Configuration keys

#### Form column

| Key | Type | Default | Description |
|---|---|---|---|
| `colsSDModel` | `string` | — | Model or service class (e.g. `BusinessPartner`, `Product/ProductService/search`) |
| `colsSDLabel` | `string` | `'name'` | Column displayed as the label |
| `colsSDValor` | `string` | `'id'` | Column used as the value (saved in `formData`) |
| `colsSDOrder` | `string` | `'{label} ASC'` | Raw ORDER BY |
| `colsSDTipo` | `'model'\|'service'` | `'model'` | Search mode |
| `colsSDLimit` | `int` | `15` | Results limit |
| `colsSDMode` | `'create'\|'edit'\|'both'` | `'both'` | Which modal mode the field appears in |
| `colsSDLabelTwo` | `string\|null` | `null` | Second label column in the list item |
| `colsRelacao` | `string\|null` | `null` | Eloquent relation name used to pre-fill the label in edit mode |
| `colsRelacaoExibe` | `string\|null` | `null` | Relation attribute displayed in the input in edit mode |

#### Filter keys (side panel)

To use SD as a filter, define in the `customFilters` of CrudConfig:

```json
"customFilters": [
  {
    "field": "supplier_id",
    "colsFilterType": "searchdropdown",
    "colsSDModel": "Supplier",
    "colsSDLabel": "trade_name",
    "colsSDValor": "id"
  }
]
```

---

### Via model

```json
{
  "colsNomeFisico": "business_partner_id",
  "colsNomeLogico": "Business Partner",
  "colsTipo": "searchdropdown",
  "colsGravar": true,
  "colsSDModel": "BusinessPartner",
  "colsSDLabel": "name",
  "colsSDValor": "id",
  "colsSDOrder": "name ASC",
  "colsSDTipo": "model",
  "colsSDLimit": 15,
  "colsRelacao": "businessPartner",
  "colsRelacaoExibe": "name"
}
```

**`colsRelacao` + `colsRelacaoExibe`** cause the label to be pre-filled in **edit** mode: Ptah reads `$record->businessPartner->name` and displays it in the input without a new query.

---

### Via custom service (BaseCrud)

```json
{
  "colsNomeFisico": "product_id",
  "colsNomeLogico": "Product",
  "colsTipo": "searchdropdown",
  "colsGravar": true,
  "colsSDModel": "Product\\ProductService\\searchActive",
  "colsSDTipo": "service"
}
```

**Format of `colsSDModel` for service:**

```
{Namespace}\\{ClassName}\\{methodName}
```

Ptah extracts the last segment as the method and the rest as the class:

```
Product\\ProductService\\searchActive
→ class:  App\Services\Product\ProductService
→ method: searchActive($query)
```

The method receives `string $query` and **must return** `array<int, array{value: mixed, label: string}>`:

```php
namespace App\Services\Product;

class ProductService
{
    public function searchActive(string $query): array
    {
        return Product::active()
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(15)
            ->get()
            ->map(fn($p) => ['value' => $p->id, 'label' => $p->name])
            ->toArray();
    }
}
```

> **Note:** Ptah resolves the service via `app($class)`, so dependency injection works.

---

### Filter in the side panel

When `colsFilterType: searchdropdown` is configured, the filter field uses the same mechanism:

- **Input focus** → `openFilterDropdown($field)` → loads the first N items
- **Typing** → `filterSearchDropdown($field, $query)` → filters in real time
- **Selection** → `selectFilterDropdownOption($field, $value, $label)` → stores in `$filters[$field]` with operator `=`
- **Clear** → `clearFilterDropdownSelection($field)` → removes from `$filters` and resets pagination

---

### Internal flow

#### Form (create/edit modal)

```
Input focus
  → openDropdown($field)
  → sdResults[$field] = first N items (no filter)

Typing (debounce 300ms)
  → searchDropdown($field, $query)
  → sdResults[$field] = items filtered by LOWER(label) LIKE ?

Item selection
  → selectDropdownOption($field, $value, $label)
  → formData[$field]   = value       ← saved in formData
  → sdLabels[$field]   = label       ← label displayed in the input
  → sdResults[$field]  = []          ← closes the dropdown
  → sdSearches[$field] = ''
```

#### Filter in the side panel

```
Input focus
  → openFilterDropdown($field)
  → sdResults['filter_' . $field] = first N items

Typing
  → filterSearchDropdown($field, $query)
  → sdResults['filter_' . $field] = filtered items

Item selection
  → selectFilterDropdownOption($field, $value, $label)
  → filters[$field]              = value   ← activates the filter in the query
  → filterOperators[$field]      = '='
  → sdFilterLabels[$field]       = label
  → sdResults['filter_' . $field] = []

Clear
  → clearFilterDropdownSelection($field)
  → removes filters[$field], filterOperators[$field], sdFilterLabels[$field]
  → sdResults['filter_' . $field] = []
```

---

### Internal state (properties)

Properties maintained in the BaseCrud component by the `HasCrudSearchDropdown` trait:

| Property | Type | Description |
|---|---|---|
| `$sdResults` | `array` | Results indexed by `$field` (form) or `'filter_' . $field` (filter) |
| `$sdLabels` | `array` | Selected label per field in the form — displayed in the input after selection |
| `$sdSearches` | `array` | Typed term per field (preserved when the dropdown is reopened) |
| `$sdFilterLabels` | `array` | Selected label per field in the filter panel |

---

## Service return contract

The service can return an `array` **or** an `Illuminate\Support\Collection` — the component normalises automatically:

```php
// Return as array (always accepted)
return [
    ['value' => 1, 'label' => 'Product A'],
    ['value' => 2, 'label' => 'Product B'],
];

// Return as Collection (also accepted)
return $this->repository->searchDropDown($dto); // Collection of models or arrays
```

Minimum required keys per item:

| Key | Type | Description |
|---|---|---|
| `value` | `mixed` | Value saved in `formData` / event payload (usually the `id`) |
| `label` | `string` | Text displayed in the input after selection |

Additional columns for `labelTwo`/`labelThree` must be present in the return by their real column name:

```php
// Item with labelTwo='cnpj' needs the 'cnpj' key in the array
['value' => 1, 'label' => 'Acme Ltda', 'cnpj' => '12345678000190']
```

> If the return does not contain the key expected by `labelTwo`/`labelThree`, the component displays an empty string in that slot — no error.

---

## Full examples

### 1. Standalone simple — search client

```blade
{{-- View --}}
@livewire('ptah-search-dropdown', [
    'key'         => 'client-sd',
    'model'       => 'Client',
    'label'       => 'name',
    'listens'     => 'onClientSelected',
    'placeholder' => 'Search client...',
])
```

```php
// Parent Livewire component
use Livewire\Attributes\On;

#[On('onClientSelected')]
public function onClientSelected(array $data): void
{
    $this->clientId   = $data['value'];
    $this->clientName = $data['label'];
}
```

---

### 2. Standalone with service and multiple labels

See the full SOLID example in the [Via custom service](#via-custom-service-standalone) section — it uses `BusinessPartner` with `labelTwo='cnpj'` and `maskTwo='cnpj'`.

Abbreviated example of how to use it in Blade after configuring Interface + Repository + Service:

```blade
@livewire('ptah-search-dropdown', [
    'key'        => 'supplier-sd',
    'model'      => 'BusinessPartner',
    'useService' => 'searchDropDown',
    'value'      => 'id',
    'label'      => 'name',
    'labelTwo'   => 'cnpj',
    'maskTwo'    => 'cnpj',
    'listens'    => 'onSupplierSelected',
    'coringa'    => 'purchase-form',
])
```

> **Note:** To display `cnpj` via `labelTwo`, the array/Collection returned by the Service must include the `cnpj` key in each item.

---

### 3. BaseCrud — form field via model

```json
{
  "colsNomeFisico":    "category_id",
  "colsNomeLogico":    "Category",
  "colsTipo":          "searchdropdown",
  "colsGravar":        true,
  "colsSDModel":       "Category",
  "colsSDLabel":       "name",
  "colsSDValor":       "id",
  "colsSDOrder":       "name ASC",
  "colsSDTipo":        "model",
  "colsSDLimit":       20,
  "colsRelacao":       "category",
  "colsRelacaoExibe":  "name"
}
```

---

### 4. BaseCrud — form field via service

```json
{
  "colsNomeFisico": "product_id",
  "colsNomeLogico": "Product",
  "colsTipo":       "searchdropdown",
  "colsGravar":     true,
  "colsSDModel":    "Inventory\\ProductService\\searchAvailable",
  "colsSDTipo":     "service",
  "colsRelacao":    "product",
  "colsRelacaoExibe": "name"
}
```

```php
// App\Services\Inventory\ProductService
public function searchAvailable(string $query): array
{
    return Product::available()
        ->where('company_id', session('company_id'))
        ->when($query, fn($q) => $q->where('name', 'LIKE', "%{$query}%"))
        ->limit(15)
        ->get()
        ->map(fn($p) => ['value' => $p->id, 'label' => $p->name])
        ->toArray();
}
```

---

### 5. BaseCrud — filter in the side panel via service

```json
"customFilters": [
  {
    "field":           "supplier_id",
    "colsNomeLogico":  "Supplier",
    "colsFilterType":  "searchdropdown",
    "colsSDModel":     "Supplier\\SupplierService\\searchActive",
    "colsSDTipo":      "service",
    "colsSDLabel":     "trade_name",
    "colsSDValor":     "id"
  }
]
```

---

> 📄 See also: [BaseCrud.md](BaseCrud.md) · [Configuration.md](Configuration.md) · [Commands.md](Commands.md)

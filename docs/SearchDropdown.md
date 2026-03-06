# SearchDropdown — Documentação Completa

O Ptah oferece **dois sabores** de SearchDropdown:

| Sabor | Quando usar |
|---|---|
| [Componente standalone](#componente-standalone) | Em qualquer view, fora do BaseCrud |
| [Inline no BaseCrud](#inline-no-basecrud) | Campo em formulário ou filtro gerenciado pelo BaseCrud |

---

## Índice

1. [Componente Standalone](#componente-standalone)
   - [Props disponíveis](#props-disponíveis)
   - [Evento de retorno](#evento-de-retorno)
   - [Via model direto](#via-model-direto)
   - [Via service personalizado](#via-service-personalizado-standalone)
   - [Máscaras de formatação](#máscaras-de-formatação)
   - [Múltiplos labels](#múltiplos-labels)
   - [SearchDropdownDTO](#searchdropdowndto)
2. [Inline no BaseCrud](#inline-no-basecrud)
   - [Chaves de configuração](#chaves-de-configuração)
   - [Via model](#via-model)
   - [Via service personalizado](#via-service-personalizado-basecrud)
   - [Filtro no painel lateral](#filtro-no-painel-lateral)
   - [Fluxo interno](#fluxo-interno)
   - [Estado interno (propriedades)](#estado-interno-propriedades)
3. [Contrato de retorno do service](#contrato-de-retorno-do-service)
4. [Exemplos completos](#exemplos-completos)

---

## Componente Standalone

O componente `ptah-search-dropdown` é um Livewire component independente que pode ser usado **em qualquer parte do projeto** — dashboards, formulários customizados, sidebars, etc.

```blade
@livewire('ptah-search-dropdown', [
    'model'   => 'Product',
    'label'   => 'name',
    'listens' => 'onProductSelected',
])
```

### Props disponíveis

#### Configuração do campo

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `model` | `string` | `''` | Nome do model (ex: `Product`) ou subdiretório (`Purchase/Order`) |
| `value` | `string` | `'id'` | Coluna cujo valor é retornado no evento |
| `label` | `string` | `'name'` | Coluna exibida como label principal |
| `labelSecondary` | `string\|null` | `null` | Coluna exibida como segundo label |
| `labelLast` | `string\|null` | `null` | Coluna exibida como terceiro label |
| `arraySearch` | `array` | `[]` | Colunas extras incluídas no LIKE além dos labels |

#### Dados e filtros

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `dataFilter` | `array` | `[]` | Filtros WHERE extras: `[['col', 'op', 'val']]` ou `['col' => 'val']` |
| `limit` | `int` | `10` | Limite de resultados retornados |
| `orderByRaw` | `string` | `'id asc'` | Cláusula ORDER BY raw |

#### Service personalizado

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `useService` | `string\|null` | `null` | Nome do método no service a ser chamado. Quando definido, usa service em vez de model direto |

#### UI

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `key` | `string` | `''` | Chave única do componente para `wire:key` |
| `placeholder` | `string` | `'Select'` | Texto placeholder do input |
| `startList` | `string` | `'bottom'` | Posição da lista: `'top'` ou `'bottom'` |
| `initWithData` | `bool` | `true` | Se `true`, carrega itens sem precisar digitar |

#### Evento

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `listens` | `string` | `'searchDropdownResult'` | Nome do evento Livewire 3 disparado ao selecionar um item |
| `coringa` | `string` | `''` | Valor extra passado no payload do evento (útil para identificar qual SD disparou) |

#### Máscaras

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `maskLabel` | `string` | `'defaultMask'` | Máscara aplicada ao `label` |
| `maskSecondary` | `string` | `'defaultMask'` | Máscara aplicada ao `labelSecondary` |
| `maskLast` | `string` | `'defaultMask'` | Máscara aplicada ao `labelLast` |

---

### Evento de retorno

Quando o usuário seleciona um item, o componente dispara:

```php
$this->dispatch($this->listens, [
    'useService' => $this->useService,   // string|null
    'value'      => $item[$this->value], // valor da coluna $value (normalmente o id)
    'label'      => $item[$this->label], // valor da coluna $label
    'searchTerm' => $this->searchTerm,   // texto exibido no input após seleção
    'coringa'    => $this->coringa,      // valor extra configurado no componente
]);
```

Para capturar no componente pai:

```php
use Livewire\Attributes\On;

#[On('onProductSelected')]
public function onProductSelected(array $data): void
{
    $this->productId   = $data['value'];
    $this->productName = $data['label'];
}
```

Ou em JavaScript/Alpine:

```js
window.addEventListener('onProductSelected', (event) => {
    console.log(event.detail[0]); // { value, label, searchTerm, coringa, useService }
});
```

---

### Via model direto

O modo padrão. O componente consulta `App\Models\{model}` com `LOWER(label) LIKE ?`:

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

**Subdiretório:**

```blade
@livewire('ptah-search-dropdown', [
    'model'   => 'Purchase/Supplier',  // → App\Models\Purchase\Supplier
    'label'   => 'trade_name',
    'listens' => 'onSupplierSelected',
])
```

**Com filtro extra (ex: apenas ativos):**

```blade
@livewire('ptah-search-dropdown', [
    'model'      => 'Product',
    'label'      => 'name',
    'listens'    => 'onProductSelected',
    'dataFilter' => [['status', '=', 'active']],
])
```

---

### Via service personalizado (standalone)

Quando a lógica de busca é mais complexa (JOINs, escopos, multi-tenant), crie um service e passe `useService`:

```blade
@livewire('ptah-search-dropdown', [
    'model'      => 'Product',        // define qual service resolver (App\Services\ProductService)
    'useService' => 'searchActive',   // método a ser chamado no service
    'label'      => 'name',
    'listens'    => 'onProductSelected',
])
```

O service recebe um `SearchDropdownDTO` e deve retornar `array`:

```php
namespace App\Services;

use Ptah\DTO\SearchDropdownDTO;

class ProductService
{
    public function searchActive(SearchDropdownDTO $dto): array
    {
        return Product::query()
            ->where('status', 'active')
            ->where('company_id', session('company_id'))
            ->when($dto->searchTerm, fn($q) => $q->where('name', 'LIKE', "%{$dto->searchTerm}%"))
            ->orderByRaw($dto->orderByRaw)
            ->limit($dto->limit)
            ->get()
            ->map(fn($p) => [
                'value' => $p->{$dto->value},
                'label' => $p->{$dto->label},
            ])
            ->toArray();
    }
}
```

> O service é resolvido via IoC com `app()->make('App\Services\ProductService')`, então injeção de dependência funciona normalmente.

---

### Máscaras de formatação

Aplicadas ao exibir os labels na lista de resultados:

| Máscara | Formato |
|---|---|
| `defaultMask` | Sem transformação |
| `cnpj` | `00.000.000/0000-00` |
| `cpf` | `000.000.000-00` |
| `money` | `R$ 1.234,56` |
| `phone` | `(11) 9 9999-9999` ou `(11) 9999-9999` |
| `date` | `dd/mm/yyyy` (via `Carbon::parse`) |

```blade
@livewire('ptah-search-dropdown', [
    'model'          => 'Supplier',
    'value'          => 'id',
    'label'          => 'trade_name',
    'labelSecondary' => 'cnpj_number',
    'maskSecondary'  => 'cnpj',
    'listens'        => 'onSupplierSelected',
])
```

---

### Múltiplos labels

Exibe até 3 colunas no item da lista — `value` sempre em negrito, seguido pelos labels:

```
[id em negrito] - [label] - [labelSecondary] - [labelLast]
```

```blade
@livewire('ptah-search-dropdown', [
    'model'          => 'Product',
    'value'          => 'id',
    'label'          => 'name',
    'labelSecondary' => 'sku',
    'labelLast'      => 'sale_price',
    'maskLast'       => 'money',
    'listens'        => 'onProductSelected',
])
```

---

### SearchDropdownDTO

Estrutura passada ao service quando `useService` está definido:

```php
namespace Ptah\DTO;

readonly class SearchDropdownDTO
{
    public function __construct(
        public ?string $searchTerm,      // Termo digitado (null = sem filtro)
        public string  $value,           // Coluna do valor (ex: 'id')
        public string  $label,           // Coluna do label principal (ex: 'name')
        public ?string $labelSecondary,  // Segunda coluna de label
        public ?string $labelLast,       // Terceira coluna de label
        public string  $orderByRaw,      // ORDER BY raw (ex: 'name ASC')
        public int     $limit,           // Máximo de resultados
        public array   $arraySearch,     // Colunas extras para LIKE
        public array   $dataFilter,      // Filtros WHERE adicionais
    ) {}
}
```

---

## Inline no BaseCrud

O SearchDropdown também funciona embutido nos formulários e no painel de filtros do BaseCrud, gerenciado pelo trait `HasCrudSearchDropdown`.

Não é necessário instanciar nenhum componente — basta configurar a coluna com `colsTipo: searchdropdown`.

### Chaves de configuração

#### Coluna de formulário

| Chave | Tipo | Padrão | Descrição |
|---|---|---|---|
| `colsSDModel` | `string` | — | Model ou service class (ex: `BusinessPartner`, `Product/ProductService/search`) |
| `colsSDLabel` | `string` | `'name'` | Coluna exibida como label |
| `colsSDValor` | `string` | `'id'` | Coluna usada como valor (salvo em `formData`) |
| `colsSDOrder` | `string` | `'{label} ASC'` | ORDER BY raw |
| `colsSDTipo` | `'model'\|'service'` | `'model'` | Modo de busca |
| `colsSDLimit` | `int` | `15` | Limite de resultados |
| `colsSDMode` | `'create'\|'edit'\|'both'` | `'both'` | Em qual modo do modal o campo aparece |
| `colsSDLabelSecondary` | `string\|null` | `null` | Segunda coluna de label no item da lista |
| `colsRelacao` | `string\|null` | `null` | Nome da relação Eloquent usada para pré-preencher o label no modo edição |
| `colsRelacaoExibe` | `string\|null` | `null` | Atributo da relação exibido no input no modo edição |

#### Chaves de filtro (painel lateral)

Para usar SD como filtro, defina no `customFilters` do CrudConfig:

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
  "colsNomeLogico": "Parceiro",
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

**`colsRelacao` + `colsRelacaoExibe`** fazem o label ser pré-preenchido no modo **edição**: o Ptah lê `$record->businessPartner->name` e exibe no input sem precisar de nova consulta.

---

### Via service personalizado (BaseCrud)

```json
{
  "colsNomeFisico": "product_id",
  "colsNomeLogico": "Produto",
  "colsTipo": "searchdropdown",
  "colsGravar": true,
  "colsSDModel": "Product\\ProductService\\searchActive",
  "colsSDTipo": "service"
}
```

**Formato do `colsSDModel` para service:**

```
{Namespace}\\{ClassName}\\{methodName}
```

O Ptah extrai a última parte como método e o restante como classe:

```
Product\\ProductService\\searchActive
→ class:  App\Services\Product\ProductService
→ método: searchActive($query)
```

O método recebe `string $query` e **deve retornar** `array<int, array{value: mixed, label: string}>`:

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

> **Nota:** O Ptah resolve o service via `app($class)`, então injeção de dependência funciona.

---

### Filtro no painel lateral

Quando `colsFilterType: searchdropdown` está configurado, o campo de filtro usa o mesmo mecanismo:

- **Foco no input** → `openFilterDropdown($field)` → carrega os N primeiros itens
- **Digitação** → `filterSearchDropdown($field, $query)` → filtra em tempo real
- **Seleção** → `selectFilterDropdownOption($field, $value, $label)` → armazena em `$filters[$field]` com operador `=`
- **Limpar** → `clearFilterDropdownSelection($field)` → remove de `$filters` e reseta a paginação

---

### Fluxo interno

#### Formulário (modal create/edit)

```
Foco no input
  → openDropdown($field)
  → sdResults[$field] = primeiros N itens (sem filtro)

Digitação (debounce 300ms)
  → searchDropdown($field, $query)
  → sdResults[$field] = itens filtrados por LOWER(label) LIKE ?

Seleção de item
  → selectDropdownOption($field, $value, $label)
  → formData[$field]   = value       ← salvo no formData
  → sdLabels[$field]   = label       ← label exibido no input
  → sdResults[$field]  = []          ← fecha o dropdown
  → sdSearches[$field] = ''
```

#### Filtro no painel lateral

```
Foco no input
  → openFilterDropdown($field)
  → sdResults['filter_' . $field] = primeiros N itens

Digitação
  → filterSearchDropdown($field, $query)
  → sdResults['filter_' . $field] = itens filtrados

Seleção de item
  → selectFilterDropdownOption($field, $value, $label)
  → filters[$field]              = value   ← ativa o filtro na query
  → filterOperators[$field]      = '='
  → sdFilterLabels[$field]       = label
  → sdResults['filter_' . $field] = []

Limpar
  → clearFilterDropdownSelection($field)
  → remove filters[$field], filterOperators[$field], sdFilterLabels[$field]
  → sdResults['filter_' . $field] = []
```

---

### Estado interno (propriedades)

Propriedades mantidas no componente BaseCrud pelo trait `HasCrudSearchDropdown`:

| Propriedade | Tipo | Descrição |
|---|---|---|
| `$sdResults` | `array` | Resultados indexados por `$field` (formulário) ou `'filter_' . $field` (filtro) |
| `$sdLabels` | `array` | Label selecionado por campo no formulário — exibido no input após seleção |
| `$sdSearches` | `array` | Termo digitado por campo (preservado ao reabrir o dropdown) |
| `$sdFilterLabels` | `array` | Label selecionado por campo no painel de filtros |

---

## Contrato de retorno do service

Independente de qual sabor você usa, qualquer **service** deve retornar um array no formato:

```php
return [
    ['value' => 1, 'label' => 'Produto A'],
    ['value' => 2, 'label' => 'Produto B'],
    // ...
];
```

| Chave | Tipo | Descrição |
|---|---|---|
| `value` | `mixed` | Valor salvo no formData / event (normalmente o `id`) |
| `label` | `string` | Texto exibido no input após seleção |

> Se o retorno não for um array ou contiver estrutura diferente, o SD silencionamente exibe a lista vazia.

---

## Exemplos completos

### 1. Standalone simples — buscar cliente

```blade
{{-- View --}}
@livewire('ptah-search-dropdown', [
    'key'      => 'client-sd',
    'model'    => 'Client',
    'label'    => 'name',
    'listens'  => 'onClientSelected',
    'placeholder' => 'Buscar cliente...',
])
```

```php
// Componente Livewire pai
use Livewire\Attributes\On;

#[On('onClientSelected')]
public function onClientSelected(array $data): void
{
    $this->clientId   = $data['value'];
    $this->clientName = $data['label'];
}
```

---

### 2. Standalone com serviço e múltiplos labels

```blade
@livewire('ptah-search-dropdown', [
    'key'            => 'supplier-sd',
    'model'          => 'Supplier',
    'useService'     => 'searchWithCnpj',
    'value'          => 'id',
    'label'          => 'trade_name',
    'labelSecondary' => 'cnpj',
    'maskSecondary'  => 'cnpj',
    'listens'        => 'onSupplierSelected',
    'coringa'        => 'purchase-form',
])
```

```php
// App\Services\SupplierService
public function searchWithCnpj(SearchDropdownDTO $dto): array
{
    return Supplier::active()
        ->when($dto->searchTerm, fn($q) => $q->where(function ($inner) use ($dto) {
            $inner->where('trade_name', 'LIKE', "%{$dto->searchTerm}%")
                  ->orWhere('cnpj', 'LIKE', "%{$dto->searchTerm}%");
        }))
        ->limit($dto->limit)
        ->get()
        ->map(fn($s) => ['value' => $s->id, 'label' => $s->trade_name, 'cnpj' => $s->cnpj])
        ->toArray();
}
```

> **Nota:** Para exibir `cnpj` via `labelSecondary`, inclua o campo no array retornado além de `value` e `label`.

---

### 3. BaseCrud — campo de formulário via model

```json
{
  "colsNomeFisico":    "category_id",
  "colsNomeLogico":    "Categoria",
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

### 4. BaseCrud — campo de formulário via service

```json
{
  "colsNomeFisico": "product_id",
  "colsNomeLogico": "Produto",
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

### 5. BaseCrud — filtro no painel lateral via service

```json
"customFilters": [
  {
    "field":           "supplier_id",
    "colsNomeLogico":  "Fornecedor",
    "colsFilterType":  "searchdropdown",
    "colsSDModel":     "Supplier\\SupplierService\\searchActive",
    "colsSDTipo":      "service",
    "colsSDLabel":     "trade_name",
    "colsSDValor":     "id"
  }
]
```

---

> 📄 Ver também: [BaseCrud.md](BaseCrud.md) · [Configuration.md](Configuration.md) · [Commands.md](Commands.md)

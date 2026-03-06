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
| `labelTwo` | `string\|null` | `null` | Coluna exibida como segundo label |
| `labelThree` | `string\|null` | `null` | Coluna exibida como terceiro label |
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
| `listens` | `string` | `'searchDropdownResult'` | Nome do evento Livewire 4 disparado ao selecionar um item |
| `coringa` | `string` | `''` | Valor extra passado no payload do evento (útil para identificar qual SD disparou) |

#### Máscaras

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `maskOne` | `string` | `'defaultMask'` | Máscara aplicada ao `label` |
| `maskTwo` | `string` | `'defaultMask'` | Máscara aplicada ao `labelTwo` |
| `maskThree` | `string` | `'defaultMask'` | Máscara aplicada ao `labelThree` |

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

Quando a lógica de busca é mais complexa (JOINs, escopos, multi-tenant, regras de negócio), use a arquitetura **Interface → Repository → Service** que separa as responsabilidades corretamente:

- **Repository** — única camada que faz queries ao banco;
- **Service** — recebe o Repository via injeção, chama a query e trata/transforma os dados;
- **SearchDropdown** — chama o Service via `useService`, sem saber nada sobre persistência.

#### 1. Interface (Contrato)

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

#### 2. Repository (acesso ao banco)

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

#### 3. Service (lógica de negócio e transformação de dados)

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
     * O service é responsável por transformar os dados antes de entregar
     * ao componente — aqui remove a máscara do CNPJ (deixa só dígitos)
     * para que a prop maskTwo='cnpj' formate visualmente no dropdown.
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
    'model'      => 'BusinessPartner',   // resolve App\Services\BusinessPartnerService
    'useService' => 'searchDropDown',
    'value'      => 'id',
    'label'      => 'name',
    'labelTwo'   => 'cnpj',
    'maskTwo'    => 'cnpj',              // formata os dígitos do CNPJ na exibição
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

> O Service é resolvido via IoC com `app()->make('App\Services\BusinessPartnerService')`, portanto a injeção do Repository acontece automaticamente.

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
| `App\Helpers\Masks::format` | Chamada estática — `Class::method($value)` |
| `App\Services\MaskService@format` | Instância via IoC — `app(Class)->method($value)` |
| `nomeMetodo` | Método público do próprio componente |

**Máscaras dinâmicas** permitem usar qualquer helper ou service de formatação sem depender dos built-ins:

```blade
{{-- Máscara estática --}}
@livewire('ptah-search-dropdown', [
    'model'   => 'Supplier',
    'label'   => 'trade_name',
    'labelTwo' => 'cnpj_number',
    'maskTwo' => 'App\Helpers\DocumentMasks::cnpj',
    'listens' => 'onSupplierSelected',
])

{{-- Máscara via IoC --}}
@livewire('ptah-search-dropdown', [
    'model'   => 'Product',
    'label'   => 'name',
    'labelTwo' => 'price',
    'maskTwo' => 'App\Services\CurrencyService@formatBrl',
    'listens' => 'onProductSelected',
])
```

---

### Múltiplos labels

Exibe até 3 colunas no item da lista — `value` sempre em negrito, seguido pelos labels:

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

Estrutura passada ao service quando `useService` está definido:

```php
namespace Ptah\DTO;

readonly class SearchDropdownDTO
{
    public function __construct(
        public ?string $searchTerm,   // Termo digitado (null = sem filtro)
        public string  $value,        // Coluna do valor (ex: 'id')
        public string  $label,        // Coluna do label principal (ex: 'name')
        public ?string $labelTwo   = null, // Segunda coluna de label (opcional)
        public ?string $labelThree = null, // Terceira coluna de label (opcional)
        public string  $orderByRaw = 'id asc',
        public int     $limit      = 10,
        public array   $arraySearch = [],
        public array   $dataFilter  = [],
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
| `colsSDLabelTwo` | `string\|null` | `null` | Segunda coluna de label no item da lista |
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

O service pode retornar `array` **ou** um `Illuminate\Support\Collection` — o componente normaliza automaticamente:

```php
// Retorno como array (sempre aceito)
return [
    ['value' => 1, 'label' => 'Produto A'],
    ['value' => 2, 'label' => 'Produto B'],
];

// Retorno como Collection (também aceito)
return $this->repository->searchDropDown($dto); // Collection de models ou arrays
```

As chaves mínimas obrigatórias no cada item:

| Chave | Tipo | Descrição |
|---|---|---|
| `value` | `mixed` | Valor gravado no `formData` / payload do evento (normalmente o `id`) |
| `label` | `string` | Texto exibido no input após seleção |

Colunas adicionais para `labelTwo`/`labelThree` devem estar presentes no retorno pelo nome real da coluna:

```php
// Item com labelTwo='cnpj' precisa da chave 'cnpj' no array
['value' => 1, 'label' => 'Acme Ltda', 'cnpj' => '12345678000190']
```

> Se o retorno não contiver a chave esperada por `labelTwo`/`labelThree`, o componente exibe string vazia naquele slot — sem erro.

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

Veja o exemplo SOLID completo na seção [Via service personalizado](#via-service-personalizado-standalone) — ele usa `BusinessPartner` com `labelTwo='cnpj'` e `maskTwo='cnpj'`.

Exemplo resumido de como usar no Blade após configurar Interface + Repository + Service:

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

> **Nota:** Para exibir `cnpj` via `labelTwo`, o array/Collection retornado pelo Service deve incluir a chave `cnpj` em cada item.

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

# BaseCrud — Documentação Completa

**Pacote:** `jonytonet/ptah`  
**Namespace:** `Ptah\Livewire\BaseCrud`  
**Livewire:** 3.x  
**Laravel:** 11+

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Uso Básico](#uso-básico)
3. [Parâmetros de Inicialização](#parâmetros-de-inicialização)
4. [Propriedades Públicas](#propriedades-públicas)
5. [Métodos Públicos](#métodos-públicos)
6. [CrudConfig — Estrutura de Colunas](#crudconfig--estrutura-de-colunas)
7. [Tipos de Coluna](#tipos-de-coluna)
8. [Helpers de Formatação de Célula](#helpers-de-formatação-de-célula)
9. [Renderer DSL](#renderer-dsl)
10. [Estilos Condicionais de Linha](#estilos-condicionais-de-linha)
11. [Filtros](#filtros)
12. [Filtros Rápidos de Data](#filtros-rápidos-de-data)
13. [Busca Avançada](#busca-avançada)
14. [Visibilidade de Colunas](#visibilidade-de-colunas)
15. [Bulk Actions](#bulk-actions)
16. [SearchDropdown em Formulários](#searchdropdown-em-formulários)
17. [WhereHas — Filtro por Entidade Pai](#wherehas--filtro-por-entidade-pai)
18. [Multi-tenant (companyFilter)](#multi-tenant-companyfilter)
19. [Totalizadores](#totalizadores)
20. [Exportação](#exportação)
21. [Preferências de Usuário (V2.1)](#preferências-de-usuário-v21)
22. [Eventos Livewire](#eventos-livewire)
23. [Permissões](#permissões)
24. [Error Recovery](#error-recovery)
25. [CrudConfig Modal](#crudconfig-modal)
26. [FormValidatorService](#formvalidatorservice)
27. [Fluxo Interno Simplificado](#fluxo-interno-simplificado)

---

## Visão Geral

`BaseCrud` é um componente Livewire 3 que gera uma tela completa de CRUD com:

- Tabela dinâmica com sort, paginação e filtros
- Modal de criação/edição com validação
- Soft delete e restauração
- Visibilidade de colunas por usuário
- Busca global com OR em relações
- Filtros rápidos de período (hoje/semana/mês/trimestre/ano)
- Busca avançada com múltiplos critérios e lógica AND/OR
- Bulk actions (seleção múltipla, exclusão, exportação, ações customizadas)
- SearchDropdown integrado nos formulários
- Filtro por entidade pai via `whereHas`
- Totalizadores (sum/count/avg/max/min)
- Exportação síncrona e assíncrona
- Preferências persistidas por usuário (V2.1)
- Error recovery automático (limpa preferências corrompidas)
- Cache com invalidação por model

---

## Uso Básico

```blade
{{-- Mínimo obrigatório --}}
@livewire('ptah::base-crud', ['model' => 'Product'])

{{-- Com parâmetros avançados --}}
@livewire('ptah::base-crud', [
    'model'            => 'Product',
    'initialFilter'    => [['status', '=', 'active']],
    'whereHasFilter'   => 'category',
    'whereHasCondition'=> ['id', '=', 5],
    'companyFilter'    => 1,
])
```

O `model` pode incluir subdiretórios separados por `/`:

```blade
@livewire('ptah::base-crud', ['model' => 'Purchase/Order/PurchaseOrders'])
```

Isso resolve para `App\Models\Purchase\Order\PurchaseOrders`.

---

## Parâmetros de Inicialização

Passados ao `@livewire(...)` ou `<livewire ...>`.

| Parâmetro | Tipo | Padrão | Descrição |
|---|---|---|---|
| `model` | `string` | — | **Obrigatório.** Identificador do model |
| `initialFilter` | `array` | `[]` | Filtros iniciais: `[['campo', 'op', 'valor'], ...]` |
| `whereHasFilter` | `string` | `''` | Nome da relação para pré-filtrar |
| `whereHasCondition` | `array` | `[]` | Condição da relação: `['campo', 'op', 'valor']` |
| `companyFilter` | `int` | `session('company_id', 0)` | ID da empresa para filtro multi-tenant |

---

## Propriedades Públicas

Todas as propriedades públicas são acessíveis na view via `$this->` ou diretamente no template Blade.

### Estado da tabela

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$model` | `string` | `''` | Identificador do model |
| `$crudConfig` | `array` | `[]` | Configuração completa do CrudConfig |
| `$sort` | `string` | `'id'` | Coluna de ordenação |
| `$direction` | `string` | `'DESC'` | Direção: `ASC` ou `DESC` |
| `$perPage` | `int` | `25` | Registros por página |
| `$search` | `string` | `''` | Termo de busca global |
| `$showTrashed` | `bool` | `false` | Exibe registros soft-deletados |
| `$trashedCount` | `int` | `0` | Quantidade de registros na lixeira |
| `$showFilters` | `bool` | `false` | Painel de filtros visível |

### Filtros

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$filters` | `array` | `[]` | Valores dos filtros: `[campo => valor]` |
| `$dateRanges` | `array` | `[]` | Date ranges: `[campo_start => data, campo_end => data]` |
| `$savedFilters` | `array` | `[]` | Filtros salvos com nome |
| `$savingFilterName` | `?string` | `null` | Nome sendo editado ao salvar filtro |
| `$textFilter` | `array` | `[]` | Badges de filtros ativos: `[{label, field, value}]` |
| `$quickDateFilter` | `string` | `''` | Período ativo: `today\|week\|month\|quarter\|year` |
| `$quickDateColumn` | `string` | `'created_at'` | Coluna de data para o filtro rápido |

### Busca avançada

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$advancedSearchActive` | `bool` | `false` | Modo busca avançada ativo |
| `$advancedSearchFields` | `array` | `[]` | Critérios: `[{field, operator, value, logic}]` |
| `$searchHistory` | `array` | `[]` | Últimas 10 buscas globais |

### Visibilidade de colunas

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$formDataColumns` | `array` | `[]` | Mapa `[campo => bool]` de colunas visíveis |
| `$hiddenColumnsCount` | `int` | `0` | Contador de colunas ocultas |

### Bulk actions

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$selectedRows` | `array` | `[]` | IDs das linhas selecionadas (string[]) |
| `$selectAll` | `bool` | `false` | Todos selecionados |
| `$bulkActionInProgress` | `bool` | `false` | Ação bulk em execução |
| `$showBulkActions` | `bool` | `false` | Área de bulk actions visível |

### Modal de criação/edição

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$formData` | `array` | `[]` | Dados do formulário |
| `$editingId` | `?int` | `null` | ID do registro sendo editado |
| `$showModal` | `bool` | `false` | Modal visível |
| `$creating` | `bool` | `false` | Salvamento em andamento |
| `$formErrors` | `array` | `[]` | Erros de validação |

### Exclusão

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$showDeleteConfirm` | `bool` | `false` | Confirmação de exclusão visível |
| `$deletingId` | `?int` | `null` | ID do registro a ser excluído |

### SearchDropdown no formulário

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$sdSearches` | `array` | `[]` | Termos de busca: `[campo => query]` |
| `$sdResults` | `array` | `[]` | Resultados: `[campo => [{value, label}]]` |
| `$sdLabels` | `array` | `[]` | Labels exibidos: `[campo => label]` |

### Preferências e UI

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$columnOrder` | `array` | `[]` | Ordem customizada das colunas |
| `$columnWidths` | `array` | `[]` | Larguras customizadas |
| `$viewDensity` | `string` | `'comfortable'` | `compact`, `comfortable`, `spacious` |
| `$viewMode` | `string` | `'table'` | Modo de exibição |

### Filtro externo / multi-tenant

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$whereHasFilter` | `string` | `''` | Relação para pré-filtrar |
| `$whereHasCondition` | `array` | `[]` | Condição da relação |
| `$companyFilter` | `int` | `0` | ID da empresa (0 = sem filtro) |

### Exportação

| Propriedade | Tipo | Padrão | Descrição |
|---|---|---|---|
| `$showExportMenu` | `bool` | `false` | Menu de exportação visível |
| `$exportStatus` | `string` | `''` | Mensagem de status da exportação |

---

## Métodos Públicos

### Tabela

| Método | Parâmetros | Descrição |
|---|---|---|
| `sortBy()` | `string $column` | Ordena pela coluna (toggle ASC/DESC) |
| `updatedSearch()` | — | Reseta paginação ao alterar search |
| `updatedFilters()` | — | Reseta paginação e atualiza badges |
| `updatedDateRanges()` | — | Reseta paginação e atualiza badges |
| `updatedPerPage()` | — | Reseta paginação e salva preferência |
| `toggleFilters()` | — | Abre/fecha painel de filtros |
| `clearFilters()` | — | Limpa todos os filtros |
| `toggleTrashed()` | — | Alterna exibição de soft-deleted |
| `setViewDensity()` | `string $density` | Define density: `compact`, `comfortable`, `spacious` |

### Modal

| Método | Parâmetros | Descrição |
|---|---|---|
| `openCreate()` | — | Abre modal em modo criação |
| `openEdit()` | `int $id` | Abre modal em modo edição |
| `closeModal()` | — | Fecha e limpa o modal |
| `save()` | — | Salva o registro (criação ou edição) |

### Exclusão

| Método | Parâmetros | Descrição |
|---|---|---|
| `confirmDelete()` | `int $id` | Abre confirmação de exclusão |
| `cancelDelete()` | — | Cancela a exclusão |
| `deleteRecord()` | — | Executa a exclusão (soft ou hard) |
| `restoreRecord()` | `int $id` | Restaura registro soft-deleted |

### Filtros salvos

| Método | Parâmetros | Descrição |
|---|---|---|
| `saveNamedFilter()` | `string $name` | Salva conjunto de filtros com nome |
| `loadNamedFilter()` | `string $name` | Carrega filtros pelo nome |
| `deleteNamedFilter()` | `string $name` | Remove filtro salvo |

### Visibilidade de colunas

| Método | Parâmetros | Descrição |
|---|---|---|
| `getVisibleColumns()` | — | Retorna colunas visíveis (filtered) |
| `updateColumns()` | — | Persiste mudanças de visibilidade |
| `showAllColumns()` | — | Torna todas as colunas visíveis |
| `hideAllColumns()` | — | Oculta todas as colunas |
| `resetColumnsToDefault()` | — | Restaura visibilidade padrão |

### Filtros de texto (badges)

| Método | Parâmetros | Descrição |
|---|---|---|
| `buildTextFilter()` | — | Reconstrói array de badges ativos |
| `removeTextFilterBadge()` | `string $field` | Remove um filtro pelo campo |

### Bulk actions

| Método | Parâmetros | Descrição |
|---|---|---|
| `toggleSelectAll()` | — | Seleciona/deseleciona toda a página |
| `toggleSelectRow()` | `int\|string $id` | Alterna seleção de uma linha |
| `bulkDelete()` | — | Exclui registros selecionados |
| `bulkExport()` | `string $format = 'excel'` | Exporta registros selecionados |
| `executeBulkAction()` | `string $action` | Executa ação bulk configurada |

### Filtros rápidos de data

| Método | Parâmetros | Descrição |
|---|---|---|
| `applyQuickDateFilter()` | `string $period` | Aplica/remove filtro de período (toggle) |
| `updatedQuickDateFilter()` | — | Atualizado automaticamente ao mudar `$quickDateFilter` |

### Busca avançada

| Método | Parâmetros | Descrição |
|---|---|---|
| `toggleAdvancedSearch()` | — | Ativa/desativa busca avançada |
| `addAdvancedSearchField()` | `string $field, string $operator, mixed $value, string $logic = 'AND'` | Adiciona critério de busca |
| `removeAdvancedSearchField()` | `int $index` | Remove critério pelo índice |

### Histórico de busca

| Método | Parâmetros | Descrição |
|---|---|---|
| `clearSearchHistory()` | — | Limpa histórico de buscas |

### Preferências

| Método | Parâmetros | Descrição |
|---|---|---|
| `savePreferences()` | — | Persiste preferências (V2.1) |

### SearchDropdown (formulário)

| Método | Parâmetros | Descrição |
|---|---|---|
| `openDropdown()` | `string $field` | Carrega os primeiros itens (sem query) ao focar/clicar |
| `searchDropdown()` | `string $field, string $query` | Filtra sugestões pelo texto digitado (min 1 char, debounce 300ms) |
| `selectDropdownOption()` | `string $field, mixed $value, string $label` | Confirma seleção e atualiza `formData` e `sdLabels` |
| `filterSearchDropdown()` | `string $field, string $query` | Busca no painel de filtros |

### Exportação

| Método | Parâmetros | Descrição |
|---|---|---|
| `export()` | `string $format = 'excel'` | Inicia exportação (sync ou async) |

### Formatação

| Método | Parâmetros | Descrição |
|---|---|---|
| `formatCell()` | `array $col, mixed $row` | Formata o valor de uma célula |
| `getRowStyle()` | `mixed $row` | Retorna CSS inline baseado em `contitionStyles` |
| `getDefaultPermissionIdentifier()` | — | Ex: `"products.index"` |
| `updateTrashedCount()` | — | Atualiza `$trashedCount` |

---

## CrudConfig — Estrutura de Colunas

O `CrudConfig` é recuperado do banco de dados (tabela `crud_configs`) pelo `CrudConfigService`.

### Estrutura geral

```json
{
  "crud": "Product",
  "companyField": "company_id",
  "quickDateColumn": "created_at",
  "permissions": {
    "create": true,
    "edit": true,
    "delete": true,
    "export": true
  },
  "uiPreferences": {
    "compactMode": false,
    "perPage": 25
  },
  "cols": [ ...ColDef[] ],
  "totalizadores": { ... },
  "exportConfig": { ... },
  "bulkActions": [ ...BulkAction[] ],
  "customFilters": [ ...CustomFilter[] ],
  "contitionStyles": [ ...CondStyle[] ]
}
```

### Definição de coluna (`ColDef`)

| Chave | Tipo | Descrição |
|---|---|---|
| `colsNomeFisico` | `string` | Nome real do campo na tabela |
| `colsNomeLogico` | `string` | Rótulo exibido ao usuário |
| `colsTipo` | `string` | Tipo da coluna — veja [Tipos de Coluna](#tipos-de-coluna) |
| `colsGravar` | `'S'\|'N'` | Campo incluído ao salvar |
| `colsRequired` | `'S'\|'N'` | Obrigatório no formulário |
| `colsHelper` | `string\|null` | Helper legacy de formatação — veja [Helpers](#helpers-de-formatação-de-célula) |
| `colsRenderer` | `string\|null` | Renderer DSL — `badge`, `pill`, `boolean`, `money`, `link`, `image`, `truncate` |
| `colsRendererBadges` | `array\|null` | Mapa `["valor" => "cor"]` para `badge`/`pill` |
| `colsCellStyle` | `string\|null` | CSS inline no `<span>` da célula |
| `colsCellClass` | `string\|null` | Classes Tailwind adicionais da célula |
| `colsCellIcon` | `string\|null` | Ícone `heroicon-*` prefixado ao conteúdo |
| `colsMinWidth` | `string\|null` | Largura mínima do th (ex: `"120px"`) |
| `colsMask` | `string\|null` | Máscara: `cpf`, `cnpj`, `phone`, `cep`, `currency`, `percent` |
| `colsMaskTransform` | `string\|null` | Transformação pós-máscara: `upper`, `lower`, `ucfirst` |
| `colsRelacao` | `string\|null` | Nome da relação Eloquent |
| `colsRelacaoExibe` | `string\|null` | Campo da relação a exibir |
| `colsRelacaoNested` | `string\|null` | Notação dot para relações aninhadas: `category.parent.name` |
| `colsOrderBy` | `string\|null` | Coluna real para ORDER BY |
| `colsMetodoCustom` | `string\|null` | Padrão `Namespace\Classe\método(%campo%)` |
| `colsSelect` | `array\|null` | Opções de select: `[valor => label]` |
| `colsSDModel` | `string\|null` | Model do SearchDropdown (ex: `BusinessPartner` ou FQCN) |
| `colsSDLabel` | `string\|null` | Campo da tabela usado como label (ex: `name`) |
| `colsSDValor` | `string\|null` | Campo da tabela usado como value (ex: `id`) |
| `colsSDOrder` | `string\|null` | Ordenação: `"name ASC"` (padrão: `{sdLabel} ASC`) |
| `colsSDTipo` | `'model'\|'service'` | Origem dos dados (`model` = Eloquent direto, `service` = método de serviço) |
| `colsSDLimit` | `int` | Limite de itens retornados (padrão: `15`) |
| `colsSDMode` | `'create'\|'edit'\|'both'` | Em qual modo do modal o campo SD aparece |
| `colsValidations` | `array\|null` | Regras do FormValidatorService: `["required","email","min:3"]` |

---

## Tipos de Coluna

Valor de `colsTipo`:

| Tipo | Descrição |
|---|---|
| `text` | Campo de texto livre |
| `number` / `numeric` | Campo numérico |
| `date` | Data sem hora |
| `datetime` / `timestamp` | Data com hora |
| `boolean` | Verdadeiro/falso |
| `select` | Select com opções fixas (`colsSelect`) |
| `searchdropdown` | Campo com busca dinâmica (SD) |
| `array` | Lista de valores |
| `relation` | Filtro via `whereHas` |

---

## Helpers de Formatação de Célula

Configurado em `colsHelper` da coluna (helpers legacy).

| Helper | Resultado |
|---|---|
| `dateFormat` | `01/12/2025` |
| `dateTimeFormat` | `01/12/2025 14:30` |
| `currencyFormat` | `R$ 1.234,56` |
| `yesOrNot` | `Sim` / `Não` |
| `flagChannel` | Badge colorido: **G** verde, **Y** amarelo, **R** vermelho |

### Método customizado (`colsMetodoCustom`)

```
"App\\Services\\ProductService\\getStatus(%id%)"
```

- O padrão é `Namespace\Classe\metodo(%campo%)`.
- `%campo%` é substituído pelo valor do campo no registro.
- O método é chamado via `app()->make(Classe)->metodo($param)`.

---

## Renderer DSL

O `colsRenderer` é a forma moderna e recomendada de formatar células.

| Renderer | Descrição |
|---|---|
| `badge` | `<span>` com fundo colorido (padded, bordas leves) |
| `pill` | Igual ao `badge` mas com bordas completamente arredondadas |
| `boolean` | `✅` (verde) / `❌` (vermelho) baseado em truthy |
| `money` | Formata como `R$ X.XXX,XX` |
| `link` | `<a href="[valor]" target="_blank">` |
| `image` | `<img src="[valor]">` thumbnail 40×40 |
| `truncate` | Texto cortado em ~40 chars com `title` completo no hover |

### Badge / Pill

Cores nomeadas: `green`, `red`, `yellow`, `blue`, `indigo`, `purple`, `pink`, `gray`.  
Cores hex (`#RRGGBB`): geram `background-color` inline com 13% de opacidade e `color` correspondente.

```json
{
  "colsNomeFisico": "status",
  "colsRenderer": "badge",
  "colsRendererBadges": {
    "active":   "green",
    "inactive": "red",
    "pending":  "#F59E0B"
  }
}
```

### Estilo, classe e ícone por célula

```json
{
  "colsCellStyle": "font-weight:600;",
  "colsCellClass": "text-indigo-700 italic",
  "colsCellIcon":  "heroicon-o-star",
  "colsMinWidth":  "140px"
}
```

### Máscaras (`colsMask`)

| Máscara | Entrada | Saída |
|---|---|---|
| `cpf` | `12345678901` | `123.456.789-01` |
| `cnpj` | `12345678000190` | `12.345.678/0001-90` |
| `phone` | `11987654321` | `(11) 98765-4321` |
| `cep` | `01310100` | `01310-100` |
| `currency` | `1234.5` | `R$ 1.234,50` |
| `percent` | `0.75` | `75%` |

Combinar com `colsMaskTransform: "upper"` transforma o resultado final.

### Relações aninhadas (`colsRelacaoNested`)

Notação dot para relações em cadeia, sem `colsMetodoCustom`:

```json
{ "colsRelacaoNested": "category.parent.name" }
```

Resolvido via `resolveNestedValue()` em qualquer profundidade.

---

## Estilos Condicionais de Linha

Configurado em `contitionStyles` do CrudConfig.

```json
"contitionStyles": [
  {
    "colsNomeFisico": "status",
    "condition": "==",
    "value": "inactive",
    "style": "opacity: 0.5; color: #999;"
  }
]
```

| Operador | Descrição |
|---|---|
| `==` | Igual (string) |
| `!=` | Diferente (string) |
| `>` | Maior (float) |
| `<` | Menor (float) |
| `>=` | Maior ou igual (float) |
| `<=` | Menor ou igual (float) |

Retornado por `getRowStyle($row)`, aplicado via `style="{{ $this->getRowStyle($row) }}"`.

---

## Filtros

### Filtros do formulário (`$filters`)

Aplicados automaticamente quando `$filters[campo]` tem valor.

```php
$this->filters['status'] = 'active';
$this->filters['name']   = 'João';
```

Os operadores são inferidos automaticamente:
- String com mais de 1 caractere → `LIKE %valor%`
- Valor exato → `=`

### Date ranges (`$dateRanges`)

Suporta dois padrões:

```php
// Padrão Ptah
$this->dateRanges['created_at_start'] = '2025-01-01';
$this->dateRanges['created_at_end']   = '2025-12-31';

// Padrão legado ERP
$this->dateRanges['created_at_from'] = '2025-01-01';
$this->dateRanges['created_at_to']   = '2025-12-31';
```

### Filtros customizados (`customFilters`)

Definidos no CrudConfig, processados separadamente via `FilterService::processCustomFilters()`.

```json
"customFilters": [
  {
    "field": "status",
    "type": "select",
    "operator": "="
  }
]
```

Para ativar no template, use `wire:model="filters.status"`.

---

## Filtros Rápidos de Data

Períodos disponíveis:

| Valor | Descrição |
|---|---|
| `today` | Hoje (00:00 → 23:59) |
| `week` | Esta semana (seg → dom) |
| `month` | Este mês (dia 1 → último) |
| `quarter` | Este trimestre |
| `year` | Este ano |

```blade
<button wire:click="applyQuickDateFilter('today')">Hoje</button>
<button wire:click="applyQuickDateFilter('month')">Este mês</button>
```

A coluna usada é `$quickDateColumn` (padrão `created_at`, configurável em `crudConfig['quickDateColumn']`).

---

## Busca Avançada

```blade
<button wire:click="toggleAdvancedSearch()">Busca Avançada</button>

@if ($advancedSearchActive)
    {{-- Adiciona critério --}}
    <button wire:click="addAdvancedSearchField('price', '>=', 100, 'AND')">
        Preço >= 100
    </button>

    {{-- Remove critério --}}
    @foreach ($advancedSearchFields as $i => $asf)
        <button wire:click="removeAdvancedSearchField({{ $i }})">✕</button>
    @endforeach
@endif
```

### Estrutura de um critério

```php
[
    'field'    => 'price',      // Campo da tabela
    'operator' => '>=',         // Operador: =, !=, >, <, >=, <=, LIKE, IN, NOT IN
    'value'    => 100,          // Valor
    'logic'    => 'AND',        // 'AND' ou 'OR'
]
```

Os campos com `logic = 'OR'` são agrupados em um `WHERE (... OR ...)` separado.

---

## Visibilidade de Colunas

```blade
{{-- Toggle individual --}}
<input type="checkbox" wire:model.live="formDataColumns.name" wire:change="updateColumns()">

{{-- Ações em lote --}}
<button wire:click="showAllColumns()">Mostrar Todas</button>
<button wire:click="hideAllColumns()">Ocultar Todas</button>
<button wire:click="resetColumnsToDefault()">Resetar</button>

{{-- Contador --}}
@if ($hiddenColumnsCount > 0)
    {{ $hiddenColumnsCount }} colunas ocultas
@endif
```

Na view da tabela, use o computed `visibleCols` passado pelo `render()`:

```blade
@foreach ($visibleCols as $col)
    <th>{{ $col['colsNomeLogico'] }}</th>
@endforeach
```

---

## Bulk Actions

### Configuração no CrudConfig

```json
"bulkActions": [
  {
    "label": "Aprovar Selecionados",
    "action": "aprovar",
    "method": "App\\Services\\ProductService@bulkAprovar"
  }
]
```

O método `bulkAprovar` receberá `(array $ids, string $model)`.

### Template

```blade
{{-- Checkbox de cada linha --}}
<input type="checkbox"
    wire:click="toggleSelectRow({{ $row->id }})"
    @checked(in_array((string) $row->id, $selectedRows))>

{{-- Select all --}}
<input type="checkbox" wire:click="toggleSelectAll()" @checked($selectAll)>

{{-- Painel de ações --}}
@if (count($selectedRows) > 0)
    <button wire:click="bulkDelete()">Excluir Selecionados</button>
    <button wire:click="bulkExport('excel')">Exportar</button>

    @foreach ($bulkActions as $ba)
        <button wire:click="executeBulkAction('{{ $ba['action'] }}')">
            {{ $ba['label'] }}
        </button>
    @endforeach
@endif
```

### Eventos disparados pelo bulk

| Evento | Payload |
|---|---|
| `crud-bulk-deleted` | `model, count` |
| `crud-bulk-action` | `model, action, ids` |
| `ptah:bulk-export` | `model, ids, format` |

---

## SearchDropdown em Formulários

Campos do tipo `searchdropdown` oferecem UX similar ao Select2 dentro do modal de criação/edição:

- **Foco no campo** → carrega os primeiros registros automaticamente (sem precisar digitar)
- **Digitação** → filtra em tempo real com debounce de 300ms, case-insensitive
- **Seleção** → o label selecionado persiste no input; o `id` vai para `formData`
- **Seta** → botão chevron abre/fecha o dropdown
- **Vazio** → exibe "Nenhum resultado encontrado" se a busca não retornar itens

### Configuração da coluna

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

> **`colsRelacao` + `colsRelacaoExibe`** são usados para pré-preencher o label no modo **edição**: o ptah busca `$record->businessPartner->name` e exibe no input.

### Filtro case-insensitive

A busca usa `LOWER(campo) LIKE ?` compatível com MySQL e SQLite:

```php
$q->whereRaw('LOWER(' . $sdLabel . ') LIKE ?', ['%' . mb_strtolower($query) . '%']);
```

Assim digitar `"CHOC"`, `"choc"` ou `"Choc"` retorna os mesmos resultados.

### Via Service (`colsSDTipo = 'service'`)

```json
{
  "colsSDModel": "Product\\ProductService\\searchActive",
  "colsSDTipo": "service"
}
```

O método receberá `string $query` e deve retornar `array<array{value: mixed, label: string}>`.

### Fluxo interno

```
Foco no input  →  openDropdown($field)      →  sdResults[$field] = primeiros N itens
Digitação      →  searchDropdown($field, q) →  sdResults[$field] = itens filtrados
Seleção        →  selectDropdownOption()    →  formData[$field] = value
                                              sdLabels[$field] = label
                                              sdResults[$field] = [] (fecha)
```

---

## WhereHas — Filtro por Entidade Pai

Permite abrir o CRUD já filtrado por uma entidade pai (ex: produtos de uma categoria específica).

```blade
@livewire('ptah::base-crud', [
    'model'             => 'Product',
    'whereHasFilter'    => 'category',
    'whereHasCondition' => ['id', '=', 5],
])
```

Internamente gera:

```php
$query->whereHas('category', fn($q) => $q->where('id', '=', 5));
```

---

## Multi-tenant (companyFilter)

Quando `$companyFilter > 0`, aplica automaticamente `WHERE {tabela}.{companyField} = $companyFilter`.

A coluna da empresa é configurada em `crudConfig['companyField']` (padrão: `company_id`).

```blade
@livewire('ptah::base-crud', [
    'model'         => 'Product',
    'companyFilter' => auth()->user()->company_id,
])
```

Se não passado, tenta `session('company_id', 0)`.

---

## Totalizadores

```json
"totalizadores": {
  "enabled": true,
  "columns": [
    { "field": "total_value", "aggregate": "sum" },
    { "field": "quantity",    "aggregate": "sum" },
    { "field": "total_value", "aggregate": "avg" }
  ]
}
```

| Agregado | Descrição |
|---|---|
| `sum` | Soma |
| `count` | Contagem |
| `avg` | Média (2 decimais) |
| `max` | Máximo |
| `min` | Mínimo |

Acessível na view via `$totData`:

```blade
Total: R$ {{ number_format($totData['total_value'] ?? 0, 2, ',', '.') }}
```

---

## Exportação

### Configuração

```json
"exportConfig": {
  "enabled": true,
  "asyncThreshold": 1000,
  "formats": ["excel", "csv", "pdf"]
}
```

- Se registro count ≤ `asyncThreshold` → exportação síncrona via `ptah:export-sync`.
- Se registro count > `asyncThreshold` → dispara `Ptah\Jobs\BaseCrudExportJob` na fila.

### Template

```blade
<button wire:click="$toggle('showExportMenu')">Exportar</button>

@if ($showExportMenu)
    <button wire:click="export('excel')">Excel</button>
    <button wire:click="export('csv')">CSV</button>
@endif

@if ($exportStatus)
    <span>{{ $exportStatus }}</span>
@endif
```

---

## Preferências de Usuário (V2.1)

Salvas em `user_preferences` com chave `crud.{Model}`, grupo `crud`.

### Esquema completo

```json
{
  "_version": "2.1.0",
  "_lastModified": "2025-12-01T14:30:00+00:00",
  "company": 1,
  "table": {
    "orderBy": "id",
    "direction": "DESC",
    "perPage": 25,
    "columns": [],
    "currentPage": 1
  },
  "filters": {
    "lastUsed": {},
    "saved": {},
    "customFilter": [],
    "quickDate": "month",
    "quickDateColumn": "created_at"
  },
  "columns": {
    "name": true,
    "status": true,
    "price": false
  },
  "columnWidths": {},
  "columnOrder": [],
  "viewMode": "table",
  "viewDensity": "comfortable",
  "searchHistory": ["João", "produto x"],
  "advancedSearch": {
    "active": false,
    "fields": []
  }
}
```

---

## Eventos Livewire

### Disparados pelo BaseCrud (`dispatch()`)

| Evento | Payload | Quando |
|---|---|---|
| `crud-saved` | `model` | Após save com sucesso |
| `crud-deleted` | `model` | Após exclusão |
| `crud-restored` | `model` | Após restauração |
| `crud-bulk-deleted` | `model, count` | Após bulk delete |
| `crud-bulk-action` | `model, action, ids` | Após bulk action |
| `ptah:export-sync` | `model, format, filters` | Exportação síncrona |
| `ptah:bulk-export` | `model, ids, format` | Exportação de selecionados |

### Como ouvir no componente pai

```php
use Livewire\Attributes\On;

#[On('crud-saved')]
public function onProductSaved(string $model): void
{
    // ...
}
```

---

## Permissões

Configuradas em `crudConfig['permissions']`:

```json
"permissions": {
  "create": true,
  "edit": true,
  "delete": false,
  "export": true
}
```

Acessíveis na view via `$permissions`:

```blade
@if ($permissions['create'] ?? false)
    <button wire:click="openCreate()">Novo</button>
@endif
```

O identificador padrão de permissão é retornado por `getDefaultPermissionIdentifier()`:

```php
// Model "Product"          → "product.index"
// Model "Purchase/Order"   → "purchase/order.index"
```

---

## Error Recovery

Se `getRowsProperty()` lançar qualquer exceção:

1. As preferências do usuário são limpas (`UserPreference::remove()` + `CacheService::forgetPreferences()`).
2. O erro é registrado em `Log::error()` com o stack trace.
3. Uma `session()->flash('error', ...)` é definida.
4. Um `LengthAwarePaginator` vazio é retornado (tela não quebra).

```blade
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
```

---

## CrudConfig Modal

O modal de configuração do CrudConfig é um componente Livewire (`ptah::crud-config`) que permite editar a configuração de colunas diretamente pela interface, sem tocar no banco manualmente.

### Como acessar

O botão de configuração é exibido automaticamente no BaseCrud (geralmente restrito a administradores via `@can('admin')`).

### Abas disponíveis

| Aba | Conteúdo |
|---|---|
| **Colunas** | Lista drag-and-drop das colunas. Selecione uma para editar nas sub-abas |
| **Ações** | Configuração de permissões (create, edit, delete, export) |
| **Filtros** | Configuração dos filtros customizados e coluna de data rápida |
| **Estilos** | `contitionStyles`: regras de estílo condicional de linha |
| **Geral** | Parâmetros gerais: `companyField`, `quickDateColumn`, `configLinkLinha` |
| **Permissões** | Mapeamento de gates/abilities por ação |

### Sub-abas por coluna (aba Colunas)

Ao selecionar uma coluna na sidebar, seis sub-abas são exibidas:

| Sub-aba | Campos editados |
|---|---|
| **Básico** | `colsNomeFisico`, `colsNomeLogico`, `colsTipo`, `colsGravar`, `colsRequired`, `colsIsFilterable`, estilo de célula (`colsCellStyle`, `colsCellClass`, `colsCellIcon`, `colsMinWidth`) |
| **Exibição** | `colsHelper`, `colsRenderer`, `colsRelacaoNested`, `colsMask`, `colsMaskTransform` |
| **Badges** | `colsRendererBadges` — mapa valor→cor com seletor hex nativo + 8 swatches rápidos por linha |
| **Relação** | `colsRelacao`, `colsRelacaoExibe`, `colsSDModel`, `colsSDLabel`, `colsSDValor`, `colsSDOrder`, `colsSDTipo`, `colsSDMode` |
| **Validação** | `colsValidations` (array de regras), `colsRequired` |
| **Avançado** | `colsOrderBy`, `colsReverse`, `colsMetodoCustom`, `colsAlign` |

### Reordenar colunas

As colunas podem ser reordenadas via drag-and-drop (SortableJS). A nova ordem é persistida automaticamente via `$wire.reorderFields(newOrder)` ao soltar.

### Eventos

| Evento | Quando |
|---|---|
| `ptah:crud-config-updated` | Disparado após salvar o CrudConfig Modal. O BaseCrud ouve e invalida o cache + recarrega a config ao vivo |

---

## FormValidatorService

O `FormValidatorService` (é injetado no `BaseCrud`) valida os campos do formulário usando as regras definidas em `colsValidations` de cada coluna.

### Regras suportadas

| Regra | Exemplo | Descrição |
|---|---|---|
| `required` | `"required"` | Campo obrigatório |
| `email` | `"email"` | Formato de e-mail |
| `url` | `"url"` | Formato de URL |
| `integer` | `"integer"` | Número inteiro |
| `numeric` | `"numeric"` | Número (decimal incluso) |
| `min:X` | `"min:0"` | Valor mínimo |
| `max:X` | `"max:9999"` | Valor máximo |
| `minLength:X` | `"minLength:3"` | Mínimo de caracteres |
| `maxLength:X` | `"maxLength:255"` | Máximo de caracteres |
| `between:X,Y` | `"between:1,100"` | Valor entre X e Y |
| `regex:pattern` | `"regex:^[A-Z]+$"` | Expressão regular |
| `cpf` | `"cpf"` | Valida CPF brasileiro |
| `cnpj` | `"cnpj"` | Valida CNPJ brasileiro |
| `phone` | `"phone"` | Valida telefone (8–11 dígitos) |

### Configuração na coluna

```json
{
  "colsNomeFisico": "email",
  "colsValidations": ["required", "email", "maxLength:255"]
}
```

```json
{
  "colsNomeFisico": "price",
  "colsValidations": ["required", "numeric", "min:0", "max:999999"]
}
```

Erros são populados em `$formErrors[campo]` e exibidos no modal de formulário.

---

## Fluxo Interno Simplificado

```
@livewire('ptah::base-crud', ['model' => 'Product'])
        │
        ▼
    boot()          ← Injeta CrudConfigService, FilterService, CacheService
        │
        ▼
    mount()         ← Carrega CrudConfig, inicia colunas, preferências, trashedCount
        │
        ▼
    render()        ─────────────────────────────────────────────────────┐
        │                                                                 │
        ▼                                                                 ▼
getRowsProperty()                                             view(ptah::livewire.base-crud)
  [computed]                                                       rows, visibleCols,
        │                                                          formCols, permissions,
        ├─ resolveEloquentModel()                                  totData, bulkActions,
        ├─ SoftDeletes?                                            hasActiveFilters
        ├─ eager load relations
        ├─ companyFilter (WHERE)
        ├─ whereHasFilter (whereHas)
        ├─ search → buildGlobalSearchFilters() (OR em relações)
        ├─ buildActiveFilters() → FilterService::applyFilters()
        ├─ processDateRangeFilters(dateRanges)
        ├─ quickDateFilter → getQuickDateRange()
        ├─ processCustomFilters()
        ├─ getOrderByRelationInfo() → LEFT JOIN ou orderBy simples
        └─ paginate($perPage)
```

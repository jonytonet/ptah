# CRUD Configuration Guide

**Pacote:** `jonytonet/ptah`  
**Componente:** BaseCrud Configuration  
**Laravel:** 11+

> 🎯 **Quick Start: Lifecycle Hooks**  
> Copie o template completo: [`ProductHooks.example.php`](ProductHooks.example.php) → `app/CrudHooks/ProductHooks.php`  
> Configure no modal: `@ProductHooks` → Pronto!

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Métodos de Configuração](#métodos-de-configuração)
3. [Modal de Configuração Visual](#modal-de-configuração-visual)
4. [Comando CLI (ptah:config)](#comando-cli-ptahconfig)
5. [Comparação: Modal vs CLI](#comparação-modal-vs-cli)
6. [Estrutura do CrudConfig (JSON)](#estrutura-do-crudconfig-json)
7. [Configuração de Colunas](#configuração-de-colunas)
8. [Configuração de Ações](#configuração-de-ações)
9. [Configuração de Filtros](#configuração-de-filtros)
10. [Configuração de Estilos](#configuração-de-estilos)
11. [Configuração de JOINs](#configuração-de-joins)
12. [Configurações Gerais](#configurações-gerais)
13. [Configuração de Permissões](#configuração-de-permissões)
14. [Lifecycle Hooks (Código Dinâmico)](#lifecycle-hooks-código-dinâmico)
15. [Exemplos Práticos Completos](#exemplos-práticos-completos)
16. [Workflow Recomendado](#workflow-recomendado)
17. [Import/Export de Configurações](#importexport-de-configurações)
18. [Troubleshooting](#troubleshooting)

---

## Visão Geral

O sistema CRUD do Ptah oferece **dois métodos complementares** para configurar colunas, filtros, ações, estilos e outras opções:

| Método | Interface | Melhor Para | Requer |
|--------|-----------|-------------|--------|
| **Modal Visual** | Interface gráfica com drag-and-drop | Configuração inicial, ajustes visuais, exploração | Navegador web, autenticação |
| **Comando CLI** | Terminal via `ptah:config` | Automação, CI/CD, versionamento, batch operations | Terminal, acesso ao servidor |

Ambos os métodos **salvam na mesma tabela** (`crud_configs`) e **produzem o mesmo resultado final**.

### Onde as Configurações São Armazenadas

```sql
SELECT * FROM crud_configs WHERE model = 'Product';
```

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | `int` | Primary key |
| `model` | `string` | Identificador da model (ex: `Product`, `Product/ProductStock`) |
| `config` | `json` | Configuração completa em JSON |
| `created_at` | `timestamp` | Data de criação |
| `updated_at` | `timestamp` | Última modificação |

### Cache e Invalidação

As configurações são cacheadas automaticamente:
- Cache key: `crud_config_{model}`
- TTL: Configurável em `crud_configs.cacheTtl` (padrão: 3600s)
- Invalidação automática ao salvar via modal ou CLI

---

## Métodos de Configuração

### 1. Via Modal Visual (Interface Gráfica)

Acesse o botão de configuração (⚙️) no topo da tela do CRUD:

```blade
{{-- O botão é renderizado automaticamente no BaseCrud --}}
<button wire:click="$dispatch('openConfig', { model: '{{ $model }}' })">
    ⚙️ Config
</button>
```

**Vantagens:**
✅ Interface visual intuitiva  
✅ Drag-and-drop para reordenar colunas  
✅ Preview em tempo real  
✅ Color picker integrado para badges  
✅ Não requer conhecimento técnico  
✅ Detecta relações automaticamente  

**Desvantagens:**
❌ Não versionável via Git  
❌ Não automatizável  
❌ Requer autenticação no browser  
❌ Uma configuração por vez  

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

**Vantagens:**
✅ Automatizável via scripts  
✅ Versionável (export JSON → commit)  
✅ Batch operations (configure múltiplas models)  
✅ CI/CD friendly  
✅ Reproduzível entre ambientes  
✅ Smart suggestions baseadas na model  

**Desvantagens:**
❌ Curva de aprendizado (sintaxe)  
❌ Sem preview visual  
❌ Requer acesso ao terminal  

---

## Modal de Configuração Visual

### Como Acessar

O modal é aberto automaticamente ao clicar no botão **⚙️ Config** no topo do BaseCrud (geralmente restrito a administradores via `@can('admin')` ou similar).

### Estrutura em Abas

O modal possui **7 abas principais**:

#### 1️⃣ Colunas

**O que faz:** Gerencia todas as colunas do CRUD (listagem + formulário)

**Interface:**
- **Sidebar esquerda:** Lista drag-and-drop de todas as colunas
- **Painel direito:** 6 sub-abas para editar a coluna selecionada

**Ações disponíveis:**
- 🔄 Reordenar via drag-and-drop
- ➕ Adicionar nova coluna
- ✏️ Editar coluna existente
- 🗑️ Excluir coluna
- 👁️ Toggle visibilidade

**Sub-abas por coluna:**

| Sub-aba | Campos Editados | Descrição |
|---------|-----------------|-----------|
| **Básico** | `colsNomeFisico`, `colsNomeLogico`, `colsTipo`, `colsGravar`, `colsRequired`, `colsIsFilterable`, `colsCellStyle`, `colsCellClass`, `colsCellIcon`, `colsMinWidth`, `colsSource` | Informações principais da coluna + estilo de célula + fonte SQL (JOIN badge) |
| **Exibição** | `colsHelper`, `colsRenderer`, `colsRelacaoNested`, `colsMask`, `colsMaskTransform` | Como a coluna é renderizada na tabela e formatada no formulário |
| **Badges** | `colsRendererBadges` | Mapa valor→cor para renderer `badge`/`pill` com seletor hex + 8 swatches rápidos |
| **Relação** | `colsRelacao`, `colsRelacaoExibe`, `colsSDModel`, `colsSDLabel`, `colsSDValor`, `colsSDOrder`, `colsSDTipo`, `colsSDMode` | Configuração de relacionamentos Eloquent e SearchDropdown |
| **Validação** | `colsValidations`, `colsRequired` | Regras de validação do formulário |
| **Avançado** | `colsOrderBy`, `colsReverse`, `colsMetodoCustom`, `colsAlign` | Ordenação customizada, métodos de acesso e alinhamento |

**Exemplo de fluxo:**

1. Clique em uma coluna na sidebar (ex: `status`)
2. Vá para sub-aba **"Exibição"**
3. Altere `colsRenderer` para `badge`
4. Vá para sub-aba **"Badges"**
5. Configure cores:
   - `active` → verde (`#10B981`)
   - `inactive` → vermelho (`#EF4444`)
   - `pending` → amarelo (`#F59E0B`)
6. Clique **"Salvar"** (canto superior direito)

#### 2️⃣ Ações

**O que faz:** Configura permissões e ações customizadas no CRUD

**Seções:**

1. **Permissões de Ações Padrão:**
   - ✅ Create (criar novo registro)
   - ✅ Edit (editar registro)
   - ✅ Delete (excluir registro)
   - ✅ Export (exportar dados)

2. **Ações Customizadas:**
   - Lista de ações extras (ex: "Approve", "Reject", "Send Email")
   - Cada ação tem: nome, tipo (livewire/link/javascript), valor, ícone, cor, confirmação

**Exemplo de ação customizada:**

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

**O que faz:** Configura filtros customizados e coluna de filtro rápido de data

**Seções:**

1. **Coluna de Data para Filtro Rápido:**
   - Seleciona qual coluna de data usar para "Hoje/Semana/Mês/Trimestre/Ano"
   - Padrão: `created_at`

2. **Filtros Customizados:**
   - Lista de filtros adicionais para a toolbar
   - Cada filtro tem: campo, label, tipo (text/number/date/select/searchdropdown), operador (=, !=, >, <, >=, <=, LIKE)

**Exemplo de filtro customizado:**

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

**O que faz:** Define estilos condicionais de linha (row styles)

**Interface:**
- Card por regra de estilo
- Cada regra: campo, operador, valor, CSS (background, color, fontWeight, custom)

**Exemplo de regra:**

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

**Resultado:** Linhas com `status = cancelled` ficam com fundo vermelho claro e texto vermelho escuro.

#### 5️⃣ JOINs

**O que faz:** Gerencia JOINs de tabelas (LEFT/INNER)

**Interface:**
- Cards visuais dos JOINs ativos
- Detecção de duplicata de tabela
- Formulário de criação/edição
- Toggle `DISTINCT` opcional

**Exemplo de JOIN:**

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

**Visual no modal:**
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

**O que faz:** Configurações globais do CRUD

**Seções:**

1. **Identificação:**
   - `displayName` — Nome de exibição (ex: "Products" → "Produtos")

2. **Aparência:**
   - `companyField` — Campo de multi-tenancy
   - `tableClass` — Classes CSS extras para a tabela

3. **Cache:**
   - `cacheEnabled` — Habilitar cache (true/false)
   - `cacheTtl` — Tempo de vida do cache (segundos)

4. **Exportação:**
   - `exportMaxRows` — Máximo de linhas exportáveis
   - `pdfOrientation` — Orientação do PDF (landscape/portrait)
   - `pdfPaperSize` — Tamanho do papel (A4, Letter, etc)

5. **Broadcast (Tempo Real):**
   - `broadcastEnabled` — Habilitar Echo listener
   - `broadcastChannel` — Nome do canal (padrão: `page-{model}-observer`)
   - `broadcastEvent` — Nome do evento (padrão: `.page{Model}Observer`)

6. **Tema Visual:**
   - `theme` — light/dark

#### 7️⃣ Permissões

**O que faz:** Mapeia gates/abilities Laravel por ação

**Interface:**
- Lista de ações (list, view, create, edit, delete, export, import, restore, forceDelete)
- Campo de texto para cada ação (ex: `product.create`, `view-products`)

**Exemplo:**

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

**Uso no código:**

```php
// BaseCrud verifica automaticamente:
if (!Gate::allows($this->crudConfig['permissions']['create'] ?? 'create')) {
    abort(403);
}
```

#### 8️⃣ Lifecycle Hooks

**O que faz:** Permite executar código PHP customizado em momentos específicos do ciclo de vida do registro

> 📁 **Exemplo completo:** Veja [ProductHooks.example.php](ProductHooks.example.php) — Template com +300 linhas de exemplos práticos e comentados.

**Sistema Híbrido:** Suporta **duas sintaxes**:

1. **Código Inline (eval):** Para lógica simples e rápida
   ```php
   $data['status'] = 'pending';
   Log::info('Creating product');
   ```

2. **Classes PHP (recomendado):** Para lógica complexa, testável e com autocomplete
   ```php
   @ProductHooks::beforeCreate
   @App\CrudHooks\ProductHooks
   ```

**Interface:**
- 4 textareas com editor de código
- Exemplos práticos de ambas as sintaxes
- Info box explicando variáveis disponíveis
- Warning sobre segurança

**Hooks disponíveis:**

| Hook | Quando Executa | Variáveis Disponíveis | Pode Modificar |
|------|----------------|----------------------|----------------|
| **beforeCreate** | Antes de INSERT | `$data` (array) | ✅ Sim (`$data` por referência) |
| **afterCreate** | Após INSERT | `$record` (Model), `$data` (array) | ❌ Não |
| **beforeUpdate** | Antes de UPDATE | `$data` (array), `$record` (Model) | ✅ Sim (`$data` por referência) |
| **afterUpdate** | Após UPDATE | `$record` (Model), `$data` (array) | ❌ Não |

---

### 📝 **Sintaxe 1: Código Inline (eval)**

Escreva código PHP diretamente no modal. Ideal para lógica simples.

**beforeCreate** — Definir valores padrão:
```php
$data['status'] = 'pending';
$data['uuid'] = \Illuminate\Support\Str::uuid();
Log::info('Creating new product');
```

**afterCreate** — Disparar eventos:
```php
Log::info('Product created: ' . $record->id);
event(new \App\Events\ProductCreated($record));
cache()->put('latest_product', $record->id, 3600);
```

**beforeUpdate** — Validação customizada:
```php
if ($record->status === 'draft' && isset($data['status']) && $data['status'] === 'published') {
    $data['published_at'] = now();
    $data['published_by'] = auth()->id();
}
```

**afterUpdate** — Invalidar cache:
```php
cache()->forget('product_' . $record->id);
cache()->tags(['products'])->flush();
$record->load('category', 'tags');
```

---

### 🏗️ **Sintaxe 2: Classes PHP (Recomendado)**

Para lógica complexa, crie classes PHP reais em `app/CrudHooks/`.

**Vantagens:**
✅ Autocomplete e análise estática (PHPStan/Psalm)  
✅ Testável com PHPUnit  
✅ Git-friendly e versionável  
✅ Reutilizável entre CRUDs  
✅ Sem risco de sintaxe eval()  

**Sintaxes suportadas:**

| Sintaxe no Modal | Resultado |
|------------------|-----------|
| `@ProductHooks::beforeCreate` | Chama `App\CrudHooks\ProductHooks::beforeCreate()` |
| `@ProductHooks` | Usa nome do hook como método (ex: `beforeCreate()`) |
| `@App\Services\MyHooks::customMethod` | Usa namespace completo |
| `@App\Services\MyHooks@customMethod` | Separador @ também funciona |

**Exemplo completo:**

**1. Criar classe de hooks:**

> 📋 **Template completo disponível:** Copie [ProductHooks.example.php](ProductHooks.example.php) para `app/CrudHooks/ProductHooks.php` e customize conforme necessário.

**Exemplo simplificado:**

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
     * @param array &$data Dados do formulário (mutável por referência)
     * @param Model|null $record Sempre null neste hook
     * @param mixed $component Componente Livewire (HasCrudForm trait)
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

> 💡 **Veja mais exemplos em [ProductHooks.example.php](ProductHooks.example.php):**
> - Gerar códigos únicos
> - Transições de status com validação
> - Sincronização com APIs externas
> - Notificações complexas
> - Histórico de alterações
> - +20 casos de uso documentados

**2. Configurar no modal:**

No campo "Before Create", escreva apenas:
```
@ProductHooks
```

Ou especifique o método:
```
@ProductHooks::beforeCreate
```

Ou com namespace completo:
```
@App\CrudHooks\ProductHooks::beforeCreate
```

**3. Resultado:**

✅ BaseCrud detecta a sintaxe `@` e instancia a classe  
✅ Chama o método com os parâmetros corretos  
✅ Erros são logados sem quebrar o save()  

---

### 🔒 **Segurança e Tratamento de Erro**

**Código Inline (eval):**

⚠️ **IMPORTANTE:** O código é executado com `eval()` em uma closure isolada.

✅ **Proteções implementadas:**
- Try-catch automático — erros não quebram o save()
- Log detalhado de erros no `storage/logs/laravel.log`
- Acesso restrito ao modal via `@ptahCan('configCrud', 'read')`
- Contexto isolado — variáveis limitadas ao escopo do hook

**Classes PHP:**

✅ **Mais seguro:**
- Sem eval() — código compilado pelo PHP
- Validação de sintaxe pelo IDE e CI/CD
- Erros detectados em tempo de desenvolvimento
- Class-not-found e method-not-found tratados automaticamente

**Exemplo de log de erro (inline):**

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

**Exemplo de log de erro (classe):**

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

**Restrições (código inline):**

❌ **Não funciona:**
- `return` para interromper execução (use exceptions se necessário)
- Acesso a variáveis externas não documentadas
- Declaração de classes ou funções

✅ **Funciona:**
- Modificar `$data` por referência nos hooks `before*`
- Acessar `$record` para ler dados do registro
- Usar facades Laravel (`Log`, `Cache`, `DB`, etc)
- Disparar eventos, jobs, notificações
- Executar queries SQL adicionais

---

### 💾 **JSON salvo**

**Código inline:**

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

**Classes PHP:**

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

**Híbrido (misturado):**

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

### 🚀 **Quick Start: Criando sua primeira classe de hooks**

**Opção A — Artisan (recomendado):**

```bash
php artisan ptah:hooks ProductHooks
```

Cria `app/CrudHooks/ProductHooks.php` com os 4 métodos pré-preenchidos, pronto para editar.

Com subpasta:
```bash
php artisan ptah:hooks Inventory/StockHooks
```

**Opção B — Copiar o template de exemplo:**

```bash
cp vendor/ptah/ptah/docs/ProductHooks.example.php app/CrudHooks/ProductHooks.php
```

**Estrutura gerada:**

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

> 💡 Implementar `Ptah\Contracts\CrudHooksInterface` garante autocomplete e validação pelo PHPStan/Psalm.

> ⚠️ **Sobre `$component`:** É a instância completa do componente Livewire. Prefira modificar apenas `$data` (nos hooks `before*`) e disparar eventos nos hooks `after*` — evite alterar estado interno do componente diretamente.

**2. Configure no modal:**

No campo "Before Create", escreva apenas:
```
@ProductHooks
```

Ou especificando o método:
```
@ProductHooks::beforeCreate
```

**3. Pronto!** Os hooks serão executados automaticamente em create/update.

> 📁 **Arquivo de referência:** [ProductHooks.example.php](ProductHooks.example.php)  
> - 300+ linhas de código documentado  
> - 20+ casos de uso práticos  
> - Métodos auxiliares reutilizáveis  
> - Pronto para copiar e adaptar  

---

### Salvando a Configuração

1. Clique no botão **"Salvar"** (canto superior direito do modal)
2. O sistema:
   - Valida os dados
   - Salva na tabela `crud_configs`
   - Invalida o cache
   - Dispara evento `ptah:crud-config-updated`
3. O BaseCrud recarrega automaticamente a config

### Eventos Disparados

| Evento | Quando | Payload |
|--------|--------|---------|
| `ptah:crud-config-updated` | Após salvar o modal | `{ model: 'Product' }` |

**Escutando no BaseCrud:**

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

### Visão Geral

O comando `ptah:config` permite configurar CRUDs via terminal, oferecendo dois modos:

| Modo | Sintaxe | Melhor Para |
|------|---------|-------------|
| **Interactive** | `ptah:config "App\Models\Product"` | Primeira configuração, exploração |
| **Declarative** | `ptah:config "App\Models\Product" --column="..." --action="..."` | Automação, scripts, CI/CD |

### Sintaxe Básica

```bash
php artisan ptah:config {model} [options]
```

### Opções Disponíveis

| Opção | Tipo | Descrição |
|-------|------|-----------|
| `--column=*` | Array | Adiciona/atualiza coluna |
| `--action=*` | Array | Adiciona ação customizada |
| `--filter=*` | Array | Adiciona filtro customizado |
| `--style=*` | Array | Adiciona regra de estilo |
| `--join=*` | Array | Adiciona JOIN |
| `--set=*` | Array | Define configuração geral |
| `--permission=*` | Array | Define permissão |
| `--list` | Flag | Lista configuração atual |
| `--reset` | Flag | Reseta para padrão |
| `--import=` | String | Importa de JSON |
| `--export=` | String | Exporta para JSON |
| `--non-interactive` | Flag | Pula wizard |
| `--force` | Flag | Sobrescreve sem confirmar |
| `--dry-run` | Flag | Simula sem salvar |
| `--only=*` | Array | Processa apenas seções específicas |
| `--skip=*` | Array | Pula seções específicas |

### Modo Interactive (Wizard)

Execute sem opções para iniciar o wizard:

```bash
php artisan ptah:config "App\Models\Product"
```

**Fluxo do wizard:**

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

**Vantagens do wizard:**
- ✅ Guia passo-a-passo
- ✅ Sugestões inteligentes baseadas na model
- ✅ Preview antes de salvar
- ✅ Validação em tempo real
- ✅ Opção de refazer antes de confirmar

### Modo Declarative (Inline)

Execute com opções para configurar diretamente:

```bash
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name:validation=required|max:255" \
  --column="sku:text:required:validation=required|unique:products,sku" \
  --column="price:number:required:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2" \
  --column="stock:number:label=Stock:renderer=number:rendererDecimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red" \
  --column="category_id:searchdropdown:relation=category:sdSelectColumn=name:sdValueColumn=id" \
  --set="itemsPerPage=25" \
  --set="cacheEnabled=true" \
  --set="cacheTtl=3600"
```

### Sintaxe das Opções

#### --column (Colunas)

**Formato:**
```
field:type:modifier:option=value:option=value...
```

**Partes:**
1. `field` — Nome físico da coluna (obrigatório)
2. `type` — Tipo da coluna (obrigatório): `text`, `textarea`, `number`, `date`, `datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image`
3. `modifier` — Modificador opcional (shorthand): `required`, `optional`, `readonly`, `hidden`, `noFilter`, `noSave`, `total`
4. `option=value` — Pares chave=valor para configurações adicionais

**Modifiers (shorthands):**

| Modifier | Equivale a | Descrição |
|----------|------------|-----------|
| `required` | `colsRequired = true` | Campo obrigatório |
| `optional` | `colsRequired = false` | Campo opcional |
| `readonly` | `colsEditableForm = false` | Somente leitura no formulário |
| `hidden` | `colsVisibleList = false` | Não exibir na listagem |
| `noFilter` | `colsIsFilterable = false` | Não filtrável |
| `noSave` | `colsGravar = false` | Não salvar no banco |
| `total` | `colsTotal = true` | Adicionar ao totalizador |

**Options (chave=valor):**

| Option | Mapeamento | Tipo | Exemplo |
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
| `badges` | `colsRendererBadges` | array | `badges=active:green,inactive:red` |
| `mask` | `colsMask` | string | `mask=money_brl` |
| `maskTransform` | `colsMaskTransform` | string | `maskTransform=money_to_float` |
| `maskDecimalPlaces` | `colsMaskDecimalPlaces` | int | `maskDecimalPlaces=2` |
| `validation` | `colsValidation` | array | `validation=required\|email` |
| `options` | `colsOptions` | array | `options=yes:Yes,no:No` |
| `relation` | `colsRelation` | string | `relation=category` |
| `sdTable` | `colsSdTable` | string | `sdTable=categories` |
| `sdSelectColumn` | `colsSdSelectColumn` | string | `sdSelectColumn=name` |
| `sdValueColumn` | `colsSdValueColumn` | string | `sdValueColumn=id` |
| `uploadPath` | `colsUploadPath` | string | `uploadPath=products/images` |
| `totalizer` | `colsTotal` | bool | `totalizer=true` |
| `totalizadorType` | `totalizadorType` | string | `totalizadorType=sum` |

**Exemplos:**

```bash
# Texto simples obrigatório
--column="name:text:required:label=Product Name"

# Email com validação
--column="email:text:required:validation=required|email|max:255"

# Preço com máscara e renderer
--column="price:number:required:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2"

# Status com select e badges
--column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red,pending:yellow"

# SearchDropdown para categoria
--column="category_id:searchdropdown:relation=category:sdSelectColumn=name:sdValueColumn=id"

# Data readonly
--column="created_at:datetime:readonly:renderer=datetime:rendererFormat=d/m/Y H:i:s"

# Booleano
--column="active:boolean:default=true"

# Descrição com textarea
--column="description:textarea:optional:placeholder=Enter description"

# Imagem com upload
--column="image:image:uploadPath=products:uploadMaxSize=2048:uploadAllowedTypes=jpg,png,webp"

# Número com totalizador
--column="quantity:number:total:totalizadorType=sum:totalizadorFormat=number"
```

#### --action (Ações)

**Formato:**
```
name:type:value:icon=icon:color=color:confirm=bool
```

**Exemplos:**

```bash
# Livewire action com confirmação
--action="approve:livewire:approve(%id%):icon=bx-check:color=success:confirm=true"

# Link externo
--action="view:link:https://example.com/products/%id%:icon=bx-show:color=primary"

# JavaScript action
--action="export:javascript:exportData():icon=bx-download:color=info"

#Ação com confirmação customizada
--action="delete:livewire:deleteCustom(%id%):icon=bx-trash:color=danger:confirm=true:confirmMessage=Are you sure?"
```

#### --filter (Filtros)

**Formato:**
```
field:type:operator:label=Label:options=opt1,opt2
```

**Exemplos:**

```bash
# Select simples
--filter="status:select:=:label=Status:options=active,inactive,pending"

# Número com mínimo
--filter="price:number:>=:label=Minimum Price"

# Data
--filter="created_at:date:>=:label=From Date"

# SearchDropdown
--filter="user_id:searchdropdown:=:label=User:sdTable=users:sdSelectColumn=name:sdValueColumn=id"

# Texto com LIKE
--filter="name:text:LIKE:label=Search Name"
```

#### --style (Estilos)

**Formato:**
```
field:operator:value:background=color:color=textColor:fontWeight=weight
```

**Exemplos:**

```bash
# Status cancelado em vermelho
--style="status:==:cancelled:background=#FEE2E2:color=#991B1B:fontWeight=bold"

# Prioridade alta em amarelo
--style="priority:>:5:background=#FEF3C7:color=#92400E"

# Estoque baixo
--style="stock:<:10:background=#DBEAFE:color=#1E40AF:fontWeight=normal"
```

#### --join (JOINs)

**Formato:**
```
type:table:leftCol=rightCol:select=field1,field2:where=condition
```

**Exemplos:**

```bash
# LEFT JOIN com users
--join="left:users:products.user_id=users.id:select=name,email"

# INNER JOIN com categories
--join="inner:categories:products.category_id=categories.id:select=name as category_name:where=categories.active=1"

# JOIN com DISTINCT
--join="left:suppliers:products.supplier_id=suppliers.id:select=name:distinct=true"
```

#### --set (Configurações Gerais)

**Formato:**
```
key=value
```

**Exemplos:**

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

#### --permission (Permissões)

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

### Comandos Especiais

#### --list (Listar Configuração)

Exibe a configuração atual em formato de tabela:

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

#### --reset (Resetar Configuração)

Remove toda a configuração e volta ao padrão:

```bash
php artisan ptah:config "App\Models\Product" --reset
```

**Prompt:**
```
Are you sure you want to reset all configuration for App\Models\Product? [yes/no]
> yes
✓ Configuration reset successfully!
```

#### --import (Importar de JSON)

Importa configuração de um arquivo JSON:

```bash
php artisan ptah:config "App\Models\Product" --import=product-config.json
```

**Formato do JSON:**

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

#### --export (Exportar para JSON)

Exporta configuração atual para arquivo JSON:

```bash
php artisan ptah:config "App\Models\Product" --export=product-config.json
```

**Resultado:**
```
✓ Configuration exported successfully to product-config.json
```

**Uso típico:**

```bash
# 1. Exportar config de produção
php artisan ptah:config "App\Models\Product" --export=product-config.json

# 2. Commit no Git
git add product-config.json
git commit -m "chore: export Product CRUD config"
git push

# 3. Deploy em homolog/dev
git pull
php artisan ptah:config "App\Models\Product" --import=product-config.json
```

#### --dry-run (Simular sem Salvar)

Mostra as mudanças que seriam aplicadas sem salvar:

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

#### --only / --skip (Processar Seções Específicas)

Limita ou exclui seções da configuração:

```bash
# Configurar apenas colunas e ações
php artisan ptah:config "App\Models\Product" \
  --only=columns,actions \
  --column="name:text:required"

# Configurar tudo exceto JOINs e estilos
php artisan ptah:config "App\Models\Product" \
  --skip=joins,styles \
  --column="price:number"
```

**Seções disponíveis:**
- `columns`
- `actions`
- `filters`
- `styles`
- `joins`
- `general`
- `permissions`

---

## Comparação: Modal vs CLI

| Critério | Modal Visual | Comando CLI |
|----------|--------------|-------------|
| **Interface** | Gráfica, intuitiva | Terminal, texto |
| **Curva de Aprendizado** | Baixa | Média |
| **Velocidade (1ª vez)** | Lenta | Média |
| **Velocidade (repetido)** | Média | Rápida |
| **Versionamento** | ❌ Não | ✅ Sim (export JSON) |
| **Automação** | ❌ Não | ✅ Sim (scripts) |
| **CI/CD** | ❌ Não | ✅ Sim |
| **Batch Operations** | ❌ Uma por vez | ✅ Loop múltiplas models |
| **Preview Visual** | ✅ Sim | ❌ Não |
| **Drag-and-Drop** | ✅ Sim | ❌ Não |
| **Color Picker** | ✅ Sim | ❌ Hex manual |
| **Smart Suggestions** | ⚠️ Limitado | ✅ Sim (AI-based) |
| **Requer Auth** | ✅ Sim | ❌ Não |
| **Requer Browser** | ✅ Sim | ❌ Não |
| **Offline** | ❌ Não | ✅ Sim |
| **Undo/Redo** | ⚠️ Manual | ✅ Git revert |
| **Documentação** | ⚠️ Tooltips | ✅ `--help` |

**Recomendação:**

- 🎨 **Modal Visual:** Melhor para primeira configuração, ajustes pontuais, exploração de opções
- 💻 **Comando CLI:** Melhor para automação, múltiplas models, versionamento, CI/CD, reprodutibilidade

**Workflow Ideal:**

1. Configure a primeira model via **Modal Visual** (exploração)
2. Exporte para JSON: `php artisan ptah:config "App\Models\Product" --export=product.json`
3. Versione o JSON no Git
4. Crie script para outras models baseado no JSON
5. Use CLI para ajustes em lote

---

## Estrutura do CrudConfig (JSON)

Configuração completa salva na tabela `crud_configs.config`:

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

## Configuração de Colunas

Propriedades de cada coluna em `cols[]`:

### Propriedades Básicas

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsNomeFisico` | string | — | Nome físico da coluna no banco (obrigatório) |
| `colsNomeLogico` | string | `ucfirst(colsNomeFisico)` | Label de exibição |
| `colsTipo` | string | `'text'` | Tipo do input: `text`, `textarea`, `number`, `date`, `datetime`, `select`, `searchdropdown`, `boolean`, `file`, `image` |
| `colsGravar` | bool | `true` | Salvar valor no banco |
| `colsRequired` | bool | `false` | Campo obrigatório |
| `colsIsFilterable` | bool | `true` | Permite filtrar por esta coluna |
| `colsVisibleList` | bool | `true` | Exibir na listagem |
| `colsEditableForm` | bool | `true` | Editável no formulário |
| `colsAlign` | string | `'text-start'` | Alinhamento: `text-start`, `text-center`, `text-end` |
| `colsWidth` | string | `'auto'` | Largura da coluna: `120px`, `20%`, `auto` |
| `colsSource` | string | `''` | Badge indicando fonte SQL (ex: `JOIN categories`) |

### Estilo de Célula

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsCellStyle` | string | `''` | CSS inline para a célula (ex: `background: #FEE;`) |
| `colsCellClass` | string | `''` | Classes CSS para a célula |
| `colsCellIcon` | string | `''` | Ícone no cabeçalho (ex: `bx-user`, `fa-user`) |
| `colsMinWidth` | string | `''` | Largura mínima (ex: `100px`) |

### Exibição e Renderização

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsHelper` | string | `''` | Texto de ajuda abaixo do campo |
| `colsRenderer` | string | `'text'` | Tipo de renderer: `text`, `badge`, `pill`, `boolean`, `money`, `date`, `datetime`, `link`, `image`, `truncate`, `number`, `filesize`, `duration`, `code`, `color`, `progress`, `rating`, `qrcode` |
| `colsRendererLink` | string | `''` | URL pattern para renderer `link` (ex: `/products/%id%`) |
| `colsRendererTarget` | string | `'_self'` | Target do link: `_self`, `_blank` |
| `colsRendererCurrency` | string | `'BRL'` | Moeda para renderer `money`: `BRL`, `USD`, `EUR` |
| `colsRendererDecimals` | int | `2` | Casas decimais para renderer `money` ou `number` |
| `colsRendererPrefix` | string | `''` | Prefixo para renderer `number` |
| `colsRendererSuffix` | string | `''` | Sufixo para renderer `number` |
| `colsRendererFormat` | string | `''` | Formato para renderer `date`/`datetime` (ex: `d/m/Y H:i:s`) |
| `colsRendererMaxChars` | int | `50` | Máximo de caracteres para renderer `truncate` |
| `colsRendererBadges` | array | `[]` | Mapa valor→cor para renderers `badge`/`pill` |

### Relação

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsRelacao` | string | `''` | Nome do método de relação Eloquent |
| `colsRelacaoExibe` | string | `'name'` | Campo a exibir da relação |
| `colsRelacaoNested` | string | `''` | Relação aninhada (ex: `category.parent.name`) |
| `colsSDModel` | string | `''` | Model completa para SearchDropdown (ex: `App\Models\Category`) |
| `colsSDLabel` | string | `'name'` | Campo de exibição no SearchDropdown |
| `colsSDValor` | string | `'id'` | Campo de valor no SearchDropdown |
| `colsSDOrder` | string | `'name ASC'` | Ordenação do SearchDropdown |
| `colsSDTipo` | string | `'searchdropdown'` | Tipo do SearchDropdown |
| `colsSDMode` | string | `'single'` | Modo: `single`, `multiple` |

### Máscara de Input

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsMask` | string | `''` | Máscara de input: `money_brl`, `money_usd`, `percent`, `cpf`, `cnpj`, `rg`, `pis`, `ncm`, `ean13`, `phone`, `cep`, `plate`, `credit_card`, `date`, `datetime`, `time`, `integer`, `uppercase`, `custom_regex` |
| `colsMaskTransform` | string | `''` | Transformação ao salvar: `money_to_float`, `digits_only`, `plate_clean`, `date_br_to_iso`, `date_iso_to_br`, `uppercase`, `lowercase`, `trim` |
| `colsMaskDecimalPlaces` | int | `2` | Casas decimais para máscaras de dinheiro |
| `colsMaskEmptyValue` | string | `''` | Valor salvo quando campo vazio |

### Validação

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsValidations` | array | `[]` | Array de regras de validação: `["required", "email", "max:255"]` |
| `colsValidationMessage` | string | `''` | Mensagem de erro customizada |

### Select Options

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsOptions` | array | `[]` | Opções para `<select>`: `{"value": "Label"}` |
| `colsOptionsFrom` | string | `''` | Método que retorna as opções dinamicamente |

### Upload de Arquivo

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsUploadPath` | string | `''` | Caminho de upload (ex: `products/images`) |
| `colsUploadDisk` | string | `'public'` | Disco de storage: `public`, `s3`, etc |
| `colsUploadMaxSize` | int | `2048` | Tamanho máximo em KB |
| `colsUploadAllowedTypes` | array | `[]` | Extensões permitidas: `["jpg", "png", "webp"]` |
| `colsUploadMultiple` | bool | `false` | Upload múltiplo |

### Totalizador

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsTotal` | bool | `false` | Adicionar ao totalizador |
| `totalizadorType` | string | `'sum'` | Tipo: `sum`, `count`, `avg`, `max`, `min` |
| `totalizadorFormat` | string | `'number'` | Formato: `currency`, `number`, `integer` |
| `totalizadorCurrency` | string | `'BRL'` | Moeda para formato currency |
| `totalizadorDecimals` | int | `2` | Casas decimais |
| `totalizadorPrefix` | string | `''` | Prefixo (ex: `$`) |
| `totalizadorSuffix` | string | `''` | Sufixo (ex: `un`) |

### Avançado

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsOrderBy` | string | `''` | Ordenação customizada (ex: `FIELD(status, 'pending', 'active', 'inactive')`) |
| `colsReverse` | bool | `false` | Inverter ordem de valores (para arrays) |
| `colsMetodoCustom` | string | `''` | Método accessor customizado na model |
| `colsPlaceholder` | string | `''` | Placeholder do input |
| `colsDefaultValue` | mixed | `null` | Valor padrão ao criar |

---

## Configuração de Ações

Propriedades de cada ação em `actions[]`:

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `actionName` | string | — | Nome único da ação (obrigatório) |
| `actionLabel` | string | `ucfirst(actionName)` | Label de exibição no botão |
| `actionType` | string | `'livewire'` | Tipo: `livewire`, `link`, `javascript` |
| `actionValue` | string | — | Valor/comando da ação (obrigatório) |
| `actionIcon` | string | `''` | Ícone do botão (ex: `bx-check`, `fa-check`) |
| `actionColor` | string | `'primary'` | Cor do botão: `primary`, `success`, `danger`, `warning`, `info`, `secondary` |
| `actionPosition` | string | `'row'` | Posição: `row` (por linha), `bulk` (bulk action), `both` |
| `actionConfirm` | bool | `false` | Exigir confirmação |
| `actionConfirmMessage` | string | `''` | Mensagem de confirmação |
| `actionPermission` | string | `''` | Gate/ability necessária |

**Placeholders suportados em `actionValue`:**
- `%id%` → ID do registro
- `%field%` → Valor de qualquer campo (ex: `%email%`, `%name%`)

**Exemplos:**

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

## Configuração de Filtros

Propriedades de cada filtro em `filters[]`:

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `colsFilterField` | string | — | Campo a filtrar (obrigatório) |
| `colsFilterLabel` | string | `ucfirst(field)` | Label do filtro |
| `colsFilterType` | string | `'text'` | Tipo: `text`, `number`, `date`, `select`, `searchdropdown` |
| `colsFilterOperator` | string | `'='` | Operador: `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE` |
| `colsFilterPlaceholder` | string | `''` | Placeholder do input |
| `colsFilterOptions` | array | `[]` | Opções para tipo `select`: `{"value": "Label"}` |
| `colsFilterWhereHas` | string | `''` | Nome da relação para `whereHas` |
| `colsFilterRelationField` | string | `''` | Campo na relação para `whereHas` |
| `colsFilterAggregate` | string | `''` | Função de agregação: `SUM`, `COUNT`, `AVG`, `MAX`, `MIN` |
| `colsFilterSdTable` | string | `''` | Tabela para SearchDropdown |
| `colsFilterSdSelectColumn` | string | `'name'` | Coluna de exibição para SearchDropdown |
| `colsFilterSdValueColumn` | string | `'id'` | Coluna de valor para SearchDropdown |

**Exemplos:**

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

## Configuração de Estilos

Propriedades de cada regra de estilo em `styles[]`:

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `styleField` | string | — | Campo a verificar (obrigatório) |
| `styleOperator` | string | `'=='` | Operador: `==`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE` |
| `styleValue` | mixed | — | Valor de comparação (obrigatório) |
| `styleBackgroundColor` | string | `''` | Cor de fundo (ex: `#FEE2E2`) |
| `styleColor` | string | `''` | Cor do texto (ex: `#991B1B`) |
| `styleFontWeight` | string | `'normal'` | Peso da fonte: `normal`, `bold`, `lighter`, `bolder` |
| `styleCustom` | string | `''` | CSS customizado (ex: `border: 2px solid red;`) |

**Exemplo:**

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

**Resultado:** Linhas onde `status === 'cancelled'` terão:
- Background vermelho claro
- Texto vermelho escuro
- Fonte em negrito

---

## Configuração de JOINs

Propriedades de cada JOIN em `joins[]`:

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `joinTable` | string | — | Tabela a juntar (obrigatório) |
| `joinType` | string | `'left'` | Tipo de JOIN: `left`, `inner` |
| `joinLeftColumn` | string | — | Coluna da tabela principal (obrigatório) |
| `joinRightColumn` | string | — | Coluna da tabela juntada (obrigatório) |
| `joinSelect` | array | `[]` | Campos a selecionar: `["table.field as alias"]` |
| `joinDistinct` | bool | `false` | Usar `DISTINCT` |
| `joinWhere` | string | `''` | Condição `WHERE` adicional (ex: `table.active = 1`) |

**Exemplo:**

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

**SQL Resultante:**

```sql
LEFT JOIN categories 
  ON products.category_id = categories.id 
  AND categories.active = 1
SELECT categories.name as category_name, categories.slug as category_slug
```

---

## Configurações Gerais

Propriedades no nível raiz do config:

### Identificação

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `displayName` | string | `class_basename($model)` | Nome de exibição do CRUD |

### Cache

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `cacheEnabled` | bool | `true` | Habilitar cache |
| `cacheTtl` | int | `3600` | Tempo de vida do cache (segundos) |

### Paginação

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `paginationEnabled` | bool | `true` | Habilitar paginação |
| `itemsPerPage` | int | `25` | Itens por página |
| `paginationOptions` | array | `[10, 25, 50, 100]` | Opções de itens por página |

### Busca

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `searchEnabled` | bool | `true` | Habilitar busca global |
| `searchPlaceholder` | string | `'Search...'` | Placeholder do campo de busca |

### Exportação

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `exportEnabled` | bool | `true` | Habilitar exportação |
| `exportMaxRows` | int | `10000` | Máximo de linhas exportáveis |
| `exportFormats` | array | `['pdf', 'excel', 'csv']` | Formatos habilitados |
| `pdfOrientation` | string | `'landscape'` | Orientação do PDF: `landscape`, `portrait` |
| `pdfPaperSize` | string | `'A4'` | Tamanho do papel: `A4`, `Letter`, etc |

### Aparência

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `theme` | string | `'light'` | Tema visual: `light`, `dark` |
| `compactMode` | bool | `false` | Modo compacto |
| `striped` | bool | `true` | Linhas zebradas |
| `hover` | bool | `true` | Efeito hover nas linhas |
| `showRowNumbers` | bool | `true` | Exibir número da linha |
| `tableClass` | string | `''` | Classes CSS extras para a tabela |

### Multi-tenant

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `companyField` | string | `'company_id'` | Campo de empresa para filtro multi-tenant |

### Soft Deletes

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `softDeletes` | bool | `false` | Usar soft deletes |
| `showTrashed` | bool | `false` | Exibir deletados por padrão |

### Filtro Rápido de Data

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `quickDateColumn` | string | `'created_at'` | Coluna para filtro rápido de data |

### Broadcast (Tempo Real)

| Propriedade | Tipo | Padrão | Descrição |
|-------------|------|--------|-----------|
| `broadcastEnabled` | bool | `false` | Habilitar Echo listener |
| `broadcastChannel` | string | `'page-{model}-observer'` | Nome do canal |
| `broadcastEvent` | string | `'.page{Model}Observer'` | Nome do evento |

**Exemplo de uso:**

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

## Configuração de Permissões

Propriedades em `permissions`:

| Chave | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `list` | string | `''` | Gate para listar registros |
| `view` | string | `''` | Gate para visualizar um registro |
| `create` | string | `''` | Gate para criar registro |
| `edit` | string | `''` | Gate para editar registro |
| `delete` | string | `''` | Gate para deletar registro |
| `export` | string | `''` | Gate para exportar |
| `import` | string | `''` | Gate para importar |
| `restore` | string | `''` | Gate para restaurar soft-deleted |
| `forceDelete` | string | `''` | Gate para deletar permanentemente |

**Exemplo:**

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

**Uso no BaseCrud:**

```php
if (!Gate::allows($this->crudConfig['permissions']['create'] ?? 'create')) {
    abort(403, 'Unauthorized');
}
```

---

## Exemplos Práticos Completos

### Exemplo 1: E-commerce Product

**Via Modal:**
1. Abra o modal de configuração
2. Configure colunas:
   - `name` → text, required
   - `sku` → text, required, unique
   - `price` → number, money renderer, mask money_brl
   - `cost` → number, money renderer, mask money_brl
   - `stock` → number, number renderer
   - `status` → select (active/inactive), badge renderer
   - `category_id` → searchdropdown
3. Adicione ação customizada "Duplicate"
4. Configure filtros de status e categoria
5. Adicione estilo condicional para baixo estoque
6. Configure totalizador para preço e custo
7. Salve

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name:validation=required|max:255" \
  --column="sku:text:required:label=SKU:validation=required|unique:products,sku" \
  --column="price:number:required:label=Price:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2:validation=required|numeric|min:0:totalizer=true:totalizadorType=sum" \
  --column="cost:number:label=Cost:mask=money_brl:renderer=money:rendererCurrency=BRL:rendererDecimals=2:validation=numeric|min:0:totalizer=true:totalizadorType=sum" \
  --column="stock:number:label=Stock:renderer=number:rendererDecimals=0:validation=integer|min:0" \
  --column="status:select:required:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red" \
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

### Exemplo 2: CRM Contacts

**Via CLI:**

```bash
php artisan ptah:config "App\Models\Contact" \
  --column="name:text:required:label=Full Name:validation=required|max:255" \
  --column="email:text:required:label=Email:validation=required|email|unique:contacts,email" \
  --column="phone:text:label=Phone:mask=phone:validation=phone" \
  --column="company:text:label=Company" \
  --column="position:text:label=Position" \
  --column="lead_status:select:required:options=new:New,contacted:Contacted,qualified:Qualified,lost:Lost:renderer=badge:badges=new:blue,contacted:yellow,qualified:green,lost:red" \
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

### Exemplo 3: Blog Posts

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
  --column="status:select:required:options=draft:Draft,published:Published,scheduled:Scheduled:renderer=badge:badges=draft:gray,published:green,scheduled:blue" \
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

## Workflow Recomendado

### Workflow 1: Desenvolvimento Inicial

```bash
# 1. Gerar CRUD com ptah:forge
php artisan ptah:forge Post --fields="title:string,content:text,status:enum(draft|published)"
php artisan migrate

# 2. Configurar via Modal Visual (primeira vez)
# - Abra o navegador → /posts
# - Clique no botão ⚙️ Config
# - Configure colunas, ações, filtros visualmente
# - Salve

# 3. Exportar configuração para versionamento
php artisan ptah:config "App\Models\Post" --export=config/cruds/post.json

# 4. Commit no Git
git add config/cruds/post.json
git commit -m "feat: add Post CRUD config"
```

### Workflow 2: Replicar em Outros Ambientes

```bash
# 1. Pull do Git
git pull origin main

# 2. Importar configuração
php artisan ptah:config "App\Models\Post" --import=config/cruds/post.json

# 3. Verificar se funcionou
php artisan ptah:config "App\Models\Post" --list
```

### Workflow 3: Configurar Múltiplas Models

```bash
# 1. Criar script de configuração
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

# 2. Executar
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

### Workflow 5: Backup e Restore

```bash
# Backup de todas as configurações
mkdir -p backups/cruds/$(date +%Y-%m-%d)

php artisan ptah:config "App\Models\Product" --export=backups/cruds/$(date +%Y-%m-%d)/product.json
php artisan ptah:config "App\Models\Category" --export=backups/cruds/$(date +%Y-%m-%d)/category.json
php artisan ptah:config "App\Models\User" --export=backups/cruds/$(date +%Y-%m-%d)/user.json

# Restore de backup
BACKUP_DATE="2026-03-01"
php artisan ptah:config "App\Models\Product" --import=backups/cruds/${BACKUP_DATE}/product.json
php artisan ptah:config "App\Models\Category" --import=backups/cruds/${BACKUP_DATE}/category.json
php artisan ptah:config "App\Models\User" --import=backups/cruds/${BACKUP_DATE}/user.json
```

---

## Import/Export de Configurações

### Estrutura de Diretório Recomendada

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

### Export Completo

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

### Import Completo

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

### Problema 1: Config não aparece no CRUD

**Sintomas:**
- Modal salvo mas CRUD não reflete mudanças
- Comando executado mas tabela não muda

**Causa:** Cache não invalidado

**Solução:**

```bash
# Limpar cache manualmente
php artisan cache:forget "crud_config_Product"

# Ou limpar todo o cache
php artisan cache:clear

# Verificar se config está no banco
php artisan ptah:config "App\Models\Product" --list
```

### Problema 2: Comando não encontrado

**Sintomas:**
```
Command "ptah:config" is not defined.
```

**Causa:** Comando não registrado no ServiceProvider

**Solução:**

```bash
# 1. Verificar se comando está registrado
grep -r "ConfigCommand" vendor/jonytonet/ptah/src/

# 2. Limpar cache de comandos
php artisan optimize:clear

# 3. Re-executar
php artisan ptah:config "App\Models\Product"
```

### Problema 3: Import falha com erro de JSON

**Sintomas:**
```
Invalid JSON file: Syntax error
```

**Causa:** JSON malformado

**Solução:**

```bash
# Validar JSON
cat config/cruds/product.json | jq .

# Se erro, corrigir manualmente ou:
php artisan ptah:config "App\Models\Product" --reset
php artisan ptah:config "App\Models\Product" --export=config/cruds/product.json
```

### Problema 4: Permissões não funcionam

**Sintomas:**
- Gates configurados mas usuário consegue acessar

**Causa:** Gates não definidos no AuthServiceProvider

**Solução:**

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

### Problema 5: Syntax error no comando CLI

**Sintomas:**
```
Parse error in column syntax
```

**Causa:** Sintaxe incorreta na opção `--column`

**Solução:**

```bash
# ❌ Errado (faltam aspas)
php artisan ptah:config App\Models\Product --column=name:text

# ✅ Correto
php artisan ptah:config "App\Models\Product" --column="name:text"

# ❌ Errado (pipe sem escapar)
--column="email:text:validation=required|email"

# ✅ Correto (pipe escapado ou entre aspas)
--column="email:text:validation=required\|email"
--column='email:text:validation=required|email'
```

### Problema 6: Modal não salva alterações

**Sintomas:**
- Clica em "Salvar" mas mudanças não persistem

**Causa:** JavaScript error ou validação falhando

**Solução:**

```bash
# 1. Verificar console do navegador (F12)
# Procurar erros JavaScript

# 2. Verificar logs do Laravel
tail -f storage/logs/laravel.log

# 3. Verificar permissões da tabela crud_configs
php artisan tinker
>>> DB::table('crud_configs')->where('model', 'Product')->first()
```

### Problema 7: Import não sobrescreve config existente

**Sintomas:**
- Import executado mas config antiga permanece

**Causa:** Precisa usar `--force`

**Solução:**

```bash
php artisan ptah:config "App\Models\Product" --import=config/cruds/product.json --force
```

---

## Links Relacionados

- [BaseCrud Documentation](BaseCrud.md) — Documentação completa do componente BaseCrud
- [Commands Documentation](Commands.md) — Todos os comandos Artisan do Ptah
- [Migration Guide](MigrationGuide.md) — Guia de migração entre versões

---

**Última atualização:** 4 de março de 2026  
**Versão:** 2.2.0

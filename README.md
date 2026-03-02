# Ptah 🏛️

> **Ptah** — deus egípcio da criação, dos artesãos e arquitetos. Patrono de quem constrói coisas belas e funcionais.

**Ptah** é um pacote Laravel que combina **scaffolding de código** com um **sistema de componentes visuais** prontos para uso. Com um único comando você gera toda a estrutura de uma entidade; com uma tag você renderiza interfaces consistentes.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012%2B-red)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-06b6d4)](https://tailwindcss.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## O que é o Ptah?

O pacote é dividido em três subsistemas complementares:

| Subsistema | Descrição |
|---|---|
| **Ptah Forge** | Biblioteca de 26 componentes Blade com Tailwind v4 + Alpine.js (`<x-forge-*>`) |
| **ptah:forge** | Gerador de scaffolding SOLID que cria toda a estrutura de uma entidade com um único comando |
| **BaseCrud** | Componente Livewire dinâmico gerado automaticamente — tabela, filtros, modal create/edit, soft delete, exportação e preferências, tudo configurado via JSON no banco |

---

## Sumário

- [Requisitos](#-requisitos)
- [Instalação](#-instalação)
- [Ptah Forge — Componentes](#-ptah-forge--componentes)
  - [Layout Dashboard](#layout-dashboard)
  - [Dark Mode automático](#dark-mode-automático)
  - [Sidebar — collapse / expand](#sidebar--collapse--expand)
  - [Layout Auth](#layout-auth)
  - [Componentes disponíveis](#componentes-disponíveis)
  - [Demo](#demo)
- [ptah:forge — Scaffolding](#-ptahforge--scaffolding)
  - [Uso básico](#uso-básico)
  - [Subpastas — organização para projetos grandes](#subpastas--organização-para-projetos-grandes)
  - [Definindo campos](#definindo-campos)
  - [Detecção automática de FK](#tipos-suportados)
  - [Modo API](#modo-api)
  - [Arquivos gerados](#arquivos-gerados)
  - [Próximos passos pós-geração](#próximos-passos-pós-geração)
- [Arquitetura das camadas](#-arquitetura-das-camadas)
- [Arquitetura SOLID dos Geradores](#-arquitetura-solid-dos-geradores)
- [Sistema de UserPreferences](#-sistema-de-userpreferences)
- [BaseCrud — Tela Dinâmica Completa](#-basecrud--tela-dinâmica-completa)
  - [Como funciona](#como-funciona)
  - [Como usar o BaseCrud](#como-usar-o-basecrud)
  - [Schema de configuração](#schema-de-configuração-cols)
  - [Tipos de colunas](#tipos-de-colunas-colstipo)
  - [Helpers de formatação](#helpers-de-formatação)
  - [Renderer DSL](#helpers-de-formatação)
  - [Estilos condicionais](#estilos-condicionais-de-linha)
  - [Filtros e FilterService](#filtros-e-filterservice)
  - [SearchDropdown](#searchdropdown)
  - [Exportação](#exportação)
  - [Preferências por usuário](#preferências-por-usuário)
  - [whereHasFilter](#wherehasfilter--pré-filtrado-por-entidade-pai)
  - [Visibilidade de colunas](#visibilidade-de-colunas)
  - [Bulk Actions](#bulk-actions--ações-em-lote)
  - [Filtros rápidos de data](#filtros-rápidos-de-data)
  - [Badges de filtros ativos](#badges-de-filtros-ativos-textfilter)
  - [Busca avançada](#busca-avançada)
  - [colsMetodoCustom](#colsmetodocustom)
  - [CrudConfig Modal](#crudconfig-modal)
  - [FormValidatorService](#formvalidatorservice)
  - [CrudConfigService](#crudconfigservice)
  - [CacheService](#cacheservice)
- [Módulos Opcionais — Auth, Menu, Company & Permissions](#-módulos-opcionais--auth-menu-company--permissions)
  - [Ativando os módulos](#ativando-os-módulos)
  - [Módulo Auth — visão rápida](#módulo-auth--visão-rápida)
  - [Módulo Menu — visão rápida](#módulo-menu--visão-rápida)
  - [Módulo Company — visão rápida](#módulo-company--visão-rápida)
  - [Módulo Permissions — visão rápida](#módulo-permissions--visão-rápida)
- [Configuração](#-configuração)
- [Customizando Stubs](#-customizando-stubs)
- [Testes](#-testes)
- [Laravel Boost — Integração com IA](#-laravel-boost--integração-com-ia)
- [Desenvolvimento com IA](#-desenvolvimento-com-ia)
- [Comandos disponíveis](#-comandos-disponíveis)

---

## 📋 Requisitos

| Requisito | Versão |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 |
| Alpine.js | ^3.x (via CDN ou npm) |
| Livewire | ^3.0 (obrigatório — BaseCrud e `forge-pagination`) |

---

## 🚀 Instalação

### 1. Instale via Composer

```bash
composer require jonytonet/ptah
```

### 2. Execute a instalação

```bash
php artisan ptah:install
```

O comando:
- Publica `config/ptah.php`
- Publica os stubs em `stubs/ptah/`
- Publica e executa as migrations

**Opcional — dados de demonstração:**

```bash
php artisan ptah:install --demo
```

A flag `--demo` ativa o `PtahDemoSeeder` ao final da instalação, criando dados de exemplo prontos para exploração: 2 empresas (`BETA`, `CORP`), departamentos (TI, Comercial, Financeiro), roles (Editor, Viewer) e itens de menu. Ideal para onboarding de novos desenvolvedores.

### 3. Publicação manual (opcional)

```bash
# Configuração
php artisan vendor:publish --tag=ptah-config

# Views e componentes Forge (para customização)
php artisan vendor:publish --tag=ptah-views

# Assets CSS do Forge
php artisan vendor:publish --tag=ptah-assets

# Stubs do scaffolding
php artisan vendor:publish --tag=ptah-stubs

# Migrations
php artisan vendor:publish --tag=ptah-migrations
```

---

## 🎨 Ptah Forge — Componentes

**Ptah Forge** é a biblioteca de componentes visuais do pacote. Usa **Tailwind CSS v4** e **Alpine.js v3** nativamente, sem build steps adicionais.

### Tokens de design

| Token | Valor | Uso |
|---|---|---|
| `primary` | `#5b21b6` | Ações principais, foco |
| `success` | `#10b981` | Confirmações, status OK |
| `danger` | `#ef4444` | Erros, exclusões |
| `warn` | `#f59e0b` | Alertas, atenção |
| `dark` | `#1e293b` | Textos, fundos escuros |

---

### Layout Dashboard

O layout principal para páginas autenticadas. Inclui sidebar, navbar e área de notificações.

**Via componente:**

```blade
<x-forge-dashboard-layout>
    <x-slot:title>Dashboard</x-slot:title>

    <x-forge-stat-card label="Receita" value="R$ 42.890" trend="+12%" />
</x-forge-dashboard-layout>
```

**Via `@extends`:**

```blade
@extends('ptah::layouts.forge-dashboard')

@section('title', 'Produtos')

@section('content')
    {{-- Seu conteúdo aqui --}}
@endsection
```

**Props do `forge-dashboard-layout`:**

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `appName` | string | `config('app.name')` | Nome do sistema |
| `logoUrl` | string | `null` | URL do logo |
| `title` | string | `null` | Título da aba |

---

### Dark Mode automático

O `forge-dashboard-layout` detecta automaticamente a preferência de cor do sistema operacional do usuário via `prefers-color-scheme` e aplica o tema escuro sem nenhuma configuração.

**Como funciona:**
- Na inicialização, lê `localStorage.getItem('ptah_dark_mode')` — se houver override manual, usa ele
- Caso contrário, usa `window.matchMedia('(prefers-color-scheme: dark)').matches`
- Um listener em tempo real reage a mudanças de tema do SO enquanto a página está aberta
- O botão sol/lua na navbar permite ao usuário sobrescrever manualmente; a escolha persiste em `localStorage`

**Comportamentos:**

| Chave localStorage | Valor | Efeito |
|---|---|---|
| `ptah_dark_mode` | `'true'` | Força dark independente do SO |
| `ptah_dark_mode` | `'false'` | Força light independente do SO |
| `ptah_dark_mode` | ausente | Segue o SO automaticamente |

Quando ativo, a classe `ptah-dark` é aplicada no elemento raiz. O CSS define overrides completos para sidebar, navbar e área de conteúdo sob `.ptah-dark`.

> **Independência do BaseCrud:** o tema do layout e o tema do BaseCrud são independentes. O BaseCrud tem seu próprio toggle light/dark no modal de `CrudConfig`, que persiste por componente no banco de dados. Ver [§29 Tema Visual](#tema-visual-light--dark).

---

### Sidebar — collapse / expand

A sidebar possui um botão de colapso/expansão na navbar (ao lado da logo, visível no desktop).

**Comportamento:**
- **Expandida** (`lg:w-64`): ícones + labels visíveis
- **Colapsada** (`lg:w-16`): somente ícones, labels ocultos com transição suave
- O estado é persistido em `localStorage` (`ptah_sidebar_collapsed`) e restaurado automaticamente ao recarregar a página

**Mobile:** comportamento inalterado — a sidebar desliza lateralmente via overlay, ativada pelo botão hamburger (também na navbar)

**Ícone do botão:** retângulo com painel lateral — indica "recolher" quando a sidebar está aberta e "expandir" quando está fechada.

**Grupos accordion (driver `database`):**

Itens do tipo `menuGroup` com filhos renderizam como **accordion Alpine.js** — clique no título do grupo para expandir/recolher os sub-itens. Grupos com a rota ativa iniciam automaticamente abertos.

**Ícones:**

| Formato | Exemplo | Renderizado como |
|---|---|---|
| Classe CSS (Boxicons) — **padrão** | `bx bx-home-alt` | `<i class="bx bx-home-alt">` |
| Classe CSS (Font Awesome) — **padrão** | `fas fa-user` | `<i class="fas fa-user">` |


> Boxicons 2.1.4 e Font Awesome 6.7.2 são as bibliotecas de ícones padrão do Ptah e são carregadas automaticamente pelo `forge-dashboard-layout` via CDN.

---

### Layout Auth

Layout centralizado para páginas de autenticação (login, registro, etc).

```blade
@extends('ptah::layouts.forge-auth')

@section('title', 'Entrar')

@section('content')
    <form method="POST" action="/login">
        @csrf
        <x-forge-input name="email" type="email" label="E-mail" />
        <x-forge-input name="password" type="password" label="Senha" class="mt-4" />
        <x-forge-button type="submit" color="primary" class="w-full mt-6">
            Entrar
        </x-forge-button>
    </form>
@endsection
```

---

### Componentes disponíveis

#### `forge-button`

```blade
<x-forge-button color="primary">Salvar</x-forge-button>
<x-forge-button color="danger" flat>Excluir</x-forge-button>
<x-forge-button color="primary" loading>Processando...</x-forge-button>
<x-forge-button tag="a" href="/dashboard" color="dark">Voltar</x-forge-button>
```

| Prop | Tipo | Valores | Padrão |
|---|---|---|---|
| `color` | string | `primary` `success` `danger` `warn` `dark` `light` `secondary` | `primary` |
| `size` | string | `sm` `md` `lg` | `md` |
| `tag` | string | `button` `a` | `button` |
| `flat` | bool | — | `false` |
| `relief` | bool | — | `false` |
| `rounded` | bool | — | `false` |
| `loading` | bool | — | `false` |
| `disabled` | bool | — | `false` |

> **`color="light"` e `color="secondary"`:** fundo `bg-gray-100`, hover `hover:bg-gray-200`, texto `text-gray-700`. Ideal para botões secundários (ex: Cancelar) que devem ter contraste visível tanto no light quanto no dark mode.

---

#### `forge-input`

```blade
<x-forge-input name="name" label="Nome completo" />
<x-forge-input name="email" type="email" label="E-mail" :error="$errors->first('email')" />
<x-forge-input name="price" type="number" label="Preço" :value="old('price', $product->price)" />
```

| Prop | Tipo | Padrão |
|---|---|---|
| `name` | string | — |
| `label` | string | — |
| `type` | string | `text` |
| `value` | mixed | `null` |
| `placeholder` | string | `' '` |
| `error` | string | `null` |
| `disabled` | bool | `false` |
| `required` | bool | `false` |

> **`type="password"`:** quando o tipo é `password`, o `forge-input` renderiza automaticamente um botão de olho (👁) ao lado direito para alternar visibilidade da senha. Nenhuma prop extra é necessária.

---

#### `forge-select`

```blade
<x-forge-select
    name="status"
    label="Status"
    :options="[
        ['value' => 'active',   'label' => 'Ativo'],
        ['value' => 'inactive', 'label' => 'Inativo'],
    ]"
    :selected="old('status', $item->status)"
/>
```

---

#### `forge-textarea`

```blade
<x-forge-textarea name="description" label="Descrição" :maxlength="500" :rows="4" />
```

---

#### `forge-checkbox` / `forge-radio` / `forge-switch`

```blade
<x-forge-checkbox name="active" label="Ativo" :checked="$item->is_active" color="success" />

<x-forge-radio name="plan" value="pro" label="Pro" :checked="$item->plan === 'pro'" />

<x-forge-switch name="notifications" label="Receber notificações" :checked="true" />
```

---

#### `forge-alert`

```blade
<x-forge-alert type="success" :dismissible="true">
    Registro salvo com sucesso!
</x-forge-alert>

<x-forge-alert type="danger">
    Erro ao processar a requisição.
</x-forge-alert>
```

| `type` | Valores: `info` `success` `warning` `danger` | Padrão: `info` |

---

#### `forge-card`

```blade
<x-forge-card title="Informações do Produto" :hoverable="true">
    <p>Conteúdo do card.</p>

    <x-slot:footer>
        <x-forge-button color="primary">Salvar</x-forge-button>
    </x-slot:footer>
</x-forge-card>
```

---

#### `forge-table`

```blade
<x-forge-table
    :columns="[
        ['key' => 'id',     'label' => 'ID',    'sortable' => true],
        ['key' => 'name',   'label' => 'Nome',  'sortable' => true],
        ['key' => 'status', 'label' => 'Status'],
    ]"
    :rows="$products->map(fn($p) => [
        'id'     => $p->id,
        'name'   => $p->name,
        'status' => $p->status,
        '_actions' => [
            'show'    => route('products.show', $p),
            'edit'    => route('products.edit', $p),
            'destroy' => route('products.destroy', $p),
        ],
    ])->values()->toArray()"
    :searchable="true"
/>
```

---

#### `forge-pagination`

Integra com o paginador do Laravel via Livewire.

```blade
<x-forge-pagination :paginator="$products" />
```

---

#### `forge-modal`

```blade
<div x-data="{ open: false }">
    <x-forge-button @click="open = true" color="primary">Abrir</x-forge-button>

    <x-forge-modal x-model="open" title="Confirmação">
        <p>Deseja confirmar a operação?</p>
        <x-slot:footer>
            <x-forge-button @click="open = false" color="primary">Confirmar</x-forge-button>
            <x-forge-button @click="open = false" color="dark" flat>Cancelar</x-forge-button>
        </x-slot:footer>
    </x-forge-modal>
</div>
```

---

#### `forge-notification`

Notificações flutuantes com auto-close.

```blade
<div x-data="{ show: false, msg: '' }">
    <x-forge-button @click="show = true; msg = 'Salvo!'" color="success">
        Salvar
    </x-forge-button>

    <x-forge-notification
        x-model="show"
        x-bind:message="msg"
        type="success"
        title="Sucesso"
        :auto-close="4000"
    />
</div>
```

> O `forge-dashboard-layout` já inclui uma instância global de `forge-notification`.

---

#### `forge-tabs` + `forge-tab`

`forge-tabs` suporta dois modos de uso:

**Modo 1 — Slot/Livewire** (estado gerenciado externamente pelo componente Livewire):

```blade
{{-- No componente Livewire: public string $activeTab = 'info'; --}}
<x-forge-tabs>
    <x-slot name="tabs">
        <x-forge-tab key="info"    :active="$activeTab === 'info'"    wire:click="$set('activeTab','info')">Dados</x-forge-tab>
        <x-forge-tab key="history" :active="$activeTab === 'history'" wire:click="$set('activeTab','history')">Histórico</x-forge-tab>
    </x-slot>

    @if($activeTab === 'info')
        <p>Conteúdo da aba de dados.</p>
    @endif
    @if($activeTab === 'history')
        <p>Conteúdo do histórico.</p>
    @endif
</x-forge-tabs>
```

**Modo 2 — Array/Alpine** (estado interno, sem Livewire):

```blade
<x-forge-tabs :tabs="[
    ['id' => 'info',    'label' => 'Dados',     'slot' => '<p>Conteúdo dados.</p>'],
    ['id' => 'history', 'label' => 'Histórico', 'slot' => '<p>Conteúdo histórico.</p>'],
]" defaultTab="info" />
```

**Props de `forge-tabs`:**

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `tabs` | array | `[]` | Abas para o Modo Array: `[['id','label','slot']]` |
| `color` | string | `primary` | Cor da aba ativa: `primary` `success` `danger` `warn` |
| `defaultTab` | string | `null` | ID da aba inicial (Modo Array) |

**Props de `forge-tab` (Modo Slot):**

| Prop | Tipo | Padrão | Descrição |
|---|---|---|---|
| `key` | string | `''` | Identificador da aba (informativo) |
| `active` | bool | `false` | Se é a aba selecionada |
| `color` | string | `primary` | Cor quando ativa |

> `forge-tab` aceita qualquer atributo extra (`wire:click`, `@click`, etc.), que é repassado ao `<button>` gerado.

---

#### `forge-stat-card`

```blade
<div class="grid grid-cols-4 gap-4">
    <x-forge-stat-card label="Receita"      value="R$ 42.890" trend="+12.5%" color="primary" />
    <x-forge-stat-card label="Pedidos"      value="1.340"     trend="+8.2%"  color="success" />
    <x-forge-stat-card label="Devoluções"   value="38"        trend="-3.1%"  color="danger" />
    <x-forge-stat-card label="Ticket Médio" value="R$ 320"    trend="+5.0%"  color="warn" />
</div>
```

---

#### Outros componentes

| Componente | Descrição |
|---|---|
| `forge-tab` | Sub-componente aba para uso junto com `forge-tabs` (Modo Slot/Livewire) |
| `forge-badge` | Badges coloridos com suporte a ponto animado |
| `forge-avatar` | Avatar com iniciais, foto ou badge de status |
| `forge-breadcrumb` | Navegação em migalhas de pão |
| `forge-spinner` | Indicadores de carregamento (circle, dots, wave) |
| `forge-progress` | Barra de progresso com animação |
| `forge-list` | Lista de itens com avatar, descrição e badge |
| `forge-stepper` | Passos de um processo (wizard) |
| `forge-chart-card` | Card wrapper para gráficos |
| `forge-navbar` | Navbar superior com dropdown de usuário, botão dark mode (sol/lua) e botão de collapse da sidebar (desktop) |
| `forge-sidebar` | Sidebar responsiva com collapse/expand persistido — icon-only no modo colapsado; suporta **grupos accordion** (Alpine `x-collapse`) e ícones Boxicons/FontAwesome via classe CSS |
| `forge-dashboard-layout` | Layout completo com dark mode automático via OS (`prefers-color-scheme`) e override manual via localStorage |

---

### Demo

Em ambiente local, acesse o showcase completo de todos os componentes:

```
http://seu-app.test/ptah-forge-demo
```

> A rota é registrada automaticamente nos ambientes `local`, `development` e `staging`.

---

## ⚡ ptah:forge — Scaffolding

### Uso básico

```bash
php artisan ptah:forge Product
```

Gera 13+ artefatos em segundos: Model, Migration, DTO, RepositoryInterface, Repository, Service, Controller, StoreRequest, UpdateRequest, Resource, a view `index`, a rota **e a configuração completa do BaseCrud** salva no banco.

> Create, edit e show são gerenciados pelo **BaseCrud via modal Livewire** — nenhuma view adicional é necessária.

---

### Subpastas — organização para projetos grandes

Para projetos com muitas entidades, use o formato `Modulo/Entidade` para organizar todos os artefatos em subpastas:

```bash
php artisan ptah:forge Product/ProductStock \
  --fields="product_supplier_id:unsignedBigInteger,company_id:unsignedBigInteger,quantity:decimal(12,3)"
```

Todos os artefatos gerados respeitam a subpasta:

| Artefato | Caminho gerado |
|---|---|
| Model | `app/Models/Product/ProductStock.php` |
| Controller | `app/Http/Controllers/Product/ProductStockController.php` |
| Service | `app/Services/Product/ProductStockService.php` |
| Repository | `app/Repositories/Product/ProductStockRepository.php` |
| Interface | `app/Repositories/Contracts/Product/ProductStockRepositoryInterface.php` |
| DTO | `app/DTOs/Product/ProductStockDTO.php` |
| Requests | `app/Http/Requests/Product/Store|UpdateProductStockRequest.php` |
| Resource | `app/Http/Resources/Product/ProductStockResource.php` |
| Binding | `App\Repositories\Contracts\Product\ProductStockRepositoryInterface` |
| BaseCrud | `['model' => 'Product/ProductStock']` |

Subpastas multi-nível também são suportadas: `Catalog/Product/Variant`.

---

### Definindo campos

#### Via `--fields` (sem banco de dados)

```bash
php artisan ptah:forge Product \
  --fields="name:string,price:decimal(10,2),description:text:nullable,status:enum(active|inactive|pending),is_active:boolean"
```

**Tipos suportados:**

| Tipo | Alias | Blueprint gerado |
|---|---|---|
| `string` | — | `$table->string('campo')` |
| `text` | — | `$table->text('campo')` |
| `longText` | — | `$table->longText('campo')` |
| `integer` | `int` | `$table->integer('campo')` |
| `bigInteger` | `bigint` | `$table->bigInteger('campo')` |
| `unsignedBigInteger` | `ubigint` | veja nota FK abaixo |
| `decimal(p,s)` | — | `$table->decimal('campo', p, s)` |
| `float` | — | `$table->float('campo')` |
| `boolean` | `bool` | `$table->boolean('campo')` |
| `date` | — | `$table->date('campo')` |
| `datetime` | `timestamp` | `$table->timestamp('campo')` |
| `json` | — | `$table->json('campo')` |
| `enum(a\|b\|c)` | — | `$table->enum('campo', ['a','b','c'])` |

> **Detecção automática de FK** — Qualquer campo cujo nome termine em `_id` e cujo tipo seja `unsignedBigInteger` (ou `bigInteger`/`foreignId`) é reconhecido como chave estrangeira. O `ptah:forge` gera automaticamente:
>
> - **Migration:** `$table->foreignId('x_id')->constrained('xs')->cascadeOnDelete()`  
>   (com `:nullable` → `->nullable()->constrained('xs')->nullOnDelete()`)
> - **Model:** `use App\Models\X;` + método `belongsTo(X::class, 'x_id')`
>
> Exemplo:
> ```bash
> php artisan ptah:forge Product \
>   --fields="business_partner_id:unsignedBigInteger"
> # Migration: $table->foreignId('business_partner_id')->constrained('business_partners')->cascadeOnDelete();
> # Model:     public function businessPartner(): BelongsTo { ... }
> ```

**Modificadores:**

```bash
# :nullable   ->nullable()  (FK nullable usa nullOnDelete() em vez de cascadeOnDelete())
# :unique     ->unique()
email:string:unique
price:decimal(10,2):nullable
```

#### Via `--db` (tabela existente no banco)

```bash
php artisan ptah:forge Product --table=products --db
```

Lê as colunas diretamente via `SHOW FULL COLUMNS FROM` e pré-preenche `$fillable`, `$casts`, Rules e DTO automaticamente.

---

### Todas as opções

| Opção | Descrição | Padrão |
|---|---|---|
| `--table=` | Nome da tabela no banco | plural snake_case da entidade |
| `--fields=` | Definição dos campos em string | (vazio) |
| `--db` | Lê campos da tabela existente | `false` |
| `--api` | Gera apenas estrutura API (sem views) | `false` |
| `--no-soft-deletes` | Não inclui SoftDeletes no Model | `false` |
| `--force` | Sobrescreve arquivos existentes | `false` |

---

### Modo API

```bash
php artisan ptah:forge Product --api
```

- Gera `ProductApiController` em `app/Http/Controllers/Api/`
- Retorna `JsonResponse`
- Adiciona `Route::apiResource()` em `routes/api.php`
- Views não são geradas

---

### Output no terminal

```
 ptah:forge ......................................... Product/ProductStock

  Artefato                                         Status
  ───────────────────────────────────────────────────────────────
  Model [ProductStock]                             ✅ DONE
  Migration [create_product_stocks_table]          ✅ DONE
  Binding [AppServiceProvider]                     ✅ DONE
  DTO [ProductStockDTO]                            ✅ DONE
  Interface [ProductStockRepositoryInterface]      ✅ DONE
  Repository [ProductStockRepository]              ✅ DONE
  Service [ProductStockService]                    ✅ DONE
  Controller [ProductStockController]              ✅ DONE
  Request [StoreProductStock]                      ✅ DONE
  Request [UpdateProductStock]                     ✅ DONE
  Resource [ProductStockResource]                  ✅ DONE
  CrudConfig [Product/ProductStock]                ✅ DONE
  View [product_stock/index]                       ✅ DONE
  Routes [web.php]                                 ✅ DONE

 Próximos passos: (...)
```

---

### Arquivos gerados

**Sem subpasta** (`ptah:forge Product`):

```
app/
├── Models/Product.php
├── DTOs/ProductDTO.php
├── Repositories/
│   ├── Contracts/ProductRepositoryInterface.php
│   └── ProductRepository.php
├── Services/ProductService.php
└── Http/
    ├── Controllers/ProductController.php
    ├── Requests/Store|UpdateProductRequest.php
    └── Resources/ProductResource.php

resources/views/product/index.blade.php
    @livewire('ptah::base-crud', ['model' => 'Product'])
```

**Com subpasta** (`ptah:forge Product/ProductStock`):

```
app/
├── Models/Product/ProductStock.php
├── DTOs/Product/ProductStockDTO.php
├── Repositories/
│   ├── Contracts/Product/ProductStockRepositoryInterface.php
│   └── Product/ProductStockRepository.php
├── Services/Product/ProductStockService.php
└── Http/
    ├── Controllers/Product/ProductStockController.php
    ├── Requests/Product/Store|UpdateProductStockRequest.php
    └── Resources/Product/ProductStockResource.php

database/migrations/xxxx_create_product_stocks_table.php

database/crud_configs (tabela do ptah)
    └── model=Product/ProductStock  ← JSON do CrudConfigGenerator

resources/views/product_stock/index.blade.php
    @livewire('ptah::base-crud', ['model' => 'Product/ProductStock'])

routes/web.php  ← Route::get('product_stock', [ProductStockController::class, 'index'])
```

> **Modo API** com subpasta: `ptah:forge Product/ProductStock --api` gera o controller em `app/Http/Controllers/Product/Api/ProductStockApiController.php`.

---

### Próximos passos pós-geração

O comando exibe automaticamente ao final:

```
Proximos passos:

1. Execute as migrations (incluí a tabela crud_configs do ptah):
   php artisan migrate

2. Revise as regras de validação nos Requests gerados.

4. A tela de listagem já está funcional via BaseCrud:
   Acesse /product — tabela dinâmica, filtros, modal create/edit e soft delete prontos.

   Para customizar o CRUD edite diretamente a linha na tabela crud_configs:
   php artisan tinker
   >>> \Ptah\Models\CrudConfig::where('model', 'Product')->first()->config
```

---

## 🏗️ Arquitetura das camadas

### Controller Web

Fino, responsável apenas por renderizar a view index. Create, edit e delete são gerenciados pelo **BaseCrud via modal Livewire**.

```php
class ProductController extends Controller
{
    public function __construct(protected ProductService $service) {}

    /**
     * Exibe a listagem de products.
     * Criação, edição e exclusão são gerenciadas pelo BaseCrud via modal Livewire.
     */
    public function index(): View
    {
        return view('product.index');
    }
}
```

### Controller API

```php
class ProductApiController extends Controller
{
    public function __construct(protected ProductService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->paginate((int) $request->query('per_page', 15));
        return ProductResource::collection($items)->response();
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $item = $this->service->create($request->validated());
        return (new ProductResource($item))->response()->setStatusCode(201);
    }
}
```

### Service

Contém a lógica de negócio. Herda CRUD completo do `BaseService`.

```php
class ProductService extends BaseService
{
    public function __construct(protected ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    // Adicione lógica de negócio específica aqui
    public function activate(int $id): Product
    {
        return $this->repository->update($id, ['status' => 'active']);
    }
}
```

### Repository

Abstrai o banco de dados. Herda CRUD do `BaseRepository`.

```php
class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    // Queries específicas
    public function findActive(): Collection
    {
        return $this->model->where('status', 'active')->get();
    }
}
```

**Binding obrigatório no `AppServiceProvider`:**

```php
$this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
```

### DTO

```php
class ProductDTO extends BaseDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly float   $price,
        public readonly ?string $description = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            name:        $data['name'],
            price:       $data['price'],
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'price'       => $this->price,
            'description' => $this->description,
        ];
    }
}
```

---

## 🔩 Arquitetura SOLID dos Geradores

O `ptah:forge` foi construído sobre princípios SOLID. Cada artefato tem seu próprio gerador independente.

### Diagrama de responsabilidades

```
ScaffoldCommand (ptah:forge)
    │
    ├── SchemaInspector         → lê campos do banco ou da string --fields
    ├── EntityContext           → DTO imutável com todos os dados da entidade
    │
    └── Loop de Generators
         ├── ModelGenerator
         ├── MigrationGenerator
         ├── DtoGenerator
         ├── RepositoryInterfaceGenerator
         ├── RepositoryGenerator
         ├── ServiceGenerator
         ├── ControllerGenerator        (shouldRun: sem --api)
         ├── ControllerApiGenerator     (shouldRun: com --api)
         ├── RequestGenerator           (gera Store + Update)
         ├── ResourceGenerator
         ├── CrudConfigGenerator        (shouldRun: sem --api — salva JSON no banco)
         ├── ViewGenerator              (shouldRun: sem --api, gera view index)
         └── RouteGenerator

BaseCrud (Livewire Component)
    │
    ├── CrudConfigService        → lê/grava/invalida configuração JSON (crud_configs)
    │     └── CacheService       → tags Redis, TTLs separados por tipo (config/prefs/query)
    │
    ├── FilterService            → Strategy Pattern: applyFilters() com AND/OR logic
    │     ├── TextFilterStrategy        → LIKE, IN, NOT IN, IS NULL
    │     ├── NumericFilterStrategy     → =, BETWEEN, IN, NOT IN
    │     ├── DateFilterStrategy        → Carbon startOfDay/endOfDay em BETWEEN
    │     ├── RelationFilterStrategy    → whereHas, agregados SUM/COUNT/AVG/MAX/MIN
    │     └── ArrayFilterStrategy       → whereIn, whereNotIn, CSV→array
    │
    └── UserPreference           → preferências V2.1 por usuário (visibilidade, busca avançada, histórico)
```

### Princípios aplicados

| Princípio | Implementação |
|---|---|
| **SRP** | 1 generator = 1 artefato, 1 responsabilidade |
| **OCP** | Adicionar novo artefato = criar novo Generator sem alterar o comando |
| **LSP** | Todos os generators implementam `GeneratorInterface` e são intercambiáveis |
| **ISP** | `GeneratorInterface` tem apenas 2 métodos: `generate()` + `shouldRun()` |
| **DIP** | `ScaffoldCommand` depende de `GeneratorInterface`, não de classes concretas |

### Adicionando um novo Generator

1. Crie `src/Generators/AuditLogGenerator.php` estendendo `AbstractGenerator`
2. Implemente `generate(EntityContext)` e `shouldRun(EntityContext)`
3. Registre na array `$generators` de `ScaffoldCommand`

```php
// Exemplo mínimo
class AuditLogGenerator extends AbstractGenerator
{
    public function shouldRun(EntityContext $ctx): bool
    {
        return true;
    }

    public function generate(EntityContext $ctx): GeneratorResult
    {
        return $this->writeFile(
            path:         app_path("AuditLogs/{$ctx->entity}AuditLog.php"),
            stub:         'audit-log',
            replacements: ['entity' => $ctx->entity],
            force:        $ctx->force,
        );
    }
}
```

---

## 👤 Sistema de UserPreferences

Armazena preferências por usuário no banco de dados.

### Setup

```bash
php artisan migrate
```

```php
// app/Models/User.php
use Ptah\Traits\HasUserPreferences;

class User extends Authenticatable
{
    use HasUserPreferences;
}
```

### Uso

```php
// Definir
$user->setPreference('theme', 'dark');
$user->setPreference('items_per_page', 25, group: 'ui');

// Obter (com valor padrão)
$theme = $user->getPreference('theme', default: 'light');

// Obter grupo completo
$uiPrefs = $user->getPreferenceGroup('ui');

// Remover
$user->removePreference('theme');
```

### Uso direto via Model

```php
use Ptah\Models\UserPreference;

UserPreference::set(userId: 1, key: 'theme', value: 'dark');
$theme = UserPreference::get(userId: 1, key: 'theme', default: 'light');
UserPreference::remove(userId: 1, key: 'theme');
```

---

## 🖥️ BaseCrud — Tela Dinâmica Completa

O **BaseCrud** é um componente Livewire 3 gerado e configurado automaticamente pelo `ptah:forge`. Ele entrega uma tela de listagem completa apenas com `@livewire('ptah::base-crud', ['model' => 'Product'])`, sem nenhum código adicional.

### Como funciona

```
ptah:forge Product --fields="..."
        ↓
  CrudConfigGenerator salva JSON na tabela crud_configs
        ↓
  view index.blade.php renderiza @livewire('ptah::base-crud')
        ↓
  BaseCrud.php lê a configuração, constrói a query e renderiza tudo dinamicamente
```

**Recursos da tela:**

| Recurso | Descrição |
|---|---|
| Tabela dinâmica | Colunas definidas pelo JSON, sort ao clicar no th |
| Visibilidade de colunas | Toggle por coluna com contador de ocultas, show/hide/reset |
| Busca global | Campo texto com OR em texto + `whereHas` em relacionamentos |
| Painel de filtros | Tipo por coluna (select, date range, searchdropdown, texto) |
| Filtros rápidos de data | Atalhos Today/Esta semana/Este mês/Trimestre/Ano |
| Busca avançada | Campos livre com operadores e lógica AND/OR |
| Histfórico de busca | Últimas 10 buscas salvas por usuário |
| Badges de filtros ativos | Resumo visual de filtros com botão para remover cada um |
| Filtros salvos | Salvar/carregar/excluir conjuntos de filtros com nome |
| Paginação | Livewire WithPagination integrado, itens por página configurável |
| Modal create/edit | Campos gerados automaticamente por `colsGravar='S'` |
| Soft delete | Botão lixeira com contador de excluídos, exibição/restauração |
| Bulk actions | Seleção múltipla, excluir/exportar/ações customizadas em lote |
| Estilos condicionais | Cor de linha baseada em regra campo/operador/valor |
| JOINs configuráveis | LEFT / INNER JOINs declarados no CrudConfig — sem Eloquent, com filtro, sort e export |
| Totalizadores | Soma/média/contagem no tfoot com clone de query por agregado |
| Exportação | Excel/PDF síncrona ou assíncrona (via Job) |
| Preferências | Colunas, largura, densidade, filtros rápidos, histórico, salvas por usuário |
| SearchDropdown | Autocomplete via Eloquent ou Service para campos FK |
| whereHasFilter | Abrir tela pré-filtrada por entidade pai |
| Multi-tenant | Filtro dinâmico por empresa via `companyFilter` |
| Error recovery | try/catch em `getRowsProperty` com limpeza de preferências corrompidas |

---

### Como usar o BaseCrud

#### Opção 1 — Via `ptah:forge` (recomendado)

O jeito mais rápido. O comando gera tudo, incluindo a `CrudConfig` no banco:

```bash
php artisan ptah:forge Product \
  --fields="name:string,price:decimal(10,2),status:enum(active|inactive)"
```

A view gerada em `resources/views/product/index.blade.php` já contém:

```blade
@livewire('ptah::base-crud', ['model' => 'Product'])
```

Pronto — tabela, filtros, modal, paginação e preferências funcionando sem mais nenhum código.

---

#### Opção 2 — Manual (sem `ptah:forge`)

**Passo 1 — Pré-requisitos**

Seu Model deve ter `SoftDeletes` (recomendado) e `$fillable` configurado:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'price', 'status', 'category_id'];

    protected $casts = ['price' => 'float'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

**Passo 2 — Criar a CrudConfig no banco**

Via Tinker ou em um Seeder:

```php
use Ptah\Models\CrudConfig;

CrudConfig::create([
    'model'  => 'Product',
    'config' => json_encode([
        'crud'        => 'Product',
        'totalizador' => false,
        'cols'        => [
            [
                'colsNomeFisico'   => 'id',
                'colsNomeLogico'   => 'ID',
                'colsTipo'         => 'number',
                'colsGravar'       => 'N',
                'colsRequired'     => 'N',
                'colsAlign'        => 'text-center',
                'colsIsFilterable' => 'N',
            ],
            [
                'colsNomeFisico'   => 'name',
                'colsNomeLogico'   => 'Nome',
                'colsTipo'         => 'text',
                'colsGravar'       => 'S',
                'colsRequired'     => 'S',
                'colsAlign'        => 'text-start',
                'colsIsFilterable' => 'S',
            ],
            [
                'colsNomeFisico'   => 'price',
                'colsNomeLogico'   => 'Preço',
                'colsTipo'         => 'number',
                'colsGravar'       => 'S',
                'colsRequired'     => 'S',
                'colsHelper'       => 'currencyFormat',
                'colsAlign'        => 'text-end',
                'colsIsFilterable' => 'S',
            ],
            [
                'colsNomeFisico'   => 'status',
                'colsNomeLogico'   => 'Status',
                'colsTipo'         => 'select',
                'colsGravar'       => 'S',
                'colsRequired'     => 'S',
                'colsAlign'        => 'text-center',
                'colsIsFilterable' => 'S',
                'colsSelect'       => ['Ativo' => 'active', 'Inativo' => 'inactive'],
            ],
        ],
    ]),
]);
```

**Passo 3 — Renderizar na view**

```blade
{{-- resources/views/products/index.blade.php --}}
@extends('ptah::layouts.forge-dashboard')

@section('title', 'Produtos')

@section('content')
    @livewire('ptah::base-crud', ['model' => 'Product'])
@endsection
```

**Passo 4 — Rota**

```php
// routes/web.php
Route::get('/products', fn() => view('products.index'))->name('products.index');
```

---

#### Todos os parâmetros do componente

| Parâmetro | Tipo | Padrão | Descrição |
|---|---|---|---|
| `model` | string | — | **Obrigatório.** Nome do Model (ex: `'Product'`) |
| `title` | string | `null` | Título exibido no topo da tela |
| `perPage` | int | `15` | Itens por página inicial |
| `companyFilter` | array | `[]` | Multi-tenant: `['column' => 'company_id', 'value' => $id]` |
| `whereHasFilter` | string | `null` | Relação para pré-filtrar (ex: `'supplier'`) |
| `whereHasCondition` | array | `[]` | Condição do `whereHasFilter`: `['id', '=', 5]` |
| `readOnly` | bool | `false` | Desativa create/edit/delete |
| `canCreate` | bool | `true` | Exibe botão Novo |
| `canEdit` | bool | `true` | Exibe ação de edição |
| `canDelete` | bool | `true` | Exibe ação de exclusão |
| `canExport` | bool | `true` | Exibe botão de exportação |

---

#### Exemplo completo com todas as opções

```blade
@livewire('ptah::base-crud', [
    'model'             => 'Product',
    'title'             => 'Produtos do Fornecedor',
    'perPage'           => 25,
    'companyFilter'     => ['column' => 'company_id', 'value' => auth()->user()->company_id],
    'whereHasFilter'    => 'supplier',
    'whereHasCondition' => ['id', '=', $supplier->id],
    'canCreate'         => true,
    'canEdit'           => true,
    'canDelete'         => auth()->user()->can('delete products'),
    'canExport'         => true,
])
```

---

#### Ajustando a configuração depois

Para alterar colunas, helpers ou comportamentos sem re-gerar, use o Tinker:

```bash
php artisan tinker
```

```php
$config = Ptah\Models\CrudConfig::where('model', 'Product')->first();
$data   = json_decode($config->config, true);

// Ajuste o que precisar
$data['cols'][2]['colsHelper'] = 'currencyFormat';

$config->update(['config' => json_encode($data)]);
// Cache invalidado automaticamente ao salvar
```

---

### Schema de configuração (cols)

Cada entrada em `cols` representa uma coluna da tabela e/ou campo do formulário.

```json
{
  "crud": "Product",
  "configLinkLinha": "/products/%id%",
  "totalizador": false,
  "cols": [
    {
      "colsNomeFisico":   "id",
      "colsNomeLogico":   "ID",
      "colsTipo":         "number",
      "colsGravar":       "N",
      "colsRequired":     "N",
      "colsAlign":        "text-center",
      "colsIsFilterable": "N"
    },
    {
      "colsNomeFisico":   "name",
      "colsNomeLogico":   "Nome",
      "colsTipo":         "text",
      "colsGravar":       "S",
      "colsRequired":     "S",
      "colsAlign":        "text-start",
      "colsIsFilterable": "S"
    },
    {
      "colsNomeFisico":   "price",
      "colsNomeLogico":   "Preço",
      "colsTipo":         "number",
      "colsGravar":       "S",
      "colsHelper":       "currencyFormat",
      "colsAlign":        "text-end"
    },
    {
      "colsNomeFisico":   "status",
      "colsNomeLogico":   "Status",
      "colsTipo":         "select",
      "colsGravar":       "S",
      "colsSelect":       {"Ativo": "active", "Inativo": "inactive"}
    },
    {
      "colsNomeFisico":   "category_id",
      "colsNomeLogico":   "Categoria",
      "colsTipo":         "searchdropdown",
      "colsGravar":       "S",
      "colsRelacao":      "category",
      "colsRelacaoExibe": "name",
      "colsSDTipo":       "model",
      "colsSDModel":      "Category",
      "colsSDLabel":      "name",
      "colsSDValue":      "id",
      "colsSDOrder":      "name ASC"
    }
  ]
}
```

**Atributos das colunas:**

| Chave | Tipo | Descrição |
|---|---|---|
| `colsNomeFisico` | string | Nome do campo no banco |
| `colsNomeLogico` | string | Rótulo exibido no th e label do form |
| `colsTipo` | string | Tipo de renderização (ver tabela abaixo) |
| `colsGravar` | `'S'`/`'N'` | `S` = aparece no modal de form; `N` = somente na listagem |
| `colsRequired` | `'S'`/`'N'` | Campo obrigatório no formulário |
| `colsAlign` | string | Classe CSS de alinhamento do td |
| `colsHelper` | string | Helper legado de formatação da célula |
| `colsRenderer` | string | Renderer DSL: `badge`, `pill`, `boolean`, `money`, `link`, `image`, `truncate` |
| `colsRendererBadges` | array | Mapa `["valor" => "cor"]` para renderers `badge`/`pill` |
| `colsCellStyle` | string | CSS inline aplicado ao `<span>` da célula |
| `colsCellClass` | string | Classes Tailwind adicionais da célula |
| `colsCellIcon` | string | Ícone `heroicon-*` prefixado ao conteúdo |
| `colsMinWidth` | string | Largura mínima do th (ex: `"120px"`) |
| `colsMask` | string | Máscara de exibição: `cpf`, `cnpj`, `phone`, `cep`, `currency`, `percent` |
| `colsMaskTransform` | string | Transformação após a máscara: `upper`, `lower`, `ucfirst` |
| `colsRelacao` | string | Nome do relacionamento Eloquent (`$product->category`) |
| `colsRelacaoExibe` | string | Atributo do objeto relacionado a exibir |
| `colsRelacaoNested` | string | Notação dot para relações aninhadas: `category.parent.name` |
| `colsSource` | string | **JOIN** — qualified name SQL para `WHERE`/`ORDER BY` (ex: `suppliers.name`). O `colsNomeFisico` deve ser o alias |
| `colsIsFilterable` | `'S'`/`'N'` | Exibe no painel de filtros |
| `colsSelect` | object | Mapa `{"Label": "valor"}` para tipo select |
| `colsOrderBy` | string | Coluna alternativa para ORDER BY |
| `colsReverse` | `'S'`/`'N'` | Inverte sort (DESC quando `'S'`) |
| `colsMetodoCustom` | string | Método custom para formatar a célula |
| `colsValidations` | array | Regras do FormValidatorService: `["required","email","min:3"]` |
| `colsSDMode` | `'create'\|'edit'\|'both'` | Em qual modo do modal o campo SearchDropdown aparece |

---

### Tipos de colunas (`colsTipo`)

| colsTipo | Renderização na tabela | Renderização no form | Filtro |
|---|---|---|---|
| `text` | Texto simples | `<input type="text">` | LIKE |
| `number` | Número (com helper opcional) | `<input type="number">` | `=` / BETWEEN |
| `date` | Data formatada | `<input type="date">` | BETWEEN (range) |
| `select` | Label do `colsSelect` | `<select>` com as opções | `=` (dropdown) |
| `searchdropdown` | Exibe via `colsRelacaoExibe` | Autocomplete assíncrono | `=` via FK |

---

### Helpers de formatação

Definidos em `colsHelper` (legacy) ou `colsRenderer` (DSL). Aplicados em `formatCell()` antes de exibir na tabela.

**Helpers legacy (`colsHelper`):**

| Helper | Entrada | Saída exemplo |
|---|---|---|
| `dateFormat` | `2025-02-14` | `14/02/2025` |
| `dateTimeFormat` | `2025-02-14 15:30:00` | `14/02/2025 15:30` |
| `currencyFormat` | `1234.50` | `R$ 1.234,50` |
| `yesOrNot` | `1` / `0` | `Sim` / `Não` |
| `flagChannel` | `'G'` / `'Y'` / `'R'` | Badge verde/amarelo/vermelho |

**Renderer DSL (`colsRenderer`) — recomendado:**

| Renderer | Resultado | Configuração extra |
|---|---|---|
| `badge` | `<span>` com fundo colorido + texto em branco/escuro | `colsRendererBadges: {"ativo":"green","inativo":"red"}` |
| `pill` | Igual ao `badge` mas com bordas arredondadas (full) | `colsRendererBadges: { ... }` |
| `boolean` | ✅ / ❌ (ícone SVG) | — |
| `money` | `R$ 1.234,56` | — |
| `link` | `<a href="[valor]">` | — |
| `image` | `<img src="[valor]">` com thumbnail | — |
| `truncate` | Texto truncado com `title` completo | — |

**Cores do badge/pill:**

Use nomes semânticos (`green`, `red`, `yellow`, `blue`, `indigo`, `purple`, `pink`, `gray`) **ou** qualquer cor hexadecimal (`#FF5733`). Cores hex geram `background-color` inline com 13% de opacidade e `color` correspondente.

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

**Estilo e ícone por célula:**

```json
{
  "colsCellStyle": "font-weight:600;",
  "colsCellClass": "text-indigo-600",
  "colsCellIcon":  "heroicon-o-star",
  "colsMinWidth":  "140px"
}
```

**Máscara de exibição (`colsMask`):**

| Máscara | Entrada | Saída |
|---|---|---|
| `cpf` | `12345678901` | `123.456.789-01` |
| `cnpj` | `12345678000190` | `12.345.678/0001-90` |
| `phone` | `11987654321` | `(11) 98765-4321` |
| `cep` | `01310100` | `01310-100` |
| `currency` | `1234.5` | `R$ 1.234,50` |
| `percent` | `0.75` | `75%` |

Combinar com `colsMaskTransform: "upper"` transforma o resultado final em maiúsculas.

**Relações aninhadas (`colsRelacaoNested`):**

Use notação dot para exibir atributos de relações em cadeia, sem precisar de `colsMetodoCustom`:

```json
{
  "colsRelacaoNested": "category.parent.name"
}
```

O `BaseCrud` resolve automaticamente via `resolveNestedValue()`, suportando qualquer profundidade.

---

### Estilos condicionais de linha

A chave `contitionStyles` do config permite colorir linhas baseado em regras:

```json
"contitionStyles": [
  {
    "colsNomeFisico": "status",
    "condition": "==",
    "value": "inactive",
    "style": "background-color:#fff3cd;"
  },
  {
    "colsNomeFisico": "price",
    "condition": "<",
    "value": "0",
    "style": "background-color:#f8d7da;"
  }
]
```

Operadores suportados: `==`, `!=`, `>`, `<`, `>=`, `<=`.

---

### Filtros e FilterService

O `FilterService` implementa o padrão **Strategy**, registrando uma estratégia por tipo de campo. Suporta lógica **AND/OR** por filtro via `options['logic']`.

**Estratégias registradas automaticamente:**

| type | Estratégia | Comportamento |
|---|---|---|
| `text` | `TextFilterStrategy` | `LIKE`, `=`, `!=`, `IN`, `NOT IN`, `LIKE_START`, `NOT LIKE`, `IS NULL` |
| `number` / `numeric` | `NumericFilterStrategy` | `=`, `BETWEEN` (array `[from,to]`), `>`, `<`, `IN`, `NOT IN` |
| `date` / `datetime` | `DateFilterStrategy` | Carbon `startOfDay`/`endOfDay` em BETWEEN, `whereDate` |
| `relation` | `RelationFilterStrategy` | `whereHas` com sub-query, suporte a agregados (SUM/COUNT/AVG/MAX/MIN) |
| `array` | `ArrayFilterStrategy` | `whereIn` / `whereNotIn`, aceita string CSV |

```php
use Ptah\Services\Crud\FilterService;
use Ptah\DTO\FilterDTO;

$service = app(FilterService::class);

$filters = [
    // AND (padrão)
    new FilterDTO(field: 'status', value: 'active'),

    // BETWEEN em data com Carbon startOfDay/endOfDay
    new FilterDTO(field: 'created_at', value: ['2025-01-01', '2025-12-31'], operator: 'BETWEEN', type: 'date'),

    // whereHas em relação
    new FilterDTO(field: 'name', value: 'João', operator: 'LIKE', type: 'relation',
        options: ['whereHas' => 'supplier', 'column' => 'name']),

    // OR block — agrupado em WHERE (... OR ... OR ...)
    new FilterDTO(field: 'name', value: 'Teste', operator: 'LIKE', type: 'text',
        options: ['logic' => 'OR']),
    new FilterDTO(field: 'email', value: 'Teste', operator: 'LIKE', type: 'text',
        options: ['logic' => 'OR']),
];

$query = Product::query();
$service->applyFilters($query, $filters);
// Gera: WHERE status = 'active' AND created_at BETWEEN ... AND (name LIKE '%Teste%' OR email LIKE '%Teste%')
```

**Registrar estratégia customizada:**

```php
// No AppServiceProvider::boot()
app(FilterService::class)->registerStrategy('json_contains', new MyJsonStrategy());
```

**Busca global com OR em relacionamentos** (via `buildGlobalSearchFilters()`):

O `BaseCrud` usa esse método internamente para busca global — inclui `whereHas` com LIKE nos campos com `colsRelacao`.

```php
$searchFilters = $service->buildGlobalSearchFilters($crudConfig['cols'], 'João');
// Gera: WHERE (name LIKE '%João%' OR email LIKE '%João%' OR (EXISTS SELECT 1 FROM suppliers WHERE name LIKE '%João%'))
```

---

### SearchDropdown

Para campos de FK com autocomplete. Configure `colsTipo=searchdropdown` e:

```json
{
  "colsNomeFisico":   "supplier_id",
  "colsTipo":         "searchdropdown",
  "colsGravar":       "S",
  "colsSDTipo":       "model",
  "colsSDModel":      "Supplier",
  "colsSDLabel":      "name",
  "colsSDValue":      "id",
  "colsSDOrder":      "name ASC",
  "colsRelacao":      "supplier",
  "colsRelacaoExibe": "name"
}
```

**`colsSDTipo`:**
- `model` — query Eloquent direta em `App\Models\{colsSDModel}`
- `service` — chama `App\Services\{classe}::{method}($query)` (formato `Namespace\Classe\Metodo`)

---

### Exportação

Configurada via `exportConfig` no JSON:

```json
"exportConfig": {
  "enabled": true,
  "asyncThreshold": 1000,
  "maxRows": 10000,
  "orientation": "landscape",
  "formats": ["excel", "pdf"],
  "chunkSize": 500,
  "notificationChannel": "database"
}
```

- Abaixo de `asyncThreshold` registros: exportação síncrona com download imediato
- Acima: despacha `Ptah\Jobs\BaseCrudExportJob` em background + notificação ao usuário

---

### Preferências por usuário

O BaseCrud persiste preferências por usuário no `UserPreference` com grupo `crud` (versão 2.1):

| Preferência | Tipo | Descrição |
|---|---|---|
| `columnOrder` | array | Ordem das colunas na tabela |
| `columnWidths` | object | Largura por coluna em px |
| `columns` | object | Visibilidade: `{field: bool}` |
| `viewDensity` | string | `compact` \| `comfortable` \| `spacious` |
| `viewMode` | string | `table` |
| `perPage` | int | Itens por página |
| `savedFilters` | object | Filtros salvos com nome pelo usuário |
| `quickDate` | string | Filtro rápido de data: `today/week/month/quarter/year` |
| `searchHistory` | array | Últimas 10 buscas globais |
| `advancedSearch` | object | Estado da busca avançada `{active, fields}` |

As preferências são salvas/carregadas automaticamente via:

```php
// Chave usada internamente
UserPreference::set($userId, 'crud.Product', $prefsV2, 'crud');
UserPreference::get($userId, 'crud.Product', default: []);
```

---

### `whereHasFilter` — Pré-filtrado por entidade pai

Abre a tela de CRUD já filtrada por uma relação pai, sem precisar alterar a view ou o CrudConfig.

```blade
{{-- Abre os produtos do fornecedor ID=5 --}}
@livewire('ptah::base-crud', [
    'model'             => 'Product',
    'whereHasFilter'    => 'supplier',
    'whereHasCondition' => ['id', '=', 5],
])
```

A condição aceita qualquer coluna e operador da relação. Internamente usa `whereHas` no Eloquent.

---

### Visibilidade de colunas

O usuário pode ocultar/mostrar colunas individualmente. As escolhas são salvas em preferências.

**Métodos Livewire disponíveis na view:**

| Método | Ação |
|---|---|
| `wire:click="updateColumns"` | Salva o estado atual de `formDataColumns` |
| `wire:click="showAllColumns"` | Torna todas as colunas visíveis |
| `wire:click="hideAllColumns"` | Oculta todas as colunas |
| `wire:click="resetColumnsToDefault"` | Restaura todas as colunas como visíveis |

O componente expõe `$hiddenColumnsCount` (int) para exibir um badge com o número de colunas ocultas.

**Configuração do JSON:**

Não requer configuração adicional — o mapa `formDataColumns` é inicializado automaticamente a partir de `cols`.

---

### Bulk Actions — Ações em lote

Permite selecionar múltiplos registros e executar ações sobre eles.

**Uso básico:**

```blade
<input type="checkbox" wire:click="toggleSelectAll" :checked="$selectAll" />

@foreach ($rows as $row)
    <input type="checkbox" wire:click="toggleSelectRow({{ $row->id }})"
           :checked="in_array((string) {{ $row->id }}, $selectedRows)" />
@endforeach

<button wire:click="bulkDelete">Excluir selecionados</button>
<button wire:click="bulkExport('excel')">Exportar selecionados</button>
```

**Ações customizadas via JSON:**

```json
"bulkActions": [
  {"label": "Aprovar",   "action": "aprovar",   "method": "App\\Services\\ProductService@bulkAprovar"},
  {"label": "Arquivar",  "action": "arquivar",  "method": "App\\Services\\ProductService@bulkArquivar"}
]
```

```blade
<button wire:click="executeBulkAction('aprovar')">Aprovar selecionados</button>
```

O método recebe `($selectedIds, $modelName)` como parâmetros.

---

### Filtros rápidos de data

Atalhos de período com um clique. Salva em preferências.

```blade
<button wire:click="applyQuickDateFilter('today')">Hoje</button>
<button wire:click="applyQuickDateFilter('week')">Esta semana</button>
<button wire:click="applyQuickDateFilter('month')">Este mês</button>
<button wire:click="applyQuickDateFilter('quarter')">Trimestre</button>
<button wire:click="applyQuickDateFilter('year')">Ano</button>
```

Configurar a coluna de data no JSON:

```json
{
  "crud": "Product",
  "quickDateColumn": "created_at"
}
```

Clicar no mesmo período desliga o filtro (toggle).

---

### Badges de filtros ativos (`textFilter`)

O `$textFilter` é um array de badges `[{label, field, value}]` representando todos os filtros ativos. Atualizado automaticamente ao mudar filtros, dateRanges ou quickDateFilter.

```blade
@foreach ($textFilter as $badge)
    <span>
        {{ $badge['label'] }}: {{ $badge['value'] }}
        <button wire:click="removeTextFilterBadge('{{ $badge['field'] }}')">✕</button>
    </span>
@endforeach
```

---

### Busca avançada

Permite ao usuário adicionar campos de filtro livre com operador e lógica.

```blade
<button wire:click="toggleAdvancedSearch">Busca Avançada</button>

@if ($advancedSearchActive)
    {{-- Formulário de campo avançado --}}
    <select wire:model="newField">@foreach ($cols as $c) <option>{{ $c['colsNomeFisico'] }}</option> @endforeach</select>
    <select wire:model="newOperator">
        <option value="=">Igual a</option>
        <option value="!=">Diferente de</option>
        <option value="LIKE">Contém</option>
        <option value=">">Maior que</option>
    </select>
    <input wire:model="newValue" />
    <select wire:model="newLogic">
        <option value="AND">E (AND)</option>
        <option value="OR">OU (OR)</option>
    </select>
    <button wire:click="addAdvancedSearchField($newField, $newOperator, $newValue, $newLogic)">+ Adicionar</button>

    @foreach ($advancedSearchFields as $i => $asf)
        <span>{{ $asf['field'] }} {{ $asf['operator'] }} {{ $asf['value'] }}</span>
        <button wire:click="removeAdvancedSearchField({{ $i }})">✕</button>
    @endforeach
@endif
```

---

### `colsMetodoCustom`

Permite delegar a formatação de uma célula a um método de um Service externo. Seguro — usa `app()` e nunca `eval()`.

**Padrão:** `Namespace\Classe\Metodo(%campo%)`

```json
{
  "colsNomeFisico":   "status_id",
  "colsMetodoCustom": "Supplier\SupplierService\getStatusLabel(%status_id%)"
}
```

O BaseCrud resolve como:

```php
app('App\\Services\\Supplier\\SupplierService')->getStatusLabel($row->status_id)
```

`%campo%` é substituído pelo valor do campo correspondente na linha.

---

### CrudConfigService

Acesse o serviço diretamente para inspecionar ou modificar configurações programaticamente:

```php
use Ptah\Services\Crud\CrudConfigService;

$service = app(CrudConfigService::class);

// Buscar config (com cache automático)
$config = $service->find('Product');
$config = $service->findOrFail('Product'); // lança RuntimeException se não existir

// Salvar/atualizar config completa
$service->save('Product', $arrayConfig);

// Atualizar apenas uma seção
$service->updateSection('Product', 'permissions', [
    'showCreateButton' => false,
    'showDeleteButton' => false,
]);

// Invalidar cache
$service->forget('Product');

// Listar todos os models configurados
$models = $service->listModels(); // ['Product', 'Category', ...]

// Remover config
$service->delete('Product');
```

**Cache key:** `ptah.crud.{model}` (TTL configurável em `config('ptah.crud.cache_ttl')` ou por config da entidade em `cacheStrategy.ttl`)

---

### CacheService

O `CacheService` é o serviço de cache dedicado do Ptah. Suporta **tag-based invalidation** em Redis/Memcached com graceful fallback para drivers sem tags (file, database).

**TTLs separados por tipo:**

| Constante | TTL | Uso |
|---|---|---|
| `CONFIG_TTL` | 86400s (1 dia) | Configurações CrudConfig |
| `PREFERENCES_TTL` | 7200s (2h) | Preferências de usuário |
| `QUERY_TTL` | 60s | Resultados de query |
| `DEFAULT_TTL` | 3600s (1h) | Genérico |

```php
use Ptah\Services\Cache\CacheService;

$cache = app(CacheService::class);

// Config de model (tag: ptah_config + ptah_model_Product)
$cache->rememberConfig('Product', fn() => $data);
$cache->forgetConfig('Product');

// Preferências de usuário (tag: ptah_preferences + ptah_user_1)
$cache->rememberPreferences(1, 'Product', fn() => $prefs);
$cache->forgetPreferences(1, 'Product'); // uma tela
$cache->forgetPreferences(1);             // todas do usuário (só com Redis)

// Invalidar tudo de um model de uma vez (config + queries)
$cache->invalidateModel('Product');

// Verificar suporte a tags
if ($cache->supportsTagging()) {
    // driver é redis/memcached/dynamodb
}
```

> **Dica:** Para produção, configure `CACHE_DRIVER=redis` no `.env` para aproveitar a invalidação por tags e evitar o flush total em deploys.

---

## 🔐 Módulos Opcionais — Auth, Menu, Company & Permissions

O Ptah possui quatro módulos opcionais que podem ser ativados de forma independente, sem afetar projetos que usam apenas o scaffolding e o `BaseCrud`.

| Módulo | Funcionalidades |
|---|---|
| **auth** | Login com rate limit, recuperação de senha, 2FA (TOTP + e-mail + recovery codes), sessões ativas, perfil com foto |
| **menu** | Menu lateral dinâmico via banco de dados com cache, estrutura em árvore e driver pattern (retrocompatível) |
| **company** | Gestão de empresas e departamentos, slug automático, seeder idempotente, suporte multi-empresa ou tenant único |
| **permissions** | RBAC completo — roles, páginas, objetos, middleware, helpers, Blade directives, auditoria e cache de permissões |

> **Documentação completa → [docs/Modules.md](docs/Modules.md)**  
> **Referência Company → [docs/Company.md](docs/Company.md)**  
> **Referência Permissions → [docs/Permissions.md](docs/Permissions.md)**

### Ativando os módulos

```bash
# Ativar autenticação
php artisan ptah:module auth

# Ativar menu dinâmico
php artisan ptah:module menu

# Ativar gestão de empresas e departamentos
php artisan ptah:module company

# Ativar RBAC completo (ativa company automaticamente se necessário)
php artisan ptah:module permissions

# Ver estado de todos os módulos
php artisan ptah:module --list
```

O comando publica as migrations, executa `migrate` e define automaticamente a variável de ambiente no `.env`.

Ou ative manualmente via `.env`:

```dotenv
PTAH_MODULE_AUTH=true
PTAH_MODULE_MENU=true
PTAH_MENU_DRIVER=database   # 'config' (padrão) ou 'database'
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true
```

### Módulo Auth — visão rápida

Quando `PTAH_MODULE_AUTH=true`, as seguintes rotas são registradas automaticamente:

| URI | Descrição |
|---|---|
| `/login` | Login com rate limit (5 tentativas) |
| `/forgot-password` | Recuperação de senha |
| `/reset-password/{token}` | Redefinição de senha |
| `/two-factor-challenge` | Verificação 2FA pós-login |
| `/dashboard` | Dashboard inicial |
| `/profile` | Perfil com 5 abas (dados, senha, 2FA, sessões, foto) |

**2FA suportado:**
- **TOTP** (Google Authenticator, Authy…) via `pragmarx/google2fa-laravel`
- **E-mail OTP** (sem dependências extras — usa `Cache` do Laravel)
- **Códigos de recuperação** (8 códigos, uso único)

Para 2FA TOTP:

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

### Módulo Menu — visão rápida

O menu da sidebar suporta dois drivers:

| Driver | Fonte dos dados | Migração necessária |
|---|---|---|
| `config` (padrão) | `config('ptah.forge.sidebar_items')` | Não |
| `database` | Tabela `menus` com cache automático | Sim (`ptah:module menu`) |

A troca de driver **não quebra projetos existentes** — o driver `config` é o padrão e nenhum código precisa ser alterado.

Quando `driver = database`:
- O `forge-sidebar` usa `MenuService::getTree()` automaticamente (com cache configurável)
- Um item **Dashboard** fixo é injetado no topo da sidebar automaticamente
- O `forge-navbar` exibe o link **Gerenciar menu** no dropdown de Administração

**Tela de gestão:** `/ptah-menu` — CRUD completo de itens com suporte a grupos, sub-itens, ordem, ícones e status. Qualquer alteração invalida o cache automaticamente.

**Ícones suportados na tela e na sidebar:**
```
bx bx-home-alt   → Boxicons
fas fa-user       → Font Awesome Free
```
> Boxicons 2.1.4 e FontAwesome 6.7.2 são as bibliotecas padrão do Ptah e são carregadas automaticamente.

**Comportamento de grupos na sidebar:**
- `menuGroup` com filhos: accordion Alpine.js (`x-collapse`)
- `menuGroup` sem filhos: label desabilitado
- `menuLink`: link direto com highlight de rota ativa

> Consulte **[docs/Modules.md#módulo-menu](docs/Modules.md#m%C3%B3dulo-menu)** para referência completa.

### Módulo Company — visão rápida

Quando `PTAH_MODULE_COMPANY=true`, o sistema de empresas e departamentos é ativado:

```php
// Via service
$company = app(CompanyService::class)->getDefault();
$departments = app(CompanyService::class)->getDepartments($company->id);
```

| Recurso | Detalhes |
|---|---|
| Tela admin | `/ptah-companies` — listagem e gestão |
| Slug automático | Gerado no `boot()` do model |
| Seeder idempotente | `DefaultCompanySeeder` — nunca duplica |
| Campo `label` (sigla) | Até 4 caracteres, exibida no badge do company switcher na navbar |
| Company Switcher | Barra horizontal na navbar: nome da empresa ativa + labels de todas disponíveis |
| Multi-empresa | Controlado por `PTAH_COMPANY_MULTIPLE` |

> Consulte **[docs/Company.md](docs/Company.md)** para referência completa.

### Módulo Permissions — visão rápida

Quando `PTAH_MODULE_PERMISSIONS=true`, o RBAC completo é ativado:

```php
// Helper global
if (ptah_can('produtos', 'editar')) { ... }

// Middleware em rotas
Route::middleware('ptah.can:produtos,criar')->group(...);

// Blade directive
@ptahCan('produtos', 'deletar')
    <button>Deletar</button>
@endPtahCan

// Verificar MASTER role
if (ptah_is_master()) { ... }
```

| Recurso | Detalhes |
|---|---|
| Roles | MASTER (todos os acessos) + roles customizadas |
| Hierarquia | Página → Objeto → Ação (criar/editar/deletar/listar/exportar) |
| Cache | Automático com invalidação por role (`PTAH_PERM_CACHE=true`) |
| Auditoria | Log de verificações (`PTAH_PERM_AUDIT=true`) |
| Admin | 6 telas: roles, páginas, objetos, permissões por usuário, auditoria, guia interativo |

> Consulte **[docs/Permissions.md](docs/Permissions.md)** para referência completa.

---

## ⚙️ Configuração

Arquivo `config/ptah.php` após `ptah:install`:

```php
return [
    // Caminhos onde os artefatos gerados serão criados
    'paths' => [
        'models'       => app_path('Models'),
        'services'     => app_path('Services'),
        'repositories' => app_path('Repositories'),
        'dtos'         => app_path('DTOs'),
        'requests'     => app_path('Http/Requests'),
        'resources'    => app_path('Http/Resources'),
        'controllers'  => app_path('Http/Controllers'),
        'views'        => resource_path('views'),
    ],

    // UserPreferences
    'preferences' => [
        'driver' => 'database',
        'cache'  => true,
        'ttl'    => 3600,
    ],

    // API
    'api' => [
        'prefix'     => 'api',
        'middleware' => ['api', 'auth:sanctum'],
        'docs'       => true,
    ],

    // Ptah Forge — componentes visuais
    'forge' => [
        'prefix'        => 'forge',     // <x-forge-button>
        'tailwind'      => 'v4',
        'sidebar_items' => [
            // ['icon' => 'bx bx-home-alt', 'label' => 'Dashboard', 'url' => '/dashboard', 'match' => 'dashboard'],
        ],
    ],

    // Defaults para o scaffolding
    'scaffold' => [
        'layout'      => 'forge-dashboard',
        'auth_layout' => 'forge-auth',
    ],

    // BaseCrud
    'crud' => [
        'cache_enabled'  => true,
        'cache_ttl'      => 3600,   // TTL padrão do cache de configuração (segundos)
        'per_page'       => 25,     // Itens por página padrão
        'soft_deletes'   => true,   // Exibe botão de lixeira quando o model usa SoftDeletes
        'confirm_delete' => true,   // Modal de confirmação antes de excluir
        'export_driver'  => 'excel', // Driver de exportação: excel | csv | pdf
    ],
];
```

### Configurando o Sidebar

```php
'forge' => [
    'sidebar_items' => [
        ['icon' => 'bx bx-home-alt',  'label' => 'Dashboard',    'url' => '/dashboard',  'match' => 'dashboard'],
        ['icon' => 'bx bx-user',      'label' => 'Usuários',     'url' => '/users',      'match' => 'users*'],
        ['icon' => 'bx bx-cube',      'label' => 'Produtos',     'url' => '/products',   'match' => 'products*'],
        ['icon' => 'bx bx-bar-chart', 'label' => 'Relatórios',   'url' => '/reports',    'match' => 'reports*'],
        ['icon' => 'bx bx-cog',       'label' => 'Configurações','url' => '/settings',   'match' => 'settings*'],
    ],
],
```

> Use classes **Boxicons** (`bx bx-*`) ou **FontAwesome** (`fas fa-*` / `fab fa-*`). Ambas as libs são carregadas automaticamente pelo `forge-dashboard-layout`.

---

## 🔧 Customizando Stubs

Após publicar com `ptah:install` ou `vendor:publish --tag=ptah-stubs`, os stubs ficam em `stubs/ptah/`. O Ptah sempre prioriza stubs publicados sobre os do pacote.

| Stub | Artefato gerado |
|---|---|
| `model.stub` | Model Eloquent |
| `migration.stub` | Migration |
| `dto.stub` | DTO |
| `repository.stub` | Repository |
| `repository.interface.stub` | Interface do Repository |
| `service.stub` | Service |
| `controller.stub` | Controller Web |
| `controller.api.stub` | Controller API |
| `request.store.stub` | StoreRequest |
| `request.update.stub` | UpdateRequest |
| `resource.stub` | API Resource |
| `view.index.stub` | View de listagem (usa `@livewire('ptah::base-crud', ['model' => '{{ entity }}'])`) |
| `view.create.stub` | View de criação |
| `view.edit.stub` | View de edição |
| `view.show.stub` | View de detalhes |

### Variáveis disponíveis nos stubs

| Variável | Descrição | Exemplo |
|---|---|---|
| `{{ entity }}` | Nome da entidade | `Product` |
| `{{ entityLower }}` | Snake case singular | `product` |
| `{{ entityPlural }}` | Snake case plural | `products` |
| `{{ table }}` | Nome da tabela | `products` |
| `{{ namespace }}` | Namespace raiz | `App` |
| `{{ fillable }}` | Array $fillable | `'name', 'price'` |
| `{{ casts }}` | Array $casts | `'price' => 'decimal:2'` |
| `{{ columns }}` | Linhas da migration | `$table->string('name');` |
| `{{ rules }}` | Regras de validação | `'name' => ['required', 'string']` |
| `{{ dto_properties }}` | Propriedades readonly | `public readonly string $name,` |
| `{{ dto_from_array }}` | Linhas do fromArray | `name: $data['name'],` |
| `{{ resource_fields }}` | Campos do Resource | `'name' => $this->name,` |
| `{{ soft_deletes_use }}` | Use SoftDeletes | `use Illuminate\...SoftDeletes;` |
| `{{ soft_deletes_trait }}` | Trait no model | `use SoftDeletes;` |

---

## 🧪 Testes

O pacote inclui uma suíte de testes com [Orchestra Testbench](https://github.com/orchestral/testbench) e banco SQLite em memória.

### Executar

```bash
cd ptah/
vendor/bin/phpunit
```

### Estrutura

```
tests/
├── TestCase.php                        ← Base com Testbench + RefreshDatabase
├── Factories/
│   └── CompanyFactory.php              ← Factory sem Eloquent Factory nativo
├── Unit/
│   └── Models/
│       └── CompanyModelTest.php        ← Testes unitários do model Company
└── Feature/
    └── Livewire/
        └── CompanyListTest.php         ← Testes de feature do CRUD de empresas
```

### Cobertura principal

| Arquivo de teste | O que cobre |
|---|---|
| `CompanyModelTest` | `getLabelDisplay()`, scopes `active`/`default`, auto-slug, soft-delete |
| `CompanyListTest` | Renderização, criação, edição, unicidade de label, exclusão, busca |

### Ambiente

O `phpunit.xml` configura automaticamente:
- `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- `APP_ENV=testing`
- Migrations do pacote carregadas via `loadMigrationsFrom()`

---

## 🤖 Laravel Boost — Integração com IA

O Ptah inclui suporte nativo ao [Laravel Boost](https://laravel.com/docs/12.x/boost), permitindo que agentes de IA (GitHub Copilot, Claude Code, Cursor…) conheçam automaticamente as convenções do pacote ao trabalhar nos seus projetos.

### Instalação do Boost no seu projeto

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Ao rodar `boost:install`, o Ptah contribui automaticamente com:

| Arquivo | Tipo | Contéudo |
|---|---|---|
| `resources/boost/guidelines/core.blade.php` | **Guideline** | Convenções do pacote carregadas no início de cada sessão |
| `resources/boost/skills/ptah-development/SKILL.md` | **Skill** | Padrões detalhados ativados sob demanda |

**Guidelines** são carregadas sempre — informam o agente sobre scaffolding, ícones, dark mode e testes.  
**Skills** são ativadas quando necessário — detalham BaseCrud, modules, validações e fluxo SOLID.

### Manter atualizado

```bash
php artisan boost:update
```

Para automatizar, adicione ao `composer.json`:

```json
"scripts": {
    "post-update-cmd": ["@php artisan boost:update --ansi"]
}
```

---

## 💡 Desenvolvimento com IA

O arquivo [`docs/AI_Guide.md`](docs/AI_Guide.md) documenta como usar agentes de IA de forma eficaz com o Ptah.

**O que o guia cobre:**

| Seção | Conteúdo |
|---|---|
| Anatomia de um prompt | Estrutura: contexto, campos, regras, integração, padrão |
| Criar nova entidade | Template de prompt para `ptah:forge` completo |
| Configurar BaseCrud | JSON de colunas, badges, filtros e modal |
| Criar módulo opcional | Padrão para novos módulos seguindo a arquitetura do pacote |
| Adicionar validação | `Rule::unique`, `wire:model.blur`, eventos Livewire |
| Escrever testes | `CompanyFactory::new()`, Testbench, feature Livewire |
| Workflow recomendado | Scaffold → BaseCrud → regras → testes → docs → commit |
| Armadilhas comuns | O que o agente tende a errar e como evitar |

---

## 📟 Comandos disponíveis

| Comando | Descrição |
|---|---|
| `php artisan ptah:install` | Instala o pacote (publica config, stubs, migrations). Use `--demo` para popular dados de exemplo. |
| `php artisan ptah:forge {Entity}` | **Gera estrutura completa de uma entidade** ⭐ |
| `php artisan ptah:module {auth\|menu\|company\|permissions}` | Ativa módulo opcional (publica migrations + atualiza .env) |
| `php artisan ptah:module --list` | Lista módulos disponíveis e seus estados |
| `php artisan ptah:make {Entity}` | Gerador legado (sem `--fields` e `--db`) |
| `php artisan ptah:make-api {Entity}` | Gerador legado somente API |
| `php artisan ptah:docs {Entity}` | Gera anotações Swagger/OpenAPI |

---

## 📄 Licença

Este pacote é open source, licenciado sob a [Licença MIT](LICENSE).

---

<div align="center">
  <p>Feito com ❤️ por <a href="https://github.com/jonytonet">jonytonet</a></p>
  <p><em>"Ptah criou o mundo através das palavras e pensamentos do seu coração."</em></p>
</div>



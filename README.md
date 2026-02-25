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
  - [Layout Auth](#layout-auth)
  - [Componentes disponíveis](#componentes-disponíveis)
  - [Demo](#demo)
- [ptah:forge — Scaffolding](#-ptahforge--scaffolding)
  - [Uso básico](#uso-básico)
  - [Definindo campos](#definindo-campos)
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
  - [CrudConfigService](#crudconfigservice)
  - [CacheService](#cacheservice)
- [Configuração](#-configuração)
- [Customizando Stubs](#-customizando-stubs)
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
| `color` | string | `primary` `success` `danger` `warn` `dark` | `primary` |
| `size` | string | `sm` `md` `lg` | `md` |
| `tag` | string | `button` `a` | `button` |
| `flat` | bool | — | `false` |
| `relief` | bool | — | `false` |
| `rounded` | bool | — | `false` |
| `loading` | bool | — | `false` |
| `disabled` | bool | — | `false` |

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

#### `forge-tabs`

```blade
<x-forge-tabs :tabs="[
    ['key' => 'info',    'label' => 'Dados'],
    ['key' => 'history', 'label' => 'Histórico'],
]">
    <x-slot:info>
        <p>Conteúdo da aba de dados.</p>
    </x-slot:info>
    <x-slot:history>
        <p>Conteúdo do histórico.</p>
    </x-slot:history>
</x-forge-tabs>
```

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
| `forge-badge` | Badges coloridos com suporte a ponto animado |
| `forge-avatar` | Avatar com iniciais, foto ou badge de status |
| `forge-breadcrumb` | Navegação em migalhas de pão |
| `forge-spinner` | Indicadores de carregamento (circle, dots, wave) |
| `forge-progress` | Barra de progresso com animação |
| `forge-list` | Lista de itens com avatar, descrição e badge |
| `forge-stepper` | Passos de um processo (wizard) |
| `forge-chart-card` | Card wrapper para gráficos |
| `forge-navbar` | Barra de navegação superior com dropdown de usuário |
| `forge-sidebar` | Sidebar responsiva (icon bar no `md`, expandida no `lg`) |

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

Gera 16+ artefatos em segundos: Model, Migration, DTO, RepositoryInterface, Repository, Service, Controller, StoreRequest, UpdateRequest, Resource, 4 Views, a rota **e a configuração completa do BaseCrud** salva no banco.

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
| `unsignedBigInteger` | `ubigint` | `$table->unsignedBigInteger('campo')` |
| `decimal(p,s)` | — | `$table->decimal('campo', p, s)` |
| `float` | — | `$table->float('campo')` |
| `boolean` | `bool` | `$table->boolean('campo')` |
| `date` | — | `$table->date('campo')` |
| `datetime` | `timestamp` | `$table->timestamp('campo')` |
| `json` | — | `$table->json('campo')` |
| `enum(a\|b\|c)` | — | `$table->enum('campo', ['a','b','c'])` |

**Modificadores:**

```bash
# :nullable   ->nullable()
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
 ptah:forge ......................................... Product

  Artefato                                    Status
  ──────────────────────────────────────────────────────
  Model [Product]                             ✅ DONE
  Migration [create_products_table]           ✅ DONE
  DTO [ProductDTO]                            ✅ DONE
  Interface [ProductRepositoryInterface]      ✅ DONE
  Repository [ProductRepository]              ✅ DONE
  Service [ProductService]                    ✅ DONE
  Controller [ProductController]              ✅ DONE
  Request [StoreProductRequest]               ✅ DONE
  Request [UpdateProductRequest]              ✅ DONE
  Resource [ProductResource]                  ✅ DONE
  CrudConfig [Product]                        ✅ DONE
  View [product/index]                        ✅ DONE
  View [product/create]                       ✅ DONE
  View [product/edit]                         ✅ DONE
  View [product/show]                         ✅ DONE
  Routes [web.php]                            ✅ DONE

 Próximos passos: (...)
```

---

### Arquivos gerados

```
app/
├── Models/
│   └── Product.php
├── DTOs/
│   └── ProductDTO.php
├── Repositories/
│   ├── Contracts/
│   │   └── ProductRepositoryInterface.php
│   └── ProductRepository.php
├── Services/
│   └── ProductService.php
└── Http/
    ├── Controllers/
    │   ├── ProductController.php          ← web
    │   └── Api/ProductApiController.php   ← --api
    ├── Requests/
    │   ├── StoreProductRequest.php
    │   └── UpdateProductRequest.php
    └── Resources/
        └── ProductResource.php

database/migrations/
    └── xxxx_create_products_table.php

database/crud_configs (tabela via migration do ptah)
    └── model=Product  ← JSON completo gerado pelo CrudConfigGenerator

resources/views/product/
    ├── index.blade.php    ← @livewire('ptah::base-crud', ['model' => 'Product'])
    ├── create.blade.php   ← forge-input + forge-button
    ├── edit.blade.php
    └── show.blade.php     ← forge-card

routes/web.php  ← Route::resource('product', ProductController::class)
routes/api.php  ← Route::apiResource('products', ProductApiController::class)
```

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

Fino, responsável apenas por HTTP. Delega toda lógica ao Service.

```php
class ProductController extends Controller
{
    public function __construct(protected ProductService $service) {}

    public function index(): View
    {
        $products = $this->service->paginate();
        return view('product.index', compact('products'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());
        return redirect()->route('product.index')->with('success', 'Produto criado com sucesso.');
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
         ├── ViewGenerator              (shouldRun: sem --api, gera 4 views)
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
| `colsHelper` | string | Helper de formatação da célula (ver tabela de helpers) |
| `colsIsFilterable` | `'S'`/`'N'` | Exibe no painel de filtros |
| `colsRelacao` | string | Nome do relacionamento Eloquent (`$product->category`) |
| `colsRelacaoExibe` | string | Atributo do objeto relacionado a exibir |
| `colsSelect` | object | Mapa `{"Label": "valor"}` para tipo select |
| `colsOrderBy` | string | Coluna alternativa para ORDER BY |
| `colsReverse` | `'S'`/`'N'` | Inverte sort (DESC quando `'S'`) |
| `colsMetodoCustom` | string | Método custom para formatar a célula |

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

Definidos em `colsHelper`. Aplicados em `formatCell()` antes de exibir na tabela.

| Helper | Entrada | Saída exemplo |
|---|---|---|
| `dateFormat` | `2025-02-14` | `14/02/2025` |
| `dateTimeFormat` | `2025-02-14 15:30:00` | `14/02/2025 15:30` |
| `currencyFormat` | `1234.50` | `R$ 1.234,50` |
| `yesOrNot` | `1` / `0` | `Sim` / `Não` |
| `flagChannel` | `'G'` / `'Y'` / `'R'` | Badge verde/amarelo/vermelho |

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
            // ['icon' => 'home', 'label' => 'Dashboard', 'url' => '/dashboard', 'match' => 'dashboard'],
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
        ['icon' => 'home',      'label' => 'Dashboard',    'url' => '/dashboard',  'match' => 'dashboard'],
        ['icon' => 'users',     'label' => 'Usuários',     'url' => '/users',      'match' => 'users*'],
        ['icon' => 'cube',      'label' => 'Produtos',     'url' => '/products',   'match' => 'products*'],
        ['icon' => 'chart-bar', 'label' => 'Relatórios',   'url' => '/reports',    'match' => 'reports*'],
        ['icon' => 'cog',       'label' => 'Configurações','url' => '/settings',   'match' => 'settings*'],
    ],
],
```

Ícones disponíveis: `home` `users` `cube` `chart-bar` `cog`

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

## 📟 Comandos disponíveis

| Comando | Descrição |
|---|---|
| `php artisan ptah:install` | Instala o pacote (publica config, stubs, migrations) |
| `php artisan ptah:forge {Entity}` | **Gera estrutura completa de uma entidade** ⭐ |
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



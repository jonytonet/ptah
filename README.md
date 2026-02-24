# ⚒️ PTAH — Manifesto Técnico

> Ptah — Deus egípcio da criação, dos artesãos e arquitetos.
> Criou o mundo pela palavra. Você cria sistemas pelo comando.

---

## 🧭 Filosofia

Ptah é um pacote Laravel que une:
1. **Geração automática de toda estrutura** de um módulo a partir de uma tabela
2. **BaseCrud dinâmico** com Livewire 3 — uma tela CRUD completa sem escrever código
3. **Biblioteca de componentes visuais** `pt-*` (inspirada no Vuesax V3, Tailwind CSS v3 + Alpine.js)
4. **Auth completo** com Roles & Permissions
5. **Scaffold** de dashboard + sidebar prontos

---

## ⚖️ As Leis Supremas — SOLID (invioláveis)

```
S — Single Responsibility
      Cada classe tem UMA razão para mudar.
      FilterService filtra. CacheService cacheia. Nunca os dois juntos.

O — Open/Closed
      Extensível sem modificar o core.
      Stubs são publicáveis e customizáveis.
      Novas FilterStrategies são adicionadas sem tocar no FilterService.

L — Liskov Substitution
      Qualquer ProdutoRepository pode substituir BaseRepository.
      Qualquer implementação de FilterStrategyInterface é aceita.

I — Interface Segregation
      Contratos pequenos e específicos.
      BaseRepositoryInterface não carrega métodos que não usa.
      Cada contrato tem no máximo 5-7 métodos.

D — Dependency Inversion
      NUNCA: new Service() dentro de outra classe.
      SEMPRE: injeção via constructor.
      SEMPRE: depender de interfaces, não de implementações concretas.
```

---

## 📦 Stack

| Tecnologia | Versão |
|---|---|
| PHP | 8.2+ |
| Laravel | 11+ / 12+ |
| Livewire | 3.x |
| Tailwind CSS | 3.x |
| Alpine.js | 3.x |

---

## 🗂️ Estrutura completa do pacote

```
ptah/
├── composer.json
├── README.md
│
├── config/
│   └── ptah.php
│
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_ptah_pages_table.php
│       ├── 2024_01_01_000002_create_ptah_page_objects_table.php
│       ├── 2024_01_01_000003_create_ptah_page_object_params_table.php
│       ├── 2024_01_01_000004_create_ptah_profiles_table.php
│       ├── 2024_01_01_000005_create_ptah_permissions_table.php
│       ├── 2024_01_01_000006_create_ptah_departments_table.php
│       ├── 2024_01_01_000007_create_ptah_user_profiles_table.php
│       ├── 2024_01_01_000008_create_ptah_user_preferences_table.php
│       └── 2024_01_01_000009_create_ptah_crud_configs_table.php
│
├── resources/
│   ├── css/app.css
│   ├── js/app.js
│   ├── stubs/                              ← publicáveis e customizáveis
│   │   ├── model.stub
│   │   ├── model.relationship.stub
│   │   ├── repository.stub
│   │   ├── repository.interface.stub
│   │   ├── service.stub
│   │   ├── service.interface.stub
│   │   ├── dto.stub
│   │   ├── dto.create.stub
│   │   ├── dto.update.stub
│   │   ├── request.create.stub
│   │   ├── request.update.stub
│   │   ├── controller.api.stub
│   │   ├── controller.web.stub
│   │   ├── livewire.class.stub
│   │   ├── livewire.view.stub
│   │   ├── migration.stub
│   │   ├── route.web.stub
│   │   └── route.api.stub
│   └── views/
│       ├── components/                     ← Biblioteca pt-* (ex-Vuesax)
│       │   ├── pt-button.blade.php
│       │   ├── pt-input.blade.php
│       │   ├── pt-textarea.blade.php
│       │   ├── pt-select.blade.php
│       │   ├── pt-checkbox.blade.php
│       │   ├── pt-radio.blade.php
│       │   ├── pt-switch.blade.php
│       │   ├── pt-card.blade.php
│       │   ├── pt-alert.blade.php
│       │   ├── pt-modal.blade.php
│       │   ├── pt-badge.blade.php
│       │   ├── pt-avatar.blade.php
│       │   ├── pt-spinner.blade.php
│       │   ├── pt-progress.blade.php
│       │   ├── pt-notification.blade.php
│       │   ├── pt-navbar.blade.php
│       │   ├── pt-sidebar.blade.php
│       │   ├── pt-breadcrumb.blade.php
│       │   ├── pt-tabs.blade.php
│       │   ├── pt-stepper.blade.php
│       │   ├── pt-table.blade.php
│       │   ├── pt-pagination.blade.php
│       │   ├── pt-stat-card.blade.php
│       │   ├── pt-chart-card.blade.php
│       │   ├── pt-list.blade.php
│       │   └── pt-dashboard-layout.blade.php
│       ├── layouts/
│       │   ├── dashboard.blade.php
│       │   └── auth.blade.php
│       ├── livewire/
│       │   ├── base/
│       │   │   ├── base-crud.blade.php
│       │   │   ├── base-crud-create.blade.php
│       │   │   ├── base-crud-filters.blade.php
│       │   │   └── menu.blade.php
│       │   └── auth/
│       │       ├── login.blade.php
│       │       ├── register.blade.php
│       │       └── forgot-password.blade.php
│       └── scaffold/
│           ├── dashboard.blade.php
│           └── sidebar.blade.php
│
└── src/
    ├── PtahServiceProvider.php
    │
    ├── Console/
    │   └── Commands/
    │       ├── InstallCommand.php           ← ptah:install
    │       ├── MakeAllCommand.php           ← ptah:make {Model} --table=
    │       ├── MakeModelCommand.php         ← ptah:model
    │       ├── MakeMigrationCommand.php     ← ptah:migration
    │       ├── MakeDtoCommand.php           ← ptah:dto
    │       ├── MakeRepositoryCommand.php    ← ptah:repository
    │       ├── MakeServiceCommand.php       ← ptah:service
    │       ├── MakeRequestCommand.php       ← ptah:request
    │       ├── MakeControllerCommand.php    ← ptah:controller
    │       ├── MakeLivewireCommand.php      ← ptah:livewire
    │       ├── MakeRouteCommand.php         ← ptah:route
    │       └── MakeAuthCommand.php          ← ptah:auth
    │
    ├── Contracts/                           ← Interfaces (SOLID - DIP)
    │   ├── Repositories/
    │   │   ├── BaseRepositoryInterface.php
    │   │   └── CrudRepositoryInterface.php
    │   ├── Services/
    │   │   ├── BaseServiceInterface.php
    │   │   ├── FilterStrategyInterface.php
    │   │   ├── CacheServiceInterface.php
    │   │   └── PreferencesServiceInterface.php
    │   └── Generators/
    │       ├── StubGeneratorInterface.php
    │       └── SchemaReaderInterface.php
    │
    ├── DTO/                                 ← Imutáveis, readonly
    │   ├── Crud/
    │   │   ├── CrudConfigDTO.php            ← substitui $crudConfig array
    │   │   ├── ColumnDTO.php
    │   │   ├── FilterDTO.php
    │   │   ├── PaginationDTO.php
    │   │   ├── ExportConfigDTO.php
    │   │   └── BulkActionDTO.php
    │   ├── Auth/
    │   │   ├── LoginDTO.php
    │   │   └── RegisterDTO.php
    │   ├── Preferences/
    │   │   └── PreferenceDTO.php
    │   └── Generator/
    │       ├── ColumnDefinitionDTO.php
    │       ├── TableSchemaDTO.php
    │       ├── RelationshipDTO.php
    │       ├── ModelConfigDTO.php
    │       ├── StubDataDTO.php
    │       └── RouteDefinitionDTO.php
    │
    ├── Models/                              ← Models internos do Ptah
    │   ├── PtahPage.php
    │   ├── PtahPageObject.php
    │   ├── PtahPageObjectParam.php
    │   ├── PtahProfile.php
    │   ├── PtahPermission.php
    │   ├── PtahDepartment.php
    │   ├── PtahUserProfile.php
    │   ├── PtahUserPreference.php
    │   └── PtahCrudConfig.php
    │
    ├── Repositories/
    │   └── Base/
    │       ├── BaseRepository.php           ← abstract, tipado, sem Request
    │       └── BaseCrudRepository.php
    │
    ├── Services/
    │   ├── Base/
    │   │   ├── BaseService.php              ← abstract, eventos, DB::transaction
    │   │   ├── BaseCrudService.php
    │   │   ├── Cache/
    │   │   │   └── CacheService.php
    │   │   ├── Filters/
    │   │   │   ├── FilterService.php
    │   │   │   └── Strategies/
    │   │   │       ├── TextFilterStrategy.php
    │   │   │       ├── NumericFilterStrategy.php
    │   │   │       ├── DateFilterStrategy.php
    │   │   │       ├── RelationFilterStrategy.php
    │   │   │       ├── ArrayFilterStrategy.php
    │   │   │       ├── NullFilterStrategy.php
    │   │   │       └── JsonFilterStrategy.php
    │   │   └── Export/
    │   │       └── ExportService.php
    │   ├── Auth/
    │   │   ├── AuthService.php
    │   │   └── PermissionService.php
    │   ├── Preferences/
    │   │   └── PreferencesService.php       ← banco + cache (sem JSON em disco)
    │   └── Generator/
    │       ├── SchemaReaderService.php       ← multi-DB: MySQL, Postgres, SQLite
    │       ├── StubResolverService.php
    │       ├── RouteWriterService.php
    │       └── SwaggerGeneratorService.php
    │
    ├── Http/
    │   └── Livewire/
    │       ├── Base/
    │       │   ├── BaseCrud.php             ← Livewire 3 + Traits
    │       │   ├── BaseCrudCreate.php
    │       │   ├── BaseCrudFilters.php
    │       │   └── Menu.php
    │       └── Auth/
    │           ├── Login.php
    │           ├── Register.php
    │           └── ForgotPassword.php
    │
    ├── Traits/
    │   └── Livewire/
    │       ├── HasSorting.php
    │       ├── HasFilters.php
    │       ├── HasExport.php
    │       ├── HasPermissions.php
    │       ├── HasPreferences.php
    │       ├── HasBulkActions.php
    │       ├── HasTrashed.php
    │       └── HasPagination.php
    │
    ├── Helpers/
    │   └── PtahHelpers.php
    │
    └── Validation/
        ├── Rules/
        │   ├── CnpjRule.php
        │   ├── CpfRule.php
        │   ├── PhoneRule.php
        │   └── CepRule.php
        └── ConfigurationValidator.php
```

---

## ⚡ Comandos Artisan

```bash
# ─── INSTALAÇÃO ───────────────────────────────────────────────
php artisan ptah:install
# Publica: config, views, migrations, stubs
# Roda migrations do ptah
# Publica assets (CSS/JS)
# Pergunta: instalar scaffold? instalar auth?

# ─── GERADOR PRINCIPAL ────────────────────────────────────────
php artisan ptah:make Produto --table=produtos
# Executa TODOS os geradores em sequência

# ─── GERADORES INDIVIDUAIS ────────────────────────────────────
php artisan ptah:migration  Produto --table=produtos
# → database/migrations/xxxx_create_produtos_table.php

php artisan ptah:model      Produto --table=produtos
# → app/Models/Produto.php
# Com: fillable, casts, relationships, SoftDeletes, Swagger @OA

php artisan ptah:dto        Produto --table=produtos
# → app/DTO/Produto/ProdutoDTO.php
# → app/DTO/Produto/CreateProdutoDTO.php
# → app/DTO/Produto/UpdateProdutoDTO.php

php artisan ptah:repository Produto
# → app/Repositories/Produto/ProdutoRepository.php
# → app/Contracts/Repositories/ProdutoRepositoryInterface.php

php artisan ptah:service    Produto
# → app/Services/Produto/ProdutoService.php
# → app/Contracts/Services/ProdutoServiceInterface.php

php artisan ptah:request    Produto --table=produtos
# → app/Http/Requests/Produto/CreateProdutoRequest.php
# → app/Http/Requests/Produto/UpdateProdutoRequest.php

php artisan ptah:controller Produto --table=produtos
# → app/Http/Controllers/API/Produto/ProdutoApiController.php
# → app/Http/Controllers/Web/Produto/ProdutoController.php

php artisan ptah:livewire   Produto
# → app/Livewire/Produto/Index.php
# → resources/views/livewire/produto/index.blade.php

php artisan ptah:route      Produto
# Escreve em routes/web.php e routes/api.php sem sobrescrever

php artisan ptah:auth
# Publica Login, Register, ForgotPassword com visual pt-*
```

---

## 🔄 Fluxo do `ptah:make`

```
ptah:make Produto --table=produtos
│
├─ 1. SchemaReaderService::read('produtos')
│     └─ TableSchemaDTO {
│          table, modelName, primaryKey,
│          hasSoftDeletes, hasTimestamps,
│          hasCreatedBy, hasUpdatedBy,
│          columns: [ColumnDefinitionDTO],
│          relationships: [RelationshipDTO]
│        }
│
├─ 2. ptah:migration  → TableSchemaDTO → migration.stub
├─ 3. ptah:model      → TableSchemaDTO + ModelConfigDTO → model.stub
├─ 4. ptah:dto        → TableSchemaDTO → dto.stub (3 arquivos)
├─ 5. ptah:repository → ModelConfigDTO → repository.stub + interface
├─ 6. ptah:service    → ModelConfigDTO → service.stub + interface
├─ 7. ptah:request    → TableSchemaDTO → request.create + update (rules auto)
├─ 8. ptah:controller → TableSchemaDTO → api + web controller + Swagger
├─ 9. ptah:livewire   → ModelConfigDTO → class + view (pt-* components)
├─ 10. ptah:route     → RouteDefinitionDTO → RouteWriterService::append()
└─ 11. SwaggerGeneratorService::generate()
```

---

## 📋 DTOs

```php
// TableSchemaDTO — imutável, resultado da leitura do banco
final class TableSchemaDTO
{
    public function __construct(
        public readonly string $table,
        public readonly string $modelName,
        public readonly string $modelNamePlural,
        public readonly string $modelNameSnake,
        public readonly string $primaryKey,
        public readonly bool $hasSoftDeletes,
        public readonly bool $hasTimestamps,
        public readonly bool $hasCreatedBy,
        public readonly bool $hasUpdatedBy,
        /** @var ColumnDefinitionDTO[] */
        public readonly array $columns,
        /** @var RelationshipDTO[] */
        public readonly array $relationships,
    ) {}
}

// ColumnDefinitionDTO — cada coluna lida do banco
final class ColumnDefinitionDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $dbType,
        public readonly string $phpType,
        public readonly string $castType,
        public readonly string $swaggerType,
        public readonly string $validationRule,
        public readonly bool $nullable,
        public readonly bool $isPrimaryKey,
        public readonly bool $isForeignKey,
        public readonly ?string $comment,
        public readonly ?int $maxLength,
        public readonly ?int $decimalPlaces,
    ) {}
}

// CrudConfigDTO — substitui $crudConfig array em todo o BaseCrud
final class CrudConfigDTO
{
    public function __construct(
        public readonly string $model,
        public readonly string $crudTitle,
        public readonly bool $hideId,
        public readonly ?string $rowLink,
        public readonly bool $showTotalizador,
        /** @var ColumnDTO[] */
        public readonly array $columns,
        public readonly array $customFilters,
        public readonly CacheStrategyDTO $cache,
        public readonly ExportConfigDTO $export,
        public readonly UiPreferencesDTO $ui,
        public readonly PermissionsConfigDTO $permissions,
    ) {}

    public static function fromDatabase(string $model): self {}
    public static function fromArray(array $data): self {}
    public function toArray(): array {}
    public function toJson(): string {}
}

// PaginationDTO
final class PaginationDTO
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly string $orderBy = 'id',
        public readonly string $direction = 'ASC',
        public readonly ?string $search = null,
    ) {}

    public static function fromArray(array $data): self {}
}

// PreferenceDTO
final class PreferenceDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $route,
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $version = '2.0.0',
    ) {}
}

// StubDataDTO
final class StubDataDTO
{
    public function __construct(
        public readonly string $stubName,
        public readonly array $replacements,
        public readonly string $outputPath,
        public readonly bool $overwrite = false,
    ) {}
}

// RouteDefinitionDTO
final class RouteDefinitionDTO
{
    public function __construct(
        public readonly string $modelName,
        public readonly string $modelNameSnake,
        public readonly string $controllerApiClass,
        public readonly string $controllerWebClass,
        public readonly string $livewireClass,
        public readonly bool $hasApi = true,
        public readonly bool $hasWeb = true,
        public readonly ?string $middleware = 'auth',
        public readonly ?string $prefix = null,
    ) {}
}
```

---

## 🗄️ Banco de dados — tabelas do Ptah

```sql
-- Preferências de usuário (substitui JSON em disco)
ptah_user_preferences
  id, user_id (FK users), route, key, value (json),
  version, created_at, updated_at
  UNIQUE(user_id, route, key)
  INDEX(user_id, route)

-- Config CRUD (substitui JSON em storage/)
ptah_crud_configs
  id, model (unique), config (json), version,
  created_at, updated_at

-- Páginas do sistema
ptah_pages
  id, name, description, created_by, updated_by,
  created_at, updated_at

-- Objetos de uma página (botões, campos, seções)
ptah_page_objects
  id, pages_id (FK), page_section, obj_id, obj_name,
  obj_label, obj_type, obj_required, obj_url,
  obj_order, obj_description, created_at, updated_at

-- Permissões por perfil em cada objeto
ptah_page_object_params
  id, page_objects_id (FK), profiles_id (FK),
  permission_create, permission_read,
  permission_update, permission_delete,
  created_at, updated_at

-- Perfis de acesso
ptah_profiles
  id, description, active, created_by, updated_by,
  deleted_at, created_at, updated_at

-- Departamentos
ptah_departments
  id, description, active, created_by, updated_by,
  deleted_at, created_at, updated_at

-- Relação departamento ↔ perfil
ptah_department_profiles
  id, departments_id (FK), profiles_id (FK),
  active, created_at, updated_at

-- Relação usuário ↔ perfil ↔ empresa
ptah_user_profiles
  id, user_id (FK users), profiles_id (FK),
  companies_id, active,
  created_at, updated_at
```

---

## 🔐 Permissões

```
Hierarquia:
User → ptah_user_profiles → ptah_profiles
     → ptah_page_object_params → ptah_page_objects → ptah_pages

// Helper global
ptahCan('produtos', 'create') : bool
ptahCan('produtos', 'read')   : bool
ptahCan('produtos', 'update') : bool
ptahCan('produtos', 'delete') : bool

// No Blade
@ptahCan('produtos', 'create')
    <x-pt-button color="primary">Novo</x-pt-button>
@endPtahCan

// No Livewire (Trait HasPermissions)
class Index extends BaseCrud
{
    use HasPermissions;
    protected string $permissionPage = 'produtos';
}
```

---

## 🎨 Biblioteca de componentes `pt-*`

Inspirada visualmente no Vuesax V3. Tailwind CSS v3 + Alpine.js.

```
26 componentes:
├── Formulários:  pt-button, pt-input, pt-textarea, pt-select,
│                 pt-checkbox, pt-radio, pt-switch
├── Feedback:     pt-card, pt-alert, pt-modal, pt-badge,
│                 pt-avatar, pt-spinner, pt-progress, pt-notification
├── Navegação:    pt-navbar, pt-sidebar, pt-breadcrumb,
│                 pt-tabs, pt-stepper, pt-pagination
├── Dashboard:    pt-stat-card, pt-table, pt-chart-card, pt-list
└── Layout:       pt-dashboard-layout
```

---

## 🔄 Preferências — banco + cache

```
ptah_user_preferences
├── perPage       → 15, 25, 50, 100
├── columns       → colunas visíveis/ocultas
├── orderBy       → coluna de ordenação padrão
├── direction     → ASC | DESC
├── viewMode      → table | cards
├── density       → compact | comfortable | spacious
├── filters       → últimos filtros usados
└── savedFilters  → filtros salvos pelo usuário

Fluxo:
Livewire mount → PreferencesService::getAll(userId, route)
              → Cache::remember("ptah:prefs:{u}:{r}", 3600, DB)
Usuário muda  → PreferencesService::set(userId, route, key, value)
              → DB::updateOrInsert + Cache::forget
```

---

## 🎯 Validation Rules

```php
// Geradas automaticamente pelo ptah:request
// baseadas no nome e tipo da coluna:

'cnpj'      → ['nullable', 'string', new CnpjRule]
'cpf'       → ['nullable', 'string', new CpfRule]
'telefone'  → ['nullable', 'string', new PhoneRule]
'celular'   → ['nullable', 'string', new PhoneRule]
'cep'       → ['nullable', 'string', new CepRule]
'email'     → ['nullable', 'email', 'max:255']
'preco'     → ['nullable', 'numeric', 'min:0']
'valor'     → ['nullable', 'numeric', 'min:0']
'active'    → ['nullable', 'in:S,N']
'ativo'     → ['nullable', 'in:S,N']
'int'       → ['nullable', 'integer']
'decimal'   → ['nullable', 'numeric']
'varchar'   → ['nullable', 'string', 'max:{length}']
'text'      → ['nullable', 'string']
'date'      → ['nullable', 'date']
'datetime'  → ['nullable', 'date_format:Y-m-d H:i:s']
```

---

## ⚙️ config/ptah.php

```php
return [
    'prefix'  => 'ptah',
    'vendor'  => 'jonytonet',

    'namespace' => [
        'models'      => 'App\\Models',
        'dto'         => 'App\\DTO',
        'repositories'=> 'App\\Repositories',
        'contracts'   => 'App\\Contracts',
        'services'    => 'App\\Services',
        'controllers' => 'App\\Http\\Controllers',
        'requests'    => 'App\\Http\\Requests',
        'livewire'    => 'App\\Livewire',
        'rules'       => 'App\\Rules',
    ],

    'database' => [
        'driver' => env('DB_CONNECTION', 'mysql'),
    ],

    'auth' => [
        'model'       => 'App\\Models\\User',
        'guard'       => 'web',
        'permissions' => true,
    ],

    'cache' => [
        'enabled' => true,
        'ttl'     => [
            'config' => 86400,
            'prefs'  => 3600,
            'query'  => 60,
        ],
    ],

    'ui' => [
        'theme'         => 'ptah',
        'primary_color' => '#5b21b6',
        'component_prefix' => 'pt',
    ],

    'swagger' => [
        'enabled'       => true,
        'auto_generate' => true,
    ],

    'export' => [
        'async_threshold' => 1000,
        'max_rows'        => 10000,
        'formats'         => ['excel', 'pdf', 'csv'],
    ],
];
```

---

## 🗺️ Roadmap

```
Fase 1 — Core
  ✦ composer.json + PtahServiceProvider
  ✦ config/ptah.php
  ✦ Migrations do sistema (9 tabelas)
  ✦ DTOs (todos, readonly)
  ✦ Contracts / Interfaces

Fase 2 — Base
  ✦ BaseRepository (reescrito, tipado)
  ✦ BaseService (reescrito, eventos, transações)
  ✦ FilterService + todas as Strategies
  ✦ CacheService
  ✦ PreferencesService (banco + cache)

Fase 3 — Geradores
  ✦ SchemaReaderService (MySQL + Postgres + SQLite)
  ✦ StubResolverService
  ✦ RouteWriterService
  ✦ SwaggerGeneratorService
  ✦ Todos os Commands
  ✦ Todos os Stubs

Fase 4 — BaseCrud Livewire 3
  ✦ BaseCrud com Traits
  ✦ Migração Livewire 2 → 3
  ✦ Views migradas Bootstrap → Tailwind/pt-*
  ✦ CrudConfigDTO no lugar de arrays

Fase 5 — Auth + Scaffold
  ✦ Login / Register / ForgotPassword (pt-*)
  ✦ Scaffold dashboard + sidebar
  ✦ Painel de perfis e permissões
  ✦ Painel de preferências

Fase 6 — Publicação
  ✦ Testes PHPUnit/Pest
  ✦ README
  ✦ Packagist
```
# Módulo Permissions — Documentação Completa

**Pacote:** `jonytonet/ptah`  
**Namespace:** `Ptah\Services\Permission`, `Ptah\Livewire\Permission`  
**Livewire:** 3.x | **Laravel:** 11+

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Conceitos Fundamentais](#conceitos-fundamentais)
3. [Ativação](#ativação)
4. [Configuração](#configuração)
5. [Banco de Dados](#banco-de-dados)
6. [Models](#models)
   - [Role](#role)
   - [PtahPage](#ptahpage)
   - [PageObject](#pageobject)
   - [RolePermission](#rolepermission)
   - [UserRole](#userrole)
   - [PermissionAudit](#permissionaudit)
7. [PermissionService](#permissionservice)
8. [RoleService](#roleservice)
9. [Helpers Globais](#helpers-globais)
10. [Facade Permission](#facade-permission)
11. [Diretivas Blade](#diretivas-blade)
12. [Middleware ptah.can](#middleware-ptahcan)
13. [Telas de Administração](#telas-de-administração)
    - [DepartmentList](#departmentlist)
    - [RoleList](#rolelist)
    - [PageList](#pagelist)
    - [UserPermissionList](#userpermissionlist)
    - [AuditList](#auditlist)
14. [Rotas](#rotas)
15. [Seeders](#seeders)
16. [Fluxo de Verificação](#fluxo-de-verificação)
17. [Auditoria](#auditoria)
18. [Cache](#cache)
19. [Integração com Auth e BaseCrud](#integração-com-auth-e-basecrud)
20. [Exemplos Práticos](#exemplos-práticos)
21. [Referência de Configuração](#referência-de-configuração)

---

## Visão Geral

O módulo **permissions** implementa um sistema de controle de acesso hierárquico e granular baseado nos conceitos **RBAC** (Role-Based Access Control) com suporte opcional a multi-empresa.

**O que o módulo oferece:**

| Recurso | Descrição |
|---|---|
| Roles/Perfis | Agrupamentos de permissões, podendo ter departamento associado |
| Role MASTER | Bypass completo de todas as verificações de permissão |
| Páginas e Objetos | Cadastro de recursos do sistema (botões, campos, seções, APIs…) |
| Permissões CRUD | Por objeto: `can_create`, `can_read`, `can_update`, `can_delete` |
| Permissões extras | Campo JSON `extra` para ações além do CRUD padrão |
| Atribuição por empresa | Usuário pode ter roles diferentes em cada empresa |
| Roles globais | Atribuição sem empresa específica — válida em todas as empresas |
| Cache | Permissões cacheadas por usuário+empresa+objeto+ação |
| Auditoria | Log de acessos concedidos e negados com contexto JSON |
| Middleware | `ptah.can:objeto,acao` para proteção de rotas |
| Blade directives | `@ptahCan('chave', 'acao')` e `@ptahMaster` |
| Helpers globais | `ptah_can()`, `ptah_is_master()`, `ptah_permissions()` |
| 5 telas admin | Departamentos, Roles, Páginas/Objetos, Usuários, Auditoria |

---

## Conceitos Fundamentais

### Hierarquia de acesso

```
Sistema
└── Empresa (opcional — quando multi_company = true)
    └── Role (perfil de acesso)
        └── RolePermission (objeto + ações can_*)
            └── UserRole (usuário ↔ role ↔ empresa)
```

### Página vs Objeto de Página

- **Página (`PtahPage`):** representa uma tela ou contexto do sistema (ex: `admin.users`, `financeiro.contas-pagar`)
- **Objeto de Página (`PageObject`):** recurso controlável dentro de uma página (ex: botão "Criar usuário", campo "Salário", aba "Histórico financeiro")
- **Permissão:** uma `Role` tem permissão para determinadas ações em um `PageObject` específico

### Chave de objeto (`obj_key`)

A `obj_key` é o identificador usado em verificações. Recomenda-se o padrão `pagina.acao` ou `modulo.recurso`:

```
users.store        → botão "Novo Usuário"
users.salary_field → campo Salário
financeiro.relatorio_margem → relatório de margem
api.produtos.exportar → endpoint de exportação
```

### Role MASTER

O sistema permite exatamente **1 role** com `is_master = true`. Usuários com esta role têm acesso irrestrito a todos os recursos sem passar pela verificação de permissões. Ideal para administradores do sistema.

---

## Ativação

### Via comando (recomendado)

```bash
php artisan ptah:module permissions
```

O comando:
1. Ativa o módulo `company` se ainda não estiver ativo (dependência obrigatória)
2. Publica as 6 migrations de permissões
3. Executa `php artisan migrate`
4. Executa `DefaultAdminSeeder` (cria empresa padrão → departamento → role MASTER → usuário admin → vínculo)
5. Exibe as credenciais do admin criado
6. Define `PTAH_MODULE_PERMISSIONS=true` no `.env`

**Saída no terminal:**

```
  ╔══════════════════════════════════════════╗
  ║  Admin criado com sucesso!               ║
  ║  E-mail  : admin@admin.com               ║
  ║  Senha   : admin@123                     ║
  ║  ⚠ Troque a senha no primeiro acesso!   ║
  ╚══════════════════════════════════════════╝
```

### Via `.env`

```dotenv
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true
```

---

## Configuração

Em `config/ptah.php`, seção `permissions`:

```php
'permissions' => [
    // Cache de permissões ligado/desligado
    'cache'     => env('PTAH_PERMISSION_CACHE', true),
    'cache_ttl' => env('PTAH_PERMISSION_CACHE_TTL', 300),   // segundos

    // Model de usuários da aplicação host
    // Não precisa estender nenhuma classe do Ptah
    'user_model'       => env('PTAH_USER_MODEL', \App\Models\User::class),

    // Campo PK do usuário (padrão: 'id')
    'user_id_field'    => 'id',

    // Chave de sessão para identificar usuário (quando não usa Auth::)
    'user_session_key' => 'ptah_user_id',

    // Chave de sessão para empresa corrente
    'company_session_key' => 'ptah_company_id',

    // Auditoria: gravar logs de verificação
    'audit'         => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'  => env('PTAH_PERMISSION_AUDIT_DENIED', true),   // sempre audita negados
    'audit_master'  => env('PTAH_PERMISSION_AUDIT_MASTER', false),  // audita bypass MASTER

    // Multi-empresa: usa company_session_key para filtrar permissões
    'multi_company' => env('PTAH_MULTI_COMPANY', false),

    // Permitir acesso a guests (não-autenticados)
    'allow_guest'   => false,

    // Credenciais do admin para DefaultAdminSeeder
    'admin_name'     => env('PTAH_ADMIN_NAME', 'Administrador'),
    'admin_email'    => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    'admin_password' => env('PTAH_ADMIN_PASSWORD', 'admin@123'),
],
```

---

## Banco de Dados

### ptah_roles

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `department_id` | bigint FK | ✓ | Departamento associado |
| `name` | string | — | Nome do perfil |
| `description` | text | ✓ | Descrição livre |
| `color` | string | ✓ | Cor hex para identificação visual |
| `is_master` | boolean | — | Bypass total de permissões (máx. 1) |
| `is_active` | boolean | — | Ativo/inativo |
| `deleted_at` | timestamp | ✓ | SoftDelete |

### ptah_pages

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `slug` | string unique | — | Identificador da página (ex: `admin.users`) |
| `name` | string | — | Nome legível |
| `description` | text | ✓ | — |
| `route` | string | ✓ | Nome da rota Laravel |
| `icon` | string | ✓ | Ícone (emoji ou classe) |
| `is_active` | boolean | — | — |
| `sort_order` | integer | — | Ordem de exibição |

> `ptah_pages` **não** tem `deleted_at` — páginas são excluídas permanentemente.

### ptah_page_objects

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `page_id` | bigint FK | — | Página pai |
| `section` | string | — | Seção dentro da página (ex: `toolbar`, `form`, `tabs`) |
| `obj_key` | string | — | Chave única de verificação (ex: `users.store`) |
| `obj_label` | string | — | Label legível (ex: `Criar usuário`) |
| `obj_type` | enum | — | `page` `button` `field` `link` `section` `api` `report` `tab` |
| `is_active` | boolean | — | — |
| `obj_order` | integer | — | Ordem dentro da seção |

**Índice único:** `(page_id, section, obj_key)` — impede objetos duplicados por seção.

### ptah_role_permissions

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `role_id` | bigint FK | — | Role |
| `page_object_id` | bigint FK | — | Objeto de página |
| `can_create` | boolean | — | Permissão de criação |
| `can_read` | boolean | — | Permissão de leitura |
| `can_update` | boolean | — | Permissão de edição |
| `can_delete` | boolean | — | Permissão de exclusão |
| `extra` | json | ✓ | Permissões personalizadas além do CRUD |
| `deleted_at` | timestamp | ✓ | SoftDelete |

### ptah_user_roles

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `user_id` | bigint | — | ID do usuário (sem FK — model agnóstico) |
| `role_id` | bigint FK | — | Role |
| `company_id` | bigint | ✓ | Empresa (null = role global) |
| `is_active` | boolean | — | — |
| `deleted_at` | timestamp | ✓ | SoftDelete |

**Índice único:** `(user_id, role_id, company_id)`.

> `company_id = null` significa **role global** — válido em qualquer empresa. Um usuário com `company_id = 5` em um role só tem aquele role na empresa 5.

### ptah_permission_audits

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `user_id` | bigint | ✓ | ID do usuário |
| `company_id` | bigint | ✓ | Empresa no contexto |
| `resource_key` | string | — | `obj_key` verificado |
| `action` | string | — | `create` `read` `update` `delete` |
| `result` | enum | — | `granted` ou `denied` |
| `ip_address` | string | ✓ | IP da requisição |
| `user_agent` | string | ✓ | User-Agent |
| `context` | json | ✓ | Dados extras de contexto |
| `created_at` | timestamp | — | Sem `updated_at` |

---

## Models

### Role

**Namespace:** `Ptah\Models\Role`  
**Traits:** `SoftDeletes`

```php
// Relacionamentos
$role->department;   // BelongsTo(Department)
$role->permissions;  // HasMany(RolePermission)
$role->userRoles;    // HasMany(UserRole)
```

**Métodos de instância:**

| Método | Retorno | Descrição |
|---|---|---|
| `getBadgeLabel(): string` | string | `'👑 MASTER'` se `is_master`, caso contrário o nome |
| `getDisplayColor(): string` | string | Cor hex definida ou `'#6b7280'` (cinza padrão) |

**Escopos:**

```php
Role::active()->get();   // WHERE is_active = 1
Role::master()->first(); // WHERE is_master = 1
```

---

### PtahPage

**Namespace:** `Ptah\Models\PtahPage`  
**Sem SoftDeletes**

```php
$page->pageObjects; // HasMany(PageObject)
```

**Escopos:**

```php
PtahPage::active()->get();   // WHERE is_active = 1
PtahPage::ordered()->get();  // ORDER BY sort_order ASC
```

---

### PageObject

**Namespace:** `Ptah\Models\PageObject`

```php
// Tipos disponíveis
PageObject::TYPES; // ['page','button','field','link','section','api','report','tab']

$obj->page; // BelongsTo(PtahPage)
```

**Escopos:**

```php
PageObject::active()->get();
PageObject::byKey('users.store')->first();
PageObject::byType('button')->get();
```

---

### RolePermission

**Namespace:** `Ptah\Models\RolePermission`  
**Traits:** `SoftDeletes`

**Métodos:**

| Método | Retorno | Descrição |
|---|---|---|
| `allows(string $action): bool` | bool | Verifica se `can_{action}` é `true`. Ação aceita: `create`, `read`, `update`, `delete` |
| `toCrudArray(): array` | array | Retorna `['can_create'=>bool, 'can_read'=>bool, 'can_update'=>bool, 'can_delete'=>bool]` |

---

### UserRole

**Namespace:** `Ptah\Models\UserRole`  
**Traits:** `SoftDeletes`

```php
$userRole->role;    // BelongsTo(Role)
$userRole->company; // BelongsTo(Company)
```

**Escopo `forCompany`:**

```php
// roles globais (company_id IS NULL)
UserRole::forCompany(null)->get();

// roles da empresa 5 + roles globais
UserRole::forCompany(5)->get();
// WHERE (company_id = 5 OR company_id IS NULL)
```

---

### PermissionAudit

**Namespace:** `Ptah\Models\PermissionAudit`  
**Sem updated_at** (`UPDATED_AT = null`)

**Escopos:**

```php
PermissionAudit::granted()->get();
PermissionAudit::denied()->get();
PermissionAudit::forUser($userId)->get();
PermissionAudit::forResource('users.store')->get();
PermissionAudit::recent(50)->get();  // últimos 50 registros
```

---

## PermissionService

**Namespace:** `Ptah\Services\Permission\PermissionService`  
**Contrato:** `Ptah\Contracts\PermissionServiceContract`  
**Binding:** singleton

### Interface

```php
interface PermissionServiceContract
{
    public function check(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool;
    public function isMaster(mixed $user = null): bool;
    public function getPermissions(mixed $user = null, ?int $companyId = null): array;
    public function syncRole(int $userId, int $roleId, ?int $companyId = null): UserRole;
    public function detachRole(int $userRoleId): void;
    public function clearCache(mixed $user = null, ?int $companyId = null): void;
}
```

### Métodos

#### `check(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool`

Verifica se o usuário tem permissão para executar `$action` no objeto `$objectKey`.

**Fluxo interno:**

```
1. Resolver userId (Auth::id() / sessão / $user passado)
2. Se allow_guest = false e userId = null → return false
3. isMaster(user)? → return true (sem auditoria, salvo audit_master = true)
4. Verificar cache: ptah_perm:{userId}:{companyId}:{objectKey}:{action}
5. Cache hit? → return cached value
6. DB: UserRole → RolePermission → can_{action}
   (OR entre todos os roles: basta 1 role ter a permissão)
7. Gravar no cache
8. Gravar auditoria (se configurado)
9. return $result
```

```php
// Verificação simples (usa Auth::id() e CompanyService::getCurrentCompanyId())
$ok = app(PermissionServiceContract::class)->check('users.store', 'create');

// Com usuário e empresa explícitos
$ok = app(PermissionServiceContract::class)->check('financeiro.exportar', 'read', $user, 3);
```

**Parâmetro `$user` aceita:**

| Tipo | Comportamento |
|---|---|
| `null` | Usa `Auth::id()` → fallback para `Session::get(user_session_key)` |
| `int` / `string` | Trata como ID do usuário |
| `Authenticatable` / Model | Usa `$user->{user_id_field}` |

---

#### `isMaster(mixed $user = null): bool`

Verifica se o usuário tem um role com `is_master = true`. Cacheado em `ptah_is_master:{userId}`.

```php
if (ptah_is_master()) {
    // acesso total
}
```

---

#### `getPermissions(mixed $user = null, ?int $companyId = null): array`

Retorna mapa completo de permissões do usuário. Útil para carregar permissões no frontend.

```php
$perms = ptah_permissions();
// [
//   'users.store'      => ['can_create'=>true, 'can_read'=>true, 'can_update'=>false, 'can_delete'=>false],
//   'users.salary_field' => ['can_create'=>false, 'can_read'=>false, ...],
//   ...
// ]
```

Para usuários MASTER, todos os objetos retornam todas as flags como `true`.

---

#### `syncRole(int $userId, int $roleId, ?int $companyId = null): UserRole`

Atribui um role ao usuário. Usa `firstOrCreate` com `withTrashed()` — seguro para re-ativar vínculos deletados.

```php
$userRole = app(PermissionServiceContract::class)->syncRole(
    userId: $user->id,
    roleId: $role->id,
    companyId: 5  // null para role global
);
```

---

#### `detachRole(int $userRoleId): void`

Remove um vínculo usuário-role (soft-delete). Invalida o cache do usuário automaticamente.

---

#### `clearCache(mixed $user = null, ?int $companyId = null): void`

Invalida o cache de permissões. Tenta usar `Cache::tags(['ptah_permissions'])` quando disponível (Redis/Memcached); faz fallback para remoção individual de chaves em drivers sem suporte a tags.

---

## RoleService

**Namespace:** `Ptah\Services\Permission\RoleService`  
**Binding:** singleton

### Métodos

| Método | Descrição |
|---|---|
| `create(array $data): Role` | Cria role. Valida que não existe outro MASTER se `is_master = true` |
| `update(Role $role, array $data): Role` | Atualiza role. Bloqueia mudança de `is_master` se já existe outro |
| `delete(Role $role): void` | Soft-delete. Lança `ValidationException` para role MASTER |
| `bindPageObject(Role $role, int $pageObjectId, array $perms): RolePermission` | Upsert de permissão para um objeto (usa `withTrashed`) |
| `syncPageBindings(Role $role, array $bindings): void` | Substitui todas as permissões da role. Remove objetos não presentes no array; cria/atualiza os presentes |
| `getWithPermissions(Role $role): Role` | Carrega eager: `permissions.pageObject.page` + `department` |

### `syncPageBindings` — formato do array

```php
$bindings = [
    [
        'page_object_id' => 12,
        'can_create'     => true,
        'can_read'       => true,
        'can_update'     => false,
        'can_delete'     => false,
    ],
    // ...
];

app(RoleService::class)->syncPageBindings($role, $bindings);
```

Objetos com todas as flags `false` são ignorados (não criam permissão).

---

## Helpers Globais

Definidos em `src/helpers.php` — disponíveis globalmente via `autoload.files` no `composer.json`.

### `ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool`

Verifica uma permissão. Atalho para `PermissionService::check()`.

```php
if (ptah_can('users.store', 'create')) {
    // renderiza botão "Novo usuário"
}
```

### `ptah_is_master(mixed $user = null): bool`

Verifica se o usuário é MASTER.

```php
if (ptah_is_master()) {
    // mostra painel de administração total
}
```

### `ptah_permissions(mixed $user = null, ?int $companyId = null): array`

Retorna o mapa completo de permissões do usuário.

```php
$perms = ptah_permissions();
// Passar para o frontend via JavaScript:
// window.userPermissions = @json(ptah_permissions())
```

---

## Facade Permission

**Namespace:** `Ptah\Facades\Permission`

```php
use Ptah\Facades\Permission;

Permission::check('users.store', 'create');
Permission::isMaster();
Permission::getPermissions();
Permission::syncRole($userId, $roleId, $companyId);
Permission::detachRole($userRoleId);
Permission::clearCache();
```

---

## Diretivas Blade

Registradas automaticamente no `PtahServiceProvider`:

### `@ptahCan / @endPtahCan`

```blade
@ptahCan('users.store', 'create')
    <x-forge-button wire:click="create" color="primary">Novo usuário</x-forge-button>
@endPtahCan
```

Com usuário e empresa explícitos:

```blade
@ptahCan('financeiro.exportar', 'read', $user, $companyId)
    <a href="/exportar">Exportar</a>
@endPtahCan
```

### `@ptahMaster / @endPtahMaster`

```blade
@ptahMaster
    <div class="admin-only-panel">...</div>
@endPtahMaster
```

---

## Middleware ptah.can

**Namespace:** `Ptah\Http\Middleware\PtahPermission`  
**Alias:** `ptah.can` (registrado automaticamente)

### Uso em rotas

```php
// routes/web.php
Route::get('/users/create', UserController::class . '@create')
    ->middleware('ptah.can:users.store,create');

Route::delete('/users/{id}', UserController::class . '@destroy')
    ->middleware('ptah.can:users.store,delete');

// Com empresa explícita (opcional — normalmente resolve da sessão)
Route::get('/financeiro/exportar', FinanceiroController::class . '@exportar')
    ->middleware('ptah.can:financeiro.exportar,read');
```

### Comportamento

| Contexto | Resposta ao negar |
|---|---|
| Request aceita JSON (`Accept: application/json`) | `HTTP 403` com `{"message":"Acesso negado.","object":"...","action":"..."}` |
| Request web | `abort(403)` — página padrão de erro do Laravel |

### Sintaxe dos parâmetros

```
ptah.can:{objectKey},{action}[,{companyId}]
```

| Parâmetro | Obrigatório | Descrição |
|---|---|---|
| `objectKey` | ✓ | Chave do objeto (ex: `users.store`) |
| `action` | ✓ | `create`, `read`, `update` ou `delete` |
| `companyId` | — | Se omitido, resolve da sessão |

---

## Telas de Administração

### DepartmentList

**URL:** `/ptah-departments`  
**Componente:** `Ptah\Livewire\Permission\DepartmentList`

CRUD simples de departamentos. Exibe contador de roles vinculados.

| Coluna | Descrição |
|---|---|
| Nome | Nome do departamento |
| Descrição | Texto livre |
| Roles | Quantidade de roles vinculados |
| Status | Ativo/Inativo |

---

### RoleList

**URL:** `/ptah-roles`  
**Componente:** `Ptah\Livewire\Permission\RoleList`

CRUD de roles + **modal de permissões por objeto**.

**Modal de Permissões (Bind):**

Exibe todos os `PageObject` agrupados por página/seção. Para cada objeto, checkboxes independentes para `can_read`, `can_create`, `can_update`, `can_delete`. Ao salvar, chama `RoleService::syncPageBindings()`.

```
┌─ Página: admin.users — Seção: toolbar ────────────────────────────┐
│ Novo usuário (users.store) [button]   Ler ✓ | Criar ✓ | Edit ✗ | Excluir ✗ │
│ Exportar (users.export)    [button]   Ler ✓ | Criar ✗ | Edit ✗ | Excluir ✗ │
├─ Página: admin.users — Seção: form ───────────────────────────────┤
│ Campo Salário (users.salary_field) [field]   Ler ✓ | Criar ✓ | Edit ✗ | Excluir ✗ │
└────────────────────────────────────────────────────────────────────┘
```

**Proteções:**
- Role MASTER não pode ser excluído
- Botão Excluir não aparece para roles MASTER

---

### PageList

**URL:** `/ptah-pages`  
**Componente:** `Ptah\Livewire\Permission\PageList`

Interface em duas colunas:
- **Esquerda:** lista de páginas (`PtahPage`) com contador de objetos
- **Direita:** objetos da página selecionada

**Fluxo de cadastro recomendado:**

```
1. Identifique as telas do seu sistema
2. Cadastre cada tela com um slug único (ex: admin.usuarios, financeiro.contas)
3. Para cada tela, cadastre os objetos controláveisos:
   - Botões de ação (criar, exportar, aprovar)
   - Campos sensíveis (salário, margem, desconto máximo)
   - Abas (histórico financeiro, dados pessoais)
   - Relatórios (DRE, balanço)
   - Endpoints de API (/api/exportar)
4. Vá para /ptah-roles e configure as permissões de cada role
```

---

### UserPermissionList

**URL:** `/ptah-users-acl`  
**Componente:** `Ptah\Livewire\Permission\UserPermissionList`

Lista todos os usuários do model configurado (`config('ptah.permissions.user_model')`). Para cada usuário, exibe os roles atribuídos como badges.

**Modal de gestão de acesso:**
- Lista roles atribuídos com botão "Remover" (exceto roles MASTER)
- Formulário para adicionar novo role (select de role + select de empresa)
- Empresa "Global" = `company_id = null` (role válido em todas as empresas)

**Filtro por role:** select de todos os roles para filtrar a lista de usuários.

---

### AuditList

**URL:** `/ptah-audit`  
**Componente:** `Ptah\Livewire\Permission\AuditList`

Read-only. Filtros disponíveis:

| Filtro | Opções |
|---|---|
| Busca textual | resource_key, ip_address, user_id |
| Resultado | Todos / Concedido / Negado |
| Ação | Todas / Criar / Ler / Editar / Excluir |
| Data inicial | Date picker |
| Data final | Date picker |

---

## Rotas

Registradas automaticamente quando `ptah.modules.permissions = true`:

| Método | URI | Nome | Proteção |
|---|---|---|---|
| `GET` | `/ptah-departments` | `ptah.acl.departments` | `web`, `auth` |
| `GET` | `/ptah-roles` | `ptah.acl.roles` | `web`, `auth` |
| `GET` | `/ptah-pages` | `ptah.acl.pages` | `web`, `auth` |
| `GET` | `/ptah-users-acl` | `ptah.acl.users` | `web`, `auth` |
| `GET` | `/ptah-audit` | `ptah.acl.audit` | `web`, `auth` |

---

## Seeders

### DefaultCompanySeeder

Cria a empresa padrão (idempotente). Ver [Company.md](Company.md) para detalhes.

### DefaultAdminSeeder

**Namespace:** `Ptah\Seeders\DefaultAdminSeeder`

Cria toda a cadeia de forma idempotente:

```
1. Empresa padrão (via DefaultCompanySeeder)
2. Departamento "Administração" (firstOrCreate)
3. Role MASTER (firstOrCreate via department + name)
4. User admin (firstOrCreate via email, lê de config('ptah.permissions.admin_*'))
5. UserRole: admin → MASTER → empresa padrão
```

```php
// Execução manual
php artisan db:seed --class="Ptah\Seeders\DefaultAdminSeeder"
```

**Configuração das credenciais** (`.env`):

```dotenv
PTAH_ADMIN_NAME="Administrador"
PTAH_ADMIN_EMAIL="admin@meuapp.com"
PTAH_ADMIN_PASSWORD="senha-segura-aqui"
```

---

## Fluxo de Verificação

```
ptah_can('users.store', 'create')
  │
  ├─ resolve userId
  │     Auth::id() → Session::get('ptah_user_id') → null
  │
  ├─ userId null?
  │     allow_guest = false → return false
  │
  ├─ isMaster(userId)?
  │     Cache: ptah_is_master:{userId}
  │     DB: UserRole join Role WHERE is_master=1
  │     true → return true  (+ auditoria se audit_master)
  │
  ├─ Cache hit?
  │     ptah_perm:{userId}:{companyId}:users.store:create
  │     true/false → return cached
  │
  ├─ DB query
  │     UserRole (forCompany)
  │       → RolePermission (page_object.obj_key = 'users.store')
  │           → OR entre todos os roles: can_create
  │
  ├─ Cache write (TTL: cache_ttl segundos)
  │
  ├─ Auditoria (se audit=true ou audit_denied=true e result=false)
  │
  └─ return bool
```

---

## Auditoria

Ativada via `.env`:

```dotenv
PTAH_PERMISSION_AUDIT=true           # audita TODOS (concedidos + negados)
PTAH_PERMISSION_AUDIT_DENIED=true    # audita apenas negados (padrão: true)
PTAH_PERMISSION_AUDIT_MASTER=false   # audita bypass MASTER (padrão: false)
```

**Recomendações:**
- Em produção com alto volume, use `PTAH_PERMISSION_AUDIT=false` + `PTAH_PERMISSION_AUDIT_DENIED=true` — só loga o que foi negado (tentativas não autorizadas)
- Para compliance total, use `PTAH_PERMISSION_AUDIT=true`
- Para debug, adicione contexto customizado via `PermissionAudit::create([..., 'context' => ['request_id' => ...]])`

A tela de auditoria (`/ptah-audit`) exibe os registros com filtros e paginação.

---

## Cache

### Chaves de cache

| Chave | Conteúdo | TTL |
|---|---|---|
| `ptah_perm:{userId}:{companyId}:{objectKey}:{action}` | `bool` | `cache_ttl` (padrão 300s) |
| `ptah_is_master:{userId}` | `bool` | `cache_ttl` |
| `ptah_company_default` | `Company` | `cache_ttl` |
| `ptah_user_companies:{userId}` | `Collection` | `cache_ttl` |

### Invalidação

O cache é invalidado automaticamente quando:
- `detachRole()` é chamado (invalida o usuário)
- `syncRole()` é chamado (invalida o usuário)  
- `syncPageBindings()` é chamado (invalida todos os usuários com aquela role)

**Invalidação manual:**

```php
// Invalidar cache de um usuário
Permission::clearCache($user);

// Invalidar cache de um usuário em uma empresa específica
Permission::clearCache($user, $companyId);

// Invalidar tudo (tags — requer driver Redis/Memcached)
Cache::tags(['ptah_permissions'])->flush();
```

### Tags de cache

Quando o driver de cache suporta tags (Redis, Memcached), todas as chaves são gravadas com a tag `ptah_permissions`. Isso permite `flush()` atômico de todo o módulo. Em drivers sem suporte a tags (file, database), o `clearCache()` remove as chaves individualmente.

---

## Integração com Auth e BaseCrud

### Integração com o módulo Auth

Quando ambos `auth` e `permissions` estão ativos, o `PermissionService` resolve o usuário via `Auth::id()` automaticamente — sem configuração adicional.

### Integração com o BaseCrud

Para adicionar controle de permissões em uma tela BaseCrud, use o parâmetro `readOnly` combinado com verificação na view:

```blade
@livewire('ptah::base-crud', [
    'model'    => 'Product',
    'canCreate' => ptah_can('products.store', 'create'),
    'canEdit'   => ptah_can('products.store', 'update'),
    'canDelete' => ptah_can('products.store', 'delete'),
    'canExport' => ptah_can('products.export', 'read'),
])
```

Ou controle total via `readOnly` para telas de apenas leitura:

```blade
@livewire('ptah::base-crud', [
    'model'    => 'Product',
    'readOnly' => !ptah_can('products.store', 'update'),
])
```

---

## Exemplos Práticos

### Exemplo 1 — Botão condicional na view

```blade
{{-- resources/views/users/index.blade.php --}}
@ptahCan('users.store', 'create')
    <x-forge-button wire:click="create">Novo Usuário</x-forge-button>
@endPtahCan

@ptahCan('users.salary_field', 'read')
    <td>{{ $user->salary }}</td>
@else
    <td>***</td>
@endPtahCan
```

### Exemplo 2 — Rota protegida

```php
// routes/web.php
Route::get('/admin/usuarios', UserController::class . '@index')
    ->middleware(['auth', 'ptah.can:users.index,read'])
    ->name('admin.users.index');

Route::post('/admin/usuarios', UserController::class . '@store')
    ->middleware(['auth', 'ptah.can:users.store,create'])
    ->name('admin.users.store');
```

### Exemplo 3 — Verificação no Service

```php
// app/Services/UserService.php
use Ptah\Contracts\PermissionServiceContract;

class UserService
{
    public function __construct(
        private PermissionServiceContract $permissions,
    ) {}

    public function updateSalary(int $userId, float $salary): void
    {
        if (!$this->permissions->check('users.salary_field', 'update')) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        User::findOrFail($userId)->update(['salary' => $salary]);
    }
}
```

### Exemplo 4 — Passar para o frontend via JavaScript

```blade
{{-- No layout --}}
<script>
    window.PtahUser = {
        isMaster: @json(ptah_is_master()),
        permissions: @json(ptah_permissions()),
    };
</script>
```

```js
// No JavaScript
if (window.PtahUser.permissions['users.store']?.can_create) {
    showCreateButton();
}
```

### Exemplo 5 — Seletor de empresa na navbar

```php
// app/Livewire/CompanySwitcher.php
class CompanySwitcher extends Component
{
    public function switch(int $companyId): void
    {
        app(\Ptah\Contracts\CompanyServiceContract::class)->setCurrentCompany($companyId);
        app(\Ptah\Contracts\PermissionServiceContract::class)->clearCache();
        $this->redirect(request()->header('Referer') ?? '/dashboard');
    }
}
```

---

## Referência de Configuração

```php
// config/ptah.php

'modules' => [
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
],

'permissions' => [
    'cache'              => env('PTAH_PERMISSION_CACHE', true),
    'cache_ttl'          => env('PTAH_PERMISSION_CACHE_TTL', 300),
    'user_model'         => env('PTAH_USER_MODEL', \App\Models\User::class),
    'user_id_field'      => 'id',
    'user_session_key'   => 'ptah_user_id',
    'company_session_key'=> 'ptah_company_id',
    'audit'              => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'       => env('PTAH_PERMISSION_AUDIT_DENIED', true),
    'audit_master'       => env('PTAH_PERMISSION_AUDIT_MASTER', false),
    'multi_company'      => env('PTAH_MULTI_COMPANY', false),
    'allow_guest'        => false,
    'admin_name'         => env('PTAH_ADMIN_NAME', 'Administrador'),
    'admin_email'        => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    'admin_password'     => env('PTAH_ADMIN_PASSWORD', 'admin@123'),
],
```

### Variáveis de ambiente

| Variável | Padrão | Descrição |
|---|---|---|
| `PTAH_MODULE_COMPANY` | `false` | Ativa o módulo company |
| `PTAH_MODULE_PERMISSIONS` | `false` | Ativa o módulo permissions |
| `PTAH_USER_MODEL` | `App\Models\User` | FQCN do model de usuários |
| `PTAH_PERMISSION_CACHE` | `true` | Habilita cache de permissões |
| `PTAH_PERMISSION_CACHE_TTL` | `300` | TTL do cache em segundos |
| `PTAH_PERMISSION_AUDIT` | `false` | Audita todos os acessos |
| `PTAH_PERMISSION_AUDIT_DENIED` | `true` | Audita somente acessos negados |
| `PTAH_PERMISSION_AUDIT_MASTER` | `false` | Audita bypass MASTER |
| `PTAH_MULTI_COMPANY` | `false` | Permissões filtradas por empresa |
| `PTAH_ADMIN_EMAIL` | `admin@admin.com` | E-mail do admin padrão |
| `PTAH_ADMIN_PASSWORD` | `admin@123` | Senha do admin padrão |

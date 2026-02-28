# Continuação — Módulos `company` + `permissions`

## Contexto

Estamos desenvolvendo o pacote Laravel **`jonytonet/ptah`** (path local: `c:\Users\jony.tonet\Desktop\Dev\composer_project\ptah`).  
App de teste: `c:\Users\jony.tonet\Desktop\Dev\composer_project\ptah-app`  
Deploy: `git commit` no ptah → `composer update jonytonet/ptah --no-scripts` no ptah-app → `php artisan vendor:publish --tag=ptah-views --force`

Os módulos `auth` e `menu` já foram implementados e commitados. O próximo passo é implementar os módulos **`company`** e **`permissions`**.

---

## O que já existe no pacote

- `config/ptah.php` — seções: `paths`, `preferences`, `api`, `forge`, `scaffold`, `modules` (auth/menu), `auth`, `menu`
- `src/PtahServiceProvider.php` — registro condicional por módulo, singletons, Livewire, rotas
- `src/Commands/Modules/ModuleCommand.php` — `php artisan ptah:module {auth|menu|--list}`
- Módulo auth: Login, ForgotPassword, ResetPassword, TwoFactorChallenge, Profile (5 abas), Dashboard
- Módulo menu: driver `config`/`database`, MenuService, modelo Menu com árvore + cache
- BaseCrud: tela de listagem Livewire dinâmica configurada via `CrudConfig` no banco
- `ptah_can()` helper e Facade `Permission` — **ainda NÃO implementados** (próxima etapa)

---

## Plano completo a implementar

### Módulo `company`

**1. Migration: `ptah_companies`**
```
id, name, slug (unique), logo_path (nullable), email (nullable), phone (nullable),
tax_id (nullable, CNPJ/CPF), address (json nullable), settings (json nullable),
is_default (boolean default false), is_active (boolean default true),
created_by (unsignedBigInt nullable), updated_by (nullable), deleted_by (nullable),
timestamps, softDeletes
```

**2. Model: `Ptah\Models\Company`**
- SoftDeletes, casts (`address` → array, `settings` → array)
- Scope `default()`, scope `active()`
- Relacionamentos: `userRoles(): HasMany(UserRole)`
- Método `getLogoUrl(): string` — retorna URL do logo ou placeholder

**3. Service: `Ptah\Services\Company\CompanyService`** (singleton)
- `getDefault(): Company`
- `getById(int $id): Company`
- `getUserCompanies(int $userId): Collection`
- `clearCache(): void`

**4. Seeder: `Ptah\Seeders\DefaultCompanySeeder`**
- `Company::firstOrCreate(['is_default' => true], [name, slug, is_active])` — dados de `config('app.name')`
- Idempotente

**5. Livewire: `CompanyList`** — tela BaseCrud + `CompanyEditModal` (tabs: dados, endereço, logo, configurações)

**6. Rotas:** `/ptah-companies` → `ptah.company.index`

---

### Módulo `permissions`

#### Migrations (6 tabelas na ordem)

```
1. ptah_departments
   id, name, description (nullable), is_active (bool default true),
   created_by (nullable), updated_by (nullable), timestamps, softDeletes

2. ptah_roles
   id, name, description (nullable), department_id (FK ptah_departments nullable),
   is_master (bool default false), is_active (bool default true),
   created_by, updated_by, timestamps, softDeletes
   — index: (department_id, is_active)

3. ptah_pages
   id, name (string unique), description (nullable), route (nullable), icon (nullable),
   is_active (bool default true), timestamps
   — sem softDeletes (são registros de sistema, geralmente seedados)

4. ptah_page_objects
   id, pages_id (FK ptah_pages), section (string), obj_key (string),
   obj_label (string), obj_type (enum: page|button|field|link|section default page),
   obj_order (int default 0), is_active (bool default true), timestamps
   — unique: (pages_id, section, obj_key)
   — index: (pages_id, is_active)

5. ptah_role_permissions
   id, role_id (FK ptah_roles), page_object_id (FK ptah_page_objects),
   can_create (bool default false), can_read (bool default true),
   can_update (bool default false), can_delete (bool default false),
   created_by (nullable), updated_by (nullable), timestamps, softDeletes
   — unique: (role_id, page_object_id)

6. ptah_user_roles
   id, user_id (unsignedBigInt — sem FK pois o pacote não conhece o model do host),
   role_id (FK ptah_roles), company_id (unsignedBigInt nullable — sem FK),
   is_active (bool default true),
   created_by (nullable), timestamps, softDeletes
   — unique: (user_id, role_id, company_id)
   — index: (user_id, is_active)

7. ptah_permission_audits
   id, user_id (unsignedBigInt nullable), company_id (nullable),
   page_object_id (FK nullable), resource_key (string nullable),
   action (enum: create|read|update|delete), result (enum: granted|denied),
   ip_address (string nullable), user_agent (string nullable),
   created_at (timestamp) — sem updated_at, sem softDeletes
   — index: (user_id, created_at)
```

#### Models

| Classe | Namespace | Detalhes |
|---|---|---|
| `Department` | `Ptah\Models\Department` | SoftDeletes; `roles(): HasMany` |
| `Role` | `Ptah\Models\Role` | SoftDeletes; `department(): BelongsTo`, `permissions(): HasMany(RolePermission)`, `users(): HasMany(UserRole)`; scope `master()` |
| `PtahPage` | `Ptah\Models\PtahPage` | `pageObjects(): HasMany` |
| `PageObject` | `Ptah\Models\PageObject` | `page(): BelongsTo(PtahPage)`, `rolePermissions(): HasMany(RolePermission)`; scope `active()` |
| `RolePermission` | `Ptah\Models\RolePermission` | SoftDeletes; `role(): BelongsTo`, `pageObject(): BelongsTo`; casts booleanos |
| `UserRole` | `Ptah\Models\UserRole` | SoftDeletes; `role(): BelongsTo`, escopo `active()`; **sem FK para tabela users** |
| `PermissionAudit` | `Ptah\Models\PermissionAudit` | sem updated_at; `$timestamps = false`; `$dateFormat` só created_at |

#### Services

**`Ptah\Services\Permission\PermissionService`** (singleton):
```php
check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
// 1. isMaster($user) → true imediatamente (sem auditoria se config audit_master=false)
// 2. verifica cache "ptah_perm:{userId}:{companyId}:{objectKey}:{action}"
// 3. query: UserRole→Role→RolePermission→PageObject onde obj_key = $objectKey
// 4. cacheia resultado (TTL config('ptah.permissions.cache_ttl', 3600))
// 5. se config audit=true: grava PermissionAudit

isMaster(mixed $user): bool
// verifica UserRole ativo com role.is_master = true (cacheado)

getPermissions(mixed $user, ?int $companyId = null): array
// retorna mapa completo ['objectKey' => ['create'=>bool, 'read'=>bool, ...]]

getCompaniesForResource(mixed $user, string $objectKey, string $action): array
// retorna company_ids onde o usuário tem acesso ao recurso

syncRole(mixed $user, int $roleId, array $companyIds = []): void
// cria UserRole para cada companyId (ou sem company se vazio), limpa cache

detachRole(mixed $user, int $roleId, ?int $companyId = null): void
// soft delete de UserRole, limpa cache

clearCache(mixed $user = null, ?int $companyId = null): void
// Cache::forget ou pattern delete se Redis
```

**`Ptah\Services\Permission\RoleService`** (singleton):
```php
create(array $data): Role         // valida: só 1 role com is_master=true
update(Role $role, array $data): Role
bindPageObject(Role $role, int $pageObjectId, array $permissions): RolePermission
unbindPageObject(Role $role, int $pageObjectId): void
getWithPermissions(int $roleId): Role  // com eager load
```

#### Helper global e Facade

**`app/helpers.php` do pacote** (carregado via `autoload.files` no `composer.json`):
```php
function ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool
// $user = null → usa auth()->user() ou Session::get('SESS_config_users_id')
// $companyId = null → usa Session::get('SESS_empresa') ou auth company context
```

**`Ptah\Facades\Permission`:**
```php
Permission::can($objectKey, $action)
Permission::canAs($user, $objectKey, $action, $companyId)
Permission::isMaster($user)
```

**Diretiva Blade `@ptahCan / @endPtahCan`:**
```blade
@ptahCan('users.store', 'create')
    <button>Novo</button>
@endPtahCan

@ptahCanAny(['users.store.create', 'users.store.update'])
    ...
@endPtahCanAny
```
Registrada no `PtahServiceProvider::boot()`.

**Middleware `PtahPermission`:**
```php
// Registrado como 'ptah.can' no ServiceProvider
Route::middleware('ptah.can:users.store,create')->group(...)
```

#### Seeder: `DefaultAdminSeeder`

Executado por `ptah:module permissions`. Criação em sequência (todos com `firstOrCreate`):
1. Empresa padrão via `DefaultCompanySeeder` (se módulo company não ativo, cria inline)
2. `Department::firstOrCreate(['name' => 'Administração'])`
3. `Role::firstOrCreate(['is_master' => true], ['name' => 'MASTER', 'department_id' => ..., 'is_active' => true])`
4. `$UserModel::firstOrCreate(['email' => config('ptah.permissions.admin_email')], ['name', 'password' => Hash::make(...)])`
5. `UserRole::firstOrCreate(['user_id' => $admin->id, 'role_id' => $masterRole->id, 'company_id' => $company->id])`

#### Config (novas seções a adicionar em `config/ptah.php`)

```php
'company' => [
    'model'          => \Ptah\Models\Company::class,
    'table'          => 'ptah_companies',
    'logo_disk'      => 'public',
    'logo_path'      => 'company-logos',
    'address_fields' => ['street','number','complement','district','city','state','zip_code','country'],
],

'permissions' => [
    'cache'           => true,
    'cache_ttl'       => 3600,
    'user_model'      => env('PTAH_USER_MODEL', 'App\Models\User'),
    'user_id_field'   => 'id',
    'user_company_session_key' => 'SESS_empresa',   // chave da session para empresa atual
    'audit'           => env('PTAH_PERMISSION_AUDIT', false),
    'audit_denied'    => true,
    'audit_master'    => false,
    'multi_company'   => true,
    'admin_name'      => env('PTAH_ADMIN_NAME', 'Administrador'),
    'admin_email'     => env('PTAH_ADMIN_EMAIL', 'admin@admin.com'),
    'admin_password'  => env('PTAH_ADMIN_PASSWORD', 'admin@123'),
],
```

#### Telas de gestão (Livewire)

| Componente | Rota | Estratégia |
|---|---|---|
| `CompanyList` + `CompanyEditModal` | `/ptah-companies` | BaseCrud + modal com tabs (dados, endereço JSON, logo upload, settings JSON) |
| `DepartmentList` | `/ptah-departments` | BaseCrud puro (CRUD simples via modal do BaseCrud) |
| `RoleList` + `RoleBindModal` | `/ptah-roles` | BaseCrud + modal de bind de objetos (igual ProfileBindModal do ERP); badge 👑 MASTER; bloqueia delete/desativar roles master |
| `PageList` + `PageObjectList` | `/ptah-pages` | BaseCrud puro (dois BaseCruds na mesma página, segundo filtrado por page_id) |
| `UserPermissionList` + `UserRoleModal` | `/ptah-users-acl` | BaseCrud + modal bind user×role×empresa (similar ModalEditUser do ERP) |
| `AuditList` | `/ptah-audit` | BaseCrud read-only (canCreate=false, canEdit=false, canDelete=false) |

#### Rotas

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-companies',    CompanyList::class)->name('ptah.company.index');
    Route::get('/ptah-departments',  DepartmentList::class)->name('ptah.acl.departments');
    Route::get('/ptah-roles',        RoleList::class)->name('ptah.acl.roles');
    Route::get('/ptah-pages',        PageList::class)->name('ptah.acl.pages');
    Route::get('/ptah-users-acl',    UserPermissionList::class)->name('ptah.acl.users');
    Route::get('/ptah-audit',        AuditList::class)->name('ptah.acl.audit');
});
```

Carregadas condicionalmente:
- Company routes: `config('ptah.modules.company')`
- Permission routes: `config('ptah.modules.permissions')`

#### `ptah:module` — novos módulos a adicionar

```php
protected array $modules = [
    'auth'         => 'PTAH_MODULE_AUTH',
    'menu'         => 'PTAH_MODULE_MENU',
    'company'      => 'PTAH_MODULE_COMPANY',      // NOVO
    'permissions'  => 'PTAH_MODULE_PERMISSIONS',  // NOVO
];
```

**`ptah:module company`** faz:
1. Publica migration `ptah_companies`
2. Executa migrate
3. Executa `DefaultCompanySeeder`
4. Define `PTAH_MODULE_COMPANY=true` no `.env`

**`ptah:module permissions`** faz:
1. Chama `ptah:module company` se não ativo
2. Publica as 7 migrations de permissões
3. Executa migrate
4. Executa `DefaultAdminSeeder`
5. Define `PTAH_MODULE_PERMISSIONS=true` no `.env`
6. Exibe box com credenciais do admin

#### `composer.json` — adicionar ao autoload

```json
"autoload": {
    "psr-4": { "Ptah\\": "src/" },
    "files": ["src/helpers.php"]
}
```

#### Navbar

Adicionar ícone 🔐 (link para `/ptah-users-acl`) ao lado do ícone ⚙️ existente, quando `config('ptah.modules.permissions')` for `true`.

---

## Arquivos a criar (ordem de implementação)

```
config/ptah.php                                    → adicionar seções company + permissions
composer.json                                      → adicionar autoload files
src/helpers.php                                    → ptah_can() helper global
src/Facades/Permission.php                         → facade
src/Migrations/2024_01_04_000000_create_ptah_companies_table.php
src/Migrations/2024_01_04_000001_create_ptah_departments_table.php
src/Migrations/2024_01_04_000002_create_ptah_roles_table.php
src/Migrations/2024_01_04_000003_create_ptah_pages_table.php
src/Migrations/2024_01_04_000004_create_ptah_page_objects_table.php
src/Migrations/2024_01_04_000005_create_ptah_role_permissions_table.php
src/Migrations/2024_01_04_000006_create_ptah_user_roles_table.php
src/Migrations/2024_01_04_000007_create_ptah_permission_audits_table.php
src/Models/Company.php
src/Models/Department.php
src/Models/Role.php
src/Models/PtahPage.php
src/Models/PageObject.php
src/Models/RolePermission.php
src/Models/UserRole.php
src/Models/PermissionAudit.php
src/Services/Company/CompanyService.php
src/Services/Permission/PermissionService.php
src/Services/Permission/RoleService.php
src/Seeders/DefaultCompanySeeder.php
src/Seeders/DefaultAdminSeeder.php
src/Livewire/Company/CompanyList.php
src/Livewire/Company/CompanyEditModal.php
src/Livewire/Permission/DepartmentList.php
src/Livewire/Permission/RoleList.php
src/Livewire/Permission/RoleBindModal.php
src/Livewire/Permission/PageList.php
src/Livewire/Permission/UserPermissionList.php
src/Livewire/Permission/UserRoleModal.php
src/Livewire/Permission/AuditList.php
resources/views/livewire/company/company-list.blade.php
resources/views/livewire/company/company-edit-modal.blade.php
resources/views/livewire/permission/department-list.blade.php
resources/views/livewire/permission/role-list.blade.php
resources/views/livewire/permission/role-bind-modal.blade.php
resources/views/livewire/permission/page-list.blade.php
resources/views/livewire/permission/user-permission-list.blade.php
resources/views/livewire/permission/user-role-modal.blade.php
resources/views/livewire/permission/audit-list.blade.php
routes/ptah-company.php
routes/ptah-permissions.php
src/Commands/Modules/ModuleCommand.php             → adicionar 'company' e 'permissions'
src/PtahServiceProvider.php                        → registrar tudo de forma condicional
```

---

## Decisões já tomadas

- Prefixo `ptah_` em todas as tabelas novas (evita colisão com tabelas do app host)
- `user_id` em `ptah_user_roles` e `ptah_permission_audits` é `unsignedBigInt` **sem FK** — o pacote não conhece o model `User` do app host
- `company_id` tem o mesmo tratamento (sem FK, referencia tabela do host ou `ptah_companies`)
- Só 1 role pode ter `is_master = true` — validação no `RoleService::create()` e `RoleService::update()`
- Usuário MASTER: `PermissionService::check()` retorna `true` sem nenhuma query adicional
- Auditoria de usuário MASTER é desligada por padrão (`audit_master = false`)
- Seeder é idempotente — pode rodar múltiplas vezes sem duplicar dados
- Senha do admin é hasheada com `Hash::make()` no seeder
- `DefaultAdminSeeder` ativa `ptah:module company` automaticamente se necessário

---

## Próximo passo imediato

Começar pela implementação na seguinte ordem:
1. `config/ptah.php` — adicionar seções `company` e `permissions`
2. `composer.json` — adicionar `autoload.files`
3. `src/helpers.php` — helper `ptah_can()`
4. `src/Facades/Permission.php`
5. Todas as migrations (8 arquivos)
6. Todos os models (8 arquivos)
7. Services (CompanyService, PermissionService, RoleService)
8. Seeders (DefaultCompanySeeder, DefaultAdminSeeder)
9. Livewire components + views
10. Rotas + atualizar PtahServiceProvider + ModuleCommand + Navbar

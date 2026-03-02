# Módulo Company — Documentação Completa

**Pacote:** `jonytonet/ptah`  
**Namespace:** `Ptah\Services\Company`, `Ptah\Livewire\Company`  
**Livewire:** 3.x | **Laravel:** 11+

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Ativação](#ativação)
3. [Configuração](#configuração)
4. [Banco de Dados](#banco-de-dados)
   - [ptah_companies](#ptah_companies)
   - [ptah_departments](#ptah_departments)
5. [Models](#models)
   - [Company](#company)
   - [Department](#department)
6. [CompanyService](#companyservice)
7. [Componente Livewire — CompanyList](#componente-livewire--companylist)
8. [Componente Livewire — CompanySwitcher](#componente-livewire--companyswitcher)
9. [Rota](#rota)
10. [Seeder](#seeder)
    - [DefaultCompanySeeder](#defaultcompanyseeder)
    - [PtahDemoSeeder](#ptahdemoaseeder)
11. [Multi-empresa vs Tenant único](#multi-empresa-vs-tenant-único)
12. [Integração com Permissions](#integração-com-permissions)
13. [Customizando Views](#customizando-views)

---

## Visão Geral

O módulo **company** oferece gerenciamento completo de empresas e departamentos para o sistema. É a fundação para cenários multi-empresa (holding, franquias, SaaS) e também funciona em instalações de empresa única, onde serve apenas como contexto organizacional dos departamentos.

| Recurso | Descrição |
|---|---|
| Empresas | CRUD completo com logo, dados fiscais, endereço e configurações arbitrárias |
| Departamentos | Agrupamento hierárquico para organizar roles/perfis de acesso |
| Contexto de sessão | Empresa ativa do usuário salva em sessão, com fallback para empresa padrão |
| Cache | Empresa padrão e empresas do usuário cacheadas automaticamente |
| Proteção | Bloqueio de exclusão da empresa marcada como padrão |

**Princípio:** módulo independente. Pode ser ativado sem o módulo `permissions`. O módulo `permissions`, porém, requer o `company` como dependência.

---

## Ativação

### Via comando (recomendado)

```bash
php artisan ptah:module company
```

O comando:
1. Publica as migrations `ptah_companies` e `ptah_departments`
2. Executa `php artisan migrate`
3. Cria a empresa padrão via `DefaultCompanySeeder`
4. Define `PTAH_MODULE_COMPANY=true` no `.env`
5. Exibe próximos passos

### Via `.env` (manual)

```dotenv
PTAH_MODULE_COMPANY=true
```

### Via `config/ptah.php`

```php
'modules' => [
    'company' => env('PTAH_MODULE_COMPANY', false),
],
```

---

## Configuração

Em `config/ptah.php`, seção `company`:

```php
'company' => [
    // Model principal. Substitua por \App\Models\Company::class se quiser
    // usar um model próprio que estenda o da aplicação.
    'model'      => \Ptah\Models\Company::class,

    // Nome da tabela no banco
    'table'      => 'ptah_companies',

    // Disco do Filesystem para upload de logos
    'logo_disk'  => 'public',

    // Caminho base dentro do disco para armazenar logos
    'logo_path'  => 'companies/logos',

    // Campos de endereço que serão salvos no JSON 'address'
    'address_fields' => ['street', 'number', 'complement', 'district', 'city', 'state', 'country', 'zip'],
],
```

> **Usando model próprio:** se sua aplicação precisar adicionar métodos ou relacionamentos ao `Company`, crie `app/Models/Company.php` estendendo `Ptah\Models\Company` e aponte `config('ptah.company.model')` para ele.

---

## Banco de Dados

### ptah_companies

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `name` | string | — | Razão social / Nome fantasia |
| `slug` | string unique | — | Gerado automaticamente a partir do nome |
| `label` | string(4) | ✓ | Sigla de até 4 caracteres — exibida no badge do company switcher |
| `logo_path` | string(2048) | ✓ | Caminho relativo no disco configurado |
| `email` | string | ✓ | E-mail de contato |
| `phone` | string | ✓ | Telefone |
| `tax_id` | string | ✓ | CNPJ, CPF, EIN, VAT, etc. |
| `tax_type` | string | ✓ | Tipo do documento (`cnpj`, `cpf`, `ein`, `vat`, `other`) |
| `address` | json | ✓ | Campos de endereço (conforme `address_fields`) |
| `settings` | json | ✓ | Configurações arbitrárias em JSON |
| `is_default` | boolean | — | Empresa padrão do sistema (apenas 1) |
| `is_active` | boolean | — | Visibilidade / disponibilidade |
| `deleted_at` | timestamp | ✓ | SoftDelete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

### ptah_departments

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint PK | — | — |
| `name` | string | — | Nome do departamento |
| `description` | text | ✓ | Descrição livre |
| `is_active` | boolean | — | Visibilidade |
| `deleted_at` | timestamp | ✓ | SoftDelete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

> **Por que departments não têm company_id?** Departamentos são globais no modelo padrão — eles agrupam roles que podem ser globais ou por empresa. Se sua aplicação precisar de departamentos por empresa, adicione a coluna via migration própria.

---

## Models

### Company

**Namespace:** `Ptah\Models\Company`  
**Tabela:** `ptah_companies` (ou conforme `config('ptah.company.table')`)  
**Traits:** `SoftDeletes`

#### Cast automático

```php
protected $casts = [
    'address'    => 'array',
    'settings'   => 'array',
    'is_default' => 'boolean',
    'is_active'  => 'boolean',
];
```

#### Boot — auto-slug

O `Company` gera automaticamente o `slug` no evento `creating`, a partir do `name`, usando `Str::slug()`. Se o slug já existir, um sufixo numérico é adicionado (`empresa`, `empresa-2`, `empresa-3`, …). Em `updating`, o slug só é regenerado se o campo `name` foi alterado.

#### Métodos de instância

| Método | Retorno | Descrição |
|---|---|---|
| `getLogoUrl(): string` | string | URL completa do logo via `Storage::url()`. Se não houver logo, retorna URL de um avatar gerado pelo serviço `ui-avatars.com` com as iniciais do nome |
| `getAddressField(string $key, mixed $default = null)` | mixed | Acesso seguro a um campo do JSON `address` |
| `getSetting(string $key, mixed $default = null)` | mixed | Acesso seguro a um campo do JSON `settings` |

#### Escopos

```php
Company::active()->get();           // WHERE is_active = 1 AND deleted_at IS NULL
Company::default()->first();        // WHERE is_default = 1
Company::withTrashed()->find($id);  // inclui soft-deletados (para seeders idempotentes)
```

---

### Department

**Namespace:** `Ptah\Models\Department`  
**Tabela:** `ptah_departments`  
**Traits:** `SoftDeletes`

#### Relacionamentos

```php
$department->roles; // HasMany(Role) — roles que pertencem ao departamento
```

#### Escopos

```php
Department::active()->get();   // WHERE is_active = 1 AND deleted_at IS NULL
```

---

## CompanyService

**Namespace:** `Ptah\Services\Company\CompanyService`  
**Contrato:** `Ptah\Contracts\CompanyServiceContract`  
**Binding:** singleton no `PtahServiceProvider`

### Interface

```php
interface CompanyServiceContract
{
    public function getDefault(bool $createIfMissing = false): ?Company;
    public function getUserCompanies(mixed $userId = null): Collection;
    public function getCurrentCompanyId(mixed $userId = null): ?int;
    public function setCurrentCompany(int $companyId): void;
    public function createDefaultCompany(): Company;
    public function clearCache(?int $userId = null): void;
}
```

### Métodos

#### `getDefault(bool $createIfMissing = false): ?Company`

Retorna a empresa com `is_default = true`. O resultado é cacheado na chave `ptah_company_default`.

Se `$createIfMissing = true` e nenhuma empresa padrão existir, chama `createDefaultCompany()` automaticamente.

```php
$company = app(CompanyServiceContract::class)->getDefault();
```

---

#### `getUserCompanies(mixed $userId = null): Collection`

Retorna todas as empresas às quais o usuário tem acesso, via join com `ptah_user_roles`.

- Se `$userId = null`, resolve pelo `Auth::id()` ou pela sessão (chave `config('ptah.permissions.user_session_key')`)
- Cacheado na chave `ptah_user_companies:{userId}`
- Se o módulo `permissions` não estiver ativo ou o usuário for MASTER, retorna `Company::active()->get()`

```php
$companies = app(CompanyServiceContract::class)->getUserCompanies();
// Collection de Company
```

---

#### `getCurrentCompanyId(mixed $userId = null): ?int`

Determina a empresa ativa no contexto atual:

1. Lê `Session::get(config('ptah.permissions.company_session_key'))`
2. Se vazio, usa `getDefault()`
3. Se `config('ptah.company.multi_company') = false`, sempre retorna a empresa padrão

```php
$companyId = app(CompanyServiceContract::class)->getCurrentCompanyId();
```

---

#### `setCurrentCompany(int $companyId): void`

Salva o ID na sessão. Use em seletores de empresa na navbar.

```php
app(CompanyServiceContract::class)->setCurrentCompany($companyId);
```

---

#### `createDefaultCompany(): Company`

Cria uma empresa padrão usando `config('app.name')` como nome. Chamada automaticamente pelo `DefaultCompanySeeder` e pelo `ptah:module company`.

---

#### `clearCache(?int $userId = null): void`

Invalida o cache da empresa padrão. Se `$userId` fornecido, invalida também o cache de empresas do usuário.

---

### Injeção de dependência

```php
// Via contrato (recomendado)
use Ptah\Contracts\CompanyServiceContract;

public function __construct(
    private readonly CompanyServiceContract $companies,
) {}

// Via facade (helpers globais não existe para company — use o serviço direto)
$companies = app(CompanyServiceContract::class);
```

---

## Componente Livewire — CompanyList

**Namespace:** `Ptah\Livewire\Company\CompanyList`  
**View:** `ptah::livewire.company.company-list`  
**Layout:** `ptah::layouts.forge-dashboard`

### Propriedades

| Propriedade | Tipo | Descrição |
|---|---|---|
| `search` | string | Texto de busca (nome, e-mail, tax_id) |
| `sort` | string | Coluna de ordenação (padrão: `name`) |
| `direction` | string | `asc` ou `desc` |
| `showModal` | bool | Controle do modal criar/editar |
| `showDeleteModal` | bool | Controle do modal de confirmação |
| `isEditing` | bool | Modo edição vs criação |
| `editingId` | int\|null | ID em edição |
| `name` | string | Campo formulário |
| `label` | string | Campo formulário — sigla de até 4 chars, exibida no badge do switcher |
| `email` | string | Campo formulário |
| `phone` | string | Campo formulário |
| `tax_id` | string | Campo formulário |
| `tax_type` | string | Campo formulário |
| `is_active` | bool | Campo formulário |
| `is_default` | bool | Campo formulário |

### Métodos

| Método | Descrição |
|---|---|
| `create()` | Abre modal em modo criação |
| `edit(int $id)` | Carrega dados e abre modal em modo edição |
| `save()` | Valida e persiste (create ou update) |
| `confirmDelete(int $id)` | Abre modal de confirmação com o ID alvo |
| `delete()` | Executa soft-delete; bloqueia empresa `is_default` |
| `sort(string $column)` | Alterna ordenação da tabela |

### Propriedade computada

```php
$this->rows // Ptah\Models\Company paginado (15 por página), filtrado e ordenado
```

### Regras de validação

```php
'name'     => ['required', 'string', 'max:255'],
'label'    => [
    'nullable',
    'string',
    'max:4',
    Rule::unique('ptah_companies', 'label')->ignore($this->editingId),
],
'email'    => ['nullable', 'email', 'max:255'],
'tax_id'   => ['nullable', 'string', 'max:50'],
'tax_type' => ['nullable', 'string', 'in:cnpj,cpf,ein,vat,other'],
```

> **Unicidade do label:** a regra `Rule::unique(...)->ignore($editingId)` garante que não existam duas empresas com a mesma sigla (ex: dois `BETA`), mas permite salvar a edição de uma empresa sem alterar seu próprio label.

---

## Componente Livewire — CompanySwitcher

**Namespace:** `Ptah\Livewire\Company\CompanySwitcher`  
**View:** `ptah::livewire.company.company-switcher`  
**Uso:** embutido automaticamente no `forge-navbar`

Exibe uma barra horizontal na navbar com a empresa ativa e as outras empresas disponíveis.

### Comportamento

| Situação | O que aparece |
|---|---|
| 1 empresa cadastrada | Componente não renderiza nada |
| 2 ou mais empresas | Nome completo da ativa + labels de todas (como tabs) |

### Layout visual

```
[ Laravel ]  |  [ LAR ]  [ SLP ]
  ↑                  ↑       ↑
Nome da ativa   Label de cada empresa (botão clicável)
```

- O tab da empresa ativa fica com fundo na cor primária (`#5b21b6`)
- Clicar em outro label troca a empresa ativa e recarrega a página atual
- Em dark mode as cores adaptam via `.ptah-dark` no ancestral

### `getLabelDisplay()`

Método do model `Company`. Retorna em ordem de prioridade:

1. `$company->label` (se preenchido)
2. Primeiras 2 letras do nome (ex: `Laravel` → `LA`)

Usado pelo CompanySwitcher e pelo badge da tabela de empresas.

### Propriedades Livewire

| Propriedade | Tipo | Descrição |
|---|---|---|
| `activeId` | int\|null | ID da empresa ativa na sessão |
| `pageUrl` | string | URL capturada no `mount()` para redirect após troca |

### Troca de empresa

```php
// Internamente chama:
$this->companyService->initSession($companyId);
$this->redirect($this->pageUrl);
```

> **Por que capturar a URL no `mount()`?** Dentro de um request Livewire AJAX (`/livewire/update`), `request()->fullUrl()` retorna a URL do endpoint interno — não a URL da página. Por isso a URL é capturada em `mount()` via `url()->current()`, quando o componente ainda está no contexto do request web normal.

---

## Rota

Registrada automaticamente quando `ptah.modules.company = true`:

| Método | URI | Nome | Proteção |
|---|---|---|---|
| `GET` | `/ptah-companies` | `ptah.company.index` | `web`, `auth` |

Você pode sobrescrever o prefixo de URL publicando as rotas e editando o arquivo:

```bash
php artisan vendor:publish --tag=ptah-views --force
```

Ou definindo rotas próprias no `routes/web.php` da aplicação:

```php
Route::get('/admin/empresas', \Ptah\Livewire\Company\CompanyList::class)
    ->middleware(['web', 'auth'])
    ->name('admin.companies');
```

---

## Seeder

### DefaultCompanySeeder

**Namespace:** `Ptah\Seeders\DefaultCompanySeeder`

Cria a empresa padrão de forma **idempotente** (seguro para executar múltiplas vezes):

```php
// Execução manual
php artisan db:seed --class="Ptah\Seeders\DefaultCompanySeeder"
```

**Lógica:**

```php
// Busca incluindo soft-deletados para não duplicar
$company = Company::withTrashed()->where('is_default', true)->first();

if (!$company) {
    Company::create([
        'name'       => config('app.name', 'Default Company'),
        'is_default' => true,
        'is_active'  => true,
    ]);
}
```

---

### PtahDemoSeeder

**Namespace:** `Ptah\Seeders\PtahDemoSeeder`

Cria dados de demonstração prontos para exploração. Todas as operações são **idempotentes** — seguro executar múltiplas vezes.

```bash
# Executar manualmente
php artisan db:seed --class="Ptah\Seeders\PtahDemoSeeder"

# Ou ativar durante a instalação do pacote
php artisan ptah:install --demo
```

**O que é criado:**

| Tipo | Itens |
|---|---|
| Empresas | `BETA` (Beta Tecnologia Ltda) e `CORP` (Corp Solutions S/A) |
| Departamentos | TI, Comercial, Financeiro |
| Roles | Editor, Viewer |
| Itens de menu | Usuários, Produtos, Relatórios (somente se `menu.driver = database`) |

---

## Multi-empresa vs Tenant único

O módulo funciona nos dois cenários sem configuração adicional:

### Cenário 1 — Empresa única

```dotenv
PTAH_MODULE_COMPANY=true
# Sem mudanças adicionais
```

Neste caso, a tela `/ptah-companies` serve para completar dados cadastrais da empresa (CNPJ, logo, endereço). Os departamentos organizam os perfis de acesso.

### Cenário 2 — Multi-empresa

```dotenv
PTAH_MODULE_COMPANY=true
PTAH_MULTI_COMPANY=true  # Ativa resolução por sessão
```

No módulo `permissions`, o `CompanyService::getCurrentCompanyId()` é usado para filtrar permissões por empresa. Usuários podem ter roles diferentes em cada empresa.

**Seletor de empresa customizado na navbar:**

```blade
{{-- Exemplo de componente Livewire próprio --}}
<select wire:change="switchCompany($event.target.value)">
    @foreach ($userCompanies as $company)
        <option value="{{ $company->id }}" {{ $current == $company->id ? 'selected' : '' }}>
            {{ $company->name }}
        </option>
    @endforeach
</select>
```

```php
public function switchCompany(int $id): void
{
    app(\Ptah\Contracts\CompanyServiceContract::class)->setCurrentCompany($id);
    $this->redirect(request()->header('Referer') ?? '/dashboard');
}
```

---

## Integração com Permissions

Quando o módulo `permissions` está ativo, os departamentos aparecem como categoria dos roles no `RoleList`. A empresa atual do usuário é usada automaticamente pelo `PermissionService` para filtrar permissões.

Ver documentação completa em [Permissions.md](Permissions.md).

---

## Customizando Views

```bash
# Publica todas as views do pacote
php artisan vendor:publish --tag=ptah-views --force
```

Arquivos relevantes após publicação:

```
resources/views/vendor/ptah/
└── livewire/
    └── company/
        └── company-list.blade.php   ← CRUD de empresas
```

Para sobrescrever apenas o layout do card de empresa ou adicionar campos extras (ex: campo `website`), edite o arquivo publicado e adicione a propriedade correspondente no seu `CompanyList` estendido:

```php
// app/Livewire/Company/CompanyList.php
class CompanyList extends \Ptah\Livewire\Company\CompanyList
{
    public string $website = '';

    protected function extraFillable(): array
    {
        return ['website' => $this->website];
    }
}
```

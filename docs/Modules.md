# Módulos Opcionais — Auth, Menu, Company & Permissions

**Pacote:** `jonytonet/ptah`  
**Versão mínima:** ver tags no repositório  
**Laravel:** 11+ | **Livewire:** 3.x

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Ativando os Módulos](#ativando-os-módulos)
3. [Módulo Auth](#módulo-auth)
   - [Configuração](#configuração-auth)
   - [Rotas](#rotas)
   - [Componentes Livewire](#componentes-livewire)
   - [LoginPage](#loginpage)
   - [ForgotPasswordPage](#forgotpasswordpage)
   - [ResetPasswordPage](#resetpasswordpage)
   - [TwoFactorChallengePage](#twofactorchallengepage)
   - [ProfilePage](#profilepage)
   - [Dashboard](#dashboard)
4. [Autenticação 2FA](#autenticação-2fa)
   - [TOTP (App Autenticador)](#totp-app-autenticador)
   - [E-mail OTP](#e-mail-otp)
   - [Códigos de Recuperação](#códigos-de-recuperação)
   - [TwoFactorService](#twofactorservice)
5. [Gerenciamento de Sessões](#gerenciamento-de-sessões)
   - [SessionService](#sessionservice)
6. [Módulo Menu](#módulo-menu)
   - [Configuração](#configuração-menu)
   - [Driver `config`](#driver-config)
   - [Driver `database`](#driver-database)
   - [Model Menu](#model-menu)
   - [MenuService](#menuservice)
   - [Tela de Gestão de Menus](#tela-de-gestão-de-menus)
   - [Sidebar — Ícones e Grupos Accordion](#sidebar--ícones-e-grupos-accordion)
7. [Módulo Company](#módulo-company)
8. [Módulo Permissions](#módulo-permissions)
9. [Comando ptah:module](#comando-ptahmodule)
10. [Dependências Opcionais](#dependências-opcionais)
11. [Referência de Configuração](#referência-de-configuração)
12. [Customizando Views](#customizando-views)

---

## Visão Geral

Os módulos **Auth** e **Menu** são subsistemas opcionais do Ptah que podem ser ativados de forma independente em qualquer projeto.

| Módulo | Funcionalidades |
|---|---|
| **auth** | Login com rate limit, recuperação de senha, 2FA (TOTP + e-mail), sessões ativas, perfil com foto |
| **menu** | Menu lateral dinâmico carregado do banco, com cache e estrutura em árvore |
| **company** | Gestão de empresas e departamentos, contexto multi-tenant por sessão |
| **permissions** | ACL hierárquica: roles, objetos de página, permissões CRUD, middleware, Blade directives, auditoria |

**Princípio de não-ruptura:** todos os módulos são `false` por padrão. Um projeto que usa apenas o scaffolding `ptah:forge` e o `BaseCrud` continua 100% funcional sem nenhuma mudança.

**Dependências entre módulos:**

```
permissions → requer company
company     → independente
auth        → independente
menu        → independente (opcional: requer auth para a tela de gestão)
```

---

## Ativando os Módulos

### Via comando (recomendado)

```bash
# Ativar autenticação
php artisan ptah:module auth

# Ativar menu dinâmico
php artisan ptah:module menu

# Ativar gestão de empresas
php artisan ptah:module company

# Ativar ACL / permissões (ativa company automaticamente se necessário)
php artisan ptah:module permissions

# Ver estado de todos os módulos
php artisan ptah:module --list
```

O comando:
1. Publica as migrations necessárias
2. Executa `php artisan migrate`
3. Define automaticamente a variável de ambiente no `.env`

### Via `.env` (manual)

```dotenv
PTAH_MODULE_AUTH=true
PTAH_MODULE_MENU=true
PTAH_MENU_DRIVER=database   # 'config' (padrão) ou 'database'
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true
```

### Via `config/ptah.php` (manual)

```php
'modules' => [
    'auth'        => env('PTAH_MODULE_AUTH', false),
    'menu'        => env('PTAH_MODULE_MENU', false),
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
],
```

---

## Módulo Auth

### Configuração Auth

Em `config/ptah.php`, seção `auth`:

```php
'auth' => [
    'guard'              => 'web',
    'home'               => '/dashboard',    // redireciona após login
    'register_enabled'   => false,           // sem registro público
    'two_factor'         => true,            // habilita 2FA
    'remember_me'        => true,            // exibe "lembrar-me" no login
    'session_protection' => true,            // gerencia sessões ativas
    'route_prefix'       => '',              // prefixo de URL (ex: 'admin')
    'middleware'         => ['web'],
],
```

### Rotas

Registradas automaticamente quando `ptah.modules.auth = true`:

| Método | URI | Nome | Proteção |
|---|---|---|---|
| `GET` | `/login` | `ptah.auth.login` | pública |
| `POST` | `/logout` | `ptah.auth.logout` | pública |
| `GET` | `/forgot-password` | `ptah.auth.forgot-password` | pública |
| `GET` | `/reset-password/{token}` | `password.reset` | pública |
| `GET` | `/two-factor-challenge` | `ptah.auth.two-factor` | pública |
| `GET` | `/dashboard` | `ptah.dashboard` | `auth` |
| `GET` | `/profile` | `ptah.profile` | `auth` |

> Use `route_prefix` para montar todas as rotas sob um prefixo. Ex: `'route_prefix' => 'admin'` gera `/admin/login`, `/admin/dashboard`, etc.

### Componentes Livewire

Registrados sob o namespace `Ptah\Livewire\Auth`:

| Tag | Classe | Layout |
|---|---|---|
| `ptah::auth.login` | `LoginPage` | `ptah::layouts.forge-auth` |
| `ptah::auth.forgot-password` | `ForgotPasswordPage` | `ptah::layouts.forge-auth` |
| `ptah::auth.reset-password` | `ResetPasswordPage` | `ptah::layouts.forge-auth` |
| `ptah::auth.two-factor` | `TwoFactorChallengePage` | `ptah::layouts.forge-auth` |
| `ptah::auth.profile` | `ProfilePage` | `ptah::layouts.forge-dashboard` |

---

### LoginPage

**Arquivo:** `src/Livewire/Auth/LoginPage.php`  
**View:** `resources/views/livewire/auth/login.blade.php`

Funcionalidades:
- Autenticação via `Auth::attempt()`
- **Rate Limit:** 5 tentativas por `email|ip`, bloqueio por 60 segundos
- Campo "lembrar-me" (configurável via `ptah.auth.remember_me`)
- Detecção de 2FA ativo: em vez de fazer login, salva `ptah.2fa.user_id` na sessão e redireciona para `/two-factor-challenge`
- Sem 2FA: `Session::regenerate()` + redirect para `ptah.auth.home`

**Propriedades Livewire:**

| Propriedade | Tipo | Descrição |
|---|---|---|
| `email` | string | Campo e-mail |
| `password` | string | Campo senha |
| `remember` | bool | Marcar "lembrar-me" |
| `errorMessage` | string | Mensagem de erro exibida no alerta |

---

### ForgotPasswordPage

**Arquivo:** `src/Livewire/Auth/ForgotPasswordPage.php`  
**View:** `resources/views/livewire/auth/forgot-password.blade.php`

Usa o broker padrão do Laravel (`Password::sendResetLink()`). Exibe feedback de sucesso/erro sem revelar se o e-mail existe no banco.

---

### ResetPasswordPage

**Arquivo:** `src/Livewire/Auth/ResetPasswordPage.php`  
**View:** `resources/views/livewire/auth/reset-password.blade.php`

- `mount(string $token)` — recebe token e e-mail via query string
- `Password::reset()` → dispara evento `PasswordReset` → redirect para login com `status`

---

### TwoFactorChallengePage

**Arquivo:** `src/Livewire/Auth/TwoFactorChallengePage.php`  
**View:** `resources/views/livewire/auth/two-factor-challenge.blade.php`

Fluxo de verificação 2FA pós-login:

```
LoginPage salva ptah.2fa.user_id na sessão
  ↓
TwoFactorChallengePage::mount() verifica session
  ↓
Usuário digita código
  ↓
verify() → TwoFactorService::verify*()
  ↓
Sucesso → Auth::loginUsingId() + Session::regenerate() + evento Login
```

**Propriedades:**

| Propriedade | Tipo | Descrição |
|---|---|---|
| `code` | string | Código digitado |
| `usingRecovery` | bool | Toggle para usar código de recuperação |

**Métodos:**

| Método | Descrição |
|---|---|
| `verify()` | Verifica o código (recovery / email OTP / TOTP) |
| `sendEmailCode()` | Re-envia código OTP por e-mail |

---

### ProfilePage

**Arquivo:** `src/Livewire/Auth/ProfilePage.php`  
**View:** `resources/views/livewire/auth/profile.blade.php`

Página de perfil com 5 abas:

| Aba (`activeTab`) | Funcionalidade |
|---|---|
| `profile` | Editar nome e e-mail |
| `password` | Alterar senha (valida senha atual) |
| `two_factor` | Configurar / desativar 2FA (TOTP + e-mail) |
| `sessions` | Ver e revogar sessões ativas |
| `photo` | Upload de foto de perfil (`WithFileUploads`) |

**Métodos principais:**

| Método | Descrição |
|---|---|
| `saveProfile()` | Persiste nome e e-mail |
| `savePassword()` | Valida atual + salva nova senha |
| `initTotp()` | Gera secret TOTP + QR code SVG, exibe formulário de confirmação |
| `confirmTotp()` | Verifica código e ativa 2FA TOTP |
| `enableEmailTwoFactor()` | Ativa 2FA por e-mail imediatamente |
| `disableTwoFactor()` | Desativa e apaga dados de 2FA |
| `regenerateRecoveryCodes()` | Gera novos 8 códigos de recuperação |
| `loadSessions()` | Carrega sessões ativas via `SessionService` |
| `revokeSession($id)` | Revoga sessão específica |
| `revokeOtherSessions()` | Revoga todas exceto a atual |
| `savePhoto()` | Salva foto no disco `profile-photos` |
| `removePhoto()` | Remove foto e limpa o campo no banco |

---

### Dashboard

**View:** `resources/views/livewire/auth/dashboard.blade.php`

View estática servida pela rota `ptah.dashboard`. Usa o layout `forge-dashboard` e exibe 4 `<x-forge-stat-card>` de exemplo com informações do sistema (nome do usuário, app, ambiente, versão Laravel).

Para personalizar, publique as views:

```bash
php artisan vendor:publish --tag=ptah-views --force
# ou apenas para auth:
php artisan vendor:publish --tag=ptah-auth --force
```

Depois edite `resources/views/vendor/ptah/livewire/auth/dashboard.blade.php`.

---

## Autenticação 2FA

O sistema suporta dois métodos simultâneos que o usuário escolhe na aba `two_factor` do perfil.

### TOTP (App Autenticador)

Usa a biblioteca `pragmarx/google2fa-laravel` (instalação opcional — ver [Dependências Opcionais](#dependências-opcionais)).

**Fluxo de ativação:**

```
ProfilePage::initTotp()
  → TwoFactorService::enableTotp()
      → Gera secret (Google2FA::generateSecretKey())
      → Salva criptografado em two_factor_secret
      → Retorna [secret, qrCodeSvg, recoveryCodes]
  → Exibe QR Code + campo de confirmação

Usuário escaneia e digita código → ProfilePage::confirmTotp()
  → TwoFactorService::confirmTotp()
      → Verifica código com Google2FA::verifyKey()
      → Seta two_factor_confirmed_at = now()
      → Seta two_factor_type = 'totp'
```

**QR Code:** gerado via `bacon/bacon-qr-code` em SVG. Se a biblioteca não estiver instalada, usa a API do Google Charts como fallback.

**Colunas adicionadas à tabela `users`:**

| Coluna | Tipo | Descrição |
|---|---|---|
| `two_factor_secret` | text nullable | Secret TOTP (criptografado) |
| `two_factor_recovery_codes` | text nullable | JSON com 8 códigos |
| `two_factor_confirmed_at` | timestamp nullable | Data de confirmação; `null` = não ativo |
| `two_factor_type` | string nullable | `'totp'` ou `'email'` |
| `profile_photo_path` | string(2048) nullable | Caminho da foto de perfil |

### E-mail OTP

Não requer biblioteca adicional. Usa o `Cache` do Laravel.

**Fluxo:**

```
TwoFactorService::sendEmailCode($user)
  → Gera código de 6 dígitos
  → Cache::put("ptah_2fa_email_{userId}", $code, 600)
  → Envia TwoFactorCodeMail
  → Retorna o código (para testes)

TwoFactorService::verifyEmailCode($user, $code)
  → Cache::get("ptah_2fa_email_{userId}")
  → Compara com hash_equals para timing safety
  → Se correto: Cache::forget()
```

**TTL:** 600 segundos (10 minutos).

### Códigos de Recuperação

8 códigos no formato `xxxxx-xxxxx`, gerados com `Str::random()`.

- Armazenados em `two_factor_recovery_codes` como JSON criptografado
- **Cada código é de uso único** — ao ser verificado, é removido do array
- O usuário pode regenerar na aba `two_factor` do perfil

### TwoFactorService

**Namespace:** `Ptah\Services\Auth\TwoFactorService`  
**Singleton** registrado no `PtahServiceProvider`.

| Método | Retorno | Descrição |
|---|---|---|
| `enableTotp(User $user)` | array | Gera secret + QR + recovery codes |
| `confirmTotp(User $user, string $code)` | bool | Confirma e ativa TOTP |
| `verifyTotp(User $user, string $code)` | bool | Verifica código no login |
| `sendEmailCode(User $user)` | string | Envia e-mail OTP; retorna código |
| `verifyEmailCode(User $user, string $code)` | bool | Verifica OTP do e-mail |
| `verifyRecoveryCode(User $user, string $code)` | bool | Usa e consome código de recuperação |
| `isEnabled(User $user)` | bool | `true` se `two_factor_confirmed_at` não é `null` |
| `disable(User $user)` | void | Limpa todos os campos 2FA |

---

## Gerenciamento de Sessões

Requer driver de sessão `database` (`SESSION_DRIVER=database`). O `SessionService` verifica silenciosamente se a tabela `sessions` existe antes de qualquer consulta.

### SessionService

**Namespace:** `Ptah\Services\Auth\SessionService`  
**Singleton** registrado no `PtahServiceProvider`.

| Método | Retorno | Descrição |
|---|---|---|
| `getActiveSessions(User $user)` | array | Lista sessões ativas com detalhes de dispositivo |
| `revokeSession(string $sessionId)` | void | Remove sessão pelo ID |
| `revokeOtherSessions(User $user, string $currentId)` | void | Remove todas exceto a atual |

**Estrutura de cada sessão retornada:**

```php
[
    'id'                  => 'abc123...',
    'ip_address'          => '192.168.0.1',
    'user_agent'          => 'Mozilla/5.0...',
    'browser'             => 'Chrome',        // detectado via parseAgent()
    'platform'            => 'Windows',       // detectado via parseAgent()
    'last_activity'       => 1709000000,
    'last_activity_human' => 'há 3 minutos',  // Carbon::diffForHumans()
    'is_current'          => true,
]
```

**Browsers detectados:** Edge, Opera, Chrome, Firefox, Safari, IE  
**Plataformas detectadas:** Windows, macOS, Linux, Android, iPhone, iPad

---

## Módulo Menu

### Configuração Menu

Em `config/ptah.php`, seção `menu`:

```php
'menu' => [
    'driver'    => env('PTAH_MENU_DRIVER', 'config'),   // 'config' ou 'database'
    'cache'     => true,
    'cache_ttl' => 300,    // segundos
    'max_depth' => 4,      // profundidade máxima da árvore
],
```

### Driver `config`

**Padrão — nenhuma migration necessária.** Os itens do menu são lidos de `ptah.forge.sidebar_items`, exatamente como antes. Projetos existentes continuam funcionando sem nenhuma mudança.

```php
// config/ptah.php
'forge' => [
    'sidebar_items' => [
        ['icon' => 'home',  'label' => 'Dashboard', 'url' => '/dashboard', 'match' => 'dashboard'],
        ['icon' => 'users', 'label' => 'Usuários',  'url' => '/users',     'match' => 'users*'],
    ],
],
```

### Driver `database`

Ativado com `PTAH_MENU_DRIVER=database`. Os itens são lidos da tabela `menus` com cache automático.

**Prioridade de resolução no `forge-sidebar`:**

```
prop :items (explícito)
  ↓ (se null)
MenuService::getTree()  ← quando driver = 'database'
  ↓ (se driver = 'config' ou módulo menu inativo)
config('ptah.forge.sidebar_items')
  ↓ (se vazio)
itens de demo hardcoded
```

### Model Menu

**Namespace:** `Ptah\Models\Menu`  
**Tabela:** `menus`  
**SoftDeletes:** sim

**Schema da tabela:**

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | bigint PK | — |
| `parent_id` | bigint FK nullable | Auto-referência (grupos) |
| `text` | string | Texto interno/legado |
| `label` | virtual (alias de `text`) | Rótulo exibido — a sidebar lê `label` com fallback para `text` |
| `url` | string(2048) | URL do link |
| `icon` | string nullable | Classe CSS (`bx bx-home`, `fas fa-user`) ou nome SVG legado (`home`) |
| `type` | enum | `menuLink` ou `menuGroup` |
| `target` | enum | `_self` ou `_blank` |
| `link_order` | integer | Ordem de exibição |
| `is_active` | boolean | Visibilidade |
| `deleted_at` | timestamp | SoftDelete |

**Relacionamentos:**

```php
$menu->parent   // BelongsTo(Menu)
$menu->children // HasMany(Menu)
```

**Métodos estáticos:**

| Método | Retorno | Descrição |
|---|---|---|
| `Menu::getTreeForSidebar()` | array | Árvore cacheada pronta para o sidebar |
| `Menu::clearCache()` | void | Invalida o cache manualmente |
| `Menu::buildTree()` | array | Constrói árvore sem cache |

**Formato de saída** (compatível com `forge-sidebar`):

```php
[
    [
        'label'    => 'Dashboard',
        'url'      => '/dashboard',
        'icon'     => 'bx bx-home-alt',   // classe CSS Boxicons
        'type'     => 'menuLink',
        'target'   => '_self',
        'match'    => 'dashboard',
        'children' => [],
    ],
    [
        'label'    => 'Cadastros',
        'icon'     => 'bx bx-folder',
        'type'     => 'menuGroup',         // grupo → accordion na sidebar
        'children' => [
            [
                'label'  => 'Produtos',
                'url'    => '/products',
                'icon'   => 'bx bx-cube',
                'type'   => 'menuLink',
                'target' => '_self',
                'match'  => 'products*',
            ],
        ],
    ],
]
```

### MenuService

**Namespace:** `Ptah\Services\Menu\MenuService`  
**Singleton** registrado no `PtahServiceProvider`.

| Método | Retorno | Descrição |
|---|---|---|
| `getTree()` | array | Items do menu conforme o driver configurado |
| `getFromConfig()` | array | Lê e normaliza `ptah.forge.sidebar_items` |
| `clearCache()` | void | Invalida o cache (`ptah_menu_tree`) |
| `allForAdmin()` | Collection | Todos os itens (incluindo inativos) para tela de gestão |
| `listForSelect()` | array | Grupos para campo `parent_id` no formulário |

**Cache:**

- Chave: `ptah_menu_tree`  
- TTL: `config('ptah.menu.cache_ttl', 300)` segundos  
- Invalidação automática: o Observer do model `Menu` chama `Menu::clearCache()` nos eventos `saved`, `deleted` e `restored`

---

### Tela de Gestão de Menus

Quando o módulo menu está ativo, o Ptah registra automaticamente a tela Livewire de CRUD de itens:

| Rota | Componente | Acesso |
|---|---|---|
| `/ptah-menu` | `Ptah\Livewire\Menu\MenuList` | `ptah.menu.manage` |

A tela aparece automaticamente no **dropdown de Administração** da navbar (`forge-navbar`) assim que a rota existir.

**Funcionalidades:**
- Criação e edição de **links** (`menuLink`) e **grupos** (`menuGroup`)
- Campo **pai** — permite aninhar links dentro de um grupo
- Preview em tempo real do ícone no formulário
- **Ordem** de exibição (`link_order`)
- Toggle de status (ativo/inativo) diretamente na tabela
- Exclusão de grupo desvincula automaticamente os filhos (evita órfãos)
- **Busca** e filtro por tipo na toolbar
- Invalida o cache `ptah_menu_tree` após cada operação

**Ícones suportados:**

| Formato | Exemplo | Resultado |
|---|---|---|
| Classe CSS com espaço | `bx bx-home-alt` | `<i class="bx bx-home-alt">` (Boxicons) |
| Classe CSS com espaço | `fas fa-user` | `<i class="fas fa-user">` (Font Awesome) |
| Nome simples (legado) | `home` | SVG inline do mapa legado |

> As bibliotecas Boxicons 2.1.4 e Font Awesome 6.7.2 já são carregadas via CDN pelo `forge-dashboard-layout`.

---

### Sidebar — Ícones e Grupos Accordion

O `forge-sidebar` foi atualizado para suportar três comportamentos dependendo do `type` de cada item:

| `type` | Comportamento |
|---|---|
| `menuLink` | Link direto — `<a href>` com highlight de rota ativa |
| `menuGroup` + filhos | Cabeçalho de grupo com **accordion Alpine.js** (`x-collapse`) — seta animada indica aberto/fechado |
| `menuGroup` sem filhos | Label desabilitado (div, sem clique) |

**Driver `database` — Dashboard fixo:**

Quando `PTAH_MENU_DRIVER=database`, o item **Dashboard** é injetado automaticamente no topo do menu (antes dos itens do banco) com ícone `bx bx-home-alt`. Os demais itens vêm do banco.

**Renderização de ícones:**

```php
// Lógica em forge-sidebar.blade.php
if (str_contains($icon, ' ')) {
    // Boxicons / Font Awesome → <i class>
    return '<i class="' . $icon . '">';
}
// Nome sem espaço → SVG inline (legado)
return $svgIcons[$icon] ?? $svgIcons['cube'];
```

**Logout:**  
O botão de saída usa `<i class="bx bx-log-out">` em vez de SVG inline.

**Estado accordion:**  
Grupos que contêm a rota ativa iniciam **abertos** automaticamente (`x-data="{ open: true }"`). O estado não é persistido entre sessões.

---

## Módulo Company

O módulo **company** adiciona gestão completa de empresas e departamentos ao Ptah.

**Recursos:**
- CRUD de empresas com logo, dados fiscais (CNPJ/CPF/EIN/VAT), endereço em JSON e configurações arbitrárias
- CRUD de departamentos (agrupadores de roles)
- `CompanyService` com contexto por sessão, cache e suporte multi-empresa
- Tela `/ptah-companies` com Livewire 4
- `DefaultCompanySeeder` idempotente
- Todos os models (`Company`, `Department`) usam a trait `HasAuditFields` — `created_by`, `updated_by` e `deleted_by` preenchidos automaticamente via Eloquent events

**Ativação rápida:**

```bash
php artisan ptah:module company
```

> Consulte a **documentação completa** em [Company.md](Company.md).

---

## Módulo Permissions

O módulo **permissions** implementa ACL hierárquica granular baseada em roles.

**Recursos:**
- Roles/Perfis com departamento e cor identificadora
- Role MASTER — bypass total de verificações
- Páginas e Objetos de Página (button, field, section, api, report, tab, link, page)
- Permissões por objeto: `can_create`, `can_read`, `can_update`, `can_delete` + JSON `extra`
- `PermissionService` com cache (suporte a tags Redis), auditoria e resolução automática de usuário
- `RoleService` com proteção de MASTER e sincronização de permissões em lote
- Helpers globais: `ptah_can()`, `ptah_is_master()`, `ptah_permissions()`
- Facade `Permission::check()`
- Diretivas Blade: `@ptahCan` / `@ptahMaster`
- Middleware: `ptah.can:objeto,acao`
- 5 telas admin Livewire: Departamentos, Roles, Páginas/Objetos, Usuários ACL, Auditoria
- `DefaultAdminSeeder` idempotente que cria toda a cadeia admin
- Todos os models (`Role`, `PtahPage`, `PageObject`, `UserRole`, `RolePermission`) usam a trait `HasAuditFields` — rastreamento automático de quem criou, editou e excluiu cada registro

**Ativação rápida:**

```bash
php artisan ptah:module permissions
# Ativa company automaticamente se necessário
```

> Consulte a **documentação completa** em [Permissions.md](Permissions.md).

---

## Comando ptah:module

```
php artisan ptah:module {module?} {--list} {--force}
```

| Argumento/Opção | Descrição |
|---|---|
| `module` | `auth`, `menu`, `company` ou `permissions`. Se omitido, exibe seletor interativo |
| `--list` | Exibe tabela com o estado de cada módulo |
| `--force` | Sobrescreve arquivos publicados existentes |

**Exemplo de saída do `--list`:**

```
  Módulos disponíveis no Ptah:

  ┌─────────────┬────────────────────────────┬───────────┐
  │ Módulo      │ Variável .env              │ Estado    │
  ├─────────────┼────────────────────────────┼───────────┤
  │ auth        │ PTAH_MODULE_AUTH           │ ✔ ativo   │
  │ menu        │ PTAH_MODULE_MENU           │ ✘ inativo │
  │ company     │ PTAH_MODULE_COMPANY        │ ✔ ativo   │
  │ permissions │ PTAH_MODULE_PERMISSIONS    │ ✔ ativo   │
  └─────────────┴────────────────────────────┴───────────┘

  Para ativar: php artisan ptah:module {módulo}
```

**O que `ptah:module auth` faz:**

1. Publica a migration `add_two_factor_columns_to_users_table`
2. Executa `php artisan migrate`
3. Adiciona `PTAH_MODULE_AUTH=true` ao `.env`
4. Exibe próximos passos

**O que `ptah:module menu` faz:**

1. Publica a migration `create_menus_table`
2. Executa `php artisan migrate`
3. Adiciona `PTAH_MODULE_MENU=true` ao `.env`
4. Exibe próximos passos

**O que `ptah:module company` faz:**

1. Publica as migrations `ptah_companies` e `ptah_departments`
2. Executa `php artisan migrate`
3. Executa `DefaultCompanySeeder` (empresa padrão idempotente)
4. Adiciona `PTAH_MODULE_COMPANY=true` ao `.env`
5. Exibe próximos passos

**O que `ptah:module permissions` faz:**

1. Ativa `company` se ainda não estiver ativo
2. Publica as 6 migrations de permissões
3. Executa `php artisan migrate`
4. Executa `DefaultAdminSeeder` (empresa → departamento → MASTER role → admin → vínculo)
5. Adiciona `PTAH_MODULE_PERMISSIONS=true` ao `.env`
6. Exibe caixa com credenciais do admin criado

---

## Dependências Opcionais

Por padrão, o pacote não instala nenhuma dependência extra para os módulos. A 2FA TOTP usa fallback automático.

### Para 2FA TOTP completo

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

| Pacote | Finalidade |
|---|---|
| `pragmarx/google2fa-laravel` | Geração e verificação de códigos TOTP |
| `bacon/bacon-qr-code` | Geração de QR Code em SVG (setup TOTP) |

**Sem esses pacotes:**
- TOTP **não funciona** — a opção não será exibida no perfil (verifique com `class_exists(\PragmaRX\Google2FA\Google2FA::class)`)
- QR Code usa a API do Google Charts como fallback visual

### Para sessões ativas

```bash
php artisan session:table
php artisan migrate
```

E no `.env`:

```dotenv
SESSION_DRIVER=database
```

O `SessionService` verifica silenciosamente se a tabela existe — sem essa configuração, a aba Sessões exibirá uma lista vazia sem erro.

---

## Referência de Configuração

Seção completa adicionada ao `config/ptah.php`:

```php
/*
|--------------------------------------------------------------------------
| Módulos Opcionais
|--------------------------------------------------------------------------
*/
'modules' => [
    'auth'        => env('PTAH_MODULE_AUTH', false),
    'menu'        => env('PTAH_MODULE_MENU', false),
    'company'     => env('PTAH_MODULE_COMPANY', false),
    'permissions' => env('PTAH_MODULE_PERMISSIONS', false),
],

/*
|--------------------------------------------------------------------------
| Configurações de Autenticação
|--------------------------------------------------------------------------
*/
'auth' => [
    'guard'              => 'web',
    'home'               => '/dashboard',
    'register_enabled'   => false,
    'two_factor'         => true,
    'remember_me'        => true,
    'session_protection' => true,
    'route_prefix'       => '',
    'middleware'         => ['web'],
],

/*
|--------------------------------------------------------------------------
| Configurações de Menu
|--------------------------------------------------------------------------
*/
'menu' => [
    'driver'    => env('PTAH_MENU_DRIVER', 'config'),
    'cache'     => true,
    'cache_ttl' => 300,
    'max_depth' => 4,
],

/*
|--------------------------------------------------------------------------
| Configurações de Company
|--------------------------------------------------------------------------
*/
'company' => [
    'table_prefix'       => 'ptah_',
    'default_name'       => env('PTAH_COMPANY_NAME', 'Minha Empresa'),
    'default_slug'       => env('PTAH_COMPANY_SLUG', 'minha-empresa'),
    'allow_multiple'     => env('PTAH_COMPANY_MULTIPLE', false),
    'require_department' => env('PTAH_COMPANY_REQUIRE_DEPT', false),
    'route_prefix'       => 'ptah-companies',
    'middleware'         => ['web', 'auth'],
],

/*
|--------------------------------------------------------------------------
| Configurações de Permissions
|--------------------------------------------------------------------------
*/
'permissions' => [
    'cache_enabled'  => env('PTAH_PERM_CACHE', true),
    'cache_ttl'      => env('PTAH_PERM_CACHE_TTL', 300),
    'audit_enabled'  => env('PTAH_PERM_AUDIT', false),
    'master_role'    => 'MASTER',
    'route_prefix'   => 'ptah-admin',
    'middleware'      => ['web', 'auth'],
    'admin_name'     => env('PTAH_ADMIN_NAME', 'Admin'),
    'admin_email'    => env('PTAH_ADMIN_EMAIL', 'admin@ptah.test'),
    'admin_password' => env('PTAH_ADMIN_PASSWORD', 'ptah@admin'),
],
```

---

## Customizando Views

As views dos módulos fazem parte do namespace `ptah::`. Para customizar, publique e edite localmente:

```bash
# Publica TODAS as views (inclui auth + componentes Forge)
php artisan vendor:publish --tag=ptah-views --force
```

As views serão copiadas para:

```
resources/views/vendor/ptah/
├── layouts/
│   ├── forge-auth.blade.php
│   └── forge-dashboard.blade.php
├── livewire/
│   └── auth/
│       ├── login.blade.php
│       ├── forgot-password.blade.php
│       ├── reset-password.blade.php
│       ├── two-factor-challenge.blade.php
│       ├── profile.blade.php
│       └── dashboard.blade.php
├── mail/
│   └── two-factor-code.blade.php
└── components/
    └── ...componentes Forge...
```

O Laravel carrega automaticamente views do diretório `vendor/ptah` com precedência sobre as do pacote.

> **Atenção:** após publicar, atualizações futuras do pacote não afetarão as views publicadas. Re-publique com `--force` quando quiser receber as atualizações visuais.

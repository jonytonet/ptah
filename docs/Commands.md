# Comandos do Ptah

Este documento lista todos os comandos Artisan disponíveis no pacote Ptah.

---

## Índice

1. [ptah:install](#ptahinstall)
2. [ptah:forge](#ptahforge)
3. [ptah:module](#ptahmodule)
4. [ptah:config](#ptahconfig)

---

## ptah:install

**Descrição:** Instala o pacote Ptah no projeto Laravel.

**Uso:**
```bash
php artisan ptah:install
php artisan ptah:install --force
php artisan ptah:install --skip-npm
php artisan ptah:install --demo
php artisan ptah:install --boost
```

**Opções:**
- ``--force`` — Sobrescreve arquivos existentes sem perguntar
- ``--skip-npm`` — Não executa ``npm install`` e ``npm run build``
- ``--demo`` — Instala dados de demonstração (companies, departments, roles, menu)
- ``--boost`` — Instala Laravel Boost para integração com agentes de IA (Copilot, Claude, Cursor)

**O que faz:**

1. **Publica configurações** → ``config/ptah.php``
2. **Publica stubs** → ``stubs/ptah/`` (para customização)
3. **Publica migrations** → ``database/migrations/``
4. **Publica traduções** → ``lang/vendor/ptah/``
5. **Configura Tailwind CSS** → Injeta design tokens no ``resources/css/app.css``
6. **Executa migrations** → Cria tabelas ``ptah_*``
7. **Cria symlink de storage** → ``php artisan storage:link``
8. **Seed admin padrão** → Cria empresa e usuário admin (se migrations executadas)
9. **Seed de demo** → Cria dados de exemplo (se ``--demo``)
10. **Instala Boost** → ``composer require laravel/boost --dev`` + ``boost:install`` (se ``--boost``)
11. **Instala dependências Node** → ``npm install && npm run build`` (exceto se ``--skip-npm``)

**Credenciais padrão:** (configuráveis em ``config/ptah.php``)
- E-mail: ``admin@admin.com``
- Senha: ``admin@123``

**Próximos passos após instalação:**

1. Revisar ``config/ptah.php``
2. Adicionar trait ``HasUserPreferences`` no User model
3. Habilitar módulos necessários:
   - ``php artisan ptah:module auth`` — Login, 2FA, profile
   - ``php artisan ptah:module menu`` — Sidebar dinâmico
   - ``php artisan ptah:module company`` — Multi-empresa
   - ``php artisan ptah:module permissions`` — RBAC
4. Fazer login com credenciais padrão
5. Scaffoldar entidades com ``php artisan ptah:forge {Entity}``

---

## ptah:forge

**Descrição:** Gera estrutura completa para uma entidade (scaffolding SOLID).

**Uso:**
```bash
# Básico
php artisan ptah:forge Product

# Com subdirectory (Product/ProductStock)
php artisan ptah:forge Product/ProductStock

# Especificar tabela customizada
php artisan ptah:forge Product --table=custom_products

# Definir campos manualmente
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2):nullable,status:enum(active|inactive)"

# Ler campos do banco de dados
php artisan ptah:forge Product --db

# Gerar Web + API juntos
php artisan ptah:forge Product --api

# Gerar APENAS API (sem views web)
php artisan ptah:forge Product --api-only

# Sem soft deletes
php artisan ptah:forge Product --no-soft-deletes

# Sobrescrever arquivos existentes
php artisan ptah:forge Product --force
```

**Opções:**
- ``--table=`` — Nome da tabela no banco (padrão: plural snake_case da entidade)
- ``--fields=`` — Definição de campos: ``"campo:tipo:modificadores"``
- ``--db`` — Ler campos diretamente do banco (via INFORMATION_SCHEMA)
- ``--api`` — Gera web + API juntos (Controller API, Requests API, Swagger, rotas v1)
- ``--api-only`` — Gera APENAS API, sem views web (Livewire não criado)
- ``--no-soft-deletes`` — Não adiciona SoftDeletes ao model
- ``--force`` — Sobrescreve arquivos existentes sem confirmação

**Arquivos gerados:**

### Modo Web (padrão)
| Tipo | Caminho | Descrição |
|------|---------|-----------|
| Model | ``app/Models/{Entity}.php`` | Eloquent model com SoftDeletes |
| Migration | ``database/migrations/{timestamp}_create_{entities}_table.php`` | Schema da tabela |
| DTO | ``app/DTOs/{Entity}DTO.php`` | Data Transfer Object |
| Repository Interface | ``app/Repositories/Contracts/{Entity}RepositoryInterface.php`` | Contrato do repositório |
| Repository | ``app/Repositories/{Entity}Repository.php`` | Implementação do repositório |
| Service | ``app/Services/{Entity}Service.php`` | Lógica de negócio |
| Controller | ``app/Http/Controllers/{Entity}Controller.php`` | Controller web (BaseCrud Livewire) |
| Request Store | ``app/Http/Requests/Store{Entity}Request.php`` | Validação de criação |
| Request Update | ``app/Http/Requests/Update{Entity}Request.php`` | Validação de atualização |
| Resource | ``app/Http/Resources/{Entity}Resource.php`` | API Resource (usado também no web) |
| View | ``resources/views/{entity}/index.blade.php`` | View index com BaseCrud |
| Route | ``routes/web.php`` | Rota web para o CRUD |
| CrudConfig | ``crud_configs`` table | Configuração JSON do BaseCrud |
| Binding | ``app/Providers/AppServiceProvider.php`` | Repository binding injetado |

### Modo API (--api ou --api-only)
| Tipo | Caminho | Descrição |
|------|---------|-----------|
| Controller API | ``app/Http/Controllers/API/{Entity}Controller.php`` | Controller API com Swagger annotations |
| Request Create API | ``app/Http/Requests/Create{Entity}Request.php`` | Validação API de criação |
| Request Update API | ``app/Http/Requests/Update{Entity}Request.php`` | Validação API de atualização |
| Route API | ``routes/api.php`` | Rotas v1 API |

> **Nota:** Com ``--api``, gera **web + API**. Com ``--api-only``, gera **apenas API** (sem views, sem Livewire).

**Sintaxe de fields:**

```bash
--fields="campo1:tipo:modificador1:modificador2,campo2:tipo"
```

**Tipos disponíveis:**
- ``string``, ``text``, ``integer``, ``bigInteger``, ``unsignedBigInteger``
- ``decimal(10,2)``, ``float``, ``double``
- ``boolean``, ``date``, ``datetime``, ``timestamp``
- ``enum(value1|value2|value3)``
- ``json``, ``jsonb``

**Modificadores:**
- ``nullable`` — Permite NULL
- ``unique`` — Índice único
- ``index`` — Índice simples
- ``default(valor)`` — Valor padrão

**Exemplos:**

```bash
# E-commerce básico
php artisan ptah:forge Product --fields="name:string,description:text:nullable,price:decimal(10,2),stock:integer:default(0),is_active:boolean:default(true)"

# Foreign key
php artisan ptah:forge ProductStock --fields="product_id:unsignedBigInteger:index,quantity:decimal(12,3),location:string:nullable"

# Enum com nullable
php artisan ptah:forge Order --fields="status:enum(pending|processing|shipped|delivered):default(pending),total:decimal(10,2)"

# Ler do banco existente
php artisan ptah:forge Customer --db --table=customers

# Gerar API completa sem web
php artisan ptah:forge Product --api-only --fields="name:string,price:decimal(10,2)"
```

**Próximos passos após scaffold:**

1. Executar migration: ``php artisan migrate``
2. Ajustar configuração JSON do CRUD na tabela ``crud_configs``
3. Implementar regras de negócio no ``{Entity}Service.php``
4. Adicionar validações customizadas nos Requests
5. Escrever testes em ``tests/Feature/{Entity}Test.php``

---

## ptah:module

**Descrição:** Habilita módulos opcionais do Ptah.

**Uso:**
```bash
# Interativo (menu de escolha)
php artisan ptah:module

# Direto
php artisan ptah:module auth
php artisan ptah:module menu
php artisan ptah:module company
php artisan ptah:module permissions
php artisan ptah:module api

# Listar módulos disponíveis e estados
php artisan ptah:module --list

# Forçar sobrescrita
php artisan ptah:module auth --force
```

**Opções:**
- ``--list`` — Lista módulos disponíveis e seus estados (habilitado/desabilitado)
- ``--force`` — Sobrescreve arquivos existentes ao publicar

**Módulos disponíveis:**

### 1. auth
**O que faz:**
- Publica migration de 2FA
- Executa migrations
- Ativa autenticação com login, recuperação de senha, 2FA (TOTP e E-mail)

**ENV:**
```env
PTAH_MODULE_AUTH=true
```

**Rotas criadas:**
- ``/auth/login`` — Login
- ``/auth/forgot-password`` — Recuperação de senha
- ``/auth/reset-password/{token}`` — Redefinir senha
- ``/auth/two-factor-challenge`` — Verificação 2FA
- ``/auth/profile`` — Perfil do usuário

**Arquivos publicados:**
- ``database/migrations/*_add_two_factor_fields_to_users_table.php``

---

### 2. menu
**O que faz:**
- Publica migration de menus
- Executa migrations
- Ativa sidebar dinâmico (menu configurável via banco)

**ENV:**
```env
PTAH_MODULE_MENU=true
```

**Tabela criada:**
- ``ptah_menu_items`` — Itens do menu hierárquico

**Componentes:**
- Livewire: ``MenuList`` — Gerenciamento de itens do menu
- Blade component: ``<x-ptah::menu />`` — Renderiza o menu na sidebar

---

### 3. company
**O que faz:**
- Publica migrations de empresas
- Executa migrations
- Seeders empresa padrão
- Ativa sistema multi-empresa (multi-tenancy)

**ENV:**
```env
PTAH_MODULE_COMPANY=true
```

**Tabelas criadas:**
- ``ptah_companies`` — Empresas
- ``ptah_company_user`` — Pivot usuário-empresa

**Componentes:**
- Livewire: ``CompanyList`` — Gerenciamento de empresas
- Livewire: ``CompanySwitcher`` — Troca de empresa ativa (dropdown no header)

**Arquivos publicados:**
- ``database/migrations/*_create_ptah_companies_table.php``
- ``database/migrations/*_create_ptah_company_user_table.php``

---

### 4. permissions
**O que faz:**
- Publica migrations de permissões
- Executa migrations
- Seed admin padrão com role MASTER
- Ativa RBAC (Role-Based Access Control)

**Dependência:** Requer módulo ``company`` habilitado (ativa automaticamente se não estiver)

**ENV:**
```env
PTAH_MODULE_PERMISSIONS=true
```

**Tabelas criadas:**
- ``ptah_roles`` — Perfis de acesso
- ``ptah_role_user`` — Pivot usuário-role
- ``ptah_departments`` — Departamentos
- ``ptah_pages`` — Páginas/objetos do sistema
- ``ptah_page_role`` — Permissões (CRUD por página e role)
- ``ptah_user_permissions`` — Permissões específicas de usuário
- ``ptah_audit_logs`` — Logs de auditoria

**Credenciais admin:**
- E-mail: ``admin@admin.com`` (configurável em ``config/ptah.php``)
- Senha: ``admin@123`` (configurável em ``config/ptah.php``)
- Role: MASTER (todas as permissões)

**Componentes:**
- Livewire: ``RoleList`` — Gerenciamento de roles
- Livewire: ``DepartmentList`` — Gerenciamento de departamentos
- Livewire: ``PageList`` — Gerenciamento de páginas
- Livewire: ``UserPermissionList`` — Permissões por usuário
- Livewire: ``AuditList`` — Logs de auditoria
- Livewire: ``PermissionGuide`` — Guia interativo de permissões

**Helpers:**
- ``ptah_can($page, $action, $user, $companyId)`` — Verifica permissão
- ``ptah_is_master($user)`` — Verifica se é MASTER
- ``@ptahCan('sales', 'create')`` — Blade directive
- ``@ptahMaster`` — Blade directive

**Arquivos publicados:**
- ``database/migrations/*_create_ptah_permissions_tables.php``

---

### 5. api
**O que faz:**
- Instala ``darkaonline/l5-swagger`` via Composer
- Publica classes base da API
- Publica configuração do L5-Swagger
- Configura Swagger UI em ``/api/documentation``

**ENV:**
```env
PTAH_MODULE_API=true
```

**Arquivos publicados:**
- ``app/Responses/BaseResponse.php`` — Resposta padronizada da API
- ``app/Http/Controllers/API/BaseApiController.php`` — Controller base com helpers
- ``app/Http/Controllers/API/SwaggerInfo.php`` — Anotações ``@OA\Info`` do Swagger
- ``config/l5-swagger.php`` — Configuração do L5-Swagger

**Rotas criadas:**
- ``GET /api/documentation`` — Swagger UI interativo
- ``GET /api/documentation.json`` — Especificação OpenAPI

**Uso após instalação:**

```bash
# Gerar documentação Swagger
php artisan l5-swagger:generate

# Acessar UI
http://localhost/api/documentation
```

**Next steps:**
1. Visite ``/api/documentation`` para ver a UI do Swagger
2. Regenere docs após criar APIs: ``php artisan l5-swagger:generate``
3. Ajuste scan path em ``config/l5-swagger.php`` se necessário

---

## ptah:config

**Descrição:** Configura CRUD settings de uma model via linha de comando (alternativa ao modal visual).

> 📘 **Documentação Completa:** Para guia detalhado de configuração (modal visual + CLI), exemplos práticos, comparações e troubleshooting, consulte [**Configuration.md**](Configuration.md).

**Uso:**
```bash
# Interactive mode (wizard com perguntas)
php artisan ptah:config "App\Models\Product"

# Declarative mode (inline syntax)
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name:validation=required|max:255" \
  --column="price:number:required:label=Price:mask=money_brl:renderer=money" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red" \
  --action="approve:livewire:approve(%id%):icon=bx-check:color=success" \
  --filter="status:select:=:options=active,inactive" \
  --set="cacheEnabled=true" \
  --set="itemsPerPage=25"

# List current configuration
php artisan ptah:config "App\Models\Product" --list

# Reset configuration to defaults
php artisan ptah:config "App\Models\Product" --reset

# Import from JSON file
php artisan ptah:config "App\Models\Product" --import=config.json

# Export to JSON file
php artisan ptah:config "App\Models\Product" --export=product-config.json

# Non-interactive mode (skip wizard)
php artisan ptah:config "App\Models\Product" --non-interactive \
  --column="name:text:required"

# Dry-run (show changes without saving)
php artisan ptah:config "App\Models\Product" \
  --column="name:text" \
  --dry-run

# Process only specific sections
php artisan ptah:config "App\Models\Product" \
  --only=columns,actions \
  --column="name:text"

# Skip specific sections
php artisan ptah:config "App\Models\Product" \
  --skip=styles,joins \
  --column="name:text"

# Force overwrite existing config
php artisan ptah:config "App\Models\Product" \
  --column="name:text" \
  --force
```

**Opções:**
- ``{model}`` — Full model class name (e.g., ``App\Models\Product``)
- ``--column=*`` — Add/update column: ``field:type:modifier:option=value``
- ``--action=*`` — Add custom action: ``name:type:value:icon=icon:color=color``
- ``--filter=*`` — Add custom filter: ``field:type:operator:label=Label``
- ``--style=*`` — Add style rule: ``field:operator:value:css``
- ``--join=*`` — Add table join: ``type:table:on:select=field1,field2``
- ``--set=*`` — Set general config: ``key=value``
- ``--permission=*`` — Set permission: ``action=permission``
- ``--list`` — List current configuration (beautiful table format)
- ``--reset`` — Reset configuration to defaults
- ``--import=`` — Import configuration from JSON file
- ``--export=`` — Export configuration to JSON file
- ``--non-interactive`` — Skip wizard questions, use only provided options
- ``--force`` — Force overwrite existing configuration
- ``--dry-run`` — Show what would be changed without saving
- ``--only=*`` — Process only specific sections (columns,actions,filters,styles,joins,general,permissions)
- ``--skip=*`` — Skip specific sections

**Column Syntax (--column):**

```bash
# Basic format
field:type:modifier:option=value

# Examples
name:text:required:label=Name
email:text:required:validation=email|max:255
price:number:label=Price:mask=money_brl:renderer=money:rendererDecimals=2
status:select:options=active,inactive:renderer=badge:badges=active:green,inactive:red
user_id:searchdropdown:relation=user:sdSelectColumn=name:sdValueColumn=id
description:textarea:optional:placeholder=Enter description
active:boolean:default=true
created_at:datetime:readonly:renderer=datetime:rendererFormat=d/m/Y H:i:s

# Modifiers (shorthands)
required    → colsRequired = true
optional    → colsRequired = false
readonly    → colsEditableForm = false
hidden      → colsVisibleList = false
noFilter    → colsIsFilterable = false
noSave      → colsGravar = false
total       → colsTotal = true (add to totalizer)

# Column options (option=value)
label           → colsNomeLogico (display label)
help            → colsHelpText (help text below field)
placeholder     → colsPlaceholder
default         → colsDefaultValue
align           → colsAlign (text-start, text-center, text-end)
width           → colsWidth (120px, 20%, auto)
renderer        → colsRenderer (text, badge, pill, boolean, money, date, datetime, link, image, etc.)
rendererLink    → colsRendererLink (URL pattern for link renderer)
rendererTarget  → colsRendererTarget (_self, _blank)
rendererCurrency→ colsRendererCurrency (BRL, USD, EUR)
rendererDecimals→ colsRendererDecimals (2, 0)
rendererPrefix  → colsRendererPrefix (prefix for number renderer)
rendererSuffix  → colsRendererSuffix (suffix for number renderer)
badges          → colsRendererBadges (value:color pairs, e.g., active:green,inactive:red)
mask            → colsMask (money_brl, cpf, cnpj, phone, cep, date, etc.)
maskTransform   → colsMaskTransform (money_to_float, digits_only, etc.)
validation      → colsValidation (Laravel validation rules: required|email|max:255)
options         → colsOptions (for select: value1:Label1,value2:Label2 or value1,value2)
relation        → colsRelation (relation method name)
sdTable         → colsSdTable (table for searchdropdown)
sdSelectColumn  → colsSdSelectColumn (display column for searchdropdown)
sdValueColumn   → colsSdValueColumn (value column for searchdropdown)
uploadPath      → colsUploadPath (path for file uploads)
totalizer       → colsTotal (add to totalizer)
totalizadorType → totalizadorType (sum, avg, count, min, max)
```

**Action Syntax (--action):**

```bash
# Format
name:type:value:icon=icon:color=color

# Examples
approve:livewire:approve(%id%):icon=bx-check:color=success
reject:livewire:reject(%id%):icon=bx-x:color=danger
view:link:https://example.com/view/%id%:icon=bx-show:color=primary
export:javascript:exportData():icon=bx-download:color=info
```

**Filter Syntax (--filter):**

```bash
# Format
field:type:operator:label=Label

# Examples
status:select:=:label=Status:options=active,inactive
price:number:>=:label=Minimum Price
created_at:date:>=:label=From Date
user_id:searchdropdown:=:sdTable=users:sdSelectColumn=name
```

**Style Syntax (--style):**

```bash
# Format
field:operator:value:background=color:color=textColor

# Examples
status:==:cancelled:background=#FEE:color=#C00
priority:>:5:background=#FFE:fontWeight=bold
```

**Join Syntax (--join):**

```bash
# Format
type:table:leftColumn=rightColumn:select=field1,field2

# Examples
left:users:products.user_id=users.id:select=name,email
inner:categories:products.category_id=categories.id:select=name
```

**General Settings (--set):**

```bash
# Examples
--set="cacheEnabled=true"
--set="cacheTime=60"
--set="paginationEnabled=true"
--set="itemsPerPage=25"
--set="searchEnabled=true"
--set="exportEnabled=true"
--set="softDeletes=true"
--set="theme=dark"
--set="compactMode=false"
```

**Permissions (--permission):**

```bash
# Examples
--permission="list=product.index"
--permission="create=product.create"
--permission="edit=product.update"
--permission="delete=product.destroy"
```

**Workflow Examples:**

```bash
# 1. Interactive wizard (recommended for first-time config)
php artisan ptah:config "App\Models\Product"
# Answer questions step-by-step with smart suggestions

# 2. Quick declarative setup
php artisan ptah:config "App\Models\Product" \
  --column="name:text:required:label=Product Name" \
  --column="sku:text:required:label=SKU:validation=required|unique:products,sku" \
  --column="price:number:required:mask=money_brl:renderer=money" \
  --column="stock:number:label=Stock:renderer=number:rendererDecimals=0" \
  --column="status:select:options=active:Active,inactive:Inactive:renderer=badge:badges=active:green,inactive:red" \
  --column="category_id:searchdropdown:relation=category:sdSelectColumn=name" \
  --set="itemsPerPage=25" \
  --set="cacheEnabled=true"

# 3. View current config
php artisan ptah:config "App\Models\Product" --list

# 4. Add more columns later
php artisan ptah:config "App\Models\Product" \
  --column="description:textarea:optional" \
  --column="image:file:uploadPath=products"

# 5. Export for backup or sharing
php artisan ptah:config "App\Models\Product" --export=product-config.json

# 6. Import in another environment
php artisan ptah:config "App\Models\Product" --import=product-config.json

# 7. Reset to defaults
php artisan ptah:config "App\Models\Product" --reset
```

**Benefits of CLI Configuration:**

✅ **Automation** — Integrate with CI/CD pipelines  
✅ **Version Control** — Export configs to JSON and commit  
✅ **Batch Operations** — Configure multiple models via scripts  
✅ **Reproducibility** — Share configs across teams/environments  
✅ **Speed** — Faster than clicking through modal UI  
✅ **Testability** — Script config changes with --dry-run  
✅ **Introspection** — Smart suggestions based on model metadata  

**Where configs are stored:**

Configurations are saved in ``crud_configs`` table:
- ``model`` — Full model class name
- ``config`` — JSON configuration
- ``updated_at`` — Last modified timestamp

Cache is automatically cleared after saving.

**Next Steps:**

1. Configure your first model: ``php artisan ptah:config "App\Models\YourModel"``
2. View configuration: ``php artisan ptah:config "App\Models\YourModel" --list``
3. Refresh browser to see changes in CRUD interface
4. Export for backup: ``php artisan ptah:config "App\Models\YourModel" --export=backup.json``

---

## Ordem recomendada de instalação

```bash
# 1. Instalar pacote básico
composer require jonytonet/ptah
php artisan ptah:install

# 2. Habilitar módulos necessários
php artisan ptah:module company
php artisan ptah:module permissions
php artisan ptah:module auth
php artisan ptah:module menu

# 3. (Opcional) Habilitar módulo API
php artisan ptah:module api

# 4. (Opcional) Demo data para explorar
php artisan ptah:install --demo

# 5. Scaffoldar primeira entidade
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"

# 6. Executar migration
php artisan migrate

# 7. Acessar sistema
# http://localhost/products
```

---

## Dicas de uso

### Scaffolding incremental

```bash
# Web primeiro
php artisan ptah:forge Product

# Depois adicionar API (não sobrescreve arquivos existentes)
php artisan ptah:forge Product --api --force
```

### Subpastas (organização)

```bash
# Estrutura: Purchase/Order, Purchase/OrderItem
php artisan ptah:forge Purchase/Order
php artisan ptah:forge Purchase/OrderItem

# Resultado:
# app/Models/Purchase/Order.php
# app/Services/Purchase/OrderService.php
# resources/views/purchase/order/index.blade.php
```

### Leitura do banco existente

```bash
# Se a tabela já existe no banco
php artisan ptah:forge Customer --db --table=customers
```

Isso inspeciona a estrutura via ``INFORMATION_SCHEMA`` e gera Models/DTOs/Migrations compatíveis.

---

## Troubleshooting

### Erro: "Model not found"
**Causa:** Binding do repositório não registrado.

**Solução:** Adicione no ``AppServiceProvider::boot()``:
```php
$this->app->bind(
    \App\Repositories\Contracts\ProductRepositoryInterface::class,
    \App\Repositories\ProductRepository::class
);
```

### Erro: "Class not found" após scaffold
**Causa:** Autoload não atualizado.

**Solução:**
```bash
composer dump-autoload
```

### Erro: npm/yarn não encontrado
**Causa:** Node.js não instalado ou não no PATH.

**Solução:**
1. Instale Node.js: https://nodejs.org
2. Ou use ``--skip-npm`` e rode manualmente depois:
```bash
npm install
npm run build
```

### Migrations duplicadas
**Causa:** Re-execução do ``ptah:install`` ou ``ptah:forge``.

**Solução:** Use ``--force`` apenas quando realmente quiser sobrescrever. Para módulos, verifique com ``ptah:module --list`` antes.

---

## Histórico de comandos

### Comandos removidos (V2.2+)

Estes comandos foram descontinuados e substituídos por ``ptah:forge``:

| Comando removido | Substituição |
|------------------|--------------|
| ``ptah:make-api {Entity}`` | ``ptah:forge {Entity} --api-only`` |
| ``ptah:docs {Entity}`` | Swagger gerado automaticamente via ``ptah:forge --api`` |

**Migração:**

```bash
# ❌ Antes (V2.1)
php artisan ptah:make Product        # Web
php artisan ptah:make-api Product    # API
php artisan ptah:docs Product        # Swagger manual

# ✅ Agora (V2.2+)
php artisan ptah:forge Product              # Web
php artisan ptah:forge Product --api        # Web + API
php artisan ptah:forge Product --api-only   # Só API
# Swagger gerado automaticamente
```

---

## Performance

### Comando lento: ptah:install --boost
**Causa:** ``composer require laravel/boost`` pode demorar 1-2 minutos.

**Solução:** Isso é normal. Laravel Boost instala dependências pesadas (AST parsers). Use ``--skip-npm`` para pular Node se já tiver assets buildados.

### Comando lento: ptah:forge --db
**Causa:** Consulta INFORMATION_SCHEMA pode ser lenta em bancos grandes.

**Solução:** Use ``--fields`` manual para tabelas conhecidas:
```bash
php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"
```

---

## Referências

- [InstallationGuide.md](InstallationGuide.md) — Guia completo de instalação
- [BaseCrud.md](BaseCrud.md) — Referência do BaseCrud
- [Modules.md](Modules.md) — Detalhes dos módulos
- [AI_Guide.md](AI_Guide.md) — Prompts para agentes de IA
- [Permissions.md](Permissions.md) — Sistema RBAC detalhado

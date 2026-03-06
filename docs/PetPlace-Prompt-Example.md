# 🐾 PetPlace — Exemplo de Prompt para IA (Ptah)

> **Exemplo prático para IA agents** — Versão enxuta focada em scaffolding + validação
> 
> **Atualizado:** 5 de março de 2026  
> **Changelog:** Refatorado para formato minimalista (regras avançadas movidas para Boost)

> 📊 **Documentação complementar:**  
> - [`Configuration.md`](Configuration.md) — Guia completo de configuração do BaseCrud  
> - [`ProductHooks.example.php`](ProductHooks.example.php) — Template de Lifecycle Hooks  
> - [`../../PTAH_ANALISE_E_ROADMAP.md`](../../PTAH_ANALISE_E_ROADMAP.md) — Análise técnica + roadmap

---

## 📋 Contexto do Projeto

**PetPlace** é um portal completo para petshops com:
- **Admin/Back-office**: produtos, serviços, agendamentos, estoque, clientes, pedidos
- **Marketplace**: catálogo público, carrinho, checkout, portal do tutor

**Stack:**
- Laravel 12, PHP 8.3, Livewire 4, Tailwind CSS v4, Alpine.js 3
- Pacote: `jonytonet/ptah` (scaffolding SOLID + BaseCrud dinâmico)

---

## 🚀 Instalação Inicial

```bash
# 1. Criar projeto Laravel
composer create-project laravel/laravel petplace
cd petplace

# 2. Configurar .env
APP_NAME=PetPlace
APP_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo
PTAH_LOCALE=pt_BR
DB_CONNECTION=mysql  # ou sqlite para testes

# 3. Instalar ptah (local ou GitHub)
# Adicionar ao composer.json:
# "repositories": [{"type": "path", "url": "../composer_project/ptah", "options": {"symlink": true}}]
composer require jonytonet/ptah:@dev

# 4. Executar instalador
php artisan ptah:install  # Responda YES quando perguntar sobre migrations

# 5. Ativar módulos (nessa ordem)
php artisan ptah:module auth
php artisan ptah:module menu
php artisan ptah:module company
php artisan ptah:module permissions
php artisan ptah:module api

# 6. Limpar cache
php artisan config:clear

# 7. Instalar Laravel Boost (regras de arquitetura + performance)
php artisan ptah:install --boost

# 8. Rodar migrations
php artisan migrate

# 9. Iniciar servidor
php artisan serve
npm run dev  # outro terminal
```

**Login padrão:** `admin@admin.com` / `admin@123`

---

## 🏗️ Scaffolding das Entidades

Execute os comandos abaixo em sequência. O ptah gera:
- Model, Migration, DTO, Repository, Service, Controller, Requests, Views, Routes
- **Menu automático**: cada entidade adiciona link na sidebar (MenuRegistry.php)

```bash
# === CATÁLOGO ===
php artisan ptah:forge Catalog/Category \
  --fields="name:string:surname=Categoria,slug:string,description:text:nullable:surname=Descricao,parent_id:unsignedBigInteger:nullable,is_active:boolean:surname=Ativo" \
  --api

php artisan ptah:forge Catalog/Brand \
  --fields="name:string:surname=Marca,slug:string,logo_url:string:nullable:surname=Logo,is_active:boolean:surname=Ativo" \
  --api

php artisan ptah:forge Catalog/Product \
  --fields="name:string:surname=Nome,sku:string:unique:surname=SKU,description:text:nullable:surname=Descricao,price:decimal(10,2):surname=Preco,sale_price:decimal(10,2):nullable:surname=Preco Promo,stock:integer:surname=Estoque,weight:decimal(10,3):nullable:surname=Peso (kg),category_id:unsignedBigInteger,brand_id:unsignedBigInteger,is_active:boolean:surname=Ativo,is_featured:boolean:surname=Destaque" \
  --api

# === REFERÊNCIA VETERINÁRIA ===
php artisan ptah:forge Reference/AnimalSpecies \
  --fields="name:string:surname=Especie,slug:string,is_active:boolean:surname=Ativo,sort_order:integer:surname=Ordem" \
  --api

php artisan ptah:forge Reference/AnimalBreed \
  --fields="name:string:surname=Raca,slug:string,species_id:unsignedBigInteger,size:string:surname=Porte,origin:string:nullable:surname=Origem,is_active:boolean:surname=Ativo,sort_order:integer:surname=Ordem" \
  --api

# === SERVIÇOS ===
php artisan ptah:forge Scheduling/ServiceCategory \
  --fields="name:string:surname=Categoria,slug:string,description:text:nullable:surname=Descricao,is_active:boolean:surname=Ativo,sort_order:integer:surname=Ordem" \
  --api

php artisan ptah:forge Scheduling/Service \
  --fields="service_category_id:unsignedBigInteger,name:string:surname=Servico,slug:string,description:text:nullable:surname=Descricao,price:decimal(10,2):surname=Preco,duration_minutes:integer:surname=Duracao (min),type:string:surname=Tipo,requirements:text:nullable:surname=Requisitos,requires_appointment:boolean:surname=Exige Agendamento,is_active:boolean:surname=Ativo,sort_order:integer:surname=Ordem" \
  --api

php artisan ptah:forge Scheduling/Employee \
  --fields="user_id:unsignedBigInteger,employee_code:string:unique:surname=Matricula,role:string:surname=Funcao,specialties:json:surname=Especialidades,service_categories:json:surname=Categorias,hourly_rate:decimal(10,2):surname=Valor/Hora,max_simultaneous_services:integer:surname=Max. Simultaneos,is_active:boolean:surname=Ativo,notes:text:nullable:surname=Observacoes"

# === AGENDAMENTOS ===
php artisan ptah:forge Scheduling/Appointment \
  --fields="client_id:unsignedBigInteger,pet_id:unsignedBigInteger,service_id:unsignedBigInteger,employee_id:unsignedBigInteger,scheduled_at:datetime:surname=Data/Hora,status:string:surname=Status,notes:text:nullable:surname=Observacoes" \
  --api

# === CLIENTES & PETS ===
php artisan ptah:forge Clients/Client \
  --fields="user_id:unsignedBigInteger,phone:string:surname=Telefone,whatsapp:string:nullable:surname=WhatsApp,cpf:string:unique:surname=CPF,birth_date:date:surname=Nascimento,gender:string:surname=Genero,marital_status:string:nullable:surname=Estado Civil,profession:string:nullable:surname=Profissao,observations:text:nullable:surname=Observacoes,accepts_promotions:boolean:surname=Aceita Promocoes,accepts_reminders:boolean:surname=Aceita Lembretes,is_active:boolean:surname=Ativo"

php artisan ptah:forge Clients/ClientAddress \
  --fields="client_id:unsignedBigInteger,type:string:surname=Tipo,label:string:surname=Identificacao,street:string:surname=Rua,number:string:surname=Numero,complement:string:nullable:surname=Complemento,neighborhood:string:surname=Bairro,city:string:surname=Cidade,state:string:surname=Estado,zip_code:string:surname=CEP,reference:string:nullable:surname=Referencia,is_primary:boolean:surname=Principal,is_active:boolean:surname=Ativo"

php artisan ptah:forge Clients/ClientContact \
  --fields="client_id:unsignedBigInteger,name:string:surname=Nome,relationship:string:surname=Parentesco,phone:string:surname=Telefone,whatsapp:string:nullable:surname=WhatsApp,email:string:nullable:surname=E-mail,is_emergency:boolean:surname=Emergencia,is_authorized:boolean:surname=Autorizado,observations:text:nullable:surname=Observacoes,is_active:boolean:surname=Ativo"

php artisan ptah:forge Clients/Pet \
  --fields="client_id:unsignedBigInteger,user_id:unsignedBigInteger:nullable,species_id:unsignedBigInteger,breed_id:unsignedBigInteger,name:string:surname=Nome,gender:string:surname=Sexo,birth_date:date:surname=Nascimento,weight:decimal(5,2):nullable:surname=Peso (kg),color:string:surname=Pelagem/Cor,microchip:string:nullable:surname=Microchip,registration_number:string:nullable:surname=Registro,is_neutered:boolean:surname=Castrado,health_conditions:text:nullable:surname=Condicoes de Saude,allergies:text:nullable:surname=Alergias,medications:text:nullable:surname=Medicamentos,special_care:text:nullable:surname=Cuidados Especiais,observations:text:nullable:surname=Observacoes,photo_path:string:nullable:surname=Foto,is_active:boolean:surname=Ativo"

# === SAÚDE (SEM SOFT DELETE) ===
php artisan ptah:forge Health/VaccinationType \
  --fields="name:string:surname=Vacina,slug:string,description:text:nullable:surname=Descricao,category:string:surname=Categoria,interval_months:integer:nullable:surname=Intervalo (meses),first_dose_age_weeks:integer:nullable:surname=1a Dose (semanas),requires_annual_booster:boolean:surname=Reforco Anual,is_mandatory:boolean:surname=Obrigatoria,is_active:boolean:surname=Ativa,sort_order:integer:surname=Ordem"

php artisan ptah:forge Health/PetVaccination \
  --fields="pet_id:unsignedBigInteger,vaccination_type_id:unsignedBigInteger,application_date:date:surname=Data Aplicacao,next_due_date:date:nullable:surname=Proxima Dose,batch_number:string:nullable:surname=Lote,manufacturer:string:nullable:surname=Fabricante,veterinarian_name:string:nullable:surname=Veterinario,veterinarian_crmv:string:nullable:surname=CRMV,weight_at_application:decimal(5,2):nullable:surname=Peso na Aplicacao,notes:text:nullable:surname=Observacoes,adverse_reactions:text:nullable:surname=Reacoes Adversas,certificate_path:string:nullable:surname=Certificado" \
  --no-soft-deletes

php artisan ptah:forge Health/PetMedicalRecord \
  --fields="pet_id:unsignedBigInteger,consultation_date:date:surname=Data,consultation_time:string:nullable:surname=Hora,type:string:surname=Tipo,veterinarian_name:string:surname=Veterinario,veterinarian_crmv:string:nullable:surname=CRMV,weight:decimal(5,2):nullable:surname=Peso (kg),temperature:decimal(4,1):nullable:surname=Temperatura,symptoms:text:nullable:surname=Sintomas,physical_exam:text:nullable:surname=Exame Fisico,diagnosis:text:nullable:surname=Diagnostico,treatment:text:nullable:surname=Tratamento,medications:text:nullable:surname=Medicamentos,recommendations:text:nullable:surname=Recomendacoes,return_date:date:nullable:surname=Retorno,consultation_fee:decimal(10,2):nullable:surname=Valor,attachments:json:nullable:surname=Anexos" \
  --no-soft-deletes

php artisan ptah:forge Health/PetServiceHistory \
  --fields="pet_id:unsignedBigInteger,service_id:unsignedBigInteger,service_date:date:surname=Data,service_time:string:nullable:surname=Hora,price_paid:decimal(10,2):surname=Valor Pago,status:string:surname=Status,notes:text:nullable:surname=Observacoes,performed_by_id:unsignedBigInteger,before_photos:json:nullable:surname=Fotos Antes,after_photos:json:nullable:surname=Fotos Depois" \
  --no-soft-deletes

# === PEDIDOS ===
php artisan ptah:forge Orders/Order \
  --fields="client_id:unsignedBigInteger,status:string:surname=Status,subtotal:decimal(10,2):surname=Subtotal,discount:decimal(10,2):surname=Desconto,shipping:decimal(10,2):surname=Frete,total:decimal(10,2):surname=Total,notes:text:nullable:surname=Observacoes" \
  --api

php artisan ptah:forge Orders/OrderItem \
  --fields="order_id:unsignedBigInteger,product_id:unsignedBigInteger,qty:integer:surname=Qtd,unit_price:decimal(10,2):surname=Preco Unit.,total:decimal(10,2):surname=Total" \
  --no-soft-deletes

# === ESTOQUE ===
php artisan ptah:forge Inventory/StockMovement \
  --fields="product_id:unsignedBigInteger,type:string:surname=Tipo,qty:integer:surname=Quantidade,reason:string:surname=Motivo,user_id:unsignedBigInteger" \
  --no-soft-deletes

# === FINANCEIRO ===
php artisan ptah:forge Financial/Receivable \
  --fields="order_id:unsignedBigInteger,amount:decimal(10,2):surname=Valor,due_date:date:surname=Vencimento,paid_at:datetime:nullable:surname=Pago em,status:string:surname=Status"

# === FINALIZAR ===
php artisan migrate
php artisan ptah:menu-sync --fresh  # Sincroniza menu da sidebar
```

---

## ✅ Pós-Scaffolding: Resolver TODOs nos Models

Após gerar todas as entidades, os Models contêm TODOs marcando relacionamentos.

### Exemplo: `app/Models/Product.php`

```php
// TODO: Uncomment and configure relationships
// public function category(): BelongsTo
// {
//     return $this->belongsTo(Category::class);
// }
//
// public function brand(): BelongsTo
// {
//     return $this->belongsTo(Brand::class);
// }
```

### Tarefas Obrigatórias

1. **Descomentar relacionamentos** nos models gerados
2. **Adicionar imports** necessários (`BelongsTo`, `HasMany`, etc.)
3. **Configurar chaves estrangeiras** se não seguirem convenção Laravel

### Lista de Relacionamentos por Entidade

| Model | Relacionamentos a Configurar |
|-------|------------------------------|
| **Product** | `belongsTo(Category)`, `belongsTo(Brand)` |
| **AnimalBreed** | `belongsTo(AnimalSpecies)` |
| **Service** | `belongsTo(ServiceCategory)` |
| **Employee** | `belongsTo(User)` |
| **Appointment** | `belongsTo(Client)`, `belongsTo(Pet)`, `belongsTo(Service)`, `belongsTo(Employee)` |
| **Client** | `belongsTo(User)`, `hasMany(ClientAddress)`, `hasMany(ClientContact)`, `hasMany(Pet)` |
| **ClientAddress** | `belongsTo(Client)` |
| **ClientContact** | `belongsTo(Client)` |
| **Pet** | `belongsTo(Client)`, `belongsTo(User)`, `belongsTo(AnimalSpecies)`, `belongsTo(AnimalBreed)`, `hasMany(PetVaccination)`, `hasMany(PetMedicalRecord)`, `hasMany(PetServiceHistory)` |
| **PetVaccination** | `belongsTo(Pet)`, `belongsTo(VaccinationType)` |
| **PetMedicalRecord** | `belongsTo(Pet)` |
| **PetServiceHistory** | `belongsTo(Pet)`, `belongsTo(Service)`, `belongsTo(Employee, 'performed_by_id')` |
| **Order** | `belongsTo(Client)`, `hasMany(OrderItem)` |
| **OrderItem** | `belongsTo(Order)`, `belongsTo(Product)` |
| **StockMovement** | `belongsTo(Product)`, `belongsTo(User)` |
| **Receivable** | `belongsTo(Order)` |

---

## 🎨 Configuração Visual do BaseCrud (CLI)

Após scaffolding, configure os CRUDs via linha de comando usando `ptah:config`:

### Sintaxe Básica

```bash
php artisan ptah:config "App\Models\Product" \
  --column="name:text:label=Nome:sortable=true" \
  --column="status:badge:label=Status:badgeMap=active:success:Ativo,inactive:danger:Inativo" \
  --column="price:money:label=Preço:sortable=true" \
  --column="created_at:date:label=Criado em:sortable=true"
```

### Exemplos Práticos por Entidade

#### 1. Product (Catálogo)

```bash
php artisan ptah:config "App\Models\Product" \
  --column="id:text:label=ID:sortable=true:width=80" \
  --column="sku:text:label=SKU:sortable=true:searchable=true" \
  --column="name:text:label=Nome:sortable=true:searchable=true" \
  --column="category_id:relation:label=Categoria:relation=category.name:searchable=true" \
  --column="brand_id:relation:label=Marca:relation=brand.name:searchable=true" \
  --column="price:money:label=Preço:sortable=true" \
  --column="stock:numeric:label=Estoque:sortable=true" \
  --column="is_active:badge:label=Status:badgeMap=1:success:Ativo,0:danger:Inativo" \
  --column="is_featured:boolean:label=Destaque" \
  --style="is_active:eq:0:bg-red-50 text-red-700" \
  --style="stock:lt:5:bg-yellow-50 text-yellow-700" \
  --style="is_featured:eq:1:bg-purple-50 font-semibold" \
  --filter="is_active:boolean:eq:Ativos" \
  --filter="is_featured:boolean:eq:Em Destaque" \
  --filter="stock:numeric:lt:Estoque Baixo" \
  --action="duplicate:wire:duplicate:bx bx-copy:info:Duplicar este produto?" \
  --set="itemsPerPage=15" \
  --set="cacheEnabled=true" \
  --set="cacheTime=30"
```

#### 2. Order (Pedidos)

```bash
php artisan ptah:config "App\Models\Order" \
  --column="id:text:label=ID:sortable=true:width=80" \
  --column="client_id:relation:label=Cliente:relation=client.user.name:searchable=true" \
  --column="status:badge:label=Status:badgeMap=pending:warning:Pendente,paid:info:Pago,shipped:primary:Enviado,delivered:success:Entregue,cancelled:danger:Cancelado" \
  --column="total:money:label=Total:sortable=true" \
  --column="created_at:date:label=Data:sortable=true" \
  --style="status:eq:pending:bg-yellow-50 text-yellow-700" \
  --style="status:eq:cancelled:bg-red-50 text-red-700" \
  --style="status:eq:delivered:bg-green-50 text-green-700" \
  --filter="status:select:eq:Pendentes:pending" \
  --filter="status:select:eq:Pagos:paid" \
  --filter="status:select:eq:Entregues:delivered" \
  --action="print:wire:printInvoice:bx bx-printer:primary" \
  --action="cancel:wire:cancel:bx bx-x:danger:Deseja cancelar este pedido?" \
  --set="itemsPerPage=20"
```

#### 3. Appointment (Agendamentos)

```bash
php artisan ptah:config "App\Models\Appointment" \
  --column="id:text:label=ID:sortable=true:width=80" \
  --column="scheduled_at:datetime:label=Data/Hora:sortable=true" \
  --column="client_id:relation:label=Cliente:relation=client.user.name:searchable=true" \
  --column="pet_id:relation:label=Pet:relation=pet.name:searchable=true" \
  --column="service_id:relation:label=Serviço:relation=service.name:searchable=true" \
  --column="employee_id:relation:label=Profissional:relation=employee.user.name" \
  --column="status:badge:label=Status:badgeMap=pending:warning:Pendente,confirmed:info:Confirmado,in_progress:primary:Em Andamento,done:success:Concluído,cancelled:danger:Cancelado" \
  --style="status:eq:cancelled:bg-red-50 text-red-700 line-through" \
  --style="status:eq:done:bg-green-50 text-green-700" \
  --filter="status:select:eq:Hoje" \
  --filter="status:select:eq:Esta Semana" \
  --filter="status:select:eq:Pendentes:pending" \
  --action="confirm:wire:confirm:bx bx-check:success:Confirmar agendamento?" \
  --action="cancel:wire:cancel:bx bx-x:danger:Cancelar agendamento?" \
  --set="itemsPerPage=25"
```

#### 4. Pet (Clientes)

```bash
php artisan ptah:config "App\Models\Pet" \
  --column="id:text:label=ID:sortable=true:width=80" \
  --column="name:text:label=Nome:sortable=true:searchable=true" \
  --column="client_id:relation:label=Tutor:relation=client.user.name:searchable=true" \
  --column="species_id:relation:label=Espécie:relation=species.name" \
  --column="breed_id:relation:label=Raça:relation=breed.name" \
  --column="gender:badge:label=Sexo:badgeMap=male:info:Macho,female:primary:Fêmea" \
  --column="birth_date:date:label=Nascimento:sortable=true" \
  --column="is_neutered:boolean:label=Castrado" \
  --column="is_active:badge:label=Status:badgeMap=1:success:Ativo,0:danger:Inativo" \
  --style="is_active:eq:0:bg-red-50 text-red-700" \
  --filter="is_active:boolean:eq:Ativos" \
  --filter="is_neutered:boolean:eq:Castrados" \
  --action="medical_history:wire:viewMedicalHistory:bx bx-file-find:info" \
  --action="vaccinations:wire:viewVaccinations:bx bx-injection:primary" \
  --set="itemsPerPage=15"
```

#### 5. Client (Clientes)

```bash
php artisan ptah:config "App\Models\Client" \
  --column="id:text:label=ID:sortable=true:width=80" \
  --column="user_id:relation:label=Nome:relation=user.name:searchable=true" \
  --column="phone:text:label=Telefone:searchable=true" \
  --column="cpf:text:label=CPF:searchable=true" \
  --column="birth_date:date:label=Nascimento:sortable=true" \
  --column="accepts_promotions:boolean:label=Promoções" \
  --column="is_active:badge:label=Status:badgeMap=1:success:Ativo,0:danger:Inativo" \
  --style="is_active:eq:0:bg-red-50 text-red-700" \
  --filter="is_active:boolean:eq:Ativos" \
  --filter="accepts_promotions:boolean:eq:Aceita Promoções" \
  --action="view_pets:wire:viewPets:bx bx-happy-heart:primary" \
  --action="view_orders:wire:viewOrders:bx bx-shopping-bag:info" \
  --set="itemsPerPage=20"
```

### Comandos Adicionais Úteis

```bash
# Listar configuração atual
php artisan ptah:config "App\Models\Product" --list

# Exportar configuração para JSON
php artisan ptah:config "App\Models\Product" --export=product-config.json

# Importar configuração de JSON
php artisan ptah:config "App\Models\Product" --import=product-config.json

# Resetar configuração (volta ao padrão)
php artisan ptah:config "App\Models\Product" --reset

# Dry-run (mostra o que seria alterado sem salvar)
php artisan ptah:config "App\Models\Product" --column="name:text:label=Nome" --dry-run

# Configurar apenas colunas (pula outras seções)
php artisan ptah:config "App\Models\Product" --only=columns --non-interactive

# Configurar tudo exceto estilos
php artisan ptah:config "App\Models\Product" --skip=styles
```

### Formato das Opções

#### --column

Formato: `campo:tipo:modificador1:modificador2:option1=value1:option2=value2`

**Tipos disponíveis:**
- `text` — Texto simples
- `badge` — Badge colorido (requer `badgeMap`)
- `boolean` — Ícone ✓/✗
- `date` — Data formatada (DD/MM/YYYY)
- `datetime` — Data + hora
- `money` — Valor monetário (R$ 1.234,56)
- `numeric` — Número formatado
- `relation` — Relacionamento (requer `relation=model.field`)

**Modificadores:**
- `sortable=true` — Habilita ordenação
- `searchable=true` — Habilita busca
- `label=Texto` — Label da coluna
- `width=80` — Largura em pixels
- `badgeMap=value1:color1:text1,value2:color2:text2` — Mapeamento de badges
- `relation=model.field` — Caminho do relacionamento

#### --style

Formato: `campo:operador:valor:classes_css`

**Operadores:**
- `eq` — Igual (==)
- `ne` — Diferente (!=)
- `lt` — Menor que (<)
- `gt` — Maior que (>)
- `lte` — Menor ou igual (<=)
- `gte` — Maior ou igual (>=)

**Exemplo:** `is_active:eq:0:bg-red-50 text-red-700`

#### --filter

Formato: `campo:tipo:operador:label[:valor_padrao]`

**Tipos:**
- `boolean` — Checkbox
- `select` — Dropdown
- `numeric` — Input numérico
- `date` — Date picker

**Exemplo:** `status:select:eq:Pendentes:pending`

#### --action

Formato: `nome:tipo:metodo:icone:cor[:mensagem_confirmacao]`

**Tipos:**
- `wire` — Chama método Livewire
- `route` — Redireciona para rota
- `url` — Abre URL externa

**Cores:** `primary`, `success`, `danger`, `warning`, `info`

**Exemplo:** `duplicate:wire:duplicate:bx bx-copy:info:Deseja duplicar?`

#### --set

Formato: `chave=valor`

**Configurações gerais:**
- `itemsPerPage=15` — Itens por página
- `cacheEnabled=true` — Habilitar cache
- `cacheTime=30` — Tempo de cache (minutos)
- `paginationEnabled=true` — Habilitar paginação
- `exportEnabled=true` — Habilitar exportação

---

## 🎨 Configuração Visual Alternativa (Modal Web)

Se preferir configurar pela interface web, acesse cada CRUD e clique no ícone ⚙️:

### 1. Colunas da Listagem

```json
{
  "columns": [
    {"key": "id", "label": "ID", "sortable": true},
    {"key": "name", "label": "Nome", "sortable": true},
    {"key": "status", "label": "Status", "type": "badge", "badgeMap": {
      "active": {"text": "Ativo", "color": "success"},
      "inactive": {"text": "Inativo", "color": "danger"}
    }},
    {"key": "price", "label": "Preço", "type": "money"},
    {"key": "created_at", "label": "Criado em", "type": "date"}
  ]
}
```

### 2. Row Styles (Destaque Visual)

```json
{
  "rowStyles": [
    {"condition": "is_active == false", "classes": "bg-red-50 text-red-700"},
    {"condition": "status == 'overdue'", "classes": "bg-yellow-50 text-yellow-700"},
    {"condition": "is_featured == true", "classes": "bg-purple-50 font-semibold"}
  ]
}
```

### 3. Quick Filters (Filtros Rápidos)

```json
{
  "quickFilters": [
    {"label": "Ativos", "field": "is_active", "value": true},
    {"label": "Inativos", "field": "is_active", "value": false},
    {"label": "Em Destaque", "field": "is_featured", "value": true}
  ]
}
```

### 4. Search Dropdown (Busca por FK)

```json
{
  "searchDropdown": {
    "category_id": {
      "label": "Categoria",
      "model": "App\\Models\\Category",
      "displayField": "name",
      "searchFields": ["name"],
      "placeholder": "Buscar categoria..."
    },
    "brand_id": {
      "label": "Marca",
      "model": "App\\Models\\Brand",
      "displayField": "name"
    }
  }
}
```

### 5. Custom Actions (Ações Personalizadas)

```json
{
  "customActions": [
    {
      "label": "Duplicar",
      "icon": "bx bx-copy",
      "wire": "duplicate",
      "color": "info",
      "confirm": "Deseja duplicar este registro?"
    }
  ]
}
```

---

## 📊 Validação Final

### Checklist de Sucesso

- [ ] Todas as migrations rodaram sem erro
- [ ] Menu da sidebar exibe todos os módulos e links (`ptah:menu-sync --fresh`)
- [ ] Todos os relacionamentos nos Models foram descomentados (TODOs resolvidos)
- [ ] **CRUDs configurados via `ptah:config`** com colunas, badges, filtros e ações
- [ ] Todos os CRUDs abrem sem erro (teste criar/editar/deletar)
- [ ] Relacionamentos funcionam (ex: dropdown de categoria no Product)
- [ ] Login funciona (`admin@admin.com` / `admin@123`)
- [ ] API Swagger disponível em `/api/documentation`

### Comandos de Verificação

```bash
# Ver todas as rotas web
php artisan route:list --path=/ --columns=method,uri,name

# Ver todas as rotas API
php artisan route:list --path=api --columns=method,uri

# Ver menu sincronizado
php artisan tinker
>>> DB::table('menus')->get(['id', 'parent_id', 'text', 'url', 'type', 'link_order'])

# Testar queries
php artisan tinker
>>> App\Models\Product::with('category', 'brand')->first()
```

---

## 🔧 Sugestões de Melhorias Visuais

Após a validação básica, implemente:

### 1. Badges Coloridos (Status)

Configure `badgeMap` em todos os CRUDs com status/enum:
- `active/inactive` → success/danger
- `pending/confirmed/done/cancelled` → warn/info/success/danger
- `open/paid/overdue` → info/success/danger

### 2. Ícones nas Actions

Use ícones Boxicons nas ações customizadas:
- `bx bx-copy` → Duplicar
- `bx bx-download` → Exportar PDF
- `bx bx-send` → Enviar Email
- `bx bx-printer` → Imprimir

### 3. Formatação de Valores

Configure `type` correto nas colunas:
- `money` → Mostra R$ 1.234,56
- `date` → Mostra 05/03/2026
- `boolean` → Mostra ✓ / ✗

### 4. Destaque de Registros Importantes

Use `rowStyles` para destacar:
- Produtos em falta (stock < 5) → amarelo
- Pedidos atrasados (due_date < hoje) → vermelho
- Clientes VIP → roxo

### 5. Filtros Contextuais

Configure `quickFilters` relevantes para cada contexto:
- **Products**: "Em Destaque", "Sem Estoque", "Promoção"
- **Orders**: "Pendentes", "Pagos", "Atrasados"
- **Appointments**: "Hoje", "Esta Semana", "Cancelados"

---

## 📖 Documentação Completa

- **Lifecycle Hooks**: `ProductHooks.example.php` (na mesma pasta)
- **Configuração Avançada**: `Configuration.md` (na mesma pasta)
- **Análise do Projeto**: `../../PTAH_ANALISE_E_ROADMAP.md` (raiz do workspace)

---

## 🎯 Objetivo da Validação

Este prompt deve resultar em:

1. ✅ Instalar ptah sem erros (~2 minutos)
2. ✅ Gerar 23 entidades completas (~5 minutos)
3. ✅ Popular menu automaticamente com `ptah:menu-sync --fresh`
4. ✅ **Configurar 5+ CRUDs via `ptah:config` CLI** (Product, Order, Appointment, Pet, Client)
5. ✅ Resolver TODOs dos relacionamentos nos Models
6. ✅ Resultar em sistema funcional com CRUDs estilizados

### Fluxo Completo de Validação

```bash
# 1. Scaffolding (5 min)
php artisan ptah:forge Catalog/Product --fields="..." --api
php artisan ptah:forge Orders/Order --fields="..." --api
# ... (demais entidades)

# 2. Migrations + Menu (1 min)
php artisan migrate
php artisan ptah:menu-sync --fresh

# 3. Resolver TODOs nos Models (2 min)
# Descomentar relacionamentos em:
# - app/Models/Product.php
# - app/Models/Order.php
# - app/Models/Appointment.php
# - (demais models conforme tabela de relacionamentos)

# 4. Configurar CRUDs via CLI (3 min) ⭐ ESTE É O TESTE!
php artisan ptah:config "App\Models\Product" --column="..." --style="..." --filter="..." --action="..."
php artisan ptah:config "App\Models\Order" --column="..." --style="..." --filter="..." --action="..."
php artisan ptah:config "App\Models\Appointment" --column="..." --style="..." --filter="..." --action="..."
php artisan ptah:config "App\Models\Pet" --column="..." --style="..." --filter="..." --action="..."
php artisan ptah:config "App\Models\Client" --column="..." --style="..." --filter="..." --action="..."

# 5. Validação (2 min)
php artisan serve
# Acessar http://localhost:8000
# Testar login (admin@admin.com / admin@123)
# Navegar pelos CRUDs configurados
# Validar badges coloridos, filtros rápidos, ações customizadas
```

**Teste bem-sucedido:**
- ✅ Menu completo na sidebar (23 links)
- ✅ CRUDs visuais com badges coloridos (status, gênero, etc.)
- ✅ Row styles aplicados (registros inativos em vermelho, etc.)
- ✅ Filtros rápidos funcionando (Ativos, Pendentes, etc.)
- ✅ Ações customizadas visíveis (Duplicar, Imprimir, etc.)
- ✅ Relacionamentos carregando (dropdown de Categoria funciona no Product)

**Total:** ~15 minutos para projeto completo funcional e estilizado! 🚀

---

**Última atualização:** 5 de março de 2026  
**Versão:** 2.1 (Menu Automático + TODOs + Config via CLI + Validação Completa)

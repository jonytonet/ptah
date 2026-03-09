# 🐾 PetPlace — Exemplo de Prompt para IA (Ptah)


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

# 3. Instalar ptah (GitHub)
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

> 💡 **Configuração visual do BaseCrud** (colunas, badges, filtros, estilos e ações customizadas): consulte [Configuration.md](Configuration.md).

---

## 📊 Validação Final

### Checklist de Sucesso

- [ ] Todas as migrations rodaram sem erro
- [ ] Menu da sidebar exibe todos os módulos e links (`ptah:menu-sync --fresh`)
- [ ] Todos os relacionamentos nos Models foram descomentados (TODOs resolvidos)
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
4. ✅ Resolver TODOs dos relacionamentos nos Models
5. ✅ Resultar em sistema funcional e operacional

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

# 4. Validação (2 min)
php artisan serve
# Acessar http://localhost:8000
# Testar login (admin@admin.com / admin@123)
# Navegar pelos CRUDs e validar que CRUD abre, cria, edita e exclui
# Confirmar que o menu está populado na sidebar
```

**Teste bem-sucedido:**
- ✅ Menu completo na sidebar (23 links)
- ✅ Relacionamentos carregando (dropdown de Categoria funciona no Product)

**Total:** ~10 minutos para projeto completo funcional! 🚀

---

**Última atualização:** 9 de março de 2026  
**Versão:** 2.1 (Menu Automático + TODOs + Config via CLI + Validação Completa)

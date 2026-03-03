# Guia de Desenvolvimento com Agentes de IA

**Pacote:** `jonytonet/ptah`  
**Propósito:** Como usar agentes de IA (GitHub Copilot, Claude, Cursor…) para acelerar o desenvolvimento com o Ptah, mantendo os padrões SOLID, BaseCrud e arquitetura do projeto.

---

## Sumário

1. [Contexto do Projeto](#contexto-do-projeto)
2. [Anatomia de um Bom Prompt](#anatomia-de-um-bom-prompt)
3. [Prompt: Criar Nova Entidade Completa](#prompt-criar-nova-entidade-completa)
4. [Prompt: Configurar BaseCrud](#prompt-configurar-basecrud)
5. [Prompt: Criar Módulo Opcional](#prompt-criar-módulo-opcional)
6. [Prompt: Adicionar Validação e Regra de Negócio](#prompt-adicionar-validação-e-regra-de-negócio)
7. [Prompt: Escrever Testes](#prompt-escrever-testes)
8. [Workflow Recomendado](#workflow-recomendado)
9. [Dicas de Produtividade](#dicas-de-produtividade)
10. [Armadilhas Comuns](#armadilhas-comuns)
11. [Performance e Alta Demanda](#performance-e-alta-demanda)

---

## Contexto do Projeto

Antes de qualquer prompt, forneça ao agente o contexto necessário. O Ptah tem arquitetura específica que o agente precisa conhecer:

```
Pacote:    jonytonet/ptah
Laravel:   12.x  |  PHP 8.3  |  Livewire 3  |  Tailwind v4  |  Alpine.js 3
Ícones:    Boxicons 2.1.4 + FontAwesome 6.7.2 (via CDN — nunca SVG inline)
Dark mode: classe `.ptah-dark` no elemento raiz — CSS centralizado no forge-dashboard-layout
Locale:    PTAH_LOCALE=en (padrão) | pt_BR — textos da UI via `__('ptah::ui.*')`
Testes:    Orchestra Testbench + PHPUnit 11 + SQLite :memory:
```

> **Dica:** execute `php artisan ptah:install --boost` para instalar o [Laravel Boost](https://laravel.com/docs/12.x/boost)
> automaticamente. Com o Boost ativo, os agentes (Copilot, Claude Code, Cursor) recebem os guidelines e
> skills do Ptah a cada sessão — sem necessidade de colar contexto manualmente nos prompts.

**Camadas geradas pelo `ptah:forge`:**

```
app/
├── Http/Requests/{Entity}/Store{Entity}Request.php
├── Http/Requests/{Entity}/Update{Entity}Request.php
├── Http/Resources/{Entity}/{Entity}Resource.php
├── DTO/{Entity}/{Entity}DTO.php
├── Contracts/
│   ├── Repositories/{Entity}RepositoryContract.php
│   └── Services/{Entity}ServiceContract.php
├── Repositories/{Entity}Repository.php
├── Services/{Entity}Service.php
└── Models/{Entity}.php

database/migrations/YYYY_MM_DD_create_{entities}_table.php
resources/views/{entity}/index.blade.php           ← usa BaseCrud
```

---

## Anatomia de um Bom Prompt

Um prompt eficaz para geração de código com o Ptah deve conter:

```
[CONTEXTO]     → Qual o papel da entidade no sistema?
[CAMPOS]       → Quais campos existem e seus tipos?
[REGRAS]       → Validações, unicidade, relacionamentos.
[INTEGRAÇÃO]   → Qual módulo usa? (company, permissions, menu?)
[PADRÃO]       → Lembrete da arquitetura (SOLID, BaseCrud, Ptah)
```

---

## Prompt: Criar Nova Entidade Completa

Use este template quando quiser criar uma entidade do zero com scaffolding Ptah.

```
Você está trabalhando no pacote jonytonet/ptah (Laravel 12, Livewire 3,
Tailwind v4). Use o comando ptah:forge para gerar a entidade.

ENTIDADE: Product (Produto)

CAMPOS:
  - name:string         obrigatório, max:255
  - sku:string          obrigatório, único, max:50
  - price:decimal(10,2) obrigatório, min:0
  - stock:integer       padrão 0, min:0
  - category_id:FK      relação belongsTo Category
  - is_active:boolean   padrão true
  - description:text    nullable

REGRAS DE NEGÓCIO:
  - O SKU não pode ser alterado após o primeiro pedido
  - Produtos inativos não aparecem no catálogo público
  - soft_delete habilitado

GERE:
  1. O comando ptah:forge com os campos acima
  2. A migration completa com índices em sku e category_id
  3. O Model com casts, fillable e scope `active()`
  4. O DTO, Repository, Service e Contracts seguindo SOLID
  5. Regras de validação no StoreProductRequest / UpdateProductRequest
```

### Resultado esperado

```bash
php artisan ptah:forge Product \
  --fields="name:string,sku:string,price:decimal,stock:integer,category_id:unsignedBigInteger,is_active:boolean,description:text" \
  --soft-delete
```

---

## Prompt: Configurar BaseCrud

Use quando a entidade já foi gerada e você precisa configurar a tabela dinâmica.

```
Configure o BaseCrud para a entidade Product no banco de dados (tabela crud_configs).

COLUNAS VISÍVEIS:
  - name    → Texto, sortável, pesquisável
  - sku     → Texto, sortável, badge primário
  - price   → Monetário (R$), alinhado à direita, sortável
  - stock   → Número inteiro, badge: verde se >= 5, vermelho se < 5
  - is_active → Boolean com ícone: ✅ Ativo / ❌ Inativo

MODAL DE CRIAÇÃO/EDIÇÃO:
  - Todos os campos acima + description (textarea) + category_id (searchDropdown)
  - Largura: md (768px)

FILTROS RÁPIDOS:
  - status: Todos / Apenas ativos / Apenas inativos
  - Filtro rápido de data por updated_at

REGRAS:
  - Use padrão Ptah: ícones Boxicons (bx bx-*) ou FontAwesome (fas fa-*)
  - Dark mode deve funcionar — use classes ptah-dark no CSS condicional
  - Preserve o padrão de cores: primary=#5b21b6, success=#10b981, danger=#ef4444
```

---

## Prompt: Criar Módulo Opcional

Use quando quiser adicionar um novo módulo ao pacote (similar aos módulos auth, menu, company, permissions).

```
Crie um novo módulo opcional chamado "notifications" para o pacote jonytonet/ptah.

PADRÃO DO PACOTE:
  - Módulos são ativados via config ptah.modules.notifications = true
  - Variável de ambiente: PTAH_MODULE_NOTIFICATIONS
  - Comando de ativação: php artisan ptah:module notifications
  - Migrations publicadas com tag ptah-migrations
  - Views em resources/views/livewire/notifications/
  - Service Provider já registrado em PtahServiceProvider.php
  - Rotas em routes/ptah.php sob middleware ['web','auth'], prefixo ptah-*

FUNCIONALIDADE:
  - Tabela ptah_notifications (user_id, type, title, body, read_at, data JSON)
  - NotificationService com métodos: send(), markAsRead(), unreadCount()
  - Componente Livewire NotificationBell para o navbar (badge com contagem)
  - Livewire NotificationList com paginação e filtro lido/não-lido

REFERÊNCIA DE ARQUITETURA:
  - Siga o padrão do módulo company em src/Livewire/Company/ e src/Services/Company/
  - CSS visual centralizado em forge-dashboard-layout.blade.php
```

---

## Prompt: Adicionar Validação e Regra de Negócio

Use para adicionar validações em um Livewire pré-existente.

```
No componente Livewire Ptah\Livewire\Company\CompanyList, adicione:

1. VALIDAÇÃO: O campo `email` deve ser único na tabela ptah_companies,
   ignorando a empresa sendo editada (padrão: Rule::unique()->ignore($editingId)).

2. REGRA: Antes de salvar, se is_default mudar de false para true,
   remova o flag is_default de todas as outras empresas da tabela.

3. FEEDBACK: Ao concluir essa troca, dispare um evento Livewire
   `company-default-changed` para que outros componentes na página
   possam reagir (ex: CompanySwitcher atualizar o estado visual).

RESTRIÇÕES:
  - Use wire:model.blur em campos text/email (não wire:model.live)
  - Não adicione <style> local — CSS vai em forge-dashboard-layout.blade.php
  - Siga o padrão dos outros campos do rules() já existente no arquivo
```

---

## Prompt: Escrever Testes

Use para solicitar testes alinhados com a estrutura de testes do pacote.

```
Escreva testes para o módulo de empresas do pacote jonytonet/ptah.

SETUP DO AMBIENTE DE TESTES:
  - Estende Ptah\Tests\TestCase (Orchestra Testbench + SQLite :memory:)
  - Use CompanyFactory::new()->create([...]) — NÃO use Company::factory()
    (o pacote não usa Eloquent Factory nativo)
  - Testbench registra automaticamente PtahServiceProvider —
    CompanyService já está disponível via app(CompanyServiceContract::class)

COBERTURA SOLICITADA:
  1. Unit → Model: getLabelDisplay(), scopes active/default, auto-slug, soft delete
  2. Feature → Livewire:
     - can render (assertOk)
     - pode criar empresa válida (assertDatabaseHas)
     - nome é obrigatório (assertHasErrors(['name' => 'required']))
     - label único bloqueia duplicata (assertHasErrors(['label']))
     - pode editar sem mudar label (assertHasNoErrors)
     - não pode excluir empresa padrão (assertDatabaseHas com deleted_at null)
     - busca filtra resultados

LOCALIZAÇÃO DOS ARQUIVOS:
  tests/Unit/Models/CompanyModelTest.php
  tests/Feature/Livewire/CompanyListTest.php
```

---

## Workflow Recomendado

```
1. SCAFFOLDING
   ↓ ptah:forge {Entity} --fields="..." --soft-delete
   ↓ Revisar migration, model e DTO gerados

2. CONFIGURAÇÃO DO BASECRUD
   ↓ Montar JSON de colunas na tabela crud_configs
   ↓ Configurar modal, filtros e ações

3. REGRAS DE NEGÓCIO
   ↓ Service layer — lógica no {Entity}Service.php
   ↓ Validações nos Requests ou no Livewire (Rule::unique, etc.)

4. TESTES
   ↓ Unit: model + service
   ↓ Feature: Livewire (create, validate, edit, delete)

5. DOCS
   ↓ Atualizar docs/{Entity}.md se for módulo do pacote
   ↓ Commit semântico: feat: / fix: / docs:
```

---

## Dicas de Produtividade

### Forneça o arquivo relevante

Em vez de descrever um arquivo, abra-o no editor e peça para o agente ler o contexto. O agente analisa o código real antes de sugerir mudanças.

```
// Bom ✅
"Veja o CompanyList.php aberto no editor e adicione validação de email único"

// Menos eficaz ❌
"No arquivo que faz o CRUD de empresas, adicione validação de email único"
```

### Use referências de código existente

```
// Bom ✅
"Siga o mesmo padrão de Rule::unique que já está no campo label do CompanyList::rules()"
```

### Lembre o agente das restrições visuais

```
// Sempre inclua no prompt quando mexer em CSS/visual:
"CSS vai centralizado em forge-dashboard-layout.blade.php, não crie <style> local.
 Ícones: Boxicons (bx bx-*) ou FontAwesome (fas fa-*), nunca SVG inline."
```

### Sessões longas — reinicie o contexto

Em sessões que envolvem muitos arquivos, resuma o estado atual ao agente:

```
"Contexto: pacote jonytonet/ptah, branch main, commit abc1234.
 Já implementamos X e Y. Agora vamos implementar Z.
 Arquivos relevantes: [lista os arquivos]"
```

---

## Armadilhas Comuns

| Armadilha | Como evitar |
|---|---|
| Agente usa `Company::factory()` | Sempre diga: "use `CompanyFactory::new()` — não existe Eloquent Factory" |
| CSS em `<style>` local na view | Lembre: "CSS centralizado em `forge-dashboard-layout.blade.php`" |
| `wire:model.live` em inputs de texto | Lembre: "use `wire:model.blur` em campos text/email/phone" |
| SVG inline como ícone | Lembre: "use classes CSS Boxicons (`bx bx-*`) ou FontAwesome (`fas fa-*`)" |
| Ignorar soft delete na unicidade | Lembre: `Rule::unique()->ignore($id)` e verificar `withTrashed()` quando necessário |
| Agente gera bind manual no TestCase | "O `PtahServiceProvider` já registra todos os binds automaticamente" |
| Commit sem mensagem semântica | Padrão: `feat:` / `fix:` / `docs:` / `refactor:` / `test:` |
| Texto de UI hardcoded em português | "Textos da UI ficam em `__('ptah::ui.KEY')` — nunca hardcode strings visuas ao usuário" |

---

## Performance e Alta Demanda

> Este projeto é voltado para **alta performance e alta concorrência**.
> Performance é um requisito de primeira classe, não um detalhe de implementação.

### Regras cardinais para qualquer prompt de código

Sempre inclua estas restrições ao solicitar geração de código:

```
RESTRIÇÕES DE PERFORMANCE (obrigatórias):
- NUNCA gere foreach dentro de foreach em coleções Eloquent
  Use keyBy() / groupBy() + lookup por chave O(1)
- NUNCA gere query dentro de loop
  Colete os IDs primeiro, depois whereIn() em uma única query
- SEMPRE use with(['relation']) antes de qualquer foreach que acesse relações
- Qualquer operação pesada (email, PDF, export, API externa) deve ser um Job (Queue)
- Cache com Cache::tags()->remember() para dados lidos com frequência
- Toda migration nova deve incluir index() nas colunas de filtro, FK e order
- Não use ->get() sem limitação em tabelas grandes — use paginate() ou chunk()
```

### Prompt tipo: Gerar opção com performance

```
Gere o método {nome} no {ProductRepository} seguindo as regras de alta performance:

1. EAGER LOAD: use with(['category','brand']) — nunca acesse relações dentro de foreach
2. SEM N+1: colete todos os IDs necessários e busque em whereIn() único
3. CACHE: envolva com Cache::tags(['products'])->remember('products:active', 1800, fn()=>...)
4. PAGINADO: retorne paginate(20) — nunca ->get() sem limite
5. INDEX: confirme que as colunas filtradas têm index na migration
6. JOB: se o método for pesado (> 200ms), retorne void e despache um Job
```

### Jobs — regra do agente

```
Sempre que o agente identificar qualquer uma dessas operações em código síncrono:
  - Envio de email / SMS / notificação push
  - Geração de PDF ou Excel
  - Chamada a API externa (pagamentos, frete, CEP, ERP)
  - Processamento de imagem (resize, crop, compress)
  - Atualização de estoque em lote
  - Geração de relatório ou agregação pesada

O agente DEVE mover essa lógica para um Job e disparar com dispatch().
Nunca executar sôncrono dentro de Request/Response cycle.
```

### Cache — regra do agente

```
O agente DEVe envolver com Cache::tags()->remember() toda query que:
  - Busca tabelas de referência (species, breeds, categories, services)
  - Lista produtos ativos do catálogo
  - Agrega totais de dashboard
  - Carrega permissões do usuário

TTL recomendados:
  - Tabelas de referência imutáveis: 86400 (24h)
  - Catálogo de produtos: 1800 (30min)
  - Agregados de dashboard: 300 (5min)
  - Permissões do usuário: até logout (invalidar no login/logout)
```

### Indexes — regra do agente

Sempre que o agente gerar uma migration nova, deve incluir indexes em:

```php
// Obrigatório em toda migration
$table->index('is_active');           // filtro booleano padrão
$table->index('status');              // enum/status é quase sempre filtrado
$table->index(['deleted_at', 'is_active']); // soft-delete + ativo
$table->index(['created_at']);        // ORDER BY padrão

// Compostos quando o padrão de query é conhecido
$table->index(['category_id', 'is_active', 'price']); // filtro de catálogo
$table->index(['client_id', 'status']);               // pedidos por cliente
```

### Ferramentas de apoio (instalar no projeto)

```bash
# Produção
composer require laravel/horizon                  # monitoramento de filas Redis
composer require laravel/scout                    # full-text search (Meilisearch)

# Desenvolvimento (--dev)
composer require --dev laravel/telescope          # inspetor de queries, jobs, cache
composer require --dev itsgoingd/clockwork        # profiler no browser devtools
```

> **⚠️ Horizon requer Linux/WSL** — `laravel/horizon` depende das extensões `ext-pcntl` e `ext-posix`, disponíveis apenas em ambientes Unix.
> Instale Horizon **somente no servidor de produção ou em WSL**. Em máquinas Windows nativas, use `php artisan queue:work` para desenvolvimento local.

### Anti-patterns que o agente deve RECUSAR gerar

```
❌ foreach dentro de foreach em coleções Eloquent
❌ query (findBy, where, all, get) dentro de qualquer laço
❌ ->with() omitido quando a view/code acessa relações
❌ Mail::send() ou SMS síncrono em request web
❌ Cache::remember() sem tags (impossível invalidar por grupo)
❌ ->get() em tabela sem paginate/limit/chunk
❌ SELECT * em queries de lista (ótimo: ->select(['id','name','status']))
❌ Migration sem index nas colunas de filtro ou FK
❌ Lógica pesada em propriedade Livewire sem #[Computed]
❌ Chamada HTTP externa síncrona dentro de controller
```

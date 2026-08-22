# Testes — como rodar

**Público:** quem for contribuir com o pacote (`ptah/`) e precisa rodar/escrever
testes. Cobre as duas suítes que existem hoje: a suíte padrão (PHPUnit puro,
via Testbench) e a suíte de navegador (Dusk, via `orchestra/testbench-dusk`).

---

## 1. Suíte padrão (`tests/Unit` + `tests/Feature`)

```bash
vendor/bin/phpunit
```

Usa `phpunit.xml` (raiz do pacote). SQLite `:memory:`, sem dependências
externas — não precisa de Chrome, banco de dados real nem rede. Hoje soma
1424 testes. Deve continuar rápida e 100% verde em qualquer ambiente,
inclusive CI mínimo sem Chrome instalado — é exatamente por isso que a suíte
de navegador (seção 2) vive num arquivo de configuração **separado**.

Qualidade de código, sempre antes de propor uma mudança:

```bash
vendor/bin/pint --dirty      # formata só os arquivos alterados
vendor/bin/phpstan analyse   # nível 5, escopo: src/
```

---

## 2. Suíte de navegador (`tests/Browser`) — Dusk

**O que ela pega que a suíte padrão não pega:** bugs que só existem no DOM
*renderizado* — medição real de layout (a toolbar que decide rótulo-ou-ícone
pelo espaço disponível), uma guarda de visibilidade de dialog que engolia
atalhos de teclado em silêncio, foco que não volta depois de um modal fechar,
o navegador realmente resolvendo a densidade/tema via CSS custom properties.
Nada disso é visível para um teste que só lê uma string HTML (`Livewire::test()->html()`).

### Instalação (uma vez)

```bash
composer require --dev orchestra/testbench-dusk
vendor/bin/dusk-updater detect --auto-update
```

O segundo comando detecta a versão do Chrome instalado na máquina e baixa o
ChromeDriver compatível para `vendor/laravel/dusk/bin/` — não precisa apontar
caminho manualmente depois disso. Requer Google Chrome (ou Chromium)
instalado; nada além disso (sem Selenium standalone, sem Docker).

### Rodando

```bash
vendor/bin/phpunit -c phpunit.dusk.xml
```

Note o `-c phpunit.dusk.xml` — **arquivo de configuração diferente do
padrão**, de propósito: a suíte de navegador não faz parte de
`vendor/bin/phpunit` (que continua lendo `phpunit.xml`, sem Chrome, sem esta
suíte). Isso é o que garante que ninguém quebra a suíte principal por não ter
Chrome instalado, e que CI sem display gráfico continua funcionando sem
tocar em nada.

Por padrão os testes rodam **headless** (sem abrir uma janela do Chrome). Para
ver o navegador de verdade enquanto escreve/depura um teste:

```bash
DUSK_HEADLESS_DISABLED=1 vendor/bin/phpunit -c phpunit.dusk.xml
```

Rodar um arquivo/teste específico funciona como em qualquer suíte PHPUnit:

```bash
vendor/bin/phpunit -c phpunit.dusk.xml tests/Browser/CrudModalBrowserTest.php
vendor/bin/phpunit -c phpunit.dusk.xml --filter n_opens_the_create_modal
```

### Como a app de teste é montada

`tests/Browser/DuskTestCase.php` espelha `tests/TestCase.php` (mesmos
providers do pacote, mesmas migrations de `tests/migrations` + `src/Migrations`),
mas com duas diferenças **obrigatórias** para o cenário Dusk (documentadas
com o "por quê" inline na própria classe):

- **`app.key` fixa**, não aleatória por boot: o servidor Dusk reconstrói a
  aplicação do zero em CADA requisição HTTP (não existe um único processo
  "app" de ponta a ponta como numa app real). O prefixo de URL dos assets do
  Livewire é derivado de `sha256(app.key . 'livewire-endpoint')` — uma key
  aleatória por boot faz a página carregar com um prefixo e a requisição
  seguinte (buscar `livewire.js`) cair em outro: 404, Alpine/Livewire nunca
  inicializam, e absolutamente nenhum teste de navegador funciona.
- **`session.driver = 'file'`**, não `'array'`: pela mesma razão (processo
  novo por requisição), uma sessão só-em-memória não sobrevive entre a
  requisição que renderiza a página e a chamada Ajax subsequente do Livewire
  (abrir o modal, salvar…) — o CSRF falha com "Page Expired".
- Migração + seed dos dados de fixture (`CrudConfig` do stub + duas linhas)
  acontecem dentro de um callback `$app->booted()`, não em `setUp()`: o
  processo servidor nunca passa pelo ciclo de vida do PHPUnit, só pelo boot
  normal do container.

As rotas de teste (`/dusk-test/crud`, `/dusk-test/other`,
`/dusk-test/ptah-components.css`) são registradas em
`DuskTestCase::defineWebRoutes()` e montam o `BaseCrud` dentro do layout real
(`<x-forge-dashboard-layout>`), do mesmo jeito que uma tela gerada por
`ptah:forge` (ver `src/Stubs/view.index.stub`) — não um `Livewire::test()`
isolado.

### Duas armadilhas de seletor (se for escrever um novo teste)

- **`Browser::keys('body', ...)` não funciona** para atalhos globais
  (`@keydown.window`): o resolver de elementos do Dusk usa `prefix='body'`
  por padrão, então o seletor `'body'` formata para `"body body"` (não casa
  com nada) — e mesmo corrigindo para `''`, o WebDriver mover o "foco do SO"
  para sintetizar teclas não confiavelmente resulta num evento que borbulha
  até `window` no Chrome headless. Use
  `DuskTestCase::dispatchGlobalKeydown($browser, $key, ctrl: ..., meta: ...)`,
  que despacha um `KeyboardEvent` real via `document.body.dispatchEvent(...)`.
- **`.ptah-modal-panel` sozinho é ambíguo**: BaseCrud sempre renderiza três
  elementos com essa classe (overlay de atalhos, modal criar/editar, modal de
  exclusão) — `waitFor`/`click` do Dusk resolvem para o PRIMEIRO que casa o
  seletor no DOM, então prender a espera no elemento errado é silencioso (fica
  esperando um elemento que nunca vai abrir). Use o seletor documentado em
  `CrudModalBrowserTest::CREATE_EDIT_MODAL`.

### Os fluxos cobertos

| Arquivo | O que guarda |
|---|---|
| `CrudKeyboardShortcutsBrowserTest` | `?` abre o overlay de atalhos, Esc fecha, `/` foca a busca, `n` abre "Novo" — tudo com o dialog de verdade renderizado (a guarda `_anyDialogOpen()` só existe para não comer teclas atrás de um modal aberto). |
| `CrudModalBrowserTest` | Esc fecha o modal criar/editar e devolve o foco ao gatilho (`x-trap` do Alpine); campo obrigatório vazio ganha `aria-invalid` COM borda visivelmente diferente (não só o atributo). |
| `SidebarCollapseBrowserTest` | Ctrl+B colapsa a sidebar (largura real muda, rótulos saem do fluxo); o grupo ativo continua marcado colapsado; clicar o grupo colapsado expande de novo. |
| `GlobalDensityBrowserTest` | Trocar `data-ptah-density` no `<html>` muda a altura computada de um controle real. |
| `ToolbarLabelCollapseBrowserTest` | Em viewport largo os rótulos da toolbar aparecem; encolher a janela colapsa para só-ícone. |
| `SearchPersistenceAcrossBackNavigationBrowserTest` | **Fluxo 6 — o sintoma real do usuário.** Digitar na busca, navegar para outra URL, `history.back()`: o input e a listagem filtrada precisam sobreviver. Ver achado abaixo. |
| `DarkThemeBrowserTest` | Alternar `.ptah-dark` no `<html>` muda o `background-color` computado (pega token órfão). |
| `ColumnPermissionBrowserTest` | O gate `colsPermission`, num Chrome real: o `<th>`/`<td>` da coluna negada nunca chega ao DOM (não só ausente do HTML servido) — classe de bug que um teste `Livewire::test()->html()` não enxerga. |

**Fluxo 8 (search-dropdown por teclado) não foi implementado** — ver
"Achados" abaixo.

### Achados desta rodada (ONDA IV)

- **Fluxo 6 (pesquisa + voltar) — NÃO reproduzido nesta configuração.** O
  teste passa: depois de digitar "Alfa", navegar para `/dusk-test/other` e
  voltar (`$browser->back()`), o Chrome restaura a página via bfcache — o
  campo de busca mantém o valor digitado E a listagem continua filtrada, sem
  nova requisição ao servidor. `search` em `BaseCrud.php` não tem `#[Url]`
  (é uma propriedade Livewire comum, nada na URL reflete o filtro), então
  isso só funciona porque o navegador restaura o DOM/estado JS inteiro do
  bfcache — não há nenhum mecanismo do lado da aplicação garantindo isso.
  Se o bug relatado pelo usuário persistir na prática, as hipóteses que este
  teste NÃO cobrem (e que vale investigar a seguir) são: navegação via
  `wire:navigate` (SPA, não teste aqui — a rota trivial usada é um link
  normal), abas/histórico mais longos, sessão expirada entre a ida e a volta,
  ou uma extensão/política do navegador do usuário desabilitando bfcache
  (ex.: DevTools aberto desliga bfcache no Chrome).
- **`getComputedStyle(...).display` de um rótulo de toolbar nunca é
  `'inline'`, mesmo com a regra CSS correta.** `.ptah-c-btn_label` é filho
  direto de um container `inline-flex` (`.ptah-c-control`/`.ptah-c-btn`), e
  todo navegador "blockifica" o valor COMPUTADO de `display` de um item flex
  (a regra em `ptah-components.css` especifica `inline`, o computado vira
  `block`). Não é bug — é a única leitura confiável para "rótulo visível" é
  `!== 'none'`, não comparar com um valor de `display` específico. Guardado
  no comentário de `ToolbarLabelCollapseBrowserTest`.
- **Clicar um grupo colapsado da sidebar via `Browser::click()` pode FECHAR
  um grupo já ativo, em vez de abri-lo.** `Browser::click()` move o cursor
  real até o elemento antes de clicar, o que dispara `@mouseenter` no
  `<aside>` (`hovered = true`) ANTES do próprio clique — como o grupo já
  ativo inicia com `open: true` (`x-data` computado no servidor), esse hover
  "fantasma" faz `iconOnly()` virar `false` no instante do clique, e o
  handler cai no ramo `else { open = !open }`, fechando o que já estava
  aberto. Não é bug do componente: é exatamente o caso "touch, sem hover"
  que o próprio comentário de `forge-sidebar.blade.php` já documenta como
  suportado de propósito — mas confirma que UM CLIQUE REAL DE MOUSE em cima
  do ícone colapsado de um grupo JÁ ATIVO tem esse comportamento
  contraintuitivo (fecha em vez de expandir) sempre que o cursor precisa
  atravessar a sidebar para chegar até o botão. `SidebarCollapseBrowserTest`
  contorna isso com `element.click()` via `script()` (equivalente ao caso
  touch); um usuário de mouse real passa por isso todo dia.

### Fluxo 8 (search-dropdown por teclado) — por que não foi feito

O plano original marcava este fluxo como opcional ("se couber"). Não coube,
por dois motivos concretos:

1. O campo `colsTipo: 'searchdropdown'` inline do modal criar/editar
   (`_modal-form.blade.php`) **não implementa navegação por setas** — só
   `click`/`mousedown` e digitação. Não há o que testar sem inventar
   comportamento.
2. O componente Livewire dedicado `ptah-search-dropdown`
   (`Ptah\Livewire\SearchDropdown\SearchDropdown`, usado no painel de
   filtros) **tem** `ArrowUp`/`ArrowDown`/`Enter` implementados
   (`search-dropdown.blade.php`), mas sempre resolve o model para
   `App\Models\{Nome}` (`resolveModelClass()`) — não aceita um FQCN
   diretamente. Isso exige um model do stub sob `App\Models\*`, incompatível
   com a convenção de fixtures deste pacote (namespace `Ptah\Tests\...`).
   Fazer esse fluxo caber corretamente exigiria mexer em código de produção
   ou inventar uma convenção de fixture nova — fora do escopo de "adicionar
   testes".

### Known quirk — synthetic clicks on teleported modal buttons (Chrome ≥ 151.0.7922.173)

A WebDriver click on a `wire:click` button living inside forge-modal's
`@teleport('body')` delivers every DOM event to the element (pointerdown,
mousedown, mouseup, click — none default-prevented; verified with listeners on
the button itself) yet Livewire's binding never fires. Human clicks work in
real usage, `$wire.method()` works, and keyboard-driven `wire:model` works —
the quirk is exclusive to the synthetic dispatch path. Until the upstream
cause is identified, browser tests that need to trigger an action inside a
teleported modal should call `window.Livewire.all()[0].$wire.method()` via
`executeScript` and assert on the resulting DOM, which still exercises the
full server round-trip.

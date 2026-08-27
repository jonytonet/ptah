# Telas Customizadas — construindo fora do BaseCrud

**Público:** devs de projetos consumidores (`petplace/` e afins) que criam uma tela
própria — não gerada por `ptah:forge` nem baseada em `<x-base-crud>` — mas que
ainda precisa parecer, e se comportar, como o resto do produto Ptah: mesmo tema,
mesma densidade, mesmo tamanho de fonte, mesma acessibilidade.

Tudo neste documento foi lido diretamente do código do pacote
(`resources/css/ptah-components.css`, `resources/views/components/forge-*.blade.php`,
`resources/views/components/forge-dashboard-layout.blade.php`, `src/Support/AppearancePresets.php`
e os testes de `tests/Unit/Support/`). Nenhuma API aqui foi inventada — onde a
API real não deixou algo claro, isso está listado nas "Lacunas" ao final do PR
que gerou este doc.

---

## 1. Contrato de tokens

> ### Regra de ouro
> **Nunca hardcode cor ou tamanho.** Se a sua tela precisa de uma cor, uma borda,
> um espaçamento de campo ou uma altura de controle que nenhum token abaixo
> cobre, **o token é que está faltando** — abra uma issue / peça um novo token.
> Não é a sua tela que deve inventar um hex ou um `px` fixo. Um valor
> hardcoded nunca responde a tema, densidade ou fonte escolhidos pelo usuário
> em `/profile`.

Todos os tokens abaixo são `var(--ptah-*)`, declarados em `:root` (padrão claro)
e sobrescritos em `.ptah-dark` (escuro) dentro de
`resources/css/ptah-components.css`. Os componentes `<x-forge-*>` já os
consomem internamente — você só precisa deles diretamente quando monta
marcação própria (ex.: uma tabela paginada no servidor, que os componentes
publicados não cobrem hoje).

### Superfícies (fundos opacos ou quase)

| Token | Papel |
|---|---|
| `--ptah-canvas` | Fundo "flush" com a página: toolbar, card do painel de filtro. |
| `--ptah-surface` | Superfície padrão tipo cartão: botões, célula sticky, modal, linha master/detail. |
| `--ptah-surface-raised` | Superfície elevada com sombra: menus dropdown, chrome do modal. |
| `--ptah-surface-sunken` | Painel recuado: corpo do modal, linha master/detail. |
| `--ptah-surface-hover` | Tinta de hover genérica: botões, linhas, célula sticky, badge do empty-state. |
| `--ptah-menu-hover` | Hover de item de dropdown/lista (sutilmente diferente de `--ptah-surface-hover`). |
| `--ptah-panel` | Faixa de cabeçalho/rodapé: `thead`, `tfoot`, header/footer do painel de filtro. |
| `--ptah-field` | Fundo do campo quando ativo/focado. |
| `--ptah-field-muted` | Fundo do campo em repouso (não focado). |
| `--ptah-control-ghost` / `--ptah-control-ghost-hover` | Chrome de controle "ghost" (ex.: botões mostrar/ocultar tudo da visibilidade de colunas) e seu hover. |

### Linhas (bordas/divisores)

| Token | Papel |
|---|---|
| `--ptah-line` | Divisor sutil: dropdowns, header/footer do painel de filtro, header do modal, base da linha. |
| `--ptah-line-strong` | Borda mais visível: contêineres externos (toolbar, cards, wrapper), regra de 2px de `thead`/`tfoot`. |
| `--ptah-line-control` | Trilho/thumb de scrollbar, borda do input de salvar-filtro. |
| `--ptah-line-field` | Borda de repouso de campo de formulário: select de itens-por-página, input de filtro, borda de formulário genérica. |
| `--ptah-line-field-hover` | Borda de hover do token acima. |

### Tintas de texto

| Token | Papel |
|---|---|
| `--ptah-text-strong` | Texto mais escuro: valor do input de busca, título do modal. |
| `--ptah-text-field` | Texto digitado/selecionado dentro de inputs e selects. |
| `--ptah-text` | Texto de corpo padrão: copy do painel de filtro, título do empty-state, rodapé de tabela, itens de dropdown. |
| `--ptah-text-secondary` | Texto de botão-ícone, labels de formulário. |
| `--ptah-text-muted` | Texto discreto: copy de ajuda, setas de ordenação ociosas, subtítulos. |
| `--ptah-text-faint` | Texto mais discreto ainda: resumo de paginação, labels pequenos de campo. |
| `--ptah-icon-muted` | Glifos de ícone da toolbar. |
| `--ptah-text-on-accent` | Tinta branca sobre fundos sólidos de destaque/força — invariante entre claro/escuro. |

### Semânticas (marca + status), com variantes `-soft` / `-strong` / `-lite`

Todas derivam de `--color-primary` / `--color-success` / `--color-danger` /
`--color-warn` (config `config/ptah.php` → `theme.colors`, ver README) via
`color-mix()` — **isso não é a mesma coisa** que os 6 eixos de aparência da
seção 2: cor de marca é config do host; tema/densidade/fonte são preferência
por usuário.

| Token | Papel |
|---|---|
| `--ptah-primary` | Cor de marca base (herda `--color-primary` do host). |
| `--ptah-primary-ring` | Anel de foco (`box-shadow`) em campos/botões focados. |
| `--ptah-primary-soft` / `-softer` | Fundo tintado suave: linha selecionada, item de dropdown selecionado, ícone do modal. |
| `--ptah-primary-border` | Borda tintada de marca. |
| `--ptah-primary-strong` | Marca escurecida — texto/ícone de marca com mais contraste. |
| `--ptah-primary-lite` | Marca "clareada" para servir de tinta em fundo escuro (dark mode). |
| `--ptah-primary-soft-d` | Variante translúcida de `-soft` para uso sobre fundo escuro. |
| `--ptah-success-soft` / `-strong` / `-lite` | Mesma família, para o status de sucesso (chips, alertas). |
| `--ptah-danger-soft` / `-strong` / `-lite` | Mesma família, para o status de perigo. |
| `--ptah-warn-soft` / `-strong` / `-lite` | Mesma família, para o status de alerta. |

### Densidade (eixo global de aparência)

| Token | Papel |
|---|---|
| `--ptah-control-h` | Altura uniforme de controles de toolbar (botão, select, input). |
| `--ptah-control-px` | Padding horizontal desses mesmos controles (exceto `<input>`, que cuida do próprio padding para não colidir com ícones). |
| `--ptah-control-fs` | Tamanho de fonte dos controles de toolbar. |
| `--ptah-row-py` | Padding vertical de linha de tabela/lista densidade-consciente. |
| `--ptah-field-fs` | Tamanho de fonte de campos de formulário "grandes" (login, perfil, módulos) — família própria porque esses campos já eram maiores que os controles de toolbar antes do eixo de densidade existir. |
| `--ptah-field-py` | Padding vertical desses mesmos campos. |
| `--ptah-bar-py` | Padding vertical de barras/toolbars de módulo. |

---

## 2. Os 6 eixos de aparência

O `<html>` renderizado por `<x-forge-dashboard-layout>` (usado por
`ptah::layouts.forge-dashboard`, o layout de toda tela full-page do pacote)
carrega 6 atributos `data-ptah-*`, resolvidos por
`Ptah\Support\AppearancePresets` a partir da preferência salva do usuário
(`UserPreference` no banco) ou do cookie `ptah_appearance` como fallback:

| Atributo | Valores possíveis | O que controla |
|---|---|---|
| `data-ptah-light` | `puro` \| `papel` \| `nevoa` | Tom do modo claro. |
| `data-ptah-dark` | `carvao` \| `grafite` \| `meianoite` | Tom do modo escuro. |
| `data-ptah-accent` | `azul` \| `violeta` \| `ciano` \| `verde` \| `teal` \| `ambar` \| `vermelho` \| `rosa` \| `cinza` | Cor de destaque (accent). |
| `data-ptah-text` | `suave` \| `neutra` \| `forte` | Peso da cor de texto. |
| `data-ptah-density` | `compacta` \| `confortavel` \| `espacosa` | Os 4 tokens de densidade da seção 1. |
| `data-ptah-fontsize` | `pequena` \| `normal` \| `grande` | Escala de fonte. |

O claro/escuro em si (qual dos dois blocos, `:root` ou `.ptah-dark`, está
ativo) é decidido por uma classe `.ptah-dark` aplicada na raiz — detectada do
SO e/ou sobrescrita pelo usuário — e é ortogonal aos 6 atributos acima (que
escolhem o *tom dentro* do modo já decidido).

**O que uma tela custom precisa fazer para responder a esses 6 eixos: nada —
literalmente nada — desde que ela só use os componentes `<x-forge-*>` e os
tokens `--ptah-*` da seção 1.** Os presets de cada eixo já reescrevem os
tokens; qualquer marcação que leia um token em vez de um hex segue o eixo de
graça. O único jeito de *quebrar* essa garantia é hardcodar uma cor/tamanho
(a mesma regra de ouro da seção 1) ou colocar um `<style>` na própria view — ver
armadilha na seção 5.

---

## 3. Catálogo `forge-*`

Props e slots abaixo foram lidos diretamente do bloco `@props` e do comentário
de cada componente em `resources/views/components/`.

### `forge-button`
Props: `color` (`primary|success|danger|warn|dark|light|secondary`, padrão `primary`),
`size` (`sm|md|lg`, padrão `md`), `flat`, `relief`, `rounded`, `disabled`, `loading`.
Slots: default (texto), `icon` (SVG confiável — nunca dado do usuário, é `{!! !!}` raw).

```blade
<x-forge-button color="primary" size="sm" wire:click="save">
    <x-slot:icon><svg class="w-4 h-4">...</svg></x-slot:icon>
    Salvar
</x-forge-button>
```

### `forge-input`
Props: `label`, `placeholder`, `type` (padrão `text`), `state` (`normal|success|danger|warn`),
`iconBefore`/`iconAfter` (HTML raw — nunca dado do usuário), `disabled`, `loading`,
`message`, `required`, `error` (força `state="danger"` e mostra a mensagem), `value`, `name`.

```blade
<x-forge-input label="E-mail" type="email" wire:model="email" :error="$errors->first('email')" required />
```

**Acessibilidade já resolvida:** quando `error` é uma string não vazia, o componente
emite `aria-invalid="true"` e `aria-describedby` apontando para a mensagem —
automático, você só precisa passar `:error`. O `id` do input é derivado
deterministicamente (`md5` de label+name+type+wire:model), não de `uniqid()`/
`Str::random()` — essencial para o Livewire não perder o foco durante a
digitação (ver armadilha na seção 5).

### `forge-select`
Props: `label`, `options` (`[['value' => ..., 'label' => ...], ...]`), `placeholder`
(padrão `Select...`), `multiple`, `disabled`, `required`, `error`, `selected`, `name`.
Requer Alpine.js. **Limitação conhecida:** `multiple` não funciona com `wire:model`
(a ponte usa um `<input type="hidden">` com JSON, não array).

```blade
<x-forge-select label="Status" wire:model="status"
    :options="[['value' => 'open', 'label' => 'Aberto'], ['value' => 'done', 'label' => 'Concluído']]" />
```

### `forge-textarea`
Props: `label`, `placeholder`, `rows` (padrão `4`), `color`, `state`, `disabled`,
`helper`, `maxlength`, `counter` (mostra contador de caracteres; requer Alpine.js).

### `forge-switch`
Props: `label`, `color` (`primary|success|danger|warn`), `checked`, `disabled`,
`size` (`sm|md|lg`). Requer Alpine.js.

### `forge-checkbox`
Props: `label`, `color` (`primary|success|danger|warn`), `checked`, `disabled`.

### `forge-radio`
Props: `label`, `color` (`primary|success|danger|warn`), `value`, `name`, `disabled`.

### `forge-modal`
Props: `title`, `subtitle` (opcional), `size` (`sm|md|lg|xl|2xl|full`, padrão `md`).
Slots: default (corpo), `footer`. Dois modos, **mutuamente exclusivos**:

```blade
{{-- (a) x-data no escopo do PAI --}}
<div x-data="{ open: false }">
    <x-forge-button @click="open = true">Abrir</x-forge-button>
    <x-forge-modal title="Título">
        Conteúdo
        <x-slot:footer>
            <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
        </x-slot:footer>
    </x-forge-modal>
</div>

{{-- (b) self-contained via wire:model --}}
<x-forge-modal wire:model="showModal" title="Título">
    Conteúdo
    <x-slot:footer>
        <x-forge-button wire:click="save">Salvar</x-forge-button>
    </x-slot:footer>
</x-forge-modal>
```

**Acessibilidade já resolvida:** `role="dialog"` + `aria-modal="true"` +
`aria-labelledby` (id determinístico via `md5($title)`); `x-trap.noscroll`
prende o foco dentro do modal; `Esc` fecha (via `@keydown.escape.window`,
chamando `closeModal()` se existir, senão `open = false`); clique no backdrop
fecha do mesmo jeito.

### `forge-alert`
Props: `color` (`primary|success|danger|warn|dark`), `closable`, `title`.
Aceita os aliases `type` (`warning|info|error|...`) e `dismissible` usados por
telas já existentes no pacote. Requer Alpine.js (fechar com `x-transition`).

### `forge-card`
Props: `type` (`default|primary|success|danger|warn|dark`), `flat`, `hoverable`.
Slots: `header`, default, `footer`, `img`.

### `forge-page-header`
Props: `title` (obrigatório na prática), `subtitle`, `back` (URL do botão voltar).
Slot default: ações do lado direito (botões).

### `forge-pagination`
Não é um componente `<x-forge-pagination>` de props próprias — é a *view* de
paginação do Laravel, usada como
`{{ $paginator->links('ptah::components.forge-pagination') }}`. Espera as
variáveis padrão que o `LengthAwarePaginator` injeta (`$paginator`, `$elements`).

### `forge-table`
Props: `headers` (`[['key' => ..., 'label' => ...], ...]`), `rows` (array),
`searchable`, `sortable` (busca/ordenação **client-side via Alpine**, sobre o
array de `rows` já renderizado), `emptyMessage`. Slot `actions`. Requer
Alpine.js. **Não tem paginação nem integração com `wire:model`** — serve para
uma lista pequena e estática por render. Para uma lista paginada no servidor
(o caso comum de uma tela Livewire), monte a marcação você mesmo com os
tokens da seção 1 (ver receita na seção 4) em vez de forçar `forge-table`
nesse papel.

### `forge-avatar`
Props: `src`, `alt`, `size` (`xs|sm|md|lg|xl`), `color`, `text` (iniciais quando
não há imagem), `badgeColor` (`online|offline|busy|primary|success|danger|warn`),
`badgePosition` (`top-right|bottom-right`).

### `forge-stepper`
Props: `steps` (`[['label' => ..., 'description' => ...], ...]`), `currentStep`
(padrão `1`), `color` (`primary|success|warn`).

### `forge-list`
Props: `items` (`[['avatar'|'name'|'description'|'badge'|'value' => ...], ...]`).

### `forge-empty`
Props: `title`, `description` (ambos opcionais). Slots: `icon` (glifo customizado,
tem um genérico "sem dados" por padrão), `cta` (ação abaixo do texto). Já
tokenizado (`.ptah-c-empty_box/_ttl/_sub`) e "density-agnostic" — não precisa
de receita própria de espaçamento.

```blade
<x-forge-empty :title="__('Nenhuma tarefa')" :description="__('Crie a primeira para começar.')">
    <x-slot:cta>
        <x-forge-button color="primary" wire:click="create">Nova tarefa</x-forge-button>
    </x-slot:cta>
</x-forge-empty>
```

### `forge-skeleton`
Props: `variant` (`text|title|avatar|card|table-row`, padrão `text`), `count`
(quantas linhas repetir quando `variant="text"`). Anima com `animate-pulse`,
que a regra global de `prefers-reduced-motion` do pacote já congela — nada a
fazer para respeitar "menos movimento".

### `forge-toast-host`
Sem props — vive uma única vez no layout (`forge-dashboard-layout`) e escuta o
evento `ptah-toast` em `window`. Qualquer componente Livewire dispara um toast
com:

```php
$this->dispatch('ptah-toast', title: 'Salvo com sucesso.', color: 'success');
// color aceito: success | danger | warn | primary
// undoId (opcional): habilita botão "Desfazer", que re-emite 'ptah-toast-undo'
// no window com { id: $undoId } — quem souber desfazer escuta esse evento.
```

Auto-dismiss (3.5s, ou 6s com "Desfazer") pausa no hover/foco — WCAG 2.2.1.

### Outros componentes publicados (fora do escopo detalhado acima)
`forge-progress` (barra 0–100, `color`/`size`/`label`/`animated`),
`forge-stat-card` (cartão de métrica com `title`/`value`/`icon`/`trend`),
`forge-chart-card` (moldura para gráfico, slots `header`/`legend`/`footer`),
`forge-badge` (selo numérico/dot sobre outro elemento, `position`/`dot`),
`forge-notification` (toast posicionável com `duration`, distinto do
`forge-toast-host` global), `forge-spinner` (`circle|dots|wave`),
`forge-breadcrumb` (`items`, `separator`), `forge-navbar` e `forge-sidebar`
(chrome do layout — normalmente você não os usa direto, eles já vêm dentro de
`forge-dashboard-layout`), `forge-tab`/`forge-tabs` (abas, dois modos: slot
Livewire ou array Alpine). Todos seguem a mesma convenção de `@props`
documentada no topo do respectivo arquivo `.blade.php` — leia-o antes de usar
se ele não estiver detalhado aqui.

---

## 4. Receita de página

Esqueleto completo — page-header, toolbar com busca, tabela paginada no
servidor, empty state e modal de formulário — usando só componentes e tokens.
Ele nasce respondendo a tema/densidade/fonte porque nenhuma linha aqui
hardcoda cor: os poucos pontos que não são cobertos por um `<x-forge-*>`
(cabeçalho e corpo da tabela) usam classes que **o projeto host** declara no
seu próprio `resources/css/app.css`, no mesmo espírito do cabeçalho de
`ptah-components.css` ("importe este arquivo no app.css do host") — nunca um
`<style>` dentro da view (ver armadilha 5.5).

```css
/* resources/css/app.css do projeto host */
@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';

.app-table-wrap  { border: 1px solid var(--ptah-line-strong); border-radius: .375rem; overflow-x: auto; }
.app-thead       { background-color: var(--ptah-panel); }
.app-th          { color: var(--ptah-text-muted); }
.app-tr          { border-bottom: 1px solid var(--ptah-line); }
.app-td          { color: var(--ptah-text); }
```

```blade
{{-- resources/views/livewire/task-list.blade.php --}}
<div>
    <x-forge-page-header :title="__('Tarefas')" :subtitle="__('Gerencie as tarefas do time')">
        <x-forge-button color="primary" size="sm" wire:click="create">
            <x-slot:icon><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></x-slot:icon>
            {{ __('Nova tarefa') }}
        </x-forge-button>
    </x-forge-page-header>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex-1 min-w-[200px] max-w-xs">
            <x-forge-input
                type="search"
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Buscar...')"
            />
        </div>
    </div>

    @if ($tasks->isEmpty())
        <x-forge-empty :title="__('Nenhuma tarefa')" :description="__('Crie a primeira para começar.')">
            <x-slot:cta>
                <x-forge-button color="primary" wire:click="create">{{ __('Nova tarefa') }}</x-forge-button>
            </x-slot:cta>
        </x-forge-empty>
    @else
        <div class="app-table-wrap">
            <table class="w-full text-sm">
                <thead class="app-thead">
                    <tr>
                        <th class="app-th px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider">{{ __('Título') }}</th>
                        <th class="app-th px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="app-th px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        <tr class="app-tr">
                            <td class="app-td px-3 py-2.5">{{ $task->title }}</td>
                            <td class="app-td px-3 py-2.5">{{ $task->status }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <x-forge-button color="light" size="sm" wire:click="edit({{ $task->id }})">{{ __('Editar') }}</x-forge-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $tasks->links('ptah::components.forge-pagination') }}
        </div>
    @endif

    <x-forge-modal wire:model="showModal" :title="$editingId ? __('Editar tarefa') : __('Nova tarefa')">
        <div class="space-y-4">
            <x-forge-input :label="__('Título')" wire:model="title" :error="$errors->first('title')" required />
            <x-forge-select :label="__('Status')" wire:model="status"
                :options="[['value' => 'open', 'label' => __('Aberto')], ['value' => 'done', 'label' => __('Concluído')]]" />
        </div>
        <x-slot:footer>
            <x-forge-button color="light" wire:click="$set('showModal', false)">{{ __('Cancelar') }}</x-forge-button>
            <x-forge-button wire:click="save">{{ __('Salvar') }}</x-forge-button>
        </x-slot:footer>
    </x-forge-modal>
</div>
```

Classe Livewire mínima (namespace do projeto host, não do pacote):

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Task;

#[Layout('ptah::layouts.forge-dashboard')]
class TaskList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $status = 'open';

    #[Computed]
    public function tasks(): LengthAwarePaginator
    {
        return Task::query()
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(15);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'status']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->editingId = $task->id;
        $this->title = $task->title;
        $this->status = $task->status;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'title'  => ['required', 'string', 'max:255'],
            'status' => ['required', 'string'],
        ]);

        Task::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->dispatch('ptah-toast', title: __('Tarefa salva com sucesso.'), color: 'success');
    }
}
```

---

## 5. Armadilhas (as regras da casa)

| # | Armadilha | Correção | Guard que pega |
|---|---|---|---|
| 1 | Gerar o `id` de um elemento com `uniqid()`/`Str::random()` numa view renderizada por Livewire. O DOM-diff do Livewire usa `el.id` como chave quando não há `wire:key`; um id novo a cada render faz o morph **remover e recriar** o elemento — perde foco, estado do Alpine (`open`, `activeIndex`). | Derive o id deterministicamente da identidade do campo (`md5(label.'\|'.name.'\|'.tipo.'\|'.wire:model)`), igual em todo render — é o que `forge-input`/`forge-select`/`forge-modal` já fazem. | `tests\Feature\Components\ForgeInputMorphKeyTest.php`, `ForgeSelectMorphKeyTest.php`, `ForgeModalAccessibilityTest.php` |
| 2 | Aspas duplas dentro de um atributo Alpine (`x-data="{ ... "algo" ... }"`) — a primeira aspa dupla interna fecha o atributo prematuramente e o resto do script vaza como texto visível na página, com o Alpine morto por baixo. Acontece até dentro de comentário JS ou seletor CSS de exemplo. | Use aspas simples dentro do JS/expressão; qualquer prosa em português vai num comentário Blade **fora** do elemento. | `tests\Unit\Support\LayoutXDataQuotingTest.php` |
| 3 | `:title="__('...')"` (ou `:placeholder`/`:aria-label`) numa tag HTML **pura** (`<button>`, `<input>`, `<nav>`...). Blade só avalia `:attr` em componentes `<x-...>`; numa tag pura o `:` vira literal no DOM, e como toda página do pacote tem um `x-data` na raiz, o Alpine tenta avaliar `__('...')` como expressão JS e lança um `ReferenceError` silencioso. | Troque por `title="{{ __('...') }}"` (compilação no servidor, sem `:`). | `tests\Unit\Support\BladeBoundAttributeOnPlainTagTest.php` |
| 4 | Referenciar `$el`/`$wire` "crus" dentro de um **método** declarado no objeto de `x-data` (não dentro de uma diretiva como `x-init`/`@click`). Dentro de um método de objeto JS os magics do Alpine não existem como variável solta — lança `ReferenceError` silencioso. | Use `this.$el` / `this.$wire` dentro de métodos do `x-data`. Ver o comentário em `base-crud.blade.php` (`_hotkeys()`), que documenta exatamente este bug. | Sem guard automatizado hoje — revisão manual / console do navegador (ver "Lacunas" abaixo). |
| 5 | Colocar um `<style>` dentro de uma view Blade (o pacote está ativamente desmontando o único bloco legado desse tipo, em `forge-dashboard-layout`). CSS de tema vive em `resources/css/ptah-components.css`. | Declare classes que usam `var(--ptah-*)` no `resources/css/app.css` do projeto host (que já importa `ptah-components.css`), nunca inline num `<style>` da view. | `tests\Unit\Support\CrudConfigThemeParityTest.php` (bloqueia `<style>` novo em `crud-config.blade.php`) e `LayoutStyleBaselineTest.php` (golden-master do bloco legado em desmonte) — nenhum dos dois cobre uma view nova de um projeto host; a disciplina aqui é convenção, não teste automatizado de terceiros. |
| 6 | Referenciar no `x-data`/`@click`/etc. uma função Alpine que nunca foi definida (erro de digitação, função removida numa refatoração). Sem guard estático, isso só aparece como `ReferenceError` no console em tempo de execução. | Use o padrão defensivo que o próprio `forge-modal`/`forge-toast-host` usam para chamadas opcionais: `typeof minhaFuncao === 'function' ? minhaFuncao() : (fallback)`. Teste manualmente no navegador (Console) antes de dar por certo. | Sem guard automatizado hoje (ver "Lacunas" abaixo). |

---

**Paleta fixa em view do pacote é barrada por teste.** `HardcodedPaletteCeilingTest`
mantém um teto POR ARQUIVO de utilitários `bg-*`/`text-*` de paleta fixa que só
pode diminuir — a contagem cresceu 999→1019 entre 1.15.0 e 1.25.0 enquanto a
regra só existia em prosa, e é por isso que agora existe catraca.

---

## 6. Diagnóstico: "troquei o tom para *papel* e um item ficou branco"

O sintoma mais comum de tela fora do tema, visto em projeto real: o usuário
troca o tom claro em `/profile` → Aparência para **papel** (ou **névoa**) e um
cartão, um fundo ou um texto fica para trás — um retângulo branco numa página
de papel. A causa é sempre a mesma: **alguma cor daquele item não passa por um
token que os presets reescrevem.**

### Como achar o infrator

```bash
# nas views do SEU projeto (o host):
grep -rnE 'bg-(white|light|slate|gray)-?|text-(slate|gray)-|dark:|#[0-9a-fA-F]{3,6}'   resources/views --include='*.blade.php'

# e no CSS próprio do host:
grep -rnE '#[0-9a-fA-F]{3,6}|rgb\(' resources/css --include='*.css' | grep -v 'var(--'
```

Cada linha que aparecer é um candidato. Nem toda ocorrência é bug — `bg-primary`,
`bg-success`, `bg-danger`, `bg-warn` são seguras (a primeira segue o eixo de
accent; as demais são constantes de status por decisão) — mas **superfície e
texto** em classe de paleta fixa são exatamente o que não acompanha.

### Tabela de conversão

| Se a view tem | Troque por |
|---|---|
| `bg-white`, `bg-light`, `bg-gray-50` (superfície de cartão) | `style="background: var(--ptah-surface)"` |
| `bg-slate-50/100` (painel, faixa de cabeçalho) | `var(--ptah-panel)` ou `var(--ptah-surface-sunken)` |
| `bg-white` em dropdown/menu flutuante | `var(--ptah-surface-raised)` |
| `hover:bg-gray-100` | `var(--ptah-surface-hover)` (ou `--ptah-menu-hover` em item de menu) |
| `text-slate-800`, `text-gray-900` | `var(--ptah-text)` (ou `--ptah-text-strong` para título) |
| `text-slate-500`, `text-gray-400` | `var(--ptah-text-secondary)` / `--ptah-text-faint` |
| `border-slate-200` | `var(--ptah-line)` (ou `--ptah-line-strong`) |
| `dark:bg-slate-800` + par claro | **apague o par inteiro** — o token já carrega os dois modos |
| `<style>` na view com hex | mova para o `app.css` do host, reescrito em tokens |

O par `claro + dark:` é o caso que mais engana: ele *parece* cuidar de tema,
mas cuida só do eixo claro/escuro e ignora os outros cinco. Um token cuida dos
seis de uma vez — o `.ptah-dark` e cada preset reescrevem o MESMO nome.

> **Escopo desta receita: o SEU projeto (host).** Dentro do pacote ptah a
> convenção é outra — classe nomeada `ptah-c-*` com par de regras (claro e
> `.ptah-dark`) em `resources/css/ptah-components.css`, nunca `style=""` inline.
> O host usa `style=""`/token porque não pode editar o stylesheet do pacote;
> quem contribui para o pacote segue a convenção dele, e o
> `HardcodedPaletteCeilingTest` a aplica.

### Se o infrator for do próprio ptah

Algumas views internas do pacote ainda carregam paleta fixa (herança de antes
do contrato de tokens) — a visão em cards do BaseCrud é o caso mais visível.
Isso é dívida nossa, rastreada em
[KnownLimitations.md](KnownLimitations.md#package-views-with-fixed-palette-surfaces);
não tente "consertar" por cima com CSS no host, porque o seletor que você
escrever vai quebrar quando o pacote corrigir. Se doer, abra uma issue e pine
o tom **puro** até a correção.

---

## 7. Checklist de PR

- [ ] Nenhuma cor/tamanho hardcoded — só `color="..."` dos componentes ou `var(--ptah-*)`.
- [ ] Nenhum `<style>` na view; CSS extra (se necessário) vai no `app.css` do host, usando tokens.
- [ ] Nenhum `id` gerado com `uniqid()`/`Str::random()` em elemento renderizado por Livewire.
- [ ] Nenhuma aspa dupla dentro de `x-data`/`x-init`/`x-effect`.
- [ ] Nenhum `:title`/`:placeholder`/`:aria-label` com `__()` numa tag HTML pura.
- [ ] `$el`/`$wire` só via `this.$el`/`this.$wire` dentro de métodos de `x-data`.
- [ ] Toast de sucesso/erro via `dispatch('ptah-toast', title: ..., color: ...)`, não um alerta inline reinventado.
- [ ] Testado nos dois temas (claro/escuro), pelo menos uma densidade não-padrão e uma fonte não-padrão em `/profile`.

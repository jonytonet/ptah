{{--
    Guarda dos atalhos (FIX 3, Onda C): _anyDialogOpen() substitui o antigo
    `document.body.style.overflow === 'hidden'`, que so o modal de criar/editar
    (_modal-form.blade.php) mantinha atualizado — o confirm de exclusao em massa
    e o de descartar alteracoes, ambos deste mesmo arquivo, nao tocavam nele, e
    um atalho ainda disparava por baixo deles. Todo dialog do pacote (forge-modal
    e os confirms ad-hoc abaixo) carrega aria-modal=true; a visibilidade real
    e testada com checkVisibility()/getClientRects() porque o x-show mora no
    WRAPPER e display computado do painel interno nunca muda
    (display:none), entao checar o display computado cobre qualquer um deles sem
    acoplar a um estado Alpine especifico de outro componente.
--}}
<div class="ptah-base-crud" data-density="{{ $viewDensity }}" wire:key="base-crud-{{ $crudTitle }}"
     x-data="{
         _bulkConfirm: null,
         _showShortcuts: false,
         _anyDialogOpen() {
             /* getComputedStyle(el).display NAO vira none quando quem esconde e
                um ANCESTRAL: os confirms ad-hoc e este proprio overlay poem o
                x-show no wrapper e o aria-modal no painel interno, cujo display
                computado fica block para sempre — a versao anterior via um
                dialog aberto em TODA pagina e engolia todas as teclas em
                silencio (bug reportado pelo usuario: nada acontece, nada no
                console). checkVisibility() olha a cadeia inteira; o fallback
                getClientRects() cobre navegador antigo (elemento nao
                renderizado nao gera caixa, mesmo position:fixed). */
             return Array.from(document.querySelectorAll('[aria-modal=true]')).some(
                 el => el.checkVisibility ? el.checkVisibility() : el.getClientRects().length > 0
             );
         },
         _hotkeys(e) {
             // Ignore while typing or while any dialog is open.
             if (e.target.closest('input, textarea, select, [contenteditable]')) return;
             if (e.key === '?') {
                 e.preventDefault();
                 if (this._anyDialogOpen()) return;
                 this._showShortcuts = true;

                 return;
             }
             if (this._anyDialogOpen()) return;
             if (e.key === 'f' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                 e.preventDefault();
                 this.$wire.toggleFilters();

                 return;
             }
             if (e.key === 'v' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                 e.preventDefault();
                 /* Cicla auto → table → cards → auto. O ternario anterior
                    (table ? cards : table) engolia o estado 'auto': a primeira
                    tecla 'v' a partir do default virava 'table', que e
                    justamente o que o usuario nao pediu. */
                 const _next = { auto: 'table', table: 'cards', cards: 'auto' };
                 this.$wire.setViewMode(_next[this.$wire.viewMode] ?? 'auto');

                 return;
             }
             if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                 e.preventDefault();
                 this.$wire.$refresh();

                 return;
             }
             if (e.key === '/') {
                 e.preventDefault();
                 /* this.$el/this.$wire por EXPLICITUDE. Nota de arquivo: magic
                    cru ($wire) DENTRO de metodo do x-data FUNCIONA — o avaliador
                    do Alpine cria o objeto sob with(scope), e a closure resolve o
                    magic na chamada (verificado empiricamente; o search-dropdown
                    usa $wire cru ha meses). O prefixo this. e convencao local
                    para nao depender desse mecanismo pouco obvio. */
                 const s = this.$el.querySelector('input.ptah-c-search')
                        || this.$el.querySelector('.ptah-c-search input')
                        || this.$el.querySelector('.ptah-c-search');
                 if (s) s.focus();
             }
             @if ($effectivePerms['canCreate'] ?? false)
             if (e.key.toLowerCase() === 'n' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                 e.preventDefault();
                 this.$wire.showModal = true;
                 this.$wire.prepareCreate();
             }
             @endif
         }
     }"
     @keydown.window="_hotkeys($event)"
     {{-- A pilha de toasts vive no layout (forge-toast-host). Aqui so escutamos o
          pedido de desfazer que ela re-emite, porque restoreRecord e deste componente. --}}
     @ptah-toast-undo.window="$wire.restoreRecord($event.detail.id)">


    {{-- Mensagens de sessão (export / crud-config) --}}
    @if (session('crud-success') || $exportStatus)
        <x-forge-alert type="success" :dismissible="true" class="mb-3">
            {{ session('crud-success', $exportStatus) }}
        </x-forge-alert>
    @endif

    {{-- Banner: filters applied via URL (?f[...]) --}}
    @if (!empty($urlFilters))
        @include('ptah::livewire.base-crud.partials._url-filters-banner')
    @endif

    @if (!empty($crudConfig))

        @include('ptah::livewire.base-crud.partials._toolbar')

        @include('ptah::livewire.base-crud.partials._filter-panel')

        {{-- Barra de loading fina: aparece apenas para busca/filtros/paginação, sem mover o layout --}}
        <div class="relative h-0.5 -mt-px">
            <div wire:loading.flex wire:target="search,updatedSearch,gotoPage,nextPage,previousPage,sortBy,setPerPage,updatedFormDataColumns,clearFilters,removeTextFilterBadge,toggleTrashed"
                 class="ptah-loading-bar absolute inset-0 hidden"></div>
        </div>

        {{-- Layout da listagem.

             'auto' (default) renderiza AS DUAS e deixa o CSS escolher: tabela em
             >= md, cards abaixo. A alternativa seria decidir no servidor, mas o
             servidor nao conhece o viewport — precisaria de um round-trip do
             Alpine informando a largura, o que custa um flash de layout errado
             no primeiro paint e outro a cada rotacao de tela. Aqui a troca e
             instantanea, sobrevive a redimensionamento e nao persiste nada
             especifico do aparelho.

             O custo e HTML duplicado no modo auto, MEDIDO e nao estimado: numa
             tela real de 4 linhas, +11.826 bytes crus e +984 bytes apos gzip
             (2,1% da resposta). O gzip come quase tudo porque a marcacao
             duplicada e altamente repetitiva; a ~2,9 KB crus por linha, uma
             pagina de 25 linhas custa da ordem de 70 KB crus.

             Nao ha trabalho extra de banco nem de formatacao: as duas visoes
             consomem as MESMAS linhas e o mesmo formatCell. A metade escondida
             fica em display:none, que o navegador nao pagina nem calcula
             layout. E quem fixa 'table' ou 'cards' nao paga nada disso —
             renderiza apenas a visao escolhida.

             ptah.crud.responsive_cards = false devolve o comportamento anterior
             (auto se comporta como table) para quem nao quiser a troca. --}}
        @php
            $ptahResponsiveCards = (bool) config('ptah.crud.responsive_cards', true);
            $ptahViewMode = ($viewMode === 'auto' && ! $ptahResponsiveCards) ? 'table' : $viewMode;
        @endphp

        @if ($ptahViewMode === 'cards')
            @include('ptah::livewire.base-crud.partials._cards')
        @elseif ($ptahViewMode === 'table')
            @include('ptah::livewire.base-crud.partials._table')
        @else
            <div class="hidden md:block">
                @include('ptah::livewire.base-crud.partials._table')
            </div>
            <div class="md:hidden">
                @include('ptah::livewire.base-crud.partials._cards')
            </div>
        @endif

        @include('ptah::livewire.base-crud.partials._pagination')

        {{-- Bulk actions floating bar --}}
        @if (count($selectedRows) > 0)
            <div class="fixed bottom-4 inset-x-0 mx-auto w-max z-40 px-5 py-2.5 rounded-lg shadow-2xl
                        flex items-center gap-3 ptah-c-bulk_bar">
                <svg class="w-4 h-4 shrink-0 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span class="text-sm font-semibold">
                    {{ __('ptah::ui.bulk_n_selected', ['n' => count($selectedRows)]) }}
                </span>

                @if ($showTrashed)
                    {{-- Modo lixeira: limpar permanentemente + restaurar --}}
                    <button wire:loading.attr="disabled"
                        @click="_bulkConfirm = 'force'"
                        class="ptah-c-bulk_delete_btn inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ __('ptah::ui.bulk_force_delete_btn') }}
                    </button>
                    <button wire:click="bulkRestore" wire:loading.attr="disabled"
                        class="ptah-c-bulk_cancel_btn inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        {{ __('ptah::ui.bulk_restore_btn') }}
                    </button>
                @else
                    {{-- Modo normal: excluir --}}
                    <button wire:loading.attr="disabled"
                        @click="_bulkConfirm = 'delete'"
                        class="ptah-c-bulk_delete_btn inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ __('ptah::ui.bulk_delete_btn') }}
                    </button>
                @endif

                <button wire:click="clearSelection" class="ptah-c-bulk_cancel_btn">
                    {{ __('ptah::ui.bulk_cancel') }}
                </button>
            </div>

            {{-- Spacer so the floating bar never covers the pagination controls --}}
            <div class="h-16" aria-hidden="true"></div>

            {{-- Bulk delete confirmation (replaces the native confirm() dialog) --}}
            <div x-show="_bulkConfirm" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="_bulkConfirm = null">
                <div class="absolute inset-0 bg-black/40" @click="_bulkConfirm = null"></div>
                <div x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-md rounded-lg shadow-2xl bg-white dark:bg-slate-800 p-5"
                     role="alertdialog" aria-modal="true">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-md bg-red-50 dark:bg-red-900/30 ring-4 ring-red-50 dark:ring-red-900/20">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-white"
                               x-text="_bulkConfirm === 'force'
                                   ? '{{ addslashes(__('ptah::ui.bulk_force_delete_confirm', ['n' => count($selectedRows)])) }}'
                                   : '{{ addslashes(__('ptah::ui.bulk_delete_confirm', ['n' => count($selectedRows)])) }}'"></p>
                            <p x-show="_bulkConfirm === 'force'" class="text-xs mt-1 text-red-500">
                                {{ __('ptah::ui.bulk_force_irreversible') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="_bulkConfirm = null"
                            class="px-4 py-2 text-sm font-semibold rounded-md text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            {{ __('ptah::ui.btn_cancel') }}
                        </button>
                        <button @click="_bulkConfirm === 'force' ? $wire.bulkForceDelete() : $wire.bulkDelete(); _bulkConfirm = null"
                            class="px-4 py-2 text-sm font-semibold rounded-md text-white bg-danger-dark hover:opacity-90">
                            {{ __('ptah::ui.btn_delete') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

    @else
        <x-forge-alert type="warning">
            {{ __('ptah::ui.crud_no_config') }} <strong>{{ $model }}</strong>.
            Execute <code>php artisan ptah:forge {{ $model }}</code> para gerar.
        </x-forge-alert>
    @endif

    {{-- Atalhos de teclado (FIX 3, Onda C) — "?" fora de um campo abre esta lista.
         So os atalhos que este componente realmente tem (ver _hotkeys acima):
         "/" foca a busca, "n" abre "Novo" (so quando o usuario pode criar). --}}
    <div x-show="_showShortcuts" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="_showShortcuts = false">
        <div class="absolute inset-0 bg-black/40" @click="_showShortcuts = false"></div>
        <div x-trap.noscroll="_showShortcuts"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             role="dialog" aria-modal="true" aria-labelledby="ptah-shortcuts-title"
             class="ptah-modal-panel relative w-full max-w-sm rounded-xl border shadow-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 id="ptah-shortcuts-title" class="text-base font-semibold">
                    {{ __('ptah::ui.shortcuts_title') }}
                </h3>
                <button type="button" @click="_showShortcuts = false"
                    class="shrink-0 rounded transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    aria-label="{{ __('ptah::ui.modal_close') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_search') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">/</kbd></dd>
                </div>
                @if ($effectivePerms['canCreate'] ?? false)
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_new') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">n</kbd></dd>
                </div>
                @endif
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_filters') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">f</kbd></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_view_mode') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">v</kbd></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_refresh') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">r</kbd></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_sidebar') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">Ctrl+B</kbd></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('ptah::ui.shortcuts_help') }}</dt>
                    <dd><kbd class="px-1.5 py-0.5 rounded border text-xs font-mono ptah-c-kbd">?</kbd></dd>
                </div>
            </dl>
        </div>
    </div>

    @include('ptah::livewire.base-crud.partials._modal-form')

    @include('ptah::livewire.base-crud.partials._modal-delete')

    {{-- Loading overlay apenas para ações pesadas (salvar, deletar, exportar) --}}
    <div wire:loading.delay.long wire:target="save,deleteRecord,export"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/20">
        <x-forge-spinner color="primary" size="lg" />
    </div>

    @include('ptah::livewire.base-crud.partials._scripts')

</div>

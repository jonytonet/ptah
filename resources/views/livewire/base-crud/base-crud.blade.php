<div class="ptah-base-crud" data-density="{{ $viewDensity }}" wire:key="base-crud-{{ $crudTitle }}"
     x-data="{
         _bulkConfirm: null,
         _hotkeys(e) {
             // Ignore while typing or while any modal holds the page scroll.
             if (e.target.closest('input, textarea, select, [contenteditable]')) return;
             if (document.body.style.overflow === 'hidden') return;
             if (e.key === '/') {
                 e.preventDefault();
                 const s = $el.querySelector('input.ptah-c-search')
                        || $el.querySelector('.ptah-c-search input')
                        || $el.querySelector('.ptah-c-search');
                 if (s) s.focus();
             }
             @if ($effectivePerms['canCreate'] ?? false)
             if (e.key.toLowerCase() === 'n' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                 e.preventDefault();
                 $wire.showModal = true;
                 $wire.prepareCreate();
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

        @if ($viewMode === 'cards')
            @include('ptah::livewire.base-crud.partials._cards')
        @else
            @include('ptah::livewire.base-crud.partials._table')
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

    @include('ptah::livewire.base-crud.partials._modal-form')

    @include('ptah::livewire.base-crud.partials._modal-delete')

    {{-- Loading overlay apenas para ações pesadas (salvar, deletar, exportar) --}}
    <div wire:loading.delay.long wire:target="save,deleteRecord,export"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/20">
        <x-forge-spinner color="primary" size="lg" />
    </div>

    @include('ptah::livewire.base-crud.partials._scripts')

</div>

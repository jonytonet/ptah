<div class="ptah-base-crud" wire:key="base-crud-{{ $crudTitle }}"
     x-data="{
         _toast: { show: false, title: '', color: 'success', timer: null },
         _showToast(title, color) {
             this._toast.title = title;
             this._toast.color = color;
             this._toast.show = true;
             clearTimeout(this._toast.timer);
             this._toast.timer = setTimeout(() => { this._toast.show = false; }, 3500);
         }
     }"
     @ptah-toast.window="_showToast($event.detail.title, $event.detail.color)">

    {{-- Toast notification --}}
    <div x-show="_toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 left-4 z-50 flex items-center gap-2.5 px-4 py-3 rounded-lg shadow-lg text-white text-sm font-semibold"
         :class="{
             'bg-success': _toast.color === 'success',
             'bg-warn':    _toast.color === 'warn',
             'bg-danger':  _toast.color === 'danger',
             'bg-primary': _toast.color === 'primary'
         }">
        <svg x-show="_toast.color === 'success'" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <svg x-show="_toast.color === 'warn'"    class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <svg x-show="_toast.color === 'danger'"  class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span x-text="_toast.title"></span>
        <button @click="_toast.show = false" class="ml-1 opacity-70 hover:opacity-100">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Mensagens de sessão (export / crud-config) --}}
    @if (session('crud-success') || $exportStatus)
        <x-forge-alert type="success" :dismissible="true" class="mb-3">
            {{ session('crud-success', $exportStatus) }}
        </x-forge-alert>
    @endif

    @if (!empty($crudConfig))

        @include('ptah::livewire.base-crud.partials._toolbar')

        @include('ptah::livewire.base-crud.partials._filter-panel')

        {{-- Barra de loading fina: aparece apenas para busca/filtros/paginação, sem mover o layout --}}
        <div class="relative h-0.5 -mt-px">
            <div wire:loading.flex wire:target="search,updatedSearch,gotoPage,nextPage,previousPage,sortBy,setPerPage,updatedFormDataColumns,clearFilters,removeTextFilterBadge,toggleTrashed"
                 class="ptah-loading-bar absolute inset-0 hidden"></div>
        </div>

        @include('ptah::livewire.base-crud.partials._table')

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
                        @click="confirm('{{ addslashes(__('ptah::ui.bulk_force_delete_confirm', ['n' => count($selectedRows)])) }}') && $wire.bulkForceDelete()"
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
                        @click="confirm('{{ addslashes(__('ptah::ui.bulk_delete_confirm', ['n' => count($selectedRows)])) }}') && $wire.bulkDelete()"
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

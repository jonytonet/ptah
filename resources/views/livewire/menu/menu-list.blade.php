{{-- ptah::livewire.menu.menu-list --}}
<div>
    {{-- Título --}}
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">{{ __('ptah::ui.menu_title') }}</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ __('ptah::ui.menu_subtitle') }}</p>
    </div>

    {{-- Alertas --}}
    @if ($successMsg) <x-forge-alert type="success" class="mb-3">{{ $successMsg }}</x-forge-alert> @endif
    @if ($errorMsg)   <x-forge-alert type="danger"  class="mb-3">{{ $errorMsg }}</x-forge-alert>   @endif

    {{-- Toolbar --}}
    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border shadow-sm rounded-xl bg-white border-slate-200">
        <button wire:click="create"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none select-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('ptah::ui.menu_new_item_btn') }}
        </button>

        {{-- Busca --}}
        <div class="flex-1 min-w-[180px] max-w-xs">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" :placeholder="__('ptah::ui.menu_search_ph')"
                    class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
            </div>
        </div>

        {{-- Filtro tipo --}}
        <select wire:model.live="typeFilter"
            class="py-2 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all">
            <option value="">{{ __('ptah::ui.menu_all_types') }}</option>
            <option value="menuLink">Link</option>
            <option value="menuGroup">Grupo</option>
        </select>
    </div>

    {{-- Tabela --}}
    <div class="ptah-module-table overflow-x-auto border shadow-sm border-slate-200 rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 w-10">{{ __('ptah::ui.menu_col_icon') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 cursor-pointer" wire:click="sort('text')">
                        <span class="flex items-center gap-1">{{ __('ptah::ui.menu_col_text') }} @if($sort==='text')<span class="text-indigo-500">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</span>
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.menu_col_type') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.menu_col_url') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.menu_col_parent') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 cursor-pointer" wire:click="sort('link_order')">
                        <span class="flex items-center justify-center gap-1">{{ __('ptah::ui.menu_col_order') }} @if($sort==='link_order')<span class="text-indigo-500">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</span>
                    </th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.menu_col_status') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.menu_col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="transition-colors hover:bg-slate-50/70 {{ !$row->is_active ? 'opacity-50' : '' }}">
                        {{-- Ícone preview --}}
                        <td class="px-3 py-2.5 text-center">
                            <span class="text-slate-600 text-lg leading-none" title="{{ $row->icon }}">
                                <i class="{{ $row->icon ?? 'bx bx-circle' }}"></i>
                            </span>
                        </td>
                        {{-- Texto --}}
                        <td class="px-3 py-2.5 font-medium text-slate-800">{{ $row->text }}</td>
                        {{-- Tipo --}}
                        <td class="px-3 py-2.5">
                            @if($row->type === 'menuGroup')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                    <i class="bx bx-folder text-sm"></i> {{ __('ptah::ui.menu_group_badge') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    <i class="bx bx-link text-sm"></i> {{ __('ptah::ui.menu_link_badge') }}
                                </span>
                            @endif
                        </td>
                        {{-- URL --}}
                        <td class="px-3 py-2.5 text-slate-500 text-xs font-mono max-w-[180px] truncate" title="{{ $row->url }}">
                            {{ $row->url ?? '—' }}
                        </td>
                        {{-- Grupo pai --}}
                        <td class="px-3 py-2.5 text-slate-500">
                            {{ $row->parent?->text ?? '—' }}
                        </td>
                        {{-- Ordem --}}
                        <td class="px-3 py-2.5 text-center">
                            <span class="text-xs font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">{{ $row->link_order }}</span>
                        </td>
                        {{-- Status --}}
                        <td class="px-3 py-2.5 text-center">
                            <button wire:click="toggleActive({{ $row->id }})" :title="$row->is_active ? __('ptah::ui.menu_toggle_disable') : __('ptah::ui.menu_toggle_enable')">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-opacity hover:opacity-70
                                    {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $row->is_active ? __('ptah::ui.lbl_active') : __('ptah::ui.lbl_inactive') }}
                                </span>
                            </button>
                        </td>
                        {{-- Ações --}}
                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="edit({{ $row->id }})" class="transition-colors text-primary hover:text-primary/80" :title="__('ptah::ui.btn_edit_title')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="confirmDelete({{ $row->id }})" class="transition-colors text-danger hover:text-danger/80" :title="__('ptah::ui.btn_delete_title')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                    <i class="bx bx-menu text-4xl text-slate-400"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">{{ __('ptah::ui.menu_empty_found') }}</p>
                                    <p class="text-xs mt-0.5 text-slate-400">{{ __('ptah::ui.menu_empty') }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginação --}}
    @if ($rows->hasPages())
        <div class="mt-4">{{ $rows->links('ptah::components.forge-pagination') }}</div>
    @endif

    {{-- ===== Modal create/edit ===== --}}
    @if ($showModal)
    <div
        x-data
        x-show="true"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/40"
        @keydown.escape.window="$wire.showModal = false"
    >
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-100 dark:border-slate-700"
            @click.stop
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-base font-bold text-slate-800 dark:text-white">
                    {{ $isEditing ? __('ptah::ui.menu_form_title_edit') : __('ptah::ui.menu_form_title_new') }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4">

                {{-- Tipo --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{{ __('ptah::ui.menu_form_type') }} <span class="text-danger">*</span></label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" wire:model.live="type" value="menuLink" class="text-indigo-600">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('ptah::ui.menu_form_direct_link') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" wire:model.live="type" value="menuGroup" class="text-indigo-600">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('ptah::ui.menu_form_group_type') }}</span>
                        </label>
                    </div>
                    @error('type') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Texto --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{{ __('ptah::ui.menu_form_text_label') }} <span class="text-danger">*</span></label>
                    <input wire:model="text" type="text" :placeholder="__('ptah::ui.menu_form_text_ph')"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
                    @error('text') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- URL (só para menuLink) --}}
                @if ($type === 'menuLink')
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">URL</label>
                    <input wire:model="url" type="text" :placeholder="__('ptah::ui.menu_form_url_ph')"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all font-mono"/>
                    @error('url') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                @endif

                {{-- Ícone --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                        {{ __('ptah::ui.menu_form_icon_label') }}
                        <span class="font-normal text-slate-400 dark:text-slate-500">{{ __('ptah::ui.menu_form_icon_hint') }}</span>
                    </label>
                    <div class="flex gap-2 items-center">
                        <input wire:model.live="icon" type="text" :placeholder="__('ptah::ui.menu_form_icon_ph')"
                            class="flex-1 px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all font-mono"/>
                        <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xl flex-shrink-0" title="Preview">
                            <i class="{{ $icon ?: 'bx bx-circle' }}"></i>
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                        Exemplos: <code class="bg-slate-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">bx bx-home</code>
                        <code class="bg-slate-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">fas fa-cog</code>
                        <code class="bg-slate-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">bx bxs-shopping-bag</code>
                    </p>
                    @error('icon') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Grupo pai --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{{ __('ptah::ui.menu_form_parent_group') }}</label>
                    <select wire:model="parent_id"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all bg-white">
                        <option value="">{{ __('ptah::ui.menu_form_root') }}</option>
                        @foreach ($this->groups as $group)
                            <option value="{{ $group->id }}">{{ $group->text }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Linha: Ordem + Abertura + Status --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{{ __('ptah::ui.menu_form_order') }}</label>
                        <input wire:model="link_order" type="number" min="0"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{{ __('ptah::ui.menu_form_opening') }}</label>
                        <select wire:model="target"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all bg-white">
                            <option value="_self">{{ __('ptah::ui.menu_form_same_tab') }}</option>
                            <option value="_blank">{{ __('ptah::ui.menu_form_new_tab') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{{ __('ptah::ui.menu_col_status') }}</label>
                        <label class="flex items-center gap-2 cursor-pointer mt-2 select-none">
                            <input type="checkbox" wire:model="is_active" class="rounded text-indigo-600">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('ptah::ui.menu_form_active') }}</span>
                        </label>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 rounded-b-2xl">
                <button wire:click="$set('showModal', false)"
                    class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                    {{ __('ptah::ui.btn_cancel') }}
                </button>
                <button wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-all disabled:opacity-60">
                    <span wire:loading wire:target="save">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                    </span>
                    {{ $isEditing ? __('ptah::ui.menu_save_changes') : __('ptah::ui.menu_create_item') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Modal confirmação de exclusão ===== --}}
    @if ($showDeleteModal)
    <div
        x-data
        x-show="true"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
        @keydown.escape.window="$wire.showDeleteModal = false"
    >
        <div
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-sm border border-slate-100 dark:border-slate-700"
            @click.stop
        >
            <div class="flex items-center gap-4 mb-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-red-100 dark:bg-red-900/30 flex-shrink-0">
                    <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white">{{ __('ptah::ui.menu_delete_title') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ __('ptah::ui.menu_delete_text') }}</p>
                </div>
            </div>
            <div class="flex gap-3 justify-end">
                <button wire:click="$set('showDeleteModal', false)"
                    class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                    {{ __('ptah::ui.btn_cancel') }}
                </button>
                <button wire:click="delete"
                    class="px-4 py-2 text-sm font-semibold text-white bg-danger hover:bg-danger/90 rounded-lg shadow-sm transition-colors">
                    {{ __('ptah::ui.menu_delete_confirm') }}
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

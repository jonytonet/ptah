{{-- ptah::livewire.permission.page-list --}}
<div>
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">Páginas e Objetos</h1>
        <p class="text-sm text-slate-500 mt-0.5">Cadastre as páginas do sistema e seus objetos (botões, campos, links) para controle de acesso.</p>
    </div>

    @if ($successMsg) <x-forge-alert type="success" class="mb-3">{{ $successMsg }}</x-forge-alert> @endif
    @if ($errorMsg)   <x-forge-alert type="danger"  class="mb-3">{{ $errorMsg }}</x-forge-alert>   @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- ── COLUNA: Páginas ──────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-700">Páginas</h2>
                <button wire:click="createPage"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none select-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Página
                </button>
            </div>
            <div class="relative mb-3">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar página..."
                    class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="divide-y divide-slate-100">
                    @forelse ($pageRows as $page)
                        <div
                            wire:click="selectPage({{ $page->id }}, '{{ addslashes($page->name) }}')"
                            class="flex items-center justify-between px-4 py-3 cursor-pointer transition-colors {{ $selectedPageId === $page->id ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-slate-50/70' }}"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    @if ($page->icon)
                                        <span class="text-slate-400 text-sm">{{ $page->icon }}</span>
                                    @endif
                                    <span class="font-medium text-slate-800 truncate">{{ $page->name }}</span>
                                    <span class="text-xs text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded font-mono shrink-0">{{ $page->page_objects_count }} obj</span>
                                </div>
                                <p class="text-xs text-slate-400 font-mono mt-0.5 truncate">{{ $page->slug }}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0 ml-2">
                                <button wire:click.stop="editPage({{ $page->id }})" class="transition-colors text-primary hover:text-primary/80 p-1 rounded">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click.stop="confirmDeletePage({{ $page->id }})" class="transition-colors text-danger hover:text-danger/80 p-1 rounded">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Nenhuma página cadastrada</p>
                                    <p class="text-xs mt-0.5 text-slate-400">Crie a primeira página para começar.</p>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
                @if ($pageRows->hasPages())
                    <div class="flex items-center justify-between px-4 py-2 border-t border-slate-200 text-sm text-slate-500">
                        <span>{{ $pageRows->firstItem() }}–{{ $pageRows->lastItem() }} de {{ $pageRows->total() }}</span>
                        <div>{{ $pageRows->links() }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── COLUNA: Objetos da página selecionada ──────────── --}}
        <div>
            @if ($selectedPageId)
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-slate-700">
                        Objetos — <span class="text-blue-600">{{ $selectedPageName }}</span>
                    </h2>
                    <button wire:click="createObj"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none select-none">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Objeto
                    </button>
                </div>
                <div class="relative mb-3">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                    </svg>
                    <input wire:model.live.debounce.300ms="objSearch" type="search" placeholder="Buscar objeto..."
                        class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
                </div>

                <div class="ptah-module-table overflow-x-auto border shadow-sm border-slate-200 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Chave / Label</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Tipo</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Seção</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($objRows as $obj)
                                <tr class="transition-colors hover:bg-slate-50/70">
                                    <td class="px-3 py-2.5">
                                        <p class="font-medium text-slate-800 text-xs">{{ $obj->obj_label }}</p>
                                        <p class="text-xs text-slate-400 font-mono">{{ $obj->obj_key }}</p>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ $obj->obj_type }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center text-xs text-slate-500">{{ $obj->section }}</td>
                                    <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            <button wire:click="editObj({{ $obj->id }})" class="transition-colors text-primary hover:text-primary/80">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button wire:click="confirmDeleteObj({{ $obj->id }})" class="transition-colors text-danger hover:text-danger/80">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                                <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-700">Nenhum objeto nesta página</p>
                                                <p class="text-xs mt-0.5 text-slate-400">Adicione objetos para controlar o acesso.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($objRows?->hasPages())
                    <div class="flex items-center justify-between mt-2 text-sm text-slate-500">
                        <span>{{ $objRows->firstItem() }}–{{ $objRows->lastItem() }} de {{ $objRows->total() }}</span>
                        <div>{{ $objRows->links() }}</div>
                    </div>
                @endif
            @else
                <div class="flex items-center justify-center h-full min-h-[200px]">
                    <div class="text-center text-slate-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="text-sm">Selecione uma página para ver seus objetos</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Página --}}
    <div x-data="{ open: @entangle('showPageModal').live }">
        <x-forge-modal :title="$isEditingPage ? 'Editar Página' : 'Nova Página'" size="md">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input label="Slug *" wire:model="page_slug" :error="$errors->first('page_slug')" placeholder="admin.users" class="font-mono" required />
                    <x-forge-input label="Nome *" wire:model="page_name" :error="$errors->first('page_name')" required />
                </div>
                <x-forge-textarea label="Descrição" wire:model="page_description" rows="2" />
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input label="Rota Laravel" wire:model="page_route" placeholder="admin.users.index" />
                    <x-forge-input label="Ícone" wire:model="page_icon" placeholder="bx bx-home ou fas fa-home" />
                </div>
                <div class="flex items-center gap-6">
                    <x-forge-switch wire:model="page_is_active" label="Página ativa" />
                    <x-forge-input label="Ordem" wire:model="page_sort_order" type="number" class="w-24" />
                </div>
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="savePage" color="primary">Salvar</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal Objeto --}}
    <div x-data="{ open: @entangle('showObjModal').live }">
        <x-forge-modal :title="$isEditingObj ? 'Editar Objeto' : 'Novo Objeto'" size="md">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input label="Seção" wire:model="obj_section" placeholder="main / toolbar / form" required />
                    <x-forge-select
                        label="Tipo *"
                        wire:model="obj_type"
                        :options="collect($objTypes)->map(fn($t)=>['value'=>$t,'label'=>$t])->toArray()"
                    />
                </div>
                <x-forge-input label="Chave *" wire:model="obj_key" :error="$errors->first('obj_key')" placeholder="users.store" class="font-mono" required />
                <x-forge-input label="Label *" wire:model="obj_label" :error="$errors->first('obj_label')" placeholder="Criar usuário" required />
                <div class="flex items-center gap-6">
                    <x-forge-switch wire:model="obj_is_active" label="Objeto ativo" />
                    <x-forge-input label="Ordem" wire:model="obj_order" type="number" class="w-24" />
                </div>
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="saveObj" color="primary">Salvar</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal exclusão --}}
    <div x-data="{ open: @entangle('showDeleteModal').live }">
        <x-forge-modal title="Confirmar exclusão" size="sm">
            <p class="text-slate-600">
                @if ($deleteTarget === 'page')
                    Excluir esta página? Todos os objetos vinculados também serão removidos.
                @else
                    Excluir este objeto? As permissões de roles vinculadas serão removidas.
                @endif
            </p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="deleteConfirmed" color="danger">Excluir</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

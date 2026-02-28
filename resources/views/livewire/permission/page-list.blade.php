{{-- ptah::livewire.permission.page-list --}}
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Páginas e Objetos</h1>
        <p class="text-sm text-gray-500 mt-1">Cadastre as páginas do sistema e seus objetos (botões, campos, links) para controle de acesso.</p>
    </div>

    @if ($successMsg)
        <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
    @endif
    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- ── COLUNA: Páginas ──────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-700 dark:text-gray-200">Páginas</h2>
                <x-forge-button wire:click="createPage" color="primary" size="sm">+ Página</x-forge-button>
            </div>
            <x-forge-input wire:model.live.debounce.300ms="search" placeholder="Buscar página..." class="mb-3" />

            <x-forge-card flat>
                <div class="divide-y divide-gray-100 dark:divide-dark-3">
                    @forelse ($pageRows as $page)
                        <div
                            wire:click="selectPage({{ $page->id }}, '{{ addslashes($page->name) }}')"
                            class="flex items-center justify-between px-4 py-3 cursor-pointer transition-colors {{ $selectedPageId === $page->id ? 'bg-blue-50 dark:bg-blue-900/10 border-l-4 border-blue-500' : 'hover:bg-gray-50 dark:hover:bg-dark-2' }}"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    @if ($page->icon)
                                        <span class="text-gray-400 text-sm">{{ $page->icon }}</span>
                                    @endif
                                    <span class="font-medium text-gray-900 dark:text-white truncate">{{ $page->name }}</span>
                                    <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded font-mono shrink-0">{{ $page->page_objects_count }} obj</span>
                                </div>
                                <p class="text-xs text-gray-400 font-mono mt-0.5 truncate">{{ $page->slug }}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0 ml-2">
                                <button wire:click.stop="editPage({{ $page->id }})" class="text-gray-400 hover:text-blue-600 p-1 rounded">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click.stop="confirmDeletePage({{ $page->id }})" class="text-gray-400 hover:text-red-600 p-1 rounded">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-gray-400 text-sm">Nenhuma página cadastrada.</div>
                    @endforelse
                </div>
                @if ($pageRows->hasPages())
                    <div class="px-4 py-2 border-t border-gray-100 dark:border-dark-3">
                        <x-forge-pagination :paginator="$pageRows" />
                    </div>
                @endif
            </x-forge-card>
        </div>

        {{-- ── COLUNA: Objetos da página selecionada ──────────── --}}
        <div>
            @if ($selectedPageId)
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">
                        Objetos — <span class="text-blue-600">{{ $selectedPageName }}</span>
                    </h2>
                    <x-forge-button wire:click="createObj" color="primary" size="sm">+ Objeto</x-forge-button>
                </div>
                <x-forge-input wire:model.live.debounce.300ms="objSearch" placeholder="Buscar objeto..." class="mb-3" />

                <x-forge-card flat>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-dark-2 text-gray-500 text-xs uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left">Chave / Label</th>
                                    <th class="px-3 py-2 text-center">Tipo</th>
                                    <th class="px-3 py-2 text-center">Seção</th>
                                    <th class="px-3 py-2 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-dark-3">
                                @forelse ($objRows as $obj)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-dark-2">
                                        <td class="px-3 py-2">
                                            <p class="font-medium text-gray-900 dark:text-white text-xs">{{ $obj->obj_label }}</p>
                                            <p class="text-xs text-gray-400 font-mono">{{ $obj->obj_key }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $obj->obj_type }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs text-gray-500">{{ $obj->section }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button wire:click="editObj({{ $obj->id }})" class="text-gray-400 hover:text-blue-600 p-1 rounded">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <button wire:click="confirmDeleteObj({{ $obj->id }})" class="text-gray-400 hover:text-red-600 p-1 rounded">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-8 text-center text-gray-400 text-sm">Nenhum objeto nesta página.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($objRows?->hasPages())
                        <div class="px-3 py-2 border-t border-gray-100 dark:border-dark-3">
                            <x-forge-pagination :paginator="$objRows" />
                        </div>
                    @endif
                </x-forge-card>
            @else
                <div class="flex items-center justify-center h-full min-h-[200px]">
                    <div class="text-center text-gray-400">
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
                    <x-forge-input label="Ícone" wire:model="page_icon" placeholder="🏠 ou heroicon-o-home" />
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
            <p class="text-gray-600">
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

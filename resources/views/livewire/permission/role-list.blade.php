{{-- ptah::livewire.permission.role-list --}}
<div>
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">Roles / Perfis</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gerencie os perfis de acesso e suas permissões por objeto.</p>
    </div>

    @if ($successMsg) <x-forge-alert type="success" class="mb-3">{{ $successMsg }}</x-forge-alert> @endif
    @if ($errorMsg)   <x-forge-alert type="danger"  class="mb-3">{{ $errorMsg }}</x-forge-alert>   @endif

    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border shadow-sm rounded-xl bg-white border-slate-200">
        <button wire:click="create"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none select-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Role
        </button>
        <div class="flex-1 min-w-[180px] max-w-xs">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar role..."
                    class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
            </div>
        </div>
    </div>

    <div class="ptah-module-table overflow-x-auto border shadow-sm border-slate-200 rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nome</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Departamento</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Permissões</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="transition-colors hover:bg-slate-50/70 {{ $row->is_master ? 'bg-amber-50/60' : '' }}">
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                @if ($row->color)
                                    <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $row->color }}"></span>
                                @endif
                                <span class="font-medium text-slate-800">{{ $row->name }}</span>
                                @if ($row->is_master)
                                    <span class="text-xs font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">👑 MASTER</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-slate-500">{{ $row->department?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">{{ $row->permissions_count }} objetos</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                {{ $row->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openBind({{ $row->id }})"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-md transition-colors"
                                    title="Gerenciar permissões">
                                    🔑 Permissões
                                </button>
                                <button wire:click="edit({{ $row->id }})" class="transition-colors text-primary hover:text-primary/80" title="Editar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @if (!$row->is_master)
                                    <button wire:click="confirmDelete({{ $row->id }})" class="transition-colors text-danger hover:text-danger/80" title="Excluir">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Nenhum role encontrado</p>
                                    <p class="text-xs mt-0.5 text-slate-400">Adicione o primeiro perfil de acesso</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rows->hasPages())
        <div class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span>{{ $rows->firstItem() }}–{{ $rows->lastItem() }} de {{ $rows->total() }}</span>
            <div>{{ $rows->links() }}</div>
        </div>
    @endif

    {{-- Modal criar/editar role --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal :title="$isEditing ? 'Editar Role' : 'Novo Role'" size="md">
            <div class="space-y-4">
                <x-forge-input label="Nome *" wire:model="name" :error="$errors->first('name')" required />
                <x-forge-textarea label="Descrição" wire:model="description" rows="2" />
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input label="Cor (hex)" wire:model="color" placeholder="#6b7280" type="color" />
                    <x-forge-select label="Departamento" wire:model="department_id"
                        :options="$departments->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->prepend(['value'=>'','label'=>'Sem departamento'])->toArray()" />
                </div>
                <div class="flex items-center gap-6 pt-1">
                    <x-forge-switch wire:model="is_active" label="Role ativo" />
                    @if (!($editingId && \Ptah\Models\Role::find($editingId)?->is_master))
                        <x-forge-switch wire:model="is_master" label="Role MASTER (bypass total)" />
                    @else
                        <span class="text-xs text-amber-600 font-medium">👑 Este é o role MASTER</span>
                    @endif
                </div>
                @if ($is_master)
                    <x-forge-alert type="warn">⚠️ Roles MASTER têm acesso irrestrito. Apenas 1 role pode ser MASTER.</x-forge-alert>
                @endif
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="save" color="primary">Salvar</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal de bind de permissões --}}
    <div x-data="{ open: @entangle('showBindModal').live }">
        <x-forge-modal title="Gerenciar Permissões — {{ $bindingRoleName }}" size="xl">
            <div class="space-y-2 max-h-[60vh] overflow-y-auto">
                @php $currentPage = null; @endphp
                @foreach ($bindObjects as $i => $obj)
                    @if ($currentPage !== $obj['page_name'])
                        @php $currentPage = $obj['page_name']; @endphp
                        <div class="sticky top-0 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 uppercase tracking-wider rounded">
                            📄 {{ $obj['page_name'] }} — {{ $obj['section'] }}
                        </div>
                    @endif
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ $obj['obj_label'] }}</p>
                            <p class="text-xs text-slate-400 font-mono">{{ $obj['obj_key'] }} <span class="ml-1 text-slate-300">· {{ $obj['obj_type'] }}</span></p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-slate-400">Ler</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_read" class="rounded" />
                            </label>
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-slate-400">Criar</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_create" class="rounded" />
                            </label>
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-slate-400">Editar</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_update" class="rounded" />
                            </label>
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-slate-400">Excluir</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_delete" class="rounded" />
                            </label>
                        </div>
                    </div>
                @endforeach
                @if (empty($bindObjects))
                    <div class="py-8 text-center text-slate-400 text-sm">Nenhum objeto cadastrado. Acesse Páginas e cadastre os objetos primeiro.</div>
                @endif
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="saveBind" color="primary">Salvar Permissões</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal exclusão --}}
    <div x-data="{ open: @entangle('showDeleteModal').live }">
        <x-forge-modal title="Confirmar exclusão" size="sm">
            <p class="text-slate-600">Excluir este role? As permissões e vínculos com usuários serão removidos.</p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="delete" color="danger">Excluir</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

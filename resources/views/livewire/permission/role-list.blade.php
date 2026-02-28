{{-- ptah::livewire.permission.role-list --}}
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Roles / Perfis</h1>
        <p class="text-sm text-gray-500 mt-1">Gerencie os perfis de acesso e suas permissões por objeto.</p>
    </div>

    @if ($successMsg)
        <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
    @endif
    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-4 items-start sm:items-center justify-between">
        <x-forge-input wire:model.live.debounce.300ms="search" placeholder="Buscar role..." class="w-full sm:max-w-xs" />
        <x-forge-button wire:click="create" color="primary" class="shrink-0">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Novo Role
        </x-forge-button>
    </div>

    <x-forge-card flat>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-dark-2 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Nome</th>
                        <th class="px-4 py-3 text-left">Departamento</th>
                        <th class="px-4 py-3 text-center">Permissões</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-3">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-2 {{ $row->is_master ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if ($row->color)
                                        <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $row->color }}"></span>
                                    @endif
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $row->name }}</span>
                                    @if ($row->is_master)
                                        <span class="text-xs font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">👑 MASTER</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                                    {{ $row->permissions_count }} objetos
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $row->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-forge-button wire:click="openBind({{ $row->id }})" color="light" size="xs" title="Gerenciar permissões">
                                        🔑 Permissões
                                    </x-forge-button>
                                    <x-forge-button wire:click="edit({{ $row->id }})" color="light" size="xs">Editar</x-forge-button>
                                    @if (!$row->is_master)
                                        <x-forge-button wire:click="confirmDelete({{ $row->id }})" color="danger" size="xs">Excluir</x-forge-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">Nenhum role cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-dark-3">
                <x-forge-pagination :paginator="$rows" />
            </div>
        @endif
    </x-forge-card>

    {{-- Modal criar/editar role --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal :title="$isEditing ? 'Editar Role' : 'Novo Role'" size="md">
            <div class="space-y-4">
                <x-forge-input label="Nome *" wire:model="name" :error="$errors->first('name')" required />
                <x-forge-textarea label="Descrição" wire:model="description" rows="2" />
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input label="Cor (hex)" wire:model="color" placeholder="#6b7280" type="color" />
                    <x-forge-select
                        label="Departamento"
                        wire:model="department_id"
                        :options="$departments->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->prepend(['value'=>'','label'=>'Sem departamento'])->toArray()"
                    />
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
                    <x-forge-alert type="warn">
                        ⚠️ Roles MASTER têm acesso irrestrito a todos os recursos. Apenas 1 role pode ser MASTER.
                    </x-forge-alert>
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
                        <div class="sticky top-0 bg-gray-100 dark:bg-dark-3 px-3 py-1.5 text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider rounded">
                            📄 {{ $obj['page_name'] }} — {{ $obj['section'] }}
                        </div>
                    @endif
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-dark-2 border border-transparent hover:border-gray-200">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $obj['obj_label'] }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $obj['obj_key'] }}
                                <span class="ml-1 text-gray-300">· {{ $obj['obj_type'] }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-gray-400">Ler</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_read" class="rounded" />
                            </label>
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-gray-400">Criar</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_create" class="rounded" />
                            </label>
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-gray-400">Editar</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_update" class="rounded" />
                            </label>
                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                <span class="text-xs text-gray-400">Excluir</span>
                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_delete" class="rounded" />
                            </label>
                        </div>
                    </div>
                @endforeach

                @if (empty($bindObjects))
                    <div class="py-8 text-center text-gray-400 text-sm">
                        Nenhum objeto cadastrado. Cadastre páginas e objetos primeiro.
                    </div>
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
            <p class="text-gray-600">Excluir este role? As permissões e vínculos com usuários serão removidos.</p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="delete" color="danger">Excluir</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

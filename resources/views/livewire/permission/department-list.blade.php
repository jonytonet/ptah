{{-- ptah::livewire.permission.department-list --}}
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Departamentos</h1>
        <p class="text-sm text-gray-500 mt-1">Agrupe perfis/roles por departamento.</p>
    </div>

    @if ($successMsg)
        <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
    @endif
    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-4 items-start sm:items-center justify-between">
        <x-forge-input wire:model.live.debounce.300ms="search" placeholder="Buscar departamento..." class="w-full sm:max-w-xs" />
        <x-forge-button wire:click="create" color="primary" class="shrink-0">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Novo Departamento
        </x-forge-button>
    </div>

    <x-forge-card flat>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-dark-2 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left cursor-pointer" wire:click="sort('name')">
                            Nome @if($sort==='name')<span>{{ $direction==='asc'?'↑':'↓' }}</span>@endif
                        </th>
                        <th class="px-4 py-3 text-left">Descrição</th>
                        <th class="px-4 py-3 text-center">Roles</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-3">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-2">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-medium text-gray-700 bg-gray-100 px-2 py-0.5 rounded-full">
                                    {{ $row->roles_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $row->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-forge-button wire:click="edit({{ $row->id }})" color="light" size="xs">Editar</x-forge-button>
                                    <x-forge-button wire:click="confirmDelete({{ $row->id }})" color="danger" size="xs">Excluir</x-forge-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">Nenhum departamento cadastrado.</td>
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

    {{-- Modal criar/editar --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal :title="$isEditing ? 'Editar Departamento' : 'Novo Departamento'" size="md">
            <div class="space-y-4">
                <x-forge-input label="Nome *" wire:model="name" :error="$errors->first('name')" required />
                <x-forge-textarea label="Descrição" wire:model="description" rows="3" />
                <x-forge-switch wire:model="is_active" label="Departamento ativo" />
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="save" color="primary">Salvar</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal exclusão --}}
    <div x-data="{ open: @entangle('showDeleteModal').live }">
        <x-forge-modal title="Confirmar exclusão" size="sm">
            <p class="text-gray-600">Excluir este departamento? Roles vinculados perderão o departamento (nullable).</p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="delete" color="danger">Excluir</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

{{-- ptah::livewire.company.company-list --}}
<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Empresas</h1>
        <p class="text-sm text-gray-500 mt-1">Gerencie as empresas e filiais do sistema.</p>
    </div>

    {{-- Alertas --}}
    @if ($successMsg)
        <x-forge-alert type="success" class="mb-4" wire:key="success-msg">{{ $successMsg }}</x-forge-alert>
    @endif
    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4" wire:key="error-msg">{{ $errorMsg }}</x-forge-alert>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4 items-start sm:items-center justify-between">
        <x-forge-input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nome, e-mail ou CNPJ..."
            class="w-full sm:max-w-xs"
        />
        <x-forge-button wire:click="create" color="primary" class="shrink-0">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nova Empresa
        </x-forge-button>
    </div>

    {{-- Tabela --}}
    <x-forge-card flat>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-dark-2 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left cursor-pointer" wire:click="sort('name')">
                            Nome
                            @if($sort === 'name') <span class="ml-1">{{ $direction === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-left">E-mail</th>
                        <th class="px-4 py-3 text-left">CNPJ / Tax</th>
                        <th class="px-4 py-3 text-center">Padrão</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-3">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-2 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $row->getLogoUrl() }}" alt="" class="w-8 h-8 rounded-full object-cover">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $row->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs">
                                {{ $row->tax_id ? strtoupper($row->tax_type ?? '') . ' ' . $row->tax_id : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($row->is_default)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
                                        ⭐ Padrão
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $row->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-forge-button wire:click="edit({{ $row->id }})" color="light" size="xs">
                                        Editar
                                    </x-forge-button>
                                    @if (!$row->is_default)
                                        <x-forge-button wire:click="confirmDelete({{ $row->id }})" color="danger" size="xs">
                                            Excluir
                                        </x-forge-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                @if ($search)
                                    Nenhuma empresa encontrada para "<strong>{{ $search }}</strong>".
                                @else
                                    Nenhuma empresa cadastrada ainda.
                                @endif
                            </td>
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

    {{-- Modal criar / editar --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal :title="$isEditing ? 'Editar Empresa' : 'Nova Empresa'" size="lg">
            <form wire:submit="save" class="space-y-4">
                <x-forge-input
                    label="Nome *"
                    wire:model="name"
                    :error="$errors->first('name')"
                    placeholder="Razão Social"
                    required
                />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-forge-input
                        label="E-mail"
                        type="email"
                        wire:model="email"
                        :error="$errors->first('email')"
                        placeholder="contato@empresa.com"
                    />
                    <x-forge-input
                        label="Telefone"
                        wire:model="phone"
                        placeholder="(00) 00000-0000"
                    />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-forge-select
                        label="Tipo de documento"
                        wire:model="tax_type"
                        :options="collect(['cnpj','cpf','ein','vat','other'])->map(fn($v) => ['value'=>$v,'label'=>strtoupper($v)])->toArray()"
                    />
                    <x-forge-input
                        label="Número do documento"
                        wire:model="tax_id"
                        :error="$errors->first('tax_id')"
                        placeholder="00.000.000/0001-00"
                    />
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <x-forge-switch wire:model="is_active" label="Empresa ativa" />
                    <x-forge-switch wire:model="is_default" label="Empresa padrão" />
                </div>
            </form>

            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="save" color="primary" wire:loading.attr="disabled">
                    <span wire:loading wire:target="save">
                        <x-forge-spinner size="sm" /> Salvando...
                    </span>
                    <span wire:loading.remove wire:target="save">Salvar</span>
                </x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal confirmar exclusão --}}
    <div x-data="{ open: @entangle('showDeleteModal').live }">
        <x-forge-modal title="Confirmar exclusão" size="sm">
            <p class="text-gray-600 dark:text-gray-300">
                Tem certeza que deseja excluir esta empresa? Esta ação não pode ser desfeita.
            </p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="delete" color="danger">Excluir</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

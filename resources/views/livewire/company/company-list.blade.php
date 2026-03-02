{{-- ptah::livewire.company.company-list --}}
<div>
    {{-- Header --}}
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">Empresas</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gerencie as empresas e filiais do sistema.</p>
    </div>

    {{-- Alertas --}}
    @if ($successMsg)
        <x-forge-alert type="success" class="mb-3" wire:key="success-msg">{{ $successMsg }}</x-forge-alert>
    @endif
    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-3" wire:key="error-msg">{{ $errorMsg }}</x-forge-alert>
    @endif

    {{-- Toolbar --}}
    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border shadow-sm rounded-xl bg-white border-slate-200">
        <button wire:click="create"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none select-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nova Empresa
        </button>
        <div class="flex-1 min-w-[180px] max-w-xs">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nome, e-mail ou CNPJ..."
                    class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="ptah-module-table overflow-x-auto border shadow-sm border-slate-200 rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 w-12">Sigla</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 cursor-pointer" wire:click="sort('name')">
                        <span class="flex items-center gap-1">Nome @if($sort === 'name')<span class="text-indigo-500">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif</span>
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">E-mail</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">CNPJ / Tax</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Padrão</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php
                        $badgeColors = ['bg-indigo-600','bg-amber-500','bg-emerald-600','bg-rose-600','bg-violet-600','bg-sky-600'];
                        $badgeColor  = $badgeColors[$row->id % count($badgeColors)];
                    @endphp
                    <tr class="transition-colors hover:bg-slate-50/70">
                        <td class="px-3 py-2.5 text-center">
                            <div class="w-8 h-8 rounded-lg {{ $badgeColor }} flex items-center justify-center mx-auto shadow-sm">
                                <span class="text-white font-bold text-[10px] tracking-wide leading-none">
                                    {{ $row->getLabelDisplay() }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-800">{{ $row->name }}</span>
                                @if($row->is_default)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700">PADRÃO</span>
                                @endif
                            </div>
                        <td class="px-3 py-2.5 text-slate-500">{{ $row->email ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-slate-500 font-mono text-xs">{{ $row->tax_id ? strtoupper($row->tax_type ?? '') . ' ' . $row->tax_id : '—' }}</td>

                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                {{ $row->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="edit({{ $row->id }})" class="transition-colors text-primary hover:text-primary/80" title="Editar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @if (!$row->is_default)
                                    <button wire:click="confirmDelete({{ $row->id }})" class="transition-colors text-danger hover:text-danger/80" title="Excluir">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 8h6"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Nenhuma empresa encontrada</p>
                                    <p class="text-xs mt-0.5 text-slate-400">@if($search)Ajuste o filtro de busca@else Adicione a primeira empresa @endif</p>
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
        <div class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span>{{ $rows->firstItem() }}–{{ $rows->lastItem() }} de {{ $rows->total() }}</span>
            <div>{{ $rows->links() }}</div>
        </div>
    @endif

    {{-- Modal criar / editar --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal :title="$isEditing ? 'Editar Empresa' : 'Nova Empresa'" size="lg">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <x-forge-input label="Nome *" wire:model.blur="name" :error="$errors->first('name')" placeholder="Razão Social" required />
                    </div>
                    <div>
                        <x-forge-input label="Sigla (4 chars)" wire:model.blur="label" :error="$errors->first('label')" placeholder="ACME" maxlength="4" />
                        <p class="mt-1 text-[10px] text-slate-400">Exibida no badge do menu</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-forge-input label="E-mail" type="email" wire:model.blur="email" :error="$errors->first('email')" placeholder="contato@empresa.com" />
                    <x-forge-input label="Telefone" wire:model.blur="phone" placeholder="(00) 00000-0000" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-forge-select label="Tipo de documento" wire:model="tax_type"
                        :options="collect(['cnpj','cpf','ein','vat','other'])->map(fn($v) => ['value'=>$v,'label'=>strtoupper($v)])->toArray()" />
                    <x-forge-input label="Número do documento" wire:model.blur="tax_id" :error="$errors->first('tax_id')" placeholder="00.000.000/0001-00" />
                </div>
                <div class="flex items-center gap-6 pt-2">
                    <x-forge-switch wire:model="is_active" label="Empresa ativa" />
                    <x-forge-switch wire:model="is_default" label="Empresa padrão" />
                </div>
            </form>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="save" color="primary" wire:loading.attr="disabled">
                    <span wire:loading wire:target="save"><x-forge-spinner size="sm" /> Salvando...</span>
                    <span wire:loading.remove wire:target="save">Salvar</span>
                </x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal confirmar exclusão --}}
    <div x-data="{ open: @entangle('showDeleteModal').live }">
        <x-forge-modal title="Confirmar exclusão" size="sm">
            <p class="text-slate-600">Tem certeza que deseja excluir esta empresa? Esta ação não pode ser desfeita.</p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                <x-forge-button wire:click="delete" color="danger">Excluir</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

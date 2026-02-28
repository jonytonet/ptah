{{-- ptah::livewire.permission.audit-list --}}
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Auditoria de Permissões</h1>
        <p class="text-sm text-gray-500 mt-1">Log de acessos concedidos e negados. Somente leitura.</p>
    </div>

    {{-- Filtros --}}
    <x-forge-card flat class="mb-4">
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <x-forge-input wire:model.live.debounce.300ms="search" placeholder="Buscar recurso, IP, usuário..." class="md:col-span-2" />
            <x-forge-select
                wire:model.live="filterResult"
                :options="[
                    ['value'=>'','label'=>'Todos os resultados'],
                    ['value'=>'granted','label'=>'✅ Concedido'],
                    ['value'=>'denied','label'=>'❌ Negado'],
                ]"
            />
            <x-forge-select
                wire:model.live="filterAction"
                :options="[
                    ['value'=>'','label'=>'Todas as ações'],
                    ['value'=>'create','label'=>'Criar'],
                    ['value'=>'read','label'=>'Ler'],
                    ['value'=>'update','label'=>'Editar'],
                    ['value'=>'delete','label'=>'Excluir'],
                ]"
            />
            <div class="flex items-end">
                <x-forge-button wire:click="clearFilters" color="light" size="sm" class="w-full">Limpar</x-forge-button>
            </div>
        </div>
        <div class="px-4 pb-4 grid grid-cols-2 gap-3">
            <x-forge-input label="De:" wire:model.live="dateFrom" type="date" />
            <x-forge-input label="Até:" wire:model.live="dateTo"   type="date" />
        </div>
    </x-forge-card>

    <x-forge-card flat>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-dark-2 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Data/Hora</th>
                        <th class="px-4 py-3 text-left">Usuário</th>
                        <th class="px-4 py-3 text-left">Recurso</th>
                        <th class="px-4 py-3 text-center">Ação</th>
                        <th class="px-4 py-3 text-center">Resultado</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-3">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-2 {{ $row->result === 'denied' ? 'bg-red-50/30 dark:bg-red-900/5' : '' }}">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">
                                {{ $row->created_at?->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-gray-700 dark:text-gray-300 font-mono text-xs">
                                    #{{ $row->user_id ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row->resource_key ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ match($row->action) {
                                        'create' => 'bg-green-100 text-green-700',
                                        'read'   => 'bg-blue-100 text-blue-700',
                                        'update' => 'bg-amber-100 text-amber-700',
                                        'delete' => 'bg-red-100 text-red-700',
                                        default  => 'bg-gray-100 text-gray-700',
                                    } }}">
                                    {{ ucfirst($row->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($row->result === 'granted')
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">✅ Concedido</span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">❌ Negado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $row->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                @if ($search || $filterResult || $filterAction)
                                    Nenhum registro encontrado com os filtros aplicados.
                                @else
                                    Nenhum registro de auditoria. Ative com <code class="font-mono text-xs bg-gray-100 px-1 rounded">PTAH_PERMISSION_AUDIT=true</code> no .env.
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
</div>

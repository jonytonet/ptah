{{-- ptah::livewire.permission.audit-list --}}
<div>
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">Auditoria de Permissões</h1>
        <p class="text-sm text-slate-500 mt-0.5">Log de acessos concedidos e negados. Somente leitura.</p>
    </div>

    {{-- Barra de filtros --}}
    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border shadow-sm rounded-xl bg-white border-slate-200">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar recurso, IP, usuário..."
                    class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
            </div>
        </div>
        <select wire:model.live="filterResult"
            class="py-2 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all">
            <option value="">Todos os resultados</option>
            <option value="granted">✅ Concedido</option>
            <option value="denied">❌ Negado</option>
        </select>
        <select wire:model.live="filterAction"
            class="py-2 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all">
            <option value="">Todas as ações</option>
            <option value="create">Criar</option>
            <option value="read">Ler</option>
            <option value="update">Editar</option>
            <option value="delete">Excluir</option>
        </select>
        <input wire:model.live="dateFrom" type="date"
            class="py-2 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"
            title="De"/>
        <input wire:model.live="dateTo" type="date"
            class="py-2 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"
            title="Até"/>
        <button wire:click="clearFilters"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors select-none">
            Limpar
        </button>
    </div>

    <div class="ptah-module-table overflow-x-auto border shadow-sm border-slate-200 rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Data/Hora</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Usuário</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Recurso</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Ação</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Resultado</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="transition-colors hover:bg-slate-50/70 {{ $row->result === 'denied' ? 'bg-red-50/30' : '' }}">
                        <td class="px-3 py-2.5 text-slate-500 whitespace-nowrap text-xs">
                            {{ $row->created_at?->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-slate-600 font-mono text-xs">#{{ $row->user_id ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="font-mono text-xs text-slate-600">{{ $row->resource_key ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ match($row->action) {
                                    'create' => 'bg-green-100 text-green-700',
                                    'read'   => 'bg-blue-100 text-blue-700',
                                    'update' => 'bg-amber-100 text-amber-700',
                                    'delete' => 'bg-red-100 text-red-700',
                                    default  => 'bg-slate-100 text-slate-600',
                                } }}">
                                {{ ucfirst($row->action) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if ($row->result === 'granted')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">✅ Concedido</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">❌ Negado</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-400 font-mono">{{ $row->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <div>
                                    @if ($search || $filterResult || $filterAction)
                                        <p class="text-sm font-semibold text-slate-700">Nenhum registro encontrado</p>
                                        <p class="text-xs mt-0.5 text-slate-400">Tente ajustar os filtros aplicados.</p>
                                    @else
                                        <p class="text-sm font-semibold text-slate-700">Nenhum registro de auditoria</p>
                                        <p class="text-xs mt-0.5 text-slate-400">Ative com <code class="font-mono bg-slate-100 px-1 rounded">PTAH_PERMISSION_AUDIT=true</code> no .env.</p>
                                    @endif
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
</div>

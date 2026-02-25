{{--
    forge-table — Ptah Forge
    Props:
      - headers     : array [ ['key' => '', 'label' => ''], ... ]
      - rows        : array de objetos/arrays
      - searchable  : boolean - busca client-side Alpine
      - sortable    : boolean - ordenação client-side Alpine
      - emptyMessage: string
    Slots: actions (botões de ação por linha)
    Requer Alpine.js
--}}
@props([
    'headers'      => [],
    'rows'         => [],
    'searchable'   => false,
    'sortable'     => false,
    'emptyMessage' => 'Nenhum registro encontrado.',
])

<div
    x-data="{
        search: '',
        sortField: '',
        sortDir: 'asc',
        get filteredRows() {
            let rows = {{ json_encode($rows) }};
            if (this.search) {
                const q = this.search.toLowerCase();
                rows = rows.filter(row =>
                    Object.values(row).some(v => String(v).toLowerCase().includes(q))
                );
            }
            if (this.sortField) {
                rows = [...rows].sort((a, b) => {
                    const av = String(a[this.sortField] ?? '').toLowerCase();
                    const bv = String(b[this.sortField] ?? '').toLowerCase();
                    return this.sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
                });
            }
            return rows;
        },
        toggleSort(field) {
            if (this.sortField === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDir = 'asc';
            }
        }
    }"
    {{ $attributes }}
>
    {{-- Busca --}}
    @if($searchable)
        <div class="mb-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="search"
                    x-model="search"
                    placeholder="Buscar..."
                    class="w-full max-w-xs pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl
                           focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
                />
            </div>
        </div>
    @endif

    {{-- Mobile: Cards --}}
    <div class="block md:hidden space-y-3">
        <template x-for="(row, rowIndex) in filteredRows" :key="rowIndex">
            <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm space-y-2">
                @foreach($headers as $header)
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 flex-shrink-0">{{ $header['label'] }}:</span>
                        <span class="text-sm text-dark text-right" x-text="row['{{ $header['key'] }}'] ?? '-'"></span>
                    </div>
                @endforeach
                @isset($actions)
                    <div class="pt-2 border-t border-gray-100">{{ $actions }}</div>
                @endisset
            </div>
        </template>
        <div x-show="filteredRows.length === 0" class="text-center py-8 text-gray-400 text-sm">
            {{ $emptyMessage }}
        </div>
    </div>

    {{-- Desktop: Tabela --}}
    <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-100">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                    @foreach($headers as $header)
                        <th
                            @if($sortable) @click="toggleSort('{{ $header['key'] }}')" @endif
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider
                                   {{ $sortable ? 'cursor-pointer hover:text-primary select-none' : '' }}"
                        >
                            <span class="flex items-center gap-1">
                                {{ $header['label'] }}
                                @if($sortable)
                                    <span x-show="sortField === '{{ $header['key'] }}'" class="text-primary">
                                        <span x-show="sortDir === 'asc'">↑</span>
                                        <span x-show="sortDir === 'desc'">↓</span>
                                    </span>
                                @endif
                            </span>
                        </th>
                    @endforeach
                    @isset($actions)
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                    @endisset
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-50">
                <template x-for="(row, rowIndex) in filteredRows" :key="rowIndex">
                    <tr class="hover:bg-primary/5 transition-colors">
                        @foreach($headers as $header)
                            <td class="px-4 py-3 text-sm text-dark" x-text="row['{{ $header['key'] }}'] ?? '-'"></td>
                        @endforeach
                        @isset($actions)
                            <td class="px-4 py-3 text-right">{{ $actions }}</td>
                        @endisset
                    </tr>
                </template>
                <tr x-show="filteredRows.length === 0">
                    <td colspan="{{ count($headers) + (isset($actions) ? 1 : 0) }}"
                        class="px-4 py-8 text-center text-sm text-gray-400">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

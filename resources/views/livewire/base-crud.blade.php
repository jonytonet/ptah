<div class="ptah-base-crud" wire:key="base-crud-{{ $crudTitle }}">

    {{-- ── Mensagens de sessão ──────────────────────────────────────────── --}}
    @if (session('crud-success') || $exportStatus)
        <x-forge-alert type="success" :dismissible="true" class="mb-3">
            {{ session('crud-success', $exportStatus) }}
        </x-forge-alert>
    @endif

    @if (!empty($crudConfig))

    {{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">

        {{-- Botão Novo --}}
        @if ($permissions['showCreateButton'] ?? true)
            @if (!($permissions['create'] ?? null) || (auth()->check() && auth()->user()->can($permissions['create'])))
                <x-forge-button wire:click="openCreate" color="primary" size="sm"
                    icon='<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'>
                    Novo
                </x-forge-button>
            @endif
        @endif

        {{-- Busca Global --}}
        <div class="flex-1 min-w-[200px] max-w-xs">
            <input
                wire:model.live.debounce.400ms="search"
                type="text"
                placeholder="Buscar..."
                class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40"
            />
        </div>

        <div class="flex items-center gap-2">

            {{-- Botão Filtros --}}
            @if (!empty($crudConfig['customFilters']) || !empty($crudConfig['dateRangeFilters']))
                <x-forge-button wire:click="toggleFilters" color="{{ $showFilters ? 'primary' : 'dark' }}" flat size="sm">
                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filtros
                    @php
                        $activeFilterCount = count(array_filter($filters));
                    @endphp
                    @if($activeFilterCount > 0)
                        <x-forge-badge class="ml-1" color="danger">{{ $activeFilterCount }}</x-forge-badge>
                    @endif
                </x-forge-button>
            @endif

            {{-- Lixeira --}}
            @if ($permissions['showTrashButton'] ?? true)
                <x-forge-button wire:click="toggleTrashed" color="{{ $showTrashed ? 'danger' : 'dark' }}" flat size="sm" title="{{ $showTrashed ? 'Ver ativos' : 'Ver excluídos' }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </x-forge-button>
            @endif

            {{-- Exportação --}}
            @if (!empty($exportCfg['enabled']))
                <div class="relative" x-data="{ open: @entangle('showExportMenu') }">
                    <x-forge-button @click="open = !open" color="dark" flat size="sm">
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Exportar
                    </x-forge-button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-20 min-w-[130px]">
                        @foreach ($exportCfg['formats'] ?? ['excel'] as $fmt)
                            <button wire:click="export('{{ $fmt }}')"
                                class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 capitalize">
                                {{ strtoupper($fmt) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- View density --}}
            <div class="flex items-center gap-1">
                @foreach (['compact' => '≡', 'comfortable' => '☰', 'spacious' => '⊟'] as $density => $icon)
                    <button wire:click="$set('viewDensity', '{{ $density }}')"
                        class="px-2 py-1 text-xs rounded {{ $viewDensity === $density ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $icon }}
                    </button>
                @endforeach
            </div>

            {{-- Per page --}}
            <select wire:model.live="perPage" class="text-sm border border-gray-300 rounded px-2 py-1">
                @foreach ([10, 15, 25, 50, 100] as $n)
                    <option value="{{ $n }}">{{ $n }} / pág.</option>
                @endforeach
            </select>

        </div>
    </div>

    {{-- ── Painel de Filtros ────────────────────────────────────────────── --}}
    @if ($showFilters)
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3">

                {{-- Filtros das colunas filtráveis --}}
                @foreach ($crudConfig['cols'] ?? [] as $col)
                    @if (($col['colsIsFilterable'] ?? 'N') === 'S' && ($col['colsTipo'] ?? '') !== 'action')
                        @php
                            $cfField = $col['colsNomeFisico'];
                            $cfLabel = $col['colsNomeLogico'] ?? $cfField;
                            $cfTipo  = $col['colsTipo'] ?? 'text';
                        @endphp

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $cfLabel }}</label>

                            @if ($cfTipo === 'select' && !empty($col['colsSelect']))
                                <select wire:model.live="filters.{{ $cfField }}"
                                    class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-primary/40 focus:outline-none">
                                    <option value="">-- Todos --</option>
                                    @foreach ($col['colsSelect'] as $label => $val)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                            @elseif ($cfTipo === 'date' && isset($crudConfig['dateRangeFilters'][$cfField]))
                                <div class="flex gap-1">
                                    <input type="date" wire:model.live="dateRanges.{{ $cfField }}_from"
                                        class="flex-1 text-sm border border-gray-300 rounded px-2 py-1.5" />
                                    <input type="date" wire:model.live="dateRanges.{{ $cfField }}_to"
                                        class="flex-1 text-sm border border-gray-300 rounded px-2 py-1.5" />
                                </div>

                            @elseif ($cfTipo === 'searchdropdown')
                                <div class="relative" x-data="{ open: false }">
                                    <input type="text"
                                        wire:model.live.debounce.300ms="sdSearches.{{ $cfField }}"
                                        wire:keyup="searchDropdown('{{ $cfField }}', $event.target.value)"
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        placeholder="Buscar {{ $cfLabel }}..."
                                        class="w-full text-sm border border-gray-300 rounded px-2 py-1.5" />
                                    @if (!empty($sdResults[$cfField]))
                                        <div x-show="open" class="absolute z-30 w-full bg-white border border-gray-200 rounded shadow-lg max-h-48 overflow-y-auto">
                                            @foreach ($sdResults[$cfField] as $opt)
                                                <button type="button"
                                                    wire:click="selectDropdownOption('{{ $cfField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                    @click="open = false"
                                                    class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50">
                                                    {{ $opt['label'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                            @else
                                <input type="{{ $cfTipo === 'number' ? 'number' : 'text' }}"
                                    wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                    placeholder="{{ $cfLabel }}..."
                                    class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-primary/40 focus:outline-none" />
                            @endif
                        </div>
                    @endif
                @endforeach

                {{-- CustomFilters --}}
                @foreach ($crudConfig['customFilters'] ?? [] as $cf)
                    @php
                        $cfField = $cf['field'] ?? '';
                        $cfLabel = $cf['field'] ?? '';
                        $cfTipo  = ($cf['useSearchDropDown'] ?? 'N') === 'S' ? 'searchdropdown' : 'text';
                    @endphp
                    @if ($cfField)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $cfLabel }}</label>
                            @if ($cfTipo === 'searchdropdown')
                                <div class="relative" x-data="{ open: false }">
                                    <input type="text"
                                        wire:keyup="searchDropdown('cf_{{ $cfField }}', $event.target.value)"
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        placeholder="Buscar {{ $cfLabel }}..."
                                        class="w-full text-sm border border-gray-300 rounded px-2 py-1.5" />
                                    @if (!empty($sdResults['cf_' . $cfField]))
                                        <div x-show="open" class="absolute z-30 w-full bg-white border border-gray-200 rounded shadow-lg max-h-48 overflow-y-auto">
                                            @foreach ($sdResults['cf_' . $cfField] as $opt)
                                                <button wire:click="selectDropdownOption('{{ $cfField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                    @click="open = false"
                                                    class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50">
                                                    {{ $opt['label'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                <input type="text"
                                    wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                    placeholder="{{ $cfLabel }}..."
                                    class="w-full text-sm border border-gray-300 rounded px-2 py-1.5" />
                            @endif
                        </div>
                    @endif
                @endforeach

            </div>

            {{-- Ações de filtro --}}
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200">
                <x-forge-button wire:click="clearFilters" color="dark" flat size="sm">
                    Limpar Filtros
                </x-forge-button>

                {{-- Salvar filtro com nome --}}
                <div class="flex items-center gap-2" x-data="{ saving: false }">
                    <template x-if="saving">
                        <div class="flex gap-2">
                            <input type="text" wire:model="savingFilterName"
                                placeholder="Nome do filtro"
                                class="text-sm border border-gray-300 rounded px-2 py-1" />
                            <x-forge-button wire:click="saveNamedFilter(savingFilterName)" color="primary" size="sm">
                                Salvar
                            </x-forge-button>
                            <x-forge-button @click="saving = false" color="dark" flat size="sm">
                                Cancelar
                            </x-forge-button>
                        </div>
                    </template>
                    <template x-if="!saving">
                        <x-forge-button @click="saving = true" color="primary" flat size="sm">
                            Salvar Filtro
                        </x-forge-button>
                    </template>
                </div>
            </div>

            {{-- Filtros salvos --}}
            @if (!empty($savedFilters))
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="text-xs text-gray-500">Filtros salvos:</span>
                    @foreach (array_keys($savedFilters) as $filterName)
                        <div class="flex items-center gap-1">
                            <button wire:click="loadNamedFilter('{{ $filterName }}')"
                                class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded hover:bg-primary/20">
                                {{ $filterName }}
                            </button>
                            <button wire:click="deleteNamedFilter('{{ $filterName }}')"
                                class="text-xs text-danger hover:text-danger/80">✕</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ── Tabela ──────────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="{{ $crudConfig['tableClass'] ?? 'table' }} w-full text-sm
            @if($viewDensity === 'compact') text-xs @elseif($viewDensity === 'spacious') text-base @endif">

            <thead class="{{ $crudConfig['theadClass'] ?? 'bg-gray-50 border-b border-gray-200' }}">
                <tr>
                    @foreach ($crudConfig['cols'] ?? [] as $col)
                        @if (($col['colsTipo'] ?? '') !== 'action')
                            @php
                                $colField   = $col['colsNomeFisico'];
                                $colSortBy  = $col['colsOrderBy'] ?? $colField;
                                $colLabel   = $col['colsNomeLogico'] ?? $colField;
                                $colAlign   = $col['colsAlign'] ?? 'text-start';
                                $isSortable = !str_contains($colField, '.') && empty($col['colsMetodoCustom']);
                            @endphp
                            <th class="px-3 py-2 font-semibold text-gray-700 whitespace-nowrap {{ $colAlign }}
                                {{ $isSortable ? 'cursor-pointer select-none hover:bg-gray-100' : '' }}"
                                @if($isSortable) wire:click="sortBy('{{ $colSortBy }}')" @endif>
                                {{ $colLabel }}
                                @if ($sort === $colSortBy)
                                    <span class="ml-1 text-primary">{{ $direction === 'ASC' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                        @endif
                    @endforeach

                    {{-- Colunas action --}}
                    @foreach ($crudConfig['cols'] ?? [] as $col)
                        @if (($col['colsTipo'] ?? '') === 'action')
                            <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">
                                {{ $col['colsNomeLogico'] ?? 'Ação' }}
                            </th>
                        @endif
                    @endforeach

                    {{-- Coluna de ações padrão --}}
                    @if (($permissions['showEditButton'] ?? true) || ($permissions['showDeleteButton'] ?? true))
                        <th class="px-3 py-2 text-center font-semibold text-gray-700">Ações</th>
                    @endif
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    @php
                        $rowStyle = $this->getRowStyle($row);
                        $rowLink  = null;
                        if (!empty($crudConfig['configLinkLinha'])) {
                            $id = $row->id ?? null;
                            $rowLink = $id ? str_replace('%id%', $id, $crudConfig['configLinkLinha']) : null;
                        }
                    @endphp

                    <tr style="{{ $rowStyle }}"
                        class="hover:bg-gray-50 transition-colors
                            @if($viewDensity === 'compact') @elseif($viewDensity === 'spacious') @endif
                            {{ $rowLink ? 'cursor-pointer' : '' }}"
                        @if($rowLink) @click="window.location='{{ $rowLink }}'" @endif
                        wire:key="row-{{ $row->id ?? $loop->index }}">

                        {{-- Células de dados --}}
                        @foreach ($crudConfig['cols'] ?? [] as $col)
                            @if (($col['colsTipo'] ?? '') !== 'action')
                                @php
                                    $cellAlign = $col['colsAlign'] ?? 'text-start';
                                    $reverse   = ($col['colsReverse'] ?? 'N') === 'S';
                                @endphp
                                <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} {{ $cellAlign }} {{ $reverse ? 'font-medium' : '' }}">
                                    {!! $this->formatCell($col, $row) !!}
                                </td>
                            @endif
                        @endforeach

                        {{-- Colunas action --}}
                        @foreach ($crudConfig['cols'] ?? [] as $col)
                            @if (($col['colsTipo'] ?? '') === 'action')
                                <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} text-center">
                                    @php
                                        $actionCall  = $col['actionCall'] ?? '';
                                        $actionIcon  = $col['actionIcone'] ?? '';
                                        $actionJs    = str_replace('%id%', $row->id ?? 0, $actionCall);
                                    @endphp
                                    @if ($actionJs)
                                        <button onclick="{{ $actionJs }}"
                                            class="text-primary hover:text-primary/80 transition-colors"
                                            title="{{ $col['colsNomeLogico'] ?? '' }}">
                                            @if ($actionIcon)
                                                <i class="{{ $actionIcon }}"></i>
                                            @else
                                                ▶
                                            @endif
                                        </button>
                                    @endif
                                </td>
                            @endif
                        @endforeach

                        {{-- Botões de ação padrão --}}
                        @if (($permissions['showEditButton'] ?? true) || ($permissions['showDeleteButton'] ?? true))
                            <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">

                                    {{-- Editar --}}
                                    @if ($permissions['showEditButton'] ?? true)
                                        @if (!($permissions['edit'] ?? null) || (auth()->check() && auth()->user()->can($permissions['edit'])))
                                            <button wire:click="openEdit({{ $row->id ?? 0 }})" wire:loading.attr="disabled"
                                                class="text-primary hover:text-primary/80 transition-colors" title="Editar">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    @endif

                                    {{-- Excluir / Restaurar --}}
                                    @if ($showTrashed && method_exists($row, 'trashed') && $row->trashed())
                                        <button wire:click="restoreRecord({{ $row->id ?? 0 }})"
                                            class="text-success hover:text-success/80 transition-colors" title="Restaurar">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    @elseif ($permissions['showDeleteButton'] ?? true)
                                        @if (!($permissions['delete'] ?? null) || (auth()->check() && auth()->user()->can($permissions['delete'])))
                                            <button wire:click="confirmDelete({{ $row->id ?? 0 }})"
                                                class="text-danger hover:text-danger/80 transition-colors" title="Excluir">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        @endif
                                    @endif

                                </div>
                            </td>
                        @endif

                    </tr>
                @empty
                    <tr>
                        <td colspan="99" class="px-6 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Nenhum registro encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            {{-- Totalizadores --}}
            @if (!empty($totData))
                <tfoot class="bg-gray-50 border-t-2 border-gray-300 font-semibold">
                    <tr>
                        @foreach ($crudConfig['cols'] ?? [] as $col)
                            @if (($col['colsTipo'] ?? '') !== 'action')
                                @php $totVal = $totData[$col['colsNomeFisico'] ?? ''] ?? null; @endphp
                                <td class="px-3 py-2 {{ $col['colsAlign'] ?? 'text-start' }}">
                                    @if ($totVal !== null)
                                        @if (($col['colsHelper'] ?? '') === 'currencyFormat')
                                            R$ {{ number_format((float)$totVal, 2, ',', '.') }}
                                        @else
                                            {{ $totVal }}
                                        @endif
                                    @endif
                                </td>
                            @endif
                        @endforeach
                        <td></td>
                    </tr>
                </tfoot>
            @endif

        </table>
    </div>

    {{-- ── Paginação ────────────────────────────────────────────────────── --}}
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
        <span>
            Exibindo {{ $rows->firstItem() ?? 0 }}&ndash;{{ $rows->lastItem() ?? 0 }}
            de {{ $rows->total() }} registros
        </span>
        <div>
            {{ $rows->links('ptah::components.forge-pagination') }}
        </div>
    </div>

    @else
        {{-- Sem configuração --}}
        <x-forge-alert type="warning">
            Configuração de BaseCrud não encontrada para <strong>{{ $model }}</strong>.
            Execute <code>php artisan ptah:forge {{ $model }}</code> para gerar.
        </x-forge-alert>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ── Modal Criar / Editar ─────────────────────────────────────────── --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center"
             x-data x-on:keydown.escape.window="$wire.closeModal()">

            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Painel do modal --}}
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col mx-4">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">
                        {{ $editingId ? 'Editar' : 'Novo' }} {{ $crudTitle }}
                    </h2>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Erro geral --}}
                @if (!empty($formErrors['_general']))
                    <div class="mx-6 mt-4">
                        <x-forge-alert type="danger">{{ $formErrors['_general'] }}</x-forge-alert>
                    </div>
                @endif

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        @foreach ($formCols as $col)
                            @php
                                $fField    = $col['colsNomeFisico'];
                                $fLabel    = $col['colsNomeLogico'] ?? $fField;
                                $fTipo     = $col['colsTipo'] ?? 'text';
                                $fRequired = ($col['colsRequired'] ?? 'N') === 'S';
                                $fError    = $formErrors[$fField] ?? null;
                                $fMask     = $col['colsMask'] ?? null;
                                $colSpan   = in_array($fTipo, ['text']) && ($col['colsAlign'] ?? '') === 'text-start' ? '' : '';
                            @endphp

                            <div class="{{ $fTipo === 'searchdropdown' ? 'relative' : '' }}">

                                @if ($fTipo === 'select' && !empty($col['colsSelect']))
                                    {{-- Select --}}
                                    <x-forge-select
                                        name="{{ $fField }}"
                                        label="{{ $fLabel }}"
                                        :required="$fRequired"
                                        :error="$fError"
                                        :selected="$formData[$fField] ?? null"
                                        :options="collect($col['colsSelect'])->map(fn($v, $k) => ['value' => $v, 'label' => $k])->values()->toArray()"
                                        wire:model="formData.{{ $fField }}"
                                    />

                                @elseif ($fTipo === 'searchdropdown')
                                    {{-- SearchDropdown --}}
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ $fLabel }}{{ $fRequired ? ' *' : '' }}
                                    </label>
                                    <div x-data="{ open: false }">
                                        <input type="text"
                                            value="{{ $sdLabels[$fField] ?? ($formData[$fField] ?? '') }}"
                                            wire:keyup="searchDropdown('{{ $fField }}', $event.target.value)"
                                            @input="open = true"
                                            @click.outside="open = false"
                                            placeholder="Buscar {{ $fLabel }}..."
                                            class="w-full px-3 py-2 border {{ $fError ? 'border-danger' : 'border-gray-300' }} rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
                                        />
                                        <input type="hidden" wire:model="formData.{{ $fField }}" />
                                        @if (!empty($sdResults[$fField]))
                                            <div x-show="open"
                                                class="absolute z-30 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-48 overflow-y-auto mt-1">
                                                @foreach ($sdResults[$fField] as $opt)
                                                    <button type="button"
                                                        wire:click="selectDropdownOption('{{ $fField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                        @click="open = false"
                                                        class="block w-full text-left px-4 py-2 text-sm hover:bg-primary/5 hover:text-primary">
                                                        {{ $opt['label'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @if ($fError)
                                        <p class="mt-1 text-xs text-danger">{{ $fError }}</p>
                                    @endif

                                @elseif ($fTipo === 'date')
                                    <x-forge-input
                                        name="{{ $fField }}"
                                        type="date"
                                        label="{{ $fLabel }}"
                                        :required="$fRequired"
                                        :error="$fError"
                                        :value="$formData[$fField] ?? null"
                                        wire:model="formData.{{ $fField }}"
                                    />

                                @elseif ($fTipo === 'number')
                                    <x-forge-input
                                        name="{{ $fField }}"
                                        type="number"
                                        label="{{ $fLabel }}"
                                        :required="$fRequired"
                                        :error="$fError"
                                        :value="$formData[$fField] ?? null"
                                        wire:model="formData.{{ $fField }}"
                                        step="any"
                                    />

                                @else
                                    {{-- text (default) --}}
                                    <x-forge-input
                                        name="{{ $fField }}"
                                        type="text"
                                        label="{{ $fLabel }}"
                                        :required="$fRequired"
                                        :error="$fError"
                                        :value="$formData[$fField] ?? null"
                                        wire:model="formData.{{ $fField }}"
                                        {{ $fMask ? "data-mask=\"{$fMask}\"" : '' }}
                                    />
                                @endif

                            </div>
                        @endforeach

                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <x-forge-button wire:click="closeModal" color="dark" flat :disabled="$creating">
                        Cancelar
                    </x-forge-button>
                    <x-forge-button wire:click="save" color="primary" :loading="$creating" :disabled="$creating">
                        {{ $editingId ? 'Salvar Alterações' : 'Criar' }}
                    </x-forge-button>
                </div>

            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ── Modal Confirmar Exclusão ─────────────────────────────────────── --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" wire:click="cancelDelete"></div>
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Confirmar exclusão</h3>
                        <p class="text-sm text-gray-500">Esta ação não pode ser desfeita.</p>
                    </div>
                </div>
                <div class="flex gap-3 justify-end">
                    <x-forge-button wire:click="cancelDelete" color="dark" flat>Cancelar</x-forge-button>
                    <x-forge-button wire:click="deleteRecord" color="danger">Excluir</x-forge-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Loading overlay global --}}
    <div wire:loading.delay.long wire:target="save,deleteRecord,sortBy,export"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/20">
        <x-forge-spinner color="primary" size="lg" />
    </div>

</div>

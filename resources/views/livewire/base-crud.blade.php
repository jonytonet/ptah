<div class="ptah-base-crud" wire:key="base-crud-{{ $crudTitle }}">

    {{-- ── Mensagens de sessão ──────────────────────────────────────────── --}}
    @if (session('crud-success') || $exportStatus)
        <x-forge-alert type="success" :dismissible="true" class="mb-3">
            {{ session('crud-success', $exportStatus) }}
        </x-forge-alert>
    @endif

    @if (!empty($crudConfig))

    {{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">

        {{-- Botão Novo --}}
        @if ($permissions['showCreateButton'] ?? true)
            @if (!($permissions['create'] ?? null) || (auth()->check() && auth()->user()->can($permissions['create'])))
                <button wire:click="openCreate"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-[0_8px_20px_rgba(91,33,182,0.45)] transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none select-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Novo
                </button>
            @endif
        @endif

        {{-- Busca Global --}}
        <div class="flex-1 min-w-[200px] max-w-xs">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input
                    wire:model.live.debounce.400ms="search"
                    type="text"
                    placeholder="Buscar..."
                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40"
                />
            </div>
        </div>

        {{-- Grupo de ações à direita --}}
        <div class="flex items-center gap-1.5 ml-auto flex-wrap">

            {{-- Botão Filtros --}}
            @php
                $filterableCols = collect($crudConfig['cols'] ?? [])->where('colsIsFilterable', 'S')->count();
                $hasFilterable  = $filterableCols > 0 || !empty($crudConfig['customFilters']);
                $activeFilterCount = count(array_filter($filters)) + count(array_filter($dateRanges)) + ($quickDateFilter !== '' ? 1 : 0);
            @endphp
            @if ($hasFilterable)
                <button wire:click="toggleFilters"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 focus:outline-none
                           {{ $showFilters ? 'bg-primary text-white' : 'bg-transparent text-gray-600 hover:bg-gray-100' }}"
                    title="Filtros">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    <span class="hidden sm:inline">Filtros</span>
                    @if ($activeFilterCount > 0)
                        <span class="inline-flex items-center justify-center w-4 h-4 text-xs leading-none text-white rounded-full bg-danger">
                            {{ $activeFilterCount }}
                        </span>
                    @endif
                </button>
            @endif

            {{-- Lixeira --}}
            @if ($permissions['showTrashButton'] ?? true)
                <button wire:click="toggleTrashed"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 focus:outline-none
                           {{ $showTrashed ? 'bg-danger/10 text-danger' : 'bg-transparent text-gray-600 hover:bg-gray-100' }}"
                    title="{{ $showTrashed ? 'Ver ativos' : 'Ver excluídos' }}">
                    @if ($showTrashed)
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                        </svg>
                        <span class="hidden sm:inline">Voltar</span>
                    @else
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span class="hidden sm:inline">Lixeira</span>
                    @endif
                </button>
            @endif

            {{-- Exportação --}}
            @if (!empty($exportCfg['enabled']))
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 rounded-xl bg-transparent hover:bg-gray-100 transition-all duration-200 focus:outline-none"
                        title="Exportar">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="hidden sm:inline">Exportar</span>
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 min-w-[150px] py-1">
                        @foreach ($exportCfg['formats'] ?? ['excel'] as $fmt)
                            <button wire:click="export('{{ $fmt }}')" @click="open = false"
                                class="flex items-center gap-2.5 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                @if ($fmt === 'excel')
                                    <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Excel
                                @elseif ($fmt === 'pdf')
                                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    PDF
                                @else
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    {{ strtoupper($fmt) }}
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Colunas (visibilidade) --}}
            @if (!empty($formDataColumns))
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 focus:outline-none
                               {{ $hiddenColumnsCount > 0 ? 'text-amber-600 bg-amber-50 hover:bg-amber-100' : 'text-gray-600 bg-transparent hover:bg-gray-100' }}"
                        title="Colunas">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 10h18M3 14h18M10 6v12M14 6v12"/>
                        </svg>
                        <span class="hidden sm:inline">Colunas</span>
                        @if ($hiddenColumnsCount > 0)
                            <span class="inline-flex items-center justify-center w-4 h-4 text-xs leading-none text-white rounded-full bg-amber-500">
                                {{ $hiddenColumnsCount }}
                            </span>
                        @endif
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 min-w-[220px] py-2 max-h-80 overflow-y-auto">
                        {{-- Ações rápidas --}}
                        <div class="flex gap-2 px-3 pb-2 mb-1 border-b border-gray-100">
                            <button wire:click="showAllColumns" @click="open = false"
                                class="flex-1 py-1 text-xs text-center text-gray-700 transition-colors bg-gray-100 rounded hover:bg-gray-200">
                                Mostrar todas
                            </button>
                            <button wire:click="hideAllColumns" @click="open = false"
                                class="flex-1 py-1 text-xs text-center text-gray-700 transition-colors bg-gray-100 rounded hover:bg-gray-200">
                                Ocultar todas
                            </button>
                        </div>
                        {{-- Lista de colunas --}}
                        @foreach ($crudConfig['cols'] ?? [] as $col)
                            @if (($col['colsTipo'] ?? '') !== 'action' && ($col['colsNomeFisico'] ?? '') !== 'id')
                                @php $colField = $col['colsNomeFisico']; @endphp
                                <label class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox"
                                        wire:model.live="formDataColumns.{{ $colField }}"
                                        wire:change="updateColumns"
                                        class="rounded cursor-pointer text-primary focus:ring-primary/30">
                                    <span class="text-sm text-gray-700 select-none">{{ $col['colsNomeLogico'] ?? $colField }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Densidade da visualização --}}
            @php
                $densityMap = [
                    'compact'     => ['icon' => '≡', 'label' => 'Compacto'],
                    'comfortable' => ['icon' => '☰', 'label' => 'Confortável'],
                    'spacious'    => ['icon' => '⊟', 'label' => 'Espaçoso'],
                ];
            @endphp
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 rounded-xl bg-transparent hover:bg-gray-100 transition-all duration-200 focus:outline-none"
                    title="Densidade">
                    <span class="text-sm leading-none">{{ $densityMap[$viewDensity]['icon'] ?? '☰' }}</span>
                    <span class="hidden sm:inline">Densidade</span>
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 min-w-[180px] py-1">
                    @foreach ($densityMap as $d => $info)
                        <button wire:click="$set('viewDensity', '{{ $d }}')" @click="open = false"
                            class="flex items-center justify-between w-full px-4 py-2 text-sm hover:bg-gray-50 transition-colors
                                   {{ $viewDensity === $d ? 'text-primary font-semibold' : 'text-gray-700' }}">
                            <span>{{ $info['icon'] }} {{ $info['label'] }}</span>
                            @if ($viewDensity === $d)
                                <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Configuração do CRUD (somente admins) --}}
            @livewire('ptah::crud-config', ['model' => $model], key('crud-cfg-'.$model))

            {{-- Atualizar --}}
            <button wire:click="$refresh"
                class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors focus:outline-none"
                title="Atualizar">
                <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="$refresh"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            {{-- Limpar filtros (visível quando houver algo ativo) --}}
            @if ($search !== '' || !empty(array_filter($filters)) || $showTrashed)
                <button wire:click="clearFilters"
                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors focus:outline-none"
                    title="Limpar filtros">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif

            {{-- Per page --}}
            <select wire:model.live="perPage"
                class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                @foreach ([10, 15, 25, 50, 100] as $n)
                    <option value="{{ $n }}">{{ $n }} / pág.</option>
                @endforeach
            </select>

        </div>
    </div>

    {{-- ── Painel de Filtros ────────────────────────────────────────────── --}}
    @if ($showFilters)
        <div class="mb-4 overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl">

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50/80">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Filtros</span>
                    @if ($activeFilterCount > 0)
                        <span class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-medium">
                            {{ $activeFilterCount }} ativo{{ $activeFilterCount > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
                <button wire:click="clearFilters"
                    class="flex items-center gap-1 text-xs text-gray-400 transition-colors hover:text-danger">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Limpar tudo
                </button>
            </div>

            <div class="p-4 space-y-4">

                {{-- Atalhos rápidos de data --}}
                @php
                    $hasDateFilterCols = collect($crudConfig['cols'] ?? [])
                        ->where('colsIsFilterable', 'S')
                        ->where('colsTipo', 'date')
                        ->isNotEmpty();
                    $quickLabels = [
                        'today'     => 'Hoje',
                        'yesterday' => 'Ontem',
                        'last7'     => '7 dias',
                        'last30'    => '30 dias',
                        'week'      => 'Esta semana',
                        'month'     => 'Este mês',
                        'lastMonth' => 'Mês passado',
                        'quarter'   => 'Trimestre',
                        'year'      => 'Este ano',
                    ];
                @endphp
                @if ($hasDateFilterCols)
                    <div>
                        <p class="mb-2 text-xs font-medium text-gray-500">Atalhos de data</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($quickLabels as $period => $qlabel)
                                <button wire:click="applyQuickDateFilter('{{ $period }}')"
                                    class="px-2.5 py-1 text-xs rounded-lg border transition-colors duration-150
                                        {{ $quickDateFilter === $period
                                            ? 'bg-primary text-white border-primary shadow-sm'
                                            : 'bg-white text-gray-600 border-gray-300 hover:border-primary hover:text-primary' }}">
                                    {{ $qlabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Campos filtráveis --}}
                @php
                    $filterableCfCols = array_values(array_filter(
                        $crudConfig['cols'] ?? [],
                        fn($c) => in_array($c['colsIsFilterable'] ?? false, [true, 'S', 1, '1'], true) && ($c['colsTipo'] ?? '') !== 'action'
                    ));
                @endphp
                @if (!empty($filterableCfCols))
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">

                        @foreach ($filterableCfCols as $col)
                            @php
                                $cfField = $col['colsNomeFisico'];
                                $cfLabel = $col['colsNomeLogico'] ?? $cfField;
                                $cfTipo  = $col['colsTipo'] ?? 'text';
                            @endphp

                            {{-- Date: mostra De / Até com operador --}}
                            @if ($cfTipo === 'date')
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ $cfLabel }}</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <p class="mb-1 text-xs text-gray-400">De</p>
                                            <div class="flex gap-1">
                                                <select wire:model.live="dateRangeOperators.{{ $cfField }}_start"
                                                    class="text-xs border border-gray-300 rounded-lg px-1.5 py-2 bg-white focus:ring-1 focus:ring-primary/30 focus:outline-none w-[58px] shrink-0">
                                                    <option value=">=">&ge;</option>
                                                    <option value=">">&gt;</option>
                                                    <option value="=">=</option>
                                                </select>
                                                <input type="date"
                                                    wire:model.live="dateRanges.{{ $cfField }}_start"
                                                    class="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                            </div>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs text-gray-400">Até</p>
                                            <div class="flex gap-1">
                                                <select wire:model.live="dateRangeOperators.{{ $cfField }}_end"
                                                    class="text-xs border border-gray-300 rounded-lg px-1.5 py-2 bg-white focus:ring-1 focus:ring-primary/30 focus:outline-none w-[58px] shrink-0">
                                                    <option value="<=">&le;</option>
                                                    <option value="<">&lt;</option>
                                                    <option value="=">=</option>
                                                </select>
                                                <input type="date"
                                                    wire:model.live="dateRanges.{{ $cfField }}_end"
                                                    class="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            {{-- Select / Enum --}}
                            @elseif ($cfTipo === 'select' && !empty($col['colsSelect']))
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ $cfLabel }}</label>
                                    <select wire:model.live="filters.{{ $cfField }}"
                                        class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 bg-white focus:ring-1 focus:ring-primary/30 focus:outline-none">
                                        <option value="">-- Todos --</option>
                                        @foreach ($col['colsSelect'] as $optLabel => $optVal)
                                            <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>

                            {{-- SearchDropdown no filtro (select2-like) --}}
                            @elseif ($cfTipo === 'searchdropdown')
                                @php
                                    $cfFilterKey      = 'filter_' . $cfField;
                                    $cfFilterSelected = $sdFilterLabels[$cfField] ?? null;
                                    $cfFilterHasRes   = !empty($sdResults[$cfFilterKey]);
                                @endphp
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ $cfLabel }}</label>

                                    {{-- Badge de seleção ativa --}}
                                    @if ($cfFilterSelected)
                                        <div class="flex items-center gap-1 mb-1.5">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                                                {{ $cfFilterSelected }}
                                                <button type="button"
                                                    wire:click="clearFilterDropdownSelection('{{ $cfField }}')"
                                                    class="ml-0.5 hover:text-violet-900 leading-none">&times;</button>
                                            </span>
                                        </div>
                                    @endif

                                    <div
                                        x-data="{
                                            open: {{ $cfFilterHasRes ? 'true' : 'false' }},
                                            init() {
                                                this.$wire.$watch('sdResults', (val) => {
                                                    const res = val['{{ $cfFilterKey }}'];
                                                    this.open = Array.isArray(res) && res.length > 0;
                                                });
                                            }
                                        }"
                                        @click.outside="open = false"
                                        class="relative"
                                    >
                                        <div class="relative flex items-center">
                                            <input type="text"
                                                wire:keyup.debounce.300ms="filterSearchDropdown('{{ $cfField }}', $event.target.value)"
                                                @focus="$wire.openFilterDropdown('{{ $cfField }}')"
                                                placeholder="{{ $cfFilterSelected ? 'Alterar...' : 'Buscar ' . $cfLabel . '...' }}"
                                                autocomplete="off"
                                                class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 pr-8 focus:ring-1 focus:ring-primary/30 focus:outline-none bg-white"
                                            />
                                            <button type="button"
                                                tabindex="-1"
                                                @mousedown.prevent="open = !open; if (open) $wire.openFilterDropdown('{{ $cfField }}')"
                                                class="absolute right-2 text-gray-400 hover:text-gray-600 transition-transform duration-200"
                                                :class="open ? 'rotate-180' : ''">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div x-show="open" x-cloak
                                            class="absolute z-30 w-full mt-1 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg max-h-48">
                                            @forelse ($sdResults[$cfFilterKey] ?? [] as $opt)
                                                <button type="button"
                                                    wire:click="selectFilterDropdownOption('{{ $cfField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                    @click="open = false"
                                                    class="block w-full px-3 py-2 text-sm text-left hover:bg-violet-50 hover:text-violet-700">
                                                    {{ $opt['label'] }}
                                                </button>
                                            @empty
                                                <p class="px-3 py-2 text-xs text-gray-400 italic">Nenhum resultado encontrado.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                            {{-- Text / Number com operador --}}
                            @else
                                @php $isNum = $cfTipo === 'number'; @endphp
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ $cfLabel }}</label>
                                    <div class="flex gap-1">
                                        <select wire:model.live="filterOperators.{{ $cfField }}"
                                            class="text-xs border border-gray-300 rounded-lg px-1.5 py-2 bg-white focus:ring-1 focus:ring-primary/30 focus:outline-none w-[90px] shrink-0">
                                            @if ($isNum)
                                                <option value="=">=</option>
                                                <option value="!=">&ne;</option>
                                                <option value=">">&gt;</option>
                                                <option value=">=">&ge;</option>
                                                <option value="<">&lt;</option>
                                                <option value="<=">&le;</option>
                                            @else
                                                <option value="LIKE">contém</option>
                                                <option value="=">igual a</option>
                                                <option value="!=">diferente</option>
                                                <option value="LIKE_START">inicia com</option>
                                                <option value="LIKE_END">termina com</option>
                                            @endif
                                        </select>
                                        <input type="{{ $isNum ? 'number' : 'text' }}"
                                            wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                            placeholder="{{ $cfLabel }}..."
                                            @if($isNum) step="any" @endif
                                            class="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                    </div>
                                </div>
                            @endif

                        @endforeach

                        {{-- CustomFilters --}}
                        @foreach ($crudConfig['customFilters'] ?? [] as $cf)
                            @php
                                $cfField = $cf['field'] ?? '';
                                $cfLabel = $cf['label'] ?? $cf['field'] ?? '';
                                $cfType  = $cf['colsFilterType'] ?? (($cf['useSearchDropDown'] ?? 'N') === 'S' ? 'searchdropdown' : 'text');
                            @endphp
                            @if ($cfField)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">{{ $cfLabel }}</label>
                                    @if ($cfType === 'searchdropdown')
                                        <div class="relative" x-data="{ open: false }">
                                            <input type="text"
                                                wire:keyup="searchDropdown('cf_{{ $cfField }}', $event.target.value)"
                                                @focus="open = true"
                                                @click.outside="open = false"
                                                placeholder="Buscar {{ $cfLabel }}..."
                                                class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                            @if (!empty($sdResults['cf_' . $cfField]))
                                                <div x-show="open" class="absolute z-30 w-full mt-1 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg max-h-48">
                                                    @foreach ($sdResults['cf_' . $cfField] as $opt)
                                                        <button wire:click="selectDropdownOption('{{ $cfField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                            @click="open = false"
                                                            class="block w-full px-3 py-2 text-sm text-left hover:bg-violet-50">
                                                            {{ $opt['label'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @elseif ($cfType === 'date')
                                        <input type="date"
                                            wire:model.live="filters.{{ $cfField }}"
                                            class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                    @elseif ($cfType === 'number')
                                        <input type="number"
                                            wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                            placeholder="{{ $cfLabel }}..."
                                            class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                    @elseif ($cfType === 'select' && !empty($cf['colsSelect']))
                                        <select wire:model.live="filters.{{ $cfField }}"
                                            class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 bg-white focus:ring-1 focus:ring-primary/30 focus:outline-none">
                                            <option value="">-- Todos --</option>
                                            @foreach ($cf['colsSelect'] as $optLabel => $optVal)
                                                <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text"
                                            wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                            placeholder="{{ $cfLabel }}..."
                                            class="w-full text-sm border border-gray-300 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-primary/30 focus:outline-none" />
                                    @endif
                                </div>
                            @endif
                        @endforeach

                    </div>
                @endif

                {{-- Filtros salvos --}}
                @if (!empty($savedFilters))
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <span class="text-xs font-medium text-gray-400">Salvos:</span>
                        @foreach (array_keys($savedFilters) as $sfName)
                            <div class="flex items-center">
                                <button wire:click="loadNamedFilter('{{ $sfName }}')"
                                    class="text-xs bg-primary/10 text-primary px-2.5 py-1 rounded-l-lg hover:bg-primary/20 transition-colors border border-primary/20 border-r-0">
                                    {{ $sfName }}
                                </button>
                                <button wire:click="deleteNamedFilter('{{ $sfName }}')"
                                    class="text-xs bg-danger/10 text-danger px-1.5 py-1 rounded-r-lg hover:bg-danger/20 transition-colors border border-danger/20">
                                    &times;
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>{{-- /p-4 --}}

            {{-- Footer: salvar filtro --}}
            <div class="flex items-center gap-2 px-4 py-3 border-t border-gray-100 bg-gray-50/80"
                 x-data="{ saving: false, name: '' }">
                <template x-if="!saving">
                    <button @click="saving = true"
                        class="flex items-center gap-1 text-xs text-gray-500 transition-colors hover:text-primary">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                        </svg>
                        Salvar filtro atual com nome
                    </button>
                </template>
                <template x-if="saving">
                    <div class="flex items-center w-full gap-2">
                        <input type="text" x-model="name"
                            @keydown.enter="if(name.trim()) { $wire.saveNamedFilter(name.trim()); saving = false; name = ''; }"
                            @keydown.escape="saving = false; name = '';"
                            placeholder="Ex: Clientes ativos SP"
                            class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-primary/40 focus:outline-none"
                            x-init="$nextTick(() => $el.focus())" />
                        <button @click="if(name.trim()) { $wire.saveNamedFilter(name.trim()); saving = false; name = ''; }"
                            class="text-xs bg-primary text-white px-3 py-1.5 rounded-lg hover:bg-primary-dark transition-colors">
                            Salvar
                        </button>
                        <button @click="saving = false; name = '';"
                            class="text-xs text-gray-400 transition-colors hover:text-gray-600">
                            Cancelar
                        </button>
                    </div>
                </template>
            </div>

        </div>
    @endif

    {{-- ── Tabela ──────────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto border border-gray-200 rounded-lg" id="ptah-table-wrap-{{ $crudTitle }}">
        <table class="{{ $crudConfig['tableClass'] ?? 'table' }} ptah-cols-table w-full text-sm
            @if($viewDensity === 'compact') text-xs @elseif($viewDensity === 'spacious') text-base @endif">

            <thead class="{{ $crudConfig['theadClass'] ?? 'bg-gray-50 border-b border-gray-200' }}">
                <tr id="ptah-thead-row-{{ $crudTitle }}">
                    @foreach ($visibleCols as $col)
                        @if (($col['colsTipo'] ?? '') !== 'action')
                            @php
                                $colField    = $col['colsNomeFisico'];
                                $colSortBy   = $col['colsOrderBy'] ?? $colField;
                                $colLabel    = $col['colsNomeLogico'] ?? $colField;
                                $colAlign    = $col['colsAlign'] ?? 'text-start';
                                $isSortable  = !str_contains($colField, '.') && empty($col['colsMetodoCustom']);
                                $savedWidth  = $columnWidths[$colField] ?? null;
                                $thStyle     = $savedWidth ? "width:{$savedWidth}px;min-width:60px;" : 'min-width:60px;';
                            @endphp
                            <th class="relative px-3 py-2 font-semibold text-gray-700 whitespace-nowrap {{ $colAlign }} ptah-sortable-col"
                                data-column="{{ $colField }}"
                                style="{{ $thStyle }}"
                                draggable="true"
                                ondragstart="ptahColDragStart(event, '{{ $crudTitle }}')"
                                ondragover="ptahColDragOver(event)"
                                ondrop="ptahColDragDrop(event, '{{ $crudTitle }}')"
                                ondragend="ptahColDragEnd(event)">
                                <div class="flex items-center gap-1.5">
                                    {{-- Grip (initia o drag) --}}
                                    <span class="ptah-drag-grip shrink-0 cursor-grab text-gray-300 hover:text-gray-500 transition-colors select-none"
                                          title="Arrastar para reordenar">
                                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                            <circle cx="7" cy="5"  r="1.5"/><circle cx="13" cy="5"  r="1.5"/>
                                            <circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>
                                            <circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/>
                                        </svg>
                                    </span>
                                    {{-- Label (sort) --}}
                                    <span class="flex-1 inline-flex items-center gap-1 {{ $isSortable ? 'cursor-pointer select-none hover:text-primary' : '' }}"
                                          @if($isSortable) wire:click.stop="sortBy('{{ $colSortBy }}')" @endif>
                                        @if (!empty($col['colsCellIcon']))
                                            <i class="{{ $col['colsCellIcon'] }}"></i>
                                        @endif
                                        {{ $colLabel }}
                                        @if ($sort === $colSortBy)
                                            <span class="text-primary">{{ $direction === 'ASC' ? '↑' : '↓' }}</span>
                                        @endif
                                    </span>
                                </div>
                                {{-- Resize handle --}}
                                <div class="ptah-resize-handle absolute top-0 right-0 h-full w-1.5 cursor-col-resize z-10 hover:bg-primary/30 transition-colors"
                                     onclick="event.stopPropagation()"
                                     onmousedown="ptahResizeStart(event, '{{ $colField }}', '{{ $crudTitle }}')">
                                </div>
                            </th>
                        @endif
                    @endforeach

                    {{-- Colunas action --}}
                    @foreach ($visibleCols as $col)
                        @if (($col['colsTipo'] ?? '') === 'action')
                            <th class="px-3 py-2 font-semibold text-center text-gray-700 whitespace-nowrap">
                                {{ $col['colsNomeLogico'] ?? 'Ação' }}
                            </th>
                        @endif
                    @endforeach

                    {{-- Coluna de ações padrão --}}
                    @if (($permissions['showEditButton'] ?? true) || ($permissions['showDeleteButton'] ?? true))
                        <th class="px-3 py-2 font-semibold text-center text-gray-700">Ações</th>
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
                        @foreach ($visibleCols as $col)
                            @if (($col['colsTipo'] ?? '') !== 'action')
                                @php
                                    $cellField    = $col['colsNomeFisico'];
                                    $cellAlign    = $col['colsAlign'] ?? 'text-start';
                                    $reverse      = ($col['colsReverse'] ?? 'N') === 'S';
                                    $cellSavedW   = $columnWidths[$cellField] ?? null;
                                    $cellMinWidth = $cellSavedW
                                        ? "width:{$cellSavedW}px;min-width:60px;"
                                        : (! empty($col['colsMinWidth']) ? 'min-width:' . $col['colsMinWidth'] . ';' : '');
                                @endphp
                                <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} {{ $cellAlign }} {{ $reverse ? 'font-medium' : '' }}"
                                    @if($cellMinWidth) style="{{ $cellMinWidth }}" @endif>
                                    {!! $this->formatCell($col, $row) !!}
                                </td>
                            @endif
                        @endforeach

                        {{-- Colunas action --}}
                        @foreach ($visibleCols as $col)
                            @if (($col['colsTipo'] ?? '') === 'action')
                                <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} text-center">
                                    @php
                                        $actionType  = $col['actionType']  ?? 'javascript';
                                        $actionValue = $col['actionValue'] ?? ($col['actionCall'] ?? '');
                                        $actionIcon  = $col['actionIcon']  ?: ($col['actionIcone'] ?? '');
                                        $actionColor = $col['actionColor'] ?? 'primary';
                                        $rowId       = $row->id ?? 0;
                                        $actionStr   = str_replace(['%id%', '"id%'], [$rowId, $rowId], $actionValue);
                                    @endphp

                                    @if ($actionStr)
                                        @if ($actionType === 'link')
                                            <a href="{{ $actionStr }}"
                                                @click.stop
                                                class="transition-colors text-{{ $actionColor }} hover:opacity-75"
                                                title="{{ $col['colsNomeLogico'] ?? '' }}">
                                                @if ($actionIcon)
                                                    <i class="{{ $actionIcon }} text-base"></i>
                                                @else
                                                    {{ $col['colsNomeLogico'] ?? '→' }}
                                                @endif
                                            </a>
                                        @elseif ($actionType === 'livewire')
                                            <button wire:click="{{ $actionStr }}"
                                                @click.stop
                                                class="transition-colors text-{{ $actionColor }} hover:opacity-75"
                                                title="{{ $col['colsNomeLogico'] ?? '' }}">
                                                @if ($actionIcon)
                                                    <i class="{{ $actionIcon }} text-base"></i>
                                                @else
                                                    {{ $col['colsNomeLogico'] ?? '▶' }}
                                                @endif
                                            </button>
                                        @else
                                            {{-- javascript (default) --}}
                                            <button onclick="{{ $actionStr }}"
                                                @click.stop
                                                class="transition-colors text-{{ $actionColor }} hover:opacity-75"
                                                title="{{ $col['colsNomeLogico'] ?? '' }}">
                                                @if ($actionIcon)
                                                    <i class="{{ $actionIcon }} text-base"></i>
                                                @else
                                                    {{ $col['colsNomeLogico'] ?? '▶' }}
                                                @endif
                                            </button>
                                        @endif
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
                                                @click.stop
                                                class="transition-colors text-primary hover:text-primary/80" title="Editar">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    @endif

                                    {{-- Excluir / Restaurar --}}
                                    @if ($showTrashed && method_exists($row, 'trashed') && $row->trashed())
                                        <button wire:click="restoreRecord({{ $row->id ?? 0 }})"
                                            @click.stop
                                            class="transition-colors text-success hover:text-success/80" title="Restaurar">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    @elseif ($permissions['showDeleteButton'] ?? true)
                                        @if (!($permissions['delete'] ?? null) || (auth()->check() && auth()->user()->can($permissions['delete'])))
                                            <button wire:click="confirmDelete({{ $row->id ?? 0 }})"
                                                @click.stop
                                                class="transition-colors text-danger hover:text-danger/80" title="Excluir">
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
                <tfoot class="font-semibold border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        @foreach ($visibleCols as $col)
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
    <div class="flex items-center justify-between mt-4 text-sm text-gray-500">
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
        @teleport('body')
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
                    <button wire:click="closeModal" class="text-gray-400 transition-colors hover:text-gray-600">
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
                <div class="flex-1 px-6 py-4 overflow-y-auto">
                    <div class="flex flex-col gap-4">

                        @foreach ($formCols as $col)
                            @php
                                $fField    = $col['colsNomeFisico'];
                                $fLabel    = $col['colsNomeLogico'] ?? $fField;
                                $fTipo     = $col['colsTipo'] ?? 'text';
                                $fRequired = in_array($col['colsRequired'] ?? false, [true, 'S', 1, '1'], true);
                                $fError    = $formErrors[$fField] ?? null;
                                $fMask     = $col['colsMask'] ?? null;
                                $fValue    = $formData[$fField] ?? '';

                                // Classes de bordas reutilizáveis
                                $fBorderClass  = $fError
                                    ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
                                    : 'border-gray-300 focus:border-violet-500 focus:ring-violet-100';
                            @endphp

                            <div class="{{ $fTipo === 'searchdropdown' ? 'relative' : '' }}">

                                @if ($fTipo === 'select' && !empty($col['colsSelect']))
                                    {{-- ── Select inline (sem Blade component dentro do teleport) ── --}}
                                    @php
                                        $fOptions = collect($col['colsSelect'])
                                            ->map(fn($v, $k) => ['value' => (string)$v, 'label' => $k])
                                            ->values()
                                            ->toArray();
                                        $fInitSel  = $fValue !== '' ? json_encode((string)$fValue) : 'null';
                                        $fBorderNormal = $fError ? 'border-red-400' : 'border-gray-300';
                                        $fBorderOpen   = $fError ? 'border-red-500' : 'border-violet-500';
                                        $fRingOpen     = $fError ? 'ring-2 ring-red-200' : 'ring-2 ring-violet-100';
                                    @endphp
                                    <div class="w-full">
                                        <label class="block mb-1 text-xs font-medium text-gray-600">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <div
                                            x-data="{
                                                open: false,
                                                selected: {{ $fInitSel }},
                                                options: {{ json_encode($fOptions) }},
                                                placeholder: 'Selecione...',
                                                get displayLabel() {
                                                    if (this.selected === null || this.selected === '') return this.placeholder;
                                                    const opt = this.options.find(o => String(o.value) === String(this.selected));
                                                    return opt ? opt.label : this.placeholder;
                                                },
                                                isSelected(value) { return String(this.selected) === String(value); },
                                                toggle(value) { this.selected = String(value); this.open = false; }
                                            }"
                                            @click.outside="open = false"
                                            class="relative"
                                        >
                                            <input type="hidden"
                                                :value="selected ?? ''"
                                                x-init="$watch('selected', val => {
                                                    $el.value = val ?? '';
                                                    $el.dispatchEvent(new Event('input', { bubbles: true }));
                                                })"
                                                wire:model.live="formData.{{ $fField }}"
                                            >
                                            <div
                                                @click="open = !open"
                                                :class="open ? '{{ $fBorderOpen }} {{ $fRingOpen }}' : '{{ $fBorderNormal }}'"
                                                class="relative flex items-center justify-between rounded-lg border bg-white px-3 py-2.5 cursor-pointer select-none transition-colors duration-150"
                                            >
                                                <span
                                                    :class="(selected !== null && selected !== '') ? 'text-gray-800' : 'text-gray-400'"
                                                    class="pr-4 text-sm truncate"
                                                    x-text="displayLabel"
                                                ></span>
                                                <span class="absolute text-gray-400 transition-transform duration-200 -translate-y-1/2 right-3 top-1/2" :class="open ? 'rotate-180' : ''">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div
                                                x-show="open"
                                                x-cloak
                                                x-transition:enter="transition ease-out duration-150"
                                                x-transition:enter-start="opacity-0 -translate-y-1"
                                                x-transition:enter-end="opacity-100 translate-y-0"
                                                class="absolute z-20 w-full mt-1 overflow-auto bg-white border border-gray-100 shadow-lg rounded-xl max-h-48"
                                            >
                                                <ul class="py-1">
                                                    <template x-for="option in options" :key="option.value">
                                                        <li
                                                            @click="toggle(option.value)"
                                                            :class="isSelected(option.value) ? 'bg-violet-50 text-violet-700' : 'text-gray-700 hover:bg-gray-50'"
                                                            class="flex items-center justify-between px-4 py-2 text-sm cursor-pointer"
                                                        >
                                                            <span x-text="option.label"></span>
                                                            <svg x-show="isSelected(option.value)" class="w-4 h-4 ml-2 text-violet-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </div>
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @elseif ($fTipo === 'searchdropdown')
                                    {{-- ── SearchDropdown inline (comportamento select2) ── --}}
                                    @php
                                        $sdInitLabel  = $sdLabels[$fField] ?? '';
                                        $sdHasResults = !empty($sdResults[$fField]);
                                    @endphp
                                    <div class="w-full">
                                        <label class="block mb-1 text-xs font-medium text-gray-600">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <div
                                            x-data="{
                                                open: {{ $sdHasResults ? 'true' : 'false' }},
                                                displayVal: @js($sdInitLabel),
                                                init() {
                                                    this.$wire.$watch('sdLabels', (val) => {
                                                        if (val['{{ $fField }}'] !== undefined) {
                                                            this.displayVal = val['{{ $fField }}'];
                                                        }
                                                    });
                                                    this.$wire.$watch('sdResults', (val) => {
                                                        const res = val['{{ $fField }}'];
                                                        this.open = Array.isArray(res) && res.length > 0;
                                                    });
                                                }
                                            }"
                                            @click.outside="open = false"
                                            class="relative"
                                        >
                                            <div class="relative flex items-center">
                                                <input type="text"
                                                    x-model="displayVal"
                                                    wire:keyup.debounce.300ms="searchDropdown('{{ $fField }}', $event.target.value)"
                                                    @focus="$wire.openDropdown('{{ $fField }}')"
                                                    placeholder="Buscar {{ $fLabel }}..."
                                                    autocomplete="off"
                                                    class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 pr-9 text-sm text-gray-800 bg-white transition-colors duration-150 focus:ring-2"
                                                />
                                                <button type="button"
                                                    tabindex="-1"
                                                    @mousedown.prevent="open = !open; if (open) $wire.openDropdown('{{ $fField }}')"
                                                    class="absolute right-2.5 text-gray-400 hover:text-gray-600 transition-transform duration-200"
                                                    :class="open ? 'rotate-180' : ''">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <input type="hidden" wire:model="formData.{{ $fField }}" />
                                            <div x-show="open" x-cloak
                                                class="absolute z-30 w-full mt-1 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-xl max-h-48">
                                                @forelse ($sdResults[$fField] ?? [] as $opt)
                                                    <button type="button"
                                                        wire:click="selectDropdownOption('{{ $fField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                        @click="open = false"
                                                        class="block w-full px-4 py-2 text-sm text-left hover:bg-violet-50 hover:text-violet-700">
                                                        {{ $opt['label'] }}
                                                    </button>
                                                @empty
                                                    <p class="px-4 py-3 text-xs text-gray-400 italic">Nenhum resultado encontrado.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @else
                                    {{-- ── Input inline (text / number / date) ── --}}
                                    @php
                                        $fInputType = match($fTipo) {
                                            'date'   => 'date',
                                            'number' => 'number',
                                            default  => 'text',
                                        };
                                    @endphp
                                    <div class="w-full">
                                        <label class="block mb-1 text-xs font-medium text-gray-600">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            type="{{ $fInputType }}"
                                            name="{{ $fField }}"
                                            wire:model="formData.{{ $fField }}"
                                            @if($fRequired) required @endif
                                            @if($fTipo === 'number') step="any" @endif
                                            @if($fMask) data-mask="{{ $fMask }}" @endif
                                            placeholder=""
                                            class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm text-gray-800 bg-white transition-colors duration-150 focus:ring-2"
                                        />
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>
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
        @endteleport
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ── Modal Confirmar Exclusão ─────────────────────────────────────── --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($showDeleteConfirm)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" wire:click="cancelDelete"></div>
            <div class="relative w-full max-w-sm p-6 mx-4 bg-white shadow-2xl rounded-xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full bg-danger/10">
                        <svg class="w-5 h-5 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Confirmar exclusão</h3>
                        <p class="text-sm text-gray-500">Esta ação não pode ser desfeita.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <x-forge-button wire:click="cancelDelete" color="dark" flat>Cancelar</x-forge-button>
                    <x-forge-button wire:click="deleteRecord" color="danger">Excluir</x-forge-button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Loading overlay global --}}
    <div wire:loading.delay.long wire:target="save,deleteRecord,sortBy,export"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/20">
        <x-forge-spinner color="primary" size="lg" />
    </div>

    {{-- ── Drag-and-drop + Resize de colunas ────────────────────────────── --}}
    @once
    <style>
        /* Drag feedback */
        .ptah-sortable-col.ptah-dragging   { opacity: .45; }
        .ptah-sortable-col.ptah-drag-over  { outline: 2px solid #6366f1; outline-offset: -2px; }
        .ptah-drag-grip                    { touch-action: none; }

        /* Resize indicator */
        #ptah-resize-indicator {
            position: fixed; top: 0; bottom: 0; width: 2px;
            background: #6366f1; z-index: 9999; pointer-events: none; display: none;
        }
        #ptah-resize-indicator.active { display: block; }
    </style>

    <div id="ptah-resize-indicator"></div>

    <script>
    (function () {
        if (window.__ptahColDragInit) return;
        window.__ptahColDragInit = true;

        /* ─── estado global ─────────────────────────────────────── */
        let _draggedTh = null, _draggedIdx = null, _dragCrudId = null;
        let _resizeTh = null, _resizeStart = 0, _resizeStartW = 0, _resizeField = null, _resizeCrud = null;
        const _indicator = () => document.getElementById('ptah-resize-indicator');

        /* ─── helper: encontra o componente Livewire da tabela ───── */
        function findWire(crudId) {
            const wrap   = document.getElementById('ptah-table-wrap-' + crudId);
            const wireEl = wrap?.closest('[wire\\:id]');
            return wireEl ? Livewire.find(wireEl.getAttribute('wire:id')) : null;
        }

        /* ─── helper: colunas sortable de uma thead row ─────────── */
        function sortableThs(crudId) {
            const row = document.getElementById('ptah-thead-row-' + crudId);
            return row ? Array.from(row.querySelectorAll('th.ptah-sortable-col')) : [];
        }

        /* ═══════════════════════════════════════════════════════
           DRAG-AND-DROP DE COLUNAS
        ══════════════════════════════════════════════════════════ */
        window.ptahColDragStart = function (e, crudId) {
            // Não iniciar drag se vier do resize handle
            if (e.target.closest('.ptah-resize-handle')) {
                e.preventDefault(); return;
            }
            _draggedTh  = e.currentTarget.closest('th');
            _dragCrudId = crudId;
            const ths   = sortableThs(crudId);
            _draggedIdx = ths.indexOf(_draggedTh);

            _draggedTh.classList.add('ptah-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(_draggedIdx));
        };

        window.ptahColDragOver = function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const targetTh = e.target.closest('th.ptah-sortable-col');
            if (!targetTh || targetTh === _draggedTh || !_dragCrudId) return;

            sortableThs(_dragCrudId).forEach(th => th.classList.remove('ptah-drag-over'));
            targetTh.classList.add('ptah-drag-over');
        };

        window.ptahColDragDrop = function (e, crudId) {
            e.stopPropagation();
            const targetTh = e.target.closest('th.ptah-sortable-col');
            if (!targetTh || targetTh === _draggedTh) return;

            const ths      = sortableThs(crudId);
            const currOrder = ths.map(th => th.dataset.column);
            const toIdx     = ths.indexOf(targetTh);

            // Reordenar o array
            const fromField = currOrder.splice(_draggedIdx, 1)[0];
            currOrder.splice(toIdx, 0, fromField);

            // Mover DOM imediatamente para feedback instantâneo (Livewire re-render depois)
            const parent = targetTh.parentNode;
            if (toIdx < _draggedIdx) {
                parent.insertBefore(_draggedTh, targetTh);
            } else {
                parent.insertBefore(_draggedTh, targetTh.nextSibling);
            }

            // Persistir via Livewire
            const wire = findWire(crudId);
            if (wire) wire.call('reorderColumns', currOrder);
        };

        window.ptahColDragEnd = function (e) {
            if (_draggedTh) _draggedTh.classList.remove('ptah-dragging');
            if (_dragCrudId) sortableThs(_dragCrudId).forEach(th => th.classList.remove('ptah-drag-over'));
            _draggedTh = null; _draggedIdx = null; _dragCrudId = null;
        };

        /* ═══════════════════════════════════════════════════════
           RESIZE DE COLUNAS
        ══════════════════════════════════════════════════════════ */
        window.ptahResizeStart = function (e, field, crudId) {
            e.preventDefault(); e.stopPropagation();
            _resizeTh      = e.target.closest('th');
            _resizeField   = field;
            _resizeCrud    = crudId;
            _resizeStart   = e.pageX;
            _resizeStartW  = _resizeTh.offsetWidth;

            const ind = _indicator();
            if (ind) { ind.style.left = e.pageX + 'px'; ind.classList.add('active'); }
            document.body.style.cursor     = 'col-resize';
            document.body.style.userSelect = 'none';
        };

        document.addEventListener('mousemove', function (e) {
            if (!_resizeTh) return;
            const newW = Math.max(60, _resizeStartW + (e.pageX - _resizeStart));
            _resizeTh.style.width    = newW + 'px';
            _resizeTh.style.minWidth = newW + 'px';
            const ind = _indicator();
            if (ind) ind.style.left = e.pageX + 'px';
        });

        document.addEventListener('mouseup', function (e) {
            if (!_resizeTh) return;
            const finalW = _resizeTh.offsetWidth;

            const ind = _indicator();
            if (ind) ind.classList.remove('active');
            document.body.style.cursor     = '';
            document.body.style.userSelect = '';

            const wire = findWire(_resizeCrud);
            if (wire && _resizeField) wire.call('saveColumnWidth', _resizeField, finalW);

            _resizeTh = null; _resizeField = null; _resizeCrud = null;
        });

    })();
    </script>
    @endonce

</div>

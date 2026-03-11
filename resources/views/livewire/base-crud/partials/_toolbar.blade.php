{{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border rounded-md ptah-c-toolbar">

    {{-- Botão Novo --}}
    @if ($permissions['showCreateButton'] ?? true)
        @if (!($permissions['create'] ?? null) || (auth()->check() && auth()->user()->can($permissions['create'])))
            <x-forge-button @click="$wire.showModal = true; $wire.prepareCreate()" color="primary" size="sm">
                <x-slot name="icon">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </x-slot>
                {{ __('ptah::ui.btn_new') }}
            </x-forge-button>
        @endif
    @endif

    {{-- Busca Global --}}
    <div class="flex-1 min-w-[200px] max-w-xs">
        <x-forge-input
            wire:model.live.debounce.400ms="search"
            type="text"
            :placeholder="__('ptah::ui.search_placeholder')"
            iconBefore='<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/></svg>'
            class="ptah-c-search"
        />
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
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-all duration-150 focus:outline-none
                       {{ $showFilters ? 'ptah-c-btn_on' : 'ptah-c-btn' }}"
                title="{{ __('ptah::ui.btn_filters') }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span class="hidden sm:inline">{{ __('ptah::ui.btn_filters') }}</span>
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
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-all duration-150 focus:outline-none
                       {{ $showTrashed ? 'bg-red-50 text-red-600 border-red-200' : 'ptah-c-btn' }}"
                title="{{ $showTrashed ? __('ptah::ui.btn_view_active') : __('ptah::ui.btn_view_trash') }}">
                @if ($showTrashed)
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                    </svg>
                    <span class="hidden sm:inline">{{ __('ptah::ui.btn_back') }}</span>
                @else
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span class="hidden sm:inline">{{ __('ptah::ui.btn_trash') }}</span>
                @endif
            </button>
        @endif

        {{-- Exportação --}}
        @if (!empty($exportCfg['enabled']))
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-all duration-150 focus:outline-none ptah-c-btn"
                    title="{{ __('ptah::ui.btn_export') }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="hidden sm:inline">{{ __('ptah::ui.btn_export') }}</span>
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 mt-1 border rounded-md z-20 min-w-[160px] py-1.5 ptah-c-dd">
                    @foreach ($exportCfg['formats'] ?? ['excel'] as $fmt)
                        <button wire:click="export('{{ $fmt }}')" @click="open = false"
                            class="flex items-center gap-2.5 w-full px-4 py-2 text-sm ptah-c-dd_item">
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
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-all duration-150 focus:outline-none
                           {{ $hiddenColumnsCount > 0 ? 'text-amber-600 bg-amber-50 border-amber-200 hover:bg-amber-100' : 'ptah-c-btn' }}"
                    title="{{ __('ptah::ui.btn_columns') }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M3 14h18M10 6v12M14 6v12"/>
                    </svg>
                    <span class="hidden sm:inline">{{ __('ptah::ui.btn_columns') }}</span>
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
                     class="absolute right-0 mt-1 border rounded-md z-20 min-w-[220px] py-2 max-h-80 overflow-y-auto ptah-c-dd">
                    {{-- Ações rápidas --}}
                    <div class="flex gap-2 px-3 pb-2 mb-1 border-b border-gray-100">
                        <button wire:click="showAllColumns" @click="open = false"
                            class="flex-1 py-1 text-xs text-center text-gray-700 transition-colors bg-gray-100 rounded hover:bg-gray-200">
                            {{ __('ptah::ui.col_show_all') }}
                        </button>
                        <button wire:click="hideAllColumns" @click="open = false"
                            class="flex-1 py-1 text-xs text-center text-gray-700 transition-colors bg-gray-100 rounded hover:bg-gray-200">
                            {{ __('ptah::ui.col_hide_all') }}
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
                'compact'     => ['icon' => '≡', 'label' => __('ptah::ui.density_compact')],
                'comfortable' => ['icon' => '☰', 'label' => __('ptah::ui.density_comfortable')],
                'spacious'    => ['icon' => '⊟', 'label' => __('ptah::ui.density_spacious')],
            ];
        @endphp
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-all duration-150 focus:outline-none ptah-c-btn"
                title="{{ __('ptah::ui.btn_density') }}">
                <span class="text-sm leading-none">{{ $densityMap[$viewDensity]['icon'] ?? '☰' }}</span>
                <span class="hidden sm:inline">{{ __('ptah::ui.btn_density') }}</span>
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false"
                 class="absolute right-0 mt-1 border rounded-md z-20 min-w-[180px] py-1 ptah-c-dd">
                @foreach ($densityMap as $d => $info)
                    <button wire:click="$set('viewDensity', '{{ $d }}')" @click="open = false"
                        class="flex items-center justify-between w-full px-4 py-2 text-sm transition-colors ptah-c-dd_item
                               {{ $viewDensity === $d ? 'font-semibold text-blue-600' : '' }}">
                        <span>{{ $info['icon'] }} {{ $info['label'] }}</span>
                        @if ($viewDensity === $d)
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Configuração do CRUD (somente admins) --}}
        @livewire('ptah-crud-config', ['model' => $model], key('crud-cfg-'.$model))

        {{-- Atualizar --}}
        <button wire:click="$refresh"
            class="inline-flex items-center justify-center p-2 transition-colors border rounded-md focus:outline-none ptah-c-btn"
            title="{{ __('ptah::ui.btn_refresh') }}">
            <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="$refresh"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </button>

        {{-- Limpar filtros (visível quando houver algo ativo) --}}
        @if ($search !== '' || !empty(array_filter($filters)) || $showTrashed)
            <button wire:click="clearFilters"
                class="inline-flex items-center justify-center p-2 transition-colors border rounded-md focus:outline-none hover:bg-red-50 hover:text-red-500 hover:border-red-200 ptah-c-clear_btn"
                title="{{ __('ptah::ui.btn_clear_filters') }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif

        {{-- Per page --}}
        <select wire:model.live="perPage"
            class="text-sm border rounded-md px-2 py-1.5 focus:outline-none focus:ring-2 ptah-c-perpage">
            @foreach ([10, 15, 25, 50, 100] as $n)
                <option value="{{ $n }}">{{ $n }} {{ __('ptah::ui.per_page_suffix') }}</option>
            @endforeach
        </select>

    </div>
</div>




{{-- ── Painel de Filtros ────────────────────────────────────────────── --}}
@if ($showFilters)
    <div class="mb-4 overflow-hidden border rounded-md ptah-c-fp_card">

        <div class="flex items-center justify-between px-5 py-3.5 border-b ptah-c-fp_hd">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span class="text-sm font-semibold ptah-c-fp_text">{{ __('ptah::ui.filters_title') }}</span>
                @if (count($textFilter ?? []) > 0)
                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-medium border border-blue-100">
                        {{ count($textFilter) }} ativo{{ count($textFilter) > 1 ? 's' : '' }}
                    </span>
                @endif
            </div>
            <x-forge-button wire:click="clearFilters" flat color="danger" size="sm">
                <x-slot name="icon">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </x-slot>
                {{ __('ptah::ui.filters_clear_all') }}
            </x-forge-button>
        </div>

        <div class="p-4 space-y-4">

            {{-- Atalhos rápidos de data --}}
            @php
                $hasDateFilterCols = collect($crudConfig['cols'] ?? [])
                    ->where('colsIsFilterable', 'S')
                    ->where('colsTipo', 'date')
                    ->isNotEmpty();
                $quickLabels = [
                    'today'     => __('ptah::ui.date_today'),
                    'yesterday' => __('ptah::ui.date_yesterday'),
                    'last7'     => __('ptah::ui.date_last7'),
                    'last30'    => __('ptah::ui.date_last30'),
                    'week'      => __('ptah::ui.date_week'),
                    'month'     => __('ptah::ui.date_month'),
                    'lastMonth' => __('ptah::ui.date_last_month'),
                    'quarter'   => __('ptah::ui.date_quarter'),
                    'year'      => __('ptah::ui.date_year'),
                ];
            @endphp
            @if ($hasDateFilterCols)
                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-slate-500">{{ __('ptah::ui.filters_date_shortcuts') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($quickLabels as $period => $qlabel)
                            <button wire:click="applyQuickDateFilter('{{ $period }}')"
                                class="px-2.5 py-1 text-xs rounded-md border transition-colors duration-150
                                    {{ $quickDateFilter === $period
                                        ? 'bg-blue-700 text-white border-blue-700'
                                        : 'bg-white text-slate-600 border-slate-200 hover:border-blue-400 hover:text-blue-600' }}">
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
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="mb-1 text-xs text-gray-400">{{ __('ptah::ui.filters_date_from') }}</p>
                                        <div class="flex gap-1">
                                            <select wire:model.live="dateRangeOperators.{{ $cfField }}_start"
                                                class="text-xs rounded-md px-1.5 py-2 w-[58px] shrink-0 ptah-c-fp_input">
                                                <option value=">=">&ge;</option>
                                                <option value=">">&gt;</option>
                                                <option value="=">=</option>
                                            </select>
                                            <input type="date"
                                                wire:model.live="dateRanges.{{ $cfField }}_start"
                                                class="flex-1 min-w-0 text-sm rounded-md px-2 py-1.5 ptah-c-fp_input" />
                                        </div>
                                    </div>
                                    <div>
                                        <p class="mb-1 text-xs text-gray-400">{{ __('ptah::ui.filters_date_to') }}</p>
                                        <div class="flex gap-1">
                                            <select wire:model.live="dateRangeOperators.{{ $cfField }}_end"
                                                class="text-xs rounded-md px-1.5 py-2 w-[58px] shrink-0 ptah-c-fp_input">
                                                <option value="<=">&le;</option>
                                                <option value="<">&lt;</option>
                                                <option value="=">=</option>
                                            </select>
                                            <input type="date"
                                                wire:model.live="dateRanges.{{ $cfField }}_end"
                                                class="flex-1 min-w-0 text-sm rounded-md px-2 py-1.5 ptah-c-fp_input" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                        {{-- Select / Enum --}}
                        @elseif ($cfTipo === 'select' && !empty($col['colsSelect']))
                            @php
                                $fFilterOpts = array_map(
                                    fn($l, $v) => ['value' => $v, 'label' => $l],
                                    array_keys($col['colsSelect']),
                                    array_values($col['colsSelect'])
                                );
                            @endphp
                            <x-forge-select
                                wire:model.live="filters.{{ $cfField }}"
                                :label="$cfLabel"
                                :placeholder="__('ptah::ui.filters_all')"
                                :options="$fFilterOpts"
                            />

                        {{-- SearchDropdown no filtro (select2-like) --}}
                        @elseif ($cfTipo === 'searchdropdown')
                            @php
                                $cfFilterKey      = 'filter_' . $cfField;
                                $cfFilterSelected = $sdFilterLabels[$cfField] ?? null;
                                $cfFilterHasRes   = !empty($sdResults[$cfFilterKey]);
                            @endphp
                            <div>
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>

                                {{-- Badge de seleção ativa --}}
                                @if ($cfFilterSelected)
                                    <div class="flex items-center gap-1 mb-1.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                            {{ $cfFilterSelected }}
                                            <button type="button"
                                                wire:click="clearFilterDropdownSelection('{{ $cfField }}')"
                                                class="ml-0.5 hover:text-blue-900 leading-none">&times;</button>
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
                                            placeholder="{{ $cfFilterSelected ? __('ptah::ui.filters_change') : __('ptah::ui.filters_search_label', ['label' => $cfLabel]) }}"
                                            autocomplete="off"
                                            class="w-full text-sm rounded-md px-2.5 py-2 pr-8 ptah-c-fp_input"
                                        />
                                        <button type="button"
                                            tabindex="-1"
                                            @mousedown.prevent="open = !open; if (open) $wire.openFilterDropdown('{{ $cfField }}')"
                                            class="absolute text-gray-400 transition-transform duration-200 right-2 hover:text-gray-600"
                                            :class="open ? 'rotate-180' : ''">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div x-show="open" x-cloak
                                        class="absolute z-30 w-full mt-1 overflow-y-auto rounded-md shadow-lg max-h-48 ptah-c-dd">
                                        @forelse ($sdResults[$cfFilterKey] ?? [] as $opt)
                                            <button type="button"
                                                wire:click="selectFilterDropdownOption('{{ $cfField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                @click="open = false"
                                                class="block w-full px-3 py-2 text-sm text-left ptah-c-dd_opt">
                                                {{ $opt['label'] }}
                                            </button>
                                        @empty
                                            <p class="px-3 py-2 text-xs italic text-gray-400">{{ __('ptah::ui.filters_no_results') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                        {{-- Text / Number com operador --}}
                        @else
                            @php $isNum = $cfTipo === 'number'; @endphp
                            <div>
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>
                                <div class="flex gap-1">
                                    <select wire:model.live="filterOperators.{{ $cfField }}"
                                        class="text-xs rounded-md px-1.5 py-2 w-[90px] shrink-0 ptah-c-fp_input">
                                        @if ($isNum)
                                            <option value="=">=</option>
                                            <option value="!=">&ne;</option>
                                            <option value=">">&gt;</option>
                                            <option value=">=">&ge;</option>
                                            <option value="<">&lt;</option>
                                            <option value="<=">&le;</option>
                                        @else
                                            <option value="LIKE">{{ __('ptah::ui.filters_op_contains') }}</option>
                                            <option value="=">{{ __('ptah::ui.filters_op_equals') }}</option>
                                            <option value="!=">{{ __('ptah::ui.filters_op_not_equals') }}</option>
                                            <option value="LIKE_START">{{ __('ptah::ui.filters_op_starts') }}</option>
                                            <option value="LIKE_END">{{ __('ptah::ui.filters_op_ends') }}</option>
                                        @endif
                                    </select>
                                    <input type="{{ $isNum ? 'number' : 'text' }}"
                                        wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                        placeholder="{{ $cfLabel }}..."
                                        @if($isNum) step="any" @endif
                                        class="flex-1 min-w-0 text-sm rounded-md px-2.5 py-2 ptah-c-fp_input" />
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
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>
                                @if ($cfType === 'searchdropdown')
                                    <div class="relative" x-data="{ open: false }">
                                        <input type="text"
                                            wire:keyup="searchDropdown('cf_{{ $cfField }}', $event.target.value)"
                                            @focus="open = true"
                                            @click.outside="open = false"
                                            placeholder="{{ __('ptah::ui.filters_search_label', ['label' => $cfLabel]) }}"
                                            class="w-full text-sm rounded-md px-2.5 py-2 ptah-c-fp_input" />
                                        @if (!empty($sdResults['cf_' . $cfField]))
                                            <div x-show="open" class="absolute z-30 w-full mt-1 overflow-y-auto rounded-md shadow-lg max-h-48 ptah-c-dd">
                                                @foreach ($sdResults['cf_' . $cfField] as $opt)
                                                    <button wire:click="selectDropdownOption('{{ $cfField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                        @click="open = false"
                                                        class="block w-full px-3 py-2 text-sm text-left ptah-c-dd_opt">
                                                        {{ $opt['label'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($cfType === 'date')
                                    <x-forge-input
                                        type="date"
                                        wire:model.live="filters.{{ $cfField }}"
                                    />
                                @elseif ($cfType === 'number')
                                    <x-forge-input
                                        type="number"
                                        wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                        :placeholder="$cfLabel . '...'"
                                        step="any"
                                    />
                                @elseif ($cfType === 'select' && !empty($cf['colsSelect']))
                                    @php
                                        $cfSelectOpts = array_map(
                                            fn($l, $v) => ['value' => $v, 'label' => $l],
                                            array_keys($cf['colsSelect']),
                                            array_values($cf['colsSelect'])
                                        );
                                    @endphp
                                    <x-forge-select
                                        wire:model.live="filters.{{ $cfField }}"
                                        :placeholder="__('ptah::ui.filters_all')"
                                        :options="$cfSelectOpts"
                                    />
                                @else
                                    <x-forge-input
                                        wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                        :placeholder="$cfLabel . '...'"
                                    />
                                @endif
                            </div>
                        @endif
                    @endforeach

                </div>
            @endif

            {{-- Filtros salvos --}}
            @if (!empty($savedFilters))
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="text-xs font-medium text-gray-400">{{ __('ptah::ui.filters_saved') }}</span>
                    @foreach (array_keys($savedFilters) as $sfName)
                        <div class="flex items-center">
                            <button wire:click="loadNamedFilter('{{ $sfName }}')"
                                class="text-xs bg-blue-50 text-blue-600 px-2.5 py-1 rounded-l-md hover:bg-blue-100 transition-colors border border-blue-100 border-r-0">
                                {{ $sfName }}
                            </button>
                            <button wire:click="deleteNamedFilter('{{ $sfName }}')"
                                class="text-xs bg-red-50 text-red-500 px-1.5 py-1 rounded-r-lg hover:bg-red-100 transition-colors border border-red-100">
                                &times;
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>{{-- /p-4 --}}

        {{-- Footer: salvar filtro --}}
        <div class="flex items-center gap-2 px-5 py-3 border-t ptah-c-fp_ft"
             x-data="{ saving: false, name: '' }">
            <template x-if="!saving">
                <button @click="saving = true"
                    class="flex items-center gap-1 text-xs transition-colors text-slate-500 hover:text-blue-600">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                    </svg>
                    {{ __('ptah::ui.filters_save_action') }}
                </button>
            </template>
            <template x-if="saving">
                <div class="flex items-center w-full gap-2">
                    <input type="text" x-model="name"
                        @keydown.enter="if(name.trim()) { $wire.saveNamedFilter(name.trim()); saving = false; name = ''; }"
                        @keydown.escape="saving = false; name = '';"
                        placeholder="{{ __('ptah::ui.filters_save_placeholder') }}"
                        class="flex-1 text-sm border rounded-md px-3 py-1.5 ptah-c-fp_save_in"
                        x-init="$nextTick(() => $el.focus())" />
                    <button @click="if(name.trim()) { $wire.saveNamedFilter(name.trim()); saving = false; name = ''; }"
                        class="text-xs bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary/90 transition-colors">
                        {{ __('ptah::ui.filters_btn_save') }}
                    </button>
                    <button @click="saving = false; name = '';"
                        class="text-xs text-gray-400 transition-colors hover:text-gray-600">
                        {{ __('ptah::ui.filters_btn_cancel') }}
                    </button>
                </div>
            </template>
        </div>

    </div>
@endif



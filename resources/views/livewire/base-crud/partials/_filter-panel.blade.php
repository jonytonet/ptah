{{-- ── Painel de Filtros ────────────────────────────────────────────── --}}
@php
    // PTAH_CRUD_FILTER_PANEL=modal (1.62.0): o MESMO painel, num dialogo
    // sobre a tela; o acordeao continua o padrao. Esc, fundo e o ✕ fecham.
    // No modal o painel ja vem na pagina e abre/fecha no navegador (1.62.1):
    // cada abertura esperava o servidor, e o React abria na hora.
    $fpModal = $this->filterPanelIsModal();
@endphp
@if ($showFilters || $fpModal)
    @if ($fpModal)
        {{-- Tamanho e espaco em style, nao em utilitarios novos: um host que
             atualizou o ptah e nao recompilou o CSS via o modal sem limite de
             largura e colado no topo (visto no PetPlace, 1.62.1). --}}
        {{-- 1.63.2: a mesma casca do forge-modal e do dialogo de filtros do
             React/Vue — centrado, cabecalho com titulo e ✕, so o corpo rola,
             rodape com o botao. --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="padding: 1rem"
             x-data="{ open: $wire.entangle('showFilters') }" x-show="open" x-cloak x-trap.noscroll="open"
             @keydown.escape.window="if (open) open = false" data-ptah-filter-modal>
            <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="open = false" aria-hidden="true"></div>
            <div role="dialog" aria-modal="true" aria-labelledby="ptah-filter-modal-title"
                 @if (config('ptah.crud.modal_draggable', true)) @include('ptah::partials.drag-panel', ['open' => 'open']) @endif
                 class="ptah-modal-panel relative flex w-full flex-col rounded-xl border shadow-2xl" style="max-width: 42rem; max-height: 90vh">
                <div class="shrink-0 flex items-center justify-between px-6 py-4 border-b ptah-c-modal_rule @if (config('ptah.crud.modal_draggable', true)) cursor-move select-none @endif"
                     @if (config('ptah.crud.modal_draggable', true)) @include('ptah::partials.drag-handle') @endif>
                    <h3 id="ptah-filter-modal-title" class="text-base font-semibold">{{ __('ptah::ui.filters_title') }}</h3>
                    <button type="button" @click="open = false"
                        class="ml-4 shrink-0 rounded ptah-c-fp_text transition-opacity duration-150 hover:opacity-70 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        title="{{ __('ptah::ui.filters_close') }}" aria-label="{{ __('ptah::ui.filters_close') }}" data-ptah-filter-modal-close>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-5" style="min-height: 0">
    @endif
    <div class="{{ $fpModal ? '' : 'mb-4 overflow-hidden border rounded-lg ptah-c-fp_card' }}">

        {{-- Clicar no cabecalho fecha o painel (1.60.3), como o botao Filtros da barra. --}}
        @if ($fpModal)
        <div class="flex items-center justify-between gap-2 pb-3">
            <div class="flex items-center gap-2">
        @else
        <div class="flex items-center justify-between px-5 py-3.5 border-b ptah-c-fp_hd cursor-pointer select-none"
             wire:click="toggleFilters" title="{{ __('ptah::ui.filters_close') }}">
            <div class="flex items-center gap-2">
        @endif
                @unless ($fpModal)
                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span class="text-sm font-semibold ptah-c-fp_text">{{ __('ptah::ui.filters_title') }}</span>
                @endunless
                @if (count($textFilter ?? []) > 0)
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium border ptah-c-active_badge">
                        {{ trans_choice('ptah::ui.filters_active_count', count($textFilter), ['count' => count($textFilter)]) }}
                    </span>
                @endif
            </div>
            <div class="flex items-center">
            <x-forge-button wire:click.stop="clearFilters" flat color="danger" size="sm">
                <x-slot name="icon">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </x-slot>
                {{ __('ptah::ui.filters_clear_all') }}
            </x-forge-button>
            </div>
        </div>

        <div class="{{ $fpModal ? '' : 'p-4' }} space-y-4">

            {{-- Atalhos rápidos de data --}}
            @php
                // Filterable as the engine reads it (true, 'S', 1, '1') — compared
                // loosely with 'S' here, so a column saved as 1 or '1' filtered
                // by date but never offered the shortcuts.
                $hasDateFilterCols = collect($crudConfig['cols'] ?? [])
                    ->filter(fn ($col) => in_array($col['colsIsFilterable'] ?? false, [true, 'S', 1, '1'], true)
                        && ($col['colsTipo'] ?? '') === 'date')
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
                @php
                    // A qual campo os atalhos se aplicam (1.61.0): sem isso, "Hoje"
                    // nao dizia se era criado, resolvido ou vencimento.
                    $qdCol = (string) ($quickDateColumn ?: 'created_at');
                    $qdLabel = collect($crudConfig['cols'] ?? [])->firstWhere('colsNomeFisico', $qdCol)['colsNomeLogico'] ?? null;
                    $qdLabel = $qdLabel ?: __($qdCol === 'created_at' ? 'ptah::ui.col_created_at' : $qdCol);
                @endphp
                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wider uppercase ptah-c-fp_label">{{ __('ptah::ui.filters_date_shortcuts') }} ({{ $qdLabel }})</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($quickLabels as $period => $qlabel)
                            <button wire:click="applyQuickDateFilter('{{ $period }}')"
                                class="px-2.5 py-1 text-xs rounded-md border transition-colors duration-150
                                    {{ $quickDateFilter === $period ? 'ptah-c-ql_btn_on' : 'ptah-c-ql_btn' }}">
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
            {{-- Os filtros customizados moram neste mesmo grid: sem o `||`, uma tela
                 so com customFilters (nenhuma coluna filtravel) nao mostrava nenhum. --}}
            @if (!empty($filterableCfCols) || !empty($crudConfig['customFilters']))
                {{-- No modal, um filtro por linha (1.63.2): mais facil de ler; o acordeao segue em grade. --}}
                <div class="grid grid-cols-1 {{ $fpModal ? '' : 'sm:grid-cols-2 lg:grid-cols-3' }} gap-x-4 gap-y-3" @if ($fpModal) data-ptah-filter-stack @endif>

                    @foreach ($filterableCfCols as $col)
                        @php
                            $cfField = $col['colsNomeFisico'];
                            $cfLabel = $col['colsNomeLogico'] ?? $cfField;
                            $cfTipo  = $col['colsTipo'] ?? 'text';
                        @endphp

                        {{-- Date: mostra De / Até com operador --}}
                        @if ($cfTipo === 'date')
                            <div class="{{ $fpModal ? '' : 'sm:col-span-2' }}">
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="mb-1 text-xs ptah-c-fp_muted">{{ __('ptah::ui.filters_date_from') }}</p>
                                        <div class="flex gap-1">
                                            <select wire:model.live="dateRangeOperators.{{ $cfField }}_start"
                                                class="text-xs rounded-md px-1.5 py-2 w-[58px] shrink-0 ptah-c-fp_input ptah-c-control">
                                                <option value=">=">&ge;</option>
                                                <option value=">">&gt;</option>
                                                <option value="=">=</option>
                                            </select>
                                            <input type="date"
                                                wire:model.live="dateRanges.{{ $cfField }}_start"
                                                class="flex-1 min-w-0 text-sm rounded-md px-2 py-1.5 ptah-c-fp_input ptah-c-control" />
                                        </div>
                                    </div>
                                    <div>
                                        <p class="mb-1 text-xs ptah-c-fp_muted">{{ __('ptah::ui.filters_date_to') }}</p>
                                        <div class="flex gap-1">
                                            <select wire:model.live="dateRangeOperators.{{ $cfField }}_end"
                                                class="text-xs rounded-md px-1.5 py-2 w-[58px] shrink-0 ptah-c-fp_input ptah-c-control">
                                                <option value="<=">&le;</option>
                                                <option value="<">&lt;</option>
                                                <option value="=">=</option>
                                            </select>
                                            <input type="date"
                                                wire:model.live="dateRanges.{{ $cfField }}_end"
                                                class="flex-1 min-w-0 text-sm rounded-md px-2 py-1.5 ptah-c-fp_input ptah-c-control" />
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
                                // Os que NAO tem valor (1.46.0): sem isto, um select so
                                // filtrava por um dos valores da lista.
                                $fFilterOpts[] = ['value' => \Ptah\Livewire\BaseCrud\BaseCrud::FILTER_EMPTY, 'label' => '('.__('ptah::ui.filters_op_is_null').')'];
                                $fFilterOpts[] = ['value' => \Ptah\Livewire\BaseCrud\BaseCrud::FILTER_FILLED, 'label' => '('.__('ptah::ui.filters_op_is_not_null').')'];
                            @endphp
                            @if ($this->filterAllowsMultiple($col))
                                {{-- Mais de um (colsFilterMultiple, 1.63.0): cada opcao e um
                                     chip que liga e desliga; nenhum ligado = todos. --}}
                                @php $fSelOn = (array) ($filters[$cfField] ?? []); @endphp
                                <div>
                                    <p class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</p>
                                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ $cfLabel }}" data-ptah-filter-multi="{{ $cfField }}">
                                        @foreach ($col['colsSelect'] as $fOptLabel => $fOptValue)
                                            @php $fOn = in_array((string) $fOptValue, array_map('strval', $fSelOn), true); @endphp
                                            <button type="button"
                                                wire:click="toggleFilterValue({{ \Illuminate\Support\Js::from($cfField) }}, {{ \Illuminate\Support\Js::from((string) $fOptValue) }})"
                                                aria-pressed="{{ $fOn ? 'true' : 'false' }}"
                                                class="px-2.5 py-1 text-xs rounded-md border transition-colors duration-150 {{ $fOn ? 'ptah-c-ql_btn_on' : 'ptah-c-ql_btn' }}">
                                                {{ $fOptLabel }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <x-forge-select
                                    wire:model.live="filters.{{ $cfField }}"
                                    :label="$cfLabel"
                                    :placeholder="__('ptah::ui.filters_all')"
                                    :options="$fFilterOpts"
                                />
                            @endif

                        {{-- SearchDropdown no filtro (select2-like) --}}
                        @elseif ($cfTipo === 'searchdropdown')
                            @php
                                $cfFilterKey      = 'filter_' . $cfField;
                                $cfFilterSelected = $sdFilterLabels[$cfField] ?? null;
                                $cfSdSettings     = $this->sdSettings($col);
                            @endphp
                            <div>
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>

                                {{-- Operador: igual / diferente (item 5) --}}
                                <select wire:model.live="filterOperators.{{ $cfField }}"
                                    class="w-full text-xs rounded-md px-2 py-1.5 mb-1.5 ptah-c-fp_input ptah-c-control">
                                    <option value="=">{{ __('ptah::ui.filters_op_equals') }}</option>
                                    <option value="!=">{{ __('ptah::ui.filters_op_not_equals') }}</option>
                                    <option value="IS NULL">{{ __('ptah::ui.filters_op_is_null') }}</option>
                                    <option value="IS NOT NULL">{{ __('ptah::ui.filters_op_is_not_null') }}</option>
                                </select>

                                {{-- Badge de seleção ativa. Mais de um (colsFilterMultiple,
                                     1.63.0): um chip por item, cada um com o seu ✕; a busca
                                     abaixo continua para acrescentar outro. --}}
                                @if (is_array($cfFilterSelected) && $cfFilterSelected !== [])
                                    <div class="flex flex-wrap items-center gap-1 mb-1.5" data-ptah-filter-multi="{{ $cfField }}">
                                        @foreach ($cfFilterSelected as $cfVal => $cfValLabel)
                                            <span class="ptah-c-chip" wire:key="fchip-{{ $cfField }}-{{ $cfVal }}">
                                                {{ $cfValLabel }}
                                                <button type="button"
                                                    wire:click="removeFilterValue({{ \Illuminate\Support\Js::from($cfField) }}, {{ \Illuminate\Support\Js::from((string) $cfVal) }})"
                                                    class="leading-none" title="{{ __('ptah::ui.filters_remove', ['label' => $cfValLabel]) }}" aria-label="{{ __('ptah::ui.filters_remove', ['label' => $cfValLabel]) }}">&times;</button>
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif ($cfFilterSelected)
                                    <div class="flex items-center gap-1 mb-1.5">
                                        <span class="ptah-c-chip">
                                            {{ $cfFilterSelected }}
                                            <button type="button"
                                                wire:click="clearFilterDropdownSelection('{{ $cfField }}')"
                                                class="leading-none" title="{{ __('ptah::ui.btn_clear_filters') }}">&times;</button>
                                        </span>
                                    </div>
                                @endif

                                {{-- Static x-data (1.60.5): with the open state written into
                                     it, every render changed the attribute and the morph
                                     restarted the dropdown — same cause as the form's
                                     searchdropdown loop fixed in 1.60.3. --}}
                                <div
                                    x-data="{
                                        open: false,
                                        init() {
                                            const res0 = this.$wire.sdResults ? this.$wire.sdResults['{{ $cfFilterKey }}'] : undefined;
                                            this.open = Array.isArray(res0) && res0.length > 0;
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
                                            placeholder="{{ $cfFilterSelected ? __('ptah::ui.filters_change') : ($cfSdSettings['placeholder'] ?: __('ptah::ui.filters_search_label', ['label' => $cfLabel])) }}"
                                            autocomplete="off"
                                            class="w-full text-sm rounded-md px-2.5 py-2 pr-8 ptah-c-fp_input ptah-c-control"
                                        />
                                        <button type="button"
                                            tabindex="-1"
                                            @mousedown.prevent="open = !open; if (open) $wire.openFilterDropdown('{{ $cfField }}')"
                                        class="absolute transition-transform duration-200 right-2 ptah-c-fp_chevron"
                                            :class="open ? 'rotate-180' : ''">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div x-show="open" x-cloak
                                        x-init="@include('ptah::partials.float', ['open' => 'open', 'up' => $cfSdSettings['startList'] === 'top'])"
                                        class="absolute z-50 w-full {{ $cfSdSettings['startList'] === 'top' ? 'bottom-full mb-1' : 'mt-1' }} overflow-y-auto rounded-md shadow-lg max-h-48 ptah-c-dd">
                                        @forelse ($sdResults[$cfFilterKey] ?? [] as $opt)
                                            <button type="button"
                                                wire:click="selectFilterDropdownOption({{ \Illuminate\Support\Js::from($cfField) }}, {{ \Illuminate\Support\Js::from($opt['value']) }}, {{ \Illuminate\Support\Js::from((string) $opt['label']) }})"
                                                    @click="open = false; $root.querySelector('input').value = ''"
                                                class="block w-full px-3 py-2 text-sm text-left ptah-c-dd_opt">
                                                {{ $opt['label'] }}
                                                @if (array_key_exists('labelTwo', $opt))
                                                    <span class="block text-[11px] opacity-70">{{ $opt['labelTwo'] }}</span>
                                                @endif
                                                @if (array_key_exists('labelThree', $opt))
                                                    <span class="block text-[11px] opacity-70">{{ $opt['labelThree'] }}</span>
                                                @endif
                                            </button>
                                        @empty
                                            <p class="px-3 py-2 text-xs italic ptah-c-fp_muted">{{ __('ptah::ui.filters_no_results') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                        {{-- Text / Number com operador --}}
                        @else
                            @php
                                $isNum = $cfTipo === 'number';
                                // Dinheiro em centavos (1.62.0): digita-se em reais, com virgula
                                // ou ponto ("2.160,00") — um input number nem aceitaria isso.
                                $isCents = $this->isCentsColumn($col);
                                // Value input is disabled for the value-less NULL operators.
                                $cfOp = $filterOperators[$cfField] ?? null;
                                $isNullOp = in_array(strtoupper((string) $cfOp), ['IS NULL', 'IS NOT NULL'], true);
                            @endphp
                            <div>
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>
                                <div class="flex gap-1">
                                    <select wire:model.live="filterOperators.{{ $cfField }}"
                                        class="text-xs rounded-md px-1.5 py-2 w-[90px] shrink-0 ptah-c-fp_input ptah-c-control">
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
                                        {{-- Value-less operators (item 2) --}}
                                        <option value="IS NULL">{{ __('ptah::ui.filters_op_is_null') }}</option>
                                        <option value="IS NOT NULL">{{ __('ptah::ui.filters_op_is_not_null') }}</option>
                                    </select>
                                    <input type="{{ $isNum && ! $isCents ? 'number' : 'text' }}"
                                        wire:model.live.debounce.400ms="filters.{{ $cfField }}"
                                        placeholder="{{ $isNullOp ? '—' : ($isCents ? '0,00' : $cfLabel.'...') }}"
                                        @if($isCents) inputmode="decimal" @elseif($isNum) step="any" @endif
                                        @disabled($isNullOp)
                                        class="flex-1 min-w-0 text-sm rounded-md px-2.5 py-2 ptah-c-fp_input ptah-c-control disabled:opacity-50 disabled:cursor-not-allowed" />
                                </div>
                            </div>
                        @endif

                    @endforeach

                    {{-- CustomFilters --}}
                    @foreach ($crudConfig['customFilters'] ?? [] as $cf)
                        @php
                            $cfField = $cf['field'] ?? '';
                            $cfLabel = $cf['label'] ?? $cf['field'] ?? '';
                            // Um filtro customizado `searchdropdown` nunca funcionou (a busca
                            // procurava uma coluna `cf_<campo>`, e o clique gravava no
                            // formulario, nao no filtro). Desde a 1.41.5 ele vira o filtro de
                            // texto, que filtra de fato pelo valor digitado.
                            $cfType  = $cf['colsFilterType'] ?? 'text';
                            if ($cfType === 'searchdropdown') {
                                $cfType = 'text';
                            }
                        @endphp
                        @if ($cfField)
                            <div>
                                <label class="block text-xs font-medium mb-1.5 ptah-c-fp_label">{{ $cfLabel }}</label>
                                @if ($cfType === 'date')
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
                    <span class="text-xs font-medium ptah-c-fp_label">{{ __('ptah::ui.filters_saved') }}</span>
                    @foreach (array_keys($savedFilters) as $sfName)
                        <div class="flex items-center">
                            <button wire:click="loadNamedFilter({{ \Illuminate\Support\Js::from((string) $sfName) }})"
                                class="text-xs px-2.5 py-1 rounded-l-md transition-colors border border-r-0 ptah-c-saved_filter_btn">
                                {{ $sfName }}
                            </button>
                            <button wire:click="deleteNamedFilter({{ \Illuminate\Support\Js::from((string) $sfName) }})"
                                class="text-xs px-1.5 py-1 rounded-r-lg transition-colors border ptah-c-saved_filter_del">
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
                    class="flex items-center gap-1 text-xs transition-colors ptah-c-fp_save_btn">
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
                        class="text-xs transition-colors ptah-c-fp_cancel_btn">
                        {{ __('ptah::ui.filters_btn_cancel') }}
                    </button>
                </div>
            </template>
        </div>

    </div>
    @if ($fpModal)
                </div>
                {{-- Rodape do modal (1.62.1): so o ✕ para fechar era estranho — os
                     filtros ja valem ao digitar, e o botao diz o que acontece. --}}
                <div class="shrink-0 flex flex-wrap items-center justify-end gap-3 px-6 py-4 border-t ptah-c-modal_rule" data-ptah-filter-modal-footer>
                    <x-forge-button @click="open = false" color="primary" data-ptah-filter-modal-done>{{ __('ptah::ui.filters_show_results') }}</x-forge-button>
                </div>
            </div>
        </div>
    @endif
@endif

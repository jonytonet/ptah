{{-- ── Tabela ──────────────────────────────────────────────────────── --}}
{{-- Loading indicator (filter/search/sort) --}}
<div wire:loading.delay class="h-0.5 mb-2 overflow-hidden rounded-full bg-primary/10">
    <div class="h-full w-full ptah-loading-bar"></div>
</div>

<div class="overflow-x-auto border rounded-lg ptah-c-tbl_wrap transition-opacity duration-300"
     wire:loading.class="opacity-60"
     id="ptah-table-wrap-{{ $crudTitle }}">
    <table class="{{ $crudConfig['tableClass'] ?? 'table' }} ptah-cols-table w-full text-sm
        @if($viewDensity === 'compact') text-xs @elseif($viewDensity === 'spacious') text-base @endif">

        @php $masterDetails = $crudConfig['masterDetail'] ?? []; @endphp
        <thead class="{{ $crudConfig['theadClass'] ?? 'ptah-c-thead' }}">
            <tr id="ptah-thead-row-{{ $crudTitle }}">
                @if (!empty($masterDetails))
                    <th class="w-8 px-2 py-3 ptah-no-print"></th>
                @endif
                @if ($effectivePerms['canDelete'])
                    <th class="px-3 py-3 w-8 ptah-c-th_text ptah-no-print">
                        <input type="checkbox" wire:click="toggleSelectAll" @checked($selectAll)
                               aria-label="{{ __('ptah::ui.bulk_select_all') }}"
                               class="rounded cursor-pointer text-primary focus:ring-primary/30">
                    </th>
                @endif
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
                        <th class="group/th relative px-3 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap ptah-c-th_text {{ $colAlign }} ptah-sortable-col"
                            data-column="{{ $colField }}"
                            style="{{ $thStyle }}"
                            @if($isSortable)
                                aria-sort="{{ $sort === $colSortBy ? ($direction === 'ASC' ? 'ascending' : 'descending') : 'none' }}"
                            @endif
                            draggable="true"
                            ondragstart="ptahColDragStart(event, '{{ $crudTitle }}')"
                            ondragover="ptahColDragOver(event)"
                            ondrop="ptahColDragDrop(event, '{{ $crudTitle }}')"
                            ondragend="ptahColDragEnd(event)">
                            <div class="flex items-center gap-1.5">
                                {{-- Grip (initia o drag) — visible only while hovering the header --}}
                                <span class="text-gray-300 transition-opacity select-none ptah-drag-grip shrink-0 cursor-grab hover:text-gray-500 opacity-0 group-hover/th:opacity-100"
                                      title="{{ __('ptah::ui.col_drag_title') }}">
                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                        <circle cx="7" cy="5"  r="1.5"/><circle cx="13" cy="5"  r="1.5"/>
                                        <circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>
                                        <circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/>
                                    </svg>
                                </span>
                                {{-- Label (sort) — a real button so keyboard users can sort --}}
                                @if ($isSortable)
                                    <button type="button" wire:click.stop="sortBy('{{ $colSortBy }}')"
                                        class="flex-1 inline-flex items-center gap-1 cursor-pointer select-none ptah-c-sort_hover bg-transparent border-0 p-0 text-inherit font-semibold uppercase tracking-wider text-xs"
                                        aria-label="{{ __('ptah::ui.sort_by_column', ['column' => $colLabel]) }}">
                                        {{ $colLabel }}
                                        @if ($sort === $colSortBy)
                                            @if ($direction === 'ASC')
                                                <svg class="w-3 h-3 shrink-0 ptah-c-sort_active" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                            @else
                                                <svg class="w-3 h-3 shrink-0 ptah-c-sort_active" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                            @endif
                                        @else
                                            <svg class="w-3 h-3 shrink-0 ptah-c-sort_idle" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M16 15l-4 4-4-4"/></svg>
                                        @endif
                                    </button>
                                @else
                                    <span class="flex-1 inline-flex items-center gap-1">{{ $colLabel }}</span>
                                @endif
                            </div>
                            {{-- Resize handle (wider hitbox, thin visual on hover) --}}
                            <div class="ptah-resize-handle absolute top-0 -right-1 h-full w-3 cursor-col-resize z-10 hover:bg-primary/30 transition-colors"
                                 onclick="event.stopPropagation()"
                                 onmousedown="ptahResizeStart(event, '{{ $colField }}', '{{ $crudTitle }}')">
                            </div>
                        </th>
                    @endif
                @endforeach

                {{-- Colunas action --}}
                @foreach ($visibleCols as $col)
                    @if (($col['colsTipo'] ?? '') === 'action')
                        <th class="px-3 py-3 text-xs font-semibold tracking-wider text-center uppercase whitespace-nowrap ptah-c-th_text" style="width:1%">
                            {{ $col['colsNomeLogico'] ?? __('ptah::ui.col_default_action') }}
                        </th>
                    @endif
                @endforeach

                {{-- Coluna de ações padrão (sticky: stays visible on horizontal scroll) --}}
                @if ($effectivePerms['canUpdate'] || $effectivePerms['canDelete'])
                    {{-- width:1% + nowrap = shrink-to-fit dos ícones, sem sobra --}}
                    <th class="sticky right-0 z-[1] px-3 py-3 text-xs font-semibold tracking-wider text-center uppercase whitespace-nowrap ptah-c-th_text ptah-c-thead ptah-no-print" style="width:1%">{{ __('ptah::ui.col_actions') }}</th>
                @endif
            </tr>
        </thead>

        <tbody class="ptah-c-tbody_div">
            @php
                // ── Group break ("quebra"): headers + per-group subtotals ──
                $breakField  = $crudConfig['groupBreak'] ?? null;
                $breakCol    = $breakField ? collect($crudConfig['cols'] ?? [])->firstWhere('colsNomeFisico', $breakField) : null;
                $breakLabel  = $breakCol['colsNomeLogico'] ?? $breakField;
                $breakTotCols = ($breakField && !empty($totData)) ? array_keys($totData) : [];
                $prevBreak   = '__ptah_init__';
                $breakSums   = [];
                $breakCount  = 0;
            @endphp
            @forelse ($rows as $row)
                @if ($breakField)
                    @php $curBreak = data_get($row, $breakField); @endphp

                    {{-- Subtotal do grupo anterior --}}
                    @if ($prevBreak !== '__ptah_init__' && $curBreak !== $prevBreak)
                        @include('ptah::livewire.base-crud.partials._break-subtotal')
                        @php $breakSums = []; $breakCount = 0; @endphp
                    @endif

                    {{-- Cabeçalho do grupo --}}
                    @if ($curBreak !== $prevBreak)
                        <tr class="ptah-c-break_row" wire:key="break-{{ md5((string) $curBreak) }}-{{ $loop->index }}">
                            <td colspan="99" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide ptah-c-break_td">
                                <span class="opacity-60">{{ $breakLabel }}:</span>
                                {!! $breakCol ? $this->formatCell($breakCol, $row) : e((string) $curBreak) !!}
                            </td>
                        </tr>
                    @endif

                    @php
                        $prevBreak = $curBreak;
                        $breakCount++;
                        foreach ($breakTotCols as $btc) {
                            $breakSums[$btc] = ($breakSums[$btc] ?? 0) + (float) data_get($row, $btc);
                        }
                    @endphp
                @endif
                @php
                    $rowStyle = $this->getRowStyle($row);
                    $rowLink  = null;
                    if (!empty($crudConfig['configLinkLinha'])) {
                        $id = $row->id ?? null;
                        $rowLink = $id ? str_replace('%id%', $id, $crudConfig['configLinkLinha']) : null;
                    }
                @endphp

                <tr style="{{ $rowStyle }}"
                    class="group transition-colors ptah-c-tr ptah-tr
                        @if($viewDensity === 'compact') @elseif($viewDensity === 'spacious') @endif
                        {{ in_array($row->id ?? 0, $selectedRows) ? 'ptah-c-tr_selected' : '' }}
                        {{ $rowLink ? 'cursor-pointer' : '' }}"
                    @if($rowLink)
                        @click="ptahRowNav($event, '{{ $rowLink }}')"
                        @auxclick="ptahRowNav($event, '{{ $rowLink }}')"
                    @endif
                    wire:key="row-{{ $row->id ?? $loop->index }}">

                    {{-- Mestre/detalhe: expandir linha --}}
                    @if (!empty($masterDetails))
                        @php $isExpanded = in_array($row->id ?? 0, $expandedRows, true); @endphp
                        <td class="px-2 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} ptah-no-print" onclick="event.stopPropagation()">
                            <button wire:click="toggleDetail({{ $row->id ?? 0 }})" @click.stop
                                class="p-1.5 -m-1 rounded transition-all text-slate-400 hover:text-primary {{ $isExpanded ? 'rotate-90 text-primary' : '' }}"
                                title="{{ __('ptah::ui.btn_detail_title') }}"
                                aria-label="{{ __('ptah::ui.btn_detail_title') }}"
                                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}">
                                <svg class="w-4 h-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </td>
                    @endif

                    {{-- Checkbox de seleção em bulk --}}
                    @if ($effectivePerms['canDelete'])
                        <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} ptah-no-print" onclick="event.stopPropagation()">
                            <input type="checkbox" wire:model.live="selectedRows" value="{{ $row->id ?? 0 }}"
                                   onclick="event.stopPropagation()"
                                   aria-label="{{ __('ptah::ui.bulk_select_row', ['id' => $row->id ?? 0]) }}"
                                   class="rounded cursor-pointer text-primary focus:ring-primary/30">
                        </td>
                    @endif

                    {{-- Células de dados --}}
                    @foreach ($visibleCols as $col)
                        @if (($col['colsTipo'] ?? '') !== 'action')
                            @php
                                $cellField    = $col['colsNomeFisico'];
                                $cellAlign    = $col['colsAlign'] ?? 'text-start';
                                $reverse      = in_array($col['colsReverse'] ?? false, [true, 'S', 1, '1'], true);
                                $cellSavedW   = $columnWidths[$cellField] ?? null;
                                $cellMinWidth = $cellSavedW
                                    ? "width:{$cellSavedW}px;min-width:60px;"
                                    : (! empty($col['colsMinWidth']) ? 'min-width:' . $col['colsMinWidth'] . ';' : '');
                            @endphp
                            <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} whitespace-nowrap {{ $cellAlign }} {{ $reverse ? 'font-medium' : '' }}"
                                @if($cellMinWidth) style="{{ $cellMinWidth }}" @endif>
                                {!! $this->formatCell($col, $row) !!}
                            </td>
                        @endif
                    @endforeach

                    {{-- Colunas action --}}
                    @foreach ($visibleCols as $col)
                        @if (($col['colsTipo'] ?? '') === 'action')
                            <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} text-center whitespace-nowrap">
                                @php
                                    $actionType  = $col['actionType']  ?? 'javascript';
                                    $actionValue = $col['actionValue'] ?? ($col['actionCall'] ?? '');
                                    $actionIcon  = $col['actionIcon']  ?: ($col['actionIcone'] ?? '');
                                    $actionColor = $col['actionColor'] ?? 'primary';
                                    $rowId       = $row->id ?? 0;
                                    $actionStr   = str_replace(['%id%', '"id%'], [$rowId, $rowId], $actionValue);
                                    // Block dangerous URL schemes on link actions (HTML escaping
                                    // does NOT neutralise javascript:/data:/vbscript: in href).
                                    $isUnsafeHref = ($actionType === 'link')
                                        && preg_match('/^\s*(javascript|data|vbscript):/i', $actionStr);
                                    if ($isUnsafeHref) {
                                        $actionStr = '#';
                                    }
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

                    {{-- Botões de ação padrão (sticky column; larger touch targets via p-2/-m-1) --}}
                    @if ($effectivePerms['canUpdate'] || $effectivePerms['canDelete'])
                        <td class="sticky right-0 z-[1] px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} text-center whitespace-nowrap ptah-c-sticky_cell ptah-no-print" style="width:1%">
                            <div class="ptah-row-btns flex items-center justify-center gap-1">

                                {{-- Editar --}}
                                @if ($effectivePerms['canUpdate'] && !$showTrashed)
                                    <button wire:click="openEdit({{ $row->id ?? 0 }})" wire:loading.attr="disabled"
                                        @click.stop
                                        class="p-2 -m-1 rounded transition-colors text-primary hover:text-primary/80"
                                        title="{{ __('ptah::ui.btn_edit_title') }}"
                                        aria-label="{{ __('ptah::ui.btn_edit_title') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                @endif

                                {{-- Duplicar --}}
                                @if ($effectivePerms['canCreate'] && !$showTrashed)
                                    <button wire:click="duplicateRecord({{ $row->id ?? 0 }})" wire:loading.attr="disabled"
                                        @click.stop
                                        class="p-2 -m-1 rounded transition-colors text-slate-400 hover:text-primary"
                                        title="{{ __('ptah::ui.btn_duplicate_title') }}"
                                        aria-label="{{ __('ptah::ui.btn_duplicate_title') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                @endif

                                {{-- Excluir / Restaurar --}}
                                @if ($showTrashed && method_exists($row, 'trashed') && $row->trashed())
                                    @if ($effectivePerms['canRestore'])
                                        <button wire:click="restoreRecord({{ $row->id ?? 0 }})"
                                            @click.stop
                                            class="p-2 -m-1 rounded transition-colors text-success hover:text-success/80"
                                            title="{{ __('ptah::ui.btn_restore_title') }}"
                                            aria-label="{{ __('ptah::ui.btn_restore_title') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    @endif
                                @elseif ($effectivePerms['canDelete'])
                                    <button wire:click="confirmDelete({{ $row->id ?? 0 }})"
                                        @click.stop
                                        class="p-2 -m-1 rounded transition-colors text-danger hover:text-danger/80"
                                        title="{{ __('ptah::ui.btn_delete_title') }}"
                                        aria-label="{{ __('ptah::ui.btn_delete_title') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                @endif

                            </div>
                        </td>
                    @endif

                </tr>

                {{-- Linha expandida: grids de detalhe (BaseCrud aninhado, lazy) --}}
                @if (!empty($masterDetails) && in_array($row->id ?? 0, $expandedRows, true))
                    <tr wire:key="detail-row-{{ $row->id }}" class="ptah-c-detail_row">
                        <td colspan="99" class="px-6 py-4 ptah-c-detail_td">
                            <div class="space-y-4">
                                @foreach ($masterDetails as $dIdx => $detail)
                                    @if (!empty($detail['model']) && !empty($detail['foreignKey']))
                                        <div>
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                {{ $detail['title'] ?? $detail['model'] }}
                                            </p>
                                            @livewire('ptah-base-crud', [
                                                'model' => $detail['model'],
                                                'lockedFilters' => [$detail['foreignKey'] => $row->id],
                                            ], key('detail-'.$model.'-'.$row->id.'-'.$dIdx))
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="99" class="px-6 py-16 text-center ptah-empty-cell">
                        @php
                            $emptyIsFiltered = $search !== ''
                                || count(array_filter($filters)) > 0
                                || count(array_filter($dateRanges)) > 0
                                || $quickDateFilter !== '';
                        @endphp
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-16 h-16 rounded-full ptah-c-empty_box">
                                @if ($emptyIsFiltered)
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                    </svg>
                                @else
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-semibold ptah-c-empty_ttl">
                                    {{ $emptyIsFiltered ? __('ptah::ui.empty_filtered_title') : __('ptah::ui.empty_title') }}
                                </p>
                                <p class="text-xs mt-0.5 ptah-c-empty_sub">
                                    {{ $emptyIsFiltered ? __('ptah::ui.empty_filtered_subtitle') : __('ptah::ui.empty_subtitle') }}
                                </p>
                            </div>
                            @if ($emptyIsFiltered)
                                <button wire:click="clearFilters"
                                    class="mt-1 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-all ptah-c-btn">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    {{ __('ptah::ui.btn_clear_filters') }}
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse

            {{-- Subtotal do último grupo da página --}}
            @if ($breakField && $prevBreak !== '__ptah_init__')
                @include('ptah::livewire.base-crud.partials._break-subtotal')
            @endif
        </tbody>

        {{-- Totalizadores --}}
        @if (!empty($totData))
            <tfoot class="ptah-c-tfoot">
                <tr>
                    @foreach ($visibleCols as $col)
                        @if (($col['colsTipo'] ?? '') !== 'action')
                            @php $totVal = $totData[$col['colsNomeFisico'] ?? ''] ?? null; @endphp
                                <td class="px-3 py-2.5 whitespace-nowrap ptah-c-tfoot_td {{ $col['colsAlign'] ?? 'text-start' }}">
                                @if ($totVal !== null)
                                    @if (($col['colsHelper'] ?? '') === 'currencyFormat')
                                    {{ __('ptah::ui.currency_prefix') }}{{ number_format((float)$totVal, 2, __('ptah::ui.number_dec_point'), __('ptah::ui.number_thousands')) }}
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


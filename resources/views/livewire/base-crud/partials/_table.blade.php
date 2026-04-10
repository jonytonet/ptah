{{-- ── Tabela ──────────────────────────────────────────────────────── --}}
{{-- Loading indicator (filter/search/sort) --}}
<div wire:loading.delay class="h-0.5 mb-2 overflow-hidden rounded-full bg-primary/10">
    <div class="h-full w-full ptah-loading-bar"></div>
</div>

<div class="overflow-x-auto border rounded-md ptah-c-tbl_wrap transition-opacity duration-300"
     wire:loading.class="opacity-60"
     id="ptah-table-wrap-{{ $crudTitle }}">
    <table class="{{ $crudConfig['tableClass'] ?? 'table' }} ptah-cols-table w-full text-sm
        @if($viewDensity === 'compact') text-xs @elseif($viewDensity === 'spacious') text-base @endif">

        <thead class="{{ $crudConfig['theadClass'] ?? 'ptah-c-thead' }}">
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
                        <th class="relative px-3 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap ptah-c-th_text {{ $colAlign }} ptah-sortable-col"
                            data-column="{{ $colField }}"
                            style="{{ $thStyle }}"
                            draggable="true"
                            ondragstart="ptahColDragStart(event, '{{ $crudTitle }}')"
                            ondragover="ptahColDragOver(event)"
                            ondrop="ptahColDragDrop(event, '{{ $crudTitle }}')"
                            ondragend="ptahColDragEnd(event)">
                            <div class="flex items-center gap-1.5">
                                {{-- Grip (initia o drag) --}}
                                <span class="text-gray-300 transition-colors select-none ptah-drag-grip shrink-0 cursor-grab hover:text-gray-500"
                                      title="{{ __('ptah::ui.col_drag_title') }}">
                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                        <circle cx="7" cy="5"  r="1.5"/><circle cx="13" cy="5"  r="1.5"/>
                                        <circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>
                                        <circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/>
                                    </svg>
                                </span>
                                {{-- Label (sort) --}}
                                <span class="flex-1 inline-flex items-center gap-1 {{ $isSortable ? 'cursor-pointer select-none hover:text-blue-600' : '' }}"
                                      @if($isSortable) wire:click.stop="sortBy('{{ $colSortBy }}')" @endif>
                                    {{ $colLabel }}
                                    @if ($isSortable)
                                        @if ($sort === $colSortBy)
                                            @if ($direction === 'ASC')
                                                <svg class="w-3 h-3 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                            @else
                                                <svg class="w-3 h-3 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                            @endif
                                        @else
                                            <svg class="w-3 h-3 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M16 15l-4 4-4-4"/></svg>
                                        @endif
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
                        <th class="px-3 py-3 text-xs font-semibold tracking-wider text-center uppercase whitespace-nowrap ptah-c-th_text">
                            {{ $col['colsNomeLogico'] ?? __('ptah::ui.col_default_action') }}
                        </th>
                    @endif
                @endforeach

                {{-- Coluna de ações padrão --}}
                @if ($effectivePerms['canUpdate'] || $effectivePerms['canDelete'])
                    <th class="px-3 py-3 text-xs font-semibold tracking-wider text-center uppercase ptah-c-th_text">{{ __('ptah::ui.col_actions') }}</th>
                @endif
            </tr>
        </thead>

        <tbody class="ptah-c-tbody_div">
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
                    class="transition-colors ptah-c-tr
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
                    @if ($effectivePerms['canUpdate'] || $effectivePerms['canDelete'])
                        <td class="px-3 py-{{ $viewDensity === 'compact' ? '1' : '2.5' }} text-center whitespace-nowrap">
                            <div class="ptah-row-btns flex items-center justify-center gap-2">

                                {{-- Editar --}}
                                @if ($effectivePerms['canUpdate'])
                                    <button wire:click="openEdit({{ $row->id ?? 0 }})" wire:loading.attr="disabled"
                                        @click.stop
                                        class="transition-colors text-primary hover:text-primary/80" title="{{ __('ptah::ui.btn_edit_title') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                @endif

                                {{-- Excluir / Restaurar --}}
                                @if ($showTrashed && method_exists($row, 'trashed') && $row->trashed())
                                    @if ($effectivePerms['canRestore'])
                                        <button wire:click="restoreRecord({{ $row->id ?? 0 }})"
                                            @click.stop
                                            class="transition-colors text-success hover:text-success/80" title="{{ __('ptah::ui.btn_restore_title') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    @endif
                                @elseif ($effectivePerms['canDelete'])
                                    <button wire:click="confirmDelete({{ $row->id ?? 0 }})"
                                        @click.stop
                                        class="transition-colors text-danger hover:text-danger/80" title="{{ __('ptah::ui.btn_delete_title') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                @endif

                            </div>
                        </td>
                    @endif

                </tr>
            @empty
                <tr>
                    <td colspan="99" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-16 h-16 rounded-md ptah-c-empty_box">
                                <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold ptah-c-empty_ttl">{{ __('ptah::ui.empty_title') }}</p>
                                <p class="text-xs mt-0.5 ptah-c-empty_sub">{{ __('ptah::ui.empty_subtitle') }}</p>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforelse
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


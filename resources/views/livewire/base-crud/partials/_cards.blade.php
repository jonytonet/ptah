{{-- ── Visão em cards / mosaico (viewMode = 'cards') ───────────────── --}}
@php
    // Title column: first visible non-id, non-action column.
    $cardCols = array_values(array_filter(
        $visibleCols,
        fn ($c) => ($c['colsTipo'] ?? '') !== 'action' && ($c['colsNomeFisico'] ?? '') !== 'id'
    ));
    $titleCol = $cardCols[0] ?? null;
    $bodyCols = array_slice($cardCols, 1);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 transition-opacity duration-300"
     wire:loading.class="opacity-60">

    @forelse ($rows as $row)
        @php
            $rowStyle = $this->getRowStyle($row);
            $rowLink  = null;
            if (!empty($crudConfig['configLinkLinha'])) {
                $id = $row->id ?? null;
                $rowLink = $id ? str_replace('%id%', $id, $crudConfig['configLinkLinha']) : null;
            }
            $isSelected = in_array($row->id ?? 0, $selectedRows);
        @endphp

        <div wire:key="card-{{ $row->id ?? $loop->index }}"
             style="{{ $rowStyle }}"
             @if($rowLink)
                 @click="ptahRowNav($event, '{{ $rowLink }}')"
                 @auxclick="ptahRowNav($event, '{{ $rowLink }}')"
             @endif
             class="flex flex-col border rounded-lg p-4 transition-shadow ptah-c-tbl_wrap bg-white dark:bg-slate-800 hover:shadow-md
                    {{ $rowLink ? 'cursor-pointer' : '' }}
                    {{ $isSelected ? 'ptah-c-tr_selected' : '' }}">

            {{-- Header: checkbox + título --}}
            <div class="flex items-start gap-2.5 mb-3">
                @if ($effectivePerms['canDelete'])
                    <input type="checkbox" wire:model.live="selectedRows" value="{{ $row->id ?? 0 }}"
                           onclick="event.stopPropagation()"
                           aria-label="{{ __('ptah::ui.bulk_select_row', ['id' => $row->id ?? 0]) }}"
                           class="mt-0.5 rounded cursor-pointer text-primary focus:ring-primary/30">
                @endif
                <div class="min-w-0 flex-1">
                    @if ($titleCol)
                        <p class="text-sm font-semibold truncate text-slate-800 dark:text-white">
                            {!! $this->formatCell($titleCol, $row) !!}
                        </p>
                    @endif
                    <p class="text-[11px] text-slate-400">#{{ $row->id ?? '—' }}</p>
                </div>
            </div>

            {{-- Body: label/valor das demais colunas visíveis --}}
            <dl class="flex-1 space-y-1.5">
                @foreach ($bodyCols as $col)
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <dt class="shrink-0 font-medium uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">
                            {{ $col['colsNomeLogico'] ?? $col['colsNomeFisico'] }}
                        </dt>
                        <dd class="truncate text-right text-slate-700 dark:text-slate-200">
                            {!! $this->formatCell($col, $row) !!}
                        </dd>
                    </div>
                @endforeach
            </dl>

            {{-- Footer: ações padrão --}}
            @if ($effectivePerms['canUpdate'] || $effectivePerms['canDelete'])
                <div class="flex items-center justify-end gap-1 pt-3 mt-3 border-t border-slate-100 dark:border-slate-700">
                    @if ($effectivePerms['canUpdate'] && !$showTrashed)
                        <button wire:click="openEdit({{ $row->id ?? 0 }})" wire:loading.attr="disabled" @click.stop
                            class="p-2 -m-1 rounded transition-colors text-primary hover:text-primary/80"
                            title="{{ __('ptah::ui.btn_edit_title') }}" aria-label="{{ __('ptah::ui.btn_edit_title') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                    @endif
                    @if ($effectivePerms['canCreate'] && !$showTrashed)
                        <button wire:click="duplicateRecord({{ $row->id ?? 0 }})" wire:loading.attr="disabled" @click.stop
                            class="p-2 -m-1 rounded transition-colors text-slate-400 hover:text-primary"
                            title="{{ __('ptah::ui.btn_duplicate_title') }}" aria-label="{{ __('ptah::ui.btn_duplicate_title') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    @endif
                    @if ($showTrashed && method_exists($row, 'trashed') && $row->trashed())
                        @if ($effectivePerms['canRestore'])
                            <button wire:click="restoreRecord({{ $row->id ?? 0 }})" @click.stop
                                class="p-2 -m-1 rounded transition-colors text-success hover:text-success/80"
                                title="{{ __('ptah::ui.btn_restore_title') }}" aria-label="{{ __('ptah::ui.btn_restore_title') }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                        @endif
                    @elseif ($effectivePerms['canDelete'])
                        <button wire:click="confirmDelete({{ $row->id ?? 0 }})" @click.stop
                            class="p-2 -m-1 rounded transition-colors text-danger hover:text-danger/80"
                            title="{{ __('ptah::ui.btn_delete_title') }}" aria-label="{{ __('ptah::ui.btn_delete_title') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <div class="col-span-full px-6 py-16 text-center border rounded-lg ptah-c-tbl_wrap">
            <div class="flex flex-col items-center gap-3">
                <div class="flex items-center justify-center w-16 h-16 rounded-full ptah-c-empty_box">
                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold ptah-c-empty_ttl">{{ __('ptah::ui.empty_title') }}</p>
                <p class="text-xs ptah-c-empty_sub">{{ __('ptah::ui.empty_subtitle') }}</p>
            </div>
        </div>
    @endforelse
</div>

{{-- ptah::livewire.exports.exports-panel --}}
<div @if ($hasPending) wire:poll.5s @endif>
    <div class="mb-4">
        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ __('ptah::ui.exports_panel_title') }}</h2>
    </div>

    <div class="overflow-x-auto border rounded-md border-slate-200 dark:border-slate-700">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800 border-b-2 border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('ptah::ui.exports_panel_col_model') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('ptah::ui.exports_panel_col_format') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('ptah::ui.exports_panel_col_status') }}</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('ptah::ui.exports_panel_col_rows') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('ptah::ui.exports_panel_col_created') }}</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('ptah::ui.exports_panel_col_actions') }}</th>
                </tr>
            </thead>
            {{-- aria-live: announces status changes (queued → processing → done/
                 failed) to assistive tech as wire:poll refreshes the table. --}}
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700" aria-live="polite">
                @forelse ($exports as $row)
                    @php
                        $expired = $row->expires_at && $row->expires_at->isPast();
                        $canDownload = $row->status === 'done' && ! $expired;
                        $statusMap = [
                            'queued' => ['label' => __('ptah::ui.export_status_queued'), 'class' => 'bg-warn text-dark'],
                            'processing' => ['label' => __('ptah::ui.export_status_processing'), 'class' => 'bg-primary text-white'],
                            'done' => ['label' => __('ptah::ui.export_status_done'), 'class' => 'bg-success text-white'],
                            'failed' => ['label' => __('ptah::ui.export_status_failed'), 'class' => 'bg-danger text-white'],
                        ];
                        // Unknown/future status → neutral design-token fallback
                        // (bg-dark, one of the five theme colors), never an
                        // ad-hoc hardcoded gray.
                        $status = $statusMap[$row->status] ?? ['label' => $row->status, 'class' => 'bg-dark text-white'];
                    @endphp
                    <tr class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/60">
                        <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ class_basename(str_replace('/', '\\', $row->model)) }}</td>
                        <td class="px-3 py-2.5 text-xs uppercase text-slate-500 dark:text-slate-400">{{ $row->format }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $status['class'] }}"
                                @if ($row->status === 'failed' && $row->error) title="{{ $row->error }}" @endif>
                                {{ $status['label'] }}
                            </span>
                            @if ($row->status === 'failed' && $row->error)
                                <div class="mt-1 text-[10px] text-danger max-w-45 truncate mx-auto" title="{{ $row->error }}">
                                    {{ $row->error }}
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right text-slate-500 dark:text-slate-400">{{ $row->rows ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $row->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap"
                            wire:loading.class="opacity-50 pointer-events-none" wire:target="remove({{ $row->id }})">
                            @if ($canDownload)
                                <a href="{{ route('ptah.export.file', ['export' => $row->id]) }}"
                                   class="inline-flex items-center px-2 py-1 mr-1.5 text-xs font-semibold rounded-md ptah-c-btn">
                                    {{ __('ptah::ui.btn_download') }}
                                </a>
                            @endif
                            <button type="button" wire:click="remove({{ $row->id }})"
                                wire:loading.attr="disabled" wire:target="remove({{ $row->id }})"
                                wire:confirm="{{ __('ptah::ui.exports_panel_remove_confirm') }}"
                                class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-semibold rounded-md ptah-c-btn">
                                <svg wire:loading wire:target="remove({{ $row->id }})" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                {{ __('ptah::ui.btn_delete') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-sm text-center text-slate-400 dark:text-slate-500">
                            {{ __('ptah::ui.exports_panel_empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($exports->hasPages())
        <div class="mt-4">{{ $exports->links('ptah::components.forge-pagination') }}</div>
    @endif
</div>

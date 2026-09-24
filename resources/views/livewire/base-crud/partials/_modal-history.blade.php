{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Histórico do registro (HasCrudHistory) ───────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@if ($showHistoryModal)
    <x-forge-modal wire:model="showHistoryModal" :title="__('ptah::ui.history_title')" size="lg">
        @if ($historyItems === [])
            <x-forge-empty :title="__('ptah::ui.history_empty')" />
        @else
            <ol class="space-y-4">
                @foreach ($historyItems as $i => $item)
                    <li wire:key="history-{{ $i }}" class="rounded-md border p-3" style="border-color: var(--ptah-line); background: var(--ptah-surface)">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold" style="color: var(--ptah-text-strong)">
                                {{ __('ptah::ui.history_event_'.$item['event']) }}
                                <span class="font-normal ptah-c-muted">· {{ $item['who'] }}</span>
                            </p>
                            <time class="text-xs tabular-nums ptah-c-muted">{{ $item['when'] }}</time>
                        </div>

                        @if ($item['changes'] !== [])
                            <dl class="mt-2 grid gap-x-3 gap-y-1 text-sm" style="grid-template-columns: minmax(8rem, auto) 1fr">
                                @foreach ($item['changes'] as $change)
                                    <dt class="ptah-c-form_lbl">{{ $change['label'] }}</dt>
                                    <dd style="color: var(--ptah-text)" class="break-words">
                                        @if ($item['event'] === 'updated')
                                            <span class="line-through ptah-c-muted">{{ $change['old'] }}</span>
                                            <span aria-hidden="true" class="ptah-c-muted">→</span>
                                            <span class="sr-only">{{ __('ptah::ui.history_became') }}</span>
                                        @endif
                                        <span>{{ $change['new'] }}</span>
                                    </dd>
                                @endforeach
                            </dl>
                        @endif

                        @if ($item['hidden'] > 0)
                            <p class="mt-1 text-xs ptah-c-muted">{{ trans_choice('ptah::ui.history_hidden_fields', $item['hidden'], ['count' => $item['hidden']]) }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        <x-slot name="footer">
            <x-forge-button wire:click="closeHistory" color="dark" flat>{{ __('ptah::ui.modal_close') }}</x-forge-button>
        </x-slot>
    </x-forge-modal>
@endif

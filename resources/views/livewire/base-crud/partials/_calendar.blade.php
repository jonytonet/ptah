{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Calendário (HasCrudBoards) — a mesma listagem, por data ───────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@php
    $cal = $this->calendarGrid();
    $calMonth = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $cal['month'])->locale(app()->getLocale());
    $weekdays = explode(',', __('ptah::ui.calendar_weekdays'));
@endphp
<div class="rounded-md border" style="border-color: var(--ptah-line-strong); background: var(--ptah-surface)">
    <header class="flex items-center justify-between gap-2 px-3 py-2 border-b" style="border-color: var(--ptah-line); background: var(--ptah-panel)">
        <x-forge-button wire:click="calendarShift(-1)" color="dark" flat size="sm" :aria-label="__('ptah::ui.calendar_prev')">‹</x-forge-button>
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold capitalize" style="color: var(--ptah-text-strong)">{{ $calMonth->translatedFormat('F Y') }}</h3>
            <x-forge-button wire:click="$set('calendarMonth', '')" color="dark" flat size="sm">{{ __('ptah::ui.calendar_today') }}</x-forge-button>
        </div>
        <x-forge-button wire:click="calendarShift(1)" color="dark" flat size="sm" :aria-label="__('ptah::ui.calendar_next')">›</x-forge-button>
    </header>

    @if ($cal['overflow'])
        <div class="px-3 pt-2"><x-forge-alert color="warn">{{ __('ptah::ui.calendar_overflow') }}</x-forge-alert></div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full table-fixed text-sm min-w-[640px]">
            <thead>
                <tr>
                    @foreach ($weekdays as $wd)
                        <th scope="col" class="px-2 py-1.5 text-xs font-semibold text-left ptah-c-muted">{{ $wd }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($cal['weeks'] as $week)
                    <tr>
                        @foreach ($week as $day)
                            <td wire:key="cal-{{ $day['date'] }}" class="align-top h-24 p-1 border-t"
                                style="border-color: var(--ptah-line); {{ $day['in_month'] ? '' : 'background: var(--ptah-surface-sunken)' }}">
                                <div class="text-xs tabular-nums mb-1 {{ $day['in_month'] ? '' : 'ptah-c-muted' }}"
                                    @if ($day['today']) style="color: var(--ptah-primary); font-weight: 700" aria-current="date" @endif>
                                    {{ $day['day'] }}
                                </div>
                                <ul class="space-y-0.5">
                                    @foreach (array_slice($day['items'], 0, 4) as $item)
                                        <li>
                                            <button type="button" wire:click="openEdit({{ json_encode($item['id']) }})"
                                                class="block w-full truncate rounded px-1 text-left text-xs hover:underline focus:outline-none focus-visible:underline"
                                                style="background: var(--ptah-primary-soft); color: var(--ptah-primary-strong)"
                                                title="{{ $item['title'] }}{{ $item['subtitle'] !== '' ? ' — '.$item['subtitle'] : '' }}">
                                                {{ $item['title'] }}
                                            </button>
                                        </li>
                                    @endforeach
                                    @if (count($day['items']) > 4)
                                        <li class="text-xs ptah-c-muted">+{{ count($day['items']) - 4 }}</li>
                                    @endif
                                </ul>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

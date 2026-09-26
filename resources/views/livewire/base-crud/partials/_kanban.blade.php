{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Kanban (HasCrudBoards) — a mesma listagem, em colunas de status ── --}}
{{-- Arrastar grava; o "Mover para" faz o mesmo pelo teclado.            --}}
{{-- Só os destinos de kanbanTargets() (locked/transitions) aceitam o    --}}
{{-- card; o moveCard() confere de novo no servidor.                     --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@php
    $kanbanColumns = $this->kanbanColumns();
    $kanbanOptions = $this->kanbanOptions();
    $kanbanCanMove = $this->authorizeCrudAction('update') && $this->crudConfigAllows('update');
    $kanbanTargets = $kanbanCanMove ? $this->kanbanTargets() : [];
    $kanbanLocked = array_map('strval', (array) ($this->crudConfig['kanbanConfig']['locked'] ?? []));
    $kanbanLockedMsg = (string) ($this->crudConfig['kanbanConfig']['lockedMessage'] ?? '');
@endphp
<div class="flex gap-3 overflow-x-auto pb-2"
    x-data="{ over: null, from: null, targets: @js((object) $kanbanTargets), accepts(to) { return this.from !== null && (this.targets[this.from] ?? []).includes(to) } }">
    @foreach ($kanbanColumns as $column)
        <section wire:key="kanban-col-{{ $column['value'] }}"
            class="flex flex-col shrink-0 w-72 rounded-md border"
            style="border-color: var(--ptah-line-strong); background: var(--ptah-surface-sunken)"
            :style="over === @js($column['value']) ? 'outline: 2px solid var(--ptah-primary); outline-offset: -2px' : ''"
            @php
                $isOther = $column['value'] === \Ptah\Livewire\BaseCrud\BaseCrud::KANBAN_OTHER;
                $isLocked = in_array($column['value'], $kanbanLocked, true);
                $cardTargets = $kanbanTargets[$column['value']] ?? [];
            @endphp
            @if ($isLocked) data-ptah-kanban-locked @endif
            @if ($kanbanCanMove && ! $isOther && ! $isLocked)
                @dragover="if (accepts(@js($column['value']))) { $event.preventDefault(); over = @js($column['value']) }"
                @dragleave="over = null"
                @drop.prevent="over = null; accepts(@js($column['value'])) && $wire.moveCard($event.dataTransfer.getData('text/plain'), @js($column['value']))"
            @endif
            aria-label="{{ $column['label'] }}">
            <header class="flex items-center justify-between px-3 py-2 border-b" style="border-color: var(--ptah-line); background: var(--ptah-panel)">
                <h3 class="flex items-center gap-1 text-sm font-semibold" style="color: var(--ptah-text-strong)">
                    @if ($isLocked)<i class="bx bx-lock-alt ptah-c-muted" aria-hidden="true"></i>@endif
                    {{ $column['label'] }}
                </h3>
                <span class="text-xs tabular-nums ptah-c-muted">{{ $column['total'] }}</span>
            </header>

            <ul class="flex flex-col gap-2 p-2 min-h-16">
                @forelse ($column['cards'] as $card)
                    <li wire:key="kanban-card-{{ $card['id'] }}"
                        class="rounded-md border p-2 text-sm"
                        style="border-color: var(--ptah-line); background: var(--ptah-surface){{ $isLocked ? '; opacity: .75' : '' }}"
                        @if ($kanbanCanMove && $cardTargets !== []) draggable="true" @dragstart="from = @js($column['value']); $event.dataTransfer.setData('text/plain', @js((string) $card['id']))" @dragend="from = null; over = null" @endif>
                        <button type="button" wire:click="openEdit({{ json_encode($card['id']) }})"
                            class="block w-full text-left font-medium hover:underline focus:outline-none focus-visible:underline"
                            style="color: var(--ptah-text)">
                            {{ $card['title'] }}
                        </button>
                        @if ($card['subtitle'] !== '')
                            <p class="mt-0.5 text-xs ptah-c-muted truncate">{{ $card['subtitle'] }}</p>
                        @endif
                        @if ($kanbanCanMove && $cardTargets !== [])
                            <label class="sr-only" for="kanban-move-{{ $card['id'] }}">{{ __('ptah::ui.kanban_move_to') }}</label>
                            <select id="kanban-move-{{ $card['id'] }}"
                                class="mt-2 w-full text-xs rounded-md px-1.5 py-1 ptah-c-fp_input ptah-c-control"
                                x-on:change="$wire.moveCard(@js((string) $card['id']), $event.target.value)">
                                @if ($isOther)
                                    <option value="" selected disabled>{{ __('ptah::ui.kanban_move_to') }}…</option>
                                @endif
                                @foreach ($kanbanOptions as $optLabel => $optValue)
                                    @continue($optValue !== $column['value'] && ! in_array($optValue, $cardTargets, true))
                                    <option value="{{ $optValue }}" @selected($optValue === $column['value'])>{{ __('ptah::ui.kanban_move_to') }}: {{ $optLabel }}</option>
                                @endforeach
                            </select>
                        @endif
                    </li>
                @empty
                    <li class="px-1 py-3 text-xs text-center ptah-c-muted">{{ __('ptah::ui.kanban_empty_column') }}</li>
                @endforelse
            </ul>

            @if ($isLocked && $kanbanLockedMsg !== '')
                <p class="px-3 pb-2 text-xs ptah-c-muted">{{ $kanbanLockedMsg }}</p>
            @endif
            @if ($isOther)
                <p class="px-3 pb-2 text-xs ptah-c-muted">{{ __('ptah::ui.kanban_other_hint') }}</p>
            @endif
            @if ($column['total'] > count($column['cards']))
                <p class="px-3 pb-2 text-xs ptah-c-muted">{{ __('ptah::ui.kanban_more', ['count' => $column['total'] - count($column['cards'])]) }}</p>
            @endif
        </section>
    @endforeach
</div>

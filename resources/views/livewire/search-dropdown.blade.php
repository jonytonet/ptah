<div wire:ignore.self>

    <div class="row" wire:key="sd-{{ $key }}">
        <div class="my-2 form-inline my-lg-0">

            <div class="input-group input-group-sm">
                <input
                    wire:model.live.debounce.500ms="searchTerm"
                    wire:focus="$set('show', true)"
                    wire:keydown.escape="$set('show', false)"
                    wire:key="sd-input-{{ $key }}"
                    class="form-control form-control-sm"
                    placeholder="{{ $placeholder }}"
                    autocomplete="off"
                />
                <button
                    type="button"
                    class="input-group-text fw-bold"
                    style="cursor: pointer;"
                    wire:click="clearData"
                    title="Limpar"
                >
                    <em class="bx bx-x fs-5"></em>
                </button>
            </div>

            @if ($show && count($data))
                <div
                    id="sd-result-{{ $key }}"
                    class="rounded position-absolute form-inline bg-secondary"
                    wire:key="sd-result-{{ $key }}"
                    style="z-index: 999; max-height: 400px; max-width: 600px; overflow-y: auto; {{ $startList }}: 100%;"
                >
                    <div class="list-group">
                        @foreach ($data as $i => $item)
                            <span
                                wire:mousedown="selectedItem({{ json_encode($item) }})"
                                class="p-2 list-group-item list-group-item-action"
                                role="button"
                                style="font-size: 11px !important; font-weight: normal;"
                                wire:key="sd-item-{{ $item[$value] }}-{{ $i }}"
                            >
                                @if ($labelLast)
                                    <strong>{{ $item[$value] }}</strong> -
                                    {{ $this->formatValue($item[$label], $maskLabel) }} -
                                    {{ $this->formatValue($item[$labelSecondary], $maskSecondary) }} -
                                    {{ $this->formatValue($item[$labelLast], $maskLast) }}
                                @elseif ($labelSecondary)
                                    <strong>{{ $item[$value] }}</strong> -
                                    {{ $this->formatValue($item[$label], $maskLabel) }} -
                                    {{ $this->formatValue($item[$labelSecondary], $maskSecondary) }}
                                @else
                                    <strong>{{ $item[$value] }}</strong> -
                                    {{ $this->formatValue($item[$label], $maskLabel) }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>

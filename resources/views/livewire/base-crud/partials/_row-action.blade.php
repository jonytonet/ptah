{{--
    One configured `action` column, for one row.

    Extracted from _table.blade.php so the card view can render the same thing.
    It could not before: _cards only ever emitted edit / duplicate / restore /
    delete, and ignored `colsTipo === 'action'` entirely. That was a gap while
    cards were an opt-in alternative; it became a real hole in 1.25.0, when cards
    became the DEFAULT view on a phone — every custom action an integrator had
    configured was simply unreachable on mobile, with nothing on screen to say so.

    Shared rather than copied, deliberately. The two views drifting is exactly how
    this happened, and the copy would have to carry the `javascript:`/`data:` href
    guard below too — a security check is the last thing that should exist twice.

    Expects:
      $col   the action column's config
      $row   the current model
--}}
@php
    $actionType = $col['actionType'] ?? 'javascript';
    $actionValue = $col['actionValue'] ?? ($col['actionCall'] ?? '');
    // `?? '' ?:` and not the original `?:` alone. The inherited line assumed
    // the key was always present and only guarded against it being empty, so a
    // configured action WITHOUT an icon raised "Undefined array key" — which
    // Laravel promotes to an ErrorException, i.e. a 500 on the whole listing.
    // An icon-less action is a legitimate config: the branches below already
    // fall back to printing the label.
    $actionIcon = ($col['actionIcon'] ?? '') ?: ($col['actionIcone'] ?? '');
    $actionColor = $col['actionColor'] ?? 'primary';
    $rowId = $row->id ?? 0;
    $actionStr = str_replace(['%id%', '"id%'], [$rowId, $rowId], $actionValue);

    // Blocks dangerous URL schemes on link actions. HTML escaping does NOT
    // neutralise javascript:/data:/vbscript: inside an href, and the value comes
    // from crud_configs — editable through the visual modal.
    $isUnsafeHref = ($actionType === 'link')
        && preg_match('/^\s*(javascript|data|vbscript):/i', $actionStr);

    if ($isUnsafeHref) {
        $actionStr = '#';
    }

    $actionTitle = $col['colsNomeLogico'] ?? '';
@endphp

@if ($actionStr)
    @if ($actionType === 'link')
        <a href="{{ $actionStr }}"
            @click.stop
            class="transition-colors text-{{ $actionColor }} hover:opacity-75"
            title="{{ $actionTitle }}">
            @if ($actionIcon)
                <i class="{{ $actionIcon }} text-base"></i>
            @else
                {{ $actionTitle ?: '→' }}
            @endif
        </a>
    @elseif ($actionType === 'livewire')
        <button wire:click="{{ $actionStr }}"
            @click.stop
            class="transition-colors text-{{ $actionColor }} hover:opacity-75"
            title="{{ $actionTitle }}">
            @if ($actionIcon)
                <i class="{{ $actionIcon }} text-base"></i>
            @else
                {{ $actionTitle ?: '▶' }}
            @endif
        </button>
    @else
        {{-- javascript (default) --}}
        <button onclick="{{ $actionStr }}"
            @click.stop
            class="transition-colors text-{{ $actionColor }} hover:opacity-75"
            title="{{ $actionTitle }}">
            @if ($actionIcon)
                <i class="{{ $actionIcon }} text-base"></i>
            @else
                {{ $actionTitle ?: '▶' }}
            @endif
        </button>
    @endif
@endif

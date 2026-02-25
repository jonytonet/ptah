{{--
    forge-badge — Ptah Forge
    Props:
      - color   : primary | success | danger | warn | dark | light  (padrão: danger)
      - position: top-right | top-left | bottom-right | bottom-left
      - value   : string | number
      - dot     : boolean - apenas um ponto sem valor
--}}
@props([
    'color'    => 'danger',
    'position' => 'top-right',
    'value'    => '',
    'dot'      => false,
])

@php
    $colorMap = [
        'primary' => 'bg-primary text-white',
        'success' => 'bg-success text-white',
        'danger'  => 'bg-danger text-white',
        'warn'    => 'bg-warn text-white',
        'dark'    => 'bg-dark text-white',
        'light'   => 'bg-gray-100 text-gray-800',
    ];
    $colorClass = $colorMap[$color] ?? $colorMap['danger'];

    $posMap = [
        'top-right'    => '-top-1.5 -right-1.5',
        'top-left'     => '-top-1.5 -left-1.5',
        'bottom-right' => '-bottom-1.5 -right-1.5',
        'bottom-left'  => '-bottom-1.5 -left-1.5',
    ];
    $posClass  = $posMap[$position] ?? $posMap['top-right'];
    $badgeSize = $dot ? 'h-3 w-3' : 'min-w-[1.25rem] h-5 px-1 text-[10px] font-bold';
@endphp

<span class="relative inline-flex {{ $attributes->get('class', '') }}">
    {{ $slot }}
    <span class="absolute {{ $posClass }} inline-flex items-center justify-center rounded-full {{ $colorClass }} {{ $badgeSize }}">
        @if ($dot)
            <span class="absolute inline-flex h-full w-full rounded-full {{ $colorMap[$color] ?? $colorMap['danger'] }} opacity-75 animate-ping"></span>
        @else
            {{ $value }}
        @endif
    </span>
</span>

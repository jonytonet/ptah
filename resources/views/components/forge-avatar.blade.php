{{--
    forge-avatar — Ptah Forge
    Props:
      - src          : URL da imagem
      - alt          : string
      - size         : xs | sm | md | lg | xl  (padrão: md)
      - color        : primary | success | danger | warn | dark | light
      - text         : iniciais quando não há imagem
      - badgeColor   : online | offline | busy | primary | success | danger | warn
      - badgePosition: top-right | bottom-right  (padrão: bottom-right)
--}}
@props([
    'src'           => null,
    'alt'           => '',
    'size'          => 'md',
    'color'         => 'primary',
    'text'          => '',
    'badgeColor'    => null,
    'badgePosition' => 'bottom-right',
])

@php
    $sizeMap = [
        'xs' => ['container' => 'h-7 w-7 text-xs',   'badge' => 'h-2 w-2'],
        'sm' => ['container' => 'h-9 w-9 text-sm',   'badge' => 'h-2.5 w-2.5'],
        'md' => ['container' => 'h-11 w-11 text-base','badge' => 'h-3 w-3'],
        'lg' => ['container' => 'h-14 w-14 text-lg', 'badge' => 'h-3.5 w-3.5'],
        'xl' => ['container' => 'h-18 w-18 text-xl', 'badge' => 'h-4 w-4'],
    ];
    $s = $sizeMap[$size] ?? $sizeMap['md'];

    $colorMap = [
        'primary' => 'bg-primary text-white',
        'success' => 'bg-success text-white',
        'danger'  => 'bg-danger text-white',
        'warn'    => 'bg-warn text-white',
        'dark'    => 'bg-dark text-white',
        'light'   => 'bg-gray-100 text-gray-800',
    ];
    $colorClass = $colorMap[$color] ?? $colorMap['primary'];

    $badgePosMap = [
        'top-right'    => '-top-0.5 -right-0.5',
        'bottom-right' => '-bottom-0.5 -right-0.5',
    ];
    $badgePosClass = $badgePosMap[$badgePosition] ?? $badgePosMap['bottom-right'];

    $badgeColorMap = [
        'primary' => 'bg-primary', 'success' => 'bg-success',
        'danger'  => 'bg-danger',  'warn'    => 'bg-warn',
        'dark'    => 'bg-dark',    'light'   => 'bg-gray-200',
        'online'  => 'bg-success', 'offline' => 'bg-gray-400', 'busy' => 'bg-danger',
    ];
    $badgeColorClass = isset($badgeColor) ? ($badgeColorMap[$badgeColor] ?? 'bg-gray-400') : null;
    $initials        = $text ? mb_strtoupper(mb_substr($text, 0, 2)) : '';
@endphp

<span class="relative inline-flex shrink-0 {{ $attributes->get('class', '') }}">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" class="rounded-full object-cover {{ $s['container'] }} ring-2 ring-white" />
    @else
        <span class="inline-flex items-center justify-center rounded-full font-semibold {{ $s['container'] }} {{ $colorClass }} ring-2 ring-white">
            {{ $initials ?: '?' }}
        </span>
    @endif

    @if ($badgeColorClass)
        <span class="absolute {{ $badgePosClass }} inline-flex rounded-full {{ $s['badge'] }} {{ $badgeColorClass }} ring-2 ring-white"></span>
    @endif
</span>

{{--
    forge-progress — Ptah Forge
    Props:
      - value   : 0–100  (padrão: 0)
      - color   : primary | success | danger | warn | dark  (padrão: primary)
      - size    : sm | md | lg | xl  (padrão: md)
      - label   : boolean - exibe percentual
      - animated: boolean - pulso de animação
--}}
@props([
    'value'    => 0,
    'color'    => 'primary',
    'size'     => 'md',
    'label'    => false,
    'animated' => false,
])

@php
    $value = min(100, max(0, (int) $value));

    $colors = [
        'primary' => 'bg-primary', 'success' => 'bg-success',
        'danger'  => 'bg-danger',  'warn'    => 'bg-warn',
        'dark'    => 'bg-dark',
    ];
    $sizes = [
        'sm' => 'h-1.5', 'md' => 'h-2.5', 'lg' => 'h-4', 'xl' => 'h-6',
    ];

    $colorClass    = $colors[$color] ?? $colors['primary'];
    $sizeClass     = $sizes[$size]   ?? $sizes['md'];
    $animatedClass = $animated ? 'animate-pulse' : '';
@endphp

<div {{ $attributes }}>
    @if($label)
        <div class="flex items-center justify-between mb-1">
            @isset($labelText)
                <span class="text-xs font-medium text-gray-700">{{ $labelText }}</span>
            @endisset
            <span class="text-xs font-medium text-gray-500 ml-auto">{{ $value }}%</span>
        </div>
    @endif

    <div class="w-full bg-gray-200 rounded-full {{ $sizeClass }} overflow-hidden">
        <div
            class="{{ $colorClass }} {{ $sizeClass }} rounded-full transition-all duration-500 {{ $animatedClass }}"
            style="width: {{ $value }}%"
        ></div>
    </div>
</div>

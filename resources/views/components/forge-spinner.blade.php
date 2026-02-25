{{--
    forge-spinner — Ptah Forge
    Props:
      - color: primary | success | danger | warn | dark | light | white  (padrão: primary)
      - size : sm | md | lg  (padrão: md)
      - type : circle | dots | wave  (padrão: circle)
--}}
@props([
    'color' => 'primary',
    'size'  => 'md',
    'type'  => 'circle',
])

@php
    $colorMap = [
        'primary' => 'text-primary', 'success' => 'text-success',
        'danger'  => 'text-danger',  'warn'    => 'text-warn',
        'dark'    => 'text-dark',    'light'   => 'text-gray-400',
        'white'   => 'text-white',
    ];
    $colorClass = $colorMap[$color] ?? $colorMap['primary'];

    $sizeMap = [
        'sm' => ['svg' => 'h-4 w-4',   'dot' => 'h-1.5 w-1.5', 'bar' => 'h-4 w-1'],
        'md' => ['svg' => 'h-7 w-7',   'dot' => 'h-2 w-2',     'bar' => 'h-6 w-1.5'],
        'lg' => ['svg' => 'h-10 w-10', 'dot' => 'h-3 w-3',     'bar' => 'h-8 w-2'],
    ];
    $s = $sizeMap[$size] ?? $sizeMap['md'];
@endphp

@if ($type === 'circle')
    <span {{ $attributes->merge(['class' => "inline-flex {$colorClass}", 'role' => 'status', 'aria-label' => 'Carregando']) }}>
        <svg class="animate-spin {{ $s['svg'] }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
    </span>

@elseif ($type === 'dots')
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 {$colorClass}", 'role' => 'status', 'aria-label' => 'Carregando']) }}>
        <span class="rounded-full bg-current {{ $s['dot'] }} animate-bounce [animation-delay:-0.3s]"></span>
        <span class="rounded-full bg-current {{ $s['dot'] }} animate-bounce [animation-delay:-0.15s]"></span>
        <span class="rounded-full bg-current {{ $s['dot'] }} animate-bounce"></span>
    </span>

@elseif ($type === 'wave')
    <span {{ $attributes->merge(['class' => "inline-flex items-end gap-0.5 {$colorClass}", 'role' => 'status', 'aria-label' => 'Carregando']) }}>
        <span class="rounded-sm bg-current {{ $s['bar'] }} animate-wave [animation-delay:0s]"></span>
        <span class="rounded-sm bg-current {{ $s['bar'] }} animate-wave [animation-delay:0.1s]"></span>
        <span class="rounded-sm bg-current {{ $s['bar'] }} animate-wave [animation-delay:0.2s]"></span>
        <span class="rounded-sm bg-current {{ $s['bar'] }} animate-wave [animation-delay:0.3s]"></span>
        <span class="rounded-sm bg-current {{ $s['bar'] }} animate-wave [animation-delay:0.4s]"></span>
    </span>
@endif

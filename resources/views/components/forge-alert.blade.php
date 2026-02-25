{{--
    forge-alert — Ptah Forge
    Props:
      - color   : primary | success | danger | warn | dark  (padrão: primary)
      - closable: boolean - exibe botão de fechar
      - title   : string - título opcional
    Requer Alpine.js
--}}
@props([
    'color'    => 'primary',
    'closable' => false,
    'title'    => '',
])

@php
    $colorMap = [
        'primary' => ['bg' => 'bg-primary-light', 'border' => 'border-l-4 border-primary', 'title' => 'text-primary-dark', 'text' => 'text-primary', 'icon' => 'text-primary'],
        'success' => ['bg' => 'bg-success-light', 'border' => 'border-l-4 border-success', 'title' => 'text-success-dark', 'text' => 'text-success', 'icon' => 'text-success'],
        'danger'  => ['bg' => 'bg-danger-light',  'border' => 'border-l-4 border-danger',  'title' => 'text-danger-dark',  'text' => 'text-danger',  'icon' => 'text-danger'],
        'warn'    => ['bg' => 'bg-warn-light',     'border' => 'border-l-4 border-warn',    'title' => 'text-warn-dark',    'text' => 'text-warn',    'icon' => 'text-warn'],
        'dark'    => ['bg' => 'bg-dark',           'border' => 'border-l-4 border-dark-dark','title' => 'text-white',        'text' => 'text-dark-light','icon' => 'text-dark-light'],
    ];
    $c = $colorMap[$color] ?? $colorMap['primary'];

    $icons = [
        'primary' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>',
        'success' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'danger'  => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'warn'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        'dark'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>',
    ];
    $icon = $icons[$color] ?? $icons['primary'];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    {{ $attributes->merge(['class' => "flex items-start gap-3 p-4 rounded-xl {$c['bg']} {$c['border']}"]) }}
>
    <span class="shrink-0 mt-0.5 {{ $c['icon'] }}">{!! $icon !!}</span>

    <div class="flex-1">
        @if ($title)
            <p class="font-semibold text-sm mb-0.5 {{ $c['title'] }}">{{ $title }}</p>
        @endif
        <div class="text-sm {{ $c['text'] }}">{{ $slot }}</div>
    </div>

    @if ($closable)
        <button
            type="button"
            @click="show = false"
            class="shrink-0 ml-auto {{ $c['icon'] }} hover:opacity-70 transition-opacity duration-150 focus:outline-none"
            aria-label="Fechar alerta"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    @endif
</div>

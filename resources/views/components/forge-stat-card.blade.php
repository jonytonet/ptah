{{--
    forge-stat-card — Ptah Forge
    Props:
      - title     : string
      - value     : string
      - icon      : HTML raw string
      - color     : primary | success | danger | warn | dark  (padrão: primary)
      - trend     : string (+5% / -2%)
      - trendLabel: string
--}}
@props([
    'title'      => 'Métrica',
    'value'      => '0',
    'icon'       => null,
    'color'      => 'primary',
    'trend'      => null,
    'trendLabel' => null,
])

@php
    $colorClasses = [
        'primary' => ['bg' => 'bg-primary/10', 'text' => 'text-primary'],
        'success' => ['bg' => 'bg-success/10', 'text' => 'text-success'],
        'danger'  => ['bg' => 'bg-danger/10',  'text' => 'text-danger'],
        'warn'    => ['bg' => 'bg-warn/10',     'text' => 'text-warn'],
        'dark'    => ['bg' => 'bg-dark/10',     'text' => 'text-dark'],
    ];
    $cc = $colorClasses[$color] ?? $colorClasses['primary'];

    $trendPositive = $trend && str_starts_with(ltrim($trend), '+');
    $trendNegative = $trend && str_starts_with(ltrim($trend), '-');
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200']) }}>
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-500 font-medium">{{ $title }}</p>
            <p class="text-2xl md:text-3xl font-bold text-dark mt-1">{{ $value }}</p>

            @if($trend)
                <div class="flex items-center gap-1 mt-2">
                    @if($trendPositive)
                        <svg class="w-3.5 h-3.5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                        </svg>
                        <span class="text-xs font-semibold text-success">{{ $trend }}</span>
                    @elseif($trendNegative)
                        <svg class="w-3.5 h-3.5 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-xs font-semibold text-danger">{{ $trend }}</span>
                    @else
                        <span class="text-xs font-semibold text-gray-500">{{ $trend }}</span>
                    @endif
                    @if($trendLabel)
                        <span class="text-xs text-gray-400">{{ $trendLabel }}</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex-shrink-0 ml-4">
            <div class="w-12 h-12 rounded-2xl {{ $cc['bg'] }} flex items-center justify-center">
                @if($icon)
                    <span class="{{ $cc['text'] }}">{!! $icon !!}</span>
                @else
                    <svg class="w-6 h-6 {{ $cc['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                @endif
            </div>
        </div>
    </div>
</div>

{{--
    forge-button — Ptah Forge
    Props:
      - color   : primary | success | danger | warn | dark | light  (padrão: primary)
      - size    : sm | md | lg  (padrão: md)
      - flat    : boolean - fundo transparente
      - relief  : boolean - aparência sólida sem sombra
      - rounded : boolean - totalmente arredondado
      - disabled: boolean
      - loading : boolean - exibe spinner inline
    Slots:
      - default : texto do botão
      - icon    : ícone opcional
--}}
@props([
    'color'    => 'primary',
    'size'     => 'md',
    'flat'     => false,
    'relief'   => false,
    'rounded'  => false,
    'disabled' => false,
    'loading'  => false,
])

@php
    $colorMap = [
        'primary' => [
            'bg'        => 'bg-primary',
            'hover'     => 'hover:bg-primary-dark',
            'text'      => 'text-primary',
            'shadow'    => 'shadow-[0_8px_20px_rgba(91,33,182,0.45)]',
            'relief'    => 'bg-primary-dark',
            'flatHover' => 'hover:bg-primary-light',
        ],
        'success' => [
            'bg'        => 'bg-success',
            'hover'     => 'hover:bg-success-dark',
            'text'      => 'text-success',
            'shadow'    => 'shadow-[0_8px_20px_rgba(16,185,129,0.45)]',
            'relief'    => 'bg-success-dark',
            'flatHover' => 'hover:bg-success-light',
        ],
        'danger' => [
            'bg'        => 'bg-danger',
            'hover'     => 'hover:bg-danger-dark',
            'text'      => 'text-danger',
            'shadow'    => 'shadow-[0_8px_20px_rgba(239,68,68,0.45)]',
            'relief'    => 'bg-danger-dark',
            'flatHover' => 'hover:bg-danger-light',
        ],
        'warn' => [
            'bg'        => 'bg-warn',
            'hover'     => 'hover:bg-warn-dark',
            'text'      => 'text-warn',
            'shadow'    => 'shadow-[0_8px_20px_rgba(245,158,11,0.45)]',
            'relief'    => 'bg-warn-dark',
            'flatHover' => 'hover:bg-warn-light',
        ],
        'dark' => [
            'bg'        => 'bg-dark',
            'hover'     => 'hover:bg-dark-dark',
            'text'      => 'text-dark',
            'shadow'    => 'shadow-[0_8px_20px_rgba(30,41,59,0.45)]',
            'relief'    => 'bg-dark-dark',
            'flatHover' => 'hover:bg-dark-light',
        ],
        'light' => [
            'bg'        => 'bg-gray-100',
            'hover'     => 'hover:bg-gray-200',
            'text'      => 'text-gray-700',
            'shadow'    => 'shadow-[0_8px_20px_rgba(0,0,0,0.12)]',
            'relief'    => 'bg-gray-300',
            'flatHover' => 'hover:bg-gray-50',
        ],
    ];

    $c = $colorMap[$color] ?? $colorMap['primary'];

    $sizeMap = [
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-5 py-2.5 text-sm gap-2',
        'lg' => 'px-7 py-3.5 text-base gap-2.5',
    ];
    $sizeClass = $sizeMap[$size] ?? $sizeMap['md'];

    if ($flat) {
        $variantClass = "bg-transparent {$c['text']} {$c['flatHover']}";
    } elseif ($relief) {
        $variantClass = "{$c['relief']} text-white";
    } else {
        $variantClass = "{$c['bg']} {$c['hover']} text-white {$c['shadow']}";
    }

    $radiusClass    = $rounded ? 'rounded-full' : 'rounded-xl';
    $disabledClass  = $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
    $baseTransition = 'transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 active:shadow-none';
@endphp

<button
    {{ $attributes->merge([
        'type'     => 'button',
        'class'    => "inline-flex items-center justify-center font-semibold select-none focus:outline-none
                       {$sizeClass} {$radiusClass} {$variantClass} {$baseTransition} {$disabledClass}",
        'disabled' => $disabled || $loading ? true : false,
    ]) }}
>
    @if ($loading)
        <svg class="animate-spin h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
    @endif

    @if (isset($icon) && !$loading)
        <span class="shrink-0">{{ $icon }}</span>
    @endif

    <span>{{ $slot }}</span>
</button>

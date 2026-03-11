{{--
    forge-button — Ptah Forge
    Props:
      - color   : primary | success | danger | warn | dark | light  (default: primary)
      - size    : sm | md | lg  (default: md)
      - flat    : boolean - transparent background
      - relief  : boolean - solid appearance without shadow
      - rounded : boolean - fully rounded
      - disabled: boolean
      - loading : boolean - shows inline spinner
    Slots:
      - default : button text
      - icon    : optional icon
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
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-primary-dark',
            'flatHover' => 'hover:bg-primary-light',
        ],
        'success' => [
            'bg'        => 'bg-success',
            'hover'     => 'hover:bg-success-dark',
            'text'      => 'text-success',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-success-dark',
            'flatHover' => 'hover:bg-success-light',
        ],
        'danger' => [
            'bg'        => 'bg-danger',
            'hover'     => 'hover:bg-danger-dark',
            'text'      => 'text-danger',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-danger-dark',
            'flatHover' => 'hover:bg-danger-light',
        ],
        'warn' => [
            'bg'        => 'bg-warn',
            'hover'     => 'hover:bg-warn-dark',
            'text'      => 'text-warn',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-warn-dark',
            'flatHover' => 'hover:bg-warn-light',
        ],
        'dark' => [
            'bg'        => 'bg-dark dark:bg-slate-600',
            'hover'     => 'hover:bg-dark-dark dark:hover:bg-slate-500',
            'text'      => 'text-dark dark:text-slate-300',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-dark-dark dark:bg-slate-700',
            'flatHover' => 'hover:bg-dark-light dark:hover:bg-slate-700',
        ],
        'light' => [
            'bg'        => 'bg-gray-100 dark:bg-slate-700',
            'hover'     => 'hover:bg-gray-200 dark:hover:bg-slate-600',
            'text'      => 'text-gray-700 dark:text-slate-300',
            'textSolid' => 'text-gray-700 dark:text-slate-200',
            'shadow'    => '',
            'relief'    => 'bg-gray-300 dark:bg-slate-600',
            'flatHover' => 'hover:bg-gray-50 dark:hover:bg-slate-700',
        ],
        'secondary' => [
            'bg'        => 'bg-gray-100 dark:bg-slate-700',
            'hover'     => 'hover:bg-gray-200 dark:hover:bg-slate-600',
            'text'      => 'text-gray-700 dark:text-slate-300',
            'textSolid' => 'text-gray-700 dark:text-slate-200',
            'shadow'    => '',
            'relief'    => 'bg-gray-300 dark:bg-slate-600',
            'flatHover' => 'hover:bg-gray-50 dark:hover:bg-slate-700',
        ],
    ];

    $c = $colorMap[$color] ?? $colorMap['primary'];
    $ptahColorClass = 'ptah-btn-' . $color;

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
        $variantClass = "{$c['bg']} {$c['hover']} {$c['textSolid']} {$c['shadow']}";
    }

    $radiusClass    = $rounded ? 'rounded-full' : 'rounded-md';
    $disabledClass  = $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
    $baseTransition = 'transition-colors duration-150 active:opacity-80';
@endphp

<button
    {{ $attributes->merge([
        'type'     => 'button',
        'class'    => "ptah-btn {$ptahColorClass} inline-flex items-center justify-center font-semibold select-none focus:outline-none
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
        <span class="shrink-0">{!! $icon !!}</span>
    @endif

    <span>{{ $slot }}</span>
</button>

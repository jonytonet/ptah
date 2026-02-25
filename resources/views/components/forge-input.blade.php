{{--
    forge-input — Ptah Forge
    Props:
      - label      : string (floating label)
      - placeholder: string
      - type       : text | email | password | number | ...  (padrão: text)
      - color      : primary | success | danger | warn | dark  (padrão: primary)
      - state      : normal | success | danger | warn
      - iconAfter  : HTML string
      - iconBefore : HTML string
      - disabled   : boolean
      - loading    : boolean
      - message    : string
--}}
@props([
    'label'       => '',
    'placeholder' => ' ',
    'type'        => 'text',
    'color'       => 'primary',
    'state'       => 'normal',
    'iconAfter'   => null,
    'iconBefore'  => null,
    'disabled'    => false,
    'loading'     => false,
    'message'     => '',
])

@php
    $colorBorder = [
        'primary' => 'focus:border-primary',
        'success' => 'focus:border-success',
        'danger'  => 'focus:border-danger',
        'warn'    => 'focus:border-warn',
        'dark'    => 'focus:border-dark',
    ];
    $colorLabel = [
        'primary' => 'peer-focus:text-primary',
        'success' => 'peer-focus:text-success',
        'danger'  => 'peer-focus:text-danger',
        'warn'    => 'peer-focus:text-warn',
        'dark'    => 'peer-focus:text-dark',
    ];
    $stateClass = match($state) {
        'success' => 'border-success',
        'danger'  => 'border-danger',
        'warn'    => 'border-warn',
        default   => 'border-gray-300',
    };
    $messageColor = match($state) {
        'success' => 'text-success',
        'danger'  => 'text-danger',
        'warn'    => 'text-warn',
        default   => 'text-gray-400',
    };
    $focusBorder   = $colorBorder[$color] ?? $colorBorder['primary'];
    $focusLabel    = $colorLabel[$color]  ?? $colorLabel['primary'];
    $disabledClass = $disabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'bg-white';
    $paddingLeft   = $iconBefore ? 'pl-10' : 'pl-3';
    $paddingRight  = ($iconAfter || $loading) ? 'pr-10' : 'pr-3';
@endphp

<div class="relative w-full">
    @if ($iconBefore)
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none z-10">
            {!! $iconBefore !!}
        </span>
    @endif

    <input
        {{ $attributes->merge([
            'type'        => $type,
            'placeholder' => $placeholder,
            'id'          => $attributes->get('id', 'forge-input-' . uniqid()),
            'class'       => "peer block w-full border-b-2 {$stateClass} {$focusBorder} outline-none
                              pt-5 pb-1.5 {$paddingLeft} {$paddingRight} text-sm text-gray-800
                              {$disabledClass} transition-colors duration-200 placeholder-transparent",
            'disabled'    => $disabled,
        ]) }}
    />

    @if ($label)
        <label
            for="{{ $attributes->get('id', '') }}"
            class="absolute left-{{ $iconBefore ? '10' : '3' }} top-3 text-xs text-gray-400
                   transition-all duration-200
                   peer-placeholder-shown:top-1/2 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:text-sm
                   peer-focus:top-1 peer-focus:text-xs {{ $focusLabel }}
                   pointer-events-none"
        >
            {{ $label }}
        </label>
    @endif

    @if ($loading)
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
        </span>
    @elseif ($iconAfter)
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
            {!! $iconAfter !!}
        </span>
    @endif

    @if ($message)
        <p class="mt-1 text-xs {{ $messageColor }}">{{ $message }}</p>
    @endif
</div>

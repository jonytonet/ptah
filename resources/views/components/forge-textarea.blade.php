{{--
    forge-textarea — Ptah Forge
    Props:
      - label    : string
      - placeholder: string
      - rows     : int  (padrão: 4)
      - color    : primary | success | danger | warn  (padrão: primary)
      - state    : normal | success | danger | warn
      - disabled : boolean
      - helper   : string
      - maxlength: int
      - counter  : boolean - exibe contador de caracteres
    Requer Alpine.js (counter)
--}}
@props([
    'label'       => null,
    'placeholder' => '',
    'rows'        => 4,
    'color'       => 'primary',
    'state'       => null,
    'disabled'    => false,
    'helper'      => null,
    'maxlength'   => null,
    'counter'     => false,
])

@php
    $stateClasses = match($state) {
        'success' => 'border-success focus:ring-success focus:border-success',
        'danger'  => 'border-danger  focus:ring-danger  focus:border-danger',
        'warn'    => 'border-warn    focus:ring-warn    focus:border-warn',
        default   => 'border-gray-300 focus:ring-primary focus:border-primary',
    };
    $stateTextClass = match($state) {
        'success' => 'text-success',
        'danger'  => 'text-danger',
        'warn'    => 'text-warn',
        default   => 'text-gray-500',
    };
@endphp

<div x-data="{ chars: 0 }" class="w-full">
    @if($label)
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    @endif

    <textarea
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $disabled ? 'disabled' : '' }}
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
        @if($counter) @input="chars = $event.target.value.length" @endif
        {{ $attributes->merge([
            'class' => "w-full rounded-xl border bg-white px-4 py-2.5 text-sm transition-all duration-200
                        focus:outline-none focus:ring-2 focus:ring-offset-0
                        disabled:bg-gray-50 disabled:cursor-not-allowed resize-y {$stateClasses}"
        ]) }}
    >{{ $slot }}</textarea>

    <div class="flex items-center justify-between mt-1">
        @if($helper)
            <p class="text-xs {{ $stateTextClass }}">{{ $helper }}</p>
        @else
            <span></span>
        @endif

        @if($counter && $maxlength)
            <span class="text-xs text-gray-400">
                <span x-text="chars"></span>/{{ $maxlength }}
            </span>
        @endif
    </div>
</div>

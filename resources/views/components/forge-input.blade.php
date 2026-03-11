{{--
    forge-input — Ptah Forge
    Props:
      - label      : string (label acima do campo)
      - placeholder: string
      - type       : text | email | password | number | date | ...  (default: text)
      - state      : normal | success | danger | warn
      - iconAfter  : HTML string
      - iconBefore : HTML string
      - disabled   : boolean
      - loading    : boolean
      - message    : string
      - required   : boolean
      - error      : string|null  (overrides state to danger and shows message)
      - value      : consumed internally (not leaked to $attributes)
      - name       : consumed internally (not leaked to $attributes)
--}}
@props([
    'label'       => '',
    'placeholder' => '',
    'type'        => 'text',
    'state'       => 'normal',
    'iconAfter'   => null,
    'iconBefore'  => null,
    'disabled'    => false,
    'loading'     => false,
    'message'     => '',
    'required'    => false,
    'error'       => null,
    'value'       => null,
    'name'        => null,
])

@php
    $resolvedState   = $error ? 'danger' : $state;
    $resolvedMessage = $error ?? $message;

    $borderClass = match($resolvedState) {
        'success' => 'border-green-400 focus:border-green-500 focus:ring-green-200',
        'danger'  => 'border-red-400 focus:border-red-500 focus:ring-red-200',
        'warn'    => 'border-yellow-400 focus:border-yellow-500 focus:ring-yellow-200',
        default   => 'border-gray-300 focus:border-blue-600 focus:ring-blue-100',
    };
    $messageColor = match($resolvedState) {
        'success' => 'text-green-600',
        'danger'  => 'text-red-500',
        'warn'    => 'text-yellow-600',
        default   => 'text-gray-400',
    };
    $disabledClass  = $disabled ? 'opacity-50 cursor-not-allowed bg-gray-100' : 'bg-white';
    $paddingLeft    = $iconBefore ? 'pl-9' : 'pl-3';
    $isPassword     = $type === 'password';
    $paddingRight   = ($iconAfter || $loading || $isPassword) ? 'pr-9' : 'pr-3';
    $inputId        = $attributes->get('id', 'forge-input-' . uniqid());
@endphp

<div class="ptah-input-wrapper w-full" @if($isPassword) x-data="{ _show: false }" @endif>
    @if ($label)
        <label for="{{ $inputId }}" class="block text-xs font-medium text-gray-600 mb-1">
            {{ $label }}@if ($required) <span class="text-red-500 ml-0.5">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if ($iconBefore)
            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                {!! $iconBefore !!}
            </span>
        @endif

        <input
            id="{{ $inputId }}"
            {{ $attributes->merge([
                'type'        => $type,
                'placeholder' => $placeholder,
                'name'        => $name,
                'required'    => $required ?: null,
                'class'       => "block w-full rounded border {$borderClass} outline-none {$paddingLeft} {$paddingRight} py-2.5 text-sm text-gray-800 {$disabledClass} transition-colors duration-150 focus:ring-2",
                'disabled'    => $disabled,
            ]) }}
            @if($isPassword) :type="_show ? 'text' : 'password'" @endif
        />

        @if ($loading)
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </span>
        @elseif ($isPassword)
            <button type="button" @click="_show = !_show"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none"
                :title="_show ? 'Ocultar senha' : 'Mostrar senha'"
                tabindex="-1">
                {{-- Olho aberto --}}
                <svg x-show="!_show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7
                             -1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{-- Olho fechado --}}
                <svg x-show="_show" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7
                             a9.97 9.97 0 012.626-4.236M9.88 9.88a3 3 0 104.243 4.243
                             M3 3l18 18" />
                </svg>
            </button>
        @elseif ($iconAfter)
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                {!! $iconAfter !!}
            </span>
        @endif
    </div>

    @if ($resolvedMessage)
        <p class="mt-1 text-xs {{ $messageColor }}">{{ $resolvedMessage }}</p>
    @endif
</div>

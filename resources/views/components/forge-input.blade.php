{{--
    forge-input — Ptah Forge
    Props:
      - label      : string (label acima do campo)
      - placeholder: string
      - type       : text | email | password | number | date | ...  (padrão: text)
      - state      : normal | success | danger | warn
      - iconAfter  : HTML string
      - iconBefore : HTML string
      - disabled   : boolean
      - loading    : boolean
      - message    : string
      - required   : boolean
      - error      : string|null  (sobrescreve state para danger e exibe mensagem)
      - value      : consumido internamente (não vaza ao $attributes)
      - name       : consumido internamente (não vaza ao $attributes)
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
        default   => 'border-gray-300 focus:border-violet-500 focus:ring-violet-100',
    };
    $messageColor = match($resolvedState) {
        'success' => 'text-green-600',
        'danger'  => 'text-red-500',
        'warn'    => 'text-yellow-600',
        default   => 'text-gray-400',
    };
    $disabledClass = $disabled ? 'opacity-50 cursor-not-allowed bg-gray-100' : 'bg-white';
    $paddingLeft   = $iconBefore ? 'pl-9' : 'pl-3';
    $paddingRight  = ($iconAfter || $loading) ? 'pr-9' : 'pr-3';
    $inputId       = $attributes->get('id', 'forge-input-' . uniqid());
@endphp

<div class="w-full">
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
                'class'       => "block w-full rounded-lg border {$borderClass} outline-none {$paddingLeft} {$paddingRight} py-2.5 text-sm text-gray-800 {$disabledClass} transition-colors duration-150 focus:ring-2",
                'disabled'    => $disabled,
            ]) }}
        />

        @if ($loading)
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </span>
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

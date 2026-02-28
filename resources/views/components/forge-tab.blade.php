{{--
    forge-tab — Ptah Forge
    Sub-componente de <x-forge-tabs> (slot "tabs").
    Props:
      - key    : string  - identificador da aba (informativo, não aplicado por este componente)
      - active : bool    - se é a aba selecionada
      - color  : primary | success | danger | warn (padrão: primary)
    Aceita quaisquer atributos extras (wire:click, @click, etc.)
--}}
@props([
    'key'    => '',
    'active' => false,
    'color'  => 'primary',
])

@php
    $activeClass = [
        'primary' => 'text-primary border-b-2 border-primary',
        'success' => 'text-success border-b-2 border-success',
        'danger'  => 'text-danger  border-b-2 border-danger',
        'warn'    => 'text-warn    border-b-2 border-warn',
    ];
    $inactiveClass = 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent';
    $stateClass    = $active ? ($activeClass[$color] ?? $activeClass['primary']) : $inactiveClass;
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => "px-4 py-3 text-sm font-medium transition-all duration-200 whitespace-nowrap focus:outline-none {$stateClass}"]) }}
>
    {{ $slot }}
</button>

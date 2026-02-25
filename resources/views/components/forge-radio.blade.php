{{--
    forge-radio — Ptah Forge
    Props:
      - label   : string
      - color   : primary | success | danger | warn  (padrão: primary)
      - value   : string
      - name    : string
      - disabled: boolean
--}}
@props([
    'label'    => null,
    'color'    => 'primary',
    'value'    => '',
    'name'     => '',
    'disabled' => false,
])

@php
    $colors = [
        'primary' => 'text-primary focus:ring-primary',
        'success' => 'text-success focus:ring-success',
        'danger'  => 'text-danger  focus:ring-danger',
        'warn'    => 'text-warn    focus:ring-warn',
    ];
    $colorClass = $colors[$color] ?? $colors['primary'];
@endphp

<label class="inline-flex items-center gap-2 cursor-pointer {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}">
    <input
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => "w-4 h-4 border-gray-300 transition-colors {$colorClass}"]) }}
    />
    @if($label)
        <span class="text-sm text-gray-700 select-none">{{ $label }}</span>
    @endif
</label>

{{--
    forge-checkbox — Ptah Forge
    Props:
      - label   : string
      - color   : primary | success | danger | warn  (default: primary)
      - checked : boolean
      - disabled: boolean
--}}
@props([
    'label'    => null,
    'color'    => 'primary',
    'checked'  => false,
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
        type="checkbox"
        {{ $checked ? 'checked' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        {{-- Borda e fundo por token (ptah-c-ctrl_check), nao `border-gray-300`:
             no escuro a borda clara e o fundo branco fixo destoavam (1.46.4). --}}
        {{ $attributes->merge(['class' => "w-4 h-4 rounded ptah-c-ctrl_check transition-colors {$colorClass}"]) }}
    />
    @if($label)
        <span class="text-sm ptah-c-ctrl_label select-none">{{ $label }}</span>
    @endif
</label>

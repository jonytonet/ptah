{{--
    forge-switch — Ptah Forge
    Props:
      - label   : string
      - color   : primary | success | danger | warn  (padrão: primary)
      - checked : boolean
      - disabled: boolean
      - size    : sm | md | lg  (padrão: md)
    Requer Alpine.js
--}}
@props([
    'label'    => null,
    'color'    => 'primary',
    'checked'  => false,
    'disabled' => false,
    'size'     => 'md',
])

@php
    $sizes = [
        'sm' => ['track' => 'w-8 h-4',    'thumb' => 'w-3 h-3', 'translate' => 'translate-x-4'],
        'md' => ['track' => 'w-11 h-6',   'thumb' => 'w-4 h-4', 'translate' => 'translate-x-5'],
        'lg' => ['track' => 'w-14 h-7',   'thumb' => 'w-5 h-5', 'translate' => 'translate-x-7'],
    ];
    $colors = [
        'primary' => 'bg-primary', 'success' => 'bg-success',
        'danger'  => 'bg-danger',  'warn'    => 'bg-warn',
    ];
    $sz          = $sizes[$size] ?? $sizes['md'];
    $activeColor = $colors[$color] ?? $colors['primary'];
@endphp

<label
    x-data="{ checked: {{ $checked ? 'true' : 'false' }} }"
    class="inline-flex items-center gap-3 cursor-pointer {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}"
>
    <div class="relative">
        <input
            type="checkbox"
            x-model="checked"
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->class(['sr-only']) }}
        />
        <div
            :class="checked ? '{{ $activeColor }}' : 'bg-gray-300'"
            class="{{ $sz['track'] }} rounded-full transition-colors duration-200"
        ></div>
        <div
            :class="checked ? '{{ $sz['translate'] }}' : 'translate-x-1'"
            class="{{ $sz['thumb'] }} bg-white rounded-full absolute top-1/2 -translate-y-1/2 left-0 shadow-sm transition-transform duration-200"
        ></div>
    </div>

    @if($label)
        <span class="text-sm text-gray-700 select-none">{{ $label }}</span>
    @endif
</label>

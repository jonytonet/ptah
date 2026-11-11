{{--
    forge-switch — Ptah Forge
    Props:
      - label   : string
      - color   : primary | success | danger | warn  (default: primary)
      - checked : boolean — initial state WITHOUT wire:model. With wire:model
                  the switch reads the Livewire property itself (1.58.5);
                  the prop is ignored there.
      - disabled: boolean
      - size    : sm | md | lg  (default: md)
    Requires Alpine.js
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

    // 1.58.5: with wire:model the paint comes from the Livewire property, not
    // from the `checked` prop. Before, the prop (default false) seeded Alpine
    // and an x-model on the input fought wire:model — a switch bound to true
    // was born grey, and the first click turned the model OFF while it stayed
    // grey. Now there is one source of truth, and a change the server makes
    // later repaints it too.
    $wireModel = $attributes->wire('model')->value();
@endphp

<label
    @if ($wireModel)
        x-data="{ get checked() { return !! this.$wire.get({{ \Illuminate\Support\Js::from($wireModel) }}) } }"
    @else
        x-data="{ checked: {{ $checked ? 'true' : 'false' }} }"
    @endif
    class="inline-flex items-center gap-3 cursor-pointer {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}"
>
    <div class="relative">
        <input
            type="checkbox"
            @unless ($wireModel) x-model="checked" @endunless
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->class(['sr-only']) }}
        />
        <div
            :class="checked ? '{{ $activeColor }}' : 'ptah-c-switch_track_off'"
            class="{{ $sz['track'] }} rounded-full transition-colors duration-200"
        ></div>
        <div
            :class="checked ? '{{ $sz['translate'] }}' : 'translate-x-1'"
            class="{{ $sz['thumb'] }} ptah-c-switch_thumb rounded-full absolute top-1/2 -translate-y-1/2 left-0 shadow-sm transition-transform duration-200"
        ></div>
    </div>

    @if($label)
        <span class="text-sm ptah-c-ctrl_label select-none">{{ $label }}</span>
    @endif
</label>

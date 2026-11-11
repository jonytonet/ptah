{{--
    forge-textarea — Ptah Forge
    Props:
      - label    : string
      - placeholder: string
      - rows     : int  (default: 4)
      - color    : primary | success | danger | warn  (default: primary)
      - state    : normal | success | danger | warn
      - disabled : boolean
      - helper   : string
      - maxlength: int
      - counter  : boolean - shows character counter
    Requires Alpine.js (counter)
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

@php
    // 1.58.5: the counter started at 0 even with text already in the field
    // (wire:model or the slot) and only moved on typing. With wire:model it
    // now reads the Livewire property — server changes included; without,
    // it counts what the textarea holds once Alpine is up.
    $wireModel = $attributes->wire('model')->value();
@endphp

<div
    @if ($wireModel)
        x-data="{ get chars() { return String(this.$wire.get({{ \Illuminate\Support\Js::from($wireModel) }}) ?? '').length } }"
    @else
        x-data="{ chars: 0 }"
        x-init="$nextTick(() => { chars = $refs.field.value.length })"
    @endif
    class="ptah-textarea-wrapper w-full">
    @if($label)
        <label class="block text-sm font-medium mb-1">{{ $label }}</label>
    @endif

    <textarea
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $disabled ? 'disabled' : '' }}
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
        x-ref="field"
        @if($counter && ! $wireModel) @input="chars = $event.target.value.length" @endif
        {{ $attributes->merge([
            'class' => "w-full rounded-md border px-4 py-2.5 text-sm transition-colors duration-150
                        focus:outline-none focus:ring-2 focus:ring-offset-0
                        disabled:cursor-not-allowed resize-y {$stateClasses}"
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

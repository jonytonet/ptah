{{--
    forge-select — Ptah Forge
    Props:
      - label      : string (label acima do campo)
      - options    : array [ ['value' => '', 'label' => ''], ... ]
      - placeholder: string  (padrão: 'Selecione...')
      - multiple   : boolean
      - disabled   : boolean
      - required   : boolean
      - error      : string|null  (sobrescreve state para danger)
      - selected   : mixed|null   (valor inicial)
      - name       : consumido internamente
    Requer Alpine.js
--}}
@props([
    'label'       => '',
    'options'     => [],
    'placeholder' => 'Selecione...',
    'multiple'    => false,
    'disabled'    => false,
    'required'    => false,
    'error'       => null,
    'selected'    => null,
    'name'        => null,
])

@php
    $uniqueId        = 'forge-select-' . uniqid();
    $disabledClass   = $disabled ? 'opacity-50 pointer-events-none' : '';
    $borderNormal    = $error ? 'border-red-400' : 'border-gray-300';
    $borderOpen      = $error ? 'border-red-500' : 'border-violet-500';
    $ringOpen        = $error ? 'ring-2 ring-red-200' : 'ring-2 ring-violet-100';
    $initialSelected = $multiple ? '[]' : ($selected !== null ? json_encode($selected) : 'null');
@endphp

<div class="w-full">
    @if ($label)
        <label class="block text-xs font-medium text-gray-600 mb-1">
            {{ $label }}@if ($required) <span class="text-red-500 ml-0.5">*</span>@endif
        </label>
    @endif

    <div
        x-data="{
            open: false,
            selected: {{ $initialSelected }},
            multiple: {{ $multiple ? 'true' : 'false' }},
            options: {{ json_encode($options) }},
            placeholder: '{{ addslashes($placeholder) }}',
            get displayLabel() {
                if (this.multiple) {
                    if (!this.selected || !this.selected.length) return this.placeholder;
                    return this.selected.map(v => {
                        const opt = this.options.find(o => String(o.value) === String(v));
                        return opt ? opt.label : v;
                    }).join(', ');
                }
                if (this.selected === null || this.selected === '' || this.selected === undefined) return this.placeholder;
                const opt = this.options.find(o => String(o.value) === String(this.selected));
                return opt ? opt.label : this.placeholder;
            },
            isSelected(value) {
                if (this.multiple) return this.selected && this.selected.includes(String(value));
                return String(this.selected) === String(value);
            },
            toggle(value) {
                if (this.multiple) {
                    if (!this.selected) this.selected = [];
                    const idx = this.selected.indexOf(String(value));
                    if (idx >= 0) this.selected.splice(idx, 1);
                    else this.selected.push(String(value));
                } else {
                    this.selected = String(value);
                    this.open = false;
                }
            }
        }"
        @click.outside="open = false"
        class="relative {{ $disabledClass }}"
        id="{{ $uniqueId }}"
    >
        {{-- Hidden input: bridge Alpine selected → Livewire wire:model --}}
        <input type="hidden"
            :value="multiple ? JSON.stringify(selected) : (selected ?? '')"
            x-init="$watch('selected', val => {
                $el.value = multiple ? JSON.stringify(val) : (val ?? '');
                $el.dispatchEvent(new Event('input', { bubbles: true }));
            })"
            {{ $attributes->whereStartsWith('wire:') }}
        >

        {{-- Trigger --}}
        <div
            @click="open = !open"
            :class="open ? '{{ $borderOpen }} {{ $ringOpen }}' : '{{ $borderNormal }}'"
            class="relative flex items-center justify-between rounded-lg border bg-white px-3 py-2.5 cursor-pointer select-none transition-colors duration-150"
        >
            <span
                :class="(selected !== null && selected !== '' && selected !== undefined && (!Array.isArray(selected) || selected.length > 0)) ? 'text-gray-800' : 'text-gray-400'"
                class="text-sm truncate pr-4"
                x-text="displayLabel"
            ></span>

            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </div>

        {{-- Dropdown --}}
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute z-20 mt-1 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-auto max-h-48"
        >
            <ul class="py-1">
                <template x-for="option in options" :key="option.value">
                    <li
                        @click="toggle(option.value)"
                        :class="isSelected(option.value) ? 'bg-violet-50 text-violet-700' : 'text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 text-sm cursor-pointer flex items-center justify-between transition-colors duration-100"
                    >
                        <span x-text="option.label"></span>
                        <svg x-show="isSelected(option.value)" class="h-4 w-4 text-violet-600 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    @if ($error)
        <p class="mt-1 text-xs text-red-500">{{ $error }}</p>
    @endif
</div>

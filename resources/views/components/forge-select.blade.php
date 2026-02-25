{{--
    forge-select — Ptah Forge
    Props:
      - label      : string (floating label)
      - options    : array [ ['value' => '', 'label' => ''], ... ]
      - placeholder: string  (padrão: 'Selecione...')
      - color      : primary | success | danger | warn | dark  (padrão: primary)
      - multiple   : boolean
      - disabled   : boolean
    Requer Alpine.js
--}}
@props([
    'label'       => '',
    'options'     => [],
    'placeholder' => 'Selecione...',
    'color'       => 'primary',
    'multiple'    => false,
    'disabled'    => false,
])

@php
    $colorMap = [
        'primary' => ['focus' => 'border-primary', 'hover' => 'hover:bg-primary-light', 'sel' => 'bg-primary-light text-primary'],
        'success' => ['focus' => 'border-success', 'hover' => 'hover:bg-success-light', 'sel' => 'bg-success-light text-success'],
        'danger'  => ['focus' => 'border-danger',  'hover' => 'hover:bg-danger-light',  'sel' => 'bg-danger-light text-danger'],
        'warn'    => ['focus' => 'border-warn',     'hover' => 'hover:bg-warn-light',    'sel' => 'bg-warn-light text-warn'],
        'dark'    => ['focus' => 'border-dark',     'hover' => 'hover:bg-dark-light',    'sel' => 'bg-dark-light text-dark'],
    ];
    $c            = $colorMap[$color] ?? $colorMap['primary'];
    $uniqueId     = 'forge-select-' . uniqid();
    $disabledClass = $disabled ? 'opacity-50 pointer-events-none' : '';
@endphp

<div
    x-data="{
        open: false,
        selected: {{ $multiple ? '[]' : 'null' }},
        multiple: {{ $multiple ? 'true' : 'false' }},
        options: {{ json_encode($options) }},
        placeholder: '{{ $placeholder }}',
        get displayLabel() {
            if (this.multiple) {
                if (!this.selected.length) return this.placeholder;
                return this.selected.map(v => {
                    const opt = this.options.find(o => o.value == v);
                    return opt ? opt.label : v;
                }).join(', ');
            }
            if (this.selected === null || this.selected === '') return this.placeholder;
            const opt = this.options.find(o => o.value == this.selected);
            return opt ? opt.label : this.placeholder;
        },
        isSelected(value) {
            if (this.multiple) return this.selected.includes(value);
            return this.selected == value;
        },
        toggle(value) {
            if (this.multiple) {
                const idx = this.selected.indexOf(value);
                if (idx >= 0) this.selected.splice(idx, 1);
                else this.selected.push(value);
            } else {
                this.selected = value;
                this.open = false;
            }
        }
    }"
    @click.outside="open = false"
    class="relative w-full {{ $disabledClass }}"
    id="{{ $uniqueId }}"
>
    <div
        @click="open = !open"
        :class="open ? '{{ $c['focus'] }}' : 'border-gray-300'"
        class="relative flex items-center justify-between border-b-2 pt-5 pb-1.5 pl-3 pr-8 cursor-pointer select-none transition-colors duration-200 bg-white"
    >
        @if ($label)
            <span
                :class="(open || selected !== null && selected !== '' && (!Array.isArray(selected) || selected.length))
                    ? 'top-1 text-xs {{ $c['focus'] === 'border-primary' ? 'text-primary' : 'text-current' }}'
                    : 'top-1/2 -translate-y-1/2 text-sm text-gray-400'"
                class="absolute left-3 transition-all duration-200 pointer-events-none"
            >{{ $label }}</span>
        @endif

        <span class="text-sm text-gray-800 truncate" x-text="displayLabel"></span>

        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </div>

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
                    :class="isSelected(option.value) ? '{{ $c['sel'] }}' : 'text-gray-700 {{ $c['hover'] }}'"
                    class="px-4 py-2 text-sm cursor-pointer flex items-center justify-between transition-colors duration-100"
                    x-text="option.label"
                ></li>
            </template>
        </ul>
    </div>
</div>

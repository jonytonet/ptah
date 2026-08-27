{{--
    forge-select — Ptah Forge
    Props:
      - label      : string (label above the field)
      - options    : array [ ['value' => '', 'label' => ''], ... ]
      - placeholder: string  (default: 'Select...')
      - multiple   : boolean
      - disabled   : boolean
      - required   : boolean
      - error      : string|null  (overrides state to danger)
      - selected   : mixed|null   (initial value; se omitido e houver
                     wire:model[.mod], é semeado da propriedade Livewire
                     correspondente — dot-path suportado, ex. "filters.kind")
      - name       : consumed internally
      - searchable : boolean (default false). Adds a client-side filter input
                     at the top of the dropdown; matching is case/accent-
                     insensitive (NFD-strip diacritics). Arrow-key navigation
                     visits only the currently-matching options. Every new
                     property/method below is emitted ONLY when this prop is
                     true — a consumer that never sets it gets byte-identical
                     markup to before (see ForgeSelectSearchableTest).

    LIMITAÇÃO CONHECIDA: `multiple` não é suportado com wire:model (a ponte
    hidden envia string JSON, não array) — nenhum call site do pacote combina
    os dois hoje.
    Requires Alpine.js
--}}
@props([
    'label'       => '',
    'options'     => [],
    'placeholder' => 'Select...',
    'multiple'    => false,
    'disabled'    => false,
    'required'    => false,
    'error'       => null,
    'selected'    => null,
    'name'        => null,
    'searchable'  => false,
])

@php
    // Morph-stable id: o DOM-diff do Livewire usa `el.id` como chave quando não há
    // wire:id/wire:key, então um id aleatório por render (uniqid, como era antes)
    // faz o morph REMOVER e recriar o elemento — destruindo o componente Alpine
    // (perde foco, `open`, `activeIndex`) a cada atualização do Livewire. O id é
    // derivado da identidade do campo (label|name|placeholder|wire:model), logo
    // igual em todo render. Mesmo padrão de forge-input.blade.php.
    $wireModelAttr   = collect($attributes->whereStartsWith('wire:model')->getAttributes())->first() ?? '';
    $uniqueId        = 'forge-select-' . substr(md5($label.'|'.($name ?? '').'|'.$placeholder.'|'.$wireModelAttr), 0, 12);
    $disabledClass   = $disabled ? 'opacity-50 pointer-events-none' : '';
    $borderNormal    = $error ? 'border-red-400' : 'border-gray-300';
    $borderOpen      = $error ? 'border-red-500' : 'border-primary';
    $ringOpen        = $error ? 'ring-2 ring-red-200' : 'ring-2 ring-primary/20';
    // Exact "wire:model" ou "wire:model.<mod>" — NÃO "wire:modelable"
    // (whereStartsWith('wire:model') casaria com 'wire:modelable'; ver forge-modal.blade.php).
    $hasWireModel = $attributes->has('wire:model')
        || $attributes->whereStartsWith('wire:model.')->isNotEmpty();

    // A ponte hidden→Livewire é unidirecional (Alpine escreve via $watch, nada
    // volta de Livewire para `selected` depois do primeiro render). Com o id agora
    // estável, o node sobrevive ao morph — então este seeding cobre o primeiro
    // render; sem ele, `selected` fica null e o gatilho mostra o placeholder mesmo
    // com o valor já aplicado no Livewire. `:selected` (prop explícita) continua vencendo.
    $resolvedSelected = $selected;
    if ($resolvedSelected === null && $hasWireModel && isset($__livewire)) {
        $resolvedSelected = data_get($__livewire, (string) $attributes->wire('model')->value());
    }

    $initialSelected = $multiple
        ? '[]'
        : ($resolvedSelected !== null ? json_encode($resolvedSelected) : 'null');
@endphp

<div class="ptah-select-wrapper w-full">
    @if ($label)
        <label class="block text-xs font-medium mb-1">
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
@if ($searchable)
            search: '',
            normalizeText(value) {
                return String(value).toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
            },
            matchesFilter(option) {
                // A clear-selection option (empty value, e.g. a leading
                // None entry) is a control affordance, not searchable
                // content — it must stay reachable no matter what the user
                // typed, or the filter could hide the only way to unselect.
                if (option.value === '' || option.value === null) return true;
                const needle = this.normalizeText(this.search);
                return !needle || this.normalizeText(option.label).includes(needle);
            },
            firstVisibleIndex() {
                for (let i = 0; i < this.options.length; i++) {
                    if (this.matchesFilter(this.options[i])) return i;
                }
                return -1;
            },
            hasVisibleOptions() {
                return this.firstVisibleIndex() >= 0;
            },
            onFilterInput() {
                this.activeIndex = this.firstVisibleIndex();
            },
            onFilterEscape() {
                if (this.search !== '') {
                    this.search = '';
                    this.activeIndex = this.firstVisibleIndex();
                } else {
                    this.open = false;
                }
            },
@endif
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
            },
            activeIndex: -1,
@if ($searchable)
            openList() {
                this.open = true;
                /* A stale filter from the previous open could hide the currently
                   selected option or show a confusing empty state (review
                   finding) — every open starts clean. */
                this.search = '';
                this.$nextTick(() => { if (this.$refs.filterInput) this.$refs.filterInput.focus(); });
                const sel = this.options.findIndex(o => this.isSelected(o.value));
                this.activeIndex = (sel >= 0 && this.matchesFilter(this.options[sel])) ? sel : this.firstVisibleIndex();
            },
            move(delta) {
                if (!this.open) { this.openList(); return; }
                const n = this.options.length;
                if (!n || !this.hasVisibleOptions()) { this.activeIndex = -1; return; }
                let next = this.activeIndex;
                for (let i = 0; i < n; i++) {
                    next = (next + delta + n) % n;
                    if (this.matchesFilter(this.options[next])) { this.activeIndex = next; return; }
                }
            },
@else
            openList() {
                this.open = true;
                if (this.activeIndex < 0) {
                    const sel = this.options.findIndex(o => this.isSelected(o.value));
                    this.activeIndex = sel >= 0 ? sel : 0;
                }
            },
            move(delta) {
                if (!this.open) { this.openList(); return; }
                const n = this.options.length;
                if (!n) return;
                this.activeIndex = (this.activeIndex + delta + n) % n;
            },
@endif
            selectActive() {
                if (this.open && this.activeIndex >= 0 && this.options[this.activeIndex]) {
                    this.toggle(this.options[this.activeIndex].value);
                } else {
                    this.open = !this.open;
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
        <button
            type="button"
            {{-- Searchable: clicking must route through openList() — it is what
                 focuses the filter input and resets a stale filter. The plain
                 toggle bypassed both, caught by the browser test in real
                 Chrome (the structural tests could not see it). --}}
            @click="{{ $searchable ? 'open ? open = false : openList()' : 'open = !open' }}"
            @keydown.enter.prevent="selectActive()"
            @keydown.space.prevent="selectActive()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.escape.prevent="open = false"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            aria-controls="{{ $uniqueId }}-list"
            :class="open ? '{{ $borderOpen }} {{ $ringOpen }}' : '{{ $borderNormal }}'"
            class="ptah-select-trigger relative flex w-full items-center justify-between rounded-md border px-3 py-2.5 text-left cursor-pointer select-none transition-colors duration-150 focus:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20"
        >
            <span
                :class="(selected !== null && selected !== '' && selected !== undefined && (!Array.isArray(selected) || selected.length > 0)) ? 'ptah-c-sel_val' : 'text-gray-400'"
                class="text-sm truncate pr-4"
                x-text="displayLabel"
            ></span>

            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </button>

        {{-- Dropdown --}}
@if ($searchable)
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="ptah-select-dropdown absolute z-20 mt-1 w-full border border-gray-200 rounded-md overflow-hidden"
        >
            <div class="p-1.5 border-b ptah-c-dd_sep">
                <input
                    type="text"
                    x-ref="filterInput"
                    x-model="search"
                    @input="onFilterInput()"
                    @keydown.enter.prevent="selectActive()"
                    @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-up.prevent="move(-1)"
                    @keydown.escape.prevent="onFilterEscape()"
                    :aria-activedescendant="activeIndex >= 0 ? ('{{ $uniqueId }}-opt-' + activeIndex) : null"
                    aria-label="{{ __('ptah::ui.forge_select_filter_aria') }}"
                    autocomplete="off"
                    class="ptah-select-filter w-full rounded border px-2 py-1 text-sm focus:outline-none"
                >
            </div>
            <ul class="py-1 max-h-48 overflow-auto" role="listbox" id="{{ $uniqueId }}-list" :aria-multiselectable="multiple" aria-live="polite">
                <template x-for="(option, idx) in options" :key="option.value">
                    <li
                        x-show="matchesFilter(option)"
                        :id="'{{ $uniqueId }}-opt-' + idx"
                        @click="toggle(option.value)"
                        @mouseenter="activeIndex = idx"
                        role="option"
                        :aria-selected="isSelected(option.value)"
                        :class="[ isSelected(option.value) ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item', activeIndex === idx ? 'ptah-select-active' : '' ]"
                        class="px-4 py-2 text-sm cursor-pointer flex items-center justify-between transition-colors duration-100"
                    >
                        <span x-text="option.label"></span>
                        <svg x-show="isSelected(option.value)" class="h-4 w-4 text-primary shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </li>
                </template>
                <li x-show="!hasVisibleOptions()" class="ptah-select-empty px-4 py-2 text-sm" role="presentation">
                    {{ __('ptah::ui.no_results') }}
                </li>
            </ul>
        </div>
@else
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="ptah-select-dropdown absolute z-20 mt-1 w-full border border-gray-200 rounded-md overflow-auto max-h-48"
        >
            <ul class="py-1" role="listbox" id="{{ $uniqueId }}-list" :aria-multiselectable="multiple">
                <template x-for="(option, idx) in options" :key="option.value">
                    <li
                        @click="toggle(option.value)"
                        @mouseenter="activeIndex = idx"
                        role="option"
                        :aria-selected="isSelected(option.value)"
                        :class="[ isSelected(option.value) ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item', activeIndex === idx ? 'ptah-select-active' : '' ]"
                        class="px-4 py-2 text-sm cursor-pointer flex items-center justify-between transition-colors duration-100"
                    >
                        <span x-text="option.label"></span>
                        <svg x-show="isSelected(option.value)" class="h-4 w-4 text-primary shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </li>
                </template>
            </ul>
        </div>
@endif
    </div>

    @if ($error)
        <p class="mt-1 text-xs text-red-500">{{ $error }}</p>
    @endif
</div>

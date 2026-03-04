{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Criar / Editar ─────────────────────────────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@teleport('body')
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         x-show="$wire.showModal"
         x-cloak
         x-on:keydown.escape.window="if ($wire.showModal) { $wire.showModal = false; $wire.closeModal(); }">

        {{-- Overlay --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.showModal = false; $wire.closeModal()"></div>

        {{-- Painel do modal --}}
        <div class="relative rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col mx-4 ptah-c-modal_card">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b ptah-c-modal_hd">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg ptah-c-modal_icon">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            @if($editingId)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            @endif
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-[13px] font-semibold leading-tight ptah-c-modal_ttl">
                            {{ $editingId ? __('ptah::ui.modal_edit_prefix') : __('ptah::ui.modal_new_prefix') }} {{ $crudTitle }}
                        </h2>
                        <p class="text-[11px] leading-tight ptah-c-modal_sub">{{ $editingId ? __('ptah::ui.modal_edit_subtitle') : __('ptah::ui.modal_create_subtitle') }}</p>
                    </div>
                </div>
                <button wire:click="closeModal" class="p-2 transition-colors rounded-lg ptah-c-modal_close">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Erro geral --}}
            @if (!empty($formErrors['_general']))
                <div class="mx-6 mt-4">
                    <x-forge-alert type="danger">{{ $formErrors['_general'] }}</x-forge-alert>
                </div>
            @endif

            {{-- Body --}}
            <div class="flex-1 px-6 py-5 overflow-y-auto ptah-c-modal_body">
                <div class="flex flex-col gap-4">

                    @foreach ($formCols as $col)
                        @php
                            $fField    = $col['colsNomeFisico'];
                            $fLabel    = $col['colsNomeLogico'] ?? $fField;
                            $fTipo     = $col['colsTipo'] ?? 'text';
                            $fRequired = in_array($col['colsRequired'] ?? false, [true, 'S', 1, '1'], true);
                            $fError    = $formErrors[$fField] ?? null;
                            $fMask     = $col['colsMask'] ?? null;
                            $fValue    = $formData[$fField] ?? '';

            $fBorderClass  = $fError
                                ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
                                : 'ptah-c-form_border focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100';
                        @endphp

                        <div class="{{ $fTipo === 'searchdropdown' ? 'relative' : '' }}">

                            @if ($fTipo === 'select' && !empty($col['colsSelect']))
                                {{-- ── Select inline ── --}}
                                @php
                                    $fOptions = collect($col['colsSelect'])
                                        ->map(fn($v, $k) => ['value' => (string)$v, 'label' => $k])
                                        ->values()
                                        ->toArray();
                                    // Handle PHP booleans (cast:boolean): false → '0', true → '1'
                                    $fValSel  = is_bool($fValue) ? ($fValue ? '1' : '0') : $fValue;
                                    $fInitSel = ($fValSel !== '' && $fValSel !== null) ? json_encode((string)$fValSel) : 'null';
                                    $fBorderNormal = $fError ? 'border-red-400' : 'border-slate-200';
                                    $fBorderOpen   = $fError ? 'border-red-500' : 'border-indigo-500';
                                    $fRingOpen     = $fError ? 'ring-2 ring-red-200' : 'ring-2 ring-indigo-100/50';
                                @endphp
                                <div class="w-full">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </label>
                                    <div
                                        x-data="{
                                            open: false,
                                            selected: {{ $fInitSel }},
                                            options: {{ json_encode($fOptions) }},
                                            placeholder: '{{ __('ptah::ui.select_placeholder') }}',
                                            get displayLabel() {
                                                if (this.selected === null || this.selected === '') return this.placeholder;
                                                const opt = this.options.find(o => String(o.value) === String(this.selected));
                                                return opt ? opt.label : this.placeholder;
                                            },
                                            isSelected(value) { return String(this.selected) === String(value); },
                                            toggle(value) { this.selected = String(value); this.open = false; }
                                        }"
                                        @click.outside="open = false"
                                        class="relative"
                                    >
                                        <input type="hidden"
                                            :value="selected ?? ''"
                                            x-init="$watch('selected', val => {
                                                $el.value = val ?? '';
                                                $el.dispatchEvent(new Event('input', { bubbles: true }));
                                            })"
                                            wire:model.live="formData.{{ $fField }}"
                                        >
                                        <div
                                            @click="open = !open"
                                            :class="open ? '{{ $fBorderOpen }} {{ $fRingOpen }}' : '{{ $fBorderNormal }}'"
                                            class="relative flex items-center justify-between rounded-lg border select-none transition-colors duration-150 ptah-c-form_sel"
                                        >
                                            <span
                                                :class="(selected !== null && selected !== '') ? 'ptah-c-sel_val' : 'text-gray-400'"
                                                class="pr-4 text-sm truncate"
                                                x-text="displayLabel"
                                            ></span>
                                            <span class="absolute text-gray-400 transition-transform duration-200 -translate-y-1/2 right-3 top-1/2" :class="open ? 'rotate-180' : ''">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                            class="absolute z-20 w-full mt-1 overflow-auto border shadow-lg rounded-xl max-h-48 ptah-c-dd">
                                            <ul class="py-1">
                                                <template x-for="option in options" :key="option.value">
                                                    <li
                                                        @click="toggle(option.value)"
                                                        :class="isSelected(option.value) ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item'"
                                                        class="flex items-center justify-between px-4 py-2 text-sm cursor-pointer"
                                                    >
                                                        <span x-text="option.label"></span>
                                                        <svg x-show="isSelected(option.value)" class="w-4 h-4 ml-2 text-violet-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>
                                    @if ($fError)
                                        <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                    @endif
                                </div>

                            @elseif ($fTipo === 'searchdropdown')
                                {{-- ── SearchDropdown inline ── --}}
                                @php
                                    $sdInitLabel  = $sdLabels[$fField] ?? '';
                                    $sdHasResults = !empty($sdResults[$fField]);
                                @endphp
                                <div class="w-full">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </label>
                                    <div
                                        x-data="{
                                            open: {{ $sdHasResults ? 'true' : 'false' }},
                                            displayVal: @js($sdInitLabel),
                                            init() {
                                                this.$wire.$watch('sdLabels', (val) => {
                                                    if (val['{{ $fField }}'] !== undefined) {
                                                        this.displayVal = val['{{ $fField }}'];
                                                    }
                                                });
                                                this.$wire.$watch('sdResults', (val) => {
                                                    const res = val['{{ $fField }}'];
                                                    this.open = Array.isArray(res) && res.length > 0;
                                                });
                                            }
                                        }"
                                        @click.outside="open = false"
                                        class="relative"
                                    >
                                        <div class="relative flex items-center">
                                            <input type="text"
                                                x-model="displayVal"
                                                wire:keyup.debounce.300ms="searchDropdown('{{ $fField }}', $event.target.value)"
                                                @focus="$wire.openDropdown('{{ $fField }}')"
                                                placeholder="{{ __('ptah::ui.search_entity', ['label' => $fLabel]) }}"
                                                autocomplete="off"
                                                class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 pr-9 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                            />
                                            <button type="button"
                                                tabindex="-1"
                                                @mousedown.prevent="open = !open; if (open) $wire.openDropdown('{{ $fField }}')"
                                                class="absolute right-2.5 text-gray-400 hover:text-gray-600 transition-transform duration-200"
                                                :class="open ? 'rotate-180' : ''">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <input type="hidden" wire:model="formData.{{ $fField }}" />
                                        <div x-show="open" x-cloak
                                            class="absolute z-30 w-full mt-1 overflow-y-auto rounded-lg shadow-xl max-h-48 ptah-c-dd">
                                            @forelse ($sdResults[$fField] ?? [] as $opt)
                                                <button type="button"
                                                    wire:click="selectDropdownOption('{{ $fField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                    @click="open = false"
                                                    class="block w-full px-4 py-2 text-sm text-left ptah-c-dd_opt">
                                                    {{ $opt['label'] }}
                                                </button>
                                            @empty
                                                <p class="px-4 py-3 text-xs italic text-gray-400">{{ __('ptah::ui.no_results') }}</p>
                                            @endforelse
                                        </div>
                                    </div>
                                    @if ($fError)
                                        <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                    @endif
                                </div>

                            @elseif ($fTipo === 'image')
                                {{-- ── Image input com preview ── --}}
                                <div class="w-full"
                                    x-data="{
                                        previewUrl: {{ json_encode($fValue ?: '') }},
                                        updateFromUrl(val) { this.previewUrl = val; },
                                        handleFile(e) {
                                            const file = e.target.files[0];
                                            if (!file) return;
                                            const reader = new FileReader();
                                            reader.onload = (ev) => { this.previewUrl = ev.target.result; };
                                            reader.readAsDataURL(file);
                                        }
                                    }">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </label>
                                    <input type="text"
                                        wire:model.live="formData.{{ $fField }}"
                                        @input="updateFromUrl($event.target.value)"
                                        placeholder="https://..."
                                        class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                    />
                                    <div class="mt-2">
                                        <label class="cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 transition-colors">
                                            {{ __('ptah::ui.image_pick_file') }}
                                            <input type="file" accept="image/*" class="hidden" @change="handleFile($event)" />
                                        </label>
                                    </div>
                                    <div x-show="previewUrl" x-cloak class="mt-3">
                                        <img :src="previewUrl" alt="{{ __('ptah::ui.image_preview_label') }}"
                                             class="max-h-48 rounded-xl border border-slate-200 object-contain bg-slate-50 shadow-sm"
                                             @@error="previewUrl = ''" />
                                    </div>
                                    @if ($fError)<p class="mt-1 text-xs text-red-500">{{ $fError }}</p>@endif
                                </div>

                            @else
                                {{-- ── Input inline (text / number / date / masked) ── --}}
                                @php
                                    $fInputType = match($fTipo) {
                                        'date'   => 'date',
                                        'number' => 'number',
                                        default  => 'text',
                                    };
                                    // Masked inputs must always be type=text
                                    if ($fMask) $fInputType = 'text';
                                @endphp

                                @if($fMask === 'money_brl')
                                    {{-- ── Money BRL input with Alpine real-time formatting ── --}}
                                    @php
                                        $moneyInit = is_numeric($fValue) ? (float) $fValue : 0.0;
                                    @endphp
                                    <div class="w-full"
                                        x-data="{
                                            display: '',
                                            init() {
                                                this.display = this.fmt({{ $moneyInit }});
                                            },
                                            fmt(n) {
                                                const val = parseFloat(n) || 0;
                                                return 'R\$ ' + val.toFixed(2)
                                                    .replace('.', ',')
                                                    .replace(/(\d)(?=(\d{3})+(?!\d))/g, '\$1.');
                                            },
                                            onInput(e) {
                                                const digits = e.target.value.replace(/\D/g, '');
                                                const n = parseInt(digits || '0', 10) / 100;
                                                const formatted = this.fmt(n);
                                                e.target.value = formatted;
                                                this.display = formatted;
                                                this.\$refs.moneyHidden.value = formatted;
                                                this.\$refs.moneyHidden.dispatchEvent(new Event('change', { bubbles: true }));
                                            }
                                        }"
                                        x-init="init()"
                                    >
                                        <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            type="text"
                                            :value="display"
                                            @input.prevent="onInput(\$event)"
                                            @focus="\$event.target.setSelectionRange(\$event.target.value.length, \$event.target.value.length)"
                                            @if($fRequired) required @endif
                                            placeholder="R$ 0,00"
                                            class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                        />
                                        <input
                                            type="hidden"
                                            x-ref="moneyHidden"
                                            name="{{ $fField }}"
                                            wire:model="formData.{{ $fField }}"
                                        />
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @else
                                    {{-- ── Regular input (text / number / date) ── --}}
                                    <div class="w-full">
                                        <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            type="{{ $fInputType }}"
                                            name="{{ $fField }}"
                                            wire:model="formData.{{ $fField }}"
                                            @if($fRequired) required @endif
                                            @if($fTipo === 'number' && !$fMask) step="any" @endif
                                            placeholder=""
                                            class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                        />
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>
                                @endif
                            @endif

                        </div>
                    @endforeach

                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t ptah-c-modal_ft">
                <x-forge-button @click="$wire.showModal = false; $wire.closeModal()" color="dark" flat :disabled="$creating">
                    {{ __('ptah::ui.btn_cancel') }}
                </x-forge-button>
                <x-forge-button wire:click="save" color="primary" :loading="$creating" :disabled="$creating">
                    {{ $editingId ? __('ptah::ui.btn_save_changes') : __('ptah::ui.btn_create') }}
                </x-forge-button>
            </div>

        </div>
    </div>
@endteleport

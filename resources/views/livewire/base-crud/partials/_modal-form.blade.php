{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Criar / Editar ─────────────────────────────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: @entangle('showModal') }" @close="$wire.closeModal()">
    <x-forge-modal
        :title="($editingId ? __('ptah::ui.modal_edit_prefix') : __('ptah::ui.modal_new_prefix')) . ' ' . $crudTitle"
        :subtitle="$editingId ? __('ptah::ui.modal_edit_subtitle') : __('ptah::ui.modal_create_subtitle')"
        size="2xl"
    >
        {{-- Erro geral --}}
        @if (!empty($formErrors['_general']))
            <div class="mb-4">
                <x-forge-alert type="danger">{{ $formErrors['_general'] }}</x-forge-alert>
            </div>
        @endif

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
                            $fHelpText = $col['colsHelpText'] ?? null;

            $fBorderClass  = $fError
                                ? 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-200 dark:focus:ring-red-300'
                                : 'border-slate-200 dark:border-slate-600 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-500/30';
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
                                    $fBorderNormal = $fError ? 'border-red-400 dark:border-red-500' : 'border-slate-200 dark:border-slate-600';
                                    $fBorderOpen   = $fError ? 'border-red-500' : 'border-blue-600 dark:border-blue-400';
                                    $fRingOpen     = $fError ? 'ring-2 ring-red-200 dark:ring-red-300' : 'ring-2 ring-blue-100/50 dark:ring-blue-500/30';
                                @endphp
                                <div class="w-full">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </label>
                                    <div
                                        wire:key="ptah-select-{{ $fField }}-{{ $editingId ?? 'new' }}"
                                        x-data="{
                                            open: false,
                                            selected: {{ $fInitSel }},
                                            options: @js($fOptions),
                                            placeholder: @js(__('ptah::ui.select_placeholder')),
                                            init() {
                                                this.$wire.$watch('formData.{{ $fField }}', (val) => {
                                                    if (val !== null && val !== undefined) {
                                                        this.selected = typeof val === 'boolean' 
                                                            ? (val ? '1' : '0') 
                                                            : String(val);
                                                    } else {
                                                        this.selected = null;
                                                    }
                                                });
                                            },
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
                                            class="relative flex items-center justify-between rounded-md border px-3 py-2.5 text-sm select-none transition-colors duration-150 bg-white dark:bg-slate-700 dark:text-white ptah-c-form_sel"
                                        >
                                            <span
                                                :class="(selected !== null && selected !== '') ? 'ptah-c-sel_val' : 'text-gray-400'"
                                                class="pr-4 truncate"
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
                                            class="absolute z-20 w-full mt-1 overflow-auto border rounded-md max-h-48 bg-white dark:bg-slate-700 border-slate-200 dark:border-slate-600 ptah-c-dd">
                                            <ul class="py-1">
                                                <template x-for="option in options" :key="option.value">
                                                    <li
                                                        @click="toggle(option.value)"
                                                        :class="isSelected(option.value) ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item'"
                                                        class="flex items-center justify-between px-4 py-2 text-sm cursor-pointer"
                                                    >
                                                        <span x-text="option.label"></span>
                                                        <svg x-show="isSelected(option.value)" class="w-4 h-4 ml-2 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
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
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 ptah-c-form_lbl">
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
                                                class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 pr-9 text-sm transition-colors duration-150 focus:ring-2 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-slate-400 ptah-c-form_in"
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
                                            class="absolute z-30 w-full mt-1 overflow-y-auto rounded-md max-h-48 ptah-c-dd">
                                            @forelse ($sdResults[$fField] ?? [] as $opt)
                                                <button type="button"
                                                    wire:click="selectDropdownOption('{{ $fField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                    @click="open = false"
                                                    class="block w-full px-4 py-2 text-sm text-left hover:bg-slate-50 dark:hover:bg-slate-600 dark:text-white ptah-c-dd_opt">
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
                                {{-- ── Image input com upload real + preview ── --}}
                                @php
                                    // Resolve preview URL for existing stored images
                                    $fPreviewUrl = '';
                                    if ($fValue) {
                                        $v = (string) $fValue;
                                        if (str_starts_with($v, 'http') || str_starts_with($v, 'data:') || str_starts_with($v, '/')) {
                                            $fPreviewUrl = $v;
                                        } else {
                                            $fPreviewUrl = asset('storage/' . ltrim($v, '/'));
                                        }
                                    }
                                    // Build accept attribute from colsUploadAllowedTypes
                                    $fAllowedTypes = $col['colsUploadAllowedTypes'] ?? null;
                                    $fAccept = 'image/*';
                                    if ($fAllowedTypes) {
                                        $fTypes  = is_array($fAllowedTypes) ? $fAllowedTypes : array_map('trim', explode(',', $fAllowedTypes));
                                        $fAccept = implode(',', array_map(fn($t) => '.' . $t, $fTypes));
                                    }
                                @endphp
                                <div class="w-full"
                                    wire:key="ptah-image-{{ $fField }}-{{ $editingId ?? 'new' }}"
                                    x-data="{
                                        previewUrl: {{ json_encode($fPreviewUrl) }},
                                        savedUrl:   {{ json_encode($fPreviewUrl) }},
                                        hasFile: false,
                                        updateFromUrl(val) {
                                            this.previewUrl = val;
                                            this.hasFile = false;
                                        },
                                        handleFile(e) {
                                            const file = e.target.files[0];
                                            if (!file) return;
                                            this.hasFile = true;
                                            const reader = new FileReader();
                                            reader.onload = (ev) => { this.previewUrl = ev.target.result; };
                                            reader.readAsDataURL(file);
                                        },
                                        clearFile() {
                                            this.$refs.fileInput.value = '';
                                            this.hasFile = false;
                                            this.previewUrl = this.savedUrl;
                                        }
                                    }">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </label>

                                    {{-- File upload button --}}
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                            </svg>
                                            {{ __('ptah::ui.image_pick_file') }}
                                            <input
                                                type="file"
                                                x-ref="fileInput"
                                                accept="{{ $fAccept }}"
                                                class="hidden"
                                                wire:model="imageUploads.{{ $fField }}"
                                                @change="handleFile($event)"
                                            />
                                        </label>
                                        <span
                                            wire:loading
                                            wire:target="imageUploads.{{ $fField }}"
                                            class="text-xs text-slate-500 dark:text-slate-400 animate-pulse">
                                            {{ __('ptah::ui.image_uploading') }}
                                        </span>
                                        <button x-show="hasFile" x-cloak
                                            type="button"
                                            @click="clearFile()"
                                            class="text-xs text-red-500 hover:text-red-700 transition-colors">
                                            {{ __('ptah::ui.image_remove_file') }}
                                        </button>
                                    </div>

                                    {{-- URL fallback --}}
                                    <div class="mt-2">
                                        <p class="mb-1 text-xs text-slate-400 dark:text-slate-500">{{ __('ptah::ui.image_or_url') }}</p>
                                        <input type="text"
                                            wire:model.live="formData.{{ $fField }}"
                                            @input="updateFromUrl($event.target.value)"
                                            placeholder="https://..."
                                            class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-slate-400 ptah-c-form_in"
                                        />
                                    </div>

                                    {{-- Preview --}}
                                    <div x-show="previewUrl" x-cloak class="mt-3">
                                        <img :src="previewUrl" alt="{{ __('ptah::ui.image_preview_label') }}"
                                             class="max-h-48 rounded-md border border-slate-200 dark:border-slate-600 object-contain bg-slate-50 dark:bg-slate-800"
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
                                    {{-- ── Money BRL: Alpine inline mask ── --}}
                                    {{-- wire:key on the outer div forces full destroy+recreate on create↔edit switch --}}
                                    <div
                                        class="w-full"
                                        wire:key="ptah-money-{{ $fField }}-{{ $editingId ?? 'new' }}"
                                        x-data="{
                                            display: '',
                                            fmt(n) {
                                                const v = parseFloat(n) || 0;
                                                return 'R$ ' + v.toFixed(2)
                                                    .replace('.', ',')
                                                    .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                                            },
                                            init() {
                                                // Read directly from Livewire state (not PHP-rendered) so
                                                // create→edit never carries stale display value.
                                                const raw = this.$wire.formData?.['{{ $fField }}'] ?? 0;
                                                this.display = this.fmt(parseFloat(raw) || 0);
                                                // Keep in sync when openEdit() sets formData from server
                                                this.$wire.$watch('formData.{{ $fField }}', (val) => {
                                                    if (val === null || val === undefined) return;
                                                    // Ignore values already formatted (contain letters/symbols)
                                                    if (/[a-zA-Z$]/.test(String(val))) return;
                                                    this.display = this.fmt(parseFloat(val) || 0);
                                                });
                                            },
                                            onInput(e) {
                                                const digits = e.target.value.replace(/\D/g, '');
                                                const n = parseInt(digits || '0', 10) / 100;
                                                const f = this.fmt(n);
                                                e.target.value = f;
                                                this.display = f;
                                                const h = this.$refs.moneyHidden;
                                                h.value = f;
                                                // wire:model (deferred) syncs on save action in Livewire 4;
                                                // dispatch 'input' to keep hidden input in sync during interaction
                                                h.dispatchEvent(new Event('input', { bubbles: true }));
                                            }
                                        }"
                                    >
                                        <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 ptah-c-form_lbl">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            type="text"
                                            x-bind:value="display"
                                            @input="onInput($event)"
                                            @focus="$event.target.setSelectionRange($event.target.value.length, $event.target.value.length)"
                                            @if($fRequired) required @endif
                                            placeholder="R$ 0,00"
                                            class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-slate-400 ptah-c-form_in"
                                        />
                                        <input
                                            type="hidden"
                                            x-ref="moneyHidden"
                                            wire:model="formData.{{ $fField }}"
                                        />
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @elseif($fMask === 'uppercase')
                                    {{-- ── Uppercase: live text-transform + wire:ignore ── --}}
                                    <div class="w-full" wire:ignore>
                                        <div
                                            x-data="{
                                                value: '{{ addslashes((string)$fValue) }}',
                                                onInput(e) {
                                                    this.value = e.target.value.toUpperCase();
                                                    e.target.value  = this.value;
                                                    this.$refs.upHidden.value = this.value;
                                                    this.$refs.upHidden.dispatchEvent(new Event('change', { bubbles: true }));
                                                }
                                            }"
                                        >
                                            <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 ptah-c-form_lbl">
                                                {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                            </label>
                                            <input
                                                type="text"
                                                x-bind:value="value"
                                                @input="onInput($event)"
                                                style="text-transform: uppercase"
                                                @if($fRequired) required @endif
                                                placeholder=""
                                                class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-slate-400 ptah-c-form_in"
                                            />
                                            <input type="hidden" x-ref="upHidden" wire:model="formData.{{ $fField }}" />
                                        </div>
                                        @if ($fError)
                                            <p class="mt-1 text-xs text-red-500">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @else
                                    {{-- ── Regular input (text / number / date / other masks via server-side transform) ── --}}
                                    <x-forge-input
                                        :type="$fInputType"
                                        :label="$fLabel"
                                        wire:model="formData.{{ $fField }}"
                                        :required="$fRequired"
                                        :error="$fError"
                                        :step="($fTipo === 'number' && !$fMask) ? 'any' : null"
                                    />
                                @endif
                            @endif

                            @if (!empty($fHelpText))
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 ptah-c-form_hint">{{ $fHelpText }}</p>
                            @endif

                        </div>
                    @endforeach

        </div>

        <x-slot name="footer">
            <x-forge-button @click="open = false; $wire.closeModal()" color="dark" flat :disabled="$creating">
                {{ __('ptah::ui.btn_cancel') }}
            </x-forge-button>
            <x-forge-button wire:click="save" color="primary" :loading="$creating" :disabled="$creating">
                {{ $editingId ? __('ptah::ui.btn_save_changes') : __('ptah::ui.btn_create') }}
            </x-forge-button>
        </x-slot>
    </x-forge-modal>
</div>

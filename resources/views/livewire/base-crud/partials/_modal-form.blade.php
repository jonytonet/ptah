{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Criar / Editar ─────────────────────────────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div
    x-data="{
        open: @entangle('showModal'),
        _dirty: false,
        _confirmDiscard: false,
        _markDirty() { this._dirty = true; },
        closeModal() { this._tryClose(); },
        _tryClose() {
            if (this._dirty) {
                this._confirmDiscard = true;

                return;
            }
            this._forceClose();
        },
        _forceClose() {
            this._confirmDiscard = false;
            this._dirty = false;
            this.open = false;
            $wire.closeModal();
        },
        _focusFirst() {
            this._dirty = false;
            $nextTick(() => {
                const f = $el.querySelector(
                    'input:not([type=hidden]):not([type=checkbox]):not([readonly]), textarea, select'
                );
                if (f) f.focus();
            });
        }
    }"
    x-effect="
        if (open) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
            _dirty = false;
            _confirmDiscard = false;
        }
    "
    @ptah:form-ready.window="_focusFirst()"
    @keydown.escape.window="
        if (_confirmDiscard) { _confirmDiscard = false; }
        else if (open) { _tryClose(); }
    "
>
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

        <div class="flex flex-col gap-4" wire:key="crud-form-fields-{{ $formInstanceKey }}" @input="_markDirty()" @change="_markDirty()">

                    @php $prevFormBlock = null; @endphp
                    @foreach ($formCols as $col)
                        @php
                            // ── Form blocks (sections): heading whenever colsFormBlock changes ──
                            $fBlock = trim((string) ($col['colsFormBlock'] ?? ''));
                        @endphp
                        @if ($fBlock !== '' && $fBlock !== $prevFormBlock)
                            <div class="flex items-center gap-3 {{ $loop->first ? '' : 'mt-3' }}">
                                <span class="text-[11px] font-semibold uppercase tracking-wider ptah-c-form_block_lbl">{{ $fBlock }}</span>
                                <div class="flex-1 h-px ptah-c-form_block_sep"></div>
                            </div>
                        @endif
                        @php $prevFormBlock = $fBlock; @endphp
                        @php
                            $fField    = $col['colsNomeFisico'];
                            $fLabel    = $col['colsNomeLogico'] ?? $fField;
                            $fTipo     = $col['colsTipo'] ?? 'text';

                            // colsTipo 'boolean' não tem controle próprio: reusa o select inline
                            // (já normaliza bool PHP) com opções Sim/Não. colsSelect só é aproveitado
                            // se for array — o editor pode tê-lo deixado como string "label;value;;…".
                            if ($fTipo === 'boolean') {
                                if (! is_array($col['colsSelect'] ?? null) || $col['colsSelect'] === []) {
                                    $col['colsSelect'] = [__('ptah::ui.bool_yes') => '1', __('ptah::ui.bool_no') => '0'];
                                }
                                $fTipo = 'select';
                            }

                            $fRequired = in_array($col['colsRequired'] ?? false, [true, 'S', 1, '1'], true);
                            $fError    = $formErrors[$fField] ?? null;
                            $fMask     = $col['colsMask'] ?? null;
                            // Resolvido do registro (PtahMask). null quando o nome nao
                            // esta registrado — e ai o campo cai no input comum, que e
                            // exatamente o que "cpf" e companhia sempre fizeram.
                            $fMaskDef  = \Ptah\Support\PtahMask::get($fMask);
                            $fValue    = $formData[$fField] ?? '';
                            $fHelpText = $col['colsHelpText'] ?? null;
                            $tabIdx    = 0; // natural DOM order — a positive tabindex jumped fields ahead of the footer/close

            // A borda destes campos vem inteira de `.ptah-c-form_in` em
                            // ptah-components.css: repouso, `:focus` e
                            // `[aria-invalid="true"]`, nos dois escopos. Este helper
                            // carregava as mesmas tres coisas em utilitarios Tailwind,
                            // que NUNCA aplicaram — regra sem layer vence utilitario
                            // com layer, e a cor computada foi medida identica com e
                            // sem eles. Mantido como string vazia para nao mexer nas
                            // quatro interpolacoes abaixo.
                            $fBorderClass = '';
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
                                    // Repouso e erro vem de `.ptah-c-form_sel` /
                                    // `.ptah-c-form_sel[aria-invalid]` — os utilitarios
                                    // de paleta fixa que ficavam aqui eram inertes pelo
                                    // mesmo motivo do $fBorderClass acima.
                                    $fBorderNormal = '';
                                    // O estado ABERTO nao tem regra em CSS, entao segue
                                    // no Blade; `primary` e `danger` sao tokens de acento,
                                    // nao paleta fixa.
                                    $fBorderOpen   = $fError ? 'border-danger' : 'border-primary dark:border-primary';
                                    $fRingOpen     = $fError ? 'ring-2 ring-red-200 dark:ring-red-300' : 'ring-2 ring-primary/15 dark:ring-primary/30';
                                @endphp
                                <div class="w-full">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
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
                                            tabindex="{{ $tabIdx }}"
                                            role="combobox"
                                            aria-haspopup="listbox"
                                            :aria-expanded="open"
                                            @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                            @keydown.space.prevent @keydown.enter.prevent="open = !open"
                                            @click="open = !open"
                                            :class="open ? '{{ $fBorderOpen }} {{ $fRingOpen }}' : '{{ $fBorderNormal }}'"
                                            class="relative flex items-center justify-between rounded-md border px-3 py-2.5 text-sm select-none transition-colors duration-150 ptah-c-form_sel"
                                        >
                                            <span
                                                :class="(selected !== null && selected !== '') ? 'ptah-c-sel_val' : 'ptah-c-fp_muted'"
                                                class="pr-4 truncate"
                                                x-text="displayLabel"
                                            ></span>
                                            <span class="absolute ptah-c-fp_chevron transition-transform duration-200 -translate-y-1/2 right-3 top-1/2" :class="open ? 'rotate-180' : ''">
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
                                            class="absolute z-20 w-full mt-1 overflow-auto border rounded-md max-h-48 ptah-c-dd">
                                            <ul class="py-1">
                                                <template x-for="option in options" :key="option.value">
                                                    <li
                                                        @click="toggle(option.value)"
                                                        :class="isSelected(option.value) ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item'"
                                                        class="flex items-center justify-between px-4 py-2 text-sm cursor-pointer"
                                                    >
                                                        <span x-text="option.label"></span>
                                                        <svg x-show="isSelected(option.value)" class="w-4 h-4 ml-2 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>
                                    @if ($fError)
                                        <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
                                    @endif
                                </div>

                            @elseif ($fTipo === 'searchdropdown')
                                {{-- ── SearchDropdown inline ── --}}
                                @php
                                    $sdInitLabel  = $sdLabels[$fField] ?? '';
                                    $sdHasResults = !empty($sdResults[$fField]);
                                    $sdSettings   = $this->sdSettings($col);
                                    // Cascading dropdown: locked until the parent field has a value.
                                    $sdDependsOn   = $col['colsSDDependsOn'] ?? null;
                                    $sdParentEmpty = $sdDependsOn && (($formData[$sdDependsOn] ?? null) === null || ($formData[$sdDependsOn] ?? '') === '');
                                    $sdParentLabel = $sdDependsOn
                                        ? ((collect($crudConfig['cols'] ?? [])->firstWhere('colsNomeFisico', $sdDependsOn)['colsNomeLogico'] ?? null) ?: $sdDependsOn)
                                        : null;
                                @endphp
                                <div class="w-full">
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
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
                                                placeholder="{{ $sdParentEmpty
                                                    ? __('ptah::ui.sd_select_parent_first', ['parent' => $sdParentLabel])
                                                    : ($sdSettings['placeholder'] ?: __('ptah::ui.search_entity', ['label' => $fLabel])) }}"
                                                autocomplete="off"
                                                tabindex="{{ $tabIdx }}"
                                                @if ($sdParentEmpty) disabled @endif
                                                @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                                class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 pr-9 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in disabled:opacity-60 disabled:cursor-not-allowed"
                                            />
                                            <button type="button"
                                                tabindex="-1"
                                                @if ($sdParentEmpty) disabled @endif
                                                @mousedown.prevent="open = !open; if (open) $wire.openDropdown('{{ $fField }}')"
                                                class="absolute right-2.5 ptah-c-fp_chevron transition-transform duration-200 disabled:opacity-40 disabled:pointer-events-none"
                                                :class="open ? 'rotate-180' : ''">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <input type="hidden" wire:model="formData.{{ $fField }}" />
                                        <div x-show="open" x-cloak
                                            class="absolute z-30 w-full {{ $sdSettings['startList'] === 'top' ? 'bottom-full mb-1' : 'mt-1' }} overflow-y-auto rounded-md max-h-48 ptah-c-dd">
                                            @forelse ($sdResults[$fField] ?? [] as $opt)
                                                <button type="button"
                                                    wire:click="selectDropdownOption('{{ $fField }}', '{{ $opt['value'] }}', '{{ addslashes($opt['label']) }}')"
                                                    @click="open = false"
                                                    class="block w-full px-4 py-2 text-sm text-left ptah-c-dd_opt">
                                                    {{ $opt['label'] }}
                                                    @if (array_key_exists('labelTwo', $opt))
                                                        <span class="block text-[11px] opacity-70">{{ $opt['labelTwo'] }}</span>
                                                    @endif
                                                    @if (array_key_exists('labelThree', $opt))
                                                        <span class="block text-[11px] opacity-70">{{ $opt['labelThree'] }}</span>
                                                    @endif
                                                </button>
                                            @empty
                                                <p class="px-4 py-3 text-xs italic ptah-c-fp_muted">{{ __('ptah::ui.no_results') }}</p>
                                            @endforelse
                                        </div>
                                    </div>
                                    @if ($fError)
                                        <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
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
                                    <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                    </label>

                                    {{-- File upload button --}}
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-md border px-3 py-2 text-sm transition-colors ptah-c-btn">
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
                                            class="text-xs animate-pulse ptah-c-form_hint">
                                            {{ __('ptah::ui.image_uploading') }}
                                        </span>
                                        <button x-show="hasFile" x-cloak
                                            type="button"
                                            @click="clearFile()"
                                            class="text-xs transition-colors ptah-c-field_err hover:opacity-80">
                                            {{ __('ptah::ui.image_remove_file') }}
                                        </button>
                                    </div>

                                    {{-- URL fallback --}}
                                    <div class="mt-2">
                                        <p class="mb-1 text-xs ptah-c-form_hint">{{ __('ptah::ui.image_or_url') }}</p>
                                        <input type="text"
                                            wire:model.live="formData.{{ $fField }}"
                                            @input="updateFromUrl($event.target.value)"
                                            placeholder="https://..."
                                            tabindex="{{ $tabIdx }}"
                                            @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                            class="block w-full rounded-lg border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                        />
                                    </div>

                                    {{-- Preview --}}
                                    <div x-show="previewUrl" x-cloak class="mt-3">
                                        <img :src="previewUrl" alt="{{ __('ptah::ui.image_preview_label') }}"
                                             class="max-h-48 rounded-md border object-contain ptah-c-img_preview"
                                             @@error="previewUrl = ''" />
                                    </div>

                                    @if ($fError)<p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>@endif
                                </div>

                            @else
                                {{-- ── Input inline (text / number / date / masked) ── --}}
                                @php
                                    // `datetime` estava nesta lista de tipos desde
                                    // sempre e caia no default `text`: um campo
                                    // configurado como datetime nunca teve o
                                    // controle nativo. Vai junto com os novos.
                                    $fInputType = match($fTipo) {
                                        'date'            => 'date',
                                        'datetime',
                                        'datetime-local'  => 'datetime-local',
                                        'time'            => 'time',
                                        'number'          => 'number',
                                        'range'           => 'range',
                                        'email'           => 'email',
                                        'url'             => 'url',
                                        'tel'             => 'tel',
                                        default           => 'text',
                                    };
                                    // Masked inputs must always be type=text
                                    if ($fMask) $fInputType = 'text';
                                @endphp

                                @if($fTipo === 'color')
                                    {{-- ── Color: seletor nativo + hex editavel ──
                                         O renderer `color` da LISTAGEM ja existia; no
                                         form a pessoa digitava o hex na mao.

                                         Os dois controles, e nao so o nativo, porque o
                                         `<input type="color">` e um quadradinho que nao
                                         diz QUAL cor esta ali — e num cadastro de Tags o
                                         hex e o dado, ele vai para o CSS. Um campo de
                                         texto ao lado mostra e aceita o valor, e quem
                                         tem o hex na mao (copiado do design) cola em vez
                                         de caçar no seletor.

                                         `wire:key` no envelope pelo mesmo motivo do
                                         money: create<->edit precisa destruir e recriar,
                                         senao o Alpine carrega o display antigo. --}}
                                    <div
                                        class="w-full"
                                        wire:key="ptah-color-{{ $fField }}-{{ $editingId ?? 'new' }}"
                                        x-data="{
                                            hex: '',

                                            /* O seletor nativo EXIGE um valor valido, senao
                                               desenha preto — e preto parece uma escolha.
                                               Quando o campo esta vazio ele mostra um cinza
                                               neutro que NAO e gravado: so uma interacao
                                               real escreve no modelo. */
                                            get swatch() {
                                                return this.normalize(this.hex) ?? '#9ca3af';
                                            },

                                            normalize(v) {
                                                let s = String(v ?? '').trim();
                                                if (s === '') return null;
                                                if (s[0] !== '#') s = '#' + s;
                                                /* #abc -> #aabbcc: o seletor nativo so aceita
                                                   a forma de seis digitos. */
                                                if (/^#[0-9a-fA-F]{3}$/.test(s)) {
                                                    s = '#' + s[1] + s[1] + s[2] + s[2] + s[3] + s[3];
                                                }
                                                return /^#[0-9a-fA-F]{6}$/.test(s) ? s.toLowerCase() : null;
                                            },

                                            init() {
                                                const raw = this.$wire.formData?.['{{ $fField }}'] ?? '';
                                                this.hex = this.normalize(raw) ?? String(raw ?? '');

                                                this.$wire.$watch('formData.{{ $fField }}', (val) => {
                                                    if (val === null || val === undefined) return;
                                                    this.hex = this.normalize(val) ?? String(val);
                                                });
                                            },

                                            /* Escreve o valor CRU enquanto se digita, sem
                                               normalizar: normalizar a cada tecla impede
                                               chegar ao terceiro caractere de `#abc`. */
                                            onText(e) {
                                                this.hex = e.target.value;
                                                this.push(this.hex);
                                            },

                                            /* Ao sair do campo, normaliza o que der. O que
                                               nao for cor fica como a pessoa digitou: o
                                               servidor e quem recusa, e apagar em silencio
                                               o que ela escreveu e pior que mostrar o erro. */
                                            onTextBlur() {
                                                const n = this.normalize(this.hex);
                                                if (n !== null) {
                                                    this.hex = n;
                                                    this.push(n);
                                                }
                                            },

                                            onPick(e) {
                                                this.hex = e.target.value.toLowerCase();
                                                this.push(this.hex);
                                            },

                                            clear() {
                                                this.hex = '';
                                                this.push('');
                                            },

                                            push(v) {
                                                const h = this.$refs.colorHidden;
                                                h.value = v;
                                                h.dispatchEvent(new Event('input', { bubbles: true }));
                                            }
                                        }"
                                    >
                                        <label for="ptah-color-in-{{ $fField }}" class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                            {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                        </label>

                                        <div class="flex items-stretch gap-2">
                                            {{-- O seletor. `aria-label` proprio: o <label>
                                                 acima aponta para o campo de texto, e um
                                                 controle sem nome e invisivel para leitor
                                                 de tela. --}}
                                            <input
                                                type="color"
                                                class="ptah-c-color_pick h-[42px] w-12 shrink-0 cursor-pointer rounded-md border p-1"
                                                :value="swatch"
                                                @input="onPick($event)"
                                                tabindex="{{ $tabIdx }}"
                                                aria-label="{{ __('ptah::ui.crud_color_pick', ['field' => $fLabel]) }}"
                                            />

                                            <input
                                                id="ptah-color-in-{{ $fField }}"
                                                type="text"
                                                x-bind:value="hex"
                                                @input="onText($event)"
                                                @blur="onTextBlur()"
                                                @if($fRequired) required @endif
                                                tabindex="{{ $tabIdx }}"
                                                placeholder="#3b82f6"
                                                spellcheck="false"
                                                autocomplete="off"
                                                inputmode="text"
                                                maxlength="7"
                                                @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                                class="block w-full rounded-md border outline-none px-3 py-2.5 text-sm font-mono transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                            />

                                            @if(! $fRequired)
                                                <button
                                                    type="button"
                                                    @click="clear()"
                                                    x-show="hex !== ''"
                                                    x-cloak
                                                    class="ptah-c-color_clear shrink-0 rounded-md border px-2"
                                                    title="{{ __('ptah::ui.crud_color_clear') }}"
                                                    aria-label="{{ __('ptah::ui.crud_color_clear') }}"
                                                >
                                                    <i class="bx bx-x text-lg leading-none"></i>
                                                </button>
                                            @endif
                                        </div>

                                        <input type="hidden" x-ref="colorHidden" wire:model="formData.{{ $fField }}" />

                                        @if ($fError)
                                            <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @elseif($fMask === 'money_brl')
                                    {{-- ── Money BRL: Alpine inline mask ── --}}
                                    {{-- wire:key on the outer div forces full destroy+recreate on create↔edit switch --}}
                                    <div
                                        class="w-full"
                                        wire:key="ptah-money-{{ $fField }}-{{ $editingId ?? 'new' }}"
                                        x-on:ptah-typed-restored="display = $event.detail.value; if ($refs.moneyVisible) $refs.moneyVisible.value = display"
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
                                                window.ptahKeepTyped?.(this.$wire.$id, '{{ $fField }}', f);
                                            }
                                        }"
                                    >
                                        <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                            {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            type="text"
                                            x-bind:value="display"
                                            @input="onInput($event)"
                                            @focus="$event.target.setSelectionRange($event.target.value.length, $event.target.value.length)"
                                            @if($fRequired) required @endif
                                            tabindex="{{ $tabIdx }}"
                                            placeholder="R$ 0,00"
                                            x-ref="moneyVisible"
                                            @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                            class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                        />
                                        <input
                                            type="hidden"
                                            x-ref="moneyHidden"
                                            data-ptah-keep="{{ $this->getId() }}|{{ $fField }}"
                                            wire:model="formData.{{ $fField }}"
                                        />
                                        @if ($fError)
                                            <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
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
                                            <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                                {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                            </label>
                                            <input
                                                type="text"
                                                x-bind:value="value"
                                                @input="onInput($event)"
                                                style="text-transform: uppercase"
                                                @if($fRequired) required @endif
                                                tabindex="{{ $tabIdx }}"
                                                placeholder=""
                                                @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                                class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                            />
                                            <input type="hidden" x-ref="upHidden" wire:model="formData.{{ $fField }}" />
                                        </div>
                                        @if ($fError)
                                            <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @elseif($fMaskDef !== null && $fMaskDef['patterns'] !== [])
                                    {{-- ── Campo com mascara registrada ──
                                         O pattern vem do registro (PtahMask), que o HOST
                                         define. O pacote nao sabe o que e um CPF; ele sabe
                                         aplicar `000.000.000-00`.

                                         O valor exibido e formatado no SERVIDOR ao abrir
                                         (PtahMask::format), senao editar um registro cujo
                                         banco guarda so digitos abriria com onze numeros
                                         crus no campo.

                                         O que vai para o Livewire e o valor com mascara; a
                                         regra de gravacao (`store`) roda no save, do lado
                                         do servidor — o cliente pode ser contornado, e o
                                         que chega ao banco nao pode depender dele. --}}
                                    <div
                                        class="w-full"
                                        wire:key="ptah-mask-{{ $fField }}-{{ $editingId ?? 'new' }}"
                                        x-on:ptah-typed-restored="display = format($event.detail.value); if ($refs.maskVisible) $refs.maskVisible.value = display"
                                        x-data="{
                                            patterns: @js($fMaskDef['patterns']),
                                            display: @js(\Ptah\Support\PtahMask::format($fMask, $fValue)),

                                            fits(token, ch) {
                                                if (token === '0') return /^[0-9]$/.test(ch);
                                                if (token === 'A') return /^[A-Za-z]$/.test(ch);
                                                if (token === '*') return /^[A-Za-z0-9]$/.test(ch);
                                                return false;
                                            },

                                            isSlot(t) { return t === '0' || t === 'A' || t === '*'; },

                                            capacity(p) {
                                                return (p.match(/[0A*]/g) || []).length;
                                            },

                                            /* O MENOR pattern que ainda acomoda o que foi
                                               digitado — como em telefone de 8 e 9 digitos.
                                               So caracteres que preenchem slot contam: com a
                                               pontuacao na conta, colar '(11) 3322-4455'
                                               parece quinze caracteres e transborda um
                                               pattern de dez slots.
                                               `patterns` vem do servidor em ordem de maior
                                               capacidade primeiro, entao sobrescrever ate o
                                               fim termina no menor que serve. O gemeo em PHP
                                               (PtahMask::pick) errou as duas coisas e os
                                               testes pegaram; este e o mesmo algoritmo. */
                                            pick(value) {
                                                const tokens = (String(value ?? '').match(/[A-Za-z0-9]/g) || []).length;
                                                let chosen = this.patterns[0];
                                                for (const p of this.patterns) {
                                                    if (this.capacity(p) >= tokens) chosen = p;
                                                }
                                                return chosen;
                                            },

                                            /* Aplica o pattern ao valor CRU, pulando o que
                                               nao cabe no slot atual em vez de gastar o slot
                                               com ele: colar '(11) 98765-4321' aproveita os
                                               digitos e descarta a pontuacao.

                                               Espelha PtahMask::applyPattern de proposito. A
                                               versao anterior tinha um `tokensOf` proprio,
                                               com uma condicao que eu escrevi confuso, e ele
                                               perdia o ultimo digito ao colar valor ja
                                               formatado e embaralhava letra com numero. Os
                                               dois lados tinham de ser gemeos e nao eram; o
                                               probe pegou as tres. */
                                            apply(pattern, value) {
                                                const chars = Array.from(String(value ?? ''));
                                                let out = '';
                                                let i = 0;

                                                for (const t of pattern) {
                                                    if (!this.isSlot(t)) {
                                                        /* Literal so entra quando ha algo depois
                                                           dele; senao o campo termina com um
                                                           ponto solto enquanto se digita. */
                                                        if (i < chars.length) out += t;
                                                        continue;
                                                    }

                                                    while (i < chars.length && !this.fits(t, chars[i])) i++;
                                                    if (i >= chars.length) break;

                                                    out += chars[i];
                                                    i++;
                                                }

                                                return out;
                                            },

                                            format(value) {
                                                return this.apply(this.pick(value), value);
                                            },

                                            /* Reformatar reposiciona o cursor, e sem isto
                                               editar o meio de um CPF joga o cursor para o
                                               fim a cada tecla. Conta quantos caracteres
                                               UTEIS existem antes do cursor e recoloca depois
                                               do enesimo caractere util do texto formatado. */
                                            caretAfter(formatted, wantedTokens) {
                                                if (wantedTokens <= 0) return 0;
                                                let seen = 0;
                                                for (let i = 0; i < formatted.length; i++) {
                                                    if (/[A-Za-z0-9]/.test(formatted[i])) {
                                                        seen++;
                                                        if (seen === wantedTokens) return i + 1;
                                                    }
                                                }
                                                return formatted.length;
                                            },

                                            onInput(e) {
                                                const el = e.target;
                                                const before = el.value.slice(0, el.selectionStart ?? el.value.length);
                                                const tokensBefore = (before.match(/[A-Za-z0-9]/g) || []).length;

                                                const formatted = this.format(el.value);

                                                this.display = formatted;
                                                el.value = formatted;

                                                const pos = this.caretAfter(formatted, tokensBefore);
                                                el.setSelectionRange(pos, pos);

                                                this.push(formatted);
                                            },

                                            init() {
                                                this.$wire.$watch('formData.{{ $fField }}', (val) => {
                                                    if (val === null || val === undefined) return;
                                                    const f = this.format(val);
                                                    if (f !== this.display) this.display = f;
                                                });
                                            },

                                            push(v) {
                                                const h = this.$refs.maskHidden;
                                                h.value = v;
                                                h.dispatchEvent(new Event('input', { bubbles: true }));
                                                /* Sobrevive a resposta de uma requisicao que ja estava em voo (_scripts). */
                                                window.ptahKeepTyped?.(this.$wire.$id, '{{ $fField }}', v);
                                            }
                                        }"
                                    >
                                        <label for="ptah-mask-in-{{ $fField }}" class="block mb-1.5 text-xs font-semibold uppercase tracking-wide ptah-c-form_lbl">
                                            {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            id="ptah-mask-in-{{ $fField }}"
                                            x-ref="maskVisible"
                                            type="text"
                                            x-bind:value="display"
                                            @input="onInput($event)"
                                            @if($fRequired) required @endif
                                            tabindex="{{ $tabIdx }}"
                                            placeholder="{{ $fMaskDef['placeholder'] }}"
                                            inputmode="{{ $fMaskDef['inputmode'] }}"
                                            @if($fMaskDef['maxlength']) maxlength="{{ $fMaskDef['maxlength'] }}" @endif
                                            autocomplete="off"
                                            @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                            class="block w-full rounded-md border outline-none px-3 py-2.5 text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in"
                                        />
                                        <input type="hidden" x-ref="maskHidden" data-ptah-keep="{{ $this->getId() }}|{{ $fField }}" wire:model="formData.{{ $fField }}" />
                                        @if ($fError)
                                            <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
                                        @endif
                                    </div>

                                @else
                                    {{-- ── Regular input (text / number / date / sem mascara registrada) ── --}}
                                    @if (!empty($col['colsOnChange']))
                                        {{-- Trigger of a calculated-field formula: live binding so the recalc runs while typing --}}
                                        <x-forge-input
                                            :type="$fInputType"
                                            :label="$fLabel"
                                            wire:model.live.debounce.600ms="formData.{{ $fField }}"
                                            :required="$fRequired"
                                            :error="$fError"
                                            :step="($fTipo === 'number' && !$fMask) ? 'any' : null"
                                            :tabindex="$tabIdx"
                                        />
                                    @else
                                        <x-forge-input
                                            :type="$fInputType"
                                            :label="$fLabel"
                                            wire:model="formData.{{ $fField }}"
                                            :required="$fRequired"
                                            :error="$fError"
                                            :step="($fTipo === 'number' && !$fMask) ? 'any' : null"
                                            :tabindex="$tabIdx"
                                        />
                                    @endif
                                @endif
                            @endif

                            @if (!empty($fHelpText))
                                <p class="mt-1 text-xs ptah-c-form_hint">{{ $fHelpText }}</p>
                            @endif

                        </div>
                    @endforeach

        </div>

        <x-slot name="footer">
            @if ($editingId && $this->attachmentsEnabled())
                <x-forge-button wire:click="openAttachments({{ json_encode($editingId) }})" color="dark" flat>
                    {{ __('ptah::ui.btn_attachments') }} ({{ $this->attachmentCount($editingId) }})
                </x-forge-button>
            @endif
            @if ($editingId && $this->historyEnabled())
                <x-forge-button wire:click="openHistory({{ json_encode($editingId) }})" color="dark" flat class="mr-auto">
                    {{ __('ptah::ui.btn_history') }}
                </x-forge-button>
            @endif
            <x-forge-button @click="_tryClose()" color="dark" flat :disabled="$creating">
                {{ __('ptah::ui.btn_cancel') }}
            </x-forge-button>
            @if (! $editingId)
                <x-forge-button wire:click="saveAndNew" color="primary" flat :disabled="$creating">
                    {{ __('ptah::ui.btn_save_and_new') }}
                </x-forge-button>
            @endif
            <x-forge-button wire:click="save" color="primary" :loading="$creating" :disabled="$creating">
                {{ $editingId ? __('ptah::ui.btn_save_changes') : __('ptah::ui.btn_create') }}
            </x-forge-button>
        </x-slot>
    </x-forge-modal>

    {{-- Unsaved-changes confirmation (replaces the native confirm() dialog) --}}
    <div x-show="_confirmDiscard" x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="_confirmDiscard = false"></div>
        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative w-full max-w-sm rounded-lg shadow-2xl p-5 ptah-c-modal_card"
             role="alertdialog" aria-modal="true">
            <p class="text-sm font-semibold ptah-c-modal_ttl">
                {{ __('ptah::ui.modal_unsaved_title') }}
            </p>
            <p class="text-xs mt-1 ptah-c-modal_sub">
                {{ __('ptah::ui.modal_unsaved_confirm') }}
            </p>
            <div class="flex justify-end gap-2 mt-5">
                <button @click="_confirmDiscard = false"
                    class="px-4 py-2 text-sm font-semibold rounded-md ptah-c-discard_keep">
                    {{ __('ptah::ui.modal_unsaved_keep') }}
                </button>
                <button @click="_forceClose()"
                    class="px-4 py-2 text-sm font-semibold rounded-md ptah-c-ink_on_accent bg-danger-dark hover:opacity-90">
                    {{ __('ptah::ui.modal_unsaved_discard') }}
                </button>
            </div>
        </div>
    </div>
</div>

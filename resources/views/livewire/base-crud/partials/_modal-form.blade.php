{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Criar / Editar ─────────────────────────────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div
    x-data="{
        open: @entangle('showModal'),
        _dirty: false,
        _confirmDiscard: false,
        {{-- Mesmo aviso para fechar com alteracoes e para Descartar rascunho. --}}
        _confirmKind: 'close',
        {{-- Faixa Rascunho restaurado dentro do modal (1.61.0): o toast ficava no
             canto da tela, longe do formulario, e sumia sozinho. --}}
        _draftBanner: false,
        _markDirty() { this._dirty = true; },
        _askDraftClear() { this._confirmKind = 'draft'; this._confirmDiscard = true; },
        {{-- Depois de um erro, leva ate o primeiro campo com erro (ou a faixa geral):
             num formulario longo ele ficava fora da tela e parecia que nada tinha
             acontecido (1.61.0). --}}
        _revealError() {
            setTimeout(() => {
                {{-- Montado em partes: a palavra inteira no HTML parece, a um leitor do
                     markup (e a um teste), um campo ja marcado com erro. --}}
                const f = $el.querySelector('[aria-' + 'invalid=true]');
                const target = f || $el.querySelector('[data-ptah-form-general]');
                if (! target) return;
                target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                if (f) f.focus({ preventScroll: true });
            }, 60);
        },
        closeModal() { this._tryClose(); },
        _tryClose() {
            // Com rascunho ativo, fechar nao perde nada: sem o aviso de descarte.
            if (this._dirty && ! (this._draft && this._draft.key)) {
                this._confirmKind = 'close';
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
        // ── Rascunho no navegador (formDraft) ──────────────────────
        // Contexto vem do servidor a cada abertura (ptah:form-draft): chave
        // usuario+tela+modo+registro, campos permitidos, valores originais e,
        // na edicao, a impressao digital do registro.
        _draft: null,
        _draftDirty: false,
        {{-- O modal abre antes de a resposta chegar (o Novo abre pelo Alpine). Ate
             o formulario estar pronto -- e o rascunho restaurado -- os campos
             ficam travados: o que se digitasse ali a resposta apagaria. --}}
        _formLoading: false,
        _restorePending: false,
        _draftOn: @js($this->formDraftEnabled()),
        _draftPrefix: @js($this->formDraftPrefix()),
        {{-- Le o rascunho NA HORA do clique e o manda junto com a abertura:
             o servidor devolve o formulario ja restaurado, numa ida so. --}}
        _draftStage(target) {
            if (! this._draftOn) return;
            let item = null;
            try { item = JSON.parse(localStorage.getItem(this._draftPrefix + target) || 'null'); } catch (e) {}
            const fresh = item && item.v && item.at && ! (item.ttl && Date.now() - item.at > item.ttl * 86400000);
            $wire.$set('formDraftIncoming', fresh ? { target, values: item.v, version: item.version || '' } : [], false);
        },
        _loadingGuard: null,
        _startLoading() {
            this._formLoading = true;
            clearTimeout(this._loadingGuard);
            this._loadingGuard = setTimeout(() => this._endLoading(), 10000);
        },
        _endLoading() {
            if (! this._formLoading) return;
            clearTimeout(this._loadingGuard);
            this._formLoading = false;
            this._restorePending = false;
            $nextTick(() => {
                const f = $el.querySelector('input:not([type=hidden]):not([type=checkbox]):not([readonly]), textarea, select');
                if (f && this.open) f.focus();
            });
        },
        _draftTimer: null,
        _draftText: {
            restored: @js(__('ptah::ui.form_draft_restored')),
            discarded: @js(__('ptah::ui.form_draft_discarded')),
        },
        _draftToast(title, color) {
            window.dispatchEvent(new CustomEvent('ptah-toast', { detail: { title, color } }));
        },
        _draftNorm(v) {
            return v === null || v === undefined ? '' : (typeof v === 'object' ? JSON.stringify(v) : String(v));
        },
        _draftPick() {
            {{-- Campo a campo, sem JSON: um formData vazio chega do PHP como [] e
                 o Livewire grava formData.nome como propriedade desse ARRAY, que o
                 JSON.stringify descarta. --}}
            const data = $wire.formData || {};
            const out = {};
            (this._draft ? this._draft.fields : []).forEach((f) => {
                const v = data[f];
                out[f] = v === undefined ? null : (v !== null && typeof v === 'object' ? JSON.parse(JSON.stringify(v)) : v);
            });
            return out;
        },
        _draftDiffers(values) {
            const o = this._draft ? this._draft.original : {};
            return (this._draft ? this._draft.fields : []).some((f) => this._draftNorm(values[f]) !== this._draftNorm(o[f]));
        },
        _draftPurge(user) {
            try {
                const now = Date.now();
                Object.keys(localStorage).forEach((k) => {
                    if (k.indexOf('ptah:draft:') !== 0) return;
                    // Rascunho de outro usuario neste navegador: fora.
                    if (user && k.indexOf('ptah:draft:' + user + ':') !== 0) { localStorage.removeItem(k); return; }
                    const item = JSON.parse(localStorage.getItem(k) || 'null');
                    if (! item || ! item.at || (item.ttl && now - item.at > item.ttl * 86400000)) localStorage.removeItem(k);
                });
            } catch (e) {}
        },
        _draftInit(d) {
            this._draftDirty = false;
            {{-- O rascunho enviado ja foi usado: nao pode ir de novo na proxima requisicao. --}}
            $wire.$set('formDraftIncoming', [], false);
            this._draft = d && d.key ? d : null;
            this._draftPurge(d ? d.user : null);
            if (! this._draft) { this._endLoading(); return; }
            {{-- O servidor ja aplicou o rascunho que foi junto com a abertura. --}}
            if (d.discarded) {
                try { localStorage.removeItem(this._draft.key); } catch (e) {}
                this._draftToast(this._draftText.discarded, 'warn');
                this._endLoading();
                return;
            }
            if (d.restored) {
                this._draftDirty = true;
                this._draftBanner = true;
                this._endLoading();
                return;
            }
            let item = null;
            try { item = JSON.parse(localStorage.getItem(this._draft.key) || 'null'); } catch (e) {}
            if (! item || ! item.v) { this._endLoading(); return; }
            // O registro mudou no banco desde o rascunho: descarta e avisa, em
            // vez de gravar por cima da alteracao de outra pessoa.
            if ((this._draft.version || '') !== (item.version || '')) {
                try { localStorage.removeItem(this._draft.key); } catch (e) {}
                this._draftToast(this._draftText.discarded, 'warn');
                this._endLoading();
                return;
            }
            if (! this._draftDiffers(item.v)) { this._endLoading(); return; }
            {
                this._draftDirty = true;
                this._restorePending = true;
                this._startLoading();
                $wire.restoreFormDraft(item.v);
                this._draftBanner = true;
            }
        },
        _draftTouch() {
            {{-- Modal fechado nao grava: o commit do save volta com o formData
                 ainda cheio e regravaria o rascunho que acabou de ser apagado. --}}
            if (! this._draft || ! this._draft.key || ! this.open) return;
            clearTimeout(this._draftTimer);
            this._draftTimer = setTimeout(() => {
                if (! this._draft) return;
                const values = this._draftPick();
                this._draftDirty = this._draftDiffers(values);
                try {
                    if (this._draftDirty) {
                        localStorage.setItem(this._draft.key, JSON.stringify({ v: values, version: this._draft.version || '', at: Date.now(), ttl: this._draft.ttlDays }));
                    } else {
                        localStorage.removeItem(this._draft.key);
                    }
                } catch (e) {}
            }, 400);
        },
        _draftForget(key) {
            try { localStorage.removeItem(key); } catch (e) {}
            if (this._draft && this._draft.key === key) this._draftDirty = false;
        },
        _draftSaved(key) {
            {{-- Salvou: este rascunho acabou; a proxima abertura manda contexto novo. --}}
            clearTimeout(this._draftTimer);
            this._draftForget(key);
            if (this._draft && this._draft.key === key) this._draft = null;
        },
        _draftClear() {
            this._draftBanner = false;
            if (this._draft) this._draftForget(this._draft.key);
            $wire.clearFormDraft();
        },
        _draftRevert() {
            this._draftBanner = false;
            if (this._draft) this._draftForget(this._draft.key);
            $wire.revertFormToOriginal();
        },
        _focusFirst() {
            this._dirty = false;
            $nextTick(() => {
                const f = $el.querySelector(
                    'input:not([type=hidden]):not([type=checkbox]):not([readonly]), textarea, select'
                );
                if (f) f.focus();
            });
        },
        {{-- O x-init abaixo pendura um ouvinte no document e um hook no Livewire,
             que sobrevivem ao componente. Numa navegacao SPA o componente morre a
             cada troca de tela e nasce outro na volta: sem desfazer aqui, eles se
             acumulavam (um por visita). --}}
        _offs: [],
        destroy() {
            this._offs.forEach((off) => { try { off(); } catch (e) {} });
            this._offs = [];
        }
    }"
    x-effect="
        if (open) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
            _dirty = false;
            _confirmDiscard = false;
            _draftBanner = false;
        }
    "
    @ptah:form-ready.window="_focusFirst(); if (! _draftOn) _endLoading()"
    @ptah:form-draft.window="_draftInit($event.detail)"
    @ptah-form-opening.window="_draftStage('new'); _startLoading()"
    @ptah:form-draft-saved.window="_draftSaved($event.detail.key)"
    @ptah:form-invalid.window="if (open) _revealError()"
    {{-- Ctrl+Enter (ou Cmd+Enter) salva (1.61.0). --}}
    @keydown.window.ctrl.enter="if (open && ! _confirmDiscard && ! _formLoading) { $event.preventDefault(); $wire.save(); }"
    @keydown.window.meta.enter="if (open && ! _confirmDiscard && ! _formLoading) { $event.preventDefault(); $wire.save(); }"
    x-init="
        _draftPurge(@js($this->formDraftUser()));
        {{-- Editar: o botao e wire:click, que dispara na fase normal; este ouvinte
             de captura roda antes e deixa o rascunho do id pronto para ir junto. --}}
        const _onEditClick = (e) => {
            const btn = e.target.closest && e.target.closest('[wire\\:click^=&quot;openEdit(&quot;]');
            if (! btn || ! btn.closest('[wire\\:id=&quot;' + $wire.$id + '&quot;]')) return;
            const m = /^openEdit\((\d+)\)$/.exec(btn.getAttribute('wire:click') || '');
            if (m) _draftStage('edit:' + m[1]);
        };
        document.addEventListener('click', _onEditClick, true);
        _offs.push(() => document.removeEventListener('click', _onEditClick, true));
        {{-- selecionar um searchdropdown muda o formData pelo servidor, sem evento de input: depois de cada commit deste componente, o rascunho se atualiza --}}
        _offs.push(Livewire.hook('commit', ({ component, commit, succeed, fail }) => {
            if (component.id !== $wire.$id) return;
            const restoring = ((commit && commit.calls) || []).some((c) => c.method === 'restoreFormDraft');
            succeed(() => setTimeout(() => {
                _draftTouch();
                if (restoring) _endLoading();
            }, 0));
            fail(() => _endLoading());
        }));
    "
    @keydown.escape.window="
        if (_confirmDiscard) { _confirmDiscard = false; }
        else if (open) { _tryClose(); }
    "
>
    <x-forge-modal
        :title="($editingId ? __('ptah::ui.modal_edit_prefix') : $this->modalNewPrefix()) . ' ' . $crudTitleSingular"
        :subtitle="collect($this->getFormViewCols())->contains(fn ($c) => $this->fieldIsRequired($c)) ? __('ptah::ui.form_required_hint') : ($editingId ? __('ptah::ui.modal_edit_subtitle') : __('ptah::ui.modal_create_subtitle'))"
        size="2xl"
        draggable
    >
        {{-- Erro geral --}}
        @if ($formDraftMode !== '')
            <div x-show="_draftBanner" x-cloak role="status" data-ptah-draft-banner
                class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 text-xs ptah-c-draft_banner">
                <span>{{ __('ptah::ui.form_draft_banner') }}</span>
                @if ($formDraftMode === 'edit')
                    <button type="button" @click="_draftRevert()" class="font-semibold underline underline-offset-2">{{ __('ptah::ui.btn_form_draft_revert') }}</button>
                @else
                    <button type="button" @click="_askDraftClear()" class="font-semibold underline underline-offset-2">{{ __('ptah::ui.btn_form_draft_clear') }}</button>
                @endif
            </div>
        @endif
        @if (!empty($formErrors['_general']))
            <div class="mb-4" data-ptah-form-general>
                <x-forge-alert type="danger">{{ $formErrors['_general'] }}</x-forge-alert>
            </div>
        @endif

        <div class="relative flex flex-col gap-4" wire:key="crud-form-fields-{{ $formInstanceKey }}" @input="_markDirty(); _draftTouch()" @change="_markDirty(); _draftTouch()"
            :inert="_formLoading" :aria-busy="_formLoading ? 'true' : 'false'">
            <div x-show="_formLoading" x-cloak role="status"
                class="absolute inset-0 z-10 flex items-center justify-center gap-2 rounded-md text-sm ptah-c-muted"
                style="background: color-mix(in srgb, var(--ptah-surface) 75%, transparent)">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                <span>{{ __('ptah::ui.form_loading') }}</span>
            </div>

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
                            // 1.48.0: coluna NOT NULL vira chave liga/desliga; anulavel
                            // segue no select (a chave nao representa "nao informado").
                            $fToggle = $this->isBooleanToggle($col);
                            if ($fTipo === 'boolean' && ! $fToggle) {
                                if (! is_array($col['colsSelect'] ?? null) || $col['colsSelect'] === []) {
                                    $col['colsSelect'] = [__('ptah::ui.bool_yes') => '1', __('ptah::ui.bool_no') => '0'];
                                }
                                $fTipo = 'select';
                            }

                            // colsRequired, ou coluna NOT NULL sem DEFAULT (1.61.0): o
                            // asterisco aparece antes do erro, nao so depois.
                            $fRequired = $this->fieldIsRequired($col);
                            $fError    = $formErrors[$fField] ?? null;
                            $fMask     = $col['colsMask'] ?? null;
                            // Resolvido do registro (PtahMask). null quando o nome nao
                            // esta registrado — e ai o campo cai no input comum, que e
                            // exatamente o que "cpf" e companhia sempre fizeram.
                            $fMaskDef  = \Ptah\Support\PtahMask::get($fMask);
                            // Vazio quer dizer "nao informado" (coluna anulavel ou colsMaskEmpty: null):
                            // o campo de valor fica vazio em vez de R$ 0,00, e o save grava null.
                            $fMaskBlank = ($fMask || ! empty($col['colsMaskTransform'])) && $this->maskEmptyIsNull($col);
                            $fValue    = $formData[$fField] ?? '';
                            $fHelpText = $col['colsHelpText'] ?? null;
                            // colsPlaceholder: gravado pela CLI (placeholder=) e pelo wizard
                            // desde sempre, e ignorado ate a 1.41.3.
                            $fPlaceholder = trim((string) ($col['colsPlaceholder'] ?? ''));
                            // password (1.48.0): ao editar, vazio = manter a atual.
                            if ($fTipo === 'password' && $editingId) {
                                $fRequired = false;
                                if ($fPlaceholder === '') {
                                    $fPlaceholder = __('ptah::ui.password_keep');
                                }
                            }
                            // colsReadonly (1.48.0): mostrado, nunca gravado.
                            $fReadonly = in_array($col['colsReadonly'] ?? false, [true, 'S', 1, '1'], true);
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

                            @if ($fReadonly)
                                {{-- ── Somente leitura (1.48.0) ── --}}
                                @php
                                    $roRaw = is_bool($fValue) ? ($fValue ? '1' : '0') : $fValue;
                                    $roOptions = is_array($col['colsSelect'] ?? null) ? array_map('strval', $col['colsSelect']) : [];
                                    $roText = match (true) {
                                        $fTipo === 'password' => '',
                                        $fToggle => $roRaw === '' || $roRaw === null ? '' : ((bool) $roRaw ? __('ptah::ui.bool_yes') : __('ptah::ui.bool_no')),
                                        $fTipo === 'searchdropdown' => (string) ($sdLabels[$fField] ?? $roRaw),
                                        $roOptions !== [] => (string) (array_search((string) $roRaw, $roOptions, true) ?: $roRaw),
                                        default => is_scalar($roRaw) ? (string) $roRaw : '',
                                    };
                                @endphp
                                <div class="w-full">
                                    <span class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">{{ $fLabel }}</span>
                                    <div class="text-sm whitespace-pre-wrap break-words ptah-c-form_ro" data-ptah-readonly="{{ $fField }}">{{ $roText !== '' ? $roText : '—' }}</div>
                                </div>

                            @elseif ($fToggle)
                                {{-- ── Chave liga/desliga (boolean NOT NULL, 1.48.0) ── --}}
                                <div class="w-full">
                                    <span class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                    </span>
                                    <x-forge-switch :checked="(bool) $fValue" wire:model.live="formData.{{ $fField }}" aria-label="{{ $fLabel }}" data-ptah-toggle="{{ $fField }}" />
                                    @if ($fError)
                                        <p id="ptah-form-err-{{ $fField }}" class="mt-1 text-xs ptah-c-field_err">{{ $fError }}</p>
                                    @endif
                                </div>

                            @elseif ($fTipo === 'select' && !empty($col['colsSelect']))
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
                                    // Estado ABERTO (1.47.0): `.ptah-c-form_sel_open[_err]` em
                                    // ptah-components.css. Os utilitarios que ficavam aqui
                                    // (`ring-red-200 dark:ring-red-300`) eram paleta fixa, e o
                                    // `border-primary` perdia para a borda do `.ptah-c-form_sel`.
                                    $fOpenClass    = $fError ? 'ptah-c-form_sel_open ptah-c-form_sel_open_err' : 'ptah-c-form_sel_open';
                                @endphp
                                <div class="w-full">
                                    <label id="ptah-lbl-{{ $fField }}" class="block mb-1.5 text-xs font-medium ptah-c-form_lbl" @click="$el.parentElement.querySelector('[role=combobox]').focus()">
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
                                            toggle(value) { this.selected = String(value); this.open = false; },
                                            clear() { this.selected = null; this.open = false; },
                                            {{-- Teclado (1.61.0): antes Enter so abria e fechava a lista,
                                                 e quem preenchia com Tab nao tinha como escolher. As
                                                 setas andam pelas opcoes (o vazio do opcional conta),
                                                 Enter escolhe, Esc fecha so a lista. --}}
                                            active: -1,
                                            optional: @js(! $fRequired),
                                            get items() { return (this.optional ? [{ value: null, label: this.placeholder }] : []).concat(this.options); },
                                            show() {
                                                this.open = true;
                                                const i = this.items.findIndex(o => o.value === null ? (this.selected === null || this.selected === '') : this.isSelected(o.value));
                                                this.active = i >= 0 ? i : 0;
                                                this.$nextTick(() => this.reveal());
                                            },
                                            move(step) {
                                                if (! this.open) { this.show(); return; }
                                                const n = this.items.length;
                                                if (n === 0) return;
                                                this.active = step === 'first' ? 0 : (step === 'last' ? n - 1 : Math.min(n - 1, Math.max(0, this.active + step)));
                                                this.$nextTick(() => this.reveal());
                                            },
                                            reveal() {
                                                const el = this.$root.querySelector('[data-ptah-opt=\'' + this.active + '\']');
                                                if (el) el.scrollIntoView({ block: 'nearest' });
                                            },
                                            choose(i) {
                                                const o = this.items[i];
                                                if (! o) return;
                                                o.value === null ? this.clear() : this.toggle(o.value);
                                                this.$refs.combo.focus();
                                            },
                                            onEnter() { this.open && this.active >= 0 ? this.choose(this.active) : this.show(); }
                                        }"
                                        @click.outside="open = false"
                                        class="relative"
                                    >
                                        <input type="hidden"
                                            :value="selected ?? ''"
                                            x-init="$watch('selected', val => {
                                                $el.value = val ?? '';
                                                {{-- So o que a pessoa escolheu vai ao servidor (1.60.1): o
                                                     $wire.$watch do init() tambem muda selected com o valor
                                                     que a resposta trouxe, e reenviar esse valor abria outra
                                                     requisicao, cuja resposta mudava selected de novo — um
                                                     commit vazio por segundo, e com varios selects um
                                                     sobrescrevia o outro. --}}
                                                const srv = $wire.$get('formData.{{ $fField }}');
                                                const now = (srv === null || srv === undefined) ? '' : (typeof srv === 'boolean' ? (srv ? '1' : '0') : String(srv));
                                                if (now === String(val ?? '')) return;
                                                $el.dispatchEvent(new Event('input', { bubbles: true }));
                                            })"
                                            wire:model.live="formData.{{ $fField }}"
                                        >
                                        <div
                                            x-ref="combo"
                                            id="ptah-field-{{ $fField }}"
                                            tabindex="{{ $tabIdx }}"
                                            role="combobox"
                                            aria-haspopup="listbox"
                                            aria-labelledby="ptah-lbl-{{ $fField }}"
                                            aria-controls="ptah-list-{{ $fField }}"
                                            :aria-expanded="open"
                                            :aria-activedescendant="open && active >= 0 ? 'ptah-opt-{{ $fField }}-' + active : null"
                                            @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                            @keydown.space.prevent="open ? onEnter() : show()"
                                            @keydown.enter.prevent="onEnter()"
                                            @keydown.arrow-down.prevent="move(1)"
                                            @keydown.arrow-up.prevent="move(-1)"
                                            @keydown.home.prevent="move('first')"
                                            @keydown.end.prevent="move('last')"
                                            @keydown.escape="if (open) { open = false; $event.stopPropagation(); }"
                                            @keydown.tab="open = false"
                                            @click="open ? (open = false) : show()"
                                            :class="open ? '{{ $fOpenClass }}' : '{{ $fBorderNormal }}'"
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
                                            <ul class="py-1" role="listbox" id="ptah-list-{{ $fField }}" aria-labelledby="ptah-lbl-{{ $fField }}">
                                                @unless ($fRequired)
                                                    {{-- Opcional: volta ao vazio (1.47.0). Grava '' e o save()
                                                         converte em null quando a coluna aceita nulo. --}}
                                                    <li
                                                        @click="clear()"
                                                        data-ptah-select-clear
                                                        data-ptah-opt="0"
                                                        id="ptah-opt-{{ $fField }}-0"
                                                        role="option"
                                                        :aria-selected="(selected === null || selected === '') ? 'true' : 'false'"
                                                        @mousemove="active = 0"
                                                        :class="[(selected === null || selected === '') ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item', active === 0 ? 'ptah-c-dd_item_active' : '']"
                                                        class="px-4 py-2 text-sm cursor-pointer ptah-c-fp_muted"
                                                        x-text="placeholder"
                                                    ></li>
                                                @endunless
                                                <template x-for="(option, i) in options" :key="option.value">
                                                    <li
                                                        @click="toggle(option.value)"
                                                        :data-ptah-opt="i + (optional ? 1 : 0)"
                                                        :id="'ptah-opt-{{ $fField }}-' + (i + (optional ? 1 : 0))"
                                                        role="option"
                                                        :aria-selected="isSelected(option.value) ? 'true' : 'false'"
                                                        @mousemove="active = i + (optional ? 1 : 0)"
                                                        :class="[isSelected(option.value) ? 'ptah-c-dd_item_sel' : 'ptah-c-dd_item', active === i + (optional ? 1 : 0) ? 'ptah-c-dd_item_active' : '']"
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
                                    $sdSettings   = $this->sdSettings($col);
                                    // Cascading dropdown: locked until the parent field has a value.
                                    $sdDependsOn   = $col['colsSDDependsOn'] ?? null;
                                    $sdParentEmpty = $sdDependsOn && (($formData[$sdDependsOn] ?? null) === null || ($formData[$sdDependsOn] ?? '') === '');
                                    $sdParentLabel = $sdDependsOn
                                        ? ((collect($crudConfig['cols'] ?? [])->firstWhere('colsNomeFisico', $sdDependsOn)['colsNomeLogico'] ?? null) ?: $sdDependsOn)
                                        : null;
                                @endphp
                                <div class="w-full">
                                    <label for="ptah-field-{{ $fField }}" class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
                                        {{ $fLabel }}@if($fRequired)<span class="ptah-c-field_err ml-0.5">*</span>@endif
                                    </label>
                                    <div
                                        {{-- x-data sem nada que venha do render (1.60.3): com o
                                             rotulo ou o "aberto" escritos aqui, cada resposta que os
                                             mudava trocava o atributo, o morph do Livewire reiniciava
                                             este Alpine, e o campo voltava ao item escolhido por cima
                                             do que a pessoa digitava. O estado inicial vem do $wire. --}}
                                        x-data="{
                                            open: false,
                                            displayVal: '',
                                            {{-- O rotulo que o servidor conhece: so uma mudanca real
                                                 dele mexe no campo, nao cada resposta do componente. --}}
                                            _label: '',
                                            {{-- A pessoa digitou desde a ultima escolha (1.61.0): com
                                                 isso a lista abre tambem sem resultado, e mostra
                                                 o aviso de nenhum resultado em vez de nao abrir. --}}
                                            typed: false,
                                            active: -1,
                                            init() {
                                                const first = this.$wire.sdLabels ? this.$wire.sdLabels['{{ $fField }}'] : undefined;
                                                this._label = (first === undefined || first === null) ? '' : String(first);
                                                this.displayVal = this._label;
                                                const res0 = this.$wire.sdResults ? this.$wire.sdResults['{{ $fField }}'] : undefined;
                                                this.open = Array.isArray(res0) && res0.length > 0;
                                                this.$wire.$watch('sdLabels', (val) => {
                                                    const raw = val ? val['{{ $fField }}'] : undefined;
                                                    const label = (raw === undefined || raw === null) ? '' : String(raw);
                                                    if (label === this._label) return;
                                                    this._label = label;
                                                    this.displayVal = label;
                                                    this.typed = false;
                                                });
                                                this.$wire.$watch('sdResults', (val) => {
                                                    const res = val['{{ $fField }}'];
                                                    this.active = -1;
                                                    {{-- So abre com o foco no campo: a resposta de uma busca
                                                         que chega depois de sair dele nao reabre a lista. --}}
                                                    const here = document.activeElement === this.$refs.input;
                                                    this.open = here && Array.isArray(res) && (res.length > 0 || (this.typed && this.displayVal.trim() !== ''));
                                                });
                                            },
                                            onInput() {
                                                this.typed = true;
                                                this.active = -1;
                                                if (this.displayVal.trim() !== '') this.open = true;
                                            },
                                            opts() { return this.$refs.list ? [...this.$refs.list.querySelectorAll('[data-sd-opt]')] : []; },
                                            move(step) {
                                                const o = this.opts();
                                                if (! this.open) { this.open = true; if (! o.length) $wire.openDropdown('{{ $fField }}'); return; }
                                                if (! o.length) return;
                                                this.active = Math.min(o.length - 1, Math.max(0, this.active + step));
                                                o[this.active].scrollIntoView({ block: 'nearest' });
                                            },
                                            pickActive() {
                                                const o = this.opts();
                                                if (this.open && this.active >= 0 && o[this.active]) o[this.active].click();
                                            },
                                            {{-- Sair do campo sem escolher (1.61.0): com um item ja
                                                 escolhido, volta a mostra-lo; sem nenhum, o texto da
                                                 busca sai — ele parecia escolhido e nao era. --}}
                                            leave() {
                                                this.open = false;
                                                this.active = -1;
                                                this.typed = false;
                                                {{-- Apagou tudo e saiu: e limpar a escolha, mesmo que a
                                                     busca vazia (debounce) ainda nao tenha ido. --}}
                                                if (this.displayVal.trim() === '') {
                                                    this.displayVal = '';
                                                    if (this._label !== '') $wire.searchDropdown('{{ $fField }}', '');
                                                    return;
                                                }
                                                if (this.displayVal !== this._label) this.displayVal = this._label;
                                            },
                                            clearChoice() {
                                                this.displayVal = '';
                                                this.typed = false;
                                                this.open = false;
                                                $wire.searchDropdown('{{ $fField }}', '');
                                                this.$refs.input.focus();
                                            }
                                        }"
                                        @click.outside="if (open || displayVal !== _label) leave()"
                                        @focusout="if (! $el.contains($event.relatedTarget)) leave()"
                                        class="relative"
                                    >
                                        <div class="relative flex items-center">
                                            <input type="text"
                                                x-ref="input"
                                                id="ptah-field-{{ $fField }}"
                                                x-model="displayVal"
                                                role="combobox"
                                                aria-autocomplete="list"
                                                aria-controls="ptah-list-{{ $fField }}"
                                                :aria-expanded="open ? 'true' : 'false'"
                                                :aria-activedescendant="open && active >= 0 ? 'ptah-sdopt-{{ $fField }}-' + active : null"
                                                @input="onInput()"
                                                wire:keyup.debounce.300ms="searchDropdown('{{ $fField }}', $event.target.value)"
                                                @focus="if (! ($wire.sdResults?.['{{ $fField }}'] || []).length) $wire.openDropdown('{{ $fField }}')"
                                                @keydown.arrow-down.prevent="move(1)"
                                                @keydown.arrow-up.prevent="move(-1)"
                                                @keydown.enter="if (open && active >= 0) { $event.preventDefault(); pickActive(); }"
                                                @keydown.escape="if (open) { open = false; active = -1; $event.stopPropagation(); }"
                                                placeholder="{{ $sdParentEmpty
                                                    ? __('ptah::ui.sd_select_parent_first', ['parent' => $sdParentLabel])
                                                    : ($sdSettings['placeholder'] ?: __('ptah::ui.search_entity', ['label' => $fLabel])) }}"
                                                autocomplete="off"
                                                tabindex="{{ $tabIdx }}"
                                                @if ($sdParentEmpty) disabled @endif
                                                @if ($fError) aria-invalid="true" aria-describedby="ptah-form-err-{{ $fField }}" @endif
                                                class="block w-full rounded-md border {{ $fBorderClass }} outline-none px-3 py-2.5 {{ $fRequired ? 'pr-9' : 'pr-16' }} text-sm transition-colors duration-150 focus:ring-2 ptah-c-form_in disabled:opacity-60 disabled:cursor-not-allowed"
                                            />
                                            @unless ($fRequired || $sdParentEmpty)
                                                {{-- Limpar a escolha (1.61.0): apagar o texto ja limpava,
                                                     mas pouca gente descobria. --}}
                                                <button type="button"
                                                    x-show="_label !== ''" x-cloak
                                                    tabindex="-1"
                                                    @mousedown.prevent="clearChoice()"
                                                    class="absolute right-8 p-0.5 rounded ptah-c-fp_chevron hover:opacity-80"
                                                    title="{{ __('ptah::ui.sd_clear', ['label' => $fLabel]) }}"
                                                    aria-label="{{ __('ptah::ui.sd_clear', ['label' => $fLabel]) }}"
                                                    data-ptah-sd-clear>
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            @endunless
                                            <button type="button"
                                                tabindex="-1"
                                                @if ($sdParentEmpty) disabled @endif
                                                @mousedown.prevent="open = !open; if (open && ! ($wire.sdResults?.['{{ $fField }}'] || []).length) $wire.openDropdown('{{ $fField }}')"
                                                class="absolute right-2.5 ptah-c-fp_chevron transition-transform duration-200 disabled:opacity-40 disabled:pointer-events-none"
                                                :class="open ? 'rotate-180' : ''">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <input type="hidden" wire:model="formData.{{ $fField }}" />
                                        <div x-show="open" x-cloak x-ref="list" id="ptah-list-{{ $fField }}" role="listbox"
                                            class="absolute z-30 w-full {{ $sdSettings['startList'] === 'top' ? 'bottom-full mb-1' : 'mt-1' }} overflow-y-auto rounded-md max-h-48 ptah-c-dd">
                                            {{-- Enquanto a busca vai e volta, "Buscando..." no lugar da
                                                 lista anterior: escolher um item que nao corresponde ao
                                                 texto digitado era facil (1.61.0). --}}
                                            <p wire:loading.flex wire:target="searchDropdown" class="items-center gap-2 px-4 py-3 text-xs ptah-c-fp_muted" role="status">
                                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                                {{ __('ptah::ui.sd_loading') }}
                                            </p>
                                            <div wire:loading.remove wire:target="searchDropdown">
                                                @forelse ($sdResults[$fField] ?? [] as $opt)
                                                    <button type="button"
                                                        data-sd-opt
                                                        id="ptah-sdopt-{{ $fField }}-{{ $loop->index }}"
                                                        role="option"
                                                        tabindex="-1"
                                                        @mousemove="active = {{ $loop->index }}"
                                                        :class="active === {{ $loop->index }} ? 'ptah-c-dd_opt_active' : ''"
                                                        wire:click="selectDropdownOption({{ \Illuminate\Support\Js::from($fField) }}, {{ \Illuminate\Support\Js::from($opt['value']) }}, {{ \Illuminate\Support\Js::from((string) $opt['label']) }})"
                                                        @click="open = false; typed = false; active = -1"
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
                                                    <p class="px-4 py-3 text-xs italic ptah-c-fp_muted" data-ptah-sd-empty>{{ __('ptah::ui.no_results') }}</p>
                                                @endforelse
                                            </div>
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
                                    $fPreviewUrl = $fValue ? \Ptah\Support\UploadDisk::url($col, (string) $fValue) : '';
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
                                    <label class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
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
                                        'password'        => 'password',
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
                                                    /* O eco da propria tecla (push abaixo) nao e
                                                       normalizado: `#336` virava `#333366` no meio
                                                       de `#3366cc` (1.60.5). */
                                                    if (String(val) === String(this.hex)) return;
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
                                        <label for="ptah-color-in-{{ $fField }}" class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
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
                                            blank: {{ $fMaskBlank ? 'true' : 'false' }},
                                            fmt(n) {
                                                const v = parseFloat(n) || 0;
                                                return 'R$ ' + v.toFixed(2)
                                                    .replace('.', ',')
                                                    .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                                            },
                                            show(v) {
                                                // Vazio = nao informado: fica vazio (o placeholder mostra R$ 0,00).
                                                if (this.blank && (v === null || v === undefined || v === '')) return '';
                                                return this.fmt(parseFloat(v) || 0);
                                            },
                                            init() {
                                                // Read directly from Livewire state (not PHP-rendered) so
                                                // create→edit never carries stale display value.
                                                const raw = this.$wire.formData?.['{{ $fField }}'] ?? null;
                                                this.display = this.show(raw);
                                                // Keep in sync when openEdit() sets formData from server
                                                this.$wire.$watch('formData.{{ $fField }}', (val) => {
                                                    if ((val === null || val === undefined) && ! this.blank) return;
                                                    // Ignore values already formatted (contain letters/symbols)
                                                    if (/[a-zA-Z$]/.test(String(val))) return;
                                                    this.display = this.show(val);
                                                });
                                            },
                                            onInput(e) {
                                                const digits = e.target.value.replace(/\D/g, '');
                                                const n = parseInt(digits || '0', 10) / 100;
                                                const f = this.blank && digits === '' ? '' : this.fmt(n);
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
                                        <label class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
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
                                            <label class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
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
                                        <label for="ptah-mask-in-{{ $fField }}" class="block mb-1.5 text-xs font-medium ptah-c-form_lbl">
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
                                            placeholder="{{ $fPlaceholder !== '' ? $fPlaceholder : $fMaskDef['placeholder'] }}"
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
                                            :placeholder="$fPlaceholder"
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
                                            :placeholder="$fPlaceholder"
                                            :autocomplete="$fTipo === 'password' ? 'new-password' : null"
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
            {{-- Rodape (1.61.0): o que nao e salvar/cancelar fica num grupo a esquerda,
                 e tudo quebra linha no celular em vez de estourar a largura. --}}
            <div class="flex flex-wrap items-center gap-2 mr-auto">
                @if ($editingId && $this->attachmentsEnabled())
                    <x-forge-button wire:click="openAttachments({{ json_encode($editingId) }})" color="dark" flat>
                        {{ __('ptah::ui.btn_attachments') }} ({{ $this->attachmentCount($editingId) }})
                    </x-forge-button>
                @endif
                @if ($editingId && $this->historyEnabled())
                    <x-forge-button wire:click="openHistory({{ json_encode($editingId) }})" color="dark" flat>
                        {{ __('ptah::ui.btn_history') }}
                    </x-forge-button>
                @endif
                {{-- Rascunho: "Descartar rascunho" no Novo (pede confirmacao: apagava
                     tudo num clique, colado ao Cancelar) e "Voltar ao original" na
                     edicao, so quando algum campo difere do que o modal abriu. --}}
                @if ($formDraftMode === 'new')
                    <x-forge-button x-show="_draftDirty" x-cloak @click="_askDraftClear()" color="dark" flat :disabled="$creating" data-ptah-draft-clear>
                        {{ __('ptah::ui.btn_form_draft_clear') }}
                    </x-forge-button>
                @elseif ($formDraftMode === 'edit')
                    <x-forge-button x-show="_draftDirty" x-cloak @click="_draftRevert()" color="dark" flat :disabled="$creating">
                        {{ __('ptah::ui.btn_form_draft_revert') }}
                    </x-forge-button>
                @endif
            </div>
            <x-forge-button @click="_tryClose()" color="dark" flat :disabled="$creating">
                {{ __('ptah::ui.btn_cancel') }}
            </x-forge-button>
            {{-- Salvando (1.61.0): $creating vai e volta na mesma requisicao, entao o
                 :loading nunca aparecia; quem clicava de novo mandava outro save. O
                 wire:loading mostra e trava enquanto o save esta a caminho. --}}
            @if (! $editingId)
                <x-forge-button wire:click="saveAndNew" color="primary" flat :disabled="$creating"
                    wire:loading.attr="disabled" wire:target="save,saveAndNew">
                    {{ __('ptah::ui.btn_save_and_new') }}
                </x-forge-button>
            @endif
            <x-forge-button wire:click="save" color="primary" :disabled="$creating"
                wire:loading.attr="disabled" wire:target="save,saveAndNew" data-ptah-save>
                <span wire:loading.remove wire:target="save,saveAndNew">{{ $editingId ? __('ptah::ui.btn_save_changes') : __('ptah::ui.btn_create') }}</span>
                <span wire:loading.inline-flex wire:target="save,saveAndNew" class="items-center gap-1.5" role="status">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    {{ __('ptah::ui.form_saving') }}
                </span>
            </x-forge-button>
        </x-slot>
    </x-forge-modal>

    {{-- Unsaved-changes confirmation (replaces the native confirm() dialog) --}}
    {{-- x-trap (1.61.0): o aviso ficava fora da armadilha de foco do painel, e o
         Tab nunca chegava a "Continuar editando" / "Descartar". Abre com o foco
         no botao que nao perde nada. Serve tambem a "Descartar rascunho". --}}
    <div x-show="_confirmDiscard" x-cloak x-trap="_confirmDiscard"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="_confirmDiscard = false"></div>
        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative w-full max-w-sm rounded-lg shadow-2xl p-5 ptah-c-modal_card"
             role="alertdialog" aria-modal="true">
            <p class="text-sm font-semibold ptah-c-modal_ttl"
                x-text="_confirmKind === 'draft' ? @js(__('ptah::ui.form_draft_clear_confirm')) : @js(__('ptah::ui.modal_unsaved_title'))">
                {{ __('ptah::ui.modal_unsaved_title') }}
            </p>
            <p class="text-xs mt-1 ptah-c-modal_sub" x-show="_confirmKind !== 'draft'">
                {{ __('ptah::ui.modal_unsaved_confirm') }}
            </p>
            <div class="flex justify-end gap-2 mt-5">
                <button @click="_confirmDiscard = false"
                    class="px-4 py-2 text-sm font-semibold rounded-md ptah-c-discard_keep">
                    {{ __('ptah::ui.modal_unsaved_keep') }}
                </button>
                <button @click="_confirmKind === 'draft' ? (_confirmDiscard = false, _draftClear()) : _forceClose()"
                    class="px-4 py-2 text-sm font-semibold rounded-md ptah-c-ink_on_accent bg-danger-dark hover:opacity-90"
                    x-text="_confirmKind === 'draft' ? @js(__('ptah::ui.form_draft_clear_confirm_yes')) : @js(__('ptah::ui.modal_unsaved_discard'))">
                    {{ __('ptah::ui.modal_unsaved_discard') }}
                </button>
            </div>
        </div>
    </div>
</div>

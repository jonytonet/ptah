{{--
    Ptah AI Chat Widget — Global floating button + chat panel
    Embedded via <livewire:ptah-ai-chat-widget /> in forge-dashboard-layout.
    Only rendered when at least one active AI provider is configured.
--}}
<div>
@if($available)
<div
    x-data="{
        open: @entangle('isOpen'),

        /*
            O rascunho e local de proposito. Enquanto era `wire:model.live`, o
            valor do textarea era estado do SERVIDOR, e o servidor zerava esse
            valor ao enviar. Como `processAiMessage` e uma requisicao separada e
            lenta (espera o modelo), tudo que o usuario digitava durante a
            espera existia so no navegador — e quando aquela resposta chegava, o
            Livewire remoldava o textarea para a string vazia do snapshot e o
            texto desaparecia. Rascunho que so o navegador conhece nao pode ser
            sobrescrito por um snapshot velho.
        */
        draft: '',

        /* Tela cheia no desktop. Fica no localStorage: e preferencia de
           dispositivo, e mandar isso pro servidor custaria uma requisicao e um
           campo no snapshot para algo que a CSS resolve. */
        expanded: false,

        init() {
            try {
                this.expanded = localStorage.getItem('ptah:ai:expanded') === '1';
            } catch (e) {
                /* janela privada, cookies bloqueados: segue no modo compacto */
            }

            this.$watch('open', value => { if (value) { this.scrollToBottom(); this.focusInput(); } });
            this.$watch('expanded', value => {
                try { localStorage.setItem('ptah:ai:expanded', value ? '1' : '0'); } catch (e) {}
                this.scrollToBottom();
            });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msgList;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        focusInput() {
            this.$nextTick(() => { if (this.$refs.ta) this.$refs.ta.focus(); });
        },

        /* Cresce com o conteudo e para no teto, onde passa a rolar. O
           `overflow` alterna junto: preso em `hidden`, o texto alem do teto
           ficava invisivel E inalcancavel. */
        grow() {
            const ta = this.$refs.ta;
            if (!ta) return;
            const max = this.expanded || window.innerWidth < 640 ? 220 : 128;
            ta.style.height = 'auto';
            const next = Math.min(ta.scrollHeight, max);
            ta.style.height = next + 'px';
            ta.style.overflowY = ta.scrollHeight > max ? 'auto' : 'hidden';
        },

        resetGrow() {
            const ta = this.$refs.ta;
            if (!ta) return;
            ta.style.height = '';
            ta.style.overflowY = 'hidden';
        },

        submit() {
            const text = this.draft.trim();
            if (text === '' || this.$wire.loading) return;

            this.draft = '';
            this.resetGrow();
            this.$wire.send(text);
        },

        onEnter(e) {
            /* Shift+Enter e nova linha: nao intercepta, deixa o navegador
               inserir no lugar do cursor. O codigo anterior dava .prevent em
               toda tecla Enter e depois concatenava '
' no FIM do texto,
               ignorando onde o cursor estava. */
            if (e.shiftKey) return;

            e.preventDefault();
            this.submit();
        }
    }"
    @ai-message-sent.window="scrollToBottom()"
    @ai-draft-clear.window="draft = ''; resetGrow()"
    @keydown.escape.window="if (open) open = false"
    class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
>

    {{-- ─── Chat Panel ────────────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        {{-- No celular e sempre tela cheia: um painel de 320px numa tela de 360
             deixava a conversa numa coluna estreita com o teclado virtual por
             cima. Utilitarios `max-sm:` em vez de uma media query no
             ptah-components.css de proposito — o parser das fixtures golden
             nao e ciente de media query e ja gravou valor errado por isso. --}}
        class="ptah-c-chat_panel shadow-2xl border border-gray-200 dark:border-slate-700 flex flex-col overflow-hidden
               w-80 sm:w-96 rounded-2xl max-h-[min(560px,calc(100vh_-_100px))]
               max-sm:fixed max-sm:inset-0 max-sm:w-full max-sm:max-h-none max-sm:rounded-none max-sm:border-0"
        :class="expanded ? 'sm:fixed sm:inset-0 sm:w-full sm:max-w-none sm:max-h-none sm:rounded-none' : ''"
        role="dialog"
        aria-modal="false"
        aria-label="{{ __('ptah::ui.ai_widget_title') }}"
    >
        {{-- Panel header --}}
        <div class="flex items-center justify-between bg-primary px-4 py-3 text-white flex-shrink-0">
            <div class="flex items-center gap-2">
                <i class="bx bx-bot text-xl"></i>
                <span class="font-semibold text-sm">{{ __('ptah::ui.ai_widget_title') }}</span>
            </div>
            <div class="flex items-center gap-1">
                @auth
                <button wire:click="toggleHistory"
                        title="{{ __('ptah::ui.ai_widget_history') }}"
                        @class(['ptah-c-chat_hdr_btn rounded p-1 transition-colors', 'is-active' => $showHistory])>
                    <i class="bx bx-history text-lg"></i>
                </button>
                @endauth
                <button wire:click="newConversation"
                        title="{{ __('ptah::ui.ai_widget_new_chat') }}"
                        class="ptah-c-chat_hdr_btn rounded p-1 transition-colors">
                    <i class="bx bx-edit text-lg"></i>
                </button>
                {{-- Escondido no celular: la o painel ja ocupa a tela toda, e o
                     controle nao teria estado para alternar. --}}
                <button @click="expanded = !expanded"
                        type="button"
                        class="ptah-c-chat_hdr_btn hidden sm:block rounded p-1 transition-colors"
                        :title="expanded ? '{{ __('ptah::ui.ai_widget_collapse') }}' : '{{ __('ptah::ui.ai_widget_expand') }}'"
                        :aria-label="expanded ? '{{ __('ptah::ui.ai_widget_collapse') }}' : '{{ __('ptah::ui.ai_widget_expand') }}'">
                    <i class="bx text-lg" :class="expanded ? 'bx-collapse-alt' : 'bx-expand-alt'"></i>
                </button>
                <button @click="open = false"
                        aria-label="{{ __('ptah::ui.ai_widget_close') }}"
                        class="ptah-c-chat_hdr_btn rounded p-1 transition-colors">
                    <i class="bx bx-x text-xl"></i>
                </button>
            </div>
        </div>

        {{--
            Provider picker — only when there is an actual choice to make.
            With a single configured provider the control is pure noise in a
            panel this narrow, so it does not render at all.

            The selected id is client-writable on purpose (choosing is the
            point); AiProviderConfigService::resolveForTurn() validates that it
            names an ACTIVE config and falls back to the default otherwise, so a
            stale value in a long-open tab degrades instead of failing.
        --}}
        @if(!$showHistory && count($providerOptions) > 1)
        <div class="flex items-center gap-2 px-3 py-2 border-b ptah-c-chat_provider_bar flex-shrink-0">
            <i class="bx bx-chip text-base ptah-c-chat_label" aria-hidden="true"></i>
            <label for="ptah-ai-provider" class="sr-only">{{ __('ptah::ui.ai_widget_provider') }}</label>
            <select id="ptah-ai-provider"
                    wire:model.live="selectedConfigId"
                    class="flex-1 min-w-0 rounded-md border px-2 py-1 text-xs ptah-c-chat_provider_select">
                @foreach($providerOptions as $option)
                    <option value="{{ $option['id'] }}">
                        {{ $option['name'] }} — {{ $option['model'] }}@if($option['is_default']) ({{ __('ptah::ui.ai_widget_provider_default') }})@endif
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- History panel (authenticated users only) --}}
        @auth
        @if($showHistory)
        <div class="flex-1 overflow-y-auto flex flex-col">
            <div class="px-3 pt-3 pb-1">
                <p class="text-xs font-medium ptah-c-chat_label uppercase tracking-wide">
                    {{ __('ptah::ui.ai_widget_history') }}
                </p>
            </div>
            @if(empty($conversations))
                <div class="flex flex-col items-center justify-center flex-1 py-10 text-center text-gray-400 dark:text-slate-500">
                    <i class="bx bx-chat text-3xl mb-2"></i>
                    <p class="text-sm">{{ __('ptah::ui.ai_widget_no_history') }}</p>
                </div>
            @else
                <div class="px-2 pb-2 space-y-0.5">
                    @foreach($conversations as $conv)
                        <button
                            wire:click="loadConversation({{ $conv['id'] }})"
                            class="w-full text-left rounded-lg px-3 py-2.5 ptah-c-chat_hist_item transition-colors group {{ $conv['id'] === $conversationId ? 'bg-primary/10 dark:bg-primary/20' : '' }}"
                        >
                            <p class="text-sm font-medium ptah-c-chat_hist_title truncate group-hover:text-primary {{ $conv['id'] === $conversationId ? 'text-primary' : '' }}">
                                {{ $conv['title'] }}
                            </p>
                            <p class="text-xs ptah-c-chat_label mt-0.5">{{ $conv['date'] }}</p>
                        </button>
                    @endforeach
                    @if(count($conversations) >= $historyLimit)
                        <button
                            wire:click="loadMoreHistory"
                            class="w-full text-center text-xs text-primary hover:underline py-2 mt-0.5"
                        >
                            {{ __('ptah::ui.ai_widget_load_more') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>
        @endif
        @endauth

        {{-- Message list --}}
        @if(!$showHistory)
        <div
            x-ref="msgList"
            class="flex-1 overflow-y-auto px-4 py-3 space-y-3 scrollbar-none"
        >
            @if(empty($messages))
                <div class="flex flex-col items-center justify-center h-full py-8 text-center text-gray-400 dark:text-slate-500">
                    <i class="bx bx-bot text-4xl mb-2"></i>
                    <p class="text-sm">{{ __('ptah::ui.ai_widget_empty_hint') }}</p>
                </div>
            @else
                @foreach($messages as $msg)
                    @if($msg['role'] === 'user')
                        {{-- User message --}}
                        <div class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl rounded-tr-sm bg-primary px-3 py-2 text-sm text-white shadow-sm">
                                {!! nl2br(e($msg['content'])) !!}
                            </div>
                        </div>
                    @else
                        {{-- Assistant message --}}
                        <div class="flex items-start gap-2">
                            <div class="flex-shrink-0 w-7 h-7 rounded-full ptah-c-chat_avatar flex items-center justify-center mt-0.5">
                                <i class="bx bx-bot text-sm text-primary"></i>
                            </div>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm ptah-c-chat_bubble px-3 py-2 text-sm shadow-sm">
                                {!! nl2br(e($msg['content'])) !!}
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif

            {{-- Loading / streaming indicator.
                 The bubble is the wire:stream target: it starts with the animated
                 dots and is replaced by the streamed answer as tokens arrive. --}}
            @if($loading)
                <div class="flex items-start gap-2">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full ptah-c-chat_avatar flex items-center justify-center mt-0.5">
                        <i class="bx bx-bot text-sm text-primary"></i>
                    </div>
                    <div class="max-w-[85%] rounded-2xl rounded-tl-sm ptah-c-chat_bubble px-3 py-2 text-sm shadow-sm">
                        <div wire:stream="ai-stream">
                            <span class="inline-flex items-center gap-1 py-1">
                                <span class="block w-2 h-2 rounded-full ptah-c-chat_dot animate-wave" style="animation-delay: 0ms"></span>
                                <span class="block w-2 h-2 rounded-full ptah-c-chat_dot animate-wave" style="animation-delay: 150ms"></span>
                                <span class="block w-2 h-2 rounded-full ptah-c-chat_dot animate-wave" style="animation-delay: 300ms"></span>
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Error message --}}
            @if($errorMsg)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/50 px-3 py-2 text-xs text-red-600 dark:text-red-400">
                    <i class="bx bx-error-circle mr-1"></i> {{ $errorMsg }}
                </div>
            @endif
        </div>

        {{-- Input area --}}
        @if(!$showHistory)
        <div class="border-t border-gray-100 dark:border-slate-700 px-3 py-3 flex-shrink-0">
            <div class="flex items-end gap-2">
                {{-- Sem wire:model: o rascunho e local (ver o x-data da raiz e
                     o docblock de AiChatWidget::send). `wire:ignore` para que
                     nem o morph do Livewire toque neste no — e o morph, com um
                     snapshot velho, que apagava o texto.

                     Tambem NAO fica desabilitado durante a resposta: digitar a
                     proxima pergunta enquanto a IA responde e justamente o que
                     as pessoas fazem. So o envio e que espera. --}}
                <textarea
                    wire:ignore
                    x-ref="ta"
                    x-model="draft"
                    rows="1"
                    placeholder="{{ __('ptah::ui.ai_widget_placeholder') }}"
                    aria-label="{{ __('ptah::ui.ai_widget_placeholder') }}"
                    class="flex-1 resize-none rounded-xl border border-gray-200 dark:border-slate-600 ptah-c-chat_input px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/50"
                    style="min-height: 38px; overflow-y: hidden;"
                    @input="grow()"
                    @keydown.enter="onEnter($event)"
                ></textarea>
                <button
                    type="button"
                    @click="submit()"
                    x-bind:disabled="draft.trim() === '' || $wire.loading"
                    class="flex-shrink-0 w-9 h-9 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    title="{{ __('ptah::ui.ai_widget_send') }}"
                >
                    <svg wire:loading.remove wire:target="send,processAiMessage" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <svg wire:loading wire:target="send,processAiMessage" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                </button>
            </div>
            {{-- Era `text-gray-300 dark:text-slate-600`: cor fixa e, no claro,
                 cerca de 1,5:1 sobre branco — texto que existe e nao se le.
                 Tokenizado, como o resto do pacote. --}}
            <p class="ptah-c-chat_hint mt-1.5 text-center text-[10px]">
                {{ __('ptah::ui.ai_widget_keyboard_hint') }}
            </p>
        </div>
        @endif
        {{-- /Input area --}}
        @endif
        {{-- /Message list --}}
    </div>

    {{-- ─── Floating toggle button ───────────────────────────────────────
         Some quando o painel toma a tela — expandido no desktop, ou aberto no
         celular, onde tela cheia e o unico modo. Um botao flutuante sobre um
         painel de tela cheia cobre conteudo e passa a ser um segundo "fechar"
         concorrendo com o do cabecalho. --}}
    <button
        @click="open = !open"
        x-show="!(expanded && open)"
        :class="open ? 'max-sm:hidden' : ''"
        class="group w-14 h-14 rounded-full bg-primary text-white shadow-lg flex items-center justify-center hover:bg-primary-dark hover:scale-105 transition-all duration-200 active:scale-95"
        :title="open ? '{{ __('ptah::ui.ai_widget_close') }}' : '{{ __('ptah::ui.ai_widget_open') }}'"
        :aria-expanded="open ? 'true' : 'false'"
    >
        <i x-show="!open" class="bx bx-bot text-2xl"></i>
        <i x-show="open" x-cloak class="bx bx-x text-2xl"></i>
    </button>
</div>
@endif
</div>

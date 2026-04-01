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
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msgList;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    @ai-message-sent.window="scrollToBottom()"
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
        class="w-80 sm:w-96 rounded-2xl bg-white dark:bg-slate-800 shadow-2xl border border-gray-200 dark:border-slate-700 flex flex-col overflow-hidden"
        style="max-height: min(560px, calc(100vh - 100px));"
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
                        class="rounded p-1 transition-colors {{ $showHistory ? 'text-white bg-white/20' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                    <i class="bx bx-history text-lg"></i>
                </button>
                @endauth
                <button wire:click="newConversation"
                        title="{{ __('ptah::ui.ai_widget_new_chat') }}"
                        class="rounded p-1 text-white/70 hover:text-white hover:bg-white/10 transition-colors">
                    <i class="bx bx-edit text-lg"></i>
                </button>
                <button @click="open = false"
                        class="rounded p-1 text-white/70 hover:text-white hover:bg-white/10 transition-colors">
                    <i class="bx bx-x text-xl"></i>
                </button>
            </div>
        </div>

        {{-- History panel (authenticated users only) --}}
        @auth
        @if($showHistory)
        <div class="flex-1 overflow-y-auto flex flex-col">
            <div class="px-3 pt-3 pb-1">
                <p class="text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wide">
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
                            class="w-full text-left rounded-lg px-3 py-2.5 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors group {{ $conv['id'] === $conversationId ? 'bg-primary/10 dark:bg-primary/20' : '' }}"
                        >
                            <p class="text-sm font-medium text-gray-800 dark:text-slate-200 truncate group-hover:text-primary {{ $conv['id'] === $conversationId ? 'text-primary' : '' }}">
                                {{ $conv['title'] }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ $conv['date'] }}</p>
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
                            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mt-0.5">
                                <i class="bx bx-bot text-sm text-primary"></i>
                            </div>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-gray-100 dark:bg-slate-700 px-3 py-2 text-sm text-gray-800 dark:text-slate-200 shadow-sm">
                                {!! nl2br(e($msg['content'])) !!}
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif

            {{-- Loading indicator --}}
            @if($loading)
                <div class="flex items-start gap-2">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bx bx-bot text-sm text-primary"></i>
                    </div>
                    <div class="rounded-2xl rounded-tl-sm bg-gray-100 dark:bg-slate-700 px-3 py-3 flex items-center gap-1 shadow-sm">
                        <span class="block w-2 h-2 rounded-full bg-gray-400 dark:bg-slate-400 animate-wave" style="animation-delay: 0ms"></span>
                        <span class="block w-2 h-2 rounded-full bg-gray-400 dark:bg-slate-400 animate-wave" style="animation-delay: 150ms"></span>
                        <span class="block w-2 h-2 rounded-full bg-gray-400 dark:bg-slate-400 animate-wave" style="animation-delay: 300ms"></span>
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
                <textarea
                    wire:model.live="userInput"
                    rows="1"
                    placeholder="{{ __('ptah::ui.ai_widget_placeholder') }}"
                    class="flex-1 resize-none rounded-xl border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-slate-200 dark:placeholder-slate-400 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/50 max-h-32 overflow-hidden"
                    style="min-height: 38px; height: 38px;"
                    @keydown.enter.prevent="
                        if (!$wire.loading && !$event.shiftKey) {
                            $wire.set('userInput', $event.target.value);
                            $wire.send();
                        } else if ($event.shiftKey) {
                            $event.target.value += '\n';
                            $wire.set('userInput', $event.target.value);
                        }
                    "
                    @input="$event.target.style.height = 'auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 128) + 'px';"
                    wire:loading.attr="disabled"
                    wire:target="send,processAiMessage"
                ></textarea>
                <button
                    wire:click="send"
                    wire:loading.attr="disabled"
                    wire:target="send,processAiMessage"
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
            <p class="mt-1.5 text-center text-[10px] text-gray-300 dark:text-slate-600">
                {{ __('ptah::ui.ai_widget_keyboard_hint') }}
            </p>
        </div>
        @endif
        {{-- /Input area --}}
        @endif
        {{-- /Message list --}}
    </div>

    {{-- ─── Floating toggle button ─────────────────────────────────────── --}}
    <button
        @click="open = !open"
        class="group w-14 h-14 rounded-full bg-primary text-white shadow-lg flex items-center justify-center hover:bg-primary-dark hover:scale-105 transition-all duration-200 active:scale-95"
        :title="open ? '{{ __('ptah::ui.ai_widget_close') }}' : '{{ __('ptah::ui.ai_widget_open') }}'"
    >
        <i x-show="!open" class="bx bx-bot text-2xl"></i>
        <i x-show="open" x-cloak class="bx bx-x text-2xl"></i>
    </button>
</div>
@endif
</div>

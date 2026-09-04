{{-- ptah::livewire.ai.ai-model-config-list --}}
<div>
    {{-- Header --}}
    <x-forge-page-header
        :title="__('ptah::ui.ai_config_title')"
        :subtitle="__('ptah::ui.ai_config_subtitle')"
    />

    {{-- ─── How-to guide ─────────────────────────────────────────────────── --}}
    {{--
        forge-alert does not fit here: it renders a single static message, not
        a collapsible disclosure with a chevron toggle and a 6-card provider
        grid. Kept as hand-rolled markup, but repainted onto the same primary
        accent tokens forge-alert itself uses for its "primary" variant
        (border-primary/bg-primary-light/text-primary-*, see forge-alert.blade.php)
        instead of a raw blue-* palette — the crud-config "Guia de uso" accordion
        (same disclosure shape) follows the same border-primary/bg-primary-light
        convention.
    --}}
    <div x-data="{ open: false }" class="ptah-c-ai_panel mb-6 rounded-lg border bg-primary-light dark:bg-slate-800/60 p-4">
        <button @click="open = !open"
                class="ptah-c-ai_panel_ttl flex w-full items-center justify-between gap-2 text-sm font-medium">
            <span class="flex items-center gap-2">
                <i class="bx bx-info-circle text-lg"></i>
                {{ __('ptah::ui.ai_config_how_to_title') }}
            </span>
            <i :class="open ? 'bx-chevron-up' : 'bx-chevron-down'" class="bx text-lg transition-transform"></i>
        </button>

        <div x-show="open" x-collapse class="ptah-c-ai_panel_txt mt-3 space-y-3 text-sm">
            <p>{{ __('ptah::ui.ai_config_how_to_intro') }}</p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-md ptah-c-ai_card p-3 shadow-sm">
                    <p class="font-semibold ptah-c-ai_card_ttl">OpenAI</p>
                    <p class="mt-1 text-xs ptah-c-ai_card_desc">{{ __('ptah::ui.ai_config_how_to_openai') }}</p>
                    <p class="mt-1 text-xs font-mono ptah-c-ai_card_url">platform.openai.com/api-keys</p>
                </div>
                <div class="rounded-md ptah-c-ai_card p-3 shadow-sm">
                    <p class="font-semibold ptah-c-ai_card_ttl">Anthropic (Claude)</p>
                    <p class="mt-1 text-xs ptah-c-ai_card_desc">{{ __('ptah::ui.ai_config_how_to_anthropic') }}</p>
                    <p class="mt-1 text-xs font-mono ptah-c-ai_card_url">console.anthropic.com/settings/keys</p>
                </div>
                <div class="rounded-md ptah-c-ai_card p-3 shadow-sm">
                    <p class="font-semibold ptah-c-ai_card_ttl">Google Gemini</p>
                    <p class="mt-1 text-xs ptah-c-ai_card_desc">{{ __('ptah::ui.ai_config_how_to_gemini') }}</p>
                    <p class="mt-1 text-xs font-mono ptah-c-ai_card_url">aistudio.google.com/app/apikey</p>
                </div>
                <div class="rounded-md ptah-c-ai_card p-3 shadow-sm">
                    <p class="font-semibold ptah-c-ai_card_ttl">Ollama (Local)</p>
                    <p class="mt-1 text-xs ptah-c-ai_card_desc">{{ __('ptah::ui.ai_config_how_to_ollama') }}</p>
                    <p class="mt-1 text-xs font-mono ptah-c-ai_card_url">ollama.com — {{ __('ptah::ui.ai_config_api_key_optional') }}</p>
                </div>
                <div class="rounded-md ptah-c-ai_card p-3 shadow-sm">
                    <p class="font-semibold ptah-c-ai_card_ttl">Groq</p>
                    <p class="mt-1 text-xs ptah-c-ai_card_desc">{{ __('ptah::ui.ai_config_how_to_groq') }}</p>
                    <p class="mt-1 text-xs font-mono ptah-c-ai_card_url">console.groq.com/keys</p>
                </div>
                <div class="rounded-md ptah-c-ai_card p-3 shadow-sm">
                    <p class="font-semibold ptah-c-ai_card_ttl">Mistral</p>
                    <p class="mt-1 text-xs ptah-c-ai_card_desc">{{ __('ptah::ui.ai_config_how_to_mistral') }}</p>
                    <p class="mt-1 text-xs font-mono ptah-c-ai_card_url">console.mistral.ai/api-keys</p>
                </div>
            </div>

            <p class="ptah-c-ai_panel_ttl text-xs">{{ __('ptah::ui.ai_config_how_to_note') }}</p>
        </div>
    </div>

    {{-- ─── Feedback messages ─────────────────────────────────────────────── --}}
    @if($successMsg)
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
            <i class="bx bx-check-circle text-green-500"></i> {{ $successMsg }}
        </div>
    @endif
    @if($errorMsg)
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
            <i class="bx bx-error-circle text-red-500"></i> {{ $errorMsg }}
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border rounded-md">
        <x-forge-button wire:click="create" color="primary" size="sm">
            <x-slot name="icon">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </x-slot>
            {{ __('ptah::ui.btn_new') }}
        </x-forge-button>

        <div class="flex-1 min-w-[180px] max-w-xs">
            <x-forge-input
                wire:model.live.debounce.300ms="search"
                type="search"
                :placeholder="__('ptah::ui.search_placeholder')"
                iconBefore='<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/></svg>'
            />
        </div>
    </div>

    {{-- ─── Table section ─────────────────────────────────────────────────── --}}
    <div class="ptah-module-table overflow-x-auto border rounded-md">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider ptah-c-th_text cursor-pointer" wire:click="sort('name')">
                        {{ __('ptah::ui.ai_config_name') }}
                        @if($sort === 'name') <i class="bx bx-{{ $direction === 'asc' ? 'up' : 'down' }}-arrow-alt text-xs"></i> @endif
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider ptah-c-th_text">{{ __('ptah::ui.ai_config_provider') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider ptah-c-th_text">{{ __('ptah::ui.ai_config_model') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider ptah-c-th_text">{{ __('ptah::ui.ai_config_status') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider ptah-c-th_text">{{ __('ptah::ui.ai_config_default') }}</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider ptah-c-th_text">{{ __('ptah::ui.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <tr class="transition-colors ptah-c-mod_row">
                        <td class="whitespace-nowrap px-3 py-2.5 font-medium text-dark">
                            {{ $row->name }}
                            @if($row->notes)
                                <p class="text-xs ptah-c-ai_hint font-normal">{{ Str::limit($row->notes, 60) }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            <span class="inline-flex items-center gap-1 rounded-full ptah-c-ai_chip px-2 py-0.5 text-xs font-medium ptah-c-ai_chip_text">
                                <i class="bx bx-chip"></i>
                                {{ $providers[$row->provider] ?? $row->provider }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs ptah-c-ai_hint">{{ $row->model }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            @if($row->is_active)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                    {{ __('ptah::ui.ai_config_active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full ptah-c-ai_chip px-2 py-0.5 text-xs font-medium ptah-c-ai_hint">
                                    <span class="h-1.5 w-1.5 rounded-full ptah-c-ai_dot"></span>
                                    {{ __('ptah::ui.ai_config_inactive') }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            @if($row->is_default)
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    <i class="bx bx-star text-blue-500"></i>
                                    {{ __('ptah::ui.ai_config_is_default') }}
                                </span>
                            @else
                                <button wire:click="setDefault({{ $row->id }})"
                                        class="text-xs ptah-c-ai_hint hover:text-primary transition-colors">
                                    {{ __('ptah::ui.ai_config_set_default') }}
                                </button>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            <div class="flex justify-end gap-2">
                                <button wire:click="edit({{ $row->id }})"
                                        title="{{ __('ptah::ui.btn_edit_title') }}"
                                        class="rounded p-1 ptah-c-ai_hint ptah-c-ai_icon_btn hover:text-primary transition-colors">
                                    <i class="bx bx-pencil text-base"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $row->id }})"
                                        title="{{ __('ptah::ui.btn_delete_title') }}"
                                        class="rounded p-1 ptah-c-ai_hint hover:bg-red-50 hover:text-danger transition-colors">
                                    <i class="bx bx-trash text-base"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-md ptah-c-ai_chip">
                                    <i class="bx bx-bot text-3xl ptah-c-ai_hint"></i>
                                </div>
                                <p class="text-sm font-semibold ptah-c-ai_card_ttl">{{ __('ptah::ui.empty_title') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginação --}}
    @if ($rows->hasPages())
        <div class="mt-4">{{ $rows->links('ptah::components.forge-pagination') }}</div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Create / Edit modal                                                    --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showModal') }">
        <x-forge-modal
            title="{{ $isEditing ? __('ptah::ui.modal_edit_prefix').' '.__('ptah::ui.ai_config_title') : __('ptah::ui.modal_new_prefix').' '.__('ptah::ui.ai_config_title') }}"
            subtitle="{{ $isEditing ? __('ptah::ui.modal_edit_subtitle') : __('ptah::ui.modal_create_subtitle') }}"
            size="2xl"
        >
            <form wire:submit.prevent="save" id="ai-config-form" class="space-y-4">

                {{-- Name --}}
                <x-forge-input
                    wire:model="name"
                    label="{{ __('ptah::ui.ai_config_name') }}"
                    required
                    :error="$errors->first('name')"
                />

                {{-- Provider + Model --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-forge-select
                            wire:model.live="provider"
                            label="{{ __('ptah::ui.ai_config_provider') }}"
                            required
                            :options="collect($providers)->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
                            :selected="$provider"
                            :error="$errors->first('provider')"
                        />
                    </div>
                    <x-forge-input
                        wire:model="model"
                        label="{{ __('ptah::ui.ai_config_model') }}"
                        placeholder="e.g. gpt-4o-mini"
                        required
                        :error="$errors->first('model')"
                    />
                </div>

                {{-- API Key --}}
                <div>
                    <label class="block text-xs font-medium ptah-c-form_lbl mb-1">
                        {{ __('ptah::ui.ai_config_api_key') }}
                        @if($isEditing)
                            <span class="font-normal ptah-c-ai_hint">({{ __('ptah::ui.ai_config_api_key_leave_blank') }})</span>
                        @elseif($provider === 'ollama')
                            <span class="font-normal ptah-c-ai_hint">({{ __('ptah::ui.ai_config_api_key_optional') }})</span>
                        @else
                            <span class="text-red-500">*</span>
                        @endif
                    </label>
                    <x-forge-input
                        type="password"
                        wire:model="api_key"
                        autocomplete="new-password"
                        placeholder="{{ $isEditing ? '••••••••' : ($provider === 'ollama' ? __('ptah::ui.ai_config_api_key_optional') : '') }}"
                        :error="$errors->first('api_key')"
                    />
                </div>

                {{-- Custom Endpoint --}}
                <x-forge-input
                    type="url"
                    wire:model="api_endpoint"
                    label="{{ __('ptah::ui.ai_config_endpoint') }}"
                    placeholder="https://..."
                    :error="$errors->first('api_endpoint')"
                    :message="__('ptah::ui.ai_config_endpoint_hint')"
                />

                {{-- Max Tokens + Temperature --}}
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input
                        type="number"
                        wire:model="max_tokens"
                        label="{{ __('ptah::ui.ai_config_max_tokens') }}"
                        min="1"
                        max="128000"
                        :error="$errors->first('max_tokens')"
                    />
                    <x-forge-input
                        type="number"
                        wire:model="temperature"
                        label="{{ __('ptah::ui.ai_config_temperature') }} (0-2)"
                        min="0"
                        max="2"
                        step="0.1"
                        :error="$errors->first('temperature')"
                    />
                </div>

                {{-- System Prompt --}}
                <x-forge-textarea
                    wire:model="system_prompt"
                    label="{{ __('ptah::ui.ai_config_system_prompt') }}"
                    placeholder="{{ __('ptah::ui.ai_config_system_prompt_placeholder') }}"
                    rows="3"
                    helper="{{ __('ptah::ui.ai_config_system_prompt_hint') }}"
                    :state="$errors->has('system_prompt') ? 'danger' : null"
                />
                @error('system_prompt') <p class="-mt-2 text-xs text-red-500">{{ $message }}</p> @enderror

                {{-- Flags --}}
                <div class="flex items-center gap-6 text-sm">
                    <label class="flex items-center gap-2 cursor-pointer ptah-c-ai_checkbox_label">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-primary">
                        {{ __('ptah::ui.ai_config_active') }}
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer ptah-c-ai_checkbox_label">
                        <input type="checkbox" wire:model="is_default" class="rounded border-gray-300 text-primary">
                        {{ __('ptah::ui.ai_config_is_default') }}
                    </label>
                </div>

                {{-- Notes --}}
                <x-forge-textarea
                    wire:model="notes"
                    label="{{ __('ptah::ui.ai_config_notes') }}"
                    rows="2"
                    :state="$errors->has('notes') ? 'danger' : null"
                />
                @error('notes') <p class="-mt-2 text-xs text-red-500">{{ $message }}</p> @enderror

            </form>

            <x-slot:footer>
                <x-forge-button color="light" @click="$wire.closeModal()">
                    {{ __('ptah::ui.btn_cancel') }}
                </x-forge-button>
                <x-forge-button
                    type="submit"
                    form="ai-config-form"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $isEditing ? __('ptah::ui.btn_save_changes') : __('ptah::ui.btn_create') }}
                    </span>
                    <span wire:loading wire:target="save">{{ __('ptah::ui.ai_widget_loading') }}</span>
                </x-forge-button>
            </x-slot:footer>
        </x-forge-modal>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Delete confirmation modal                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showDeleteModal') }">
        <x-forge-modal
            title="{{ __('ptah::ui.delete_title') }}"
            subtitle="{{ __('ptah::ui.delete_message') }}"
            size="sm"
        >
            <x-slot:footer>
                <x-forge-button color="light" @click="$wire.set('showDeleteModal', false)">
                    {{ __('ptah::ui.btn_cancel') }}
                </x-forge-button>
                <x-forge-button color="danger" wire:click="delete">
                    {{ __('ptah::ui.btn_delete') }}
                </x-forge-button>
            </x-slot:footer>
        </x-forge-modal>
    </div>

</div>

{{-- ptah::livewire.ai.ai-model-config-list --}}
<div class="ptah-page-header p-6">

    {{-- ─── Page header ──────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('ptah::ui.ai_config_title') }}</h1>
            <p class="mt-1 text-sm">{{ __('ptah::ui.ai_config_subtitle') }}</p>
        </div>
        <x-forge-button wire:click="create" size="sm">
            <x-slot:icon><i class="bx bx-plus text-base"></i></x-slot:icon>
            {{ __('ptah::ui.btn_new') }}
        </x-forge-button>
    </div>

    {{-- ─── How-to guide ─────────────────────────────────────────────────── --}}
    <div x-data="{ open: false }" class="mb-6 rounded-lg border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950/40 p-4">
        <button @click="open = !open"
                class="flex w-full items-center justify-between gap-2 text-sm font-medium text-blue-800 dark:text-blue-300">
            <span class="flex items-center gap-2">
                <i class="bx bx-info-circle text-lg"></i>
                {{ __('ptah::ui.ai_config_how_to_title') }}
            </span>
            <i :class="open ? 'bx-chevron-up' : 'bx-chevron-down'" class="bx text-lg transition-transform"></i>
        </button>

        <div x-show="open" x-collapse class="mt-3 space-y-3 text-sm text-blue-900 dark:text-blue-200">
            <p>{{ __('ptah::ui.ai_config_how_to_intro') }}</p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-md bg-white dark:bg-slate-800 p-3 shadow-sm">
                    <p class="font-semibold dark:text-slate-200">OpenAI</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">{{ __('ptah::ui.ai_config_how_to_openai') }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400 dark:text-slate-500">platform.openai.com/api-keys</p>
                </div>
                <div class="rounded-md bg-white dark:bg-slate-800 p-3 shadow-sm">
                    <p class="font-semibold dark:text-slate-200">Anthropic (Claude)</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">{{ __('ptah::ui.ai_config_how_to_anthropic') }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400 dark:text-slate-500">console.anthropic.com/settings/keys</p>
                </div>
                <div class="rounded-md bg-white dark:bg-slate-800 p-3 shadow-sm">
                    <p class="font-semibold dark:text-slate-200">Google Gemini</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">{{ __('ptah::ui.ai_config_how_to_gemini') }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400 dark:text-slate-500">aistudio.google.com/app/apikey</p>
                </div>
                <div class="rounded-md bg-white dark:bg-slate-800 p-3 shadow-sm">
                    <p class="font-semibold dark:text-slate-200">Ollama (Local)</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">{{ __('ptah::ui.ai_config_how_to_ollama') }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400 dark:text-slate-500">ollama.com — {{ __('ptah::ui.ai_config_api_key_optional') }}</p>
                </div>
                <div class="rounded-md bg-white dark:bg-slate-800 p-3 shadow-sm">
                    <p class="font-semibold dark:text-slate-200">Groq</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">{{ __('ptah::ui.ai_config_how_to_groq') }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400 dark:text-slate-500">console.groq.com/keys</p>
                </div>
                <div class="rounded-md bg-white dark:bg-slate-800 p-3 shadow-sm">
                    <p class="font-semibold dark:text-slate-200">Mistral</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">{{ __('ptah::ui.ai_config_how_to_mistral') }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400 dark:text-slate-500">console.mistral.ai/api-keys</p>
                </div>
            </div>

            <p class="text-xs text-blue-700 dark:text-blue-400">{{ __('ptah::ui.ai_config_how_to_note') }}</p>
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

    {{-- ─── Table section ─────────────────────────────────────────────────── --}}
    <div class="ptah-table-wrapper">

        {{-- Search --}}
        <div class="mb-4">
            <input type="search"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('ptah::ui.search_placeholder') }}"
                   class="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-md bg-white shadow-sm border border-gray-200">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 cursor-pointer" wire:click="sort('name')">
                            {{ __('ptah::ui.ai_config_name') }}
                            @if($sort === 'name') <i class="bx bx-{{ $direction === 'asc' ? 'up' : 'down' }}-arrow-alt text-xs"></i> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">{{ __('ptah::ui.ai_config_provider') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">{{ __('ptah::ui.ai_config_model') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">{{ __('ptah::ui.ai_config_status') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">{{ __('ptah::ui.ai_config_default') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">{{ __('ptah::ui.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $row)
                        <tr class="transition-colors">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-dark">
                                {{ $row->name }}
                                @if($row->notes)
                                    <p class="text-xs text-gray-400 font-normal">{{ Str::limit($row->notes, 60) }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                    <i class="bx bx-chip"></i>
                                    {{ $providers[$row->provider] ?? $row->provider }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500">{{ $row->model }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                @if($row->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                        {{ __('ptah::ui.ai_config_active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        {{ __('ptah::ui.ai_config_inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                @if($row->is_default)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                        <i class="bx bx-star text-blue-500"></i>
                                        {{ __('ptah::ui.ai_config_is_default') }}
                                    </span>
                                @else
                                    <button wire:click="setDefault({{ $row->id }})"
                                            class="text-xs text-gray-400 hover:text-primary transition-colors">
                                        {{ __('ptah::ui.ai_config_set_default') }}
                                    </button>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="edit({{ $row->id }})"
                                            title="{{ __('ptah::ui.btn_edit_title') }}"
                                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-primary transition-colors">
                                        <i class="bx bx-pencil text-base"></i>
                                    </button>
                                    <button wire:click="confirmDelete({{ $row->id }})"
                                            title="{{ __('ptah::ui.btn_delete_title') }}"
                                            class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-danger transition-colors">
                                        <i class="bx bx-trash text-base"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">
                                <i class="bx bx-bot text-3xl block mb-2 text-gray-300"></i>
                                {{ __('ptah::ui.empty_title') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($rows->hasPages())
                <div class="border-t px-4 py-3">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Create / Edit modal                                                    --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($showModal)
    <div x-data="{ open: true }" @close="$wire.closeModal()">
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
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        {{ __('ptah::ui.ai_config_api_key') }}
                        @if($isEditing)
                            <span class="font-normal text-gray-400">({{ __('ptah::ui.ai_config_api_key_leave_blank') }})</span>
                        @elseif($provider === 'ollama')
                            <span class="font-normal text-gray-400">({{ __('ptah::ui.ai_config_api_key_optional') }})</span>
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
                    <label class="flex items-center gap-2 cursor-pointer text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-primary">
                        {{ __('ptah::ui.ai_config_active') }}
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-gray-700">
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
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- Delete confirmation modal                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($showDeleteModal)
    <div x-data="{ open: true }" @close="$wire.set('showDeleteModal', false)">
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
    @endif

</div>

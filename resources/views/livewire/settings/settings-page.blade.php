{{-- ptah::livewire.settings.settings-page — tokens --ptah-* e forge-* apenas --}}
<div>
    <x-forge-page-header :title="__('ptah::ui.settings_title')" :subtitle="__('ptah::ui.settings_subtitle')" />

    @if (! $installed)
        <x-forge-alert color="warn">{{ __('ptah::ui.settings_not_installed') }}</x-forge-alert>
    @elseif ($groups === [])
        <x-forge-empty :title="__('ptah::ui.settings_none')" :description="__('ptah::ui.settings_none_hint')" />
    @else
        @if ($perCompany && $canEditGlobal)
            <div class="flex flex-wrap items-center gap-2 mb-4" role="group" aria-label="{{ __('ptah::ui.settings_scope') }}">
                <x-forge-button wire:click="editGlobal(false)" :color="$editingGlobal ? 'dark' : 'primary'" :flat="$editingGlobal" size="sm">{{ __('ptah::ui.settings_scope_company') }}</x-forge-button>
                <x-forge-button wire:click="editGlobal(true)" :color="$editingGlobal ? 'primary' : 'dark'" :flat="! $editingGlobal" size="sm">{{ __('ptah::ui.settings_scope_global') }}</x-forge-button>
            </div>
        @endif

        <form wire:submit="save" class="space-y-6">
            @foreach ($groups as $group => $defs)
                <section class="rounded-md border p-4" style="border-color: var(--ptah-line-strong); background: var(--ptah-surface)">
                    <h2 class="mb-4 text-sm font-semibold" style="color: var(--ptah-text-strong)">{{ $group }}</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($defs as $key => $def)
                            @php
                                $type = $def['type'] ?? 'text';
                                $error = $errorsBag[$key] ?? null;
                                $source = $sources[$key]['source'] ?? 'default';
                            @endphp
                            <div wire:key="setting-{{ $key }}" class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
                                @if ($type === 'boolean')
                                    <x-forge-switch wire:model="values.{{ $key }}" name="values.{{ $key }}" :label="$def['label']" />
                                @elseif ($type === 'select')
                                    <x-forge-select wire:model="values.{{ $key }}" name="values.{{ $key }}" :label="$def['label']" :error="$error"
                                        :options="collect($def['options'] ?? [])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()" />
                                @elseif ($type === 'textarea')
                                    <x-forge-textarea wire:model="values.{{ $key }}" name="values.{{ $key }}" :label="$def['label']" :error="$error" rows="4" />
                                @else
                                    <x-forge-input wire:model="values.{{ $key }}" name="values.{{ $key }}" :label="$def['label']" :error="$error"
                                        :type="match ($type) { 'integer', 'decimal' => 'number', 'email' => 'email', 'url' => 'url', 'date' => 'date', default => (! empty($def['secret']) ? 'password' : 'text') }"
                                        :step="$type === 'decimal' ? 'any' : null" />
                                @endif

                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs ptah-c-muted">
                                    @if (! empty($def['help']))<span>{{ $def['help'] }}</span>@endif
                                    @if (! $editingGlobal && $perCompany)
                                        @if ($source === 'company')
                                            <span>{{ __('ptah::ui.settings_source_company') }}</span>
                                            <button type="button" wire:click="resetToGlobal('{{ $key }}')" class="ptah-c-muted_link underline">{{ __('ptah::ui.settings_use_global') }}</button>
                                        @else
                                            <span>{{ __('ptah::ui.settings_source_'.$source) }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="flex justify-end">
                <x-forge-button type="submit" color="primary" wire:loading.attr="disabled">{{ __('ptah::ui.btn_save') }}</x-forge-button>
            </div>
        </form>
    @endif
</div>

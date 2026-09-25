{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Anexos do registro (HasCrudAttachments) ─────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@if ($showAttachmentsModal)
    @php $canWriteAttachments = $this->attachmentsCanWrite(); @endphp
    <x-forge-modal wire:model="showAttachmentsModal" :title="__('ptah::ui.attachments_title')" size="lg">
        <div class="space-y-4">
            @if ($canWriteAttachments)
                <div class="space-y-2">
                    <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-md border px-3 py-2 text-sm transition-colors ptah-c-btn">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        {{ __('ptah::ui.attachments_pick') }}
                        <input type="file" class="sr-only" wire:model="attachmentUploads" multiple />
                    </label>
                    <div wire:loading wire:target="attachmentUploads" class="text-xs ptah-c-muted">…</div>
                    @if ($attachmentUploads !== [])
                        <x-forge-button wire:click="uploadAttachments" wire:loading.attr="disabled" color="primary" size="sm">
                            {{ trans_choice('ptah::ui.attachments_send', count($attachmentUploads), ['count' => count($attachmentUploads)]) }}
                        </x-forge-button>
                    @endif
                    @error('attachmentUploads') <p class="text-xs ptah-c-field_err" role="alert">{{ $message }}</p> @enderror
                    @error('attachmentUploads.*') <p class="text-xs ptah-c-field_err" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($attachmentItems === [])
                <x-forge-empty :title="__('ptah::ui.attachments_empty')" />
            @else
                <ul class="divide-y rounded-md border" style="border-color: var(--ptah-line-strong)">
                    @foreach ($attachmentItems as $att)
                        <li wire:key="att-{{ $att['id'] }}" class="flex items-center justify-between gap-3 px-3 py-2" style="border-color: var(--ptah-line)">
                            <div class="min-w-0">
                                <button type="button" wire:click="downloadAttachment({{ $att['id'] }})"
                                    class="block truncate text-sm font-medium text-left hover:underline focus:outline-none focus-visible:underline"
                                    style="color: var(--ptah-primary-strong)">
                                    {{ $att['name'] }}
                                </button>
                                <p class="text-xs ptah-c-muted">{{ $att['size'] }} · {{ $att['when'] }}</p>
                            </div>
                            @if ($canWriteAttachments)
                                <x-forge-button wire:click="deleteAttachment({{ $att['id'] }})"
                                    wire:confirm="{{ __('ptah::ui.attachments_delete_confirm', ['name' => $att['name']]) }}"
                                    color="danger" flat size="sm">{{ __('ptah::ui.btn_delete') }}</x-forge-button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-slot name="footer">
            <x-forge-button wire:click="closeAttachments" color="dark" flat>{{ __('ptah::ui.modal_close') }}</x-forge-button>
        </x-slot>
    </x-forge-modal>
@endif

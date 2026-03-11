{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Confirmar Exclusão ─────────────────────────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: @entangle('showDeleteConfirm') }" @close="$wire.cancelDelete()">
    <x-forge-modal :title="__('ptah::ui.delete_title')" size="sm">

        <div class="flex items-center gap-4">
            <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-md bg-red-50 dark:bg-red-900/30 ring-4 ring-red-50 dark:ring-red-900/20">
                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('ptah::ui.delete_message') }}</p>
        </div>

        <x-slot name="footer">
            <x-forge-button wire:click="cancelDelete" color="dark" flat>{{ __('ptah::ui.btn_cancel') }}</x-forge-button>
            <x-forge-button wire:click="deleteRecord" color="danger">{{ __('ptah::ui.btn_delete') }}</x-forge-button>
        </x-slot>
    </x-forge-modal>
</div>

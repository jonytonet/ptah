{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Confirmar Exclusão ─────────────────────────────────────── --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@if ($showDeleteConfirm)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="cancelDelete"></div>
        <div class="relative w-full max-w-sm mx-4 overflow-hidden shadow-2xl rounded-2xl ptah-c-del_card">
            <div class="flex items-center gap-4 px-6 py-5 border-b ptah-c-modal_hd">
                <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 rounded-xl bg-red-50 ring-4 ring-red-50">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold ptah-c-modal_ttl">{{ __('ptah::ui.delete_title') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.delete_message') }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 ptah-c-del_ft">
                <x-forge-button wire:click="cancelDelete" color="dark" flat>{{ __('ptah::ui.btn_cancel') }}</x-forge-button>
                <x-forge-button wire:click="deleteRecord" color="danger">{{ __('ptah::ui.btn_delete') }}</x-forge-button>
            </div>
        </div>
    </div>
    @endteleport
@endif

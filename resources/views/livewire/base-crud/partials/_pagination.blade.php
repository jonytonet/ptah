{{-- ── Paginação ────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mt-4 text-sm ptah-c-pag">
    <span>
        {{ __('ptah::ui.pagination', ['first' => $rows->firstItem() ?? 0, 'last' => $rows->lastItem() ?? 0, 'total' => $rows->total()]) }}
    </span>
    <div class="flex items-center gap-3">
        {{ $rows->links('ptah::components.forge-pagination') }}
        {{-- Jump-to-page --}}
        @if ($rows->lastPage() > 2)
            <div class="hidden md:flex items-center gap-1.5 text-xs"
                 x-data="{ pg: {{ $rows->currentPage() }} }"
                 x-init="$watch('$wire.page', v => { pg = v; })">
                <span class="text-gray-400 dark:text-slate-500">{{ __('ptah::ui.pagination_goto') }}</span>
                <input type="number" min="1" max="{{ $rows->lastPage() }}"
                    x-model.number="pg"
                    @keydown.enter="pg = Math.min(Math.max(1, pg), {{ $rows->lastPage() }}); $wire.gotoPage(pg)"
                    @blur="pg = Math.min(Math.max(1, pg), {{ $rows->lastPage() }}); $wire.gotoPage(pg)"
                    class="w-14 px-2 py-1 text-center text-xs rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30">
            </div>
        @endif
    </div>
</div>

{{-- ── Paginação ────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mt-4 text-sm ptah-c-pag">
    <span>
        {{ __('ptah::ui.pagination', ['first' => $rows->firstItem() ?? 0, 'last' => $rows->lastItem() ?? 0, 'total' => $rows->total()]) }}
    </span>
    <div>
        {{ $rows->links('ptah::components.forge-pagination') }}
    </div>
</div>

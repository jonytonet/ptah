{{--
    forge-pagination — Ptah Forge
    Pagination view compatible with $paginator->links('ptah::components.forge-pagination').
    Variables injected by Laravel LengthAwarePaginator:
      - $paginator : LengthAwarePaginator
      - $elements  : array (page numbers or "...")
--}}
@if ($paginator->hasPages())
<nav aria-label="{{ __('ptah::ui.pagination_nav_label') }}" class="ptah-pagination flex items-center justify-between gap-4">

    {{-- Mobile --}}
    <div class="flex items-center gap-2 md:hidden">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-2 text-sm font-medium rounded-md border opacity-40 cursor-not-allowed ptah-c-pag_btn_off">{{ __('ptah::ui.pagination_previous') }}</span>
        @else
            <button wire:click="$set('page', {{ $paginator->currentPage() - 1 }})"
                    class="px-3 py-2 text-sm font-medium rounded-md border transition-colors ptah-c-pag_btn">{{ __('ptah::ui.pagination_previous') }}</button>
        @endif

        <span class="text-sm text-gray-500 dark:text-slate-400">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <button wire:click="$set('page', {{ $paginator->currentPage() + 1 }})"
                    class="px-3 py-2 text-sm font-medium rounded-md border transition-colors ptah-c-pag_btn">{{ __('ptah::ui.pagination_next') }}</button>
        @else
            <span class="px-3 py-2 text-sm font-medium rounded-md border opacity-40 cursor-not-allowed ptah-c-pag_btn_off">{{ __('ptah::ui.pagination_next') }}</span>
        @endif
    </div>

    {{-- Desktop --}}
    <div class="hidden md:flex items-center gap-1">

        {{-- Botão < --}}
        @if ($paginator->onFirstPage())
            <span class="p-2 rounded-md cursor-not-allowed ptah-c-pag_icon" aria-label="{{ __('ptah::ui.pagination_previous_page') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </span>
        @else
            <button wire:click="$set('page', {{ $paginator->currentPage() - 1 }})"
                    class="p-2 rounded-md transition-colors ptah-c-pag_icon"
                    aria-label="{{ __('ptah::ui.pagination_previous_page') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
        @endif

        {{-- Números das páginas via $elements (padrão Laravel) --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 ptah-c-pag_gap">{{ $element }}</span>
            @elseif (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <button wire:click="$set('page', {{ $page }})"
                                aria-current="page"
                                aria-label="{{ __('ptah::ui.pagination_current_page') }}"
                                class="w-9 h-9 rounded-md text-sm font-medium bg-primary text-white transition-colors duration-150">
                            {{ $page }}
                        </button>
                    @else
                        <button wire:click="$set('page', {{ $page }})"
                                class="w-9 h-9 rounded-md text-sm font-medium transition-colors duration-150 ptah-c-pag_num">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Botão > --}}
        @if ($paginator->hasMorePages())
            <button wire:click="$set('page', {{ $paginator->currentPage() + 1 }})"
                    class="p-2 rounded-md transition-colors ptah-c-pag_icon"
                    aria-label="{{ __('ptah::ui.pagination_next_page') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        @else
            <span class="p-2 rounded-md cursor-not-allowed ptah-c-pag_icon" aria-label="{{ __('ptah::ui.pagination_next_page') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </span>
        @endif

    </div>

    <p class="text-xs hidden sm:block ptah-c-pag_sum">
        {{ __('ptah::ui.pagination_page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
    </p>
</nav>
@endif

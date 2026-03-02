{{--
    forge-pagination — Ptah Forge
    View de paginação compatível com $paginator->links('ptah::components.forge-pagination').
    Variáveis injetadas pelo Laravel LengthAwarePaginator:
      - $paginator : LengthAwarePaginator
      - $elements  : array (números de página ou "...")
--}}
@if ($paginator->hasPages())
<div class="ptah-pagination flex items-center justify-between gap-4">

    {{-- Mobile --}}
    <div class="flex items-center gap-2 md:hidden">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-400 opacity-40 cursor-not-allowed">← Anterior</span>
        @else
            <button wire:click="$set('page', {{ $paginator->currentPage() - 1 }})"
                    class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">← Anterior</button>
        @endif

        <span class="text-sm text-gray-500">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <button wire:click="$set('page', {{ $paginator->currentPage() + 1 }})"
                    class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">Próximo →</button>
        @else
            <span class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-400 opacity-40 cursor-not-allowed">Próximo →</span>
        @endif
    </div>

    {{-- Desktop --}}
    <div class="hidden md:flex items-center gap-1">

        {{-- Botão < --}}
        @if ($paginator->onFirstPage())
            <span class="p-2 rounded-xl text-gray-300 cursor-not-allowed">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </span>
        @else
            <button wire:click="$set('page', {{ $paginator->currentPage() - 1 }})"
                    class="p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
        @endif

        {{-- Números das páginas via $elements (padrão Laravel) --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-gray-400">{{ $element }}</span>
            @elseif (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <button wire:click="$set('page', {{ $page }})"
                                class="w-9 h-9 rounded-xl text-sm font-medium bg-primary text-white shadow-md shadow-primary/30 transition-all duration-200">
                            {{ $page }}
                        </button>
                    @else
                        <button wire:click="$set('page', {{ $page }})"
                                class="w-9 h-9 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100 transition-all duration-200">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Botão > --}}
        @if ($paginator->hasMorePages())
            <button wire:click="$set('page', {{ $paginator->currentPage() + 1 }})"
                    class="p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        @else
            <span class="p-2 rounded-xl text-gray-300 cursor-not-allowed">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </span>
        @endif

    </div>

    <p class="text-xs text-gray-400 hidden sm:block">
        Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
    </p>
</div>
@endif

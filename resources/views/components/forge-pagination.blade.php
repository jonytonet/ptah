{{--
    forge-pagination — Ptah Forge (Livewire 3)
    Props:
      - currentPage: int  (padrão: 1)
      - totalPages : int  (padrão: 1)
      - perPage    : int  (padrão: 15)
    Requer Livewire 3
--}}
@props([
    'currentPage' => 1,
    'totalPages'  => 1,
    'perPage'     => 15,
])

@php
    $currentPage = (int) $currentPage;
    $totalPages  = (int) $totalPages;
    $prev        = max(1, $currentPage - 1);
    $next        = min($totalPages, $currentPage + 1);

    $range = [];
    $delta = 2;
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $delta && $i <= $currentPage + $delta)) {
            $range[] = $i;
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-4']) }}>
    {{-- Mobile --}}
    <div class="flex items-center gap-2 md:hidden">
        <button
            @if($currentPage <= 1) disabled @endif
            wire:click="$set('page', {{ $prev }})"
            class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-600
                   hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >← Anterior</button>
        <span class="text-sm text-gray-500">{{ $currentPage }} / {{ $totalPages }}</span>
        <button
            @if($currentPage >= $totalPages) disabled @endif
            wire:click="$set('page', {{ $next }})"
            class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-600
                   hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >Próximo →</button>
    </div>

    {{-- Desktop --}}
    <div class="hidden md:flex items-center gap-1">
        <button
            @if($currentPage <= 1) disabled @endif
            wire:click="$set('page', {{ $prev }})"
            class="p-2 rounded-xl text-gray-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        @php $lastPage = null; @endphp
        @foreach($range as $page)
            @if($lastPage !== null && $page - $lastPage > 1)
                <span class="px-2 text-gray-400">…</span>
            @endif
            <button
                wire:click="$set('page', {{ $page }})"
                class="w-9 h-9 rounded-xl text-sm font-medium transition-all duration-200
                    {{ $page === $currentPage
                        ? 'bg-primary text-white shadow-md shadow-primary/30'
                        : 'text-gray-600 hover:bg-gray-100'
                    }}"
            >{{ $page }}</button>
            @php $lastPage = $page; @endphp
        @endforeach

        <button
            @if($currentPage >= $totalPages) disabled @endif
            wire:click="$set('page', {{ $next }})"
            class="p-2 rounded-xl text-gray-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    <p class="text-xs text-gray-400 hidden sm:block">
        Página {{ $currentPage }} de {{ $totalPages }}
    </p>
</div>

{{--
    forge-empty — Ptah Forge

    Generic empty state for custom screens outside the BaseCrud table (which
    already has its own — see _table.blade.php's `@empty` branch, now built on
    top of this same component so both stay visually identical).

    Props:
      - title       : string (optional)
      - description : string (optional)
    Slots:
      - icon : optional custom icon markup (defaults to a generic "no data" glyph)
      - cta  : optional call-to-action (button/link) rendered below the copy

    Tokenized (.ptah-c-empty_box/_ttl/_sub, same tokens the BaseCrud table
    empty state already used) and density-agnostic: every size/spacing here
    is a relative Tailwind utility (rem-based), so it follows whatever
    font-size/density the user picked in /profile without a recipe of its own.

    Usage:
        <x-forge-empty
            :title="__('No records yet')"
            :description="__('Create the first one to get started.')">
            <x-slot:icon>
                <svg class="w-8 h-8 text-slate-400" ...></svg>
            </x-slot:icon>
            <x-slot:cta>
                <x-forge-button color="primary" wire:click="create">New</x-forge-button>
            </x-slot:cta>
        </x-forge-empty>
--}}
@props([
    'title'       => '',
    'description' => '',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 text-center']) }}>
    <div class="flex items-center justify-center w-16 h-16 rounded-full ptah-c-empty_box">
        @isset($icon)
            {{ $icon }}
        @else
            <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
        @endisset
    </div>

    @if ($title !== '' || $description !== '')
        <div>
            @if ($title !== '')
                <p class="text-sm font-semibold ptah-c-empty_ttl">{{ $title }}</p>
            @endif
            @if ($description !== '')
                <p class="text-xs mt-0.5 ptah-c-empty_sub">{{ $description }}</p>
            @endif
        </div>
    @endif

    @isset($cta)
        <div>{{ $cta }}</div>
    @endisset
</div>

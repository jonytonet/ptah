{{--
    forge-skeleton — Ptah Forge

    Loading placeholder for custom screens outside the BaseCrud table (which
    already has its own thin loading bar — see _table.blade.php).

    Props:
      - variant : text | title | avatar | card | table-row  (default: text)
      - count   : int, how many lines to repeat when variant="text" (default: 1)

    Tokenized (.ptah-c-skel / .ptah-c-skel_card) and animated with Tailwind's
    `animate-pulse`, which the package's global `prefers-reduced-motion` rule
    (ptah-components.css) already freezes for anyone who asked for less
    motion — nothing to opt into here.

    Usage:
        <x-forge-skeleton variant="title" />
        <x-forge-skeleton variant="text" :count="3" />
        <x-forge-skeleton variant="avatar" />
        <x-forge-skeleton variant="card" />
        <x-forge-skeleton variant="table-row" />
--}}
@props([
    'variant' => 'text',
    'count'   => 1,
])

@php
    $block = 'ptah-c-skel animate-pulse rounded';
    $lines = max(1, (int) $count);
@endphp

@if ($variant === 'title')
    <div {{ $attributes->merge(['class' => "{$block} h-5 w-1/3", 'aria-hidden' => 'true']) }}></div>

@elseif ($variant === 'avatar')
    <div {{ $attributes->merge(['class' => "{$block} rounded-full h-10 w-10", 'aria-hidden' => 'true']) }}></div>

@elseif ($variant === 'card')
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-3 p-4 border rounded-md ptah-c-skel_card', 'aria-hidden' => 'true']) }}>
        <div class="{{ $block }} h-5 w-1/2"></div>
        <div class="{{ $block }} h-3 w-full"></div>
        <div class="{{ $block }} h-3 w-5/6"></div>
    </div>

@elseif ($variant === 'table-row')
    <div {{ $attributes->merge(['class' => 'flex items-center gap-3 px-4 py-3', 'aria-hidden' => 'true']) }}>
        <div class="{{ $block }} h-4 w-1/4"></div>
        <div class="{{ $block }} h-4 w-1/6"></div>
        <div class="{{ $block }} h-4 flex-1"></div>
    </div>

@else
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-2', 'aria-hidden' => 'true']) }}>
        @for ($i = 0; $i < $lines; $i++)
            <div class="{{ $block }} h-3 {{ $lines > 1 && $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
@endif

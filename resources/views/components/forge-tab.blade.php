{{--
    forge-tab — Ptah Forge
    Sub-componente de <x-forge-tabs> (slot "tabs").
    Props:
      - key    : string  - tab identifier (informational, not applied by this component)
      - active : bool    - whether this tab is selected
      - color  : primary | success | danger | warn (default: primary)
    Accepts any extra attributes (wire:click, @click, etc.)
--}}
@props([
    'key'    => '',
    'active' => false,
    'color'  => 'primary',
])

@php
    // .ptah-c-tab_active_* (ptah-components.css) carries a dark-mode "-lite" tint —
    // the raw text-{color}/border-{color} pair reads fine in light mode but fails
    // (or comes dangerously close) as text/border on the dark card surface.
    $activeClass = [
        'primary' => 'ptah-c-tab_active_primary border-b-2',
        'success' => 'ptah-c-tab_active_success border-b-2',
        'danger'  => 'ptah-c-tab_active_danger border-b-2',
        'warn'    => 'ptah-c-tab_active_warn border-b-2',
    ];
    // dark: variants mirror forge-tabs.blade.php's array mode (idle text since v1.12.0).
    $inactiveClass = 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 border-b-2 border-transparent';
    $stateClass    = $active ? ($activeClass[$color] ?? $activeClass['primary']) : $inactiveClass;
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => "px-4 py-3 text-sm font-medium transition-all duration-200 whitespace-nowrap focus:outline-none {$stateClass}"]) }}
>
    {{ $slot }}
</button>

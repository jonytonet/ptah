{{-- ═══════════════════════════════════════════════════════════════════════
     Ptah theme colors — injects the brand palette from config('ptah.theme.colors')
     as CSS custom properties. Drives BOTH Tailwind v4 utilities (bg-primary,
     text-success…, which compile to var(--color-*)) AND every derived tint/ring
     in ptah-components.css (via --ptah-primary + color-mix). One source of truth,
     no view publishing, survives composer update.
     ═══════════════════════════════════════════════════════════════════════ --}}
@php $ptahColors = config('ptah.theme.colors', []); @endphp
@if (! empty($ptahColors))
<style id="ptah-theme-colors">
    :root {
        @isset($ptahColors['primary'])
        --color-primary: {{ $ptahColors['primary'] }};
        --ptah-primary: {{ $ptahColors['primary'] }};
        @endisset
        @isset($ptahColors['success'])
        --color-success: {{ $ptahColors['success'] }};
        @endisset
        @isset($ptahColors['danger'])
        --color-danger: {{ $ptahColors['danger'] }};
        @endisset
        @isset($ptahColors['warn'])
        --color-warn: {{ $ptahColors['warn'] }};
        @endisset
        @isset($ptahColors['dark'])
        --color-dark: {{ $ptahColors['dark'] }};
        @endisset
    }
</style>
@endif

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
        {{-- --ptah-primary NÃO é redeclarado aqui: ptah-components.css já define
             --ptah-primary: var(--color-primary, #5b21b6) em :root — valor computado
             idêntico ao literal anterior enquanto nada mais sobrescrever --color-primary,
             mas agora também segue html[data-ptah-accent] (aba Aparência em /profile),
             que tem especificidade maior que este :root e venceria de qualquer forma;
             fixar --ptah-primary aqui apenas quebraria esse acompanhamento. --}}
        {{-- Derive the light/dark tints from the brand primary so bg-primary-light
             (sidebar active pill, primary alert) and hover:bg-primary-dark (buttons)
             follow the brand instead of the package's static blue defaults. Reference
             var(--color-primary) (not the literal) so an accent preset overriding
             --color-primary at higher specificity still reaches these derived tints. --}}
        --color-primary-light: color-mix(in srgb, var(--color-primary) 14%, #ffffff);
        --color-primary-dark:  color-mix(in srgb, var(--color-primary) 82%, #000000);
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

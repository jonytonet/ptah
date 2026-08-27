{{--
    forge-button — Ptah Forge
    Props:
      - color   : primary | success | danger | warn | dark | light  (default: primary)
      - size    : sm | md | lg  (default: md)
      - flat    : boolean - transparent background
      - relief  : boolean - solid appearance without shadow
      - rounded : boolean - fully rounded
      - disabled: boolean
      - loading : boolean - shows inline spinner
    Slots:
      - default : button text
      - icon    : optional icon — rendered raw (unescaped). SECURITY: provide only
                  trusted, developer-authored markup (e.g. an <svg>). Never feed it
                  user-controlled data, which would enable XSS.
--}}
@props([
    'color'    => 'primary',
    'size'     => 'md',
    'flat'     => false,
    'relief'   => false,
    'rounded'  => false,
    'disabled' => false,
    'loading'  => false,
])

@php
    $colorMap = [
        'primary' => [
            'bg'        => 'bg-primary',
            'hover'     => 'hover:bg-primary-dark',
            'text'      => 'text-primary',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-primary-dark',
            'flatHover' => 'hover:bg-primary-light',
        ],
        'success' => [
            // Coherent darkening scale, all vs white text: bg 5.48 -> hover 7.68 -> relief 9.72.
            // bg-success (#10b981) fails AA (2.54:1); success-dark (#059669) still falls
            // short (3.77:1) — none of the three tiers can reuse theme tokens, so all
            // three are arbitrary Tailwind values (no new token added to forge.css).
            'bg'        => 'bg-[#047857]',
            'hover'     => 'hover:bg-[#065f46]',
            'text'      => 'text-success',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-[#064e3b]',
            'flatHover' => 'hover:bg-success-light',
        ],
        'danger' => [
            // Coherent darkening scale, all vs white text: bg 4.83 -> hover 6.47 -> relief 8.31.
            // bg-danger (#ef4444) fails AA (3.76:1); danger-dark (#dc2626) passes and is
            // reused for bg. hover/relief need a 3rd/4th tier absent from the theme, so
            // they're arbitrary Tailwind values (no new token added to forge.css).
            'bg'        => 'bg-danger-dark',
            'hover'     => 'hover:bg-[#b91c1c]',
            'text'      => 'text-danger',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-[#991b1b]',
            'flatHover' => 'hover:bg-danger-light',
        ],
        'warn' => [
            'bg'        => 'bg-warn',
            'hover'     => 'hover:bg-warn-dark',
            'text'      => 'text-warn',
            // white text on bg-warn (#f59e0b) fails AA (2.15:1) — same fix as
            // forge-badge: dark text on amber (6.81:1). warn-dark only holds 4.59:1
            // with dark text, so relief reuses the hover tier as-is instead of
            // darkening further (a 3rd amber tier would drop below 4.5:1).
            'textSolid' => 'text-dark',
            'shadow'    => '',
            'relief'    => 'bg-warn-dark',
            'flatHover' => 'hover:bg-warn-light',
        ],
        'dark' => [
            // bg/hover/relief keep the brand --color-dark/-dark-dark scale as-is (light AND
            // dark scope): it is intentionally scope-INVARIANT (a "solid dark" button, same
            // idea as primary/success/danger), and dark:bg-slate-600 exists to stay visibly
            // LIGHTER than --ptah-surface dark (both #1e293b) — collapsing it into plain
            // bg-dark would make the button blend into any dark card behind it. Only the flat
            // shape's text/hover (ptah-btn-dark.ptah-btn-flat below) move to CSS.
            'bg'        => 'bg-dark dark:bg-slate-600',
            'hover'     => 'hover:bg-dark-dark dark:hover:bg-slate-500',
            'text'      => '',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-dark-dark dark:bg-slate-700',
            'flatHover' => '',
        ],
        'light' => [
            'bg'        => '',
            'hover'     => '',
            'text'      => '',
            'textSolid' => '',
            'shadow'    => '',
            'relief'    => '',
            'flatHover' => '',
        ],
        'secondary' => [
            'bg'        => '',
            'hover'     => '',
            'text'      => '',
            'textSolid' => '',
            'shadow'    => '',
            'relief'    => '',
            'flatHover' => '',
        ],
    ];

    $c = $colorMap[$color] ?? $colorMap['primary'];
    $ptahColorClass = 'ptah-btn-' . $color;
    // Onda B — hook para o eixo global de densidade (resources/css/ptah-components.css,
    // ".ptah-btn-size-sm"/".ptah-btn-size-md"): NUNCA os utilitários Tailwind abaixo
    // ($sizeClass) como seletor, porque eles descrevem o VISUAL, não a identidade do
    // slot — mesmo padrão de $ptahColorClass, uma classe por eixo.
    $ptahSizeClass = 'ptah-btn-size-' . $size;
    // Once bg/hover/relief/flatHover are emptied for the neutral color families
    // (light/secondary/dark's flat state), nothing in the emitted class list
    // distinguishes solid vs flat vs relief anymore — this hook lets
    // ptah-components.css tell them apart the same way $ptahColorClass lets it
    // tell colors apart.
    $ptahShapeClass = $flat ? 'ptah-btn-flat' : ($relief ? 'ptah-btn-relief' : '');

    $sizeMap = [
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-5 py-2.5 text-sm gap-2',
        'lg' => 'px-7 py-3.5 text-base gap-2.5',
    ];
    $sizeClass = $sizeMap[$size] ?? $sizeMap['md'];

    if ($flat) {
        $variantClass = "bg-transparent {$c['text']} {$c['flatHover']}";
    } elseif ($relief) {
        // Follow each family's own solid-text color (textSolid) instead of hardcoding
        // white — warn needs dark text (AA), and this also fixes light/secondary,
        // whose relief bg (gray-300) was unreadable with the old hardcoded white.
        $variantClass = "{$c['relief']} {$c['textSolid']}";
    } else {
        // Solid buttons get a subtle elevation; flat/relief stay flush.
        $variantClass = "{$c['bg']} {$c['hover']} {$c['textSolid']} shadow-sm {$c['shadow']}";
    }

    $radiusClass    = $rounded ? 'rounded-full' : 'rounded-md';
    $disabledClass  = $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
    $baseTransition = 'transition-colors duration-150 active:opacity-80 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-1';
@endphp

<button
    {{ $attributes->merge([
        'type'     => 'button',
        'class'    => "ptah-btn {$ptahColorClass} {$ptahSizeClass} {$ptahShapeClass} inline-flex items-center justify-center font-semibold select-none focus:outline-none
                       {$sizeClass} {$radiusClass} {$variantClass} {$baseTransition} {$disabledClass}",
        'disabled' => $disabled || $loading ? true : false,
    ]) }}
>
    @if ($loading)
        <svg class="animate-spin h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
    @endif

    @if (isset($icon) && !$loading)
        <span class="shrink-0">{!! $icon !!}</span>
    @endif

    <span>{{ $slot }}</span>
</button>

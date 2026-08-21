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
            'bg'        => 'bg-dark dark:bg-slate-600',
            'hover'     => 'hover:bg-dark-dark dark:hover:bg-slate-500',
            'text'      => 'text-dark dark:text-slate-300',
            'textSolid' => 'text-white',
            'shadow'    => '',
            'relief'    => 'bg-dark-dark dark:bg-slate-700',
            'flatHover' => 'hover:bg-dark-light dark:hover:bg-slate-700',
        ],
        'light' => [
            'bg'        => 'bg-gray-100 dark:bg-slate-700',
            'hover'     => 'hover:bg-gray-200 dark:hover:bg-slate-600',
            'text'      => 'text-gray-700 dark:text-slate-300',
            'textSolid' => 'text-gray-700 dark:text-slate-200',
            'shadow'    => '',
            'relief'    => 'bg-gray-300 dark:bg-slate-600',
            'flatHover' => 'hover:bg-gray-50 dark:hover:bg-slate-700',
        ],
        'secondary' => [
            'bg'        => 'bg-gray-100 dark:bg-slate-700',
            'hover'     => 'hover:bg-gray-200 dark:hover:bg-slate-600',
            'text'      => 'text-gray-700 dark:text-slate-300',
            'textSolid' => 'text-gray-700 dark:text-slate-200',
            'shadow'    => '',
            'relief'    => 'bg-gray-300 dark:bg-slate-600',
            'flatHover' => 'hover:bg-gray-50 dark:hover:bg-slate-700',
        ],
    ];

    $c = $colorMap[$color] ?? $colorMap['primary'];
    $ptahColorClass = 'ptah-btn-' . $color;
    // Onda B — hook para o eixo global de densidade (resources/css/ptah-components.css,
    // ".ptah-btn-size-sm"/".ptah-btn-size-md"): NUNCA os utilitários Tailwind abaixo
    // ($sizeClass) como seletor, porque eles descrevem o VISUAL, não a identidade do
    // slot — mesmo padrão de $ptahColorClass, uma classe por eixo.
    $ptahSizeClass = 'ptah-btn-size-' . $size;

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
        'class'    => "ptah-btn {$ptahColorClass} {$ptahSizeClass} inline-flex items-center justify-center font-semibold select-none focus:outline-none
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

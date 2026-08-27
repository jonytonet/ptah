{{--
    forge-chart-card — Ptah Forge
    Props:
      - title   : string
      - subtitle: string
      - color   : primary | success | danger | warn | dark
    Slots: header, legend, default, footer
--}}
@props([
    'title'    => null,
    'subtitle' => null,
    'color'    => 'primary',
])

<div {{ $attributes->merge(['class' => 'ptah-c-chart_surface rounded-md border border-gray-200 overflow-hidden']) }}>
    @if($title || isset($header))
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            @isset($header)
                {{ $header }}
            @else
                <div>
                    @if($title)
                        <h3 class="text-base font-semibold ptah-c-chart_ttl">{{ $title }}</h3>
                    @endif
                    @if($subtitle)
                        <p class="text-sm ptah-c-chart_subtitle mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
            @endisset
            @isset($legend)
                <div>{{ $legend }}</div>
            @endisset
        </div>
    @endif

    <div class="p-5">{{ $slot }}</div>

    @isset($footer)
        <div class="px-5 py-3 border-t border-gray-100 ptah-c-chart_footer">
            {{ $footer }}
        </div>
    @endisset
</div>

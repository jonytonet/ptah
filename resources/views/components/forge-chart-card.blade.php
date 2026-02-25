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

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm overflow-hidden']) }}>
    @if($title || isset($header))
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            @isset($header)
                {{ $header }}
            @else
                <div>
                    @if($title)
                        <h3 class="text-base font-semibold text-dark">{{ $title }}</h3>
                    @endif
                    @if($subtitle)
                        <p class="text-sm text-gray-500 mt-0.5">{{ $subtitle }}</p>
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
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
            {{ $footer }}
        </div>
    @endisset
</div>

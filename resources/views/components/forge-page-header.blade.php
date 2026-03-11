{{--
    forge-page-header — Ptah Forge
    Props:
      - title    : string  - main page title (required)
      - subtitle : string  - subtitle / description (optional)
      - back     : string  - URL for back button (optional)
    Slots: default (right-side actions, e.g. buttons)
--}}
@props([
    'title'    => '',
    'subtitle' => null,
    'back'     => null,
])

<div {{ $attributes->merge(['class' => 'ptah-page-header mb-6']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            @if($back)
                <a href="{{ $back }}"
                   class="inline-flex items-center justify-center text-gray-600 transition-colors duration-150 bg-gray-100 w-9 h-9 rounded-md hover:bg-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            @endif
            <div>
                <h1 class="text-2xl font-bold leading-tight text-gray-900">{{ $title }}</h1>
                @if($subtitle)
                    <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @if($slot->isNotEmpty())
            <div class="flex items-center gap-2 shrink-0">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>

{{--
    forge-breadcrumb — Ptah Forge
    Props:
      - items    : array [ ['url' => '...', 'label' => '...'], ... ]
      - separator: string  (default: '/')
--}}
@props([
    'items'     => [],
    'separator' => '/',
])

<nav {{ $attributes->merge(['class' => 'flex']) }} aria-label="Breadcrumb">
    <ol class="inline-flex items-center gap-1 overflow-x-auto text-sm whitespace-nowrap scrollbar-none">
        @foreach($items as $index => $item)
            <li class="inline-flex items-center">
                @if($index > 0)
                    <span class="mx-1 ptah-c-crumb_sep">{{ $separator }}</span>
                @endif

                @if($index < count($items) - 1)
                    <a href="{{ $item['url'] ?? '#' }}" class="ptah-c-crumb">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="ptah-c-crumb_current font-medium">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

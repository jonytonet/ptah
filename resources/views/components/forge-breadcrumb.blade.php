{{--
    forge-breadcrumb — Ptah Forge
    Props:
      - items    : array [ ['url' => '...', 'label' => '...'], ... ]
      - separator: string  (padrão: '/')
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
                    <span class="mx-1 text-gray-400">{{ $separator }}</span>
                @endif

                @if($index < count($items) - 1)
                    <a href="{{ $item['url'] ?? '#' }}" class="text-gray-500 hover:text-primary transition-colors">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="text-primary font-medium">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

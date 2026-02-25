{{--
    forge-list — Ptah Forge
    Props:
      - items: array [ ['avatar','name','description','badge','value'] ]
--}}
@props([
    'items' => [],
])

<ul {{ $attributes->merge(['class' => 'divide-y divide-gray-50']) }}>
    @forelse($items as $item)
        <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
            @if(isset($item['avatar']))
                <div class="flex-shrink-0">
                    <img src="{{ $item['avatar'] }}" alt="{{ $item['name'] ?? '' }}" class="w-10 h-10 rounded-full object-cover" />
                </div>
            @elseif(isset($item['name']))
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-light flex items-center justify-center">
                    <span class="text-primary font-semibold text-sm">{{ strtoupper(substr($item['name'], 0, 2)) }}</span>
                </div>
            @endif

            <div class="flex-1 min-w-0">
                @if(isset($item['name']))
                    <p class="text-sm font-medium text-dark truncate">{{ $item['name'] }}</p>
                @endif
                @if(isset($item['description']))
                    <p class="text-xs text-gray-500 truncate">{{ $item['description'] }}</p>
                @endif
            </div>

            <div class="flex-shrink-0 flex items-center gap-2">
                @if(isset($item['badge']))
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                        bg-{{ $item['badge']['color'] ?? 'primary' }}-light
                        text-{{ $item['badge']['color'] ?? 'primary' }}">
                        {{ $item['badge']['label'] }}
                    </span>
                @endif
                @if(isset($item['value']))
                    <span class="text-sm font-semibold text-dark">{{ $item['value'] }}</span>
                @endif
            </div>
        </li>
    @empty
        <li class="py-6 text-center text-sm text-gray-400">Nenhum item encontrado.</li>
    @endforelse
</ul>

{{--
    forge-tabs — Ptah Forge
    Props:
      - tabs      : array [ ['id' => '', 'label' => '', 'slot' => ''], ... ]
      - color     : primary | success | danger | warn  (padrão: primary)
      - defaultTab: string (id da aba inicial)
    Requer Alpine.js
--}}
@props([
    'tabs'       => [],
    'color'      => 'primary',
    'defaultTab' => null,
])

@php
    $firstTab = $defaultTab ?? (count($tabs) > 0 ? $tabs[0]['id'] : null);

    $activeClass = [
        'primary' => 'text-primary border-b-2 border-primary',
        'success' => 'text-success border-b-2 border-success',
        'danger'  => 'text-danger  border-b-2 border-danger',
        'warn'    => 'text-warn    border-b-2 border-warn',
    ];
    $active = $activeClass[$color] ?? $activeClass['primary'];
@endphp

<div x-data="{ activeTab: '{{ $firstTab }}' }" {{ $attributes }}>
    {{-- Tab Nav --}}
    <div class="relative border-b border-gray-200 overflow-x-auto scrollbar-none">
        <div class="flex min-w-max">
            @foreach($tabs as $tab)
                <button
                    @click="activeTab = '{{ $tab['id'] }}'"
                    :class="activeTab === '{{ $tab['id'] }}' ? '{{ $active }}' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'"
                    class="px-4 py-3 text-sm font-medium transition-all duration-200 whitespace-nowrap focus:outline-none"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Tab Content --}}
    <div class="mt-4">
        @foreach($tabs as $tab)
            <div
                x-show="activeTab === '{{ $tab['id'] }}'"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
            >
                @isset($tab['slot'])
                    {!! $tab['slot'] !!}
                @endisset
            </div>
        @endforeach

        {{ $slot }}
    </div>
</div>

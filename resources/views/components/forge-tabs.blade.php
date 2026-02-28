{{--
    forge-tabs — Ptah Forge

    Modo 1 — Slot (Livewire): estado de aba gerenciado externamente.
      <x-forge-tabs>
          <x-slot name="tabs">
              <x-forge-tab key="foo" :active="$activeTab === 'foo'" wire:click="$set('activeTab','foo')">Foo</x-forge-tab>
          </x-slot>
          @if($activeTab === 'foo') ... @endif
      </x-forge-tabs>

    Modo 2 — Array (Alpine): estado de aba gerenciado internamente.
      <x-forge-tabs :tabs="[['id'=>'a','label'=>'A','slot'=>'...'],...]" />

    Props:
      - tabs      : array [ ['id' => '', 'label' => '', 'slot' => ''], ... ]  (Modo 2)
      - color     : primary | success | danger | warn  (padrão: primary)
      - defaultTab: string (id da aba inicial, Modo 2)
--}}
@props([
    'tabs'       => [],
    'color'      => 'primary',
    'defaultTab' => null,
])

@php
    $useSlotMode = isset($tabs) && $tabs instanceof \Illuminate\View\ComponentSlot;
    $arrayMode   = !$useSlotMode && is_array($tabs) && count($tabs) > 0;
    $firstTab    = $defaultTab ?? ($arrayMode ? $tabs[0]['id'] : null);

    $activeClass = [
        'primary' => 'text-primary border-b-2 border-primary',
        'success' => 'text-success border-b-2 border-success',
        'danger'  => 'text-danger  border-b-2 border-danger',
        'warn'    => 'text-warn    border-b-2 border-warn',
    ];
    $active = $activeClass[$color] ?? $activeClass['primary'];
@endphp

@if ($arrayMode)
{{-- ── Modo Array / Alpine ── --}}
<div x-data="{ activeTab: '{{ $firstTab }}' }" {{ $attributes }}>
    <div class="relative border-b border-gray-200 overflow-x-auto scrollbar-none">
        <div class="flex min-w-max">
            @foreach($tabs as $tab)
                <button
                    type="button"
                    @click="activeTab = '{{ $tab['id'] }}'"
                    :class="activeTab === '{{ $tab['id'] }}' ? '{{ $active }}' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'"
                    class="px-4 py-3 text-sm font-medium transition-all duration-200 whitespace-nowrap focus:outline-none"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    </div>
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
@else
{{-- ── Modo Slot / Livewire ── --}}
<div {{ $attributes }}>
    <div class="relative border-b border-gray-200 overflow-x-auto scrollbar-none">
        <div class="flex min-w-max">
            {{ $tabs }}
        </div>
    </div>
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
@endif

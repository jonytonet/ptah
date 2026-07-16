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
      - color     : primary | success | danger | warn  (default: primary)
      - defaultTab: string (initial tab id, Mode 2)
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
<div x-data="{ activeTab: '{{ $firstTab }}', tabIds: {{ \Illuminate\Support\Js::from(array_column($tabs, 'id')) }}, move(d){ const i = this.tabIds.indexOf(this.activeTab); this.activeTab = this.tabIds[(i + d + this.tabIds.length) % this.tabIds.length]; } }" {{ $attributes }}>
    <div class="relative border-b border-gray-200 dark:border-slate-700 overflow-x-auto scrollbar-none">
        <div class="flex min-w-max" role="tablist">
            @foreach($tabs as $tab)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $tab['id'] }}"
                    aria-controls="panel-{{ $tab['id'] }}"
                    :aria-selected="activeTab === '{{ $tab['id'] }}' ? 'true' : 'false'"
                    :tabindex="activeTab === '{{ $tab['id'] }}' ? 0 : -1"
                    @click="activeTab = '{{ $tab['id'] }}'"
                    @keydown.arrow-right.prevent="move(1); $el.parentElement.querySelector('[aria-selected=true]')?.focus()"
                    @keydown.arrow-left.prevent="move(-1); $el.parentElement.querySelector('[aria-selected=true]')?.focus()"
                    :class="activeTab === '{{ $tab['id'] }}' ? '{{ $active }}' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 border-b-2 border-transparent'"
                    class="px-4 py-3 text-sm font-medium transition-all duration-200 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded-t"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="mt-4">
        @foreach($tabs as $tab)
            <div
                role="tabpanel"
                id="panel-{{ $tab['id'] }}"
                aria-labelledby="tab-{{ $tab['id'] }}"
                tabindex="0"
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
    <div class="relative border-b border-gray-200 dark:border-slate-700 overflow-x-auto scrollbar-none">
        <div class="flex min-w-max" role="tablist">
            {{ $tabs }}
        </div>
    </div>
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
@endif

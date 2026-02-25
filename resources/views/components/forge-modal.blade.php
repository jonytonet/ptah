{{--
    forge-modal — Ptah Forge
    Props:
      - title: string
      - size : sm | md | lg | xl | full  (padrão: md)
    Slots: default, footer
    Uso:
      <div x-data="{ open: false }">
          <x-forge-button @click="open = true">Abrir</x-forge-button>
          <x-forge-modal title="Título" x-bind:open="open" @close="open = false">
              Conteúdo
              <x-slot:footer>
                  <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                  <x-forge-button>Salvar</x-forge-button>
              </x-slot:footer>
          </x-forge-modal>
      </div>
    Requer Alpine.js
--}}
@props([
    'title' => '',
    'size'  => 'md',
])

@php
    $sizeMap = [
        'sm'   => 'max-w-sm',
        'md'   => 'max-w-md',
        'lg'   => 'max-w-lg',
        'xl'   => 'max-w-xl',
        'full' => 'max-w-full mx-4',
    ];
    $sizeClass = $sizeMap[$size] ?? $sizeMap['md'];
@endphp

<div
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
    {{ $attributes->except(['class']) }}
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/30 backdrop-blur-sm"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false; $dispatch('close')"
    ></div>

    {{-- Painel --}}
    <div
        class="relative z-10 w-full {{ $sizeClass }} bg-white rounded-2xl shadow-2xl overflow-hidden"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">{{ $title }}</h3>
            <button
                type="button"
                @click="open = false; $dispatch('close')"
                class="text-gray-400 hover:text-gray-600 transition-colors duration-150 focus:outline-none"
                aria-label="Fechar modal"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 text-sm text-gray-700">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if (isset($footer))
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

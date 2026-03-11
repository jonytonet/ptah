{{--
    forge-modal — Ptah Forge
    Props:
      - title    : string
      - subtitle : string|null  (opcional, renderiza abaixo do título)
      - size     : sm | md | lg | xl | 2xl | full  (default: md)
    Slots: default, footer
    Uso:
      <div x-data="{ open: false }">
          <x-forge-button @click="open = true">Abrir</x-forge-button>
          <x-forge-modal title="Título" subtitle="Subtítulo opcional">
              Content
              <x-slot:footer>
                  <x-forge-button color="light" @click="open = false">Cancelar</x-forge-button>
                  <x-forge-button>Salvar</x-forge-button>
              </x-slot:footer>
          </x-forge-modal>
      </div>
    Requires Alpine.js
--}}
@props([
    'title'    => '',
    'subtitle' => null,
    'size'     => 'md',
])

@php
    $sizeMap = [
        'sm'   => 'max-w-sm',
        'md'   => 'max-w-md',
        'lg'   => 'max-w-lg',
        'xl'   => 'max-w-xl',
        '2xl'  => 'max-w-2xl',
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
        class="ptah-modal-panel relative z-10 w-full {{ $sizeClass }} mx-4 flex flex-col max-h-[90vh] bg-white dark:bg-slate-800 rounded-md border border-gray-200 dark:border-slate-700 overflow-hidden"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{-- Header --}}
        <div class="shrink-0 flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
            <div>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="text-xs text-gray-400 dark:text-slate-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            <button
                type="button"
                @click="open = false; $dispatch('close')"
                class="ml-4 shrink-0 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors duration-150 focus:outline-none"
                aria-label="Fechar modal"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5 text-sm text-gray-700 dark:text-slate-300">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if (isset($footer))
            <div class="shrink-0 px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

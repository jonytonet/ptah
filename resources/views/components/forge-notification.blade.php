{{--
    forge-notification — Ptah Forge
    Props:
      - color   : primary | success | danger | warn | dark  (padrão: primary)
      - title   : string
      - text    : string
      - position: top-right | top-left | bottom-right | bottom-left  (padrão: top-right)
      - duration: int ms — 0 para não fechar automaticamente  (padrão: 4000)
    Uso:
      <div x-data="{ show: false }">
          <x-forge-button @click="show = true">Notificar</x-forge-button>
          <x-forge-notification title="Sucesso!" text="Mensagem" color="success"
              x-bind:show="show" @close="show = false" />
      </div>
    Requer Alpine.js
--}}
@props([
    'color'    => 'primary',
    'title'    => '',
    'text'     => '',
    'position' => 'top-right',
    'duration' => 4000,
])

@php
    $colorMap = [
        'primary' => 'bg-primary', 'success' => 'bg-success',
        'danger'  => 'bg-danger',  'warn'    => 'bg-warn',
        'dark'    => 'bg-dark',
    ];
    $bgClass = $colorMap[$color] ?? $colorMap['primary'];

    $icons = [
        'primary' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>',
        'success' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'danger'  => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'warn'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        'dark'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
    ];
    $icon = $icons[$color] ?? $icons['primary'];

    $posMap = [
        'top-right'    => 'top-4 right-4',
        'top-left'     => 'top-4 left-4',
        'bottom-right' => 'bottom-4 right-4',
        'bottom-left'  => 'bottom-4 left-4',
    ];
    $posClass   = $posMap[$position] ?? $posMap['top-right'];
    $isRight    = str_contains($position, 'right');
    $enterStart = $isRight ? 'opacity-0 translate-x-8' : 'opacity-0 -translate-x-8';
@endphp

<div
    x-data="{
        show: false,
        duration: {{ $duration }},
        timer: null,
        init() {
            this.$watch('show', val => {
                if (val && this.duration > 0) {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => {
                        this.show = false;
                        this.$dispatch('close');
                    }, this.duration);
                }
            });
        }
    }"
    x-modelable="show"
    {{ $attributes->except(['class']) }}
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="{{ $enterStart }}"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="{{ $enterStart }}"
    class="fixed {{ $posClass }} z-50 w-80 rounded-2xl overflow-hidden shadow-2xl"
>
    <div class="{{ $bgClass }} text-white flex items-start gap-3 p-4">
        <span class="shrink-0 mt-0.5">{!! $icon !!}</span>

        <div class="flex-1">
            @if ($title)
                <p class="font-semibold text-sm mb-0.5">{{ $title }}</p>
            @endif
            @if ($text)
                <p class="text-sm opacity-90">{{ $text }}</p>
            @endif
            @if ($slot->isNotEmpty())
                <div class="text-sm opacity-90">{{ $slot }}</div>
            @endif
        </div>

        <button
            type="button"
            @click="show = false; $dispatch('close')"
            class="shrink-0 opacity-80 hover:opacity-100 transition-opacity focus:outline-none"
            aria-label="Fechar notificação"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

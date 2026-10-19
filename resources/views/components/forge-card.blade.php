{{--
    forge-card — Ptah Forge
    Props:
      - type     : default | primary | success | danger | warn | dark
      - flat     : boolean - sem sombra
      - hoverable: boolean - hover elevation effect
    Slots: header, default, footer, img
--}}
@props([
    'type'      => 'default',
    'flat'      => false,
    'hoverable' => false,
])

@php
    $typeMap = [
        'default' => '',
        'primary' => 'bg-primary-light border-primary',
        'success' => 'bg-success-light border-success',
        'danger'  => 'bg-danger-light border-danger',
        'warn'    => 'bg-warn-light border-warn',
        'dark'    => 'bg-dark border-dark-dark text-white',
    ];
    $typeClass   = $typeMap[$type] ?? $typeMap['default'];
    $shadowClass = 'border border-gray-200';
    $hoverClass  = $hoverable ? 'transition-colors duration-150 hover:border-primary/40 cursor-pointer' : '';
    $ptahCardClass = 'ptah-card ptah-card-' . $type;
@endphp

{{-- Sem `overflow-hidden` no card (1.46.4): ele cortava qualquer popover de
     dentro — a lista de um forge-select presa no retangulo do card. O fundo ja
     respeita o arredondamento sozinho; quem precisa de recorte e a imagem do
     topo, recortada no proprio slot. --}}
<div {{ $attributes->merge(['class' => "rounded-md {$ptahCardClass} {$typeClass} {$shadowClass} {$hoverClass}"]) }}>

    @if (isset($img))
        <div class="w-full overflow-hidden rounded-t-md">{{ $img }}</div>
    @endif

    @if (isset($header))
        <div class="px-5 pt-5 pb-3 border-b {{ $type === 'dark' ? 'border-dark-dark' : ($type === 'default' ? '' : 'border-gray-100') }}">
            {{ $header }}
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="p-5">{{ $slot }}</div>
    @endif

    @if (isset($footer))
        <div class="px-5 py-3 border-t {{ $type === 'dark' ? 'border-dark-dark' : ($type === 'default' ? '' : 'border-gray-100') }}">
            {{ $footer }}
        </div>
    @endif
</div>

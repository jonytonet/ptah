{{--
    forge-card — Ptah Forge
    Props:
      - type     : default | primary | success | danger | warn | dark
      - flat     : boolean - sem sombra
      - hoverable: boolean - elevação no hover
    Slots: header, default, footer, img
--}}
@props([
    'type'      => 'default',
    'flat'      => false,
    'hoverable' => false,
])

@php
    $typeMap = [
        'default' => 'bg-white border-gray-100',
        'primary' => 'bg-primary-light border-primary',
        'success' => 'bg-success-light border-success',
        'danger'  => 'bg-danger-light border-danger',
        'warn'    => 'bg-warn-light border-warn',
        'dark'    => 'bg-dark border-dark-dark text-white',
    ];
    $typeClass   = $typeMap[$type] ?? $typeMap['default'];
    $shadowClass = $flat ? 'shadow-none border border-gray-200' : 'shadow-md border';
    $hoverClass  = $hoverable ? 'transition-shadow duration-200 hover:shadow-xl cursor-pointer' : '';
@endphp

<div {{ $attributes->merge(['class' => "rounded-2xl overflow-hidden {$typeClass} {$shadowClass} {$hoverClass}"]) }}>

    @if (isset($img))
        <div class="w-full">{{ $img }}</div>
    @endif

    @if (isset($header))
        <div class="px-5 pt-5 pb-3 border-b {{ $type === 'dark' ? 'border-dark-dark' : 'border-gray-100' }}">
            {{ $header }}
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="p-5">{{ $slot }}</div>
    @endif

    @if (isset($footer))
        <div class="px-5 py-3 border-t {{ $type === 'dark' ? 'border-dark-dark' : 'border-gray-100' }}">
            {{ $footer }}
        </div>
    @endif
</div>

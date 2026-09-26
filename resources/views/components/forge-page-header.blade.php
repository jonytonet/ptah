{{--
    forge-page-header — Ptah Forge
    Props:
      - title    : string  - main page title (required)
      - subtitle : string  - subtitle / description (optional)
      - back     : string  - URL for back button (optional)
    Slots: default (right-side actions, e.g. buttons)
--}}
@props([
    'title'    => '',
    'subtitle' => null,
    'back'     => null,
])

<div {{ $attributes->merge(['class' => 'ptah-page-header mb-6']) }}>
    {{-- No celular o titulo e as acoes empilham e as acoes quebram linha: com
         filtros no slot (data + selects + botao) a area de acoes nao encolhia e a
         pagina inteira rolava para o lado (542px num viewport de 390 — achado #11). --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <div class="flex items-center gap-3 min-w-0">
            @if($back)
                <a href="{{ $back }}"
                   class="ptah-c-phdr_back inline-flex items-center justify-center transition-colors duration-150 w-9 h-9 rounded-md">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            @endif
            <div>
                <h1 class="ptah-c-phdr_ttl text-2xl font-bold leading-tight">{{ $title }}</h1>
                @if($subtitle)
                    <p class="ptah-c-phdr_sub mt-0.5 text-sm">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @if($slot->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 min-w-0 sm:shrink-0">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>

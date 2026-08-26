{{--
    company-switcher — Ptah Forge
    ──────────────────────────────────────────────────────────────────
    • 1 empresa  → componente não renderiza nada
    • 2+ empresas → nome completo da empresa ativa + labels de todas

    Layout: [Nome da Empresa Ativa]  |  [LAB1]  [LAB2]  …
    - Nome por extenso: contexto visual (não clicável)
    - Labels: botões clicáveis; o ativo fica em cor primária (#5b21b6)

    Dark mode: reage à classe .ptah-dark no ancestral

    $layout:
      'inline'  → o grupo horizontal acima, usado no centro da navbar em telas
                  largas.
      'stacked' → lista vertical de itens de menu, para viver DENTRO de um
                  painel de dropdown. No celular o grupo inline disputava os
                  mesmos ~60px com os ícones da navbar e as empresas ficavam
                  ilegíveis (relato de ERP em produção), então a navbar esconde
                  o inline lá e hospeda esta variante dentro do menu de
                  administração — um menu à direita em vez de dois controles
                  brigando.
--}}
<div>
@if($companies->count() >= 2)

@if($layout === 'stacked')

    {{-- Variante de dropdown. Reaproveita .ptah-admin-dropdown-link (definido
         em forge-navbar) para herdar as cores de tema do painel que a hospeda,
         em vez de repintar item de menu com Tailwind hardcoded. --}}
    <div role="group" aria-label="{{ __('ptah::ui.switcher_select_company') }}">
        <p class="px-4 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide opacity-60">
            {{ __('ptah::ui.switcher_select_company') }}
        </p>

        @foreach($companies as $co)
            @php $isActive = $co->id === $activeId; @endphp

            <button
                wire:click="switchTo({{ $co->id }})"
                type="button"
                aria-current="{{ $isActive ? 'true' : 'false' }}"
                class="ptah-admin-dropdown-link w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-left"
            >
                {{-- Marca a ativa por ÍCONE e não só por cor: o painel escuro
                     da navbar não garante contraste suficiente para diferenciar
                     duas cores de texto (WCAG 1.4.1: cor não pode ser o único
                     portador de informação). --}}
                @if($isActive)
                    <svg class="w-4 h-4 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                @else
                    <span class="w-4 h-4 shrink-0" aria-hidden="true"></span>
                @endif

                <span class="min-w-0 flex-1 truncate {{ $isActive ? 'font-semibold' : '' }}">{{ $co->name }}</span>

                <span class="shrink-0 text-[10px] font-bold tracking-wide uppercase opacity-60">
                    {{ $co->getLabelDisplay() ?: mb_strtoupper(mb_substr($co->name, 0, 4)) }}
                </span>
            </button>
        @endforeach
    </div>

@else

    <div class="ptah-switcher-group inline-flex items-center gap-2">

        {{-- Nome por extenso da empresa ativa ────────────────────── --}}
        <span class="ptah-switcher-name whitespace-nowrap font-semibold text-sm">
            {{ $activeCompany->name ?? '' }}
        </span>

        {{-- Separador vertical ────────────────────────────────────── --}}
        <span class="ptah-switcher-sep" aria-hidden="true"></span>

        {{-- Labels (tabs) de todas as empresas ───────────────────── --}}
        <nav
            class="ptah-switcher-bar inline-flex items-center gap-0.5 rounded-md px-1.5 py-1"
            role="tablist"
            aria-label="{{ __('ptah::ui.switcher_select_company') }}"
        >
            @foreach($companies as $co)
                @php
                    $isActive = $co->id === $activeId;
                    $tabLabel = $co->getLabelDisplay() ?: mb_strtoupper(mb_substr($co->name, 0, 4));
                @endphp

                <button
                    wire:click="switchTo({{ $co->id }})"
                    type="button"
                    role="tab"
                    title="{{ $co->name }}"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    class="ptah-switcher-tab {{ $isActive ? 'ptah-switcher-tab--active' : '' }}
                           inline-flex items-center px-2.5 py-1 whitespace-nowrap
                           font-bold text-[11px] tracking-wide uppercase rounded-md
                           transition-all duration-150 focus:outline-none"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>

    </div>

@endif

@endif
</div>

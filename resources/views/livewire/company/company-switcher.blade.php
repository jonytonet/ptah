{{--
    company-switcher — Ptah Forge
    ──────────────────────────────────────────────────────────────────
    • 1 empresa  → componente não renderiza nada
    • 2+ empresas → nome completo da empresa ativa + labels de todas

    Layout: [Nome da Empresa Ativa]  |  [LAB1]  [LAB2]  …
    - Nome por extenso: contexto visual (não clicável)
    - Labels: botões clicáveis; o ativo fica em cor primária (#5b21b6)

    Dark mode: reage à classe .ptah-dark no ancestral
--}}
<div>
@if($companies->count() >= 2)

    <div class="ptah-switcher-group inline-flex items-center gap-2">

        {{-- Nome por extenso da empresa ativa ────────────────────── --}}
        <span class="ptah-switcher-name whitespace-nowrap font-semibold text-sm">
            {{ $activeCompany->name ?? '' }}
        </span>

        {{-- Separador vertical ────────────────────────────────────── --}}
        <span class="ptah-switcher-sep" aria-hidden="true"></span>

        {{-- Labels (tabs) de todas as empresas ───────────────────── --}}
        <nav
            class="ptah-switcher-bar inline-flex items-center gap-0.5 rounded-xl px-1.5 py-1"
            role="tablist"
            aria-label="Selecionar empresa"
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
                           font-bold text-[11px] tracking-wide uppercase rounded-lg
                           transition-all duration-150 focus:outline-none"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>

    </div>

@endif
</div>

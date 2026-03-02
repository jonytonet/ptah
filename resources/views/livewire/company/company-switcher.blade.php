{{--
    company-switcher — Ptah Forge
    ──────────────────────────────────────────────────────────────────
    Barra horizontal de tabs inspirada no ERP base.
    • 1 empresa  → exibe apenas o nome (sem barra de troca)
    • 2+ empresas → exibe a barra com todas as empresas como tabs
    • A empresa ativa fica destacada em âmbar
    • A 1ª empresa (is_default) mostra o nome completo
    • As demais mostram o label/sigla (ou nome truncado se sem sigla)

    Uso no layout (opcional):
        @livewire('ptah::company.switcher')

    Dark mode: reage à classe .ptah-dark no ancestral
--}}
<div>
@if($companies->count() >= 2)
    @php
        $defaultCompany = $companies->firstWhere('is_default', true) ?? $companies->first();
    @endphp

    {{-- ── Barra de tabs horizontal ──────────────────────────────────── --}}
    <nav
        class="ptah-switcher-bar inline-flex items-center gap-0.5 rounded-xl px-1.5 py-1"
        role="tablist"
        aria-label="Selecionar empresa"
    >
        @foreach($companies as $co)
            @php
                $isActive  = $co->id === $activeId;
                $isDefault = $co->id === $defaultCompany?->id;
                $tabLabel  = $co->getLabelDisplay() ?: mb_strtoupper(mb_substr($co->name, 0, 4));
                $showName  = $isDefault; // 1ª empresa (matriz) mostra o nome; outras só a sigla
            @endphp

            <button
                wire:click="switchTo({{ $co->id }})"
                type="button"
                role="tab"
                title="{{ $co->name }}"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                class="ptah-switcher-tab {{ $isActive ? 'ptah-switcher-tab--active' : '' }}
                       inline-flex items-center whitespace-nowrap
                       font-bold text-[11px] tracking-wide uppercase
                       transition-all duration-150 focus:outline-none rounded-lg
                       {{ $showName ? 'px-3' : 'px-2.5' }} py-1"
            >
                {{-- Empresa default: mostrar nome; demais: sigla --}}
                @if($showName)
                    {{ $co->name }}
                @else
                    {{ $tabLabel }}
                @endif
            </button>
        @endforeach
    </nav>
@endif

<style>
    /* ── Barra ─────────────────────────────────────── */
    .ptah-switcher-bar {
        background-color: #4b5563; /* gray-600 */
    }

    /* ── Tab inativo ───────────────────────────────── */
    .ptah-switcher-tab {
        color: #d1d5db;       /* gray-300 */
        background: transparent;
    }
    .ptah-switcher-tab:hover {
        background-color: rgba(255,255,255,.10);
        color: #f9fafb;
    }

    /* ── Tab ativo ─────────────────────────────────── */
    .ptah-switcher-tab--active {
        background-color: #f59e0b !important; /* amber-500 */
        color: #ffffff !important;
        box-shadow: 0 1px 4px rgba(245,158,11,.40);
    }

    /* ── Dark mode (classe .ptah-dark no ancestral) ── */
    .ptah-dark .ptah-switcher-bar {
        background-color: #1e293b; /* slate-800 */
    }
    .ptah-dark .ptah-switcher-tab {
        color: #94a3b8; /* slate-400 */
    }
    .ptah-dark .ptah-switcher-tab:hover {
        background-color: rgba(255,255,255,.07);
        color: #e2e8f0;
    }
    .ptah-dark .ptah-switcher-tab--active {
        background-color: #f59e0b !important;
        color: #ffffff !important;
    }
</style>
</div>

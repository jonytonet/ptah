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

<style>
    /* ── Nome da empresa ativa ──────────────────────── */
    .ptah-switcher-name {
        color: #1e293b; /* slate-800 */
    }

    /* ── Separador ──────────────────────────────────── */
    .ptah-switcher-sep {
        display: inline-block;
        width: 1px;
        height: 1.1em;
        background-color: #cbd5e1; /* slate-300 */
        border-radius: 1px;
        flex-shrink: 0;
    }

    /* ── Barra de fundo ─────────────────────────────── */
    .ptah-switcher-bar {
        background-color: #f1f5f9; /* slate-100 */
    }

    /* ── Tab inativo ────────────────────────────────── */
    .ptah-switcher-tab {
        color: #64748b; /* slate-500 */
        background: transparent;
    }
    .ptah-switcher-tab:hover {
        background-color: #ddd6fe; /* violet-200 */
        color: #4c1d95; /* violet-900 */
    }

    /* ── Tab ativo (cor primária do projeto) ────────── */
    .ptah-switcher-tab--active {
        background-color: #5b21b6 !important; /* primary */
        color: #ffffff !important;
        box-shadow: 0 1px 5px rgba(91,33,182,.35);
    }

    /* ── Dark mode ──────────────────────────────────── */
    .ptah-dark .ptah-switcher-name {
        color: #e2e8f0; /* slate-200 */
    }
    .ptah-dark .ptah-switcher-sep {
        background-color: #475569; /* slate-600 */
    }
    .ptah-dark .ptah-switcher-bar {
        background-color: #1e293b; /* slate-800 */
    }
    .ptah-dark .ptah-switcher-tab {
        color: #94a3b8; /* slate-400 */
    }
    .ptah-dark .ptah-switcher-tab:hover {
        background-color: rgba(167,139,250,.15); /* violet-400/15 */
        color: #c4b5fd; /* violet-300 */
    }
    .ptah-dark .ptah-switcher-tab--active {
        background-color: #5b21b6 !important;
        color: #ffffff !important;
    }
</style>
</div>

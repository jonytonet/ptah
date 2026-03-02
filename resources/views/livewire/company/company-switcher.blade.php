{{--
    company-switcher — Ptah Forge
    Exibido na navbar.
    - 1 empresa: badge estático (sem dropdown)
    - 2+ empresas: dropdown Alpine para troca de empresa
    Dark mode: reage à classe .ptah-dark no ancestral
--}}

@if($companies->isEmpty())
    {{-- Sem empresas: não renderiza nada --}}
@elseif($companies->count() === 1)
    {{-- ── Badge estático (single-tenant ou apenas 1 empresa) ──────────── --}}
    @php $c = $activeCompany; @endphp
    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-100">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
            <span class="text-white font-bold text-[10px] tracking-wide leading-none">
                {{ $c ? $c->getLabelDisplay() : '??' }}
            </span>
        </div>
        <span class="text-sm font-semibold text-indigo-700 hidden md:block max-w-[140px] truncate">
            {{ $c?->name ?? '' }}
        </span>
    </div>

@else
    {{-- ── Dropdown multi-empresa ───────────────────────────────────────── --}}
    @php
        $active = $activeCompany;
        $colorMap = [
            0 => 'bg-indigo-600',
            1 => 'bg-amber-500',
            2 => 'bg-emerald-600',
            3 => 'bg-rose-600',
            4 => 'bg-violet-600',
            5 => 'bg-sky-600',
            6 => 'bg-orange-600',
            7 => 'bg-teal-600',
        ];
        $companyColors = [];
        foreach ($companies as $idx => $co) {
            $companyColors[$co->id] = $colorMap[$idx % count($colorMap)];
        }
        $activeColor = $companyColors[$active?->id] ?? 'bg-indigo-600';
    @endphp

    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        class="relative"
    >
        {{-- Trigger --}}
        <button
            @click="open = !open"
            type="button"
            class="ptah-company-switcher-btn flex items-center gap-2 px-2.5 py-1.5 rounded-xl
                   border border-transparent
                   hover:bg-gray-50 hover:border-gray-200
                   transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
            :aria-expanded="open"
        >
            {{-- Badge sigla --}}
            <div class="w-7 h-7 rounded-lg {{ $activeColor }} flex items-center justify-center shrink-0 shadow-sm">
                <span class="text-white font-bold text-[10px] tracking-wide leading-none">
                    {{ $active?->getLabelDisplay() ?? '??' }}
                </span>
            </div>

            {{-- Nome da empresa ativa --}}
            <span class="text-sm font-semibold text-gray-700 hidden md:block max-w-[140px] truncate leading-none">
                {{ $active?->name ?? 'Empresa' }}
            </span>

            {{-- Chevron --}}
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        {{-- Dropdown panel --}}
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-1"
            class="ptah-company-switcher-panel absolute top-full mt-2 right-0 z-50
                   w-64 bg-white rounded-2xl shadow-xl shadow-black/10
                   border border-gray-100 overflow-hidden"
        >
            {{-- Header --}}
            <div class="px-4 pt-3.5 pb-2 border-b border-gray-50">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">
                    Selecionar Empresa
                </p>
            </div>

            {{-- Lista de empresas --}}
            <ul class="py-1.5 max-h-72 overflow-y-auto">
                @foreach($companies as $idx => $co)
                    @php
                        $isActive = $co->id === $activeId;
                        $color    = $colorMap[$idx % count($colorMap)];
                    @endphp
                    <li>
                        <button
                            wire:click="switchTo({{ $co->id }})"
                            type="button"
                            @click="open = false"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left
                                   transition-colors duration-100
                                   {{ $isActive
                                        ? 'bg-indigo-50 text-indigo-700'
                                        : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{-- Badge --}}
                            <div class="w-8 h-8 rounded-xl {{ $color }} flex items-center justify-center shrink-0 shadow-sm">
                                <span class="text-white font-bold text-[10px] tracking-wide leading-none">
                                    {{ $co->getLabelDisplay() }}
                                </span>
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold leading-tight truncate">
                                    {{ $co->name }}
                                </p>
                                @if($co->email)
                                    <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $co->email }}</p>
                                @endif
                            </div>

                            {{-- Check ativo --}}
                            @if($isActive)
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- Footer: link para gerenciar --}}
            @if(config('ptah.modules.company') && \Illuminate\Support\Facades\Route::has('ptah.company.index'))
            <div class="px-4 py-2.5 border-t border-gray-50">
                <a href="{{ route('ptah.company.index') }}"
                   class="flex items-center gap-2 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Gerenciar empresas
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Dark mode: regras CSS customizadas para .ptah-dark --}}
    <style>
        .ptah-dark .ptah-company-switcher-btn:hover {
            background-color: rgba(255,255,255,.05) !important;
            border-color: rgba(255,255,255,.1) !important;
        }
        .ptah-dark .ptah-company-switcher-btn span {
            color: #e2e8f0;
        }
        .ptah-dark .ptah-company-switcher-btn svg { color: #94a3b8; }
        .ptah-dark .ptah-company-switcher-panel {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-company-switcher-panel p { color: #94a3b8; }
        .ptah-dark .ptah-company-switcher-panel button { color: #e2e8f0; }
        .ptah-dark .ptah-company-switcher-panel button:hover { background-color: rgba(255,255,255,.05); }
        .ptah-dark .ptah-company-switcher-panel .border-gray-50 { border-color: #334155; }
        .ptah-dark .ptah-company-switcher-panel a { color: #818cf8; }
    </style>
@endif

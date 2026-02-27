{{--
    forge-navbar — Ptah Forge
    Props:
      - appName: string
      - logoUrl: string
      - sticky : boolean  (padrão: true)
    Slots: brand, actions
    Requer Alpine.js — emite evento 'toggle-sidebar'
    Dark mode: reage à classe .ptah-dark no ancestral (forge-dashboard-layout)
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'logoUrl' => null,
    'sticky'  => true,
])

<nav {{ $attributes->merge([
    'class' => 'ptah-navbar bg-white border-b border-gray-100 shadow-sm ' . ($sticky ? 'fixed top-0 left-0 right-0 z-50 h-16' : 'relative h-16')
]) }}>
    <div class="h-full px-4 flex items-center justify-between">

        {{-- Brand --}}
        <div class="flex items-center gap-2">
            {{-- Mobile: abre/fecha sidebar via overlay --}}
            <button
                @click="$dispatch('toggle-sidebar')"
                class="ptah-mobile-toggle ptah-navbar-icon-btn lg:hidden p-2 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
                aria-label="Toggle sidebar"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {{-- Desktop: expande/recolhe sidebar --}}
            <button
                @click="toggleSidebarCollapse()"
                :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
                class="ptah-navbar-icon-btn hidden lg:flex p-2 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
            >
                {{-- Sidebar aberta → painel esquerdo preenchido (clica para recolher) --}}
                <svg x-show="!sidebarCollapsed" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                    <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.75"/>
                    <path d="M9 3v18" stroke-width="1.75"/>
                    <path d="M4 7h3M4 12h3M4 17h3" stroke-width="1.75" stroke-linecap="round"/>
                </svg>
                {{-- Sidebar fechada → painel esquerdo vazio (clica para expandir) --}}
                <svg x-show="sidebarCollapsed" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                    <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.75"/>
                    <path d="M9 3v18" stroke-width="1.75" stroke-dasharray="2 2"/>
                    <path d="M13 9l3 3-3 3" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            @isset($brand)
                {{ $brand }}
            @else
                <a href="/" class="flex items-center gap-2">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="h-8 w-auto" />
                    @else
                        <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                            <span class="text-white font-bold text-sm">
                                {{ mb_strtoupper(mb_substr($appName, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                    <span class="ptah-navbar-app-name font-bold text-dark text-lg hidden sm:block">{{ $appName }}</span>
                </a>
            @endisset
        </div>

        {{-- Search Desktop --}}
        <div class="ptah-navbar-search hidden lg:flex flex-1 max-w-md mx-8">
            <div class="relative w-full">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="search"
                    placeholder="Buscar..."
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                           focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
                />
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-1 md:gap-2">
            @isset($actions)
                {{ $actions }}
            @else
                {{-- Botão toggle Dark Mode --}}
                <button
                    @click="toggleDark()"
                    class="ptah-navbar-icon-btn relative p-2 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
                    :title="darkMode ? 'Mudar para modo claro' : 'Mudar para modo escuro'"
                >
                    {{-- Ícone Sol (light mode ativo) --}}
                    <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                    </svg>
                    {{-- Ícone Lua (dark mode ativo) --}}
                    <svg x-show="darkMode" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                {{-- Menu Management (módulo menu) --}}
                @if(config('ptah.modules.menu') && auth()->check())
                <a
                    href="{{ \Illuminate\Support\Facades\Route::has('ptah.menu.manage') ? route('ptah.menu.manage') : '#' }}"
                    class="ptah-navbar-icon-btn relative p-2 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Gerenciar menu"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </a>
                @endif

                {{-- Notifications --}}
                <button class="ptah-navbar-icon-btn relative p-2 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-danger rounded-full"></span>
                </button>

                {{-- User Dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-gray-100 transition-colors">
                        <div class="ptah-user-avatar-bg w-8 h-8 rounded-full bg-primary-light flex items-center justify-center">
                            <span class="ptah-user-avatar-text text-primary font-semibold text-sm">
                                {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                        <span class="ptah-navbar-username hidden lg:block text-sm font-medium text-dark">
                            {{ auth()->user()->name ?? 'Usuário' }}
                        </span>
                        <svg class="hidden lg:block h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="ptah-user-dropdown absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50"
                    >
                        @php
                            $profileHref = config('ptah.modules.auth') && \Illuminate\Support\Facades\Route::has('ptah.profile')
                                ? route('ptah.profile')
                                : (\Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : '#');
                            $logoutAction = config('ptah.modules.auth') && \Illuminate\Support\Facades\Route::has('ptah.auth.logout')
                                ? route('ptah.auth.logout')
                                : (\Illuminate\Support\Facades\Route::has('logout') ? route('logout') : '#');
                        @endphp
                        <a href="{{ $profileHref }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Perfil
                        </a>
                        <hr class="my-1 border-gray-100">
                        <form method="POST" action="{{ $logoutAction }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-danger hover:bg-danger-light transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            @endisset
        </div>
    </div>
</nav>

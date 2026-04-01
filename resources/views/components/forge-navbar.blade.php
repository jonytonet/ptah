{{--
    forge-navbar — Ptah Forge
    Props:
      - appName: string
      - logoUrl: string
      - sticky : boolean  (default: true)
    Slots: brand, actions
    Requires Alpine.js — emits 'toggle-sidebar' event
    Dark mode: reacts to .ptah-dark class on ancestor (forge-dashboard-layout)
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'logoUrl' => null,
    'sticky'  => true,
])

<nav {{ $attributes->merge([
    'class' => 'ptah-navbar bg-white border-b border-gray-100 shadow-sm ' . ($sticky ? 'fixed top-0 left-0 right-0 z-50 h-16' : 'relative h-16')
]) }}>
    <div class="h-full px-4 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center">

        {{-- Brand --}}
        <div class="flex items-center gap-2">
            {{-- Mobile: abre/fecha sidebar via overlay --}}
            <button
                @click="$dispatch('toggle-sidebar')"
                class="ptah-mobile-toggle ptah-navbar-icon-btn lg:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
                aria-label="Toggle sidebar"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {{-- Desktop: expande/recolhe sidebar --}}
            <button
                @click="toggleSidebarCollapse()"
                :title="sidebarCollapsed ? 'Expand menu' : 'Collapse menu'"
                class="ptah-navbar-icon-btn hidden lg:flex p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
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
                        <div class="w-8 h-8 rounded-md bg-primary flex items-center justify-center">
                            <span class="text-white font-bold text-sm">
                                {{ mb_strtoupper(mb_substr($appName, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                    <span class="ptah-navbar-app-name font-bold text-dark text-lg hidden sm:block">{{ $appName }}</span>
                </a>
            @endisset
        </div>

        {{-- Company Switcher (centralizado) --}}
        <div class="flex items-center justify-center px-4">
            @livewire('ptah-company-switcher')
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-1 md:gap-2">
            @isset($actions)
                {{ $actions }}
            @else
                {{-- Dark Mode toggle button --}}
                <button
                    @click="toggleDark()"
                    class="ptah-navbar-icon-btn relative p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
                    :title="darkMode ? '{{ __('ptah::ui.navbar_light_title') }}' : '{{ __('ptah::ui.navbar_dark_title') }}'"
                >
                    {{-- Sun icon (light mode active) --}}
                    <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                    </svg>
                    {{-- Moon icon (dark mode active) --}}
                    <svg x-show="darkMode" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                {{-- Admin Dropdown (company + permissions + menu + ai_agent) --}}
                @if(auth()->check() && (config('ptah.modules.company') || config('ptah.modules.permissions') || config('ptah.modules.menu') || config('ptah.modules.ai_agent')))
                <div x-data="{ openAdmin: false }" class="relative">
                    <button
                        @click="openAdmin = !openAdmin"
                        :class="openAdmin ? 'bg-gray-100 text-primary' : 'text-gray-500 hover:bg-gray-100 hover:text-primary'"
                        class="ptah-navbar-icon-btn relative p-2 rounded-md transition-colors"
                        title="{{ __('ptah::ui.navbar_admin_title') }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>

                    <div
                        x-show="openAdmin"
                        x-cloak
                        @click.away="openAdmin = false"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="ptah-admin-dropdown absolute right-0 mt-2 w-56 bg-white rounded-md border border-gray-200 py-1 z-50"
                        @click="openAdmin = false"
                    >
                        {{-- Company --}}
                        @if(config('ptah.modules.company') && \Illuminate\Support\Facades\Route::has('ptah.company.index'))
                        <a href="{{ route('ptah.company.index') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_company') }}
                        </a>
                        @endif

                        {{-- Departamentos --}}
                        @if(config('ptah.modules.permissions') && \Illuminate\Support\Facades\Route::has('ptah.acl.departments'))
                        <a href="{{ route('ptah.acl.departments') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_departments') }}
                        </a>
                        @endif

                        {{-- Perfis de acesso --}}
                        @if(config('ptah.modules.permissions') && \Illuminate\Support\Facades\Route::has('ptah.acl.roles'))
                        <a href="{{ route('ptah.acl.roles') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_roles') }}
                        </a>
                        @endif

                        {{-- Pages / Objects --}}
                        @if(config('ptah.modules.permissions') && \Illuminate\Support\Facades\Route::has('ptah.acl.pages'))
                        <a href="{{ route('ptah.acl.pages') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_pages') }}
                        </a>
                        @endif

                        {{-- Users & Permissions --}}
                        @if(config('ptah.modules.permissions') && \Illuminate\Support\Facades\Route::has('ptah.acl.users'))
                        <a href="{{ route('ptah.acl.users') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_users') }}
                        </a>
                        @endif

                        {{-- Audit Log --}}
                        @if(config('ptah.modules.permissions') && \Illuminate\Support\Facades\Route::has('ptah.acl.audit'))
                        <a href="{{ route('ptah.acl.audit') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_audit') }}
                        </a>
                        @endif

                        {{-- Permissions guide --}}
                        @if(config('ptah.modules.permissions') && \Illuminate\Support\Facades\Route::has('ptah.acl.guide'))
                        <a href="{{ route('ptah.acl.guide') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_guide') }}
                        </a>
                        @endif

                        {{-- Menu --}}
                        @if(config('ptah.modules.menu') && \Illuminate\Support\Facades\Route::has('ptah.menu.manage'))
                        @if(config('ptah.modules.company') || config('ptah.modules.permissions'))
                        <hr class="my-1 border-gray-100">
                        @endif
                        <a href="{{ route('ptah.menu.manage') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                            </svg>
                            {{ __('ptah::ui.navbar_admin_menu') }}
                        </a>
                        @endif

                        {{-- AI Agent --}}
                        @if(config('ptah.modules.ai_agent') && \Illuminate\Support\Facades\Route::has('ptah.ai.models'))
                        @if(config('ptah.modules.company') || config('ptah.modules.permissions') || config('ptah.modules.menu'))
                        <hr class="my-1 border-gray-100">
                        @endif
                        <a href="{{ route('ptah.ai.models') }}"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="bx bx-bot h-4 w-4 text-gray-400 shrink-0 text-base leading-none"></i>
                            {{ __('ptah::ui.navbar_admin_ai_models') }}
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Notifications --}}
                <button class="ptah-navbar-icon-btn relative p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-danger rounded-full"></span>
                </button>

                {{-- User Dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-md hover:bg-gray-100 transition-colors">
                        @php
                            $__photoPath = auth()->user()->profile_photo_path ?? null;
                            $__photoUrl  = $__photoPath ? \Illuminate\Support\Facades\Storage::url($__photoPath) : null;
                        @endphp
                        @if($__photoUrl)
                            <img src="{{ $__photoUrl }}"
                                 alt="{{ auth()->user()->name ?? 'Avatar' }}"
                                 class="ptah-user-avatar-bg w-8 h-8 rounded-full object-cover ring-2 ring-primary/20">
                        @else
                            <div class="ptah-user-avatar-bg w-8 h-8 rounded-full bg-primary-light flex items-center justify-center">
                                <span class="ptah-user-avatar-text text-primary font-semibold text-sm">
                                    {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                </span>
                            </div>
                        @endif
                        <span class="ptah-navbar-username hidden lg:block text-sm font-medium text-dark">
                            {{ auth()->user()->name ?? 'User' }}
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
                        class="ptah-user-dropdown absolute right-0 mt-2 w-48 bg-white rounded-md border border-gray-200 py-1 z-50"
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
                            {{ __('ptah::ui.navbar_user_profile') }}
                        </a>
                        <hr class="my-1 border-gray-100">
                        <form method="POST" action="{{ $logoutAction }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2 px-4 py-2 text-sm text-danger hover:bg-danger-light transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                {{ __('ptah::ui.navbar_user_logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endisset
        </div>
    </div>
</nav>

{{--
    forge-dashboard-layout — Ptah Forge
    Full layout component for dashboard:
      sidebar + navbar + main content
    Props:
      - appName : string
      - logoUrl : string
      - title   : string
        Automatic behaviours:
            - Dark mode based on app preference (class based)
      - Sidebar collapse/expand persisted in localStorage
    Usage:
      <x-forge-dashboard-layout>
          <x-slot:title>Dashboard</x-slot:title>
          <p>Your content here</p>
      </x-forge-dashboard-layout>
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'logoUrl' => null,
    'title'   => null,
    'theme'   => null,
])

@php
    // Aba "Aparência" de /profile (Ptah\Livewire\Auth\ProfilePage). O banco continua
    // sendo a fonte da verdade para usuário autenticado — renderizar os 4 atributos
    // aqui (em vez de via Alpine/localStorage) é o que evita flash no F5/navegação.
    // O cookie ptah_appearance (ver AppearancePresets::queueCookie) é só fallback:
    // visitante sem sessão, ou usuário autenticado que nunca salvou preferência.
    // Nunca inverter essa ordem — ver AppearancePresets::sanitize() para o porquê
    // de tudo passar por ali antes de tocar um atributo HTML.
    //
    // Precedência final do $theme (mode claro/escuro): prop :theme do chamador >
    // banco (autenticado) > cookie > localStorage (script abaixo) > claro.
    $ptahDbTheme = auth()->check() ? \Ptah\Models\UserPreference::get(auth()->id(), 'theme') : null;
    $ptahCookieTheme = \Ptah\Support\AppearancePresets::decodeCookie(request()->cookie(\Ptah\Support\AppearancePresets::COOKIE));
    $ptahAppearance = \Ptah\Support\AppearancePresets::sanitize($ptahDbTheme ?? $ptahCookieTheme);
    $theme = $theme ?? $ptahAppearance['mode'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-ptah-light="{{ $ptahAppearance['light'] }}"
    data-ptah-dark="{{ $ptahAppearance['dark'] }}"
    data-ptah-accent="{{ $ptahAppearance['accent'] }}"
    data-ptah-text="{{ $ptahAppearance['text'] }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $appName }}{{ isset($title) ? ' — ' . $title : '' }}</title>

    {{--
        Tailwind CSS:
        - Se o projeto usa Vite com @tailwindcss/vite, os estilos já vêm via @vite abaixo.
        - Fallback para CDN apenas quando não há assets compilados (desenvolvimento sem build).
    --}}
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            primary: { DEFAULT: '{{ config('ptah.theme.colors.primary', '#5b21b6') }}', light: '#dbeafe', dark: '#1e3a8a' },
                            success: { DEFAULT: '{{ config('ptah.theme.colors.success', '#10b981') }}', light: '#d1fae5', dark: '#059669' },
                            danger:  { DEFAULT: '{{ config('ptah.theme.colors.danger', '#ef4444') }}', light: '#fee2e2', dark: '#dc2626' },
                            warn:    { DEFAULT: '{{ config('ptah.theme.colors.warn', '#f59e0b') }}', light: '#fef3c7', dark: '#d97706' },
                            dark:    { DEFAULT: '{{ config('ptah.theme.colors.dark', '#1e293b') }}', light: '#f1f5f9', dark: '#0f172a' },
                        }
                    }
                }
            }
        </script>
    @endif
    {{-- Icon libraries: Boxicons + FontAwesome Free --}}
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">

    {{-- Brand palette from config('ptah.theme.colors') --}}
    @include('ptah::partials.theme-colors')

    {{-- BEING DISMANTLED — do not add color here. Guarded by LayoutStyleBaselineTest, which
         holds a golden fixture of all 184 declaration sites and a ceiling that only ever
         shrinks (127 hex literals / 107 rules today).

         An earlier note called the whole block un-tokenizable. That was measured and is wrong:
         only 21 rules repaint Tailwind utility classes from a distance (.text-gray-400,
         .bg-slate-50, …), which works ONLY because an inline <style> is unlayered and so beats
         @layer utilities — those must move together with the view that uses them, and a green
         fixture does NOT prove such a move is safe (the fixture records what a rule declares,
         never which rule wins). The other ~84 rules select this package's own semantic classes
         (.ptah-sidebar, .ptah-nav-item, .ptah-navbar, …), compete with nothing, and should be
         migrated to resources/css/ptah-components.css using the --ptah-* neutral tokens.

         Why it matters: a literal written here is invisible to the user's theme choice, so the
         sidebar and navbar would stay slate no matter which tone the user picks. --}}
    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-none { scrollbar-width: none; -ms-overflow-style: none; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        @keyframes wave { 0%, 100% { transform: scaleY(0.4); } 50% { transform: scaleY(1.0); } }
        .animate-wave { animation: wave 1s ease-in-out infinite; }

        /* ─── Ptah Dark Mode ─────────────────────────────────────────── */
        /* Aplicado via .ptah-dark na div raiz, detectado do SO e/ou     */
        /* sobrescrito manualmente pelo usuário via localStorage.         */

        /* ─── Page Header ──────────────────────────────────── */
        .ptah-dark .ptah-page-header h1 { color: #e2e8f0; }
        .ptah-dark .ptah-page-header p  { color: #94a3b8; }
        .ptah-dark .ptah-page-header a  { background-color: #334155; color: #cbd5e1; }
        .ptah-dark .ptah-page-header a:hover { background-color: #475569; }

        /* ─── Buttons ───────────────────────────────────────── */
        .ptah-dark .ptah-btn-light,
        .ptah-dark .ptah-btn-secondary {
            background-color: #334155 !important;
            color: #e2e8f0 !important;
            box-shadow: none !important;
        }
        .ptah-dark .ptah-btn-light:hover,
        .ptah-dark .ptah-btn-secondary:hover { background-color: #475569 !important; }

        /* ─── Inputs ─────────────────────────────────────────── */
        .ptah-dark .ptah-input-wrapper label   { color: #94a3b8; }
        .ptah-dark .ptah-input-wrapper input {
            background-color: #1e293b;
            border-color: #475569;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-input-wrapper input::placeholder { color: #64748b; }
        .ptah-dark .ptah-input-wrapper input:disabled     { background-color: #0f172a; }
        .ptah-dark .ptah-input-wrapper .text-gray-400     { color: #64748b; }

        /* ─── Textarea ───────────────────────────────────────── */
        .ptah-dark .ptah-textarea-wrapper label    { color: #94a3b8; }
        .ptah-dark .ptah-textarea-wrapper textarea {
            background-color: #1e293b;
            border-color: #475569;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-textarea-wrapper textarea::placeholder { color: #64748b; }
        .ptah-dark .ptah-textarea-wrapper .text-gray-500 { color: #94a3b8; }

        /* ─── Select ─────────────────────────────────────────── */
        .ptah-dark .ptah-select-wrapper > label { color: #94a3b8; }
        .ptah-dark .ptah-select-trigger {
            background-color: #1e293b;
            border-color: #475569;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-select-dropdown {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-select-dropdown li { color: #cbd5e1; }
        .ptah-dark .ptah-select-dropdown li:hover { background-color: #334155; color: #e2e8f0; }

        /* ─── Stat Cards ────────────────────────────────────── */
        .ptah-dark .ptah-stat-card                { background-color: #1e293b; }
        .ptah-dark .ptah-stat-card .text-gray-500 { color: #94a3b8; }
        .ptah-dark .ptah-stat-card .text-dark     { color: #e2e8f0; }
        .ptah-dark .ptah-stat-card .text-gray-400 { color: #64748b; }

        /* ─── Modal ──────────────────────────────────────────── */
        .ptah-dark .ptah-modal-panel {
            background-color: #1e293b;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-modal-panel .border-b,
        .ptah-dark .ptah-modal-panel .border-t { border-color: #334155; }
        .ptah-dark .ptah-modal-panel h3              { color: #e2e8f0; }
        .ptah-dark .ptah-modal-panel .text-gray-700  { color: #cbd5e1; }
        .ptah-dark .ptah-modal-panel .text-gray-400  { color: #64748b; }
        .ptah-dark .ptah-modal-panel .text-gray-600  { color: #94a3b8; }
        .ptah-dark .ptah-modal-panel button.text-gray-400:hover { color: #e2e8f0; }

        /* ─── Table ──────────────────────────────────────────── */
        .ptah-dark .ptah-table-wrapper input[type="search"] {
            background-color: #1e293b;
            border-color: #475569;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-table-wrapper input[type="search"]::placeholder { color: #64748b; }
        /* Mobile cards */
        .ptah-dark .ptah-table-wrapper .bg-white.rounded-md {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-table-wrapper .text-gray-500 { color: #94a3b8; }
        .ptah-dark .ptah-table-wrapper .text-dark     { color: #cbd5e1; }
        .ptah-dark .ptah-table-wrapper .text-gray-400 { color: #64748b; }
        /* Desktop table */
        .ptah-dark .ptah-table-wrapper .overflow-x-auto.rounded-md { border-color: #334155; }
        .ptah-dark .ptah-table-wrapper thead tr {
            background-color: #0f172a;
            border-color: #334155;
        }
        .ptah-dark .ptah-table-wrapper thead th { color: #94a3b8; }
        .ptah-dark .ptah-table-wrapper tbody    { background-color: #1e293b; }
        .ptah-dark .ptah-table-wrapper tbody tr { border-color: #334155; }
        .ptah-dark .ptah-table-wrapper tbody td { color: #cbd5e1; }
        .ptah-dark .ptah-table-wrapper tbody tr:hover { background-color: rgba(91,33,182,.08); }

        /* ─── Pagination ──────────────────────────────────────── */
        .ptah-dark .ptah-pagination button:not(.bg-primary)      { color: #94a3b8; border-color: #475569; }
        .ptah-dark .ptah-pagination button:not(.bg-primary):hover { background-color: #334155; color: #e2e8f0; }
        .ptah-dark .ptah-pagination .text-gray-500 { color: #94a3b8; }
        .ptah-dark .ptah-pagination .text-gray-400 { color: #64748b; }

        /* ─── Badge light ────────────────────────────────────── */
        .ptah-dark .ptah-badge-light { background-color: #475569; color: #e2e8f0; }

        /* ─── Alert ──────────────────────────────────────────── */
        .ptah-dark .ptah-alert-primary { background-color: rgba(91,33,182,.18); }
        .ptah-dark .ptah-alert-success { background-color: rgba(16,185,129,.15); }
        .ptah-dark .ptah-alert-danger  { background-color: rgba(239,68,68,.15); }
        .ptah-dark .ptah-alert-warn    { background-color: rgba(245,158,11,.15); }

        /* ─── Module Toolbar (company/permission views) ──────── */
        .ptah-dark .ptah-module-toolbar {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-module-toolbar input[type="search"],
        .ptah-dark .ptah-module-toolbar select {
            background-color: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-module-toolbar input[type="search"]::placeholder { color: #64748b; }

        /* ─── Module Table ────────────────────────────────────── */
        .ptah-dark .ptah-module-table { border-color: #334155; }
        .ptah-dark .ptah-module-table thead tr {
            background-color: #1e293b;
            border-color: #475569;
        }
        .ptah-dark .ptah-module-table thead th { color: #94a3b8; }
        .ptah-dark .ptah-module-table tbody { background-color: #0f172a; }
        .ptah-dark .ptah-module-table tbody tr { border-color: #334155; }
        .ptah-dark .ptah-module-table tbody td { color: #cbd5e1; }
        .ptah-dark .ptah-module-table tbody tr:hover { background-color: #1e293b; }
        .ptah-dark .ptah-module-table .text-slate-800 { color: #e2e8f0; }
        .ptah-dark .ptah-module-table .text-slate-500 { color: #94a3b8; }
        .ptah-dark .ptah-module-table .text-slate-400 { color: #64748b; }
        .ptah-dark .ptah-module-table .bg-slate-100   { background-color: #334155; }
        .ptah-dark .ptah-module-table .bg-slate-50    { background-color: #1e293b; }
        .ptah-dark .ptah-module-table .text-slate-700  { color: #cbd5e1; }
        .ptah-dark .ptah-module-table .text-slate-300  { color: #475569; }

        /* ─── Modal genérico (slate classes) ─────────────── */
        .ptah-dark .ptah-modal-panel .text-slate-600 { color: #94a3b8; }
        .ptah-dark .ptah-modal-panel .text-slate-700 { color: #cbd5e1; }
    </style>

    {{-- Livewire (se disponível) --}}
    @if(class_exists(\Livewire\Livewire::class))
        @livewireStyles
    @endif

    @stack('styles')

    {{-- Anti-FOUC: resolve the theme and paint the dark class BEFORE first paint.
         Mirrors the Alpine darkMode logic exactly so there is never a light flash
         on F5 / navigation. Shared with layouts/forge-auth.blade.php — see the
         partial for the full precedence rules. --}}
    @include('ptah::partials.appearance-boot')
</head>
<body class="font-sans antialiased">

    {{--
        x-data raiz:
          sidebarOpen       — mobile: sidebar aberta/fechada
          sidebarCollapsed  — desktop: sidebar colapsada (icon-only) / expandida
          darkMode          — tema escuro ativo
                Persistencia em localStorage:
                    ptah_sidebar_collapsed -> 'true'/'false'
                    ptah_dark_mode         -> 'true'/'false'

        ATENÇÃO — NUNCA use aspas duplas dentro do x-data abaixo, nem em código nem em
        comentário. O atributo é delimitado por aspas duplas, então a primeira aspa
        interna FECHA o atributo e todo o resto do script é cuspido na página como
        texto visível. Foi exatamente o que aconteceu: três ocorrências (a palavra
        "mode" num comentário, um seletor meta[name=csrf-token] e um exemplo de
        seletor de preset) despejaram o Alpine inteiro na tela. Prosa longa vem para
        cá, em comentário Blade; dentro do atributo, só aspas simples.
        Guardado por LayoutXDataQuotingTest.

        Sobre o portador do tema: <html> é o único elemento que recebe .ptah-dark/.dark
        (applyTheme mais abaixo). Isso é invariante de arquitetura, não estilo: o
        @@custom-variant dark de forge.css já cobre toda a subárvore, inclusive conteúdo
        teleportado para o body, e os atributos data-ptah-* dos presets de aparência só
        existem no <html>. Se .ptah-dark voltasse a ser aplicada no body, os presets de
        tom escuro deixariam de casar e o body herdaria os tokens do bloco .ptah-dark
        genérico — silenciosamente, sem erro. Ver ThemeCarrierTest.
    --}}
    <div
        x-data="{
            sidebarOpen: false,

            sidebarCollapsed: localStorage.getItem('ptah_sidebar_collapsed') === 'true',

            isMd: window.innerWidth >= 768,
            isLg: window.innerWidth >= 1024,

            darkMode: (function() {
                var serverTheme = @js($theme);
                if (serverTheme === 'dark' || serverTheme === 'light') {
                    localStorage.setItem('ptah_dark_mode', serverTheme === 'dark');
                    return serverTheme === 'dark';
                }
                var saved = localStorage.getItem('ptah_dark_mode');
                if (saved !== null) return saved === 'true';
                return false;
            })(),

            // Rota de persistência do modo claro/escuro — só existe quando o usuário
            // está autenticado E o módulo de auth está habilitado (mesmo gate da rota
            // /profile, ver routes/ptah-auth.php). null para visitante: toggleDark()
            // então permanece 100% localStorage, como sempre foi.
            themeModeEndpoint: @js(auth()->check() && \Illuminate\Support\Facades\Route::has('ptah.appearance.theme-mode')
                ? route('ptah.appearance.theme-mode')
                : null),

            persistThemeMode() {
                if (!this.themeModeEndpoint) return;
                var token = document.querySelector('meta[name=csrf-token]');
                fetch(this.themeModeEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    },
                    body: JSON.stringify({ mode: this.darkMode ? 'dark' : 'light' }),
                    keepalive: true,
                }).catch(() => {});
            },

            applyTheme(isDark) {
                document.documentElement.classList.toggle('ptah-dark', isDark);
                document.documentElement.classList.toggle('dark', isDark);
            },

            init() {
                /* Portador do tema: ver o comentario Blade acima deste elemento. */
                this.applyTheme(this.darkMode);
                /* Atualiza breakpoints reativamente */
                this._onResize = () => { this.isMd = window.innerWidth >= 768; this.isLg = window.innerWidth >= 1024; };
                window.addEventListener('resize', this._onResize);
            },

            destroy() {
                window.removeEventListener('resize', this._onResize);
            },

            toggleDark() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('ptah_dark_mode', this.darkMode);
                this.applyTheme(this.darkMode);
                this.persistThemeMode();
            },

            toggleSidebarCollapse() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('ptah_sidebar_collapsed', this.sidebarCollapsed);
            }
        }"
        class="min-h-screen"
    >

        {{-- Sidebar --}}
        <x-forge-sidebar :app-name="$appName" :logo-url="$logoUrl" />

        {{-- Main content — margem reage ao estado da sidebar --}}
        <div
            :style="isLg ? { marginLeft: sidebarCollapsed ? '4rem' : '16rem' } : (isMd ? { marginLeft: '4rem' } : {})"
            class="transition-all duration-300 ml-0"
        >

            {{-- Navbar --}}
            <x-forge-navbar :app-name="$appName" :logo-url="$logoUrl" />

            {{-- Page --}}
            <main class="pt-16 min-h-screen">
                <div class="p-4 md:p-6 lg:p-8">
                    {{ $slot }}
                </div>
            </main>

            {{-- Pilha global de toasts: escuta `ptah-toast` no window, serve qualquer tela --}}
            <x-forge-toast-host />
        </div>
    </div>

    {{-- Notification area --}}
    <x-forge-notification />

    @auth
        @if(config('ptah.modules.ai_agent') && class_exists(\Livewire\Livewire::class))
            <livewire:ptah-ai-chat-widget />
        @endif
    @endauth

    @if(class_exists(\Livewire\Livewire::class))
        @livewireScripts
    @endif

    @stack('scripts')
</body>
</html>

{{--
    forge-dashboard-layout — Ptah Forge
    Componente de layout completo para dashboard:
      sidebar + navbar + conteúdo principal
    Props:
      - appName : string
      - logoUrl : string
      - title   : string
    Comportamentos automáticos:
      - Dark mode baseado no SO do usuário (prefers-color-scheme) com override manual
      - Sidebar collapse/expand persistido em localStorage
    Uso:
      <x-forge-dashboard-layout>
          <x-slot:title>Dashboard</x-slot:title>
          <p>Seu conteúdo aqui</p>
      </x-forge-dashboard-layout>
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'logoUrl' => null,
    'title'   => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
                theme: {
                    extend: {
                        colors: {
                            primary: { DEFAULT: '#5b21b6', light: '#ede9fe', dark: '#4c1d95' },
                            success: { DEFAULT: '#10b981', light: '#d1fae5', dark: '#059669' },
                            danger:  { DEFAULT: '#ef4444', light: '#fee2e2', dark: '#dc2626' },
                            warn:    { DEFAULT: '#f59e0b', light: '#fef3c7', dark: '#d97706' },
                            dark:    { DEFAULT: '#1e293b', light: '#f1f5f9', dark: '#0f172a' },
                        }
                    }
                }
            }
        </script>
    @endif
    {{-- Icon libraries: Boxicons + FontAwesome Free --}}
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">

    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-none { scrollbar-width: none; -ms-overflow-style: none; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        @keyframes wave { 0%, 100% { transform: scaleY(0.4); } 50% { transform: scaleY(1.0); } }
        .animate-wave { animation: wave 1s ease-in-out infinite; }

        /* ─── Ptah Dark Mode ─────────────────────────────────────────── */
        /* Aplicado via .ptah-dark na div raiz, detectado do SO e/ou     */
        /* sobrescrito manualmente pelo usuário via localStorage.         */

        /* Body / Root */
        .ptah-dark { background-color: #0f172a; color: #e2e8f0; }

        /* Sidebar */
        .ptah-dark .ptah-sidebar {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-sidebar .ptah-sidebar-logo-wrapper {
            border-color: #334155;
        }
        .ptah-dark .ptah-sidebar .ptah-sidebar-app-name {
            color: #e2e8f0;
        }
        .ptah-dark .ptah-sidebar .ptah-sidebar-toggle {
            color: #94a3b8;
        }
        .ptah-dark .ptah-sidebar .ptah-sidebar-toggle:hover {
            background-color: #334155;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-sidebar .ptah-nav-item {
            color: #94a3b8;
        }
        .ptah-dark .ptah-sidebar .ptah-nav-item:hover {
            background-color: #334155;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-sidebar .ptah-nav-item.ptah-nav-active {
            background-color: #312e81;
            color: #a5b4fc;
        }
        .ptah-dark .ptah-sidebar .ptah-sidebar-footer {
            border-color: #334155;
        }
        .ptah-dark .ptah-sidebar .ptah-logout-btn:hover {
            background-color: #450a0a;
        }
        .ptah-dark .ptah-sidebar-overlay {
            /* overlay mobile não muda no dark — já é escuro */
        }

        /* Navbar */
        .ptah-dark .ptah-navbar {
            background-color: #1e293b;
            border-color: #334155;
            box-shadow: 0 1px 3px rgba(0,0,0,.4);
        }
        .ptah-dark .ptah-navbar .ptah-navbar-search input {
            background-color: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-navbar .ptah-navbar-search input::placeholder {
            color: #64748b;
        }
        .ptah-dark .ptah-navbar .ptah-navbar-icon-btn {
            color: #94a3b8;
        }
        .ptah-dark .ptah-navbar .ptah-navbar-icon-btn:hover {
            background-color: #334155;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-navbar .ptah-navbar-app-name {
            color: #e2e8f0;
        }
        .ptah-dark .ptah-navbar .ptah-navbar-username {
            color: #cbd5e1;
        }
        .ptah-dark .ptah-navbar .ptah-user-avatar-bg {
            background-color: #312e81;
        }
        .ptah-dark .ptah-navbar .ptah-user-avatar-text {
            color: #a5b4fc;
        }
        .ptah-dark .ptah-navbar .ptah-user-dropdown {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-navbar .ptah-user-dropdown a,
        .ptah-dark .ptah-navbar .ptah-user-dropdown button {
            color: #cbd5e1;
        }
        .ptah-dark .ptah-navbar .ptah-user-dropdown a:hover {
            background-color: #334155;
        }
        .ptah-dark .ptah-navbar .ptah-user-dropdown hr {
            border-color: #334155;
        }
        .ptah-dark .ptah-navbar .ptah-mobile-toggle {
            color: #94a3b8;
        }
        .ptah-dark .ptah-navbar .ptah-mobile-toggle:hover {
            background-color: #334155;
            color: #e2e8f0;
        }

        /* Main content */
        .ptah-dark main {
            background-color: #0f172a;
        }
    </style>

    {{-- Livewire (se disponível) --}}
    @if(class_exists(\Livewire\Livewire::class))
        @livewireStyles
    @endif

    @stack('styles')
</head>
<body class="font-sans antialiased">

    {{--
        x-data raiz:
          sidebarOpen       — mobile: sidebar aberta/fechada
          sidebarCollapsed  — desktop: sidebar colapsada (icon-only) / expandida
          darkMode          — tema escuro ativo
        Persistência em localStorage:
          ptah_sidebar_collapsed  → 'true'/'false'
          ptah_dark_mode          → 'true'/'false' | null (null = seguir SO)
    --}}
    <div
        x-data="{
            sidebarOpen: false,

            sidebarCollapsed: localStorage.getItem('ptah_sidebar_collapsed') === 'true',

            darkMode: (function() {
                var saved = localStorage.getItem('ptah_dark_mode');
                if (saved !== null) return saved === 'true';
                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            })(),

            init() {
                /* Reagir a mudanças de preferência do SO em tempo real */
                var self = this;
                window.matchMedia('(prefers-color-scheme: dark)')
                    .addEventListener('change', function(e) {
                        if (localStorage.getItem('ptah_dark_mode') === null) {
                            self.darkMode = e.matches;
                        }
                    });
            },

            toggleDark() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('ptah_dark_mode', this.darkMode);
            },

            toggleSidebarCollapse() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('ptah_sidebar_collapsed', this.sidebarCollapsed);
            }
        }"
        :class="{ 'ptah-dark': darkMode }"
        class="min-h-screen"
    >

        {{-- Sidebar --}}
        <x-forge-sidebar :app-name="$appName" :logo-url="$logoUrl" />

        {{-- Main content — margem reage ao estado da sidebar --}}
        <div
            :class="sidebarCollapsed ? 'md:ml-16' : 'md:ml-16 lg:ml-64'"
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
        </div>
    </div>

    {{-- Notification area --}}
    <x-forge-notification />

    @if(class_exists(\Livewire\Livewire::class))
        @livewireScripts
    @endif

    @stack('scripts')
</body>
</html>

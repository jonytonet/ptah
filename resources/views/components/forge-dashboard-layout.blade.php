{{--
    forge-dashboard-layout — Ptah Forge
    Full layout component for dashboard:
      sidebar + navbar + main content
    Props:
      - appName : string
      - logoUrl : string
      - title   : string
    Automatic behaviours:
      - Dark mode based on OS preference (prefers-color-scheme) with manual override
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
        .ptah-dark .ptah-navbar .ptah-admin-dropdown {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-navbar .ptah-admin-dropdown a,
        .ptah-dark .ptah-navbar .ptah-admin-dropdown button {
            color: #cbd5e1;
        }
        .ptah-dark .ptah-navbar .ptah-admin-dropdown a:hover {
            background-color: #334155;
        }
        .ptah-dark .ptah-navbar .ptah-admin-dropdown svg {
            color: #64748b;
        }
        .ptah-dark .ptah-navbar .ptah-admin-dropdown hr {
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

        /* ─── Page Header ──────────────────────────────────── */
        .ptah-dark .ptah-page-header h1 { color: #e2e8f0; }
        .ptah-dark .ptah-page-header p  { color: #94a3b8; }
        .ptah-dark .ptah-page-header a  { background-color: #334155; color: #cbd5e1; }
        .ptah-dark .ptah-page-header a:hover { background-color: #475569; }

        /* ─── Cards ────────────────────────────────────────── */
        .ptah-dark .ptah-card-default {
            background-color: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        .ptah-dark .ptah-card-default .border-b,
        .ptah-dark .ptah-card-default .border-t { border-color: #334155; }

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
        .ptah-dark .ptah-table-wrapper .bg-white.rounded-xl {
            background-color: #1e293b;
            border-color: #334155;
        }
        .ptah-dark .ptah-table-wrapper .text-gray-500 { color: #94a3b8; }
        .ptah-dark .ptah-table-wrapper .text-dark     { color: #cbd5e1; }
        .ptah-dark .ptah-table-wrapper .text-gray-400 { color: #64748b; }
        /* Desktop table */
        .ptah-dark .ptah-table-wrapper .overflow-x-auto.rounded-xl { border-color: #334155; }
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

        /* ─── Page Title ─────────────────────────────────────── */
        .ptah-dark .ptah-page-title { color: #e2e8f0; }

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

        /* ─── Company Switcher ───────────────────────────── */
        .ptah-switcher-name {
            color: #1e293b;
        }
        .ptah-switcher-sep {
            display: inline-block;
            width: 1px;
            height: 1.1em;
            background-color: #cbd5e1;
            border-radius: 1px;
            flex-shrink: 0;
        }
        .ptah-switcher-bar {
            background-color: #f1f5f9;
        }
        .ptah-switcher-tab {
            color: #64748b;
            background: transparent;
        }
        .ptah-switcher-tab:hover {
            background-color: #ddd6fe;
            color: #4c1d95;
        }
        .ptah-switcher-tab--active {
            background-color: #5b21b6 !important;
            color: #ffffff !important;
            box-shadow: 0 1px 5px rgba(91,33,182,.35);
        }
        .ptah-dark .ptah-switcher-name  { color: #e2e8f0; }
        .ptah-dark .ptah-switcher-sep   { background-color: #475569; }
        .ptah-dark .ptah-switcher-bar   { background-color: #1e293b; }
        .ptah-dark .ptah-switcher-tab   { color: #94a3b8; }
        .ptah-dark .ptah-switcher-tab:hover {
            background-color: rgba(167,139,250,.15);
            color: #c4b5fd;
        }
        .ptah-dark .ptah-switcher-tab--active {
            background-color: #5b21b6 !important;
            color: #ffffff !important;
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
                /* Aplica ptah-dark no body para cobrir elementos @@teleport('body') */
                document.body.classList.toggle('ptah-dark', this.darkMode);

                /* Reagir a mudanças de preferência do SO em tempo real */
                var self = this;
                window.matchMedia('(prefers-color-scheme: dark)')
                    .addEventListener('change', function(e) {
                        if (localStorage.getItem('ptah_dark_mode') === null) {
                            self.darkMode = e.matches;
                            document.body.classList.toggle('ptah-dark', self.darkMode);
                        }
                    });
            },

            toggleDark() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('ptah_dark_mode', this.darkMode);
                document.body.classList.toggle('ptah-dark', this.darkMode);
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

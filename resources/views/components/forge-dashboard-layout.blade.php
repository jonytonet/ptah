{{--
    forge-dashboard-layout — Ptah Forge
    Componente de layout completo para dashboard:
      sidebar + navbar + conteúdo principal
    Props:
      - appName: string
      - logoUrl: string
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
    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-none { scrollbar-width: none; -ms-overflow-style: none; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        @keyframes wave { 0%, 100% { transform: scaleY(0.4); } 50% { transform: scaleY(1.0); } }
        .animate-wave { animation: wave 1s ease-in-out infinite; }
    </style>

    {{-- Livewire (se disponível) --}}
    @if(class_exists(\Livewire\Livewire::class))
        @livewireStyles
    @endif

    @stack('styles')
</head>
<body class="bg-gray-50 font-sans antialiased">

    <div x-data="{ sidebarOpen: false }" class="min-h-screen">

        {{-- Sidebar --}}
        <x-forge-sidebar :app-name="$appName" :logo-url="$logoUrl" />

        {{-- Main content shifted by sidebar --}}
        <div class="transition-all duration-300 ml-0 md:ml-16 lg:ml-64">

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

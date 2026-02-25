{{--
    Layout: forge-auth
    Uso:
      @extends('ptah::layouts.forge-auth')
      @section('title', 'Login')
      @section('content')
          ...
      @endsection
    — ou —
      <x-forge-auth-layout>
          ...
      </x-forge-auth-layout>
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'title'   => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $appName }}{{ isset($title) ? ' — ' . $title : '' }}</title>

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
    <style>[x-cloak] { display: none !important; }</style>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @if(class_exists(\Livewire\Livewire::class))
        @livewireStyles
    @endif

    @stack('styles')
</head>
<body class="min-h-screen bg-gradient-to-br from-primary/5 via-white to-primary/10 flex flex-col items-center justify-center p-4 font-sans antialiased">

    {{-- Branding --}}
    <div class="mb-8 text-center">
        <div class="w-14 h-14 rounded-2xl bg-primary mx-auto flex items-center justify-center shadow-lg mb-3">
            <span class="text-white text-2xl font-bold">
                {{ mb_strtoupper(mb_substr(config('app.name', 'P'), 0, 1)) }}
            </span>
        </div>
        <h1 class="text-2xl font-bold text-dark">{{ config('app.name', 'Ptah') }}</h1>
    </div>

    {{-- Card --}}
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
        @isset($title)
            <h2 class="text-xl font-semibold text-dark mb-6">{{ $title }}</h2>
        @endisset

        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endhasSection
    </div>

    {{-- Footer --}}
    <p class="mt-6 text-sm text-gray-400">
        &copy; {{ date('Y') }} {{ config('app.name', 'Ptah') }}. Todos os direitos reservados.
    </p>

    @if(class_exists(\Livewire\Livewire::class))
        @livewireScripts
    @endif

    @stack('scripts')
</body>
</html>

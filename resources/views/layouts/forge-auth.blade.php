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
@php
    $appName = $appName ?? config('app.name', 'Ptah');
    $title   = $title ?? null;

    // Não há usuário autenticado nestas telas (login, 2FA, esqueci a senha,
    // redefinir senha) — a ÚNICA fonte possível é o cookie ptah_appearance
    // (ver AppearancePresets::queueCookie). Sempre sanitizado antes de tocar
    // um atributo HTML: o cookie é controlado pelo cliente. Numa máquina
    // compartilhada, isto faz a tela de login aparecer no tema do último
    // usuário daquele navegador — ver docs/Configuration.md.
    $ptahAppearance = \Ptah\Support\AppearancePresets::sanitize(
        \Ptah\Support\AppearancePresets::decodeCookie(request()->cookie(\Ptah\Support\AppearancePresets::COOKIE))
    );
    $theme = $ptahAppearance['mode'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-ptah-light="{{ $ptahAppearance['light'] }}"
    data-ptah-dark="{{ $ptahAppearance['dark'] }}"
    data-ptah-accent="{{ $ptahAppearance['accent'] }}"
    data-ptah-text="{{ $ptahAppearance['text'] }}"
    data-ptah-density="{{ $ptahAppearance['density'] }}"
    data-ptah-fontsize="{{ $ptahAppearance['fontsize'] }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $appName }}{{ isset($title) ? ' — ' . $title : '' }}</title>

    @php $ptahAuthColors = config('ptah.theme.colors', []); @endphp

    {{-- Mesmo esquema do forge-dashboard-layout: quando o host tem build, os estilos
         vêm por @vite — e é isso que traz o ptah-components.css, onde vivem as regras
         dos presets de aparência. Sem este ramo, os atributos data-ptah-* que o cookie
         acabou de colocar no <html> não têm nenhuma regra para casar nesta tela, e o
         tema escolhido simplesmente não aparece no login. O CDN abaixo continua sendo
         o fallback para projeto sem build (onde o pacote já não tem CSS de componente). --}}
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
                        primary: { DEFAULT: '{{ $ptahAuthColors['primary'] ?? '#5b21b6' }}', light: '#eff6ff', dark: '#1e3a8a' },
                        success: { DEFAULT: '{{ $ptahAuthColors['success'] ?? '#10b981' }}', light: '#d1fae5', dark: '#059669' },
                        danger:  { DEFAULT: '{{ $ptahAuthColors['danger'] ?? '#ef4444' }}', light: '#fee2e2', dark: '#dc2626' },
                        warn:    { DEFAULT: '{{ $ptahAuthColors['warn'] ?? '#f59e0b' }}', light: '#fef3c7', dark: '#d97706' },
                        dark:    { DEFAULT: '{{ $ptahAuthColors['dark'] ?? '#1e293b' }}', light: '#f1f5f9', dark: '#0f172a' },
                    }
                }
            }
        }
    </script>
    @endif

    {{-- Brand palette from config('ptah.theme.colors') for ptah-components.css --}}
    @include('ptah::partials.theme-colors')
    <style>[x-cloak] { display: none !important; }</style>

    {{-- Anti-FOUC: resolve the theme and paint the dark class BEFORE first paint.
         Same partial as forge-dashboard-layout.blade.php — see it for the full
         precedence rules; here $theme comes from the ptah_appearance cookie only. --}}
    @include('ptah::partials.appearance-boot')

    {{-- Alpine via CDN apenas se Livewire não estiver presente.
         Livewire 4 já embute o Alpine internamente — carregar dois causa conflito. --}}
    @if(!class_exists(\Livewire\Livewire::class))
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endif

    @if(class_exists(\Livewire\Livewire::class))
        @livewireStyles
    @endif

    @stack('styles')
</head>
<body class="min-h-screen ptah-c-auth_bg flex flex-col items-center justify-center p-4 font-sans antialiased">

    {{-- Branding --}}
    <div class="mb-8 text-center">
        <div class="w-14 h-14 rounded-md bg-primary mx-auto flex items-center justify-center mb-3">
            <span class="text-white text-2xl font-bold">
                {{ mb_strtoupper(mb_substr(config('app.name', 'P'), 0, 1)) }}
            </span>
        </div>
        <h1 class="text-2xl font-bold ptah-c-section_ttl">{{ config('app.name', 'Ptah') }}</h1>
    </div>

    {{-- Card --}}
    <div class="w-full max-w-md ptah-card-default rounded-md p-8 border">
        @isset($title)
            <h2 class="text-xl font-semibold ptah-c-section_ttl mb-6">{{ $title }}</h2>
        @endisset

        @hasSection('content')
            @yield('content')
        @endif
        @sectionMissing('content')
            {{ $slot ?? '' }}
        @endif
    </div>

    {{-- Footer --}}
    <p class="mt-6 text-sm ptah-c-muted">
        &copy; {{ date('Y') }} {{ config('app.name', 'Ptah') }}. Todos os direitos reservados.
    </p>

    {{-- Alpine Collapse plugin — required for x-collapse (sidebar sub-menus) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>

    @if(class_exists(\Livewire\Livewire::class))
        @livewireScripts
    @endif

    @stack('scripts')
</body>
</html>


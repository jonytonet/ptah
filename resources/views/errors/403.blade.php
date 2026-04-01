{{--
    ptah::errors.403 — Página de Acesso Negado
    ─────────────────────────────────────────────
    Renderizada automaticamente pelo PtahServiceProvider quando:
      1. O app lançar um HttpException com status 403
      2. O app NÃO possuir seu próprio resources/views/errors/403.blade.php

    Publique para personalizar:
      php artisan vendor:publish --tag=ptah-errors
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('ptah::ui.error_403_title') }} — {{ config('app.name', 'Ptah') }}</title>

    {{-- Tailwind: usa assets compilados se disponíveis, senão CDN --}}
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
                            primary: { DEFAULT: '#1e40af', light: '#dbeafe', dark: '#1e3a8a' },
                            danger:  { DEFAULT: '#ef4444', light: '#fee2e2', dark: '#dc2626' },
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        /* Dark mode via preferência do sistema como fallback */
        @media (prefers-color-scheme: dark) {
            .auto-dark-bg  { background-color: #0f172a; }
            .auto-dark-txt { color: #e2e8f0; }
            .auto-dark-card { background-color: #1e293b; border-color: #334155; }
            .auto-dark-muted { color: #94a3b8; }
        }
        /* Dark mode via classe .ptah-dark (prioridade sobre media query) */
        .ptah-dark .auto-dark-bg  { background-color: #0f172a; }
        .ptah-dark .auto-dark-txt { color: #e2e8f0; }
        .ptah-dark .auto-dark-card { background-color: #1e293b; border-color: #334155; }
        .ptah-dark .auto-dark-muted { color: #94a3b8; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gray-50 auto-dark-bg auto-dark-txt">

    {{--
        Detecta preferência de dark mode salva em localStorage e aplica .ptah-dark
        no body antes de pintar a página, evitando flash de conteúdo.
    --}}
    <script>
        (function () {
            try {
                var pref = localStorage.getItem('ptah_dark_mode');
                if (pref === 'dark' || (pref === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.body.classList.add('ptah-dark');
                }
            } catch (e) {}
        })();
    </script>

    <div class="w-full max-w-lg">

        {{-- Card principal --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8 text-center auto-dark-card">

            {{-- Ícone de escudo / cadeado --}}
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-red-50">
                <svg class="w-10 h-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 2.25c-1.657 0-3.315.398-4.8 1.118L5.4 4.5A2.25 2.25 0 003.75 6.57v3.68c0 4.648 3.015 8.782 7.5 10.5 4.485-1.718 7.5-5.852 7.5-10.5V6.57a2.25 2.25 0 00-1.65-2.152l-1.8-.632A10.455 10.455 0 0012 2.25z"/>
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 8.25v4.5m0 3h.008v.008H12V15.75z"/>
                </svg>
            </div>

            {{-- Código de erro --}}
            <p class="text-6xl font-extrabold text-red-500 leading-none mb-2">403</p>

            {{-- Título --}}
            <h1 class="text-2xl font-bold text-gray-900 auto-dark-txt mb-3">
                {{ __('ptah::ui.error_403_heading') }}
            </h1>

            {{-- Descrição --}}
            <p class="text-gray-500 auto-dark-muted text-sm leading-relaxed mb-8">
                {{ __('ptah::ui.error_403_body') }}
            </p>

            {{-- Ações --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">

                {{-- Voltar --}}
                <a href="javascript:history.back()"
                   class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 auto-dark-card auto-dark-txt">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    {{ __('ptah::ui.error_403_btn_back') }}
                </a>

                {{-- Dashboard --}}
                <a href="{{ config('ptah.auth.home', '/dashboard') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg bg-blue-700 text-white hover:bg-blue-800 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                    {{ __('ptah::ui.error_403_btn_dashboard') }}
                </a>
            </div>

            {{-- Usuário autenticado --}}
            @auth
                <p class="mt-6 text-xs text-gray-400 auto-dark-muted">
                    {{ __('ptah::ui.logged_as', ['name' => auth()->user()->name ?? auth()->user()->email]) }}
                </p>
            @endauth
        </div>

        {{-- Branding --}}
        <p class="text-center text-xs text-gray-400 auto-dark-muted mt-6">
            Powered by <span class="font-semibold text-blue-700">Ptah Forge</span>
        </p>
    </div>

</body>
</html>

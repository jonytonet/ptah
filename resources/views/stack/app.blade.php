{{--
    The page of a screen in the React or Vue stack (1.59.0).

    A shell, nothing else: the front end mounts on #ptah-app and reads what it
    needs from the data attributes — the screen key, never a model. The entry
    file is the host's (resources/js/ptah-app.*, written by ptah:install
    --stack=react|vue); the components come from the package through the
    `@ptah` alias.

    Expects: $screen (the key of CrudScreen::register), $title (optional).
--}}
@php
    $ptahLogout = config('ptah.modules.auth') && \Illuminate\Support\Facades\Route::has('ptah.auth.logout')
        ? route('ptah.auth.logout')
        : (\Illuminate\Support\Facades\Route::has('logout') ? route('logout') : null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? '') !== '' ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    {{-- The menu's icons (the same set the Livewire layout loads). --}}
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    {{-- The app's CSS too, when it has one: a cell arrives rendered by the
         server (badge, pill, progress…) with the classes of the Livewire
         screen, which the app's Tailwind build provides. --}}
    @vite(array_values(array_filter([
        file_exists(resource_path('css/app.css')) ? 'resources/css/app.css' : null,
        config('ptah.crud_api.entry', 'resources/js/ptah-app.jsx'),
    ])))
</head>
<body style="margin:0">
    <div id="ptah-app"
         data-screen="{{ $screen }}"
         data-title="{{ $title ?? '' }}"
         data-brand="{{ config('app.name') }}"
         data-locale="{{ app()->getLocale() }}"
         data-api="{{ url(config('ptah.crud_api.prefix', 'ptah/api')) }}"
         @if ($ptahLogout) data-logout="{{ $ptahLogout }}" @endif></div>
</body>
</html>

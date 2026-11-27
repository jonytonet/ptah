{{--
    The page of a screen in the React or Vue stack (1.59.0).

    A shell, nothing else: the front end mounts on #ptah-app and reads what it
    needs from the data attributes — the screen key, never a model. The entry
    file is the host's (resources/js/ptah-app.*, written by ptah:install
    --stack=react|vue); the components come from the package through the
    `@ptah` alias.

    Expects: $screen (the key of CrudScreen::register) for a CRUD page, or
    $page (login, profile, dashboard…) with optional $props; $title (optional).
--}}
@php
    $ptahLogout = config('ptah.modules.auth') && \Illuminate\Support\Facades\Route::has('ptah.auth.logout')
        ? route('ptah.auth.logout')
        : (\Illuminate\Support\Facades\Route::has('logout') ? route('logout') : null);
    // The same presets, from the same place, as the Livewire layout: the
    // database for a signed-in user, else the ptah_appearance cookie.
    $ptahAppearance = \Ptah\Support\AppearancePresets::resolveForStandalonePage();
    $theme = $ptahAppearance['mode'] ?? null;
    $ptahThemeMode = auth()->check() && \Illuminate\Support\Facades\Route::has('ptah.appearance.theme-mode') ? route('ptah.appearance.theme-mode') : null;
    $ptahProfile = config('ptah.modules.auth') && \Illuminate\Support\Facades\Route::has('ptah.profile') ? route('ptah.profile') : null;
    // The menu and the user, the very answers of GET {api}/menu and {api}/me:
    // the layout paints complete on the first frame. Not for a visitor nor a
    // guest page; not when the front end swaps pages in place
    // (X-Ptah-Navigate) — its layout is already on screen.
    $ptahBoot = request()->headers->has('X-Ptah-Navigate') ? null : \Ptah\Support\StackBoot::forPage($page ?? 'crud');
    $ptahBootJson = $ptahBoot === null ? false : json_encode($ptahBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // The first answers of a CRUD screen (schema, listing, filters, config
    // button), so opening it is this one request — see StackBoot::answers().
    $ptahEmbeds = \Ptah\Support\StackBoot::embeds();
    $ptahAnswers = $ptahEmbeds && ($page ?? 'crud') === 'crud' && ($screen ?? '') !== '' ? \Ptah\Support\StackBoot::answers((string) $screen) : null;
    $ptahAnswersJson = $ptahAnswers === null ? false : json_encode($ptahAnswers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-ptah-light="{{ $ptahAppearance['light'] }}"
    data-ptah-dark="{{ $ptahAppearance['dark'] }}"
    data-ptah-accent="{{ $ptahAppearance['accent'] }}"
    data-ptah-text="{{ $ptahAppearance['text'] }}"
    data-ptah-density="{{ $ptahAppearance['density'] }}"
    data-ptah-modal-drag="{{ config('ptah.crud.modal_draggable', true) ? '1' : '0' }}"
    data-ptah-fontsize="{{ $ptahAppearance['fontsize'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? '') !== '' ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    {{-- Paints light/dark before the first frame — the very script of the Livewire layouts. --}}
    @include('ptah::partials.appearance-boot')
    {{-- Until React/Vue mount, the page's canvas (dark mode included), not a white body. --}}
    <style>body{margin:0;background:var(--ptah-canvas)}#ptah-app{min-height:100vh;background:var(--ptah-canvas)}</style>
    {{-- The menu's icons (the same set the Livewire layout loads). --}}
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    {{-- The app's CSS too, when it has one: a cell arrives rendered by the
         server (badge, pill, progress…) with the classes of the Livewire
         screen, which the app's Tailwind build provides. --}}
    @vite(array_values(array_filter([
        file_exists(resource_path('css/app.css')) ? 'resources/css/app.css' : null,
        config('ptah.crud_api.entry', 'resources/js/ptah-app.jsx'),
    ])))
    {{-- The brand palette of config('ptah.theme.colors') (PTAH_COLOR_PRIMARY…),
         after the app's CSS as in the Livewire layouts: without it the accent
         of these screens stayed the app.css default, not the host's colour. --}}
    @include('ptah::partials.theme-colors')
</head>
<body style="margin:0">
    <div id="ptah-app"
         data-page="{{ $page ?? 'crud' }}"
         data-screen="{{ $screen ?? '' }}"
         data-props="{{ json_encode((object) ($props ?? [])) }}"
         data-title="{{ $title ?? '' }}"
         data-brand="{{ config('app.name') }}"
         data-locale="{{ app()->getLocale() }}"
         data-api="{{ url(config('ptah.crud_api.prefix', 'ptah/api')) }}"
         @if ($ptahThemeMode) data-theme-mode="{{ $ptahThemeMode }}" @endif
         @if ($ptahProfile) data-profile="{{ $ptahProfile }}" @endif
         @if ($ptahLogout) data-logout="{{ $ptahLogout }}" @endif
         @if ($ptahEmbeds) data-embed="1" @endif
         @if ($ptahAnswersJson !== false) data-answers="{{ $ptahAnswersJson }}" @endif
         @if ($ptahBootJson !== false) data-boot="{{ $ptahBootJson }}" @endif></div>
</body>
</html>

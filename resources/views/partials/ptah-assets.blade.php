{{--
    ptah::partials.ptah-assets — `<x-ptah-assets />` / `@ptahAssets`

    What a HOST layout needs, in its <head>, to render ptah screens (a BaseCrud,
    an <x-forge-*>) outside the package's layouts. Everything else the
    forge-dashboard-layout gives a screen is optional chrome. See docs/Css.md.

      - bootstrap mode: the pre-compiled stylesheet (public/vendor/ptah/ptah.css),
        before the host's own CSS — place this tag before the host's @vite, so a
        host rule overriding a ptah token wins by order (as in a Tailwind host);
      - the icon fonts the screens use (Boxicons + Font Awesome);
      - the brand colours of config('ptah.theme.colors');
      - the anti-flash dark/light boot;
      - the toast host (the "undo" of a deletion lives there), appended to
        <body> when the document is parsed — it cannot be printed in <head>.

    In tailwind mode the host's app.css already carries ptah-components.css
    (`ptah:install`), so no stylesheet is linked.
--}}
@php
    $ptahDbTheme = auth()->check() ? \Ptah\Models\UserPreference::get(auth()->id(), 'theme') : null;
    $ptahCookieTheme = \Ptah\Support\AppearancePresets::decodeCookie(request()->cookie(\Ptah\Support\AppearancePresets::COOKIE));
    $theme = \Ptah\Support\AppearancePresets::sanitize($ptahDbTheme ?? $ptahCookieTheme)['mode'];
@endphp
{{ \Ptah\Support\CssFramework::stylesheetTag() }}
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
@include('ptah::partials.theme-colors', ['ptahEmbedded' => true])
@include('ptah::partials.appearance-boot')
<template id="ptah-assets-body"><x-forge-toast-host /></template>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var source = document.getElementById('ptah-assets-body');
        if (!source) return;
        document.body.appendChild(source.content.cloneNode(true));
        source.remove();
    });
</script>

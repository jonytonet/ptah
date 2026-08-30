{{--
    Rewritten onto ptah::errors.layout in 1.29.0.

    The previous version was a standalone document with its own Tailwind CDN
    fallback and ~13 hardcoded hex values, so it ignored the user's chosen
    theme entirely — reported by the package author. It also carried its own
    `.auto-dark-*` mechanism, duplicated per page, which would have had to be
    copied into every new error page.

    The shell handles all of that once: tokens when the package stylesheet is
    loaded, literal fallbacks when it is not. See the note at the top of
    layout.blade.php for why the fallback chain, not a choice between the two.
--}}
@extends('ptah::errors.layout')

@section('code', '403')
@section('title', __('ptah::ui.error_403_heading'))
@section('body', __('ptah::ui.error_403_body'))

@section('glyph')
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
    </svg>
@endsection

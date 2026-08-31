{{--
    405 Method Not Allowed.

    Added in 1.29.2 after a live sweep of every status: the other five had a
    themed page and this one silently fell through to Laravel's default, so a
    dark-theme user hit a white screen. It is reachable without any developer
    mistake — a stale form re-submitted after a route changed verb, or a
    bookmarked POST — so it belongs in the set.

    No "reload" action here, deliberately: retrying the same verb on the same
    URL produces the same 405. Back and Home are the only useful moves.
--}}
@extends('ptah::errors.layout')

@section('code', '405')
@section('title', __('ptah::ui.error_405_title'))
@section('body', __('ptah::ui.error_405_body'))

@section('glyph')
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636A9 9 0 105.636 18.364 9 9 0 0018.364 5.636zM5.636 5.636l12.728 12.728"/>
    </svg>
@endsection

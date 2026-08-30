@extends('ptah::errors.layout')

@section('code', '419')
@section('title', __('ptah::ui.error_419_title'))
@section('body', __('ptah::ui.error_419_body'))

@section('glyph')
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
@endsection

@section('actions')
    <a href="{{ url()->current() }}" class="err-btn err-btn--primary">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        {{ __('ptah::ui.error_btn_reload') }}
    </a>
@endsection

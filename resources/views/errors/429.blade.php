@extends('ptah::errors.layout')

@section('code', '429')
@section('title', __('ptah::ui.error_429_title'))
@section('body', __('ptah::ui.error_429_body'))

@section('glyph')
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
    </svg>
@endsection

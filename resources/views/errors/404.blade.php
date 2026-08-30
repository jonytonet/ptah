@extends('ptah::errors.layout')

@section('code', '404')
@section('title', __('ptah::ui.error_404_title'))
@section('body', __('ptah::ui.error_404_body'))

@section('glyph')
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 9.5l-1.8 5.2-5.2 1.8 1.8-5.2 5.2-1.8z"/>
    </svg>
@endsection

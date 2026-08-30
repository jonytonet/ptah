@extends('ptah::errors.layout')

@section('code', '500')
@section('title', __('ptah::ui.error_500_title'))
@section('body', __('ptah::ui.error_500_body'))

@section('glyph')
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.96l-6.93-12a2 2 0 00-3.5 0l-6.93 12A2 2 0 005.07 19z"/>
    </svg>
@endsection

@section('reference')
    @if (! empty($errorId))
        <p class="err-ref">
            {{ __('ptah::ui.error_500_reference') }} <code>{{ $errorId }}</code>
        </p>
    @endif
@endsection

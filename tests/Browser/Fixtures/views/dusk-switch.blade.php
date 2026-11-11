{{-- Fixture de ForgeSwitchWireModelBrowserTest: forge-switch com e sem wire:model. --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk Switch</x-slot:title>

    @livewire('dusk-switch')
</x-forge-dashboard-layout>

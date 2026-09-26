{{-- Fixture de MaskTypedDuringRequestBrowserTest: BaseCrud com campo CPF mascarado. --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk Mask</x-slot:title>

    @livewire('dusk-slow-crud', ['model' => $model])
</x-forge-dashboard-layout>

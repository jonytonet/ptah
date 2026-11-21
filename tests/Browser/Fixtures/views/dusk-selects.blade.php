{{-- Fixture de FormSelectLoopBrowserTest: BaseCrud com varios selects e nenhum padrao. --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk Selects</x-slot:title>

    @livewire('dusk-slow-select-crud', ['model' => $model])
</x-forge-dashboard-layout>

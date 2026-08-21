{{-- Fixture do Fluxo 1/2/3/4/5/6/7 (ONDA IV) — reproduz o padrão real de tela
     gerada por `ptah:forge` (ver src/Stubs/view.index.stub): BaseCrud dentro
     do layout completo, não um Livewire::test() isolado. --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk CRUD</x-slot:title>

    @livewire('ptah-base-crud', ['model' => $model])
</x-forge-dashboard-layout>

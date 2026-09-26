{{-- Fixture de PageHeaderMobileBrowserTest: cabecalho com acoes largas (como os
     paineis do PetPlace com filtros no cabecalho). --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk Header</x-slot:title>

    <x-forge-page-header title="Banho e Tosa — agenda do dia" subtitle="Filtros no cabeçalho">
        <input type="date" style="width: 150px" />
        <select style="width: 160px"><option>Todas as filiais</option></select>
        <select style="width: 160px"><option>Todos os profissionais</option></select>
        <x-forge-button color="primary">Aplicar filtros</x-forge-button>
    </x-forge-page-header>
</x-forge-dashboard-layout>

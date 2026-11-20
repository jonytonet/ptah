{{-- Fixture of SpaNavigationBrowserTest: a second page carrying the same
     persisted block as dusk-other. --}}
<x-forge-dashboard-layout>
    <x-slot:title>Dusk Persist</x-slot:title>

    <p id="dusk-persist-marker">Segunda pagina com o bloco persistido.</p>

    @include('partials.dusk-persisted-panel')
</x-forge-dashboard-layout>

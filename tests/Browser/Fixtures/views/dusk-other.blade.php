{{-- Tela trivial para o Fluxo 6 (pesquisa + voltar): só precisa existir para
     que o navegador tenha para onde navegar antes do history back(). --}}
<x-forge-dashboard-layout>
    <x-slot:title>Outra Tela</x-slot:title>

    <p id="dusk-other-marker">Pagina de teste do Fluxo 6 (pesquisa + voltar).</p>

    @include('partials.dusk-persisted-panel')
</x-forge-dashboard-layout>

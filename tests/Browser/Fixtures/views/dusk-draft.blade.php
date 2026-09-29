{{-- Fixture de FormDraftBrowserTest: BaseCrud com formDraft ligado. --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk Draft</x-slot:title>

    @livewire('ptah-base-crud', ['model' => $model])

    <script>
        // O valor atual de um campo do formulario, para o teste esperar por ele.
        window.__ptahVal = function (field) {
            var el = document.querySelector('[wire\\:model="formData.' + field + '"]');
            return el ? el.value : null;
        };
        // O contexto de rascunho que o modal recebeu (ou null), e como zera-lo
        // antes de abrir de novo — o servidor do Dusk leva segundos por
        // requisicao, e o modal do Novo abre antes da resposta chegar.
        window.__ptahDraftRoot = function () { return document.querySelector('[x-data*="_draftInit"]'); };
        window.__ptahDraftKey = function () { var r = window.__ptahDraftRoot(); var d = r ? Alpine.$data(r)._draft : null; return d ? d.key : null; };
        window.__ptahDraftReset = function () { var r = window.__ptahDraftRoot(); if (r) { Alpine.$data(r)._draft = null; } };
    </script>
</x-forge-dashboard-layout>

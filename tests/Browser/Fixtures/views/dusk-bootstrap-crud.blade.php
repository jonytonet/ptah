{{-- CssBootstrapBrowserTest: ptah.css.framework = bootstrap, the package's
     layout, Bootstrap 5.3.9 loaded by the "host" (after ptah.css on purpose:
     the isolation must not depend on the order of the two sheets), and host
     Bootstrap markup next to ptah's — in the main slot and in a forge slot. --}}
@push('styles')
    <link rel="stylesheet" href="/dusk-test/bootstrap.css">
@endpush

<x-forge-dashboard-layout>
    <x-slot:title>Dusk Bootstrap</x-slot:title>

    <div id="host-zone" class="mb-3">
        <button type="button" id="host-btn" class="btn btn-primary px-3">Host</button>
        <h2 id="host-h2">Host heading</h2>
        <div class="card" id="host-card"><div class="card-body p-4" id="host-card-body">Host card</div></div>
        <table class="table" id="host-table"><tbody><tr><td id="host-td">cell</td></tr></tbody></table>
    </div>

    <x-forge-card id="forge-card">
        <span id="forge-slot-host" class="px-3">Host markup inside a forge slot</span>
    </x-forge-card>

    @livewire('ptah-base-crud', ['model' => $model])
</x-forge-dashboard-layout>

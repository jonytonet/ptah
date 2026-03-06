<div class="ptah-base-crud" wire:key="base-crud-{{ $crudTitle }}">

    {{-- Mensagens de sessao --}}
    @if (session('crud-success') || $exportStatus)
        <x-forge-alert type="success" :dismissible="true" class="mb-3">
            {{ session('crud-success', $exportStatus) }}
        </x-forge-alert>
    @endif

    @if (!empty($crudConfig))

        @include('ptah::livewire.base-crud.partials._toolbar')

        @include('ptah::livewire.base-crud.partials._filter-panel')

        @include('ptah::livewire.base-crud.partials._table')

        @include('ptah::livewire.base-crud.partials._pagination')

    @else
        <x-forge-alert type="warning">
            {{ __('ptah::ui.crud_no_config') }} <strong>{{ $model }}</strong>.
            Execute <code>php artisan ptah:forge {{ $model }}</code> para gerar.
        </x-forge-alert>
    @endif

    @include('ptah::livewire.base-crud.partials._modal-form')

    @include('ptah::livewire.base-crud.partials._modal-delete')

    {{-- Loading overlay global --}}
    <div wire:loading.delay.long wire:target="save,deleteRecord,sortBy,export"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/20">
        <x-forge-spinner color="primary" size="lg" />
    </div>

    @include('ptah::livewire.base-crud.partials._scripts')

</div>

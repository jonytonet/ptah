<!DOCTYPE html>
<html lang="en" data-ptah-modal-drag="{{ config('ptah.crud.modal_draggable', true) ? '1' : '0' }}">
<head>
    <meta charset="UTF-8">
    <title>Dusk — a plain forge-modal</title>
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body>
    {{-- A forge-modal that does not pass `draggable`: since 1.63.2 it drags
         by default (ptah.crud.modal_draggable). --}}
    <div x-data="{ open: true }">
        <x-forge-modal title="Sem draggable">
            <p>Corpo</p>
        </x-forge-modal>
    </div>
    @livewireScripts
</body>
</html>

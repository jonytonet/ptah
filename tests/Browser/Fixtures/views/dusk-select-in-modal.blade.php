<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dusk — forge-select inside a forge-modal</title>
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body>
    {{-- The access modal of the permissions screen: a select near the end of a
         modal body that scrolls, and the modal draggable (its panel keeps a
         `translate`, a containing block for position:fixed). The open list
         must be drawn on top, whole — not cut by the body (1.63.1). --}}
    <div x-data="{ open: true }">
        <x-forge-modal title="Acesso" size="lg" draggable>
            <div style="height: 14rem">Roles atribuidos</div>
            <div id="role-select">
                <x-forge-select
                    label="Role"
                    :options="[
                        ['value' => 0, 'label' => 'Selecione...'],
                        ['value' => 1, 'label' => 'Administrador'],
                        ['value' => 2, 'label' => 'Financeiro'],
                        ['value' => 3, 'label' => 'Vendas'],
                        ['value' => 4, 'label' => 'Estoque'],
                        ['value' => 5, 'label' => 'Ultima Opcao'],
                    ]"
                />
            </div>
            <x-slot name="footer">
                <button type="button">Fechar</button>
            </x-slot>
        </x-forge-modal>
    </div>
    @livewireScripts
</body>
</html>

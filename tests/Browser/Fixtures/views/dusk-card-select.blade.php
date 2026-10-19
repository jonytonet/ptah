<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dusk — forge-select inside forge-card</title>
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body>
    <main class="p-8 max-w-md">
        {{-- Um card baixo: a lista de 8 opcoes tem de passar da borda dele. --}}
        <x-forge-card id="the-card">
            <x-forge-select
                label="Forma de pagamento"
                :options="collect(['Pix', 'Boleto', 'Cartao credito', 'Cartao debito', 'Dinheiro', 'Cheque', 'Transferencia', 'Prazo 30 dias'])
                    ->map(fn ($l) => ['value' => \Illuminate\Support\Str::slug($l), 'label' => $l])->all()"
            />
        </x-forge-card>
        <p class="mt-2">Abaixo do card.</p>
    </main>
    @livewireScripts
</body>
</html>

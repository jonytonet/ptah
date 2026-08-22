<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dusk — searchable forge-select</title>
    <link rel="stylesheet" href="/dusk-test/ptah-components.css">
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body>
    <main class="p-8 max-w-md">
        {{-- No Livewire context on purpose: the component guards its wire:model
             seeding with isset($__livewire), and every behavior this browser
             test exercises (filter, diacritics, arrows, reopen-reset) is pure
             Alpine. --}}
        <div id="with-none">
            <x-forge-select
                searchable
                label="Permission"
                :options="[
                    ['value' => '',            'label' => 'None (everyone sees it)'],
                    ['value' => 'ver-custo',   'label' => 'Ver Custo Médio'],
                    ['value' => 'permissao-x', 'label' => 'Permissão Especial'],
                    ['value' => 'outra-coisa', 'label' => 'Outra Coisa'],
                ]"
            />
        </div>

        {{-- Sem opção de valor vazio: único cenário em que o estado "sem
             resultados" pode aparecer (a opção None nunca é filtrada). --}}
        <div id="without-none" class="mt-8">
            <x-forge-select
                searchable
                label="Category"
                :options="[
                    ['value' => 'a', 'label' => 'Alpha'],
                    ['value' => 'b', 'label' => 'Beta'],
                ]"
            />
        </div>
    </main>
    @livewireScripts
</body>
</html>

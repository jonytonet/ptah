{{-- ── Ordenação da visão em cards ───────────────────────────────────────

    A visão em cards não tinha como ordenar: a ordem vinha do que estava
    escolhido na tabela, e trocar de visão congelava a listagem naquela ordem —
    no celular, onde a tabela nunca foi aberta, isso significa "sempre id DESC"
    (reportado em uso real, ERP em produção).

    O cabeçalho clicável da tabela resolve dois gestos num só (escolher coluna e
    inverter sentido). Num toque isso não funciona, então aqui os dois gestos são
    dois controles: um select para a coluna e um botão para o sentido. Ambos
    partem de `sortableColumns()`, a MESMA fonte que decide quais cabeçalhos da
    tabela são clicáveis — select e cabeçalho não podem divergir.

    Não renderiza nada quando não há coluna ordenável (listagem só de colunas
    calculadas ou de relação), em vez de mostrar um select vazio.
--}}
@php
    $ptahSortable = $this->sortableColumns();
    $ptahIsAsc = strtoupper($direction) === 'ASC';
@endphp

@if (! empty($ptahSortable))
    <div class="ptah-c-sortbar flex items-center gap-2 mb-3" role="group"
         aria-label="{{ __('ptah::ui.sort_group_label') }}">

        <label for="ptah-sort-{{ $crudTitle }}" class="sr-only">{{ __('ptah::ui.sort_by') }}</label>

        <svg class="w-4 h-4 shrink-0 ptah-c-sortbar_icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9M3 12h5m5 4l4 4 4-4m-4 4V4"/>
        </svg>

        {{-- wire:model.live="sort" é o idioma dominante do pacote para select
             (ver _filter-panel). O hook updatedSort() carrega resetPage() +
             savePreferences() e revalida o valor contra a allowlist. --}}
        <select id="ptah-sort-{{ $crudTitle }}"
                wire:model.live="sort"
                class="ptah-c-control ptah-c-sortbar_select flex-1 min-w-0 rounded-md border"
                title="{{ __('ptah::ui.sort_by') }}">
            @foreach ($ptahSortable as $ptahCol)
                <option value="{{ $ptahCol['sortBy'] }}">{{ $ptahCol['label'] }}</option>
            @endforeach
        </select>

        {{-- O sentido é um botão e não uma segunda linha de options: dobrar as
             opções ("Nome A-Z", "Nome Z-A", ...) cresce com o número de colunas
             e some com o estado atual. Aqui o estado fica visível no ícone e no
             aria-pressed. --}}
        <button type="button"
                wire:click="toggleSortDirection"
                wire:loading.attr="disabled"
                aria-pressed="{{ $ptahIsAsc ? 'true' : 'false' }}"
                class="ptah-c-control ptah-c-sortbar_dir shrink-0 rounded-md border transition-colors"
                title="{{ $ptahIsAsc ? __('ptah::ui.sort_asc') : __('ptah::ui.sort_desc') }}"
                aria-label="{{ $ptahIsAsc ? __('ptah::ui.sort_asc') : __('ptah::ui.sort_desc') }}">
            @if ($ptahIsAsc)
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9M3 12h5M16 20V4m0 0l-4 4m4-4l4 4"/>
                </svg>
            @else
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9M3 12h5M16 4v16m0 0l4-4m-4 4l-4-4"/>
                </svg>
            @endif
        </button>
    </div>
@endif

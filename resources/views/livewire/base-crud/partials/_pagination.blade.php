{{-- ── Paginação ────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mt-4 text-sm ptah-c-pag">
    <span>
        {{ __('ptah::ui.pagination', ['first' => $rows->firstItem() ?? 0, 'last' => $rows->lastItem() ?? 0, 'total' => $rows->total()]) }}
    </span>
    <div class="flex items-center gap-3">
        {{ $rows->links('ptah::components.forge-pagination') }}
        {{-- Jump-to-page --}}
        @if ($rows->lastPage() > 2)
            @php
                // O mesmo saneamento do forge-pagination: o nome da pagina vai
                // para dentro de uma expressao Alpine, e ele vem de configuracao.
                $ptahJumpPage = preg_replace('/[^A-Za-z0-9_]/', '', (string) $rows->getPageName()) ?: 'page';
            @endphp
            <div class="hidden md:flex items-center gap-1.5 text-xs"
                 x-data="{ pg: {{ $rows->currentPage() }} }"
                 {{-- `$wire.page` NAO existe. WithPagination guarda o estado em
                      `public $paginators = []` (HandlesPagination.php:10) e nao
                      declara propriedade nem metodo `page`. O proxy $wire resolve
                      nome desconhecido como METODO remoto, e o avaliador do Alpine
                      invoca qualquer funcao que a expressao produza
                      (runIfTypeOfFunction, livewire.esm.js:1882) — entao o $watch
                      disparava uma chamada ao servidor para um metodo inexistente e
                      a requisicao INTEIRA morria com MethodNotFoundException. Nao era
                      o campo que falhava: era a tela.

                      `paginators` e propriedade publica de verdade, entao isto observa
                      estado. O `if (v)` cobre o primeiro render, antes de a chave
                      existir no array. --}}
                 x-init="$watch('$wire.paginators.{{ $ptahJumpPage }}', v => { if (v) pg = v; })">
                <span class="ptah-c-pag_jump_lbl">{{ __('ptah::ui.pagination_goto') }}</span>
                <input type="number" min="1" max="{{ $rows->lastPage() }}"
                    x-model.number="pg"
                    @keydown.enter="pg = Math.min(Math.max(1, pg), {{ $rows->lastPage() }}); $wire.gotoPage(pg, '{{ $ptahJumpPage }}')"
                    @blur="pg = Math.min(Math.max(1, pg), {{ $rows->lastPage() }}); $wire.gotoPage(pg, '{{ $ptahJumpPage }}')"
                    class="w-14 px-2 py-1 text-center text-xs rounded-md border ptah-c-pag_jump_in focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30">
            </div>
        @endif
    </div>
</div>

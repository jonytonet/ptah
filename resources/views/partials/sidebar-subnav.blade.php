{{--
    Sub-itens da forge-sidebar, de qualquer nivel (1.60.1).

    Antes a sidebar desenhava so dois niveis: um filho com filhos virava link
    para `#` e os netos sumiam, enquanto o atalho de busca e a tool find_menu
    (MenuResolver::walk) mostravam caminhos "A > B > C" que nao existiam na
    barra. Agora um filho com filhos e um subgrupo que abre e fecha, e o
    partial se chama de novo para os filhos dele. A profundidade e a da arvore
    (ptah.menu.max_depth ja a corta em Menu::nestItems).

    Recebe:
      - nodes      : os filhos a desenhar
      - trail      : rotulos do topo ate o pai — a chave do subgrupo no estado
                     SPA e o caminho, nao o rotulo: "Cadastro" existe em varios
                     ramos, e abrir um nao pode abrir os outros
      - depth      : nivel destes itens (2 = filhos do topo). A partir do 3,
                     o rotulo trunca e leva title, porque o recuo come largura
      - renderIcon : o mesmo closure da sidebar
      - ptahSpa    : SpaNavigation::enabled()
--}}
@foreach($nodes as $child)
    @php
        $childLabel  = $child['label'] ?? ($child['text'] ?? '');
        $childIcon   = $child['icon'] ?? 'bx bx-circle';
        $childKids   = (array) ($child['children'] ?? []);
        $childDeep   = $depth >= 3;
    @endphp

    @if(! empty($childKids))
        @php
            $subKey    = implode('/', [...$trail, $childLabel]);
            $subActive = \Ptah\Support\MenuResolver::isActiveBranch($child);
        @endphp
        <li x-data="{ open: {{ $subActive ? 'true' : 'false' }}@if($ptahSpa) || !!(window.ptahSpa && window.ptahSpa.open.indexOf({{ \Illuminate\Support\Js::from($subKey) }}) !== -1)@endif }"@if($ptahSpa) data-ptah-group="{{ $subKey }}" @endif>
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                aria-haspopup="true"
                class="ptah-nav-item w-full flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-200
                    {{ $subActive ? 'text-primary font-semibold' : 'hover:text-primary' }}"
            >
                <span class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-sm">
                    {!! $renderIcon($childIcon) !!}
                </span>
                <span class="flex-1 min-w-0 text-left text-sm {{ $childDeep ? 'truncate' : 'whitespace-nowrap' }}"@if($childDeep) title="{{ $childLabel }}"@endif>{{ $childLabel }}</span>
                <svg
                    :class="open ? 'rotate-180' : ''"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <ul x-show="open" x-collapse class="mt-0.5 ml-3 pl-3 border-l space-y-0.5 ptah-c-sidebar_subnav">
                @include('ptah::partials.sidebar-subnav', [
                    'nodes' => $childKids,
                    'trail' => [...$trail, $childLabel],
                    'depth' => $depth + 1,
                    'renderIcon' => $renderIcon,
                    'ptahSpa' => $ptahSpa,
                ])
            </ul>
        </li>
    @else
        @php
            // SafeUrl como no item de primeiro nivel: uma linha gravada
            // antes da 1.34.8 (quando qualquer logado gravava menu) ou por
            // seeder ainda podia trazer `javascript:` (1.41.9).
            $childUrl    = \Ptah\Support\SafeUrl::sanitize($child['url'] ?? '#');
            $childTarget = $child['target'] ?? '_self';
            $childMatch  = $child['match'] ?? ltrim($childUrl, '/');
            $childActive = $childMatch ? request()->is($childMatch) : false;
        @endphp
        <li>
            <a
                href="{{ $childUrl }}"
                target="{{ $childTarget }}"{!! \Ptah\Support\SpaNavigation::attr($childUrl, $childTarget, ! \Ptah\Support\SpaNavigation::optsOut($child)) !!}
                @if ($childActive) aria-current="page" @endif
                class="ptah-nav-item flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-200
                    {{ $childActive
                        ? 'ptah-nav-active bg-primary-light text-primary font-semibold'
                        : 'hover:text-primary'
                    }}"
            >
                <span class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-sm">
                    {!! $renderIcon($childIcon) !!}
                </span>
                @if($childDeep)
                    <span class="min-w-0 truncate text-sm" title="{{ $childLabel }}">{{ $childLabel }}</span>
                @else
                    <span class="whitespace-nowrap text-sm">{{ $childLabel }}</span>
                @endif
            </a>
        </li>
    @endif
@endforeach

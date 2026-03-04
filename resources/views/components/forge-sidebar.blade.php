{{--
    forge-sidebar — Ptah Forge
    Props:
      - appName: string
      - logoUrl: string
      - items  : array de menu items (sobreescreve config ptah.forge.sidebar_items)
                 ['label', 'url', 'icon', 'match', 'children'?]
    Comportamentos:
      - Collapse/expand no desktop (icon-only) — estado persistido em localStorage (ptah_sidebar_collapsed)
      - Dark mode via classe .ptah-dark no ancestral (forge-dashboard-layout)
      - Mobile: overlay + deslize lateral via evento 'toggle-sidebar'
      - Ícones: classes CSS Boxicons (bx bx-home, bx bxs-gear…) ou FontAwesome (fas fa-user, fab fa-…)
      - driver=database: inicia com Dashboard fixo; grupos rendem como acordeon Alpine
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'logoUrl' => null,
    'items'   => null,
])

@php
    $usingDatabase = (config('ptah.modules.menu') && config('ptah.menu.driver') === 'database');

    // Prioridade: prop > MenuService (driver=database) > config
    if ($items !== null) {
        $menuItems = $items;
    } elseif ($usingDatabase) {
        $menuItems = app(\Ptah\Services\Menu\MenuService::class)->getTree();
    } else {
        $rawConfig = config('ptah.forge.sidebar_items', []);
        $menuItems = array_map(fn($i) => array_merge(['children' => [], 'type' => 'menuLink'], $i), $rawConfig);
    }

    // Quando usa banco de dados: injeta Dashboard fixo no topo
    if ($usingDatabase) {
        $dashUrl   = \Illuminate\Support\Facades\Route::has('ptah.dashboard') ? route('ptah.dashboard') : '/dashboard';
        $dashFixed = [
            'id'        => null,
            'label'     => 'Dashboard',
            'text'      => 'Dashboard',
            'url'       => $dashUrl,
            'icon'      => 'bx bx-home-alt',
            'type'      => 'menuLink',
            'target'    => '_self',
            'is_active' => true,
            'match'     => 'dashboard',
            'children'  => [],
        ];
        array_unshift($menuItems, $dashFixed);
    }

    // Fallback quando vazio
    if (empty($menuItems)) {
        $menuItems = [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bx bx-home-alt',  'type' => 'menuLink', 'match' => 'dashboard', 'children' => []],
            ['label' => 'Users',     'url' => '/users',     'icon' => 'bx bx-user',       'type' => 'menuLink', 'match' => 'users*',    'children' => []],
            ['label' => 'Products',  'url' => '/products',  'icon' => 'bx bx-cube',       'type' => 'menuLink', 'match' => 'products*', 'children' => []],
            ['label' => 'Reports',   'url' => '/reports',   'icon' => 'bx bx-bar-chart',  'type' => 'menuLink', 'match' => 'reports*',  'children' => []],
            ['label' => 'Settings',  'url' => '/settings',  'icon' => 'bx bx-cog',        'type' => 'menuLink', 'match' => 'settings*', 'children' => []],
        ];
    }

    /**
     * Renderiza ícone: aceita classes CSS Boxicons ("bx bx-home") ou FontAwesome ("fas fa-user").
     * Ícones desconhecidos ou vazios fazem fallback para bx bx-circle.
     */
    $renderIcon = function(string $icon): string {
        $cls = (trim($icon) !== '') ? e($icon) : 'bx bx-circle';
        return '<i class="' . $cls . ' text-xl leading-none w-5 h-5 flex-shrink-0 flex items-center justify-center"></i>';
    };
@endphp

{{-- Overlay mobile --}}
<div
    x-show="sidebarOpen"
    @click="sidebarOpen = false"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="ptah-sidebar-overlay fixed inset-0 bg-black/50 z-30 lg:hidden"
    style="display: none;"
></div>

{{-- Sidebar --}}
<aside
    x-data="{ hovered: false }"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
    :class="{
        'translate-x-0':     sidebarOpen,
        '-translate-x-full': !sidebarOpen,
        'lg:w-16':  sidebarCollapsed && !hovered,
        'lg:w-64':  !sidebarCollapsed || hovered,
    }"
    class="ptah-sidebar fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-100 flex flex-col
           transition-all duration-300 ease-in-out
           md:translate-x-0 md:w-16 md:hover:w-64 lg:translate-x-0"
    @toggle-sidebar.window="sidebarOpen = !sidebarOpen"
>
    {{-- Logo --}}
    <div class="ptah-sidebar-logo-wrapper h-16 flex items-center gap-3 px-4 border-b border-gray-100 flex-shrink-0">
        <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center flex-shrink-0">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="h-6 w-6 object-contain" />
            @else
                <span class="text-white font-bold text-sm">
                    {{ mb_strtoupper(mb_substr($appName, 0, 1)) }}
                </span>
            @endif
        </div>
        <span
            :style="(sidebarCollapsed && !hovered) ? 'opacity:0;width:0;overflow:hidden;' : 'opacity:1;'"
            class="ptah-sidebar-app-name font-bold text-dark text-base whitespace-nowrap transition-all duration-300">
            {{ $appName }}
        </span>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 scrollbar-none">
        <ul class="space-y-1">
            @foreach($menuItems as $item)
                @php
                    $itemType   = $item['type'] ?? 'menuLink';
                    $itemLabel  = $item['label'] ?? ($item['text'] ?? '');
                    $itemIcon   = $item['icon'] ?? 'bx bx-circle';
                    $itemUrl    = $item['url'] ?? '#';
                    $itemTarget = $item['target'] ?? '_self';
                    $itemMatch  = $item['match'] ?? ltrim($itemUrl, '/');
                    $children   = $item['children'] ?? [];
                    $hasKids    = !empty($children);
                    $isActive   = $itemMatch ? (request()->is($itemMatch) || request()->is($itemMatch . '/*')) : false;
                    // Um grupo está ativo se algum filho estiver ativo
                    $groupActive = $hasKids && collect($children)->contains(function($c) {
                        $cm = $c['match'] ?? ltrim(rtrim($c['url'] ?? '#', '/'), '/');
                        return $cm && (request()->is($cm) || request()->is($cm . '/*'));
                    });
                @endphp

                {{-- ── menuGroup com filhos → acordeon Alpine ── --}}
                @if($itemType === 'menuGroup' && $hasKids)
                    <li x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                        {{-- Botão do grupo --}}
                        <button
                            type="button"
                            @click="open = !open"
                            title="{{ $itemLabel }}"
                            class="ptah-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200
                                {{ $groupActive ? 'text-primary font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-primary' }}"
                        >
                            <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
                                {!! $renderIcon($itemIcon) !!}
                            </span>
                            <span
                                :style="(sidebarCollapsed && !hovered) ? 'opacity:0;max-width:0;overflow:hidden;white-space:nowrap;' : 'opacity:1;max-width:200px;'"
                                class="flex-1 text-left whitespace-nowrap text-sm transition-all duration-300">
                                {{ $itemLabel }}
                            </span>
                            {{-- Seta --}}
                            <svg
                                :class="open ? 'rotate-180' : ''"
                                :style="(sidebarCollapsed && !hovered) ? 'opacity:0;width:0;overflow:hidden;' : 'opacity:1;'"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- Sub-itens --}}
                        <ul x-show="open" x-collapse class="mt-1 ml-3 pl-3 border-l border-gray-200 space-y-0.5">
                            @foreach($children as $child)
                                @php
                                    $childLabel  = $child['label'] ?? ($child['text'] ?? '');
                                    $childIcon   = $child['icon'] ?? 'bx bx-circle';
                                    $childUrl    = $child['url'] ?? '#';
                                    $childTarget = $child['target'] ?? '_self';
                                    $childMatch  = $child['match'] ?? ltrim($childUrl, '/');
                                    $childActive = $childMatch ? request()->is($childMatch) : false;
                                @endphp
                                <li>
                                    <a
                                        href="{{ $childUrl }}"
                                        target="{{ $childTarget }}"
                                        title="{{ $childLabel }}"
                                        class="ptah-nav-item flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-200
                                            {{ $childActive
                                                ? 'ptah-nav-active bg-primary-light text-primary font-semibold'
                                                : 'text-gray-500 hover:bg-gray-100 hover:text-primary'
                                            }}"
                                    >
                                        <span class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-sm">
                                            {!! $renderIcon($childIcon) !!}
                                        </span>
                                        <span class="whitespace-nowrap text-sm">{{ $childLabel }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>

                {{-- ── menuGroup sem filhos → label desabilitado ── --}}
                @elseif($itemType === 'menuGroup')
                    <li>
                        <div
                            title="{{ $itemLabel }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-400 cursor-default"
                        >
                            <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
                                {!! $renderIcon($itemIcon) !!}
                            </span>
                            <span
                                :style="(sidebarCollapsed && !hovered) ? 'opacity:0;max-width:0;overflow:hidden;white-space:nowrap;' : 'opacity:1;max-width:200px;'"
                                class="whitespace-nowrap text-sm italic transition-all duration-300">
                                {{ $itemLabel }}
                            </span>
                        </div>
                    </li>

                {{-- ── menuLink → link normal ── --}}
                @else
                    <li>
                        <a
                            href="{{ $itemUrl }}"
                            target="{{ $itemTarget }}"
                            title="{{ $itemLabel }}"
                            class="ptah-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 relative
                                {{ $isActive
                                    ? 'ptah-nav-active bg-primary-light text-primary font-semibold'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-primary'
                                }}"
                        >
                            <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
                                {!! $renderIcon($itemIcon) !!}
                            </span>
                            <span
                                :style="(sidebarCollapsed && !hovered) ? 'opacity:0;max-width:0;overflow:hidden;white-space:nowrap;' : 'opacity:1;max-width:200px;'"
                                class="whitespace-nowrap text-sm transition-all duration-300">
                                {{ $itemLabel }}
                            </span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>

    {{-- Logout --}}
    @php
        $logoutAction = config('ptah.modules.auth') && \Illuminate\Support\Facades\Route::has('ptah.auth.logout')
            ? route('ptah.auth.logout')
            : (\Illuminate\Support\Facades\Route::has('logout') ? route('logout') : '#');
    @endphp
    <div class="ptah-sidebar-footer p-2 border-t border-gray-100 flex-shrink-0">
        <form method="POST" action="{{ $logoutAction }}">
            @csrf
            <button
                type="submit"
                title="{{ __('ptah::ui.navbar_user_logout') }}"
                class="ptah-logout-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-danger hover:bg-danger-light transition-all duration-200"
            >
                <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
                    <i class="bx bx-log-out text-xl leading-none"></i>
                </span>
                <span
                    :style="(sidebarCollapsed && !hovered) ? 'opacity:0;max-width:0;overflow:hidden;white-space:nowrap;' : 'opacity:1;max-width:200px;'"
                    class="whitespace-nowrap text-sm font-medium transition-all duration-300">
                    {{ __('ptah::ui.navbar_user_logout') }}
                </span>
            </button>
        </form>
    </div>
</aside>

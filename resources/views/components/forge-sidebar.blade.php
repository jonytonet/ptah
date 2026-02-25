{{--
    forge-sidebar — Ptah Forge
    Props:
      - appName: string
      - logoUrl: string
      - items  : array de menu items (sobreescreve config ptah.forge.sidebar_items)
                 ['label', 'url', 'icon', 'match']
    Requer Alpine.js — escuta evento 'toggle-sidebar' global
--}}
@props([
    'appName' => config('app.name', 'Ptah'),
    'logoUrl' => null,
    'items'   => null,
])

@php
    $menuItems = $items ?? config('ptah.forge.sidebar_items', []);

    if (empty($menuItems)) {
        $menuItems = [
            ['icon' => 'home',      'label' => 'Dashboard',   'url' => '/dashboard', 'match' => 'dashboard'],
            ['icon' => 'users',     'label' => 'Usuários',    'url' => '/users',     'match' => 'users*'],
            ['icon' => 'cube',      'label' => 'Produtos',    'url' => '/products',  'match' => 'products*'],
            ['icon' => 'chart-bar', 'label' => 'Relatórios',  'url' => '/reports',   'match' => 'reports*'],
            ['icon' => 'cog',       'label' => 'Configurações','url'=> '/settings',  'match' => 'settings*'],
        ];
    }

    $svgIcons = [
        'home'      => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
        'users'     => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
        'cube'      => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>',
        'chart-bar' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>',
        'cog'       => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        'logout'    => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>',
    ];
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
    class="fixed inset-0 bg-black/50 z-30 lg:hidden"
    style="display: none;"
></div>

{{-- Sidebar --}}
<aside
    :class="{
        'translate-x-0':  sidebarOpen,
        '-translate-x-full': !sidebarOpen,
    }"
    class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-100 flex flex-col
           transition-transform duration-300 ease-in-out
           md:translate-x-0 md:w-16 md:hover:w-64 lg:w-64 lg:translate-x-0 group/sidebar"
    @toggle-sidebar.window="sidebarOpen = !sidebarOpen"
>
    {{-- Logo --}}
    <div class="h-16 flex items-center px-4 border-b border-gray-100 flex-shrink-0 overflow-hidden">
        <div class="flex items-center gap-3 min-w-max">
            <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center flex-shrink-0">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="h-6 w-6 object-contain" />
                @else
                    <span class="text-white font-bold text-sm">
                        {{ mb_strtoupper(mb_substr($appName, 0, 1)) }}
                    </span>
                @endif
            </div>
            <span class="font-bold text-dark text-base whitespace-nowrap
                         md:opacity-0 md:group-hover/sidebar:opacity-100 lg:opacity-100
                         transition-opacity duration-200">
                {{ $appName }}
            </span>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 px-2">
        <ul class="space-y-1">
            @foreach($menuItems as $item)
                @php
                    $isActive = request()->is($item['match'] ?? ltrim($item['url'], '/'));
                @endphp
                <li>
                    <a
                        href="{{ $item['url'] }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative
                            {{ $isActive
                                ? 'bg-primary-light text-primary font-semibold'
                                : 'text-gray-600 hover:bg-gray-100 hover:text-primary'
                            }}"
                    >
                        <span class="flex-shrink-0 w-5 h-5">
                            {!! $svgIcons[$item['icon']] ?? $svgIcons['cube'] !!}
                        </span>
                        <span class="whitespace-nowrap text-sm
                                     md:opacity-0 md:group-hover/sidebar:opacity-100 lg:opacity-100
                                     transition-opacity duration-200">
                            {{ $item['label'] }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Logout --}}
    <div class="p-2 border-t border-gray-100 flex-shrink-0">
        <form method="POST" action="{{ route('logout', [], false) ?? '#' }}">
            @csrf
            <button
                type="submit"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-danger hover:bg-danger-light transition-all duration-200"
            >
                <span class="flex-shrink-0 w-5 h-5">{!! $svgIcons['logout'] !!}</span>
                <span class="whitespace-nowrap text-sm font-medium
                             md:opacity-0 md:group-hover/sidebar:opacity-100 lg:opacity-100
                             transition-opacity duration-200">
                    Sair
                </span>
            </button>
        </form>
    </div>
</aside>

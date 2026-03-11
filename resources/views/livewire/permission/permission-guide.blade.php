{{-- ptah::livewire.permission.permission-guide --}}
<div>
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">{{ __('ptah::ui.guide_title') }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('ptah::ui.guide_subtitle') }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-full shrink-0">
            {{ __('ptah::ui.guide_badge') }}
        </span>
    </div>

    {{-- Navegação de abas --}}
    <div class="flex flex-wrap gap-1 mb-6 border-b border-slate-200 pb-0">
        @foreach ([
            ['key' => 'overview',  'label' => __('ptah::ui.guide_tab_overview')],
            ['key' => 'setup',     'label' => __('ptah::ui.guide_tab_setup')],
            ['key' => 'code',      'label' => __('ptah::ui.guide_tab_code')],
            ['key' => 'faq',       'label' => __('ptah::ui.guide_tab_faq')],
        ] as $tab)
            <button
                wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors -mb-px whitespace-nowrap
                    {{ $activeTab === $tab['key']
                        ? 'border-blue-600 text-blue-600'
                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         ABA 1 — VISÃO GERAL
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'overview')
    <div class="space-y-8">

        {{-- Intro --}}
        <div class="bg-gradient-to-r from-blue-50 to-slate-50 border border-blue-100 rounded-md p-6">
            <h2 class="text-lg font-bold text-blue-900 mb-2">{{ __('ptah::ui.guide_ov_title') }}</h2>
            <p class="text-sm text-blue-700 leading-relaxed">
                {!! __('ptah::ui.guide_ov_body') !!}
            </p>
        </div>

        {{-- Diagrama de arquitetura --}}
        <div>
            <h2 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-700 text-white text-xs flex items-center justify-center font-bold">1</span>
                {{ __('ptah::ui.guide_ov_arch_title') }}
            </h2>
            <div class="overflow-x-auto">
                <div class="min-w-[700px] flex items-center justify-center gap-0 py-4">

                    {{-- Departamentos --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-36 bg-amber-50 border-2 border-amber-200 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">🏢</div>
                            <p class="text-xs font-bold text-amber-800">{{ __('ptah::ui.guide_ov_dept_title') }}</p>
                            <p class="text-xs text-amber-600 mt-0.5">{{ __('ptah::ui.guide_ov_dept_desc') }}</p>
                        </div>
                        <p class="text-xs text-slate-400 text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_dept_ex') }}</p>
                    </div>

                    {{-- Seta --}}
                    <div class="flex items-center px-2 text-slate-300 text-2xl font-thin">→</div>

                    {{-- Roles --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-36 bg-purple-50 border-2 border-purple-300 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">🎭</div>
                            <p class="text-xs font-bold text-purple-800">{{ __('ptah::ui.guide_ov_roles_title') }}</p>
                            <p class="text-xs text-purple-600 mt-0.5">{{ __('ptah::ui.guide_ov_roles_desc') }}</p>
                        </div>
                        <p class="text-xs text-slate-400 text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_roles_ex') }}</p>
                    </div>

                    {{-- Seta --}}
                    <div class="flex items-center px-2 text-slate-300 text-2xl font-thin">↔</div>

                    {{-- Páginas/Objetos --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-40 bg-blue-50 border-2 border-blue-300 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">📄</div>
                            <p class="text-xs font-bold text-blue-800">{{ __('ptah::ui.guide_ov_pages_title') }}</p>
                            <p class="text-xs text-blue-600 mt-0.5">{{ __('ptah::ui.guide_ov_pages_desc') }}</p>
                        </div>
                        <p class="text-xs text-slate-400 text-center max-w-[140px]">{{ __('ptah::ui.guide_ov_pages_ex') }}</p>
                    </div>

                    {{-- Seta --}}
                    <div class="flex items-center px-2 text-slate-300 text-2xl font-thin">←</div>

                    {{-- Usuários --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-36 bg-green-50 border-2 border-green-300 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">👤</div>
                            <p class="text-xs font-bold text-green-800">{{ __('ptah::ui.guide_ov_users_title') }}</p>
                            <p class="text-xs text-green-600 mt-0.5">{{ __('ptah::ui.guide_ov_users_desc') }}</p>
                        </div>
                        <p class="text-xs text-slate-400 text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_users_ex') }}</p>
                    </div>

                    {{-- Seta --}}
                    <div class="flex items-center px-2 text-slate-300 text-2xl font-thin">←</div>

                    {{-- Empresas --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-36 bg-slate-50 border-2 border-slate-300 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">🏭</div>
                            <p class="text-xs font-bold text-slate-700">{{ __('ptah::ui.guide_ov_co_title') }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.guide_ov_co_desc') }}</p>
                        </div>
                        <p class="text-xs text-slate-400 text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_co_ex') }}</p>
                    </div>

                </div>
            </div>
        </div>

        {{-- Conceitos-chave em cards --}}
        <div>
            <h2 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-700 text-white text-xs flex items-center justify-center font-bold">2</span>
                {{ __('ptah::ui.guide_ov_concepts_title') }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🎭</span>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_con_role_title') }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {{ __('ptah::ui.guide_con_role_body') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">Admin</span>
                        <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">Vendedor</span>
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">👑 MASTER</span>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">📄</span>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_con_page_title') }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {!! __('ptah::ui.guide_con_page_body') !!}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">admin.vendas</span>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">admin.estoque</span>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🔑</span>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_con_obj_title') }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {!! __('ptah::ui.guide_con_obj_body') !!}
                    </p>
                    <div class="mt-3 grid grid-cols-4 gap-1">
                        @foreach ([__('ptah::ui.guide_con_perms_read'), __('ptah::ui.guide_con_perms_create'), __('ptah::ui.guide_con_perms_edit'), __('ptah::ui.guide_con_perms_delete')] as $perm)
                        <div class="text-center">
                            <div class="w-7 h-7 rounded-md bg-green-100 flex items-center justify-center mx-auto"><span class="text-green-600 text-xs font-bold">✓</span></div>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $perm }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">👑</span>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_con_master_title') }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {!! __('ptah::ui.guide_con_master_body') !!}
                    </p>
                    <div class="mt-3 text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-md p-2">
                        {{ __('ptah::ui.guide_con_master_warn') }}
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🏭</span>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_con_scope_title') }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {!! __('ptah::ui.guide_con_scope_body') !!}
                    </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">📋</span>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_con_audit_title') }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {!! __('ptah::ui.guide_con_audit_body') !!}
                    </p>
                </div>

            </div>
        </div>

        {{-- Fluxo de decisão --}}
        <div>
            <h2 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-700 text-white text-xs flex items-center justify-center font-bold">3</span>
                {{ __('ptah::ui.guide_ov_flow_title') }}
            </h2>
            <div class="bg-white border border-slate-200 rounded-md p-5 overflow-x-auto">
                <div class="min-w-[500px] flex flex-col items-center gap-0">
                    {{-- Início --}}
                    <div class="bg-slate-800 text-white text-xs font-semibold px-4 py-2 rounded-full">{{ __('ptah::ui.guide_flow_start') }}</div>
                    <div class="w-px h-5 bg-slate-300"></div>
                    {{-- Passo 1 --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-md px-4 py-2 text-xs text-amber-800 font-medium text-center w-64">{{ __('ptah::ui.guide_flow_q1') }}</div>
                    <div class="flex gap-8 items-start">
                        <div class="flex flex-col items-center">
                            <div class="w-px h-4 bg-slate-300"></div>
                            <div class="text-xs text-green-600 font-bold">{{ __('ptah::ui.guide_flow_yes') }}</div>
                            <div class="w-px h-4 bg-slate-300"></div>
                            {{-- Passo 2 --}}
                            <div class="bg-amber-50 border border-amber-200 rounded-md px-4 py-2 text-xs text-amber-800 font-medium text-center w-56">{{ __('ptah::ui.guide_flow_q2') }}</div>
                            <div class="flex gap-8 items-start">
                                <div class="flex flex-col items-center">
                                    <div class="w-px h-4 bg-slate-300"></div>
                                    <div class="text-xs text-green-600 font-bold">{{ __('ptah::ui.guide_flow_yes') }}</div>
                                    <div class="w-px h-4 bg-slate-300"></div>
                                    <div class="bg-green-100 border border-green-300 rounded-md px-4 py-2 text-xs text-green-800 font-bold text-center">{{ __('ptah::ui.guide_flow_granted') }}</div>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-px h-4 bg-slate-300"></div>
                                    <div class="text-xs text-red-500 font-bold">{{ __('ptah::ui.guide_flow_no') }}</div>
                                    <div class="w-px h-4 bg-slate-300"></div>
                                    {{-- Passo 3 --}}
                                    <div class="bg-amber-50 border border-amber-200 rounded-md px-4 py-2 text-xs text-amber-800 font-medium text-center w-56">{{ __('ptah::ui.guide_flow_q3') }}</div>
                                    <div class="flex gap-6 items-start mt-0">
                                        <div class="flex flex-col items-center">
                                            <div class="w-px h-4 bg-slate-300"></div>
                                            <div class="text-xs text-green-600 font-bold">{{ __('ptah::ui.guide_flow_yes') }}</div>
                                            <div class="w-px h-4 bg-slate-300"></div>
                                            <div class="bg-green-100 border border-green-300 rounded-md px-4 py-2 text-xs text-green-800 font-bold text-center">{{ __('ptah::ui.guide_flow_granted') }}</div>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-px h-4 bg-slate-300"></div>
                                            <div class="text-xs text-red-500 font-bold">{{ __('ptah::ui.guide_flow_no') }}</div>
                                            <div class="w-px h-4 bg-slate-300"></div>
                                            <div class="bg-red-100 border border-red-300 rounded-md px-4 py-2 text-xs text-red-800 font-bold text-center">{{ __('ptah::ui.guide_flow_denied') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-center mt-4">
                            <div class="text-xs text-red-500 font-bold">{{ __('ptah::ui.guide_flow_no') }}</div>
                            <div class="w-px h-4 bg-slate-300"></div>
                            <div class="bg-red-100 border border-red-300 rounded-md px-4 py-2 text-xs text-red-800 font-bold text-center">{{ __('ptah::ui.guide_flow_login') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         ABA 2 — PASSO A PASSO
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'setup')
    <div class="space-y-6">

        <x-forge-alert type="primary">
            {!! __('ptah::ui.guide_setup_prereq') !!}
        </x-forge-alert>

        {{-- Passo 1 --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 bg-slate-50 border-b border-slate-200">
                <span class="w-8 h-8 rounded-full bg-blue-700 text-white text-sm font-bold flex items-center justify-center shrink-0">1</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">{!! __('ptah::ui.guide_s1_title') !!}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.guide_s1_desc') }}</p>
                </div>
                <a href="{{ route('ptah.acl.departments') }}" class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-blue-700 hover:bg-blue-800 rounded-md transition-colors">
                    {{ __('ptah::ui.guide_s1_btn') }}
                </a>
            </div>
            <div class="px-5 py-4 space-y-3">
                <p class="text-sm text-slate-600 leading-relaxed">
                    {{ __('ptah::ui.guide_s1_body') }}
                </p>
                <div class="bg-slate-50 border border-slate-200 rounded-md p-4 text-sm text-slate-600">
                    <strong>{{ __('ptah::ui.guide_s1_example') }}:</strong>
                    <ul class="mt-2 space-y-1 list-none">
                        <li class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span> {!! __('ptah::ui.guide_s1_ex_it') !!}</li>
                        <li class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span> {!! __('ptah::ui.guide_s1_ex_sales') !!}</li>
                        <li class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span> {!! __('ptah::ui.guide_s1_ex_fin') !!}</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Passo 2 --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 bg-slate-50 border-b border-slate-200">
                <span class="w-8 h-8 rounded-full bg-blue-700 text-white text-sm font-bold flex items-center justify-center shrink-0">2</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_s2_title') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.guide_s2_desc') }}</p>
                </div>
                <a href="{{ route('ptah.acl.pages') }}" class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-blue-700 hover:bg-blue-800 rounded-md transition-colors">
                    {{ __('ptah::ui.guide_s2_btn') }}
                </a>
            </div>
            <div class="px-5 py-4 space-y-4">
                <p class="text-sm text-slate-600 leading-relaxed">
                    {!! __('ptah::ui.guide_s2_body') !!}
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <h4 class="text-xs font-bold text-blue-800 mb-2">{{ __('ptah::ui.guide_s2_page_title') }}</h4>
                        <table class="w-full text-xs">
                            <tr class="border-b border-blue-200"><td class="py-1 text-blue-600 font-medium w-24">{{ __('ptah::ui.guide_s2_page_slug') }}</td><td class="py-1 font-mono text-blue-700">admin.vendas</td></tr>
                            <tr class="border-b border-blue-200"><td class="py-1 text-blue-600 font-medium">{{ __('ptah::ui.guide_s2_page_name') }}</td><td class="py-1 text-blue-700">Módulo de Vendas</td></tr>
                            <tr><td class="py-1 text-blue-600 font-medium">{{ __('ptah::ui.guide_s2_page_icon') }}</td><td class="py-1 text-blue-700">🛒</td></tr>
                        </table>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <h4 class="text-xs font-bold text-green-800 mb-2">{{ __('ptah::ui.guide_s2_obj_title') }}</h4>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-green-700">vendas.criar-pedido</span>
                                <span class="bg-green-100 text-green-700 px-1.5 rounded">button</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-green-700">vendas.ver-desconto</span>
                                <span class="bg-green-100 text-green-700 px-1.5 rounded">field</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-green-700">vendas.exportar</span>
                                <span class="bg-green-100 text-green-700 px-1.5 rounded">action</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Passo 3 --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 bg-slate-50 border-b border-slate-200">
                <span class="w-8 h-8 rounded-full bg-blue-700 text-white text-sm font-bold flex items-center justify-center shrink-0">3</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_s3_title') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.guide_s3_desc') }}</p>
                </div>
                <a href="{{ route('ptah.acl.roles') }}" class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-blue-700 hover:bg-blue-800 rounded-md transition-colors">
                    {{ __('ptah::ui.guide_s3_btn') }}
                </a>
            </div>
            <div class="px-5 py-4 space-y-4">
                <p class="text-sm text-slate-600 leading-relaxed">
                    {!! __('ptah::ui.guide_s3_body') !!}
                </p>
                <div class="bg-slate-50 border border-slate-200 rounded-md p-4">
                    <h4 class="text-xs font-bold text-slate-700 mb-3">{{ __('ptah::ui.guide_s3_ex_title') }}</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-200">
                                    <th class="px-3 py-2 text-left text-slate-600 font-semibold rounded-tl-lg">{{ __('ptah::ui.guide_s3_col_obj') }}</th>
                                    <th class="px-3 py-2 text-center text-slate-600 font-semibold">{{ __('ptah::ui.guide_s3_col_read') }}</th>
                                    <th class="px-3 py-2 text-center text-slate-600 font-semibold">{{ __('ptah::ui.guide_s3_col_create') }}</th>
                                    <th class="px-3 py-2 text-center text-slate-600 font-semibold">{{ __('ptah::ui.guide_s3_col_edit') }}</th>
                                    <th class="px-3 py-2 text-center text-slate-600 font-semibold rounded-tr-lg">{{ __('ptah::ui.guide_s3_col_delete') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ([
                                    ['vendas.criar-pedido',  true,  true,  true,  false],
                                    ['vendas.ver-desconto',  false, false, false, false],
                                    ['vendas.exportar',      true,  false, false, false],
                                ] as [$obj, $r, $c, $u, $d])
                                <tr class="bg-white">
                                    <td class="px-3 py-2 font-mono text-slate-600">{{ $obj }}</td>
                                    @foreach ([$r, $c, $u, $d] as $check)
                                    <td class="px-3 py-2 text-center">
                                        @if ($check)
                                            <span class="text-green-600 font-bold">✓</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">{{ __('ptah::ui.guide_s3_note') }}</p>
                </div>
            </div>
        </div>

        {{-- Passo 4 --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 bg-slate-50 border-b border-slate-200">
                <span class="w-8 h-8 rounded-full bg-blue-700 text-white text-sm font-bold flex items-center justify-center shrink-0">4</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_s4_title') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.guide_s4_desc') }}</p>
                </div>
                <a href="{{ route('ptah.acl.users') }}" class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-blue-700 hover:bg-blue-800 rounded-md transition-colors">
                    {{ __('ptah::ui.guide_s4_btn') }}
                </a>
            </div>
            <div class="px-5 py-4 space-y-3">
                <p class="text-sm text-slate-600 leading-relaxed">
                    {!! __('ptah::ui.guide_s4_body') !!}
                </p>
                <div class="bg-slate-50 border border-slate-200 rounded-md p-4 text-sm">
                    <h4 class="text-xs font-bold text-slate-700 mb-2">{!! __('ptah::ui.guide_s4_ex_title') !!}</h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
                            {!! __('ptah::ui.guide_s4_ex1') !!}
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                            {!! __('ptah::ui.guide_s4_ex2') !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Passo 5 --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 bg-slate-50 border-b border-slate-200">
                <span class="w-8 h-8 rounded-full bg-green-600 text-white text-sm font-bold flex items-center justify-center shrink-0">5</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">{{ __('ptah::ui.guide_s5_title') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('ptah::ui.guide_s5_desc') }}</p>
                </div>
                <button wire:click="$set('activeTab', 'code')"
                    class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors border border-blue-200">
                    {{ __('ptah::ui.guide_s5_btn') }}
                </button>
            </div>
            <div class="px-5 py-4">
                <p class="text-sm text-slate-600">
                    {!! __('ptah::ui.guide_s5_body') !!}
                </p>
            </div>
        </div>

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         ABA 3 — EXEMPLOS DE CÓDIGO
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'code')
    <div class="space-y-6">

        {{-- Helper Blade --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="px-5 py-3 bg-slate-800 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                <span class="w-3 h-3 rounded-full bg-green-400"></span>
                <span class="text-xs text-slate-400 ml-2">resources/views/vendas/index.blade.php — Helper ptah_can()</span>
            </div>
            <div class="p-5 bg-slate-900 overflow-x-auto">
                <pre class="text-sm leading-relaxed"><code><span class="text-slate-500">{{-- Verificar permissão de leitura --}}</span>
<span class="text-pink-400">@</span><span class="text-green-400">if</span><span class="text-slate-300"> (</span><span class="text-yellow-300">ptah_can</span><span class="text-slate-300">(</span><span class="text-amber-300">'vendas.exportar'</span><span class="text-slate-300">, </span><span class="text-amber-300">'read'</span><span class="text-slate-300">))</span>
    <span class="text-slate-300">&lt;</span><span class="text-blue-400">button</span><span class="text-slate-300">&gt;</span><span class="text-slate-300">Exportar CSV</span><span class="text-slate-300">&lt;/</span><span class="text-blue-400">button</span><span class="text-slate-300">&gt;</span>
<span class="text-pink-400">@</span><span class="text-green-400">endif</span>

<span class="text-slate-500">{{-- Verificar permissão de criação --}}</span>
<span class="text-pink-400">@</span><span class="text-green-400">if</span><span class="text-slate-300"> (</span><span class="text-yellow-300">ptah_can</span><span class="text-slate-300">(</span><span class="text-amber-300">'vendas.criar-pedido'</span><span class="text-slate-300">, </span><span class="text-amber-300">'create'</span><span class="text-slate-300">))</span>
    <span class="text-slate-300">&lt;</span><span class="text-blue-400">button</span><span class="text-slate-300"> </span><span class="text-purple-400">wire:click</span><span class="text-slate-300">=</span><span class="text-amber-300">"novoPedido"</span><span class="text-slate-300">&gt;</span><span class="text-slate-300">+ Novo Pedido</span><span class="text-slate-300">&lt;/</span><span class="text-blue-400">button</span><span class="text-slate-300">&gt;</span>
<span class="text-pink-400">@</span><span class="text-green-400">endif</span>

<span class="text-slate-500">{{-- Verificar com escopo de empresa --}}</span>
<span class="text-pink-400">@</span><span class="text-green-400">if</span><span class="text-slate-300"> (</span><span class="text-yellow-300">ptah_can</span><span class="text-slate-300">(</span><span class="text-amber-300">'vendas.ver-desconto'</span><span class="text-slate-300">, </span><span class="text-amber-300">'read'</span><span class="text-slate-300">, </span><span class="text-yellow-300">companyId</span><span class="text-slate-300">: </span><span class="text-blue-400">$empresa</span><span class="text-slate-300">-></span><span class="text-slate-300">id))</span>
    <span class="text-slate-300">&lt;</span><span class="text-blue-400">span</span><span class="text-slate-300">&gt;</span><span class="text-slate-300">Desconto: @{{ $pedido->desconto }}%</span><span class="text-slate-300">&lt;/</span><span class="text-blue-400">span</span><span class="text-slate-300">&gt;</span>
<span class="text-pink-400">@</span><span class="text-green-400">endif</span>

<span class="text-slate-500">{{-- Assinaturas completas do helper --}}</span>
<span class="text-slate-500">{{-- ptah_can(string $key, string $action = 'read', ?int $userId = null, ?int $companyId = null): bool --}}</span></code></pre>
            </div>
        </div>

        {{-- Middleware em rotas --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="px-5 py-3 bg-slate-800 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                <span class="w-3 h-3 rounded-full bg-green-400"></span>
                <span class="text-xs text-slate-400 ml-2">routes/web.php — Middleware ptah.can</span>
            </div>
            <div class="p-5 bg-slate-900 overflow-x-auto">
                <pre class="text-sm leading-relaxed"><code><span class="text-slate-500">// Proteger rota individual — verifica can_read</span>
<span class="text-blue-400">Route</span><span class="text-slate-300">::</span><span class="text-yellow-300">get</span><span class="text-slate-300">(</span><span class="text-amber-300">'/vendas/exportar'</span><span class="text-slate-300">, [</span><span class="text-blue-400">VendasController</span><span class="text-slate-300">::</span><span class="text-yellow-300">class</span><span class="text-slate-300">, </span><span class="text-amber-300">'exportar'</span><span class="text-slate-300">])</span>
    <span class="text-slate-300">-></span><span class="text-yellow-300">middleware</span><span class="text-slate-300">(</span><span class="text-amber-300">'ptah.can:vendas.exportar,read'</span><span class="text-slate-300">);</span>

<span class="text-slate-500">// Proteger rota de criar — verifica can_create</span>
<span class="text-blue-400">Route</span><span class="text-slate-300">::</span><span class="text-yellow-300">post</span><span class="text-slate-300">(</span><span class="text-amber-300">'/vendas/pedidos'</span><span class="text-slate-300">, [</span><span class="text-blue-400">PedidoController</span><span class="text-slate-300">::</span><span class="text-yellow-300">class</span><span class="text-slate-300">, </span><span class="text-amber-300">'store'</span><span class="text-slate-300">])</span>
    <span class="text-slate-300">-></span><span class="text-yellow-300">middleware</span><span class="text-slate-300">(</span><span class="text-amber-300">'ptah.can:vendas.criar-pedido,create'</span><span class="text-slate-300">);</span>

<span class="text-slate-500">// Grupo de rotas protegidas</span>
<span class="text-blue-400">Route</span><span class="text-slate-300">::</span><span class="text-yellow-300">middleware</span><span class="text-slate-300">([</span><span class="text-amber-300">'auth'</span><span class="text-slate-300">, </span><span class="text-amber-300">'ptah.can:admin.usuarios,read'</span><span class="text-slate-300">])</span>
    <span class="text-slate-300">-></span><span class="text-yellow-300">group</span><span class="text-slate-300">(</span><span class="text-green-400">function</span><span class="text-slate-300"> () {</span>
        <span class="text-blue-400">Route</span><span class="text-slate-300">::</span><span class="text-yellow-300">resource</span><span class="text-slate-300">(</span><span class="text-amber-300">'usuarios'</span><span class="text-slate-300">, </span><span class="text-blue-400">UsuarioController</span><span class="text-slate-300">::</span><span class="text-yellow-300">class</span><span class="text-slate-300">);</span>
    <span class="text-slate-300">});</span>

<span class="text-slate-500">// Sintaxe: 'ptah.can:{obj_key},{action}'</span>
<span class="text-slate-500">// Actions: read | create | update | delete</span></code></pre>
            </div>
        </div>

        {{-- PHP direto --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="px-5 py-3 bg-slate-800 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                <span class="w-3 h-3 rounded-full bg-green-400"></span>
                <span class="text-xs text-slate-400 ml-2">app/Http/Controllers/PedidoController.php — PermissionService</span>
            </div>
            <div class="p-5 bg-slate-900 overflow-x-auto">
                <pre class="text-sm leading-relaxed"><code><span class="text-slate-500">// Usando o serviço diretamente via injeção de dependência</span>
<span class="text-green-400">use</span><span class="text-slate-300"> </span><span class="text-blue-400">Ptah\Contracts\PermissionServiceContract</span><span class="text-slate-300">;</span>

<span class="text-green-400">class</span><span class="text-slate-300"> </span><span class="text-blue-400">PedidoController</span><span class="text-slate-300"> </span><span class="text-green-400">extends</span><span class="text-slate-300"> </span><span class="text-blue-400">Controller</span>
<span class="text-slate-300">{</span>
    <span class="text-green-400">public function</span><span class="text-slate-300"> </span><span class="text-yellow-300">store</span><span class="text-slate-300">(</span><span class="text-blue-400">Request</span><span class="text-slate-300"> </span><span class="text-blue-400">$request</span><span class="text-slate-300">, </span><span class="text-blue-400">PermissionServiceContract</span><span class="text-slate-300"> </span><span class="text-blue-400">$permissions</span><span class="text-slate-300">)</span>
    <span class="text-slate-300">{</span>
        <span class="text-slate-500">// Verificação manual</span>
        <span class="text-green-400">if</span><span class="text-slate-300"> (! </span><span class="text-blue-400">$permissions</span><span class="text-slate-300">-></span><span class="text-yellow-300">can</span><span class="text-slate-300">(</span>
            <span class="text-yellow-300">userId:</span><span class="text-slate-300"> </span><span class="text-yellow-300">auth</span><span class="text-slate-300">()-></span><span class="text-yellow-300">id</span><span class="text-slate-300">(),</span>
            <span class="text-yellow-300">key:</span><span class="text-slate-300"> </span><span class="text-amber-300">'vendas.criar-pedido'</span><span class="text-slate-300">,</span>
            <span class="text-yellow-300">action:</span><span class="text-slate-300"> </span><span class="text-amber-300">'create'</span><span class="text-slate-300">,</span>
        <span class="text-slate-300">)) {</span>
            <span class="text-green-400">abort</span><span class="text-slate-300">(</span><span class="text-blue-300">403</span><span class="text-slate-300">, </span><span class="text-amber-300">'Sem permissão para criar pedidos'</span><span class="text-slate-300">);</span>
        <span class="text-slate-300">}</span>

        <span class="text-slate-500">// ... criar pedido</span>
    <span class="text-slate-300">}</span>
<span class="text-slate-300">}</span></code></pre>
            </div>
        </div>

        {{-- Livewire --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="px-5 py-3 bg-slate-800 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                <span class="w-3 h-3 rounded-full bg-green-400"></span>
                <span class="text-xs text-slate-400 ml-2">app/Livewire/Vendas/PedidoList.php — Livewire Component</span>
            </div>
            <div class="p-5 bg-slate-900 overflow-x-auto">
                <pre class="text-sm leading-relaxed"><code><span class="text-green-400">use</span><span class="text-slate-300"> </span><span class="text-blue-400">Ptah\Traits\HasPermission</span><span class="text-slate-300">;</span>

<span class="text-green-400">class</span><span class="text-slate-300"> </span><span class="text-blue-400">PedidoList</span><span class="text-slate-300"> </span><span class="text-green-400">extends</span><span class="text-slate-300"> </span><span class="text-blue-400">Component</span>
<span class="text-slate-300">{</span>
    <span class="text-green-400">use</span><span class="text-slate-300"> </span><span class="text-blue-400">HasPermission</span><span class="text-slate-300">;</span>

    <span class="text-green-400">public function</span><span class="text-slate-300"> </span><span class="text-yellow-300">deletar</span><span class="text-slate-300">(</span><span class="text-green-400">int</span><span class="text-slate-300"> </span><span class="text-blue-400">$id</span><span class="text-slate-300">): </span><span class="text-green-400">void</span>
    <span class="text-slate-300">{</span>
        <span class="text-slate-500">// Trait helper — lança 403 automaticamente se sem permissão</span>
        <span class="text-blue-400">$this</span><span class="text-slate-300">-></span><span class="text-yellow-300">requirePermission</span><span class="text-slate-300">(</span><span class="text-amber-300">'vendas.criar-pedido'</span><span class="text-slate-300">, </span><span class="text-amber-300">'delete'</span><span class="text-slate-300">);</span>

        <span class="text-blue-400">Pedido</span><span class="text-slate-300">::</span><span class="text-yellow-300">destroy</span><span class="text-slate-300">(</span><span class="text-blue-400">$id</span><span class="text-slate-300">);</span>
    <span class="text-slate-300">}</span>

    <span class="text-green-400">public function</span><span class="text-slate-300"> </span><span class="text-yellow-300">render</span><span class="text-slate-300">(): </span><span class="text-blue-400">View</span>
    <span class="text-slate-300">{</span>
        <span class="text-green-400">return</span><span class="text-slate-300"> </span><span class="text-yellow-300">view</span><span class="text-slate-300">(</span><span class="text-amber-300">'livewire.vendas.pedido-list'</span><span class="text-slate-300">, [</span>
            <span class="text-slate-500">// No Blade: &#64;if(ptah_can('vendas.exportar', 'read'))</span>
            <span class="text-amber-300">'podeExportar'</span><span class="text-slate-300"> => </span><span class="text-yellow-300">ptah_can</span><span class="text-slate-300">(</span><span class="text-amber-300">'vendas.exportar'</span><span class="text-slate-300">, </span><span class="text-amber-300">'read'</span><span class="text-slate-300">),</span>
        <span class="text-slate-300">]);</span>
    <span class="text-slate-300">}</span>
<span class="text-slate-300">}</span></code></pre>
            </div>
        </div>

        {{-- .env --}}
        <div class="border border-slate-200 rounded-md overflow-hidden">
            <div class="px-5 py-3 bg-slate-800 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                <span class="w-3 h-3 rounded-full bg-green-400"></span>
                <span class="text-xs text-slate-400 ml-2">.env — Configurações do módulo Ptah</span>
            </div>
            <div class="p-5 bg-slate-900 overflow-x-auto">
                <pre class="text-sm leading-relaxed"><code><span class="text-slate-500"># Habilitar os módulos do Ptah</span>
<span class="text-green-400">PTAH_MODULE_AUTH</span><span class="text-slate-300">=</span><span class="text-amber-300">true</span>
<span class="text-green-400">PTAH_MODULE_COMPANY</span><span class="text-slate-300">=</span><span class="text-amber-300">true</span>
<span class="text-green-400">PTAH_MODULE_PERMISSIONS</span><span class="text-slate-300">=</span><span class="text-amber-300">true</span>

<span class="text-slate-500"># Habilitar log de auditoria de permissões</span>
<span class="text-green-400">PTAH_PERMISSION_AUDIT</span><span class="text-slate-300">=</span><span class="text-amber-300">true</span>

<span class="text-slate-500"># Número máximo de registros de auditoria (0 = sem limite)</span>
<span class="text-green-400">PTAH_AUDIT_MAX_RECORDS</span><span class="text-slate-300">=</span><span class="text-blue-300">10000</span></code></pre>
            </div>
        </div>

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         ABA 4 — FAQ
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'faq')
    <div class="space-y-4">

        @foreach ([
            [
                'q' => 'O que acontece se o usuário não tiver nenhum Role?',
                'a' => 'Sem nenhum Role, o usuário não terá acesso a nenhum objeto controlado. As verificações com <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah_can()</code> retornam <strong>false</strong> e o middleware <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah.can</code> retorna HTTP 403.',
            ],
            [
                'q' => 'Posso ter mais de um Role por usuário?',
                'a' => 'Sim! Um usuário pode ter múltiplos Roles, inclusive em empresas diferentes. Se qualquer um dos Roles do usuário tiver a permissão solicitada, o acesso é concedido.',
            ],
            [
                'q' => 'O que é o Role MASTER e quando usar?',
                'a' => 'Um Role MASTER bypassa <strong>todas</strong> as verificações de permissão, concedendo acesso irrestrito. Use exclusivamente para superadministradores do sistema. Só pode existir 1 Role MASTER configurado.',
            ],
            [
                'q' => 'Como funciona o escopo por empresa?',
                'a' => 'Ao vincular um usuário a um Role, você pode especificar uma Empresa. A verificação considera apenas os Roles válidos para a empresa atual do contexto. Vínculos com empresa <code class="font-mono text-xs bg-slate-100 px-1 rounded">NULL</code> são válidos globalmente.',
            ],
            [
                'q' => 'As permissões são cacheadas?',
                'a' => 'Sim. O Ptah usa o cache do Laravel para evitar queries excessivas. O cache é invalidado automaticamente quando os vínculos de um usuário são alterados via interface. Você pode limpar com <code class="font-mono text-xs bg-slate-100 px-1 rounded">php artisan cache:clear</code>.',
            ],
            [
                'q' => 'Posso criar Páginas e Objetos automaticamente via código?',
                'a' => 'Sim. Use o seeder ou crie registros em <code class="font-mono text-xs bg-slate-100 px-1 rounded">Ptah\Models\Page</code> e <code class="font-mono text-xs bg-slate-100 px-1 rounded">Ptah\Models\PageObject</code> diretamente. É útil para popular via migration ao fazer deploy.',
            ],
            [
                'q' => 'O que acontece se eu excluir um Objeto que já tem permissões definidas?',
                'a' => 'As entradas da tabela de permissões associadas ao objeto são removidas em cascata. Os Roles que tinham aquele objeto perdem a permissão automaticamente. Usuários MASTER não são afetados (bypass).',
            ],
            [
                'q' => 'Como auditar quem acessou o que?',
                'a' => 'Habilite <code class="font-mono text-xs bg-slate-100 px-1 rounded">PTAH_PERMISSION_AUDIT=true</code> no .env. Cada verificação (concedida ou negada) será registrada na tabela <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah_permission_audits</code>. Acesse o log em <a href="' . route('ptah.acl.audit') . '" class="text-blue-600 underline">Auditoria</a>.',
            ],
        ] as $item)
        <div x-data="{ open: false }" class="border border-slate-200 rounded-md overflow-hidden">
            <button
                @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-slate-50 transition-colors"
                :class="open ? 'bg-slate-50' : ''"
            >
                <span class="text-sm font-semibold text-slate-800">{{ $item['q'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform shrink-0 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="px-5 pb-4 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3"
                style="display:none"
            >
                {!! $item['a'] !!}
            </div>
        </div>
        @endforeach

        {{-- Precisa de mais ajuda? --}}
        <div class="bg-gradient-to-r from-blue-50 to-slate-50 border border-blue-100 rounded-md p-5 flex items-center gap-4">
            <div class="text-4xl">🙋</div>
            <div>
                <h3 class="text-sm font-bold text-blue-900 mb-1">{{ __('ptah::ui.guide_faq_help_title') }}</h3>
                <p class="text-xs text-blue-700">
                    {!! __('ptah::ui.guide_faq_help_body') !!}
                </p>
            </div>
        </div>

    </div>
    @endif

</div>


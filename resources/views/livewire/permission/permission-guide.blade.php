{{-- ptah::livewire.permission.permission-guide --}}
<div>
    <x-forge-page-header :title="__('ptah::ui.guide_title')" :subtitle="__('ptah::ui.guide_subtitle')">
        <span class="ptah-c-chip">{{ __('ptah::ui.guide_badge') }}</span>
    </x-forge-page-header>

    <x-forge-tabs>
        <x-slot name="tabs">
            @foreach ([
                ['key' => 'overview',  'label' => __('ptah::ui.guide_tab_overview')],
                ['key' => 'setup',     'label' => __('ptah::ui.guide_tab_setup')],
                ['key' => 'code',      'label' => __('ptah::ui.guide_tab_code')],
                ['key' => 'faq',       'label' => __('ptah::ui.guide_tab_faq')],
            ] as $tab)
                <x-forge-tab :key="$tab['key']" :active="$activeTab === $tab['key']" wire:click="$set('activeTab', '{{ $tab['key'] }}')">
                    {{ $tab['label'] }}
                </x-forge-tab>
            @endforeach
        </x-slot>

    {{-- ═══════════════════════════════════════════════════════════
         ABA 1 — VISÃO GERAL
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'overview')
    <div class="space-y-8">

        {{-- Intro --}}
        <x-forge-alert type="primary">
            <h2 class="font-bold mb-2">{{ __('ptah::ui.guide_ov_title') }}</h2>
            <p class="leading-relaxed">
                {!! __('ptah::ui.guide_ov_body') !!}
            </p>
        </x-forge-alert>

        {{-- Diagrama de arquitetura --}}
        <div>
            <h2 class="ptah-c-mod_hdg text-base font-bold mb-4 flex items-center gap-2">
                <span class="ptah-c-step_num w-6 h-6 rounded-full text-xs flex items-center justify-center font-bold">1</span>
                {{ __('ptah::ui.guide_ov_arch_title') }}
            </h2>
            <div class="overflow-x-auto">
                <div class="min-w-[700px] flex items-center justify-center gap-0 py-4">

                    {{-- Departamentos --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="ptah-c-guide_node w-36 border-2 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">🏢</div>
                            <p class="text-xs font-bold">{{ __('ptah::ui.guide_ov_dept_title') }}</p>
                            <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_ov_dept_desc') }}</p>
                        </div>
                        <p class="ptah-c-mod_subttl text-xs text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_dept_ex') }}</p>
                    </div>

                    <div class="ptah-c-guide_conn w-6 h-0.5 mx-2 shrink-0" aria-hidden="true"></div>

                    {{-- Roles --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="ptah-c-guide_node w-36 border-2 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">🎭</div>
                            <p class="text-xs font-bold">{{ __('ptah::ui.guide_ov_roles_title') }}</p>
                            <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_ov_roles_desc') }}</p>
                        </div>
                        <p class="ptah-c-mod_subttl text-xs text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_roles_ex') }}</p>
                    </div>

                    <div class="ptah-c-guide_conn w-6 h-0.5 mx-2 shrink-0" aria-hidden="true"></div>

                    {{-- Páginas/Objetos --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="ptah-c-guide_node w-40 border-2 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">📄</div>
                            <p class="text-xs font-bold">{{ __('ptah::ui.guide_ov_pages_title') }}</p>
                            <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_ov_pages_desc') }}</p>
                        </div>
                        <p class="ptah-c-mod_subttl text-xs text-center max-w-[140px]">{{ __('ptah::ui.guide_ov_pages_ex') }}</p>
                    </div>

                    <div class="ptah-c-guide_conn w-6 h-0.5 mx-2 shrink-0" aria-hidden="true"></div>

                    {{-- Usuários --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="ptah-c-guide_node w-36 border-2 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">👤</div>
                            <p class="text-xs font-bold">{{ __('ptah::ui.guide_ov_users_title') }}</p>
                            <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_ov_users_desc') }}</p>
                        </div>
                        <p class="ptah-c-mod_subttl text-xs text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_users_ex') }}</p>
                    </div>

                    <div class="ptah-c-guide_conn w-6 h-0.5 mx-2 shrink-0" aria-hidden="true"></div>

                    {{-- Empresas --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="ptah-c-guide_node w-36 border-2 rounded-md p-3 text-center">
                            <div class="text-2xl mb-1">🏭</div>
                            <p class="text-xs font-bold">{{ __('ptah::ui.guide_ov_co_title') }}</p>
                            <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_ov_co_desc') }}</p>
                        </div>
                        <p class="ptah-c-mod_subttl text-xs text-center max-w-[120px]">{{ __('ptah::ui.guide_ov_co_ex') }}</p>
                    </div>

                </div>
            </div>
        </div>

        {{-- Conceitos-chave em cards --}}
        <div>
            <h2 class="ptah-c-mod_hdg text-base font-bold mb-4 flex items-center gap-2">
                <span class="ptah-c-step_num w-6 h-6 rounded-full text-xs flex items-center justify-center font-bold">2</span>
                {{ __('ptah::ui.guide_ov_concepts_title') }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <x-forge-card>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🎭</span>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_con_role_title') }}</h3>
                    </div>
                    <p class="ptah-c-mod_subttl text-xs leading-relaxed">
                        {{ __('ptah::ui.guide_con_role_body') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span class="ptah-c-chip">Admin</span>
                        <span class="ptah-c-chip">Vendedor</span>
                        <span class="ptah-c-chip">👑 MASTER</span>
                    </div>
                </x-forge-card>

                <x-forge-card>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">📄</span>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_con_page_title') }}</h3>
                    </div>
                    <p class="ptah-c-mod_subttl text-xs leading-relaxed">
                        {!! __('ptah::ui.guide_con_page_body') !!}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span class="ptah-c-chip">admin.vendas</span>
                        <span class="ptah-c-chip">admin.estoque</span>
                    </div>
                </x-forge-card>

                <x-forge-card>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🔑</span>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_con_obj_title') }}</h3>
                    </div>
                    <p class="ptah-c-mod_subttl text-xs leading-relaxed">
                        {!! __('ptah::ui.guide_con_obj_body') !!}
                    </p>
                    <div class="mt-3 grid grid-cols-4 gap-1">
                        @foreach ([__('ptah::ui.guide_con_perms_read'), __('ptah::ui.guide_con_perms_create'), __('ptah::ui.guide_con_perms_edit'), __('ptah::ui.guide_con_perms_delete')] as $perm)
                        <div class="text-center">
                            <div class="ptah-c-guide_node_ok w-7 h-7 rounded-md border flex items-center justify-center mx-auto"><span class="text-xs font-bold">✓</span></div>
                            <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ $perm }}</p>
                        </div>
                        @endforeach
                    </div>
                </x-forge-card>

                <x-forge-card>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">👑</span>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_con_master_title') }}</h3>
                    </div>
                    <p class="ptah-c-mod_subttl text-xs leading-relaxed">
                        {!! __('ptah::ui.guide_con_master_body') !!}
                    </p>
                    <x-forge-alert type="warn" class="mt-3">
                        {{ __('ptah::ui.guide_con_master_warn') }}
                    </x-forge-alert>
                </x-forge-card>

                <x-forge-card>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🏭</span>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_con_scope_title') }}</h3>
                    </div>
                    <p class="ptah-c-mod_subttl text-xs leading-relaxed">
                        {!! __('ptah::ui.guide_con_scope_body') !!}
                    </p>
                </x-forge-card>

                <x-forge-card>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">📋</span>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_con_audit_title') }}</h3>
                    </div>
                    <p class="ptah-c-mod_subttl text-xs leading-relaxed">
                        {!! __('ptah::ui.guide_con_audit_body') !!}
                    </p>
                </x-forge-card>

            </div>
        </div>

        {{-- Fluxo de decisão --}}
        <div>
            <h2 class="ptah-c-mod_hdg text-base font-bold mb-4 flex items-center gap-2">
                <span class="ptah-c-step_num w-6 h-6 rounded-full text-xs flex items-center justify-center font-bold">3</span>
                {{ __('ptah::ui.guide_ov_flow_title') }}
            </h2>
            <div class="ptah-c-card rounded-md border p-5 space-y-2">

                <div class="flex items-center gap-3">
                    <span class="ptah-c-guide_node w-7 h-7 rounded-full border text-xs font-bold flex items-center justify-center shrink-0">1</span>
                    <div class="ptah-c-guide_node rounded-md border px-3 py-1.5 text-xs flex-1">{{ __('ptah::ui.guide_flow_q1') }}</div>
                    <span class="ptah-c-guide_node_no rounded-full border px-2 py-0.5 text-xs font-bold shrink-0">{{ __('ptah::ui.guide_flow_no') }}</span>
                </div>
                <div class="ptah-c-guide_conn w-0.5 h-3 ml-3.5" aria-hidden="true"></div>

                <div class="flex items-center gap-3">
                    <span class="ptah-c-guide_node w-7 h-7 rounded-full border text-xs font-bold flex items-center justify-center shrink-0">2</span>
                    <div class="ptah-c-guide_node rounded-md border px-3 py-1.5 text-xs flex-1">{{ __('ptah::ui.guide_flow_q2') }}</div>
                    <span class="ptah-c-guide_node_no rounded-full border px-2 py-0.5 text-xs font-bold shrink-0">{{ __('ptah::ui.guide_flow_no') }}</span>
                </div>
                <div class="ptah-c-guide_conn w-0.5 h-3 ml-3.5" aria-hidden="true"></div>

                <div class="flex items-center gap-3">
                    <span class="ptah-c-guide_node w-7 h-7 rounded-full border text-xs font-bold flex items-center justify-center shrink-0">3</span>
                    <div class="ptah-c-guide_node rounded-md border px-3 py-1.5 text-xs flex-1">{{ __('ptah::ui.guide_flow_q3') }}</div>
                    <span class="ptah-c-guide_node_ok rounded-full border px-2 py-0.5 text-xs font-bold shrink-0">{{ __('ptah::ui.guide_flow_granted') }}</span>
                </div>
                <div class="ptah-c-guide_conn w-0.5 h-3 ml-3.5" aria-hidden="true"></div>

                <div class="flex items-center gap-3">
                    <span class="ptah-c-guide_node w-7 h-7 rounded-full border text-xs font-bold flex items-center justify-center shrink-0">4</span>
                    <div class="ptah-c-guide_node rounded-md border px-3 py-1.5 text-xs flex-1">{{ __('ptah::ui.guide_flow_q4') }}</div>
                </div>
                <div class="ptah-c-guide_conn w-0.5 h-3 ml-3.5" aria-hidden="true"></div>

                <div class="flex items-center gap-3">
                    <span class="ptah-c-guide_node w-7 h-7 rounded-full border text-xs font-bold flex items-center justify-center shrink-0">5</span>
                    <div class="ptah-c-guide_node rounded-md border px-3 py-1.5 text-xs flex-1">{{ __('ptah::ui.guide_flow_q5') }}</div>
                </div>
                <div class="ptah-c-guide_conn w-0.5 h-3 ml-3.5" aria-hidden="true"></div>

                <div class="flex items-center gap-3">
                    <span class="ptah-c-guide_node w-7 h-7 rounded-full border text-xs font-bold flex items-center justify-center shrink-0">6</span>
                    <div class="ptah-c-guide_node rounded-md border px-3 py-1.5 text-xs flex-1">{{ __('ptah::ui.guide_flow_q6') }}</div>
                </div>
                <div class="ptah-c-guide_conn w-0.5 h-3 ml-3.5" aria-hidden="true"></div>

                <div class="flex items-center gap-3 pl-10">
                    <span class="ptah-c-guide_node_ok rounded-md border px-3 py-1.5 text-xs font-bold">{{ __('ptah::ui.guide_flow_granted') }}</span>
                    <span class="ptah-c-guide_node_no rounded-md border px-3 py-1.5 text-xs font-bold">{{ __('ptah::ui.guide_flow_denied') }}</span>
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
        <x-forge-card>
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <span class="ptah-c-step_num w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center shrink-0">1</span>
                    <div>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{!! __('ptah::ui.guide_s1_title') !!}</h3>
                        <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_s1_desc') }}</p>
                    </div>
                    <a href="{{ route('ptah.acl.departments') }}" class="ptah-c-btn ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md">
                        {{ __('ptah::ui.guide_s1_btn') }}
                    </a>
                </div>
            </x-slot>

            <p class="text-sm leading-relaxed">{{ __('ptah::ui.guide_s1_body') }}</p>
            <div class="ptah-c-card rounded-md border p-4 mt-3 text-sm">
                <strong>{{ __('ptah::ui.guide_s1_example') }}:</strong>
                <ul class="mt-2 space-y-1 list-disc list-inside">
                    <li>{!! __('ptah::ui.guide_s1_ex_it') !!}</li>
                    <li>{!! __('ptah::ui.guide_s1_ex_sales') !!}</li>
                    <li>{!! __('ptah::ui.guide_s1_ex_fin') !!}</li>
                </ul>
            </div>
        </x-forge-card>

        {{-- Passo 2 --}}
        <x-forge-card>
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <span class="ptah-c-step_num w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_s2_title') }}</h3>
                        <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_s2_desc') }}</p>
                    </div>
                    <a href="{{ route('ptah.acl.pages') }}" class="ptah-c-btn ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md">
                        {{ __('ptah::ui.guide_s2_btn') }}
                    </a>
                </div>
            </x-slot>

            <p class="text-sm leading-relaxed">{!! __('ptah::ui.guide_s2_body') !!}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div class="ptah-c-card rounded-md border p-4">
                    <h4 class="ptah-c-mod_hdg text-xs font-bold mb-2">{{ __('ptah::ui.guide_s2_page_title') }}</h4>
                    <table class="w-full text-xs">
                        <tr><td class="py-1 ptah-c-mod_subttl font-medium w-24">{{ __('ptah::ui.guide_s2_page_slug') }}</td><td class="py-1 font-mono">admin.vendas</td></tr>
                        <tr><td class="py-1 ptah-c-mod_subttl font-medium">{{ __('ptah::ui.guide_s2_page_name') }}</td><td class="py-1">Módulo de Vendas</td></tr>
                        <tr><td class="py-1 ptah-c-mod_subttl font-medium">{{ __('ptah::ui.guide_s2_page_icon') }}</td><td class="py-1">🛒</td></tr>
                    </table>
                </div>
                <div class="ptah-c-card rounded-md border p-4">
                    <h4 class="ptah-c-mod_hdg text-xs font-bold mb-2">{{ __('ptah::ui.guide_s2_obj_title') }}</h4>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono">vendas.criar-pedido</span>
                            <span class="ptah-c-chip">button</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono">vendas.ver-desconto</span>
                            <span class="ptah-c-chip">field</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono">vendas.exportar</span>
                            <span class="ptah-c-chip">action</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-forge-card>

        {{-- Passo 3 --}}
        <x-forge-card>
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <span class="ptah-c-step_num w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_s3_title') }}</h3>
                        <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_s3_desc') }}</p>
                    </div>
                    <a href="{{ route('ptah.acl.roles') }}" class="ptah-c-btn ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md">
                        {{ __('ptah::ui.guide_s3_btn') }}
                    </a>
                </div>
            </x-slot>

            <p class="text-sm leading-relaxed">{!! __('ptah::ui.guide_s3_body') !!}</p>
            <h4 class="ptah-c-mod_hdg text-xs font-bold mt-4 mb-3">{{ __('ptah::ui.guide_s3_ex_title') }}</h4>
            <div class="ptah-module-table overflow-x-auto border rounded-md">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="ptah-c-tbl_head_row">
                            <th class="px-3 py-2 text-left ptah-c-th_text font-semibold">{{ __('ptah::ui.guide_s3_col_obj') }}</th>
                            <th class="px-3 py-2 text-center ptah-c-th_text font-semibold">{{ __('ptah::ui.guide_s3_col_read') }}</th>
                            <th class="px-3 py-2 text-center ptah-c-th_text font-semibold">{{ __('ptah::ui.guide_s3_col_create') }}</th>
                            <th class="px-3 py-2 text-center ptah-c-th_text font-semibold">{{ __('ptah::ui.guide_s3_col_edit') }}</th>
                            <th class="px-3 py-2 text-center ptah-c-th_text font-semibold">{{ __('ptah::ui.guide_s3_col_delete') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ([
                            ['vendas.criar-pedido',  true,  true,  true,  false],
                            ['vendas.ver-desconto',  false, false, false, false],
                            ['vendas.exportar',      true,  false, false, false],
                        ] as [$obj, $r, $c, $u, $d])
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $obj }}</td>
                            @foreach ([$r, $c, $u, $d] as $check)
                            <td class="px-3 py-2 text-center">
                                @if ($check)
                                    <span class="ptah-c-guide_node_ok rounded px-1 font-bold">✓</span>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ptah-c-mod_subttl text-xs mt-2">{{ __('ptah::ui.guide_s3_note') }}</p>
        </x-forge-card>

        {{-- Passo 4 --}}
        <x-forge-card>
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <span class="ptah-c-step_num w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center shrink-0">4</span>
                    <div>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_s4_title') }}</h3>
                        <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_s4_desc') }}</p>
                    </div>
                    <a href="{{ route('ptah.acl.users') }}" class="ptah-c-btn ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md">
                        {{ __('ptah::ui.guide_s4_btn') }}
                    </a>
                </div>
            </x-slot>

            <p class="text-sm leading-relaxed">{!! __('ptah::ui.guide_s4_body') !!}</p>
            <div class="ptah-c-card rounded-md border p-4 mt-3 text-sm">
                <h4 class="ptah-c-mod_hdg text-xs font-bold mb-2">{!! __('ptah::ui.guide_s4_ex_title') !!}</h4>
                <div class="space-y-2 text-xs">
                    <div>{!! __('ptah::ui.guide_s4_ex1') !!}</div>
                    <div>{!! __('ptah::ui.guide_s4_ex2') !!}</div>
                </div>
            </div>
        </x-forge-card>

        {{-- Passo 5 --}}
        <x-forge-card>
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <span class="ptah-c-step_num w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center shrink-0">5</span>
                    <div>
                        <h3 class="ptah-c-mod_hdg text-sm font-bold">{{ __('ptah::ui.guide_s5_title') }}</h3>
                        <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_s5_desc') }}</p>
                    </div>
                    <button wire:click="$set('activeTab', 'code')" class="ptah-c-btn ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md">
                        {{ __('ptah::ui.guide_s5_btn') }}
                    </button>
                </div>
            </x-slot>

            <p class="text-sm">{!! __('ptah::ui.guide_s5_body') !!}</p>
            <p class="text-sm mt-3">{!! __('ptah::ui.guide_s5_permid_note') !!}</p>
        </x-forge-card>

        {{-- Permissão por coluna (colsPermission, opcional — não é um passo numerado) --}}
        <x-forge-card>
            <x-slot name="header">
                <h3 class="ptah-c-mod_hdg text-sm font-bold">🔒 {{ __('ptah::ui.guide_s_col_title') }}</h3>
                <p class="ptah-c-mod_subttl text-xs mt-0.5">{{ __('ptah::ui.guide_s_col_desc') }}</p>
            </x-slot>

            <p class="text-sm leading-relaxed">{!! __('ptah::ui.guide_s_col_body') !!}</p>
            <x-forge-alert type="warn" class="mt-3">
                {!! __('ptah::ui.guide_s_col_warn') !!}
            </x-forge-alert>
        </x-forge-card>

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         ABA 3 — EXEMPLOS DE CÓDIGO
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'code')
    <div class="space-y-6">

        {{-- Helper Blade --}}
        <div class="ptah-c-code rounded-md overflow-hidden border">
            <div class="ptah-c-code_cap px-5 py-2 text-xs font-mono">resources/views/vendas/index.blade.php — Helper ptah_can()</div>
            <pre class="p-5 overflow-x-auto text-sm leading-relaxed"><code>// Verificar permissão de leitura
&#64;if (ptah_can('vendas.exportar', 'read'))
    &lt;button&gt;Exportar CSV&lt;/button&gt;
&#64;endif

// Verificar permissão de criação
&#64;if (ptah_can('vendas.criar-pedido', 'create'))
    &lt;button wire:click="novoPedido"&gt;+ Novo Pedido&lt;/button&gt;
&#64;endif

// Verificar com escopo de empresa explícito
&#64;if (ptah_can('vendas.ver-desconto', 'read', companyId: $empresa->id))
    &lt;span&gt;Desconto: @{{ $pedido->desconto }}%&lt;/span&gt;
&#64;endif

// Chave QUALIFICADA — use "pagina::obj_key" quando o mesmo obj_key existe em
// mais de uma Página (PermissionService::KEY_QUALIFIER = '::'). Sem isso o
// obj_key duplicado só resolve pelo mapa BARE, que é global — não por página.
&#64;if (ptah_can('vendas::exportar', 'read'))
    &lt;button&gt;Exportar (só a Página "vendas")&lt;/button&gt;
&#64;endif

// Assinatura completa:
// ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool</code></pre>
        </div>

        {{-- Middleware em rotas --}}
        <div class="ptah-c-code rounded-md overflow-hidden border">
            <div class="ptah-c-code_cap px-5 py-2 text-xs font-mono">routes/web.php — Middleware ptah.can</div>
            <pre class="p-5 overflow-x-auto text-sm leading-relaxed"><code>// Proteger rota individual — verifica can_read
Route::get('/vendas/exportar', [VendasController::class, 'exportar'])
    -&gt;middleware('ptah.can:vendas.exportar,read');

// Proteger rota de criar — verifica can_create
Route::post('/vendas/pedidos', [PedidoController::class, 'store'])
    -&gt;middleware('ptah.can:vendas.criar-pedido,create');

// 3º parâmetro OPCIONAL: companyId explícito (sem ele, usa sessão/auth)
Route::get('/relatorios/vendas', [RelatorioController::class, 'index'])
    -&gt;middleware('ptah.can:relatorios.vendas,read,1');

// action é OPCIONAL — se omitida, o middleware assume 'read'
Route::middleware(['auth', 'ptah.can:admin.usuarios'])
    -&gt;group(function () {
        Route::resource('usuarios', UsuarioController::class);
    });

// Chave qualificada também funciona aqui — o '::' nunca colide com o
// parsing ':'/',' dos parâmetros do middleware
Route::get('/financeiro/exportar', [FinanceiroController::class, 'exportar'])
    -&gt;middleware('ptah.can:financeiro::toolbar::exportar,read');

// Sintaxe: 'ptah.can:{obj_key},{action?},{companyId?}'
// Actions: read | create | update | delete (default: read)</code></pre>
        </div>

        {{-- PHP direto --}}
        <div class="ptah-c-code rounded-md overflow-hidden border">
            <div class="ptah-c-code_cap px-5 py-2 text-xs font-mono">app/Http/Controllers/PedidoController.php — PermissionServiceContract</div>
            <pre class="p-5 overflow-x-auto text-sm leading-relaxed"><code>// Usando o contrato diretamente via injeção de dependência
use Ptah\Contracts\PermissionServiceContract;

class PedidoController extends Controller
{
    public function store(Request $request, PermissionServiceContract $permissions)
    {
        // check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
        // — NÃO é can(userId:, key:, action:); esse método não existe no contrato.
        if (! $permissions-&gt;check(auth()-&gt;user(), 'vendas.criar-pedido', 'create')) {
            abort(403, 'Sem permissão para criar pedidos');
        }

        // ... criar pedido
    }
}

// Ou pela facade, fora de um construtor:
use Ptah\Facades\Permission;

Permission::check(auth()-&gt;user(), 'vendas.criar-pedido', 'create');</code></pre>
        </div>

        {{-- Livewire --}}
        <div class="ptah-c-code rounded-md overflow-hidden border">
            <div class="ptah-c-code_cap px-5 py-2 text-xs font-mono">app/Livewire/Vendas/PedidoList.php — Componente Livewire (fora do BaseCrud)</div>
            <pre class="p-5 overflow-x-auto text-sm leading-relaxed"><code>class PedidoList extends Component
{
    public function deletar(int $id): void
    {
        // Sem trait dedicado no pacote — verifique com o helper e aborte
        // manualmente.
        if (! ptah_can('vendas.criar-pedido', 'delete')) {
            abort(403);
        }

        Pedido::destroy($id);
    }

    public function render(): View
    {
        return view('livewire.vendas.pedido-list', [
            // No Blade: &#64;if (ptah_can('vendas.exportar', 'read'))
            'podeExportar' => ptah_can('vendas.exportar', 'read'),
        ]);
    }
}

// Isto NÃO é um BaseCrud (Ptah\Livewire\BaseCrud\BaseCrud): lá, basta
// configurar 'permissions.permissionIdentifier' no CrudConfig — o próprio
// componente já gate create/update/delete e também 'read': render() aborta
// 403 antes de a listagem consultar o banco. Sem permissionIdentifier
// configurado, o CRUD fica livre (grants não são consultados).</code></pre>
        </div>

        {{-- .env --}}
        <div class="ptah-c-code rounded-md overflow-hidden border">
            <div class="ptah-c-code_cap px-5 py-2 text-xs font-mono">.env — Configurações do módulo Ptah</div>
            <pre class="p-5 overflow-x-auto text-sm leading-relaxed"><code># Habilitar os módulos do Ptah
PTAH_MODULE_AUTH=true
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true

# Auditoria de permissões — não existe "número máximo de registros": a
# tabela ptah_permission_audits cresce sem teto até você agendar a poda.
PTAH_PERMISSION_AUDIT=true                  # grava acessos CONCEDIDOS (default: false)
PTAH_PERMISSION_AUDIT_DENIED=true           # também grava NEGADOS (default: true)
PTAH_PERMISSION_AUDIT_MASTER=false          # grava acessos de MASTER (default: false)
PTAH_PERMISSION_AUDIT_RETENTION_DAYS=90     # janela usada por "ptah:audit-prune"

# Cache do mapa de permissões (recomendado ligado)
PTAH_PERMISSION_CACHE=true
PTAH_PERMISSION_CACHE_TTL=3600

# Agende a poda (comando DESTRUTIVO — revise antes de rodar em produção):
#   php artisan ptah:audit-prune --days=90</code></pre>
        </div>

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         ABA 4 — FAQ
    ══════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'faq')
    <div class="space-y-4">

        @foreach (range(1, 10) as $i)
        <div x-data="{ open: false }" class="ptah-c-card rounded-md border overflow-hidden">
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                class="ptah-c-acc_hd w-full flex items-center justify-between px-5 py-4 text-left transition-colors"
            >
                <span class="text-sm font-semibold">{{ __('ptah::ui.guide_faq_q'.$i) }}</span>
                <svg class="ptah-c-acc_chevron w-4 h-4 shrink-0 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="px-5 pb-4 text-sm leading-relaxed pt-1"
            >
                {!! $i === 8 ? __('ptah::ui.guide_faq_a8', ['audit_url' => route('ptah.acl.audit')]) : __('ptah::ui.guide_faq_a'.$i) !!}
            </div>
        </div>
        @endforeach

        {{-- Precisa de mais ajuda? --}}
        <x-forge-alert type="primary">
            <div class="flex items-center gap-4">
                <div class="text-4xl">🙋</div>
                <div>
                    <h3 class="font-bold mb-1">{{ __('ptah::ui.guide_faq_help_title') }}</h3>
                    <p>
                        {!! __('ptah::ui.guide_faq_help_body') !!}
                    </p>
                </div>
            </div>
        </x-forge-alert>

    </div>
    @endif

    </x-forge-tabs>
</div>


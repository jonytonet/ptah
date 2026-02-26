<div>
    {{-- ── Botão de abertura ────────────────────────────────────────────────── --}}
    {{-- @can('admin') --}}
        <button wire:click="openModal"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-500 rounded-lg bg-transparent hover:bg-slate-100 hover:text-slate-700 transition-all duration-150 focus:outline-none"
            title="Configurar CRUD">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="hidden md:inline">Config</span>
        </button>
    {{-- @endcan --}}

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── Modal Enterprise ─────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if ($showModal)
        @teleport('body')
        <div
            x-data="crudConfigApp(@js($formEditFields), @js($customFilters), @js($conditionStyles))"
            x-init="init()"
            class="fixed inset-0 z-[9999] flex items-center justify-center"
            @keydown.escape.window="$wire.closeModal()"
        >
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeModal"></div>

            {{-- Shell --}}
            <div class="relative flex w-full mx-4 overflow-hidden bg-white shadow-2xl max-w-7xl rounded-2xl"
                 style="height: 90vh; max-height: 900px;">

                {{-- ── Sidebar ──────────────────────────────────────────────── --}}
                <aside class="flex flex-col text-white w-60 shrink-0 bg-slate-900">
                    {{-- Header --}}
                    <div class="px-5 pt-6 pb-5 border-b border-slate-700/60">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="text-xs font-semibold tracking-widest uppercase text-slate-300">CRUD Config</span>
                        </div>
                        <p class="text-[11px] text-slate-500 font-mono truncate">{{ $model }}</p>
                    </div>

                    {{-- Nav items --}}
                    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
                        @php
                            $navItems = [
                                ['id' => 'cols',        'icon' => 'M3 10h18M3 6h18M3 14h18M3 18h18',       'label' => 'Colunas',           'count' => count(array_filter($formEditFields, fn($c) => ($c['colsTipo'] ?? '') !== 'action'))],
                                ['id' => 'actions',     'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',              'label' => 'Ações',             'count' => count(array_filter($formEditFields, fn($c) => ($c['colsTipo'] ?? '') === 'action'))],
                                ['id' => 'filters',     'icon' => 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z', 'label' => 'Filtros Custom',    'count' => count($customFilters)],
                                ['id' => 'styles',      'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01', 'label' => 'Estilos', 'count' => count($conditionStyles)],
                                ['id' => 'general',     'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4', 'label' => 'Geral',  'count' => null],
                                ['id' => 'permissions', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'label' => 'Permissões', 'count' => null],
                            ];
                        @endphp

                        @foreach ($navItems as $nav)
                            <button
                                @click="tab = '{{ $nav['id'] }}'"
                                :class="tab === '{{ $nav['id'] }}' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100'"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
                            >
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $nav['icon'] }}"/>
                                </svg>
                                <span class="flex-1 text-left text-[13px]">{{ $nav['label'] }}</span>
                                @if ($nav['count'] !== null)
                                    <span :class="tab === '{{ $nav['id'] }}' ? 'bg-indigo-500 text-white' : 'bg-slate-700 text-slate-300'"
                                          class="text-[10px] font-bold px-1.5 py-0.5 rounded-full tabular-nums">{{ $nav['count'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </nav>

                    {{-- Footer sidebar --}}
                    <div class="px-4 py-3 border-t border-slate-700/60">
                        <p class="text-[10px] text-slate-600">ptah &bull; crud engine</p>
                    </div>
                </aside>

                {{-- ── Conteúdo Principal ────────────────────────────────────── --}}
                <div class="flex flex-col flex-1 min-w-0">
                    {{-- Top bar --}}
                    <div class="flex items-center justify-between py-4 bg-white border-b px-7 border-slate-100">
                        <div>
                            <h2 class="text-base font-semibold text-slate-800" x-text="{
                                cols: 'Configuração de Colunas',
                                actions: 'Ações por Linha',
                                filters: 'Filtros Personalizados',
                                styles: 'Estilos Condicionais',
                                general: 'Configurações Gerais',
                                permissions: 'Permissões e Acesso'
                            }[tab]"></h2>
                            <p class="text-xs text-slate-400 mt-0.5" x-text="{
                                cols: 'Defina, ordene e configure cada coluna da tabela',
                                actions: 'Botões e links exibidos em cada linha',
                                filters: 'Filtros avançados com relações e agregações',
                                styles: 'Estilize linhas com base em condições dos dados',
                                general: 'Cache, exportação, aparência e comportamento',
                                permissions: 'Gates do Laravel e visibilidade de botões'
                            }[tab]"></p>
                        </div>
                        <button wire:click="closeModal" class="p-2 transition-colors rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Scroll area --}}
                    <div class="flex-1 overflow-y-auto bg-slate-50">

                        {{-- ═══════════════════════════════════════════════════ --}}
                        {{-- TAB: COLUNAS ────────────────────────────────────── --}}
                        {{-- ═══════════════════════════════════════════════════ --}}
                        <div x-show="tab === 'cols'" class="p-6 space-y-5">

                            {{-- Tabela de colunas --}}
                            <div class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200">
                                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-700">Colunas da Tabela</h3>
                                        <p class="text-xs text-slate-400 mt-0.5">Arraste para reordenar. Clique em ✏ para editar.</p>
                                    </div>
                                </div>

                                <table class="w-full text-xs">
                                    <thead class="border-b bg-slate-50 border-slate-100">
                                        <tr class="text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                            <th class="w-8 px-3 py-2.5"></th>
                                            <th class="px-3 py-2.5">Campo Físico</th>
                                            <th class="px-3 py-2.5">Label</th>
                                            <th class="px-3 py-2.5">Tipo</th>
                                            <th class="px-3 py-2.5">Renderer</th>
                                            <th class="px-3 py-2.5">Máscara</th>
                                            <th class="px-3 py-2.5 text-center">Gravar</th>
                                            <th class="px-3 py-2.5 text-center">Filtrável</th>
                                            <th class="px-3 py-2.5 w-20 text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        id="cols-sortable"
                                        class="divide-y divide-slate-100"
                                    >
                                        @foreach ($formEditFields as $i => $col)
                                            @if (($col['colsTipo'] ?? '') !== 'action')
                                                <tr class="transition-colors hover:bg-slate-50" data-index="{{ $i }}">
                                                    <td class="px-3 py-2 cursor-move select-none text-slate-300 hover:text-slate-500">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6h2v2H8zm6 0h2v2h-2zM8 11h2v2H8zm6 0h2v2h-2zM8 16h2v2H8zm6 0h2v2h-2z"/></svg>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <code class="text-[11px] font-mono text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">{{ $col['colsNomeFisico'] ?? '' }}</code>
                                                        @if (!empty($col['colsRelacaoNested']))
                                                            <span class="text-[10px] text-slate-400 ml-1">→ {{ $col['colsRelacaoNested'] }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-slate-700">{{ $col['colsNomeLogico'] ?? '' }}</td>
                                                    <td class="px-3 py-2">
                                                        <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[11px] font-medium">{{ $col['colsTipo'] ?? 'text' }}</span>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        @if (!empty($col['colsRenderer']))
                                                            <span class="bg-violet-50 text-violet-700 px-1.5 py-0.5 rounded text-[11px] font-medium">{{ $col['colsRenderer'] }}</span>
                                                        @elseif (!empty($col['colsHelper']))
                                                            <span class="bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded text-[11px]">{{ $col['colsHelper'] }}</span>
                                                        @else
                                                            <span class="text-slate-300">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        @if (!empty($col['colsMask']))
                                                            <span class="bg-green-50 text-green-700 px-1.5 py-0.5 rounded text-[11px]">{{ $col['colsMask'] }}</span>
                                                        @else
                                                            <span class="text-slate-300">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-block w-4 h-4 rounded-full {{ ($col['colsGravar'] ?? 'N') === 'S' ? 'bg-green-400' : 'bg-slate-200' }}"></span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-block w-4 h-4 rounded-full {{ ($col['colsIsFilterable'] ?? 'N') === 'S' ? 'bg-blue-400' : 'bg-slate-200' }}"></span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <button wire:click="editField({{ $i }})" @click="editTab = 'basic'" title="Editar"
                                                                class="p-1 transition-colors rounded text-slate-400 hover:bg-indigo-50 hover:text-indigo-600">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                                </svg>
                                                            </button>
                                                            <button wire:click="removeField({{ $i }})" wire:confirm="Remover coluna '{{ $col['colsNomeLogico'] ?? $col['colsNomeFisico'] }}'?"
                                                                class="p-1 transition-colors rounded text-slate-400 hover:bg-red-50 hover:text-red-500">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        @if (count(array_filter($formEditFields, fn($c) => ($c['colsTipo'] ?? '') !== 'action')) === 0)
                                            <tr>
                                                <td colspan="9" class="px-5 py-8 text-sm text-center text-slate-400">
                                                    Nenhuma coluna configurada. Adicione abaixo.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            {{-- Formulário de edição/adição --}}
                            <div class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
                                 x-data="{ editTab: 'basic' }">
                                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                                    <h3 class="text-sm font-semibold text-slate-700">
                                        {{ $editingFieldIndex >= 0 ? '✏ Editando coluna' : '+ Nova Coluna' }}
                                    </h3>
                                    @if ($editingFieldIndex >= 0)
                                        <button wire:click="cancelEditField" class="text-xs text-slate-400 hover:text-slate-600">Cancelar edição</button>
                                    @endif
                                </div>

                                {{-- Sub-tabs do formulário --}}
                                <div class="flex gap-0 px-5 border-b border-slate-100 bg-slate-50">
                                    @php
                                        $fTabs = [
                                            ['id' => 'basic',      'label' => 'Básico'],
                                            ['id' => 'renderer',   'label' => 'Exibição'],
                                            ['id' => 'mask',       'label' => 'Máscara'],
                                            ['id' => 'validation', 'label' => 'Validação'],
                                            ['id' => 'relation',   'label' => 'Relação'],
                                            ['id' => 'sd',         'label' => 'SearchDropdown'],
                                            ['id' => 'total',      'label' => 'Totalizador'],
                                        ];
                                    @endphp
                                    @foreach ($fTabs as $ft)
                                        <button @click="editTab = '{{ $ft['id'] }}'"
                                            :class="editTab === '{{ $ft['id'] }}' ? 'border-b-2 border-indigo-600 text-indigo-600 font-semibold' : 'text-slate-400 hover:text-slate-600'"
                                            class="px-3 py-2.5 text-[11px] transition-colors whitespace-nowrap">
                                            {{ $ft['label'] }}
                                        </button>
                                    @endforeach
                                </div>

                                <div class="p-5">
                                    {{-- ── Básico ───────────────────────────────────── --}}
                                    <div x-show="editTab === 'basic'" class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="cfg-label">Campo Físico (DB) *</label>
                                            <input type="text" wire:model="formDataField.colsNomeFisico" placeholder="ex: supplier_id"
                                                class="font-mono cfg-input" />
                                        </div>
                                        <div>
                                            <label class="cfg-label">Label (exibição)</label>
                                            <input type="text" wire:model="formDataField.colsNomeLogico" placeholder="ex: Fornecedor"
                                                class="cfg-input" />
                                        </div>
                                        <div>
                                            <label class="cfg-label">Tipo</label>
                                            <select wire:model.live="formDataField.colsTipo" class="cfg-input">
                                                <option value="text">text — Texto</option>
                                                <option value="number">number — Número</option>
                                                <option value="date">date — Data</option>
                                                <option value="datetime">datetime — Data e Hora</option>
                                                <option value="select">select — Seleção</option>
                                                <option value="searchdropdown">searchdropdown — Busca Relacional</option>
                                                <option value="boolean">boolean — Sim/Não</option>
                                                <option value="textarea">textarea — Texto Longo</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="cfg-label">Alinhamento</label>
                                            <select wire:model="formDataField.colsAlign" class="cfg-input">
                                                <option value="text-start">Esquerda</option>
                                                <option value="text-center">Centro</option>
                                                <option value="text-end">Direita</option>
                                            </select>
                                        </div>
                                        <div class="flex col-span-2 gap-6">
                                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                                <input type="checkbox" wire:model="formDataField.colsGravar" true-value="S" false-value="N"
                                                    class="text-indigo-600 rounded border-slate-300" />
                                                <span class="text-xs font-medium text-slate-600">Incluir no Formulário (Gravar)</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                                <input type="checkbox" wire:model="formDataField.colsRequired" true-value="S" false-value="N"
                                                    class="text-indigo-600 rounded border-slate-300" />
                                                <span class="text-xs font-medium text-slate-600">Obrigatório</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                                <input type="checkbox" wire:model="formDataField.colsIsFilterable" true-value="S" false-value="N"
                                                    class="text-indigo-600 rounded border-slate-300" />
                                                <span class="text-xs font-medium text-slate-600">Filtrável</span>
                                            </label>
                                        </div>
                                        {{-- Select options (condicional) --}}
                                        @if (($formDataField['colsTipo'] ?? '') === 'select')
                                            <div class="col-span-2">
                                                <label class="cfg-label">Opções do Select</label>
                                                <input type="text" wire:model="formDataField.colsSelect"
                                                    placeholder="chave;Rótulo;;chave2;Rótulo2"
                                                    class="font-mono cfg-input" />
                                                <p class="text-[11px] text-slate-400 mt-1">Formato: <code class="px-1 rounded bg-slate-100">chave;Rótulo</code> separados por <code class="px-1 rounded bg-slate-100">;;</code></p>
                                            </div>
                                        @endif
                                        <div>
                                            <label class="cfg-label">Método Customizado (PHP)</label>
                                            <input type="text" wire:model="formDataField.colsMetodoCustom"
                                                placeholder="App\Services\MyService\formatValue(%campo%)"
                                                class="cfg-input font-mono text-[11px]" />
                                        </div>
                                        <div>
                                            <label class="cfg-label">Ordenação alternativa (colsOrderBy)</label>
                                            <input type="text" wire:model="formDataField.colsOrderBy"
                                                placeholder="campo_db ou relation.campo"
                                                class="cfg-input" />
                                        </div>

                                        {{-- ── Estilo da Célula ── --}}
                                        <div class="col-span-2 pt-4 mt-1 border-t border-slate-100">
                                            <p class="mb-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                                <svg class="inline w-3.5 h-3.5 mr-1 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                                                Estilo da Célula
                                            </p>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="cfg-label">CSS Inline (colsCellStyle)</label>
                                                    <input type="text" wire:model.live="formDataField.colsCellStyle"
                                                        placeholder="font-weight:700; color:#1a56db; font-size:13px;"
                                                        class="cfg-input font-mono text-[11px]" />
                                                    <p class="text-[11px] text-slate-400 mt-1">Aplicado no wrapper do conteúdo da célula</p>
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Largura Mínima (colsMinWidth)</label>
                                                    <input type="text" wire:model="formDataField.colsMinWidth"
                                                        placeholder="140px ou 8rem"
                                                        class="cfg-input" />
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Ícone Prefixo (colsCellIcon)</label>
                                                    <input type="text" wire:model="formDataField.colsCellIcon"
                                                        placeholder="bx bx-user-circle mr-1"
                                                        class="cfg-input font-mono" />
                                                    <p class="text-[11px] text-slate-400 mt-1">Classe CSS do ícone (Boxicons, FontAwesome...)</p>
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Classe Tailwind / CSS Extra</label>
                                                    <input type="text" wire:model="formDataField.colsCellClass"
                                                        placeholder="font-bold text-blue-600 uppercase"
                                                        class="cfg-input font-mono" />
                                                </div>
                                            </div>
                                            {{-- Preview ao vivo --}}
                                            @if (!empty($formDataField['colsCellStyle']) || !empty($formDataField['colsCellClass']) || !empty($formDataField['colsCellIcon']))
                                                <div class="flex items-center gap-3 px-4 py-3 mt-3 rounded-lg bg-slate-50 border border-slate-200">
                                                    <span class="text-[11px] text-slate-400 shrink-0">Preview:</span>
                                                    <span
                                                        style="{{ $formDataField['colsCellStyle'] ?? '' }}"
                                                        class="inline-flex items-center {{ $formDataField['colsCellClass'] ?? '' }}">
                                                        @if (!empty($formDataField['colsCellIcon']))
                                                            <span class="{{ $formDataField['colsCellIcon'] }}"></span>
                                                        @endif
                                                        Exemplo de valor
                                                    </span>
                                                    <span class="text-[10px] text-slate-300 ml-auto font-mono">
                                                        {{ $formDataField['colsCellStyle'] ?? '' }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ── Exibição / Renderer DSL ──────────────────── --}}
                                    <div x-show="editTab === 'renderer'" class="space-y-4">
                                        <div>
                                            <label class="cfg-label">Renderer</label>
                                            <select wire:model.live="formDataField.colsRenderer" class="max-w-xs cfg-input">
                                                <option value="">— Nenhum (valor bruto) —</option>
                                                <option value="badge">badge — Badge colorido por valor</option>
                                                <option value="pill">pill — Pill arredondado</option>
                                                <option value="boolean">boolean — Sim / Não</option>
                                                <option value="money">money — Valor monetário</option>
                                                <option value="date">date — Data (d/m/Y)</option>
                                                <option value="datetime">datetime — Data e Hora</option>
                                                <option value="link">link — Link clicável</option>
                                                <option value="image">image — Imagem miniatura</option>
                                                <option value="truncate">truncate — Texto truncado</option>
                                            </select>
                                            <p class="text-[11px] text-slate-400 mt-1">⚠ <code class="px-1 rounded bg-slate-100">colsHelper</code> legado continua funcionando se nenhum renderer for selecionado.</p>
                                        </div>

                                        {{-- badge / pill --}}
                                        @if (in_array($formDataField['colsRenderer'] ?? '', ['badge', 'pill']))
                                            <div>
                                                <label class="cfg-label">Mapeamento de Badges</label>
                                                <p class="text-[11px] text-slate-400 mb-2">Cada entrada mapeia um valor do banco para um rótulo e cor.</p>
                                                @foreach ($formDataField['colsRendererBadges'] ?? [] as $bi => $badge)
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <input type="text" wire:model="formDataField.colsRendererBadges.{{ $bi }}.value"
                                                            placeholder="valor" class="flex-1 font-mono cfg-input-sm" />
                                                        <input type="text" wire:model="formDataField.colsRendererBadges.{{ $bi }}.label"
                                                            placeholder="rótulo" class="flex-1 cfg-input-sm" />
                                                        {{-- Swatches de Cor --}}
                                                        @php
                                                            $swPre = [
                                                                ['k'=>'green',  'bg'=>'#22c55e','t'=>'Verde'],
                                                                ['k'=>'yellow', 'bg'=>'#eab308','t'=>'Amarelo'],
                                                                ['k'=>'red',    'bg'=>'#ef4444','t'=>'Vermelho'],
                                                                ['k'=>'blue',   'bg'=>'#3b82f6','t'=>'Azul'],
                                                                ['k'=>'indigo', 'bg'=>'#6366f1','t'=>'Índigo'],
                                                                ['k'=>'purple', 'bg'=>'#a855f7','t'=>'Roxo'],
                                                                ['k'=>'pink',   'bg'=>'#ec4899','t'=>'Rosa'],
                                                                ['k'=>'gray',   'bg'=>'#9ca3af','t'=>'Cinza'],
                                                            ];
                                                            $swCur = $badge['color'] ?? 'gray';
                                                            $swHex = str_starts_with($swCur, '#');
                                                        @endphp
                                                        <div class="flex items-center gap-1 shrink-0" title="Cor do badge">
                                                            @foreach ($swPre as $sw)
                                                                <button type="button"
                                                                    wire:click="$set('formDataField.colsRendererBadges.{{ $bi }}.color', '{{ $sw['k'] }}')"
                                                                    style="background-color:{{ $sw['bg'] }}"
                                                                    title="{{ $sw['t'] }}"
                                                                    class="w-4 h-4 rounded-full border-2 transition-transform hover:scale-125 {{ $swCur === $sw['k'] ? 'border-slate-700 scale-125' : 'border-white shadow-sm' }}">
                                                                </button>
                                                            @endforeach
                                                            <input type="color"
                                                                value="{{ $swHex ? $swCur : '#6366f1' }}"
                                                                @change="$wire.set('formDataField.colsRendererBadges.{{ $bi }}.color', $event.target.value)"
                                                                title="Cor personalizada (hex)"
                                                                class="w-6 h-5 cursor-pointer rounded border border-slate-300 p-0 overflow-hidden" />
                                                            @if ($swHex)
                                                                <span class="text-[9px] font-mono" style="color:{{ $swCur }}">{{ $swCur }}</span>
                                                            @endif
                                                        </div>
                                                        <input type="text" wire:model="formDataField.colsRendererBadges.{{ $bi }}.icon"
                                                            placeholder="bx bx-check (opcional)" class="flex-1 cfg-input-sm" />
                                                        <button wire:click="$set('formDataField.colsRendererBadges', array_values(array_filter($formDataField['colsRendererBadges'] ?? [], fn($k) => $k != {{ $bi }}, ARRAY_FILTER_USE_KEY)))"
                                                            class="p-1 text-red-400 hover:text-red-600 shrink-0">✕</button>
                                                    </div>
                                                @endforeach
                                                <button
                                                    wire:click="$set('formDataField.colsRendererBadges', array_merge($formDataField['colsRendererBadges'] ?? [], [['value' => '', 'label' => '', 'color' => 'gray', 'icon' => '']]))"
                                                    class="mt-1 text-xs font-medium text-indigo-600 hover:text-indigo-800">+ Adicionar badge</button>
                                            </div>
                                        @endif

                                        {{-- boolean --}}
                                        @if (($formDataField['colsRenderer'] ?? '') === 'boolean')
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="cfg-label">Texto Verdadeiro</label>
                                                    <input type="text" wire:model="formDataField.colsRendererBoolTrue" placeholder="Sim" class="cfg-input" />
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Texto Falso</label>
                                                    <input type="text" wire:model="formDataField.colsRendererBoolFalse" placeholder="Não" class="cfg-input" />
                                                </div>
                                            </div>
                                        @endif

                                        {{-- money --}}
                                        @if (($formDataField['colsRenderer'] ?? '') === 'money')
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="cfg-label">Moeda</label>
                                                    <select wire:model="formDataField.colsRendererCurrency" class="cfg-input">
                                                        <option value="BRL">BRL — Real Brasileiro</option>
                                                        <option value="USD">USD — Dólar</option>
                                                        <option value="EUR">EUR — Euro</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Casas Decimais</label>
                                                    <input type="number" wire:model="formDataField.colsRendererDecimals" min="0" max="4" class="cfg-input" />
                                                </div>
                                            </div>
                                        @endif

                                        {{-- link --}}
                                        @if (($formDataField['colsRenderer'] ?? '') === 'link')
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="cfg-label">Template da URL</label>
                                                    <input type="text" wire:model="formDataField.colsRendererLinkTemplate"
                                                        placeholder="/pedidos/%id%/detalhe"
                                                        class="font-mono cfg-input" />
                                                    <p class="text-[11px] text-slate-400 mt-1">Use <code class="px-1 rounded bg-slate-100">%campo%</code> para substituir por qualquer campo do registro. Ex: <code class="px-1 rounded bg-slate-100">%id%</code></p>
                                                </div>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="cfg-label">Label do link (opcional)</label>
                                                        <input type="text" wire:model="formDataField.colsRendererLinkLabel"
                                                            placeholder="Ver detalhes" class="cfg-input" />
                                                    </div>
                                                    <div class="flex items-end pb-1">
                                                        <label class="flex items-center gap-2 cursor-pointer">
                                                            <input type="checkbox" wire:model="formDataField.colsRendererLinkNewTab" class="text-indigo-600 rounded border-slate-300" />
                                                            <span class="text-xs text-slate-600">Abrir em nova aba</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- image --}}
                                        @if (($formDataField['colsRenderer'] ?? '') === 'image')
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="cfg-label">Largura (px)</label>
                                                    <input type="number" wire:model="formDataField.colsRendererImageWidth" placeholder="40" class="cfg-input" />
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Altura (px, opcional)</label>
                                                    <input type="number" wire:model="formDataField.colsRendererImageHeight" placeholder="40" class="cfg-input" />
                                                </div>
                                            </div>
                                        @endif

                                        {{-- truncate --}}
                                        @if (($formDataField['colsRenderer'] ?? '') === 'truncate')
                                            <div class="max-w-xs">
                                                <label class="cfg-label">Máximo de Caracteres</label>
                                                <input type="number" wire:model="formDataField.colsRendererMaxChars" placeholder="50" class="cfg-input" />
                                            </div>
                                        @endif
                                    </div>

                                    {{-- ── Máscara de Input ────────────────────────── --}}
                                    <div x-show="editTab === 'mask'" class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="cfg-label">Máscara de Entrada</label>
                                                <select wire:model.live="formDataField.colsMask" class="cfg-input">
                                                    <option value="">— Sem máscara —</option>
                                                    <optgroup label="Monetário">
                                                        <option value="money_brl">money_brl — R$ 1.253,08</option>
                                                        <option value="money_usd">money_usd — $ 1,253.08</option>
                                                        <option value="percent">percent — 99,99%</option>
                                                    </optgroup>
                                                    <optgroup label="Documentos">
                                                        <option value="cpf">cpf — 000.000.000-00</option>
                                                        <option value="cnpj">cnpj — 00.000.000/0000-00</option>
                                                    </optgroup>
                                                    <optgroup label="Contato">
                                                        <option value="phone">phone — (00) 0 0000-0000</option>
                                                        <option value="cep">cep — 00000-000</option>
                                                    </optgroup>
                                                    <optgroup label="Data/Hora">
                                                        <option value="date">date — 00/00/0000</option>
                                                        <option value="datetime">datetime — 00/00/0000 00:00</option>
                                                    </optgroup>
                                                    <optgroup label="Texto">
                                                        <option value="integer">integer — Somente inteiros</option>
                                                        <option value="uppercase">uppercase — MAIÚSCULAS automático</option>
                                                        <option value="custom_regex">custom_regex — Expressão personalizada</option>
                                                    </optgroup>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="cfg-label">Transformação antes de Salvar</label>
                                                <select wire:model="formDataField.colsMaskTransform" class="cfg-input">
                                                    <option value="">— Nenhuma —</option>
                                                    <option value="money_to_float">money_to_float — "R$ 1.253,08" → 1253.08</option>
                                                    <option value="digits_only">digits_only — "055.465.309-52" → "05546530952"</option>
                                                    <option value="uppercase">uppercase — "texto" → "TEXTO"</option>
                                                    <option value="lowercase">lowercase — "TEXTO" → "texto"</option>
                                                    <option value="trim">trim — Remove espaços das bordas</option>
                                                </select>
                                            </div>
                                        </div>

                                        @if (($formDataField['colsMask'] ?? '') === 'custom_regex')
                                            <div>
                                                <label class="cfg-label">Padrão Regex (IMask)</label>
                                                <input type="text" wire:model="formDataField.colsMaskRegex"
                                                    placeholder="Ex: 000-000-A ou /^[A-Z]{3}$/"
                                                    class="font-mono cfg-input" />
                                            </div>
                                        @endif

                                        {{-- Preview da transformação --}}
                                        @if (!empty($formDataField['colsMaskTransform']))
                                            <div class="px-4 py-3 border rounded-lg bg-amber-50 border-amber-200">
                                                <p class="mb-1 text-xs font-semibold text-amber-700">⚡ Transformação aplicada ao salvar:</p>
                                                <p class="text-xs text-amber-600">
                                                    @switch($formDataField['colsMaskTransform'])
                                                        @case('money_to_float') R$ 1.253,08 → <strong>1253.08</strong> @break
                                                        @case('digits_only') 055.465.309-52 → <strong>05546530952</strong> (remove non-digits) @break
                                                        @case('uppercase') "texto" → <strong>"TEXTO"</strong> @break
                                                        @case('lowercase') "TEXTO" → <strong>"texto"</strong> @break
                                                        @case('trim') " texto " → <strong>"texto"</strong> @break
                                                    @endswitch
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- ── Validações ──────────────────────────────── --}}
                                    <div x-show="editTab === 'validation'" class="space-y-4">
                                        <p class="text-xs text-slate-500">Regras adicionais além do <strong>Obrigatório</strong> (configurado na aba Básico).</p>
                                        <div class="grid grid-cols-3 gap-3">
                                            @php
                                                $currentValidations = $formDataField['colsValidations'] ?? [];
                                                $hasRule = fn($r) => in_array($r, $currentValidations);
                                                $toggleRule = fn($r) => $hasRule($r)
                                                    ? array_values(array_filter($currentValidations, fn($v) => $v !== $r))
                                                    : [...$currentValidations, $r];
                                            @endphp
                                            @foreach (['email' => 'E-mail válido', 'url' => 'URL válida', 'integer' => 'Inteiro', 'numeric' => 'Numérico', 'cpf' => 'CPF válido', 'cnpj' => 'CNPJ válido', 'phone' => 'Telefone válido'] as $rule => $ruleLabel)
                                                <label class="flex items-center gap-2 cursor-pointer p-2.5 rounded-lg border {{ $hasRule($rule) ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 bg-white hover:bg-slate-50' }} transition-colors select-none">
                                                    <input type="checkbox"
                                                        {{ $hasRule($rule) ? 'checked' : '' }}
                                                        wire:change="$set('formDataField.colsValidations', {{ json_encode($toggleRule($rule)) }})"
                                                        class="text-indigo-600 rounded border-slate-300" />
                                                    <span class="text-xs font-medium text-slate-700">{{ $ruleLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="cfg-label">Valor Mínimo (min:X)</label>
                                                <input type="number" step="any"
                                                    value="{{ collect($currentValidations)->first(fn($v) => str_starts_with($v, 'min:')) ? substr(collect($currentValidations)->first(fn($v) => str_starts_with($v, 'min:')), 4) : '' }}"
                                                    @change="
                                                        let rules = @js($currentValidations).filter(r => !r.startsWith('min:'));
                                                        if ($event.target.value !== '') rules.push('min:' + $event.target.value);
                                                        $wire.set('formDataField.colsValidations', rules);
                                                    "
                                                    placeholder="ex: 0" class="cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Valor Máximo (max:X)</label>
                                                <input type="number" step="any"
                                                    value="{{ collect($currentValidations)->first(fn($v) => str_starts_with($v, 'max:')) ? substr(collect($currentValidations)->first(fn($v) => str_starts_with($v, 'max:')), 4) : '' }}"
                                                    @change="
                                                        let rules = @js($currentValidations).filter(r => !r.startsWith('max:'));
                                                        if ($event.target.value !== '') rules.push('max:' + $event.target.value);
                                                        $wire.set('formDataField.colsValidations', rules);
                                                    "
                                                    placeholder="ex: 9999" class="cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Comprimento Mínimo (minLength:X)</label>
                                                <input type="number" min="0"
                                                    value="{{ collect($currentValidations)->first(fn($v) => str_starts_with($v, 'minLength:')) ? substr(collect($currentValidations)->first(fn($v) => str_starts_with($v, 'minLength:')), 10) : '' }}"
                                                    @change="
                                                        let rules = @js($currentValidations).filter(r => !r.startsWith('minLength:'));
                                                        if ($event.target.value !== '') rules.push('minLength:' + $event.target.value);
                                                        $wire.set('formDataField.colsValidations', rules);
                                                    "
                                                    placeholder="ex: 3" class="cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Comprimento Máximo (maxLength:X)</label>
                                                <input type="number" min="0"
                                                    value="{{ collect($currentValidations)->first(fn($v) => str_starts_with($v, 'maxLength:')) ? substr(collect($currentValidations)->first(fn($v) => str_starts_with($v, 'maxLength:')), 10) : '' }}"
                                                    @change="
                                                        let rules = @js($currentValidations).filter(r => !r.startsWith('maxLength:'));
                                                        if ($event.target.value !== '') rules.push('maxLength:' + $event.target.value);
                                                        $wire.set('formDataField.colsValidations', rules);
                                                    "
                                                    placeholder="ex: 255" class="cfg-input" />
                                            </div>
                                            <div class="col-span-2">
                                                <label class="cfg-label">Regex Personalizado</label>
                                                <input type="text"
                                                    value="{{ collect($currentValidations)->first(fn($v) => str_starts_with($v, 'regex:')) ? substr(collect($currentValidations)->first(fn($v) => str_starts_with($v, 'regex:')), 6) : '' }}"
                                                    @change="
                                                        let rules = @js($currentValidations).filter(r => !r.startsWith('regex:'));
                                                        if ($event.target.value !== '') rules.push('regex:' + $event.target.value);
                                                        $wire.set('formDataField.colsValidations', rules);
                                                    "
                                                    placeholder="Ex: ^[A-Z]{2,5}$ ou /^\d{5}$/"
                                                    class="font-mono cfg-input" />
                                            </div>
                                        </div>
                                        @if (!empty($currentValidations))
                                            <div class="px-4 py-3 border rounded-lg bg-slate-50 border-slate-200">
                                                <p class="text-[11px] font-semibold text-slate-600 mb-1.5">Regras ativas:</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($currentValidations as $rv)
                                                        <span class="bg-indigo-100 text-indigo-700 text-[11px] font-mono px-2 py-0.5 rounded-full">{{ $rv }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- ── Relação ─────────────────────────────────── --}}
                                    <div x-show="editTab === 'relation'" class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="cfg-label">Relação Eloquent</label>
                                                <input type="text" wire:model="formDataField.colsRelacao"
                                                    placeholder="ex: supplier" class="font-mono cfg-input" />
                                                <p class="text-[11px] text-slate-400 mt-1">Nome do método de relação no Model</p>
                                            </div>
                                            <div>
                                                <label class="cfg-label">Campo a Exibir</label>
                                                <input type="text" wire:model="formDataField.colsRelacaoExibe"
                                                    placeholder="ex: name" class="font-mono cfg-input" />
                                            </div>
                                        </div>
                                        <div class="p-4 space-y-3 border-2 border-indigo-200 border-dashed rounded-lg bg-indigo-50/50">
                                            <div>
                                                <p class="mb-1 text-xs font-semibold text-indigo-700">
                                                    🔗 Relação Aninhada (Dot Notation)
                                                </p>
                                                <p class="text-[11px] text-indigo-600 mb-3">Use quando o dado está em múltiplos níveis: <code class="px-1 font-mono bg-white rounded">address.city.name</code></p>
                                                <label class="cfg-label">Caminho Dot Notation</label>
                                                <input type="text" wire:model="formDataField.colsRelacaoNested"
                                                    placeholder="ex: address.city.name ou supplier.contact.email"
                                                    class="font-mono cfg-input" />
                                                <p class="text-[11px] text-slate-400 mt-1">O eager loading é automático. O último segmento é o campo; os anteriores são as relações.</p>
                                            </div>
                                            @if (!empty($formDataField['colsRelacaoNested']))
                                                @php $nestedParts = explode('.', $formDataField['colsRelacaoNested']); @endphp
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    @foreach ($nestedParts as $pi => $part)
                                                        <span class="{{ $pi === count($nestedParts) - 1 ? 'bg-green-100 text-green-700 font-semibold' : 'bg-white text-indigo-700 border border-indigo-200' }} text-xs px-2 py-0.5 rounded font-mono">{{ $part }}</span>
                                                        @if ($pi < count($nestedParts) - 1)
                                                            <span class="text-sm text-slate-300">→</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                                <p class="text-[11px] text-slate-500">Eager loads: <code class="px-1 font-mono rounded bg-slate-100">{{ implode('.', array_slice($nestedParts, 0, count($nestedParts) - 1)) }}</code></p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ── SearchDropdown Config ───────────────────── --}}
                                    <div x-show="editTab === 'sd'" class="space-y-4">
                                        <div class="px-4 py-3 border border-blue-200 rounded-lg bg-blue-50">
                                            <p class="text-xs text-blue-700">Configuração para tipo <strong>searchdropdown</strong>. Disponível apenas quando o tipo da coluna for SearchDropdown.</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="col-span-2">
                                                <label class="cfg-label">Modo de Busca</label>
                                                <div class="flex gap-3">
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" wire:model.live="formDataField.colsSDMode" value="model" class="text-indigo-600" />
                                                        <span class="text-xs font-medium text-slate-700">Model Eloquent</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" wire:model.live="formDataField.colsSDMode" value="service" class="text-indigo-600" />
                                                        <span class="text-xs font-medium text-slate-700">Service customizado</span>
                                                    </label>
                                                </div>
                                            </div>
                                            @if (($formDataField['colsSDMode'] ?? 'model') === 'model')
                                                <div class="col-span-2">
                                                    <label class="cfg-label">Model (caminho relativo a App\Models)</label>
                                                    <input type="text" wire:model="formDataField.colsSDModel"
                                                        placeholder="ex: Entrie/ShippingCompanies"
                                                        class="font-mono cfg-input" />
                                                </div>
                                            @else
                                                <div>
                                                    <label class="cfg-label">Service (caminho relativo a App\Services)</label>
                                                    <input type="text" wire:model="formDataField.colsSDService"
                                                        placeholder="ex: Entrie/ShippingCompaniesService"
                                                        class="font-mono cfg-input" />
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Método do Service</label>
                                                    <input type="text" wire:model="formDataField.colsSDServiceMethod"
                                                        placeholder="ex: searchDropDownOfShippingCompanies"
                                                        class="font-mono cfg-input" />
                                                </div>
                                            @endif
                                            <div>
                                                <label class="cfg-label">Campo Valor (value)</label>
                                                <input type="text" wire:model="formDataField.colsSDValueField"
                                                    placeholder="id" class="font-mono cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Campo Label (label)</label>
                                                <input type="text" wire:model="formDataField.colsSDLabelField"
                                                    placeholder="name" class="font-mono cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Label Secundário (opcional)</label>
                                                <input type="text" wire:model="formDataField.colsSDLabelSecondary"
                                                    placeholder="cnpj" class="font-mono cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Ordenação (orderByRaw)</label>
                                                <input type="text" wire:model="formDataField.colsSDOrderBy"
                                                    placeholder="id asc" class="font-mono cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Limite de Resultados</label>
                                                <input type="number" wire:model="formDataField.colsSDLimit"
                                                    placeholder="10" min="1" max="100" class="cfg-input" />
                                            </div>
                                            <div>
                                                <label class="cfg-label">Placeholder</label>
                                                <input type="text" wire:model="formDataField.colsSDPlaceholder"
                                                    placeholder="Buscar..." class="cfg-input" />
                                            </div>
                                            <div class="col-span-2">
                                                <label class="cfg-label">Filtros Estáticos (JSON)</label>
                                                <input type="text" wire:model="formDataField.colsSDFilters"
                                                    placeholder='[{"field":"active","value":"S"}]'
                                                    class="cfg-input font-mono text-[11px]" />
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ── Totalizador ─────────────────────────────── --}}
                                    <div x-show="editTab === 'total'" class="space-y-4">
                                        <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer select-none border-slate-200 hover:bg-slate-50">
                                            <input type="checkbox" wire:model.live="formDataField.totalizadorEnabled"
                                                class="text-indigo-600 rounded border-slate-300" />
                                            <span class="text-sm font-medium text-slate-700">Habilitar Totalizador nesta Coluna</span>
                                        </label>
                                        @if (!empty($formDataField['totalizadorEnabled']))
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="cfg-label">Função</label>
                                                    <select wire:model="formDataField.totalizadorType" class="cfg-input">
                                                        <option value="sum">SUM — Soma</option>
                                                        <option value="avg">AVG — Média</option>
                                                        <option value="count">COUNT — Contagem</option>
                                                        <option value="min">MIN — Mínimo</option>
                                                        <option value="max">MAX — Máximo</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="cfg-label">Formato</label>
                                                    <select wire:model="formDataField.totalizadorFormat" class="cfg-input">
                                                        <option value="currency">currency — R$ 1.253,08</option>
                                                        <option value="number">number — 1.253,08</option>
                                                        <option value="integer">integer — 1.253</option>
                                                    </select>
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="cfg-label">Label</label>
                                                    <input type="text" wire:model="formDataField.totalizadorLabel"
                                                        placeholder="Total" class="cfg-input" />
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Botão salvar campo --}}
                                    <div class="flex justify-end pt-4 mt-4 border-t border-slate-100">
                                        @if ($editingFieldIndex >= 0)
                                            <button wire:click="updateField"
                                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                Salvar Alterações da Coluna
                                            </button>
                                        @else
                                            <button wire:click="addField"
                                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                Adicionar Coluna
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════ --}}
                        {{-- TAB: AÇÕES ──────────────────────────────────────── --}}
                        {{-- ═══════════════════════════════════════════════════ --}}
                        <div x-show="tab === 'actions'" class="p-6 space-y-5">
                            <div class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200">
                                <div class="px-5 py-3.5 border-b border-slate-100">
                                    <h3 class="text-sm font-semibold text-slate-700">Ações por Linha</h3>
                                </div>
                                <table class="w-full text-xs">
                                    <thead class="border-b bg-slate-50 border-slate-100">
                                        <tr class="text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                            <th class="px-4 py-2.5">Nome</th>
                                            <th class="px-4 py-2.5">Tipo</th>
                                            <th class="px-4 py-2.5">Valor / URL</th>
                                            <th class="px-4 py-2.5">Ícone</th>
                                            <th class="px-4 py-2.5">Cor</th>
                                            <th class="px-4 py-2.5">Permissão</th>
                                            <th class="px-4 py-2.5 w-12"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($formEditFields as $i => $col)
                                            @if (($col['colsTipo'] ?? '') === 'action')
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-2 font-medium text-slate-700">{{ $col['colsNomeLogico'] ?? '' }}</td>
                                                    <td class="px-4 py-2"><span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[11px]">{{ $col['actionType'] ?? 'link' }}</span></td>
                                                    <td class="px-4 py-2 font-mono text-[11px] text-slate-500 max-w-[200px] truncate">{{ $col['actionValue'] ?? '' }}</td>
                                                    <td class="px-4 py-2"><em class="{{ $col['actionIcon'] ?? 'bx bx-link' }}"></em></td>
                                                    <td class="px-4 py-2"><span class="bg-{{ $col['actionColor'] ?? 'slate' }}-100 text-{{ $col['actionColor'] ?? 'slate' }}-700 px-1.5 py-0.5 rounded text-[11px]">{{ $col['actionColor'] ?? 'primary' }}</span></td>
                                                    <td class="px-4 py-2 font-mono text-[11px] text-slate-400">{{ $col['actionPermission'] ?? '—' }}</td>
                                                    <td class="px-4 py-2">
                                                        <button wire:click="removeAction({{ $i }})" wire:confirm="Remover ação?"
                                                            class="p-1 transition-colors rounded text-slate-400 hover:text-red-500">✕</button>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="text-sm font-semibold text-slate-700">+ Nova Ação</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="cfg-label">Nome da Ação</label>
                                        <input type="text" wire:model="formDataAction.colsNomeLogico" placeholder="ex: Ver Detalhes" class="cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Tipo</label>
                                        <select wire:model="formDataAction.actionType" class="cfg-input">
                                            <option value="link">link — Redirecionar URL</option>
                                            <option value="livewire">livewire — Chamar método</option>
                                            <option value="javascript">javascript — Executar JS</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="cfg-label">Valor</label>
                                        <input type="text" wire:model="formDataAction.actionValue"
                                            placeholder="link: /pedidos/%id%  |  livewire: approve(%id%)  |  js: confirm(%id%)"
                                            class="font-mono cfg-input" />
                                        <p class="text-[11px] text-slate-400 mt-1">Use <code class="px-1 rounded bg-slate-100">%id%</code> ou <code class="px-1 rounded bg-slate-100">%campo%</code> como placeholder do registro.</p>
                                    </div>
                                    <div>
                                        <label class="cfg-label">Ícone (classe CSS)</label>
                                        <input type="text" wire:model="formDataAction.actionIcon" placeholder="bx bx-show" class="font-mono cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Cor</label>
                                        <select wire:model="formDataAction.actionColor" class="cfg-input">
                                            <option value="primary">primary</option>
                                            <option value="success">success</option>
                                            <option value="danger">danger</option>
                                            <option value="warning">warning</option>
                                            <option value="info">info</option>
                                            <option value="secondary">secondary</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="cfg-label">Permissão Gate (opcional)</label>
                                        <input type="text" wire:model="formDataAction.actionPermission" placeholder="ex: admin" class="font-mono cfg-input" />
                                    </div>
                                </div>
                                <div class="flex justify-end pt-2 border-t border-slate-100">
                                    <button wire:click="addAction" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                        + Adicionar Ação
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════ --}}
                        {{-- TAB: FILTROS CUSTOM ─────────────────────────────── --}}
                        {{-- ═══════════════════════════════════════════════════ --}}
                        <div x-show="tab === 'filters'" class="p-6 space-y-5">
                            @foreach ($customFilters as $fi => $cf)
                                <div class="p-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="text-sm font-semibold text-slate-700">{{ $cf['label'] ?? $cf['field'] ?? "Filtro #$fi" }}</span>
                                            <p class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $cf['field'] ?? '' }}</p>
                                        </div>
                                        <button wire:click="removeCustomFilter({{ $fi }})" wire:confirm="Remover filtro?" class="text-lg leading-none text-slate-400 hover:text-red-500">✕</button>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        @if (!empty($cf['whereHas'])) <span class="tag">whereHas: {{ $cf['whereHas'] }}</span> @endif
                                        @if (!empty($cf['field_relation'])) <span class="tag">relation field: {{ $cf['field_relation'] }}</span> @endif
                                        @if (!empty($cf['aggregate'])) <span class="tag bg-violet-50 text-violet-700">{{ $cf['aggregate'] }}</span> @endif
                                        @if (!empty($cf['colsFilterType'])) <span class="tag">tipo: {{ $cf['colsFilterType'] }}</span> @endif
                                    </div>
                                </div>
                            @endforeach

                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="text-sm font-semibold text-slate-700">+ Novo Filtro Personalizado</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="cfg-label">Campo (field)</label>
                                        <input type="text" wire:model="formDataFilter.field" placeholder="ex: supplier_id" class="font-mono cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Label</label>
                                        <input type="text" wire:model="formDataFilter.label" placeholder="ex: Fornecedor" class="cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Tipo de Input</label>
                                        <select wire:model="formDataFilter.colsFilterType" class="cfg-input">
                                            <option value="text">text</option>
                                            <option value="number">number</option>
                                            <option value="date">date</option>
                                            <option value="select">select</option>
                                            <option value="searchdropdown">searchdropdown</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="cfg-label">Operador Padrão</label>
                                        <select wire:model="formDataFilter.defaultOperator" class="cfg-input">
                                            <option value="=">=</option>
                                            <option value="LIKE">LIKE</option>
                                            <option value=">">></option>
                                            <option value="<"><</option>
                                            <option value=">=">>=</option>
                                            <option value="<="><=</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="cfg-label">whereHas (relação)</label>
                                        <input type="text" wire:model="formDataFilter.whereHas" placeholder="ex: purchaseOrderItems" class="font-mono cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Campo na Relação</label>
                                        <input type="text" wire:model="formDataFilter.field_relation" placeholder="ex: quantity" class="font-mono cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Agregação</label>
                                        <select wire:model="formDataFilter.aggregate" class="cfg-input">
                                            <option value="">— Nenhuma —</option>
                                            <option value="SUM">SUM</option>
                                            <option value="COUNT">COUNT</option>
                                            <option value="AVG">AVG</option>
                                            <option value="MAX">MAX</option>
                                            <option value="MIN">MIN</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end pt-2 border-t border-slate-100">
                                    <button wire:click="addCustomFilter" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                        + Adicionar Filtro
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════ --}}
                        {{-- TAB: ESTILOS ────────────────────────────────────── --}}
                        {{-- ═══════════════════════════════════════════════════ --}}
                        <div x-show="tab === 'styles'" class="p-6 space-y-5">
                            @foreach ($conditionStyles as $si => $style)
                                <div class="p-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-2">
                                            <code class="font-mono text-xs text-slate-700">{{ $style['field'] ?? '' }}</code>
                                            <span class="font-bold text-slate-400">{{ $style['condition'] ?? '==' }}</span>
                                            <code class="font-mono text-xs text-slate-700">{{ $style['value'] ?? '' }}</code>
                                        </div>
                                        <button wire:click="removeConditionStyle({{ $si }})" wire:confirm="Remover estilo?" class="text-slate-400 hover:text-red-500">✕</button>
                                    </div>
                                    <p class="text-[11px] font-mono text-violet-600 bg-violet-50 px-2 py-1 rounded mt-2">{{ $style['style'] ?? '' }}</p>
                                </div>
                            @endforeach

                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="text-sm font-semibold text-slate-700">+ Novo Estilo Condicional</h3>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="cfg-label">Campo</label>
                                        <input type="text" wire:model="formDataStyle.field" placeholder="ex: flag_canceled" class="font-mono cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Operador</label>
                                        <select wire:model="formDataStyle.condition" class="cfg-input">
                                            <option value="==">== (igual)</option>
                                            <option value="!=">!= (diferente)</option>
                                            <option value=">">> (maior)</option>
                                            <option value="<">< (menor)</option>
                                            <option value=">=">>= (maior ou igual)</option>
                                            <option value="<="><= (menor ou igual)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="cfg-label">Valor</label>
                                        <input type="text" wire:model="formDataStyle.value" placeholder="ex: Y" class="font-mono cfg-input" />
                                    </div>
                                </div>
                                <div>
                                    <label class="cfg-label">CSS Inline</label>
                                    <input type="text" wire:model="formDataStyle.style"
                                        placeholder="color:#999; text-decoration:line-through; background:#F5F5F5;"
                                        class="font-mono cfg-input" />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <p class="text-[11px] text-slate-400 font-semibold w-full">Presets rápidos:</p>
                                    <button type="button" wire:click="$set('formDataStyle.style', 'color:#999;text-decoration:line-through;background:#F5F5F5;')"
                                        class="cursor-pointer tag hover:bg-slate-200">Cancelado</button>
                                    <button type="button" wire:click="$set('formDataStyle.style', 'background:#FFF3CD;font-weight:bold;border-left:4px solid #FFC107;')"
                                        class="cursor-pointer tag hover:bg-amber-200">Urgente</button>
                                    <button type="button" wire:click="$set('formDataStyle.style', 'background:#D4EDDA;color:#155724;')"
                                        class="cursor-pointer tag hover:bg-green-200">Sucesso</button>
                                    <button type="button" wire:click="$set('formDataStyle.style', 'background:#F8D7DA;color:#721C24;font-weight:bold;')"
                                        class="cursor-pointer tag hover:bg-red-200">Alerta</button>
                                </div>
                                <div class="flex justify-end pt-2 border-t border-slate-100">
                                    <button wire:click="addConditionStyle" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                        + Adicionar Estilo
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════ --}}
                        {{-- TAB: GERAL ──────────────────────────────────────── --}}
                        {{-- ═══════════════════════════════════════════════════ --}}
                        <div x-show="tab === 'general'" class="p-6 space-y-5">
                            {{-- Aparência --}}
                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="pb-2 text-sm font-semibold border-b text-slate-700 border-slate-100">Aparência</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="cfg-label">Link da Linha (colsLinkLinha)</label>
                                        <input type="text" wire:model="configLinkLinha" placeholder="/rota/%id%" class="font-mono cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Classe da Tabela</label>
                                        <input type="text" wire:model="tableClass" placeholder="table table-hover" class="cfg-input font-mono text-[11px]" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Classe do Thead</label>
                                        <input type="text" wire:model="theadClass" placeholder="" class="font-mono cfg-input" />
                                    </div>
                                </div>
                                <div class="flex gap-6">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="uiCompactMode" class="text-indigo-600 rounded border-slate-300" />
                                        <span class="text-xs font-medium text-slate-700">Modo Compacto</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="uiStickyHeader" class="text-indigo-600 rounded border-slate-300" />
                                        <span class="text-xs font-medium text-slate-700">Cabeçalho Fixo</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="showTotalizador" class="text-indigo-600 rounded border-slate-300" />
                                        <span class="text-xs font-medium text-slate-700">Exibir Totalizador</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Cache --}}
                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <h3 class="text-sm font-semibold text-slate-700">Cache</h3>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="cacheEnabled" class="text-indigo-600 rounded border-slate-300" />
                                        <span class="text-xs font-medium text-slate-700">Habilitado</span>
                                    </label>
                                </div>
                                @if ($cacheEnabled)
                                    <div class="max-w-xs">
                                        <label class="cfg-label">TTL (segundos)</label>
                                        <input type="number" wire:model="cacheTtl" min="0" class="cfg-input" />
                                        <p class="text-[11px] text-slate-400 mt-1">300 = 5 minutos · 3600 = 1 hora</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Export --}}
                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="pb-2 text-sm font-semibold border-b text-slate-700 border-slate-100">Exportação</h3>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="cfg-label">Threshold Assíncrono (linhas)</label>
                                        <input type="number" wire:model="exportAsyncThreshold" min="1" class="cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Máximo de Linhas</label>
                                        <input type="number" wire:model="exportMaxRows" min="1" class="cfg-input" />
                                    </div>
                                    <div>
                                        <label class="cfg-label">Orientação PDF</label>
                                        <select wire:model="exportOrientation" class="cfg-input">
                                            <option value="landscape">Paisagem</option>
                                            <option value="portrait">Retrato</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════ --}}
                        {{-- TAB: PERMISSÕES ─────────────────────────────────── --}}
                        {{-- ═══════════════════════════════════════════════════ --}}
                        <div x-show="tab === 'permissions'" class="p-6 space-y-5">
                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="pb-2 text-sm font-semibold border-b text-slate-700 border-slate-100">Gates de Acesso</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach (['permissionCreate' => 'Criar', 'permissionEdit' => 'Editar', 'permissionDelete' => 'Excluir', 'permissionExport' => 'Exportar', 'permissionRestore' => 'Restaurar'] as $prop => $permLabel)
                                        <div>
                                            <label class="cfg-label">Gate: {{ $permLabel }}</label>
                                            <input type="text" wire:model="{{ $prop }}" placeholder="ex: admin ou manage-{{ strtolower($permLabel) }}" class="font-mono cfg-input" />
                                        </div>
                                    @endforeach
                                    <div>
                                        <label class="cfg-label">Identificador de Permissão</label>
                                        <input type="text" wire:model="permissionIdentifier" placeholder="pageMinhaRotina" class="font-mono cfg-input" />
                                    </div>
                                </div>
                            </div>
                            <div class="p-5 space-y-4 bg-white border shadow-sm rounded-xl border-slate-200">
                                <h3 class="pb-2 text-sm font-semibold border-b text-slate-700 border-slate-100">Visibilidade de Botões</h3>
                                <div class="grid grid-cols-2 gap-3">
                                    @foreach (['showCreateButton' => 'Botão Criar', 'showEditButton' => 'Botão Editar', 'showDeleteButton' => 'Botão Excluir', 'showTrashButton' => 'Botão Lixeira'] as $prop => $btnLabel)
                                        <label class="flex items-center gap-2 cursor-pointer p-2.5 rounded-lg border {{ $$prop ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-white' }} hover:bg-slate-50 transition-colors select-none">
                                            <input type="checkbox" wire:model="{{ $prop }}" class="text-indigo-600 rounded border-slate-300" />
                                            <span class="text-xs font-medium text-slate-700">{{ $btnLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    </div>{{-- /scroll area --}}

                    {{-- ── Footer ────────────────────────────────────────────── --}}
                    <div class="flex items-center justify-between gap-3 py-4 bg-white border-t px-7 border-slate-100 shrink-0">
                        <p class="text-xs text-slate-400">
                            {{ count($formEditFields) }} colunas · {{ count($customFilters) }} filtros · {{ count($conditionStyles) }} estilos
                        </p>
                        <div class="flex gap-3">
                            <button wire:click="closeModal"
                                class="px-4 py-2 text-xs font-semibold transition-colors bg-white border rounded-lg text-slate-600 border-slate-300 hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button wire:click="save" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold text-white transition-colors bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-60">
                                <span wire:loading wire:target="save" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                                <svg wire:loading.remove wire:target="save" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Salvar Configuração
                            </button>
                        </div>
                    </div>
                </div>{{-- /content --}}
            </div>{{-- /shell --}}
        </div>{{-- /fixed --}}
        @endteleport
    @endif

    @once
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.3/Sortable.min.js" defer></script>
<style>
    .cfg-label { display:block; margin-bottom:.25rem; font-size:.6875rem; font-weight:600; color:#6B7280; text-transform:uppercase; letter-spacing:.05em; }
    .cfg-input { display:block; width:100%; border-radius:.5rem; border:1px solid #D1D5DB; background:#fff; padding:.5rem .75rem; font-size:.75rem; color:#1E293B; outline:none; transition:border-color .15s,box-shadow .15s; }
    .cfg-input:focus { border-color:#818CF8; box-shadow:0 0 0 3px rgba(129,140,248,.2); }
    .cfg-input-sm { display:block; width:100%; border-radius:.375rem; border:1px solid #D1D5DB; background:#fff; padding:.25rem .5rem; font-size:.6875rem; color:#1E293B; outline:none; transition:border-color .15s; }
    .cfg-input-sm:focus { border-color:#818CF8; }
    .tag { display:inline-flex; align-items:center; border-radius:9999px; background:#F1F5F9; color:#475569; font-size:.6875rem; font-weight:500; padding:.125rem .5rem; }
</style>

<script>
function crudConfigApp(fields, filters, styles) {
    return {
        tab: 'cols',
        fields: fields,

        init() {
            this.initSortable();
        },

        initSortable() {
            const el = document.getElementById('cols-sortable');
            if (!el || typeof Sortable === 'undefined') return;

            Sortable.create(el, {
                animation: 150,
                handle: 'td:first-child',
                ghostClass: 'bg-indigo-50',
                onEnd: (evt) => {
                    // Monta a nova ordem de índices com base nos data-index
                    const rows = Array.from(el.querySelectorAll('tr[data-index]'));
                    const newOrder = rows.map(r => parseInt(r.getAttribute('data-index')));
                    this.$wire.reorderFields(newOrder);
                }
            });
        }
    }
}
</script>
    @endonce
</div>
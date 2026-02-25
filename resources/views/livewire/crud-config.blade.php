<div>
    {{-- ── Botão de abertura (somente admins) ────────────────────────────── --}}
    @can('admin')
        <button wire:click="openModal"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 rounded-xl bg-transparent hover:bg-amber-50 hover:text-amber-600 transition-all duration-200 focus:outline-none"
            title="Configurar CRUD">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="hidden md:inline">Config</span>
        </button>
    @endcan

    {{-- ── Modal Principal ─────────────────────────────────────────────────── --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-start justify-center pt-6 pb-4 px-4 overflow-y-auto"
            x-data="{
                tab: 'cols',
                tabs: [
                    { id: 'cols',        label: 'Colunas',           icon: '▤' },
                    { id: 'actions',     label: 'Ações',             icon: '⚡' },
                    { id: 'filters',     label: 'Filtros Custom.',   icon: '⧩' },
                    { id: 'styles',      label: 'Estilos',           icon: '🎨' },
                    { id: 'general',     label: 'Geral',             icon: '⚙' },
                    { id: 'permissions', label: 'Permissões',        icon: '🔒' },
                ]
            }">

            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeModal"></div>

            {{-- Painel --}}
            <div class="relative w-full max-w-5xl bg-white rounded-2xl shadow-2xl flex flex-col"
                 style="max-height: calc(100vh - 3rem)">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-amber-50 to-white shrink-0 rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-9 h-9 bg-amber-100 rounded-xl">
                            <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Configuração do CRUD</h2>
                            <p class="text-xs text-gray-500">{{ $model }}</p>
                        </div>
                    </div>
                    <button @click="$wire.closeModal()" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Tabs --}}
                <div class="flex items-center gap-1 px-6 pt-3 border-b border-gray-200 overflow-x-auto shrink-0">
                    <template x-for="t in tabs" :key="t.id">
                        <button @click="tab = t.id"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-t-lg transition-colors whitespace-nowrap"
                            :class="tab === t.id
                                ? 'border border-b-white border-gray-200 bg-white text-amber-600 -mb-px relative z-10'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-t-lg'">
                            <span x-text="t.icon"></span>
                            <span x-text="t.label"></span>
                        </button>
                    </template>
                </div>

                {{-- Conteúdo das tabs ─────────────────────────────────────── --}}
                <div class="flex-1 overflow-y-auto p-6">

                    {{-- ════════════════════════════════════════════════════ --}}
                    {{-- TAB: COLUNAS                                        --}}
                    {{-- ════════════════════════════════════════════════════ --}}
                    <div x-show="tab === 'cols'" x-cloak>

                        {{-- Tabela de colunas existentes --}}
                        @if (!empty($formEditFields))
                            <div class="mb-5 overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                                        <tr>
                                            <th class="px-3 py-2 text-center w-16">Ordem</th>
                                            <th class="px-3 py-2 text-left">Campo Físico</th>
                                            <th class="px-3 py-2 text-left">Nome Lógico</th>
                                            <th class="px-3 py-2 text-center">Tipo</th>
                                            <th class="px-3 py-2 text-center">Gravar</th>
                                            <th class="px-3 py-2 text-center">Filtrar</th>
                                            <th class="px-3 py-2 text-center">Total.</th>
                                            <th class="px-3 py-2 text-center w-28">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($formEditFields as $i => $field)
                                            @if (($field['colsTipo'] ?? '') !== 'action')
                                                <tr class="hover:bg-gray-50 transition-colors {{ $editingFieldIndex === $i ? 'bg-amber-50' : '' }}">
                                                    <td class="px-3 py-2 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <button wire:click="moveFieldUp({{ $i }})"
                                                                class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                                                @disabled($i === 0)>↑</button>
                                                            <span class="text-gray-400 text-xs">{{ $i + 1 }}</span>
                                                            <button wire:click="moveFieldDown({{ $i }})"
                                                                class="p-0.5 text-gray-400 hover:text-gray-600">↓</button>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono font-semibold text-gray-700">{{ $field['colsNomeFisico'] ?? '' }}</td>
                                                    <td class="px-3 py-2 text-gray-600">{{ $field['colsNomeLogico'] ?? '' }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-block px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-medium">{{ $field['colsTipo'] ?? 'text' }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-block w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center
                                                            {{ ($field['colsGravar'] ?? 'N') === 'S' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                                                            {{ ($field['colsGravar'] ?? 'N') === 'S' ? 'S' : 'N' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-block w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center
                                                            {{ ($field['colsIsFilterable'] ?? 'N') === 'S' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-400' }}">
                                                            {{ ($field['colsIsFilterable'] ?? 'N') === 'S' ? 'S' : 'N' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        @if (!empty($field['totalizadorEnabled']))
                                                            <span class="inline-block px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-xs font-medium">{{ $field['totalizadorType'] ?? 'sum' }}</span>
                                                        @else
                                                            <span class="text-gray-300">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <div class="flex items-center justify-center gap-1.5">
                                                            <button wire:click="editField({{ $i }})"
                                                                class="p-1 rounded text-blue-500 hover:bg-blue-50 transition-colors" title="Editar">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a4 4 0 01-1.414.93l-3 1 1-3a4 4 0 01.93-1.414z"/>
                                                                </svg>
                                                            </button>
                                                            <button wire:click="removeField({{ $i }})"
                                                                wire:confirm="Remover esta coluna?"
                                                                class="p-1 rounded text-red-400 hover:bg-red-50 transition-colors" title="Remover">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="mb-5 text-center py-8 text-gray-400 text-sm">Nenhuma coluna configurada.</div>
                        @endif

                        {{-- Formulário de coluna --}}
                        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">
                                {{ $editingFieldIndex >= 0 ? 'Editar Coluna' : '+ Adicionar Coluna' }}
                            </h4>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                {{-- Campo Físico --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Campo Físico *</label>
                                    <input wire:model="formDataField.colsNomeFisico" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: name">
                                </div>

                                {{-- Nome Lógico --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome Lógico *</label>
                                    <input wire:model="formDataField.colsNomeLogico" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                        placeholder="ex: Nome">
                                </div>

                                {{-- Tipo --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                                    <select wire:model="formDataField.colsTipo"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="text">text</option>
                                        <option value="number">number</option>
                                        <option value="date">date</option>
                                        <option value="datetime">datetime</option>
                                        <option value="select">select</option>
                                        <option value="searchdropdown">searchdropdown</option>
                                        <option value="boolean">boolean</option>
                                    </select>
                                </div>

                                {{-- Helper --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Helper</label>
                                    <select wire:model="formDataField.colsHelper"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="">— Nenhum —</option>
                                        <option value="dateFormat">dateFormat</option>
                                        <option value="dateTimeFormat">dateTimeFormat</option>
                                        <option value="currencyFormat">currencyFormat</option>
                                        <option value="yesOrNot">yesOrNot (S/N)</option>
                                        <option value="badge">badge</option>
                                    </select>
                                </div>

                                {{-- Alinhamento --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Alinhamento</label>
                                    <select wire:model="formDataField.colsAlign"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="text-start">Esquerda</option>
                                        <option value="text-center">Centro</option>
                                        <option value="text-end">Direita</option>
                                    </select>
                                </div>

                                {{-- Gravar --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Gravar no form</label>
                                    <select wire:model="formDataField.colsGravar"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="S">Sim</option>
                                        <option value="N">Não</option>
                                    </select>
                                </div>

                                {{-- Required --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Obrigatório</label>
                                    <select wire:model="formDataField.colsRequired"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="N">Não</option>
                                        <option value="S">Sim</option>
                                    </select>
                                </div>

                                {{-- Filtrável --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Filtrável</label>
                                    <select wire:model="formDataField.colsIsFilterable"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="S">Sim</option>
                                        <option value="N">Não</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Campos condicionais --}}
                            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                                {{-- colsSelect (tipo select) --}}
                                @if (($formDataField['colsTipo'] ?? '') === 'select')
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Opções do Select</label>
                                        <input wire:model="formDataField.colsSelect" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                            placeholder="chave;Rótulo;;chave2;Rótulo 2">
                                        <p class="text-xs text-gray-400 mt-0.5">Formato: <code>chave;Rótulo;;chave2;Rótulo 2</code></p>
                                    </div>
                                @endif

                                {{-- SearchDropdown --}}
                                @if (($formDataField['colsTipo'] ?? '') === 'searchdropdown')
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Modelo (SD)</label>
                                        <input wire:model="formDataField.colsSDModel" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                            placeholder="ex: App\Models\Supplier">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Relação (SD)</label>
                                        <input wire:model="formDataField.colsRelacao" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                            placeholder="ex: supplier">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Campo exibido (SD)</label>
                                        <input wire:model="formDataField.colsRelacaoExibe" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                            placeholder="ex: name">
                                    </div>
                                @endif

                                {{-- Relação (outros tipos) --}}
                                @if (!in_array($formDataField['colsTipo'] ?? '', ['select', 'searchdropdown', 'action']))
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Relação (opcional)</label>
                                        <input wire:model="formDataField.colsRelacao" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                            placeholder="ex: category">
                                        <p class="text-xs text-gray-400 mt-0.5">Se o campo vem de relacionamento.</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Campo exibido da relação</label>
                                        <input wire:model="formDataField.colsRelacaoExibe" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                            placeholder="ex: name">
                                    </div>
                                @endif
                            </div>

                            {{-- Totalizador --}}
                            <div class="mt-3 border-t border-gray-200 pt-3"
                                x-data="{ totEnabled: {{ !empty($formDataField['totalizadorEnabled']) ? 'true' : 'false' }} }">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox"
                                        wire:model="formDataField.totalizadorEnabled"
                                        @change="totEnabled = $event.target.checked"
                                        class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-400">
                                    <span class="text-xs font-medium text-gray-700">Habilitar Totalizador nesta coluna</span>
                                </label>

                                <div x-show="totEnabled" x-cloak class="mt-2 grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Agregação</label>
                                        <select wire:model="formDataField.totalizadorType"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                            <option value="sum">Soma (SUM)</option>
                                            <option value="avg">Média (AVG)</option>
                                            <option value="count">Contagem (COUNT)</option>
                                            <option value="min">Mínimo (MIN)</option>
                                            <option value="max">Máximo (MAX)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Formato</label>
                                        <select wire:model="formDataField.totalizadorFormat"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                            <option value="number">Número</option>
                                            <option value="currency">Moeda (R$)</option>
                                            <option value="integer">Inteiro</option>
                                            <option value="decimal">Decimal</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Rótulo</label>
                                        <input wire:model="formDataField.totalizadorLabel" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                            placeholder="ex: Total">
                                    </div>
                                </div>
                            </div>

                            {{-- Botões do formulário --}}
                            <div class="mt-3 flex items-center gap-2">
                                @if ($editingFieldIndex >= 0)
                                    <button wire:click="updateField"
                                        class="px-4 py-1.5 text-xs font-semibold bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors">
                                        Atualizar Coluna
                                    </button>
                                    <button wire:click="cancelEditField"
                                        class="px-4 py-1.5 text-xs font-semibold text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                                        Cancelar
                                    </button>
                                @else
                                    <button wire:click="addField"
                                        class="px-4 py-1.5 text-xs font-semibold bg-primary text-white rounded-lg hover:opacity-90 transition-opacity">
                                        + Adicionar Coluna
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ════════════════════════════════════════════════════ --}}
                    {{-- TAB: AÇÕES                                          --}}
                    {{-- ════════════════════════════════════════════════════ --}}
                    <div x-show="tab === 'actions'" x-cloak>

                        {{-- Lista de ações existentes --}}
                        @php
                            $actions = collect($formEditFields)->filter(fn($f) => ($f['colsTipo'] ?? '') === 'action');
                        @endphp

                        @if ($actions->isNotEmpty())
                            <div class="mb-5 overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Nome</th>
                                            <th class="px-3 py-2 text-center">Tipo</th>
                                            <th class="px-3 py-2 text-left">Valor / Método</th>
                                            <th class="px-3 py-2 text-center">Ícone</th>
                                            <th class="px-3 py-2 text-center">Cor</th>
                                            <th class="px-3 py-2 text-left">Permissão</th>
                                            <th class="px-3 py-2 text-center w-16">—</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($formEditFields as $i => $field)
                                            @if (($field['colsTipo'] ?? '') === 'action')
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-3 py-2 font-semibold text-gray-700">{{ $field['colsNomeLogico'] ?? '' }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                                            {{ ($field['actionType'] ?? '') === 'livewire' ? 'bg-purple-100 text-purple-700' : (($field['actionType'] ?? '') === 'javascript' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700') }}">
                                                            {{ $field['actionType'] ?? 'link' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-gray-600 max-w-xs truncate">{{ $field['actionValue'] ?? '' }}</td>
                                                    <td class="px-3 py-2 text-center font-mono text-gray-500">{{ $field['actionIcon'] ?? '' }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $field['actionColor'] ?? 'primary' }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-500 max-w-xs truncate">{{ $field['actionPermission'] ?? '—' }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <button wire:click="removeAction({{ $i }})"
                                                            wire:confirm="Remover esta ação?"
                                                            class="p-1 rounded text-red-400 hover:bg-red-50 transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="mb-5 text-center py-8 text-gray-400 text-sm">Nenhuma ação configurada.</div>
                        @endif

                        {{-- Formulário nova ação --}}
                        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">+ Adicionar Ação</h4>

                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome (Rótulo) *</label>
                                    <input wire:model="formDataAction.colsNomeLogico" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                        placeholder="ex: Aprovar">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo *</label>
                                    <select wire:model="formDataAction.actionType"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="link">Link (URL)</option>
                                        <option value="livewire">Livewire (método)</option>
                                        <option value="javascript">JavaScript</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Valor / Método *</label>
                                    <input wire:model="formDataAction.actionValue" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: /orders/%id%/approve">
                                    <p class="mt-0.5 text-xs text-gray-400">Use <code>%id%</code> como placeholder do ID</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Ícone (classe CSS)</label>
                                    <input wire:model="formDataAction.actionIcon" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: bx bx-check">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Cor</label>
                                    <select wire:model="formDataAction.actionColor"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="primary">primary</option>
                                        <option value="success">success</option>
                                        <option value="danger">danger</option>
                                        <option value="warning">warning</option>
                                        <option value="info">info</option>
                                        <option value="secondary">secondary</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Permissão (Gate)</label>
                                    <input wire:model="formDataAction.actionPermission" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: approve-orders">
                                    <p class="mt-0.5 text-xs text-gray-400">Deixe vazio para mostrar a todos.</p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button wire:click="addAction"
                                    class="px-4 py-1.5 text-xs font-semibold bg-primary text-white rounded-lg hover:opacity-90 transition-opacity">
                                    + Adicionar Ação
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ════════════════════════════════════════════════════ --}}
                    {{-- TAB: FILTROS PERSONALIZADOS                         --}}
                    {{-- ════════════════════════════════════════════════════ --}}
                    <div x-show="tab === 'filters'" x-cloak>

                        @if (!empty($customFilters))
                            <div class="mb-5 space-y-2">
                                @foreach ($customFilters as $i => $filter)
                                    <div class="flex items-start gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                        <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                            <div><span class="text-gray-400">Campo:</span> <span class="font-medium font-mono">{{ $filter['field'] ?? '' }}</span></div>
                                            <div><span class="text-gray-400">Label:</span> <span class="font-medium">{{ $filter['label'] ?? '' }}</span></div>
                                            <div><span class="text-gray-400">Tipo:</span> <span class="font-medium">{{ $filter['colsFilterType'] ?? 'text' }}</span></div>
                                            @if (!empty($filter['whereHas']))
                                                <div><span class="text-gray-400">whereHas:</span> <span class="font-mono font-medium">{{ $filter['whereHas'] }}</span></div>
                                            @endif
                                            @if (!empty($filter['aggregate']))
                                                <div><span class="text-gray-400">Aggregate:</span> <span class="font-medium uppercase">{{ $filter['aggregate'] }}</span></div>
                                            @endif
                                            @if (!empty($filter['defaultOperator']))
                                                <div><span class="text-gray-400">Operador:</span> <span class="font-mono font-medium">{{ $filter['defaultOperator'] }}</span></div>
                                            @endif
                                        </div>
                                        <button wire:click="removeCustomFilter({{ $i }})"
                                            wire:confirm="Remover este filtro?"
                                            class="p-1 rounded text-red-400 hover:bg-red-50 transition-colors shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mb-5 text-center py-8 text-gray-400 text-sm">Nenhum filtro personalizado.</div>
                        @endif

                        {{-- Formulário novo filtro --}}
                        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">+ Adicionar Filtro Personalizado</h4>

                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Campo Físico *</label>
                                    <input wire:model="formDataFilter.field" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: supplier_id">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
                                    <input wire:model="formDataFilter.label" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                        placeholder="ex: Fornecedor">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo do Filtro</label>
                                    <select wire:model="formDataFilter.colsFilterType"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="text">text</option>
                                        <option value="number">number</option>
                                        <option value="select">select</option>
                                        <option value="searchdropdown">searchdropdown</option>
                                        <option value="date">date</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">whereHas (relação)</label>
                                    <input wire:model="formDataFilter.whereHas" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: supplier">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Campo da Relação</label>
                                    <input wire:model="formDataFilter.field_relation" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: name">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Aggregate</label>
                                    <select wire:model="formDataFilter.aggregate"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="">— Nenhum —</option>
                                        <option value="SUM">SUM</option>
                                        <option value="COUNT">COUNT</option>
                                        <option value="AVG">AVG</option>
                                        <option value="MAX">MAX</option>
                                        <option value="MIN">MIN</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Operador padrão</label>
                                    <select wire:model="formDataFilter.defaultOperator"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="=">= (igual)</option>
                                        <option value=">=">≥ (maior ou igual)</option>
                                        <option value=">"> > (maior)</option>
                                        <option value="<=">≤ (menor ou igual)</option>
                                        <option value="<"> &lt; (menor)</option>
                                        <option value="LIKE">LIKE (contém)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button wire:click="addCustomFilter"
                                    class="px-4 py-1.5 text-xs font-semibold bg-primary text-white rounded-lg hover:opacity-90 transition-opacity">
                                    + Adicionar Filtro
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ════════════════════════════════════════════════════ --}}
                    {{-- TAB: ESTILOS CONDICIONAIS                           --}}
                    {{-- ════════════════════════════════════════════════════ --}}
                    <div x-show="tab === 'styles'" x-cloak>

                        <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
                            <strong>Como funciona:</strong> Define estilos CSS aplicados em linhas onde a condição for verdadeira.
                            A <strong>primeira regra que corresponder</strong> será usada (a ordem importa).
                        </div>

                        @if (!empty($conditionStyles))
                            <div class="mb-5 overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                                        <tr>
                                            <th class="px-3 py-2 text-center w-8">#</th>
                                            <th class="px-3 py-2 text-left">Campo</th>
                                            <th class="px-3 py-2 text-center">Operador</th>
                                            <th class="px-3 py-2 text-left">Valor</th>
                                            <th class="px-3 py-2 text-left">Estilo CSS</th>
                                            <th class="px-3 py-2 text-center">Preview</th>
                                            <th class="px-3 py-2 text-center w-12">—</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($conditionStyles as $i => $style)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-center text-gray-400">{{ $i + 1 }}</td>
                                                <td class="px-3 py-2 font-mono font-semibold text-gray-700">{{ $style['field'] ?? '' }}</td>
                                                <td class="px-3 py-2 text-center"><code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $style['operator'] ?? '=' }}</code></td>
                                                <td class="px-3 py-2 font-mono text-gray-600">{{ $style['value'] ?? '' }}</td>
                                                <td class="px-3 py-2 font-mono text-xs text-gray-500 max-w-xs truncate">{{ $style['style'] ?? '' }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="inline-block px-3 py-1 rounded text-xs font-medium" style="{{ $style['style'] ?? '' }}">
                                                        Prévia
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <button wire:click="removeConditionStyle({{ $i }})"
                                                        wire:confirm="Remover este estilo?"
                                                        class="p-1 rounded text-red-400 hover:bg-red-50 transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="mb-5 text-center py-8 text-gray-400 text-sm">Nenhum estilo condicional configurado.</div>
                        @endif

                        {{-- Formulário novo estilo --}}
                        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">+ Adicionar Estilo Condicional</h4>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Campo *</label>
                                    <input wire:model="formDataStyle.field" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: status">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Operador</label>
                                    <select wire:model="formDataStyle.operator"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40">
                                        <option value="=">=</option>
                                        <option value="!=">!=</option>
                                        <option value=">">></option>
                                        <option value=">=">&gt;=</option>
                                        <option value="<">&lt;</option>
                                        <option value="<=">&lt;=</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Valor *</label>
                                    <input wire:model="formDataStyle.value" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                        placeholder="ex: canceled">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Estilo CSS *</label>
                                    <input wire:model="formDataStyle.style" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400/40 font-mono"
                                        placeholder="ex: color:#999; text-decoration:line-through;">
                                </div>
                            </div>

                            {{-- Exemplos rápidos --}}
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="text-xs text-gray-400">Exemplos:</span>
                                <button type="button"
                                    wire:click="$set('formDataStyle.style', 'color:#999; text-decoration:line-through;')"
                                    class="text-xs px-2 py-0.5 bg-gray-100 rounded hover:bg-gray-200 transition-colors">Riscado</button>
                                <button type="button"
                                    wire:click="$set('formDataStyle.style', 'background-color:#FFF3CD; font-weight:bold;')"
                                    class="text-xs px-2 py-0.5 bg-yellow-50 rounded hover:bg-yellow-100 transition-colors">Amarelo</button>
                                <button type="button"
                                    wire:click="$set('formDataStyle.style', 'background-color:#D4EDDA; color:#155724;')"
                                    class="text-xs px-2 py-0.5 bg-green-50 rounded hover:bg-green-100 transition-colors">Verde</button>
                                <button type="button"
                                    wire:click="$set('formDataStyle.style', 'background-color:#F8D7DA; color:#721C24; font-weight:bold;')"
                                    class="text-xs px-2 py-0.5 bg-red-50 rounded hover:bg-red-100 transition-colors">Vermelho</button>
                            </div>

                            <div class="mt-3">
                                <button wire:click="addConditionStyle"
                                    class="px-4 py-1.5 text-xs font-semibold bg-primary text-white rounded-lg hover:opacity-90 transition-opacity">
                                    + Adicionar Estilo
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ════════════════════════════════════════════════════ --}}
                    {{-- TAB: GERAL                                          --}}
                    {{-- ════════════════════════════════════════════════════ --}}
                    <div x-show="tab === 'general'" x-cloak class="space-y-5">

                        {{-- Links e Classes --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 flex items-center justify-center bg-blue-100 rounded text-blue-600 text-xs">🔗</span>
                                Links e Aparência
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Link da Linha (configLinkLinha)</label>
                                    <input wire:model="configLinkLinha" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40 font-mono"
                                        placeholder="ex: /products/%id%">
                                    <p class="mt-0.5 text-xs text-gray-400">Deixe vazio para desabilitar clique na linha.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Classe da Tabela</label>
                                    <input wire:model="tableClass" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40 font-mono"
                                        placeholder="ex: table table-hover table-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Classe do Cabeçalho (thead)</label>
                                    <input wire:model="theadClass" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40 font-mono"
                                        placeholder="ex: bg-dark text-white">
                                </div>
                            </div>
                        </div>

                        {{-- Cache --}}
                        <div class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 flex items-center justify-center bg-purple-100 rounded text-purple-600 text-xs">⚡</span>
                                Cache
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 items-start">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="cacheEnabled" id="cacheEnabled"
                                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <label for="cacheEnabled" class="text-xs font-medium text-gray-700 cursor-pointer">Habilitar cache</label>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">TTL (segundos)</label>
                                    <input wire:model="cacheTtl" type="number" min="0"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40"
                                        placeholder="300">
                                </div>
                            </div>
                        </div>

                        {{-- Exportação --}}
                        <div class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 flex items-center justify-center bg-green-100 rounded text-green-600 text-xs">📥</span>
                                Exportação
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Limite para exportação assíncrona</label>
                                    <input wire:model="exportAsyncThreshold" type="number" min="0"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40"
                                        placeholder="1000">
                                    <p class="mt-0.5 text-xs text-gray-400">Acima deste nº de registros, exporta em background.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Máximo de linhas</label>
                                    <input wire:model="exportMaxRows" type="number" min="0"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40"
                                        placeholder="10000">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Orientação do PDF</label>
                                    <select wire:model="exportOrientation"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        <option value="landscape">Paisagem</option>
                                        <option value="portrait">Retrato</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- UI Preferences --}}
                        <div class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 flex items-center justify-center bg-amber-100 rounded text-amber-600 text-xs">🎨</span>
                                Preferências de Interface
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="uiCompactMode" id="uiCompact"
                                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <label for="uiCompact" class="text-xs font-medium text-gray-700 cursor-pointer">Modo Compacto padrão</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="uiStickyHeader" id="uiSticky"
                                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <label for="uiSticky" class="text-xs font-medium text-gray-700 cursor-pointer">Header fixo (sticky)</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="showTotalizador" id="showTot"
                                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <label for="showTot" class="text-xs font-medium text-gray-700 cursor-pointer">Exibir totalizadores</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ════════════════════════════════════════════════════ --}}
                    {{-- TAB: PERMISSÕES                                     --}}
                    {{-- ════════════════════════════════════════════════════ --}}
                    <div x-show="tab === 'permissions'" x-cloak class="space-y-5">

                        {{-- Gates individuais --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Gates de Permissão</h4>
                            <p class="text-xs text-gray-500 mb-3">
                                Informe o nome do Gate/Ability do Laravel para cada ação.
                                Deixe vazio para permitir a todos os usuários autenticados.
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach (['Create' => 'Criar', 'Edit' => 'Editar', 'Delete' => 'Excluir', 'Export' => 'Exportar', 'Restore' => 'Restaurar'] as $action => $label)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }} ({{ strtolower($action) }})</label>
                                        <input wire:model="permission{{ $action }}" type="text"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40 font-mono"
                                            placeholder="ex: create-products">
                                    </div>
                                @endforeach

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Identificador da Página</label>
                                    <input wire:model="permissionIdentifier" type="text"
                                        class="w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/40 font-mono"
                                        placeholder="ex: pageProducts">
                                </div>
                            </div>
                        </div>

                        {{-- Visibilidade de botões --}}
                        <div class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Visibilidade de Botões</h4>
                            <p class="text-xs text-gray-500 mb-3">Controla quais botões aparecem na interface (independente de gates).</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @foreach (['showCreateButton' => 'Botão Novo', 'showEditButton' => 'Botão Editar', 'showDeleteButton' => 'Botão Excluir', 'showTrashButton' => 'Botão Lixeira'] as $prop => $label)
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="{{ $prop }}" id="{{ $prop }}"
                                            class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/40">
                                        <label for="{{ $prop }}" class="text-xs font-medium text-gray-700 cursor-pointer">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>
                {{-- /Conteúdo das tabs --}}

                {{-- Footer --}}
                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50/80 rounded-b-2xl shrink-0">
                    <p class="text-xs text-gray-400">
                        As alterações são salvas na tabela <code>crud_configs</code> e o cache é invalidado automaticamente.
                    </p>
                    <div class="flex items-center gap-2">
                        <button wire:click="closeModal"
                            class="px-4 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button wire:click="save"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-60 cursor-not-allowed"
                            class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-bold bg-amber-500 text-white rounded-xl hover:bg-amber-600 transition-colors shadow-sm">
                            <svg wire:loading wire:target="save" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            <svg wire:loading.remove wire:target="save" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Salvar Configuração
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>

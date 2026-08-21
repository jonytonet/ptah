{{-- ptah::livewire.permission.role-list --}}
<div>
    <x-forge-page-header
        :title="__('ptah::ui.role_title')"
        :subtitle="__('ptah::ui.role_subtitle')"
    />

    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border rounded-md">
        <x-forge-button wire:click="create" color="primary" size="sm">
            <x-slot name="icon">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </x-slot>
            {{ __('ptah::ui.role_new_btn') }}
        </x-forge-button>
        <div class="flex-1 min-w-[180px] max-w-xs">
            <x-forge-input
                wire:model.live.debounce.300ms="search"
                type="search"
                :placeholder="__('ptah::ui.role_search_ph')"
                iconBefore='<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/></svg>'
            />
        </div>
    </div>

    <div class="ptah-module-table overflow-x-auto border rounded-md">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.role_col_name') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.role_col_department') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.role_col_permissions') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.role_col_status') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.role_col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="transition-colors hover:bg-slate-50/70 {{ $row->is_master ? 'ptah-c-mod_master_row' : '' }}">
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                @if ($row->color)
                                    <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $row->color }}"></span>
                                @endif
                                <span class="font-medium text-slate-800">{{ $row->name }}</span>
                                @if ($row->is_master)
                                    <span class="text-xs font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">👑 MASTER</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-slate-500">{{ $row->department?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">{{ __('ptah::ui.role_objects_count', ['count' => $row->permissions_count]) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                {{ $row->is_active ? __('ptah::ui.lbl_active') : __('ptah::ui.lbl_inactive') }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openBind({{ $row->id }})"
                                    class="ptah-c-mod_btn_soft inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md transition-colors"
                                    title="{{ __('ptah::ui.role_manage_perms_title') }}">
                                    {{ __('ptah::ui.role_manage_perms_btn') }}
                                </button>
                                <button wire:click="edit({{ $row->id }})" class="transition-colors text-primary hover:text-primary/80" title="{{ __('ptah::ui.btn_edit_title') }}" aria-label="{{ __('ptah::ui.btn_edit_title') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @if (!$row->is_master)
                                    <button wire:click="confirmDelete({{ $row->id }})" class="transition-colors text-danger hover:text-danger/80" title="{{ __('ptah::ui.btn_delete_title') }}" aria-label="{{ __('ptah::ui.btn_delete_title') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-md bg-slate-100">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">{{ __('ptah::ui.role_empty_found') }}</p>
                                    <p class="text-xs mt-0.5 text-slate-400">{{ __('ptah::ui.role_empty_hint') }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rows->hasPages())
        <div class="flex items-center justify-between mt-4 text-sm ptah-c-pag">
            <span>{{ __('ptah::ui.company_pagination', ['first' => $rows->firstItem(), 'last' => $rows->lastItem(), 'total' => $rows->total()]) }}</span>
            <div>{{ $rows->links('ptah::components.forge-pagination') }}</div>
        </div>
    @endif

    {{-- Modal criar/editar role --}}
    <div x-data="{ open: @entangle('showModal') }">
        <x-forge-modal :title="$isEditing ? __('ptah::ui.role_form_title_edit') : __('ptah::ui.role_new_btn')" size="md">
            <div class="ptah-c-mod_modal space-y-4">
                <x-forge-input :label="__('ptah::ui.role_form_name')" wire:model="name" :error="$errors->first('name')" required />
                <x-forge-textarea :label="__('ptah::ui.role_form_desc')" wire:model="description" rows="2" />
                <div class="grid grid-cols-2 gap-4">
                    <x-forge-input :label="__('ptah::ui.role_form_color')" wire:model="color" placeholder="#6b7280" type="color" />
                    <x-forge-select :label="__('ptah::ui.role_form_dept')" wire:model="department_id"
                        :options="$departments->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->prepend(['value'=>'','label'=>__('ptah::ui.role_form_no_dept')])->toArray()" />
                </div>
                <div class="flex items-center gap-6 pt-1">
                    <x-forge-switch wire:model="is_active" :label="__('ptah::ui.role_form_active')" />
                    @if (!($editingId && \Ptah\Models\Role::find($editingId)?->is_master))
                        <x-forge-switch wire:model="is_master" :label="__('ptah::ui.role_form_master')" />
                    @else
                        <span class="text-xs text-amber-600 font-medium">{{ __('ptah::ui.role_form_is_master_badge') }}</span>
                    @endif
                </div>
                @if ($is_master)
                    <x-forge-alert type="warn">{{ __('ptah::ui.role_form_master_warn') }}</x-forge-alert>
                @endif
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">{{ __('ptah::ui.btn_cancel') }}</x-forge-button>
                <x-forge-button wire:click="save" color="primary">{{ __('ptah::ui.btn_save') }}</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal de bind de permissões --}}
    @php
        // Presentation-only grouping/search-index for the accordion + client-side
        // filter below (FIX 2/FIX 3, Onda A UX-ACL) — saveBind() and the shape of
        // $bindObjects itself are untouched, this only reads the array to render it
        // grouped instead of flat. Quotes/backslashes are stripped (not escaped)
        // from the search index on purpose: it is embedded inside a single-quoted
        // Alpine JS string literal below, and stripping avoids re-introducing the
        // exact "stray quote breaks the attribute" failure mode LayoutXDataQuotingTest
        // guards against elsewhere, without needing per-character JS escaping.
        $bindSearchOf = fn (array $o): string => mb_strtolower(str_replace(["'", '"', '\\'], ' ', $o['page_name'].' '.$o['obj_label'].' '.$o['obj_key']));
        $bindGroups = collect($bindObjects)
            ->map(function (array $obj, int $i) use ($bindSearchOf): array {
                $obj['__idx'] = $i;
                $obj['__search'] = $bindSearchOf($obj);

                return $obj;
            })
            ->groupBy('page_name');
        // Sempre colapsado ao abrir (pedido do usuario): o resumo N/M no cabecalho
        // de cada grupo da a visao geral sem precisar expandir nada.
        $bindExpandByDefault = false;
        $bindFullBlob = $bindGroups->map(fn ($group, $page) => mb_strtolower(str_replace(["'", '"', '\\'], ' ', (string) $page)).' '.$group->pluck('__search')->implode(' '))->implode(' ||| ');
    @endphp
    <div x-data="{ open: @entangle('showBindModal') }">
        <x-forge-modal :title="__('ptah::ui.role_bind_modal_prefix') . ' ' . $bindingRoleName" size="xl">
            <div class="ptah-c-mod_modal" x-data="{ filterText: '' }">
                @if (! empty($bindObjects))
                    <div class="relative mb-3">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                        </svg>
                        <input
                            type="search"
                            x-model.debounce.150ms="filterText"
                            class="ptah-c-search w-full py-2 pl-9 pr-4 text-sm rounded border outline-none transition-all"
                            placeholder="{{ __('ptah::ui.role_bind_filter_ph') }}"
                            aria-label="{{ __('ptah::ui.role_bind_filter_aria') }}"
                        />
                    </div>
                @endif
                <div class="space-y-2 max-h-[60vh] overflow-y-auto">
                    @foreach ($bindGroups as $pageName => $group)
                        @php
                            $groupIdx = $loop->index;
                            $groupBlob = $group->pluck('__search')->implode(' ||| ');
                            $groupTotal = $group->count() * 4;
                            $groupChecked = $group->sum(fn (array $o) => (int) ($o['can_read'] ?? false) + (int) ($o['can_create'] ?? false) + (int) ($o['can_update'] ?? false) + (int) ($o['can_delete'] ?? false));
                            $section = $group->first()['section'] ?? '';
                        @endphp
                        <div
                            wire:key="ptah-bind-group-{{ md5($pageName) }}"
                            x-data="{ manualOpen: {{ $bindExpandByDefault ? 'true' : 'false' }}, checkedCount: {{ $groupChecked }} }"
                            x-show="filterText.trim() === '' || '{{ $groupBlob }}'.includes(filterText.trim().toLowerCase())"
                        >
                            {{-- Enquanto o filtro esta ativo os grupos com match ficam forcados
                                 abertos; alternar manualOpen nesse estado nao teria feedback
                                 visual nenhum e mudaria o estado que reaparece ao limpar o
                                 filtro — por isso o clique so alterna com o filtro vazio. --}}
                            <button
                                type="button"
                                class="ptah-c-acc_hd w-full flex items-center gap-2 px-3 py-1.5 text-xs font-bold uppercase tracking-wider rounded transition-colors"
                                @click="if (filterText.trim() === '') manualOpen = !manualOpen"
                                :aria-expanded="filterText.trim() === '' ? manualOpen : true"
                                aria-controls="ptah-bind-group-{{ $groupIdx }}"
                            >
                                <svg class="ptah-c-acc_chevron w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="truncate">📄 {{ $pageName }} — {{ $section }}</span>
                                <span class="ml-auto ptah-c-modal_sub font-normal normal-case tracking-normal shrink-0" x-text="checkedCount + '/{{ $groupTotal }}'"></span>
                            </button>
                            <div id="ptah-bind-group-{{ $groupIdx }}" x-show="filterText.trim() === '' ? manualOpen : true" class="space-y-1 pt-1">
                                @foreach ($group as $obj)
                                    @php $i = $obj['__idx']; @endphp
                                    <div
                                        wire:key="ptah-bind-obj-{{ $i }}"
                                        class="ptah-c-mod_obj_row flex items-center gap-3 px-3 py-2 rounded-md border"
                                        data-ptah-search="{{ $obj['__search'] }}"
                                        x-show="filterText.trim() === '' || $el.dataset.ptahSearch.includes(filterText.trim().toLowerCase())"
                                    >
                                        <div class="flex-1 min-w-0">
                                            <p class="ptah-c-mod_obj_ttl text-sm font-medium truncate">{{ $obj['obj_label'] }}</p>
                                            <p class="text-xs text-slate-400 font-mono">{{ $obj['obj_key'] }} <span class="ml-1 ptah-c-mod_obj_type">· {{ $obj['obj_type'] }}</span></p>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                                <span class="text-xs text-slate-400">{{ __('ptah::ui.role_bind_perm_read') }}</span>
                                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_read" @change="checkedCount += $event.target.checked ? 1 : -1" class="rounded" />
                                            </label>
                                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                                <span class="text-xs text-slate-400">{{ __('ptah::ui.role_bind_perm_create') }}</span>
                                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_create" @change="checkedCount += $event.target.checked ? 1 : -1" class="rounded" />
                                            </label>
                                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                                <span class="text-xs text-slate-400">{{ __('ptah::ui.role_bind_perm_edit') }}</span>
                                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_update" @change="checkedCount += $event.target.checked ? 1 : -1" class="rounded" />
                                            </label>
                                            <label class="flex flex-col items-center gap-0.5 cursor-pointer">
                                                <span class="text-xs text-slate-400">{{ __('ptah::ui.role_bind_perm_delete') }}</span>
                                                <input type="checkbox" wire:model="bindObjects.{{ $i }}.can_delete" @change="checkedCount += $event.target.checked ? 1 : -1" class="rounded" />
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @if (empty($bindObjects))
                        <div class="py-8 text-center text-slate-400 text-sm">{{ __('ptah::ui.role_bind_empty') }}</div>
                    @else
                        <div
                            x-show="filterText.trim() !== '' && !('{{ $bindFullBlob }}'.includes(filterText.trim().toLowerCase()))"
                            class="py-8 text-center text-slate-400 text-sm"
                        >
                            {{ __('ptah::ui.role_bind_filter_empty') }}
                        </div>
                    @endif
                </div>
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">{{ __('ptah::ui.btn_cancel') }}</x-forge-button>
                <x-forge-button wire:click="saveBind" color="primary">{{ __('ptah::ui.role_bind_save') }}</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>

    {{-- Modal exclusão --}}
    <div x-data="{ open: @entangle('showDeleteModal') }">
        <x-forge-modal :title="__('ptah::ui.delete_title')" size="sm">
            <p class="text-slate-600">{{ __('ptah::ui.role_delete_text') }}</p>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">{{ __('ptah::ui.btn_cancel') }}</x-forge-button>
                <x-forge-button wire:click="delete" color="danger">{{ __('ptah::ui.btn_delete') }}</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>



{{-- ptah::livewire.permission.user-permission-list --}}
<div>
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800 ptah-page-title">{{ __('ptah::ui.user_perm_title') }}</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ __('ptah::ui.user_perm_subtitle') }}</p>
    </div>

    @if ($successMsg) <x-forge-alert type="success" class="mb-3">{{ $successMsg }}</x-forge-alert> @endif
    @if ($errorMsg)   <x-forge-alert type="danger"  class="mb-3">{{ $errorMsg }}</x-forge-alert>   @endif

    <div class="ptah-module-toolbar flex flex-wrap items-center gap-2 px-4 py-3 mb-4 border shadow-sm rounded-xl bg-white border-slate-200">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" :placeholder="__('ptah::ui.user_perm_search_ph')"
                    class="w-full py-2 pl-9 pr-4 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all"/>
            </div>
        </div>
        <select wire:model.live="filterRole"
            class="py-2 px-3 text-sm rounded-lg border border-slate-200 bg-slate-50/60 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none transition-all">
            <option value="0">{{ __('ptah::ui.user_perm_all_roles') }}</option>
            @foreach ($roles as $r)
                <option value="{{ $r->id }}">{{ $r->is_master ? '👑 ' : '' }}{{ $r->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="ptah-module-table overflow-x-auto border shadow-sm border-slate-200 rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.user_perm_col_user') }}</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.user_perm_col_roles') }}</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('ptah::ui.user_perm_col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $user)
                    <tr class="transition-colors hover:bg-slate-50/70">
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-400 to-blue-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            @php
                                $userRoles = \Ptah\Models\UserRole::with(['role','company'])
                                    ->where('user_id', $user->id)
                                    ->active()
                                    ->get();
                            @endphp
                            <div class="flex flex-wrap gap-1">
                                @forelse ($userRoles as $ur)
                                    <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full {{ $ur->role?->is_master ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-600' }}">
                                        {{ $ur->role?->is_master ? '👑 ' : '' }}{{ $ur->role?->name }}
                                        @if ($ur->company)
                                            <span class="ml-1 text-slate-400">· {{ $ur->company->name }}</span>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400">{{ __('ptah::ui.user_perm_no_roles') }}</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                            <button wire:click="openUserModal({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-md transition-colors">
                                {{ __('ptah::ui.user_perm_manage_btn') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">{{ __('ptah::ui.user_perm_empty') }}</p>
                                    <p class="text-xs mt-0.5 text-slate-400">{{ __('ptah::ui.user_perm_empty_hint') }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rows->hasPages())
    <div class="flex items-center justify-between mt-4 text-sm text-slate-500">
        <span>{{ __('ptah::ui.company_pagination', ['first' => $rows->firstItem(), 'last' => $rows->lastItem(), 'total' => $rows->total()]) }}</span>
        <div>{{ $rows->links('ptah::components.forge-pagination') }}</div>
    </div>
    @endif

    {{-- Modal de gestão de roles do usuário --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal :title="__('ptah::ui.user_perm_modal_prefix') . ' ' . $bindingUserName" size="lg">
            <div class="space-y-5">
                {{-- Roles atuais --}}
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 mb-2">{{ __('ptah::ui.user_perm_assigned_roles') }}</h3>
                    @if ($assignedRoles)
                        <div class="space-y-1">
                            @foreach ($assignedRoles as $ar)
                                <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-50 border border-slate-200">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium {{ $ar['role_master'] ? 'text-amber-600' : 'text-slate-800' }}">
                                            {{ $ar['role_master'] ? '👑 ' : '' }}{{ $ar['role_name'] }}
                                        </span>
                                        <span class="text-xs text-slate-400">·</span>
                                        <span class="text-xs text-slate-500">{{ $ar['company_name'] }}</span>
                                    </div>
                                    @if (!$ar['role_master'])
                                        <button wire:click="removeRole({{ $ar['id'] }})" class="text-red-400 hover:text-red-600 text-xs font-medium transition-colors">
                                            {{ __('ptah::ui.user_perm_remove_btn') }}
                                        </button>
                                    @else
                                        <span class="text-xs text-amber-500">{{ __('ptah::ui.user_perm_protected') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-400 italic">{{ __('ptah::ui.user_perm_no_assigned') }}</p>
                    @endif
                </div>

                {{-- Adicionar novo --}}
                <div class="border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('ptah::ui.user_perm_add_role') }}</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <x-forge-select
                            label="Role"
                            wire:model="newRoleId"
                            :options="$roles->map(fn($r)=>['value'=>$r->id,'label'=>($r->is_master?'👑 ':''). $r->name])->prepend(['value'=>0,'label'=>'Selecione...'])->toArray()"
                        />
                        <x-forge-select
                            :label="__('ptah::ui.user_perm_company_label')"
                            wire:model="newCompanyId"
                            :options="$companies->map(fn($c)=>['value'=>$c->id,'label'=>$c->name])->prepend(['value'=>0,'label'=>__('ptah::ui.user_perm_global')])->toArray()"
                        />
                    </div>
                    <div class="mt-3">
                        <x-forge-button wire:click="addRole" color="primary" size="sm">{{ __('ptah::ui.user_perm_add_btn') }}</x-forge-button>
                    </div>
                </div>
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">{{ __('ptah::ui.user_perm_close_btn') }}</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

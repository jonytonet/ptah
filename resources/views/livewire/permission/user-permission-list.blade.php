{{-- ptah::livewire.permission.user-permission-list --}}
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Usuários — Controle de Acesso</h1>
        <p class="text-sm text-gray-500 mt-1">Atribua roles e empresas aos usuários do sistema.</p>
    </div>

    @if ($successMsg)
        <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
    @endif
    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <x-forge-input wire:model.live.debounce.300ms="search" placeholder="Buscar por nome ou e-mail..." class="w-full sm:max-w-xs" />
        <x-forge-select
            wire:model.live="filterRole"
            :options="$roles->map(fn($r)=>['value'=>$r->id,'label'=>($r->is_master?'👑 ':''). $r->name])->prepend(['value'=>0,'label'=>'Todos os roles'])->toArray()"
            class="sm:w-48"
        />
    </div>

    <x-forge-card flat>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-dark-2 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Usuário</th>
                        <th class="px-4 py-3 text-left">Roles atribuídos</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-3">
                    @forelse ($rows as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-2">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-400 to-blue-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
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
                                                <span class="ml-1 text-gray-400">· {{ $ur->company->name }}</span>
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400">Sem roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-forge-button wire:click="openUserModal({{ $user->id }}, '{{ addslashes($user->name) }}')" color="light" size="xs">
                                    🔑 Gerenciar Acesso
                                </x-forge-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-12 text-center text-gray-400">Nenhum usuário encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-dark-3">
                <x-forge-pagination :paginator="$rows" />
            </div>
        @endif
    </x-forge-card>

    {{-- Modal de gestão de roles do usuário --}}
    <div x-data="{ open: @entangle('showModal').live }">
        <x-forge-modal title="Acesso — {{ $bindingUserName }}" size="lg">
            <div class="space-y-5">
                {{-- Roles atuais --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Roles atribuídos</h3>
                    @if ($assignedRoles)
                        <div class="space-y-1">
                            @foreach ($assignedRoles as $ar)
                                <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-dark-2 border border-gray-100 dark:border-dark-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium {{ $ar['role_master'] ? 'text-amber-600' : 'text-gray-900 dark:text-white' }}">
                                            {{ $ar['role_master'] ? '👑 ' : '' }}{{ $ar['role_name'] }}
                                        </span>
                                        <span class="text-xs text-gray-400">·</span>
                                        <span class="text-xs text-gray-500">{{ $ar['company_name'] }}</span>
                                    </div>
                                    @if (!$ar['role_master'])
                                        <button wire:click="removeRole({{ $ar['id'] }})" class="text-red-400 hover:text-red-600 text-xs font-medium">
                                            Remover
                                        </button>
                                    @else
                                        <span class="text-xs text-amber-500">Protegido</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">Nenhum role atribuído.</p>
                    @endif
                </div>

                {{-- Adicionar novo --}}
                <div class="border-t border-gray-100 dark:border-dark-3 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Adicionar role</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <x-forge-select
                            label="Role"
                            wire:model="newRoleId"
                            :options="$roles->map(fn($r)=>['value'=>$r->id,'label'=>($r->is_master?'👑 ':''). $r->name])->prepend(['value'=>0,'label'=>'Selecione...'])->toArray()"
                        />
                        <x-forge-select
                            label="Empresa"
                            wire:model="newCompanyId"
                            :options="$companies->map(fn($c)=>['value'=>$c->id,'label'=>$c->name])->prepend(['value'=>0,'label'=>'Global (sem empresa)'])->toArray()"
                        />
                    </div>
                    <div class="mt-3">
                        <x-forge-button wire:click="addRole" color="primary" size="sm">Adicionar</x-forge-button>
                    </div>
                </div>
            </div>
            <x-slot name="footer">
                <x-forge-button color="light" @click="open = false">Fechar</x-forge-button>
            </x-slot>
        </x-forge-modal>
    </div>
</div>

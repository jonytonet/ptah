<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\Company;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;

#[Layout('ptah::layouts.forge-dashboard')]
class UserPermissionList extends Component
{
    use WithPagination;

    protected PermissionService $permissionService;

    public function boot(PermissionService $permissionService): void
    {
        $this->permissionService = $permissionService;
    }

    // ── Lista ──────────────────────────────────────────────────────────
    public string $search    = '';
    public int    $filterRole = 0;
    public int    $filterCompany = 0;

    // ── Modal de bind user-role ────────────────────────────────────────
    public bool  $showModal    = false;
    public ?int  $bindingUserId = null;
    public string $bindingUserName = '';

    /** @var array[] Roles já atribuídos ao usuário */
    public array $assignedRoles = [];

    /** Novo bind a adicionar */
    public int   $newRoleId    = 0;
    public int   $newCompanyId = 0;

    public string $successMsg = '';
    public string $errorMsg   = '';

    public function updatingSearch(): void { $this->resetPage(); }

    // ── Modal de gestão de roles do usuário ────────────────────────────

    public function openUserModal(int $userId, string $userName): void
    {
        $this->bindingUserId   = $userId;
        $this->bindingUserName = $userName;
        $this->newRoleId       = 0;
        $this->newCompanyId    = 0;
        $this->loadAssignedRoles();
        $this->showModal = true;
    }

    public function loadAssignedRoles(): void
    {
        $this->assignedRoles = UserRole::with(['role', 'company'])
            ->where('user_id', $this->bindingUserId)
            ->active()
            ->get()
            ->map(fn (UserRole $ur) => [
                'id'           => $ur->id,
                'role_id'      => $ur->role_id,
                'role_name'    => $ur->role?->name ?? '—',
                'role_master'  => $ur->role?->is_master ?? false,
                'company_id'   => $ur->company_id,
                'company_name' => $ur->company?->name ?? 'Global',
            ])
            ->toArray();
    }

    public function addRole(): void
    {
        if (!$this->newRoleId) {
            $this->errorMsg = 'Selecione um role.';
            return;
        }

        try {
            $companyIds = $this->newCompanyId ? [$this->newCompanyId] : [];
            $this->permissionService->syncRole($this->bindingUserId, $this->newRoleId, $companyIds);
            $this->successMsg = 'Role adicionado.';
            $this->newRoleId  = 0;
            $this->newCompanyId = 0;
            $this->loadAssignedRoles();
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }
    }

    public function removeRole(int $userRoleId): void
    {
        try {
            $ur = UserRole::findOrFail($userRoleId);

            if ($ur->role?->is_master) {
                $this->errorMsg = 'Não é possível remover o role MASTER de um usuário diretamente.';
                return;
            }

            $ur->delete();
            $this->successMsg = 'Role removido.';
            $this->loadAssignedRoles();
            $this->permissionService->clearCache($this->bindingUserId);
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }
    }

    // ── Render ─────────────────────────────────────────────────────────

    public function getRowsProperty(): LengthAwarePaginator
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('ptah.permissions.user_model', 'App\Models\User');

        if (!class_exists($userModel)) {
            return new LengthAwarePaginator([], 0, 20);
        }

        return $userModel::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRole, fn ($q) => $q->whereHas('ptahUserRoles', fn ($q2) =>
                $q2->where('role_id', $this->filterRole)->active()
            ))
            ->orderBy('name')
            ->paginate(25);
    }

    public function getRolesProperty()
    {
        return Role::active()->orderByRaw('is_master DESC')->orderBy('name')->get();
    }

    public function getCompaniesProperty()
    {
        return Company::active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('ptah::livewire.permission.user-permission-list', [
            'rows'      => $this->rows,
            'roles'     => $this->roles,
            'companies' => $this->companies,
        ]);
    }
}

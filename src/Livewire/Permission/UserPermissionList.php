<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Livewire\Permission\Concerns\RequiresMasterAccess;
use Ptah\Models\Company;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;

#[Layout('ptah::layouts.forge-dashboard')]
class UserPermissionList extends Component
{
    use RequiresMasterAccess;
    use WithPagination;

    protected PermissionService $permissionService;

    public function boot(PermissionService $permissionService): void
    {
        $this->assertMasterAccess();
        $this->permissionService = $permissionService;
    }

    // ── Lista ──────────────────────────────────────────────────────────
    public string $search = '';

    public int $filterRole = 0;

    public int $filterCompany = 0;

    // ── Modal de bind user-role ────────────────────────────────────────
    public bool $showModal = false;

    public ?int $bindingUserId = null;

    public string $bindingUserName = '';

    /** @var array[] Roles already assigned to the user */
    public array $assignedRoles = [];

    /** Novo bind a adicionar */
    public int $newRoleId = 0;

    public int $newCompanyId = 0;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ── User create / edit ─────────────────────────────────────────────
    //
    // Until 1.37.0 this screen could only bind roles; every project wrote its
    // own "users" CRUD by hand. Create and edit go through userQuery() — the
    // same query the list uses, `user_query_scope` included — so a master
    // limited to a subset of users by that scope cannot reach the others by
    // id. There is no "deactivate": the login does not read an active flag,
    // and a switch that changes nothing would be worse than none.

    public bool $showUserForm = false;

    #[Locked]
    public ?int $editingUserId = null;

    /** @var array{name?: string, email?: string, password?: string, send_link?: bool} */
    public array $userForm = [];

    /** @var array<string, string> */
    public array $userFormErrors = [];

    public function newUser(): void
    {
        $this->editingUserId = null;
        $this->userForm = ['name' => '', 'email' => '', 'password' => '', 'send_link' => $this->canSendPasswordLink()];
        $this->userFormErrors = [];
        $this->showUserForm = true;
    }

    public function editUser(int $userId): void
    {
        $user = $this->userQuery()?->find($userId);

        if (! $user) {
            return;
        }

        $this->editingUserId = (int) $user->getKey();
        $this->userForm = ['name' => (string) $user->name, 'email' => (string) $user->email, 'password' => '', 'send_link' => false];
        $this->userFormErrors = [];
        $this->showUserForm = true;
    }

    public function saveUser(): void
    {
        $userModel = $this->userModel();

        if ($userModel === null) {
            return;
        }

        $instance = new $userModel;
        $user = $this->editingUserId !== null ? $this->userQuery()?->find($this->editingUserId) : null;

        if ($this->editingUserId !== null && ! $user) {
            $this->showUserForm = false;

            return;
        }

        $validator = Validator::make($this->userForm, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(($instance->getConnectionName() ? $instance->getConnectionName().'.' : '').$instance->getTable(), 'email')->ignore($user?->getKey(), $instance->getKeyName())],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ], [], [
            'name' => __('ptah::ui.users_field_name'),
            'email' => __('ptah::ui.users_field_email'),
            'password' => __('ptah::ui.users_field_password'),
        ]);

        if ($validator->fails()) {
            $this->userFormErrors = array_map(fn (array $m) => $m[0], $validator->errors()->toArray());

            return;
        }

        $data = $validator->validated();
        $user ??= new $userModel;

        // forceFill: nome, e-mail e senha sao as colunas do contrato de auth do
        // Laravel; o $fillable do host nao deveria decidir se o admin consegue
        // criar um usuario.
        $user->forceFill(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['password'])) {
            $user->forceFill(['password' => Hash::make($data['password'])]);
        } elseif (! $user->exists) {
            // Ninguem conhece esta senha: o acesso vem do link (ou do "esqueci").
            $user->forceFill(['password' => Hash::make(Str::password(40))]);
        }

        $user->save();

        $message = __($this->editingUserId !== null ? 'ptah::ui.users_saved' : 'ptah::ui.users_created');

        if (! empty($this->userForm['send_link']) && ! $this->sendLinkTo((string) $user->email)) {
            $message .= ' '.__('ptah::ui.users_link_failed');
        } elseif (! empty($this->userForm['send_link'])) {
            $message .= ' '.__('ptah::ui.users_link_sent');
        }

        $this->showUserForm = false;
        $this->dispatch('ptah-toast', title: $message, color: 'success');
    }

    public function sendPasswordLink(int $userId): void
    {
        $user = $this->userQuery()?->find($userId);

        if (! $user) {
            return;
        }

        $ok = $this->sendLinkTo((string) $user->email);
        $this->dispatch('ptah-toast', title: __($ok ? 'ptah::ui.users_link_sent' : 'ptah::ui.users_link_failed'), color: $ok ? 'success' : 'danger');
    }

    /**
     * The reset link is the same one "forgot password" sends, so it only works
     * where that page exists (auth module).
     */
    public function canSendPasswordLink(): bool
    {
        return Route::has('password.reset');
    }

    protected function sendLinkTo(string $email): bool
    {
        if (! $this->canSendPasswordLink()) {
            return false;
        }

        try {
            return Password::sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT;
        } catch (\Throwable $e) {
            // Sem a tabela password_reset_tokens, sem mailer... a causa vai
            // para o log (ptah:last-error), nao so "confira o e-mail".
            report($e);

            return false;
        }
    }

    /**
     * @return class-string<Model>|null
     */
    protected function userModel(): ?string
    {
        $model = config('ptah.permissions.user_model', 'App\\Models\\User');

        return is_string($model) && class_exists($model) ? $model : null;
    }

    /**
     * The users this screen may show and touch: the list's query, with the
     * host's `user_query_scope`.
     */
    protected function userQuery(): ?Builder
    {
        $userModel = $this->userModel();

        if ($userModel === null) {
            return null;
        }

        $query = $userModel::query();

        if ($scopeClass = config('ptah.permissions.user_query_scope')) {
            if (class_exists($scopeClass)) {
                $query->withGlobalScope('ptah_user_scope', new $scopeClass);
            }
        }

        return $query;
    }

    // ── User role management modal ──────────────────────────────────────

    public function openUserModal(int $userId, string $userName): void
    {
        $this->bindingUserId = $userId;
        $this->bindingUserName = $userName;
        $this->newRoleId = 0;
        $this->newCompanyId = 0;
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
                'id' => $ur->id,
                'role_id' => $ur->role_id,
                'role_name' => $ur->role?->name ?? '—',
                'role_master' => $ur->role?->is_master ?? false,
                'company_id' => $ur->company_id,
                'company_name' => $ur->company?->name ?? 'Global',
            ])
            ->toArray();
    }

    public function addRole(): void
    {
        if (! $this->newRoleId) {
            $this->dispatch('ptah-toast', title: 'Selecione um role.', color: 'danger');

            return;
        }

        try {
            $companyIds = $this->newCompanyId ? [$this->newCompanyId] : [];
            $this->permissionService->syncRole($this->bindingUserId, $this->newRoleId, $companyIds);
            $this->dispatch('ptah-toast', title: 'Role adicionado.', color: 'success');
            $this->newRoleId = 0;
            $this->newCompanyId = 0;
            $this->loadAssignedRoles();
        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: 'Erro: '.$e->getMessage(), color: 'danger');
        }
    }

    public function removeRole(int $userRoleId): void
    {
        try {
            $ur = UserRole::findOrFail($userRoleId);

            if ($ur->role?->is_master) {
                $this->dispatch('ptah-toast', title: 'Cannot remove the MASTER role from a user directly.', color: 'danger');

                return;
            }

            $ur->delete();
            $this->dispatch('ptah-toast', title: 'Role removido.', color: 'success');
            $this->loadAssignedRoles();
            $this->permissionService->clearCache($this->bindingUserId);
        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: 'Erro: '.$e->getMessage(), color: 'danger');
        }
    }

    // ── Render ─────────────────────────────────────────────────────────

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = $this->userQuery();

        if ($query === null) {
            return new LengthAwarePaginator([], 0, 20);
        }

        return $query
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRole, fn ($q) => $q->whereHas('ptahUserRoles', fn ($q2) => $q2->where('role_id', $this->filterRole)->active()
            ))
            ->orderBy('name')
            ->paginate(25);
    }

    #[Computed]
    public function roles()
    {
        return Role::active()->orderByRaw('is_master DESC')->orderBy('name')->get();
    }

    #[Computed]
    public function companies()
    {
        return Company::active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('ptah::livewire.permission.user-permission-list', [
            'rows' => $this->rows,
            'roles' => $this->roles,
            'companies' => $this->companies,
        ]);
    }
}

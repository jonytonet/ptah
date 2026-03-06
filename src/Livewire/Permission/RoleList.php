<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\Department;
use Ptah\Models\PageObject;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Services\Permission\RoleService;

#[Layout('ptah::layouts.forge-dashboard')]
class RoleList extends Component
{
    use WithPagination;

    protected RoleService $roleService;

    public function boot(RoleService $roleService): void
    {
        $this->roleService = $roleService;
    }

    // ── Lista ──────────────────────────────────────────────────────────
    public string $search    = '';
    public string $sort      = 'name';
    public string $direction = 'asc';

    // ── Create/edit modal ─────────────────────────────────────────────
    public bool  $showModal  = false;
    public bool  $isEditing  = false;
    public ?int  $editingId  = null;
    public string $name         = '';
    public string $description  = '';
    public string $color        = '';
    public ?int  $department_id = null;
    public bool  $is_master     = false;
    public bool  $is_active     = true;

    // ── Permission bind modal ─────────────────────────────────────────
    public bool  $showBindModal  = false;
    public ?int  $bindingRoleId  = null;
    public string $bindingRoleName = '';
    /** @var array<int, array{obj_key, obj_label, obj_type, section, can_create, can_read, can_update, can_delete}> */
    public array $bindObjects     = [];
    public int   $bindFilterPageId = 0;

    // ── Delete confirmation ────────────────────────────────────────────
    public ?int  $deleteId        = null;
    public bool  $showDeleteModal = false;

    // ── Feedback ──────────────────────────────────────────────────────
    public string $successMsg = '';
    public string $errorMsg   = '';

    // ── Rules ──────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:500',
            'color'         => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:ptah_departments,id',
            'is_master'     => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function updatingSearch(): void { $this->resetPage(); }

    // ── Modal Criar/Editar ─────────────────────────────────────────────

    public function create(): void
    {
        $this->reset(['name', 'description', 'color', 'department_id', 'is_master', 'is_active', 'editingId']);
        $this->is_active = true;
        $this->is_master = false;
        $this->isEditing = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->editingId     = $id;
        $this->name          = $role->name;
        $this->description   = $role->description ?? '';
        $this->color         = $role->color ?? '';
        $this->department_id = $role->department_id;
        $this->is_master     = $role->is_master;
        $this->is_active     = $role->is_active;
        $this->isEditing     = true;
        $this->showModal     = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'name'          => $this->name,
                'description'   => $this->description ?: null,
                'color'         => $this->color ?: null,
                'department_id' => $this->department_id,
                'is_master'     => $this->is_master,
                'is_active'     => $this->is_active,
            ];

            if ($this->isEditing) {
                $role = Role::findOrFail($this->editingId);
                $this->roleService->update($role, $data);
                $this->successMsg = 'Role updated.';
            } else {
                $this->roleService->create($data);
                $this->successMsg = 'Role created.';
            }

            $this->showModal = false;
        } catch (ValidationException $e) {
            $this->errorMsg = collect($e->errors())->flatten()->first() ?? 'Validation error.';
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }
    }

    // ── Permission Bind Modal ──────────────────────────────────────────────────────

    public function openBind(int $roleId): void
    {
        $role = $this->roleService->getWithPermissions($roleId);

        $this->bindingRoleId   = $roleId;
        $this->bindingRoleName = $role->name;

        // Build list of all objects with existing permissions of the role
        $existingMap = $role->permissions
            ->keyBy('page_object_id')
            ->map(fn (RolePermission $rp) => $rp->toCrudArray());

        $this->bindObjects = PageObject::with('page')
            ->active()
            ->orderBy('obj_order')
            ->get()
            ->map(fn (PageObject $obj) => [
                'id'         => $obj->id,
                'page_id'    => $obj->page_id,
                'page_name'  => $obj->page?->name ?? '—',
                'section'    => $obj->section,
                'obj_key'    => $obj->obj_key,
                'obj_label'  => $obj->obj_label,
                'obj_type'   => $obj->obj_type,
                'can_create' => $existingMap[$obj->id]['create'] ?? false,
                'can_read'   => $existingMap[$obj->id]['read']   ?? false,
                'can_update' => $existingMap[$obj->id]['update'] ?? false,
                'can_delete' => $existingMap[$obj->id]['delete'] ?? false,
            ])
            ->toArray();

        $this->showBindModal = true;
    }

    public function saveBind(): void
    {
        if (!$this->bindingRoleId) {
            return;
        }

        $role     = Role::findOrFail($this->bindingRoleId);
        $bindings = [];

        foreach ($this->bindObjects as $obj) {
            $hasAny = $obj['can_create'] || $obj['can_read'] || $obj['can_update'] || $obj['can_delete'];

            if ($hasAny) {
                $bindings[(int) $obj['id']] = [
                    'can_create' => $obj['can_create'],
                    'can_read'   => $obj['can_read'],
                    'can_update' => $obj['can_update'],
                    'can_delete' => $obj['can_delete'],
                ];
            }
        }

        try {
            $this->roleService->syncPageBindings($role, $bindings);
            $this->successMsg    = "Permissions for '{$role->name}' updated.";
            $this->showBindModal = false;
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }
    }

    // ── Deletion ───────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deleteId        = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        try {
            $role = Role::findOrFail($this->deleteId);
            $this->roleService->delete($role);
            $this->successMsg = 'Role deleted.';
        } catch (ValidationException $e) {
            $this->errorMsg = collect($e->errors())->flatten()->first() ?? 'Error.';
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    // ── Render ─────────────────────────────────────────────────────────

    #[Computed]
    public function departments()
    {
        return Department::active()->orderBy('name')->get();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return Role::query()
            ->with('department')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('permissions')
            ->orderByRaw('is_master DESC')
            ->orderBy($this->sort, $this->direction)
            ->paginate(20);
    }

    public function render()
    {
        return view('ptah::livewire.permission.role-list', [
            'rows'        => $this->rows,
            'departments' => $this->departments,
        ]);
    }
}

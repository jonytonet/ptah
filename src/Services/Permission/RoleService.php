<?php

declare(strict_types=1);

namespace Ptah\Services\Permission;

use Illuminate\Validation\ValidationException;
use Ptah\Models\PageObject;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;

/**
 * Manages creation, update and permission binding for Roles.
 *
 * Business rules:
 *  - Only 1 role with is_master = true may exist
 *  - MASTER role cannot be deleted or deactivated
 *  - Object binding uses upsert (create or update)
 */
class RoleService
{
    // ─────────────────────────────────────────
    // Role CRUD
    // ─────────────────────────────────────────

    /**
     * Creates a new role, validating the MASTER uniqueness rule.
     *
     * @throws ValidationException
     */
    public function create(array $data): Role
    {
        if (!empty($data['is_master']) && $data['is_master']) {
            $this->assertNoMasterExists();
        }

        // Ensures default colour for MASTER
        if (!empty($data['is_master']) && empty($data['color'])) {
            $data['color'] = '#fbbf24';
        }

        return Role::create($data);
    }

    /**
     * Updates an existing role.
     *
     * @throws ValidationException
     */
    public function update(Role $role, array $data): Role
    {
        // Trying to make it MASTER when it currently is not
        if (!empty($data['is_master']) && !$role->is_master) {
            $this->assertNoMasterExists();
        }

        // Prevent deactivating the MASTER role
        if ($role->is_master && isset($data['is_active']) && !$data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => trans('ptah::ui.role_master_cannot_deactivate'),
            ]);
        }

        $role->update($data);
        return $role->fresh();
    }

    /**
     * Deletes a role (soft delete). Blocks deletion of MASTER.
     *
     * @throws ValidationException
     */
    public function delete(Role $role): void
    {
        if ($role->is_master) {
            throw ValidationException::withMessages([
                'role' => trans('ptah::ui.role_master_cannot_delete'),
            ]);
        }

        $role->delete();
    }

    // ─────────────────────────────────────────
    // Permission binding
    // ─────────────────────────────────────────

    /**
     * Associates or updates the permission of an object in a role.
     *
     * @param  array{can_create?: bool, can_read?: bool, can_update?: bool, can_delete?: bool, extra?: array} $permissions
     */
    public function bindPageObject(Role $role, int $pageObjectId, array $permissions = []): RolePermission
    {
        // Validates that the object exists
        $pageObject = PageObject::findOrFail($pageObjectId);

        $defaults = [
            'can_create' => false,
            'can_read'   => true,
            'can_update' => false,
            'can_delete' => false,
            'extra'      => null,
        ];

        $data = array_merge($defaults, $permissions);

        return RolePermission::withTrashed()->updateOrCreate(
            ['role_id' => $role->id, 'page_object_id' => $pageObject->id],
            array_merge($data, ['deleted_at' => null])
        );
    }

    /**
     * Removes the permission of an object from the role (soft delete).
     */
    public function unbindPageObject(Role $role, int $pageObjectId): void
    {
        RolePermission::where('role_id', $role->id)
            ->where('page_object_id', $pageObjectId)
            ->delete();
    }

    /**
     * Synchronises ALL objects of a page for the role.
     * Objects not included in $bindings are removed.
     *
     * @param  array<int, array> $bindings  [pageObjectId => [can_create, can_read, ...]]
     */
    public function syncPageBindings(Role $role, array $bindings): void
    {
        $incoming = array_keys($bindings);

        // Remove objects that are no longer included
        RolePermission::where('role_id', $role->id)
            ->whereNotIn('page_object_id', $incoming)
            ->delete();

        // Upsert the ones that came in
        foreach ($bindings as $pageObjectId => $perms) {
            $this->bindPageObject($role, (int) $pageObjectId, $perms);
        }
    }

    /**
     * Returns the role with all permissions loaded (eager).
     */
    public function getWithPermissions(int $roleId): Role
    {
        return Role::with([
            'permissions.pageObject.page',
            'department',
        ])->findOrFail($roleId);
    }

    // ─────────────────────────────────────────
    // Internal validation
    // ─────────────────────────────────────────

    /**
     * @throws ValidationException
     */
    protected function assertNoMasterExists(): void
    {
        if (Role::where('is_master', true)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages([
                'is_master' => trans('ptah::ui.role_master_already_exists'),
            ]);
        }
    }
}

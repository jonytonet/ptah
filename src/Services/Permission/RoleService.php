<?php

declare(strict_types=1);

namespace Ptah\Services\Permission;

use Illuminate\Validation\ValidationException;
use Ptah\Models\PageObject;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;

/**
 * Gerencia criação, atualização e binding de permissões em Roles.
 *
 * Regras de negócio:
 *  - Só pode existir 1 role com is_master = true
 *  - Role MASTER não pode ser excluído nem desativado
 *  - Binding de objetos usa upsert (cria ou atualiza)
 */
class RoleService
{
    // ─────────────────────────────────────────
    // CRUD de Roles
    // ─────────────────────────────────────────

    /**
     * Cria um novo role, validando a regra de unicidade do MASTER.
     *
     * @throws ValidationException
     */
    public function create(array $data): Role
    {
        if (!empty($data['is_master']) && $data['is_master']) {
            $this->assertNoMasterExists();
        }

        // Garante cor padrão para MASTER
        if (!empty($data['is_master']) && empty($data['color'])) {
            $data['color'] = '#fbbf24';
        }

        return Role::create($data);
    }

    /**
     * Atualiza um role existente.
     *
     * @throws ValidationException
     */
    public function update(Role $role, array $data): Role
    {
        // Se está tentando tornar MASTER e não é o atual
        if (!empty($data['is_master']) && !$role->is_master) {
            $this->assertNoMasterExists();
        }

        // Impede desativar role MASTER
        if ($role->is_master && isset($data['is_active']) && !$data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'O role MASTER não pode ser desativado.',
            ]);
        }

        $role->update($data);
        return $role->fresh();
    }

    /**
     * Exclui um role (soft delete). Bloqueia exclusão de MASTER.
     *
     * @throws ValidationException
     */
    public function delete(Role $role): void
    {
        if ($role->is_master) {
            throw ValidationException::withMessages([
                'role' => 'O role MASTER não pode ser excluído.',
            ]);
        }

        $role->delete();
    }

    // ─────────────────────────────────────────
    // Binding de permissões
    // ─────────────────────────────────────────

    /**
     * Associa ou atualiza permissão de um objeto em um role.
     *
     * @param  array{can_create?: bool, can_read?: bool, can_update?: bool, can_delete?: bool, extra?: array} $permissions
     */
    public function bindPageObject(Role $role, int $pageObjectId, array $permissions = []): RolePermission
    {
        // Valida que o objeto existe
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
     * Remove a permissão de um objeto do role (soft delete).
     */
    public function unbindPageObject(Role $role, int $pageObjectId): void
    {
        RolePermission::where('role_id', $role->id)
            ->where('page_object_id', $pageObjectId)
            ->delete();
    }

    /**
     * Sincroniza TODOS os objetos de uma página para o role.
     * Objetos não incluídos em $bindings são removidos.
     *
     * @param  array<int, array> $bindings  [pageObjectId => [can_create, can_read, ...]]
     */
    public function syncPageBindings(Role $role, array $bindings): void
    {
        $incoming = array_keys($bindings);

        // Remove os que não vieram mais
        RolePermission::where('role_id', $role->id)
            ->whereNotIn('page_object_id', $incoming)
            ->delete();

        // Upsert dos que vieram
        foreach ($bindings as $pageObjectId => $perms) {
            $this->bindPageObject($role, (int) $pageObjectId, $perms);
        }
    }

    /**
     * Retorna o role com todas as permissões carregadas (eager).
     */
    public function getWithPermissions(int $roleId): Role
    {
        return Role::with([
            'permissions.pageObject.page',
            'department',
        ])->findOrFail($roleId);
    }

    // ─────────────────────────────────────────
    // Validação interna
    // ─────────────────────────────────────────

    /**
     * @throws ValidationException
     */
    protected function assertNoMasterExists(): void
    {
        if (Role::where('is_master', true)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages([
                'is_master' => 'Já existe um role MASTER. Só é permitido um role MASTER no sistema.',
            ]);
        }
    }
}

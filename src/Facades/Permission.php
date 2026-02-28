<?php

declare(strict_types=1);

namespace Ptah\Facades;

use Illuminate\Support\Facades\Facade;
use Ptah\Services\Permission\PermissionService;

/**
 * Facade para o PermissionService do Ptah.
 *
 * @method static bool   check(mixed $user, string $objectKey, string $action, ?int $companyId = null)
 * @method static bool   isMaster(mixed $user = null)
 * @method static array  getPermissions(mixed $user = null, ?int $companyId = null)
 * @method static array  getCompaniesForResource(mixed $user, string $objectKey, string $action)
 * @method static void   syncRole(mixed $user, int $roleId, array $companyIds = [])
 * @method static void   detachRole(mixed $user, int $roleId, ?int $companyId = null)
 * @method static void   clearCache(mixed $user = null, ?int $companyId = null)
 *
 * Exemplos:
 *   Permission::check(auth()->user(), 'users.store', 'create')
 *   Permission::isMaster()
 *   Permission::canAs($user, 'reports', 'read', $companyId)
 *
 * @see \Ptah\Services\Permission\PermissionService
 */
class Permission extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PermissionService::class;
    }
}

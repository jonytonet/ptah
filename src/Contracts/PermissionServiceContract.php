<?php

declare(strict_types=1);

namespace Ptah\Contracts;

interface PermissionServiceContract
{
    /**
     * Checks whether the user has permission to perform the action on the object.
     *
     * @param  mixed       $user       User, user ID or null (uses current auth)
     * @param  string      $objectKey  Object key (e.g. 'users.store')
     * @param  string      $action     create|read|update|delete
     * @param  int|null    $companyId  Company ID (null = session/auth context)
     */
    public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool;

    /**
     * Checks whether the user has the MASTER role (full bypass).
     */
    public function isMaster(mixed $user = null): bool;

    /**
     * Returns the full permissions map for the user.
     *
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    public function getPermissions(mixed $user = null, ?int $companyId = null): array;

    /**
     * Returns the company IDs where the user has access to the resource/action.
     *
     * @return int[]
     */
    public function getCompaniesForResource(mixed $user, string $objectKey, string $action): array;

    /**
     * Associates a role with the user (creates a UserRole for each company).
     *
     * @param  int[] $companyIds  Company IDs; [] = no company (single-company)
     */
    public function syncRole(mixed $user, int $roleId, array $companyIds = []): void;

    /**
     * Removes the role-user association (soft delete of UserRole).
     */
    public function detachRole(mixed $user, int $roleId, ?int $companyId = null): void;

    /**
     * Invalidates the user's permission cache.
     * Without parameters = invalidates everything.
     */
    public function clearCache(mixed $user = null, ?int $companyId = null): void;
}

<?php

declare(strict_types=1);

use Ptah\Services\Permission\PermissionService;

if (!function_exists('ptah_can')) {
    /**
     * Checks whether the user has permission to perform an action on a resource.
     *
     * The $user parameter accepts:
     *  - null          → uses auth()->user() or session (config ptah.permissions.user_session_key)
     *  - int|string    → user ID, resolved via config ptah.permissions.user_model
     *  - Authenticatable → user model directly
     *
     * The $companyId parameter accepts:
     *  - null          → uses session (config ptah.permissions.company_session_key)
     *  - int           → company ID
     *
     * @param  string          $objectKey  Object key (e.g. 'users.store', 'reports.export')
     * @param  string          $action     Desired action: 'create', 'read', 'update', 'delete'
     * @param  mixed           $user       User (null = current auth)
     * @param  int|null        $companyId  Company ID (null = current session)
     * @return bool
     */
    function ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->check($user, $objectKey, $action, $companyId);
    }
}

if (!function_exists('ptah_is_master')) {
    /**
     * Checks whether the user has the MASTER role (full permission bypass).
     *
     * @param  mixed $user  User (null = current auth)
     * @return bool
     */
    function ptah_is_master(mixed $user = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->isMaster($user);
    }
}

if (!function_exists('ptah_company_id')) {
    /**
     * Returns the active company ID from the session.
     * Uses the key configured in ptah.permissions.company_session_key.
     *
     * @return int  0 if no company is selected
     */
    function ptah_company_id(): int
    {
        $key = config('ptah.permissions.company_session_key', 'ptah_company_id');
        return (int) session($key, 0);
    }
}

if (!function_exists('ptah_active_company')) {
    /**
     * Returns the Company model for the active session company, or null.
     *
     * @return \Ptah\Models\Company|null
     */
    function ptah_active_company(): ?\Ptah\Models\Company
    {
        /** @var \Ptah\Services\Company\CompanyService $service */
        $service = app(\Ptah\Services\Company\CompanyService::class);
        return $service->getActive();
    }
}

if (!function_exists('ptah_companies')) {
    /**
     * Returns the Collection of all active companies.
     * Result is cached for 5 minutes.
     *
     * @return \Illuminate\Support\Collection
     */
    function ptah_companies(): \Illuminate\Support\Collection
    {
        /** @var \Ptah\Services\Company\CompanyService $service */
        $service = app(\Ptah\Services\Company\CompanyService::class);
        return $service->getAll();
    }
}

if (!function_exists('ptah_permissions')) {
    /**
     * Returns the complete permissions map for the user.
     *
     * @param  mixed    $user       User (null = current auth)
     * @param  int|null $companyId  Company ID (null = current session)
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    function ptah_permissions(mixed $user = null, ?int $companyId = null): array
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->getPermissions($user, $companyId);
    }
}

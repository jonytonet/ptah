<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Ptah\Models\Company;
use Ptah\Services\Company\CompanyService;
use Ptah\Services\Permission\PermissionService;

if (! function_exists('ptah_can')) {
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
     * @param  string  $objectKey  Object key (e.g. 'users.store', 'reports.export')
     * @param  string  $action  Desired action: 'create', 'read', 'update', 'delete'
     * @param  mixed  $user  User (null = current auth)
     * @param  int|null  $companyId  Company ID (null = current session)
     */
    function ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->check($user, $objectKey, $action, $companyId);
    }
}

if (! function_exists('ptah_is_master')) {
    /**
     * Checks whether the user has the MASTER role (full permission bypass).
     *
     * @param  mixed  $user  User (null = current auth)
     */
    function ptah_is_master(mixed $user = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->isMaster($user);
    }
}

if (! function_exists('ptah_can_manage_config')) {
    /**
     * Whether the given/current user may open and save the in-app CRUD
     * configuration editor (ptah-crud-config).
     *
     * The editor writes joins, lifecycle hooks, link templates, colsMetodoCustom,
     * etc. — inputs that feed SQL/render sinks — so it must be gated:
     *  - permissions module ACTIVE → master user OR 'crud.config' read grant;
     *  - module OFF               → config('ptah.crud.config_editor'), default deny.
     *
     * Why `read` and not a dedicated `manage`/`configure` verb: PermissionService::ACTIONS
     * is a whitelist whose entries are interpolated into a `can_{action}` COLUMN name
     * (anti-SQLi guard) — adding a verb means adding a column, i.e. a migration. This
     * package is already installed in production elsewhere and its migrations are
     * auto-discovered (loadMigrationsFrom in the service provider), so a new migration
     * would fire on the next `php artisan migrate` any consuming app happens to run, for
     * a feature that app may not even use. Instead, the CAPABILITY is expressed as its
     * own OBJECT (`crud.config`, a `page_object` an admin registers explicitly) and
     * `read` on that object is what's granted — the object, not the verb, carries the
     * meaning "may configure the CRUD editor". Do NOT "fix" this back to a `manage`
     * verb without adding the column via a migration a human reviews and runs by hand;
     * doing so silently makes this grant a no-op again for every non-MASTER user.
     *
     * @param  mixed  $user  User (null = current auth)
     */
    function ptah_can_manage_config(mixed $user = null): bool
    {
        if (config('ptah.modules.permissions')) {
            return ptah_is_master($user) || ptah_can('crud.config', 'read', $user);
        }

        return (bool) config('ptah.crud.config_editor', false);
    }
}

if (! function_exists('ptah_company_id')) {
    /**
     * Returns the active company ID from the session.
     * Uses the key configured in ptah.permissions.company_session_key.
     *
     * @return int 0 if no company is selected
     */
    function ptah_company_id(): int
    {
        $key = config('ptah.permissions.company_session_key', 'ptah_company_id');

        return (int) session($key, 0);
    }
}

if (! function_exists('ptah_active_company')) {
    /**
     * Returns the Company model for the active session company, or null.
     */
    function ptah_active_company(): ?Company
    {
        /** @var CompanyService $service */
        $service = app(CompanyService::class);

        return $service->getActive();
    }
}

if (! function_exists('ptah_companies')) {
    /**
     * Returns the Collection of all active companies.
     * Result is cached for 5 minutes.
     */
    function ptah_companies(): Collection
    {
        /** @var CompanyService $service */
        $service = app(CompanyService::class);

        return $service->getAll();
    }
}

if (! function_exists('ptah_permissions')) {
    /**
     * Returns the complete permissions map for the user.
     *
     * @param  mixed  $user  User (null = current auth)
     * @param  int|null  $companyId  Company ID (null = current session)
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    function ptah_permissions(mixed $user = null, ?int $companyId = null): array
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->getPermissions($user, $companyId);
    }
}

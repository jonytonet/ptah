<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Ptah\Models\Company;
use Ptah\Services\Company\CompanyService;
use Ptah\Services\Notification\NotificationService;
use Ptah\Services\Permission\PermissionService;
use Ptah\Traits\ResolvesUser;

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
     * @param  string  $objectKey  Object key (e.g. 'users.store', 'reports.export'). May also be a
     *                             QUALIFIED key (`page::obj_key` / `page::section::obj_key`, see
     *                             `PermissionService::KEY_QUALIFIER`) to disambiguate an obj_key
     *                             that collides across pages — passed through unchanged to check().
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

if (! function_exists('ptah_has_role')) {
    /**
     * Checks whether the user holds (at least one of) the given role name(s).
     * Tolerant match: case-insensitive/trimmed, or equal once both sides go
     * through `Str::slug()` (so "Vendas Externas" matches "vendas-externas").
     *
     * This is IDENTITY, not a GATE — for authorization use ptah_can(). A
     * MASTER user does NOT automatically "have" every role name here; they
     * only bypass permission checks, which is a different concern.
     *
     * @param  string|string[]  $roles  One role name, or several (OR match)
     * @param  mixed  $user  User (null = current auth)
     * @param  int|null  $companyId  Company ID (null = current session)
     */
    function ptah_has_role(string|array $roles, mixed $user = null, ?int $companyId = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->hasRole($user, $roles, $companyId);
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

if (! function_exists('ptah_notify')) {
    /**
     * Pushes (or, when $data contains `dedupe_key`, updates) a notification
     * for a single user — see Ptah\Services\Notification\NotificationService::push().
     * No-op (returns 0) when the notifications module is off, its table is
     * not migrated, or $user cannot be resolved to an id.
     *
     * @param  mixed  $user  int|string id, an Authenticatable/Model, or null (= current auth)
     * @param  array<string, mixed>  $data  type,category,title,body,icon,url,action_label,dedupe_key,company_id
     */
    function ptah_notify(mixed $user, array $data): int
    {
        $userId = (new class
        {
            use ResolvesUser;

            public function resolve(mixed $user): ?int
            {
                return $this->resolveUserId($user);
            }
        })->resolve($user);

        if ($userId === null) {
            return 0;
        }

        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        return $service->toUser($userId, $data);
    }
}

if (! function_exists('ptah_notify_role')) {
    /**
     * Broadcasts a notification to every user holding an ACTIVE assignment of
     * an active role named $roleName (tolerant match, see
     * PermissionService::hasRole()), optionally scoped to a company.
     *
     * @param  array<string, mixed>  $data
     * @return int How many notifications were written.
     */
    function ptah_notify_role(string $roleName, array $data, ?int $companyId = null): int
    {
        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        return $service->toRole($roleName, $data, $companyId);
    }
}

if (! function_exists('ptah_notify_all')) {
    /**
     * Broadcasts a notification to every "staff" user (anyone holding an
     * active role, optionally scoped to a company) or, with $onlyStaff =
     * false, to every user of the host application's user model.
     *
     * @param  array<string, mixed>  $data
     * @return int How many notifications were written.
     */
    function ptah_notify_all(array $data, ?int $companyId = null, bool $onlyStaff = true): int
    {
        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        return $service->toAll($data, $companyId, $onlyStaff);
    }
}

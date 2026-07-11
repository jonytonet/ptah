<?php

declare(strict_types=1);

namespace Ptah\Services\Permission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Ptah\Contracts\PermissionServiceContract;
use Ptah\Models\PageObject;
use Ptah\Models\PermissionAudit;
use Ptah\Models\UserRole;
use Ptah\Traits\ResolvesUser;

/**
 * Central permission verification service for Ptah.
 *
 * Hierarchy: Company → Role (is_master=bypass) → Page → Object → CRUD
 *
 * Adaptable to different scenarios:
 *  - app with Sanctum/Passport   → $user = Auth::user()
 *  - legacy app with session ID  → $user = null (reads PTAH_USER_SESSION_KEY)
 *  - single-tenant               → $companyId = null, multi_company = false
 *  - multi-company               → $companyId = active company id
 */
class PermissionService implements PermissionServiceContract
{
    use ResolvesUser;

    /** The only valid CRUD actions — whitelisted before any query interpolation. */
    public const ACTIONS = ['create', 'read', 'update', 'delete'];

    // ─────────────────────────────────────────
    // Company resolution
    // ─────────────────────────────────────────

    /**
     * Resolves the active company ID.
     */
    protected function resolveCompanyId(?int $companyId): ?int
    {
        if ($companyId !== null) {
            return $companyId;
        }

        if (! config('ptah.permissions.multi_company', true)) {
            return null;
        }

        $sessionKey = config('ptah.permissions.company_session_key', 'ptah_company_id');
        if ($sessionKey && Session::has($sessionKey)) {
            return (int) Session::get($sessionKey);
        }

        return null;
    }

    // ─────────────────────────────────────────
    // Cache helpers
    // ─────────────────────────────────────────

    protected function cacheKey(string $type, int $userId, ?int $companyId, string $extra = ''): string
    {
        // Generation-based versioning: every key embeds the global and per-user
        // version counters. Bumping a counter (on a role/permission change)
        // instantly orphans every key of that generation — works on ANY cache
        // driver (file included), O(1), no tag support or key enumeration needed.
        $g = $this->globalVersion();
        $uv = $this->userVersion($userId);

        return "ptah_{$type}:g{$g}:u{$uv}:{$userId}:{$companyId}:{$extra}";
    }

    protected function ttl(): int
    {
        return (int) config('ptah.permissions.cache_ttl', 3600);
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('ptah.permissions.cache', true);
    }

    // ─────────────────────────────────────────
    // Cache generations (versioning)
    // ─────────────────────────────────────────

    /** Global cache generation — bumped when ANY role/permission definition changes. */
    protected function globalVersion(): int
    {
        return (int) Cache::get('ptah_perm_gver', 1);
    }

    /** Per-user cache generation — bumped when that user's role assignments change. */
    protected function userVersion(int $userId): int
    {
        return (int) Cache::get("ptah_perm_uver:{$userId}", 1);
    }

    /**
     * Invalidates every cached permission map and master flag for ALL users at
     * once. Called by the model observers when a Role or RolePermission changes,
     * since the affected users cannot be enumerated cheaply.
     */
    public function bumpGlobalVersion(): void
    {
        if (! Cache::has('ptah_perm_gver')) {
            Cache::forever('ptah_perm_gver', 1);
        }
        Cache::increment('ptah_perm_gver');
    }

    /** Invalidates every cached entry for a single user across all companies. */
    protected function bumpUserVersion(int $userId): void
    {
        $key = "ptah_perm_uver:{$userId}";
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }
        Cache::increment($key);
    }

    // ─────────────────────────────────────────
    // Contract implementation
    // ─────────────────────────────────────────

    /**
     * {@inheritdoc}
     *
     * Unified implementation: delegates to the full map cached by getPermissions().
     * Eliminates double cache (individual + map) that caused stale data after revocation.
     */
    public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
    {
        $userId = $this->resolveUserId($user);

        // Guests without permission (unless allow_guest = true)
        if ($userId === null) {
            return (bool) config('ptah.permissions.allow_guest', false);
        }

        // Whitelist the action before it ever touches a query/column name.
        $action = strtolower($action);
        if (! in_array($action, self::ACTIONS, true)) {
            return false;
        }

        // 1. Short-circuit: MASTER roles pass everything
        if ($this->isMasterById($userId)) {
            if (config('ptah.permissions.audit') && config('ptah.permissions.audit_master')) {
                $this->writeAudit($userId, $companyId, $objectKey, strtolower($action), 'granted');
            }

            return true;
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);

        // 2. Look up in the full map (single source of truth, already cached)
        //    Ensures consistency: clearCache() invalidates the map and this read
        //    immediately reflects any role/permission changes.
        $map = $this->getPermissions($user, $resolvedCompanyId);
        $result = (bool) ($map[$objectKey][$action] ?? false);

        // 3. Auditoria — grava acessos concedidos quando `audit` está ligado; os
        //    negados só quando `audit_denied` também está (conforme documentado).
        if (config('ptah.permissions.audit')) {
            if ($result || config('ptah.permissions.audit_denied')) {
                $this->writeAudit($userId, $resolvedCompanyId, $objectKey, $action, $result ? 'granted' : 'denied');
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isMaster(mixed $user = null): bool
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return false;
        }

        return $this->isMasterById($userId);
    }

    /**
     * Internal MASTER check by ID (cached).
     */
    protected function isMasterById(int $userId): bool
    {
        if ($this->cacheEnabled()) {
            // Versioned key: a role becoming/ceasing to be MASTER (global bump) or
            // the user's assignments changing (user bump) invalidates it at once.
            return (bool) Cache::remember(
                $this->cacheKey('is_master', $userId, null),
                $this->ttl(),
                fn () => $this->queryIsMaster($userId)
            );
        }

        return $this->queryIsMaster($userId);
    }

    protected function queryIsMaster(int $userId): bool
    {
        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('is_master', true)->where('is_active', true))
            ->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions(mixed $user = null, ?int $companyId = null): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        if ($this->isMasterById($userId)) {
            // MASTER: devolve mapa "tudo liberado" dos objetos cadastrados (cached)
            if ($this->cacheEnabled()) {
                return Cache::remember(
                    "ptah_master_map:g{$this->globalVersion()}",
                    $this->ttl(),
                    fn () => $this->buildMasterPermissionMap()
                );
            }

            return $this->buildMasterPermissionMap();
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);

        if ($this->cacheEnabled()) {
            $key = $this->cacheKey('perms_map', $userId, $resolvedCompanyId);

            return Cache::remember($key, $this->ttl(), fn () => $this->buildPermissionMap($userId, $resolvedCompanyId));
        }

        return $this->buildPermissionMap($userId, $resolvedCompanyId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCompaniesForResource(mixed $user, string $objectKey, string $action): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        // Whitelist before interpolating into the column name.
        $action = strtolower($action);
        if (! in_array($action, self::ACTIONS, true)) {
            return [];
        }

        $actionColumn = "can_{$action}";

        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('permissions', fn ($q2) => $q2
                    ->where($actionColumn, true)
                    ->whereHas('pageObject', fn ($q3) => $q3->where('obj_key', $objectKey))
                )
            )
            ->pluck('company_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function syncRole(mixed $user, int $roleId, array $companyIds = []): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return;
        }

        if (empty($companyIds)) {
            $this->upsertUserRole($userId, $roleId, null);
        } else {
            foreach ($companyIds as $companyId) {
                $this->upsertUserRole($userId, $roleId, (int) $companyId);
            }
        }

        $this->clearCache($user);
    }

    /**
     * Creates or (re)activates a user↔role assignment, restoring it if it was
     * soft-deleted. Uses the SoftDeletes API (restore) rather than a mass-assigned
     * `deleted_at => null` — the latter is silently dropped (not fillable), leaving
     * the assignment trashed.
     */
    protected function upsertUserRole(int $userId, int $roleId, ?int $companyId): void
    {
        $ur = UserRole::withTrashed()->firstOrNew([
            'user_id' => $userId,
            'role_id' => $roleId,
            'company_id' => $companyId,
        ]);

        if ($ur->trashed()) {
            $ur->restore(); // clears deleted_at via the SoftDeletes API
        }

        $ur->is_active = true;
        $ur->save();
    }

    /**
     * {@inheritdoc}
     */
    public function detachRole(mixed $user, int $roleId, ?int $companyId = null): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return;
        }

        $query = UserRole::where('user_id', $userId)->where('role_id', $roleId);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $query->delete(); // SoftDelete

        $this->clearCache($user, $companyId);
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(mixed $user = null, ?int $companyId = null): void
    {
        // Global flush: bump the global generation. Every cached map/master flag
        // for every user (any company) becomes unreachable at once.
        if ($user === null) {
            $this->bumpGlobalVersion();

            return;
        }

        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return;
        }

        // Per-user flush: bump this user's generation. Clears the master flag and
        // every company-scoped map for the user in one shot — no per-company
        // enumeration, no dependency on cache tags or the underlying driver.
        $this->bumpUserVersion($userId);
    }

    // ─────────────────────────────────────────
    // Internal DB queries
    // ─────────────────────────────────────────

    /**
     * Direct DB query to check whether a user has permission for an action on an object.
     *
     * @internal Kept for use in subclasses that need a point query without the map.
     *           The check() method uses getPermissions() (cached map) as the source of truth.
     */
    protected function queryPermission(int $userId, ?int $companyId, string $objectKey, string $actionColumn): bool
    {
        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->forCompany($companyId)
            ->whereHas('role', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('permissions', fn ($q2) => $q2
                    ->where($actionColumn, true)
                    ->whereNull('deleted_at')
                    ->whereHas('pageObject', fn ($q3) => $q3
                        ->where('obj_key', $objectKey)
                        ->where('is_active', true)
                    )
                )
            )
            ->exists();
    }

    /**
     * Builds the full permissions map: [ 'obj_key' => ['create'=>bool, ...] ]
     */
    protected function buildPermissionMap(int $userId, ?int $companyId): array
    {
        $rows = UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->forCompany($companyId)
            // Only ACTIVE roles grant — consistent with isMaster()/queryPermission().
            // Without this, deactivating a role would NOT revoke access via check().
            ->whereHas('role', fn ($q) => $q->where('is_active', true))
            ->with([
                'role.permissions' => fn ($q) => $q->whereNull('deleted_at'),
                'role.permissions.pageObject',
            ])
            ->get()
            ->flatMap(fn (UserRole $ur) => $ur->role->permissions ?? collect());

        $map = [];

        foreach ($rows as $perm) {
            $key = $perm->pageObject?->obj_key ?? null;
            if (! $key) {
                continue;
            }

            if (! isset($map[$key])) {
                $map[$key] = ['create' => false, 'read' => false, 'update' => false, 'delete' => false];
            }

            // OR logic: if any role grants, consider it granted
            $map[$key]['create'] = $map[$key]['create'] || $perm->can_create;
            $map[$key]['read'] = $map[$key]['read'] || $perm->can_read;
            $map[$key]['update'] = $map[$key]['update'] || $perm->can_update;
            $map[$key]['delete'] = $map[$key]['delete'] || $perm->can_delete;
        }

        return $map;
    }

    /**
     * MASTER map: all registered objects with all flags set to true.
     */
    protected function buildMasterPermissionMap(): array
    {
        return PageObject::query()
            ->active()
            ->pluck('obj_key')
            ->unique()
            ->mapWithKeys(fn ($key) => [
                $key => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────
    // Audit
    // ─────────────────────────────────────────

    protected function writeAudit(int $userId, ?int $companyId, string $resourceKey, string $action, string $result): void
    {
        try {
            PermissionAudit::create([
                'user_id' => $userId,
                'company_id' => $companyId,
                'resource_key' => $resourceKey,
                'action' => $action,
                'result' => $result,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'context' => [
                    'uri' => Request::getRequestUri(),
                    'method' => Request::method(),
                ],
            ]);
        } catch (\Throwable) {
            // Never bring the application down due to an audit log failure
        }
    }
}

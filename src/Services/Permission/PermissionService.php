<?php

declare(strict_types=1);

namespace Ptah\Services\Permission;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Ptah\Contracts\PermissionServiceContract;
use Ptah\Models\PageObject;
use Ptah\Models\PermissionAudit;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
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

    /**
     * The only valid actions — whitelisted before any query interpolation
     * (each one is turned into a `can_{action}` column name).
     */
    public const ACTIONS = ['create', 'read', 'update', 'delete'];

    /**
     * Separator used to disambiguate an `obj_key` that collides across
     * different pages (see `ConfigDoctorCommand`'s "obj_key collision"
     * check). A qualified key takes the form `{page.slug}::{obj_key}` or,
     * to disambiguate within the same page, `{page.slug}::{section}::{obj_key}`.
     * The BARE key resolution (buildPermissionMap/getPermissions) is
     * untouched and remains the primary, backward-compatible lookup —
     * qualified keys are only consulted when the bare lookup misses AND the
     * key actually contains this qualifier (see `check()`).
     */
    public const KEY_QUALIFIER = '::';

    /**
     * Guard against unbounded growth on a long-running worker (Octane): once
     * the request memo holds this many entries, it is wiped instead of kept
     * growing. A single request legitimately touching more than 100 distinct
     * (user, company) permission lookups is not a realistic case this memo
     * needs to optimise for.
     */
    protected const MEMO_MAX = 100;

    /**
     * Request-scoped memo — since this class is bound as a singleton, one
     * instance lives for the whole request/job (or, on Octane, several).
     * Bounded, and fully cleared on any global/user cache-generation bump.
     *
     * @var array<string, mixed>
     */
    protected array $requestMemo = [];

    /**
     * Memoizes the result of $resolver() for the given key, for the lifetime
     * of this instance. Does NOT itself read/write the generation counters —
     * callers key the memo with an already-resolved (fresh) cache key, so a
     * generation bump (from ANY PermissionService instance, since the
     * counters live in the shared Cache store) changes the key on the very
     * next call and is picked up immediately, exactly like the underlying
     * Cache::remember() calls already are. This is what lets a mid-request
     * revocation still be seen by the next check() (see
     * PermissionServiceTest::revoking_a_permission_takes_effect_immediately,
     * which predates this memo and must keep passing unmodified).
     *
     * Limitation: a generation bump from a genuinely different OS
     * process/worker only becomes visible on this instance's NEXT request —
     * within the same request there is nothing to memoize across processes.
     */
    protected function memo(string $key, \Closure $resolver): mixed
    {
        if (array_key_exists($key, $this->requestMemo)) {
            return $this->requestMemo[$key];
        }

        if (count($this->requestMemo) >= self::MEMO_MAX) {
            $this->requestMemo = [];
        }

        return $this->requestMemo[$key] = $resolver();
    }

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
        $this->requestMemo = [];
    }

    /** Invalidates every cached entry for a single user across all companies. */
    protected function bumpUserVersion(int $userId): void
    {
        $key = "ptah_perm_uver:{$userId}";
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }
        Cache::increment($key);
        $this->requestMemo = [];
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

        if (isset($map[$objectKey])) {
            // Literal (bare) match takes precedence — an obj_key containing
            // "::" literally (unusual, but not forbidden) resolves here first,
            // never falling through to the qualified map by accident.
            $result = (bool) $map[$objectKey][$action];
        } elseif (str_contains($objectKey, self::KEY_QUALIFIER)) {
            // 2b. Bare lookup missed AND the caller passed a qualified key
            //     (page::obj_key or page::section::obj_key) — consult the
            //     qualified map. Without this, a colliding obj_key (see
            //     ConfigDoctorCommand's "obj_key collision" check) has no way
            //     to be granted unambiguously.
            $qmap = $this->getQualifiedPermissions($user, $resolvedCompanyId);
            $result = (bool) ($qmap[$objectKey][$action] ?? false);
        } else {
            $result = false;
        }

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
     * Returns the (active) role names bound to the user in the given company
     * scope — identity information, NOT a gate. Mirrors the same activity
     * rules as the permission map: only an active `UserRole` (forCompany)
     * bound to an active `Role` counts.
     *
     * @return string[]
     */
    public function getRoleNames(mixed $user = null, ?int $companyId = null): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);
        $memoKey = $this->cacheKey('role_names', $userId, $resolvedCompanyId);

        return $this->memo($memoKey, function () use ($userId, $resolvedCompanyId, $memoKey) {
            if ($this->cacheEnabled()) {
                return Cache::remember($memoKey, $this->ttl(), fn () => $this->queryRoleNames($userId, $resolvedCompanyId));
            }

            return $this->queryRoleNames($userId, $resolvedCompanyId);
        });
    }

    /**
     * @return string[]
     */
    protected function queryRoleNames(int $userId, ?int $companyId): array
    {
        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->forCompany($companyId)
            ->whereHas('role', fn ($q) => $q->where('is_active', true))
            ->with('role:id,name')
            ->get()
            ->pluck('role.name')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Checks whether the user holds (at least one of) the given role name(s)
     * — a tolerant string match (case-insensitive, trimmed, and also compared
     * via `Str::slug()` on both sides so "Vendas Externas" matches
     * "vendas-externas"). `$roles` as an array is an OR.
     *
     * This is IDENTITY, not a GATE: unlike `check()`, MASTER does **not**
     * short-circuit here — a master user does not "have" every role name,
     * they only bypass every permission check. Do not use this to authorize
     * an action; use `ptah_can()` / `check()` for that.
     */
    public function hasRole(mixed $user, string|array $roles, ?int $companyId = null): bool
    {
        $wanted = is_array($roles) ? $roles : [$roles];
        $current = $this->getRoleNames($user, $companyId);

        foreach ($wanted as $want) {
            foreach ($current as $have) {
                if ($this->roleNamesMatch($want, $have)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Role-name match: equal once both are lower-cased and trimmed — and
     * nothing looser. The first version also matched on Str::slug equality,
     * which collapses SEPARATORS into an equivalence class: "Vendas-SP" and
     * "vendas sp" would name the same role, and two distinct roles differing
     * only by separator would collide (review finding). hasRole() is identity
     * — host apps branch behavior on it — so a wrong-role match is a wrong
     * branch. Tightening later would be breaking; loosening later is not.
     */
    protected function roleNamesMatch(string $a, string $b): bool
    {
        $aTrim = mb_strtolower(trim($a));
        $bTrim = mb_strtolower(trim($b));

        if ($aTrim === '' || $bTrim === '') {
            return false;
        }

        return $aTrim === $bTrim;
    }

    /**
     * Internal MASTER check by ID (cached).
     */
    protected function isMasterById(int $userId): bool
    {
        $memoKey = $this->cacheKey('is_master', $userId, null);

        return $this->memo($memoKey, function () use ($userId, $memoKey) {
            if ($this->cacheEnabled()) {
                // Versioned key: a role becoming/ceasing to be MASTER (global bump) or
                // the user's assignments changing (user bump) invalidates it at once.
                return (bool) Cache::remember(
                    $memoKey,
                    $this->ttl(),
                    fn () => $this->queryIsMaster($userId)
                );
            }

            return $this->queryIsMaster($userId);
        });
    }

    /**
     * MASTER is GLOBAL by definition — this query deliberately does NOT filter
     * by `company_id`. A `UserRole` binding's `company_id` is irrelevant to
     * whether that user is MASTER: holding a `role.is_master = true`
     * assignment in ANY company (or none) grants MASTER everywhere, for
     * every company, in this and every other check()/getPermissions() call.
     *
     * This was a deliberate ratification, not an oversight: scoping MASTER by
     * company_id would be a breaking change that revokes access for every
     * existing MASTER binding created for a single company under the
     * (incorrect) assumption that it scoped anything — including this
     * package's own reference environment (`user=1, company_id=1,
     * role=MASTER`). Hosts who need a company-scoped "super admin" should
     * model it as a REGULAR role with every object granted, not MASTER.
     *
     * Because a `company_id` on a MASTER binding is silently ignored here,
     * `upsertUserRole()` below normalises new/updated MASTER bindings to
     * `company_id = null` at write time (so the stored data matches what this
     * method actually does), and `ptah:config:doctor` flags any PRE-EXISTING
     * MASTER binding that still carries a company_id as a security alert.
     */
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
            $memoKey = "ptah_master_map:g{$this->globalVersion()}";

            return $this->memo($memoKey, function () use ($memoKey) {
                if ($this->cacheEnabled()) {
                    return Cache::remember($memoKey, $this->ttl(), fn () => $this->buildMasterPermissionMap());
                }

                return $this->buildMasterPermissionMap();
            });
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);
        $memoKey = $this->cacheKey('perms_map', $userId, $resolvedCompanyId);

        return $this->memo($memoKey, function () use ($userId, $resolvedCompanyId, $memoKey) {
            if ($this->cacheEnabled()) {
                return Cache::remember($memoKey, $this->ttl(), fn () => $this->buildPermissionMap($userId, $resolvedCompanyId));
            }

            return $this->buildPermissionMap($userId, $resolvedCompanyId);
        });
    }

    /**
     * Mirrors `getPermissions()` but returns the QUALIFIED map (keyed by
     * `{page.slug}::{obj_key}` / `{page.slug}::{section}::{obj_key}` — see
     * `buildQualifiedPermissionMap()`). Used by `check()` as a fallback when
     * the bare `obj_key` lookup misses and the requested key is itself
     * qualified.
     *
     * Includes the MASTER arm deliberately — returning `[]` for master would
     * be a silent no-op (every qualified lookup would then miss for a user
     * who should pass everything).
     *
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    public function getQualifiedPermissions(mixed $user = null, ?int $companyId = null): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        if ($this->isMasterById($userId)) {
            $memoKey = "ptah_master_qmap:g{$this->globalVersion()}";

            return $this->memo($memoKey, function () use ($memoKey) {
                if ($this->cacheEnabled()) {
                    return Cache::remember($memoKey, $this->ttl(), fn () => $this->buildMasterQualifiedPermissionMap());
                }

                return $this->buildMasterQualifiedPermissionMap();
            });
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);
        $memoKey = $this->cacheKey('perms_qmap', $userId, $resolvedCompanyId);

        return $this->memo($memoKey, function () use ($userId, $resolvedCompanyId, $memoKey) {
            if ($this->cacheEnabled()) {
                return Cache::remember($memoKey, $this->ttl(), fn () => $this->buildQualifiedPermissionMap($userId, $resolvedCompanyId));
            }

            return $this->buildQualifiedPermissionMap($userId, $resolvedCompanyId);
        });
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

        // A qualified key (page::obj_key or page::section::obj_key — see
        // check()/buildQualifiedPermissionMap()) must decompose into the same
        // page/section restriction the qualified map applies, otherwise this
        // method silently returns [] for any resource only reachable through
        // its qualified form (an obj_key colliding across pages).
        [$bareObjKey, $pageSlug, $section] = $this->decomposeQualifiedKey($objectKey);

        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('permissions', fn ($q2) => $q2
                    ->where($actionColumn, true)
                    // Same activity rule the permission maps apply: a deactivated
                    // object — or one whose page is deactivated — grants nothing, so
                    // it must not contribute companies either. Without this, a
                    // company selector built from this method still offers branches
                    // for a resource the gate will refuse.
                    ->whereHas('pageObject', function ($q3) use ($bareObjKey, $pageSlug, $section) {
                        $q3->where('obj_key', $bareObjKey)
                            ->where('is_active', true)
                            ->whereHas('page', function ($q4) use ($pageSlug) {
                                $q4->where('is_active', true);
                                if ($pageSlug !== null) {
                                    $q4->where('slug', $pageSlug);
                                }
                            });

                        if ($section !== null) {
                            $q3->where('section', $section);
                        }
                    })
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
     * Decomposes a possibly-qualified object key into
     * [bareObjKey, pageSlug|null, section|null]. A bare key (no
     * `KEY_QUALIFIER`) yields [$objectKey, null, null].
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    protected function decomposeQualifiedKey(string $objectKey): array
    {
        if (! str_contains($objectKey, self::KEY_QUALIFIER)) {
            return [$objectKey, null, null];
        }

        // Literal-primeiro, a MESMA ordem que check() aplica no mapa bare: um
        // obj_key legitimo que contenha '::' nunca e decomposto — sem isso,
        // getCompaniesForResource filtrava por um page.slug inexistente e
        // devolvia [] silencioso para um recurso real (achado de revisao).
        if (PageObject::query()->where('obj_key', $objectKey)->exists()) {
            return [$objectKey, null, null];
        }

        $parts = explode(self::KEY_QUALIFIER, $objectKey);

        if (count($parts) >= 3) {
            // page::section::obj_key — obj_key may itself contain further
            // qualifier separators, so re-join everything after the first two.
            return [implode(self::KEY_QUALIFIER, array_slice($parts, 2)), $parts[0], $parts[1]];
        }

        // page::obj_key
        return [$parts[1], $parts[0], null];
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
     *
     * MASTER is global (see `queryIsMaster()`) — a `company_id` on a MASTER
     * binding is never actually enforced, it only misleads whoever created it
     * into believing access is scoped to that company. Rather than throwing
     * (which would break an existing seeder that happens to pass a
     * `company_id` alongside a MASTER role), the scope is silently normalised
     * to `null` here — matching what `queryIsMaster()` already does in
     * practice — and logged, so the caller has a trail explaining why the
     * stored row differs from what was requested.
     */
    protected function upsertUserRole(int $userId, int $roleId, ?int $companyId): void
    {
        $role = $companyId !== null ? Role::find($roleId) : null;

        if ($role !== null && $role->is_master) {
            Log::warning('Ptah: company_id ignored for a MASTER role binding — MASTER is global by definition.', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'company_id' => $companyId,
            ]);

            $companyId = null;
        }

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
     * Returns the raw, still-ungrouped `RolePermission` rows that grant
     * something to this user in this company scope — the same activity
     * rules `buildPermissionMap()` (bare map) and `buildQualifiedPermissionMap()`
     * (qualified map) both fold into their respective maps. Extracted so both
     * builders share a single source of truth for "what counts as a grant".
     *
     * @param  bool  $withPage  Eager-load `role.permissions.pageObject.page` (needed
     *                          to qualify a key by page slug/section); the bare map
     *                          doesn't need it.
     * @return Collection<int, mixed>
     */
    protected function grantRowsFor(int $userId, ?int $companyId, bool $withPage = false): Collection
    {
        $pageObjectRelation = $withPage ? 'role.permissions.pageObject.page' : 'role.permissions.pageObject';

        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->forCompany($companyId)
            // Only ACTIVE roles grant — consistent with isMaster()/queryPermission().
            // Without this, deactivating a role would NOT revoke access via check().
            ->whereHas('role', fn ($q) => $q->where('is_active', true))
            ->with([
                // Only permissions bound to an ACTIVE object on an ACTIVE page grant —
                // consistent with queryPermission() and with buildMasterPermissionMap().
                // Without this, deactivating an object/page from /ptah-pages would not
                // revoke access via check().
                'role.permissions' => fn ($q) => $q->whereNull('deleted_at')
                    ->whereHas('pageObject', fn ($q2) => $q2->where('is_active', true)
                        ->whereHas('page', fn ($q3) => $q3->where('is_active', true))
                    ),
                $pageObjectRelation,
            ])
            ->get()
            ->flatMap(fn (UserRole $ur) => $ur->role->permissions ?? collect());
    }

    /**
     * Builds the full permissions map: [ 'obj_key' => ['create'=>bool, ...] ]
     */
    protected function buildPermissionMap(int $userId, ?int $companyId): array
    {
        $rows = $this->grantRowsFor($userId, $companyId);

        $map = [];

        foreach ($rows as $perm) {
            $key = $perm->pageObject?->obj_key ?? null;
            if (! $key) {
                continue;
            }

            if (! isset($map[$key])) {
                $map[$key] = array_fill_keys(self::ACTIONS, false);
            }

            // OR logic: if any role grants, consider it granted. Derived from
            // ACTIONS (rather than hardcoding the four CRUD flags) so a new
            // whitelisted verb is picked up here automatically instead of
            // silently staying `false` for everyone but MASTER.
            foreach (self::ACTIONS as $action) {
                $map[$key][$action] = $map[$key][$action] || (bool) $perm->{"can_{$action}"};
            }
        }

        return $map;
    }

    /**
     * Builds the QUALIFIED permissions map — same grants as `buildPermissionMap()`,
     * but keyed by `{page.slug}::{obj_key}` AND `{page.slug}::{section}::{obj_key}`
     * instead of (or in addition to, via `getQualifiedPermissions()`) the bare
     * `obj_key`. Disambiguates the case where the same `obj_key` is registered
     * on more than one page (see `ConfigDoctorCommand`'s "obj_key collision"
     * check) — `check()` only consults this map when the bare lookup misses.
     *
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    protected function buildQualifiedPermissionMap(int $userId, ?int $companyId): array
    {
        $rows = $this->grantRowsFor($userId, $companyId, withPage: true);

        $map = [];

        foreach ($rows as $perm) {
            // The `pageObject`/`page` relations have no PHPDoc generics
            // (matching the rest of this codebase), so static analysis only
            // knows them as a plain Model — narrow explicitly rather than
            // chaining `?->`.
            $pageObject = $perm->pageObject;
            if (! $pageObject instanceof PageObject) {
                continue;
            }

            $page = $pageObject->page;
            $pageSlug = $page instanceof PtahPage ? $page->slug : null;
            $objKey = $pageObject->obj_key;
            $section = $pageObject->section;

            if (! $objKey || ! $pageSlug) {
                continue;
            }

            $keys = [$pageSlug.self::KEY_QUALIFIER.$objKey];
            if ($section !== '') {
                $keys[] = $pageSlug.self::KEY_QUALIFIER.$section.self::KEY_QUALIFIER.$objKey;
            }

            foreach ($keys as $key) {
                if (! isset($map[$key])) {
                    $map[$key] = array_fill_keys(self::ACTIONS, false);
                }

                // Same OR-merge as buildPermissionMap().
                foreach (self::ACTIONS as $action) {
                    $map[$key][$action] = $map[$key][$action] || (bool) $perm->{"can_{$action}"};
                }
            }
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
            // An inactive page deactivates all of its objects too — keeps the
            // MASTER map consistent with buildPermissionMap()'s pageObject+page check.
            ->whereHas('page', fn ($q) => $q->where('is_active', true))
            ->pluck('obj_key')
            ->unique()
            // Derived from ACTIONS (not the 4 CRUD flags hardcoded) — a MASTER
            // must pass every whitelisted verb, including `manage`, otherwise
            // buildPermissionMap()/buildMasterPermissionMap() drift out of sync
            // the moment a new action is added.
            ->mapWithKeys(fn ($key) => [
                $key => array_fill_keys(self::ACTIONS, true),
            ])
            ->toArray();
    }

    /**
     * MASTER qualified map: same source (active `PageObject` on an active
     * `PtahPage`) as `buildMasterPermissionMap()`, but keyed by
     * `{page.slug}::{obj_key}` and `{page.slug}::{section}::{obj_key}` — the
     * qualified-key equivalent of the MASTER bypass, so a qualified lookup
     * for a MASTER user doesn't silently miss.
     */
    protected function buildMasterQualifiedPermissionMap(): array
    {
        $map = [];

        PageObject::query()
            ->active()
            ->whereHas('page', fn ($q) => $q->where('is_active', true))
            ->with('page:id,slug')
            ->get()
            ->each(function (PageObject $obj) use (&$map) {
                $page = $obj->page;
                if (! $page instanceof PtahPage) {
                    return;
                }

                $pageSlug = $page->slug;
                $map[$pageSlug.self::KEY_QUALIFIER.$obj->obj_key] = array_fill_keys(self::ACTIONS, true);

                if ($obj->section !== '') {
                    $map[$pageSlug.self::KEY_QUALIFIER.$obj->section.self::KEY_QUALIFIER.$obj->obj_key] = array_fill_keys(self::ACTIONS, true);
                }
            });

        return $map;
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

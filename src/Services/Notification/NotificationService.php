<?php

declare(strict_types=1);

namespace Ptah\Services\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Ptah\Models\Notification;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Traits\ResolvesUser;
use Throwable;

/**
 * "Baterias inclusas" notification center — push/read/dismiss on the opt-in
 * `ptah_notifications` table (see database/migrations/…create_ptah_notifications_table.php).
 * The package does not use a BaseService internally, so this is a plain class.
 *
 * Every read/write goes through {@see tableExists()} first, which is gated by
 * `ptah.notifications.enabled`: with the module off (the default), this
 * service never issues a query — a consumer who only wants the Camada 1
 * navbar slot pays zero cost.
 */
class NotificationService
{
    use ResolvesUser;

    /**
     * Allowlist of $data keys accepted by push()/pushMany()/toRole()/toAll().
     * `user_id` is deliberately absent: the recipient is always the resolved
     * server-side argument, never something the caller's $data can override
     * (the exact IDOR shape fixed for revokeSession in v1.16.0).
     *
     * @var list<string>
     */
    private const ALLOWED_DATA_KEYS = [
        'type', 'category', 'title', 'body', 'icon', 'url', 'action_label', 'dedupe_key', 'company_id',
    ];

    /**
     * Memoizes tableExists() across calls within the same process. Reset via
     * {@see forgetTableExistsCache()} — needed both by the test suite (a
     * fresh Testbench app can toggle the config/schema between tests that
     * still share this PHP process) and by any long-running worker (Octane,
     * a queue worker) that enables the module and runs the opt-in migration
     * without restarting.
     */
    private static ?bool $tableExistsMemo = null;

    public function __construct(
        protected PermissionService $permissions = new PermissionService
    ) {}

    /**
     * Resets the memoized tableExists() result.
     */
    public static function forgetTableExistsCache(): void
    {
        self::$tableExistsMemo = null;
    }

    /**
     * Creates (or, when `dedupe_key` is present, updates) a notification for
     * one user. Returns null when the module/table is unavailable.
     *
     * @param  array<string, mixed>  $data  type,category,title,body,icon,url,action_label,dedupe_key,company_id
     */
    public function push(int $userId, array $data): ?Notification
    {
        if (! $this->tableExists()) {
            return null;
        }

        $payload = $this->filterData($data);
        $payload['user_id'] = $userId;

        $dedupeKey = $payload['dedupe_key'] ?? null;

        // A null dedupe_key must go through plain create(): updateOrCreate()'s
        // WHERE would match `dedupe_key IS NULL`, which is TRUE for every
        // untagged notification of this user — the 2nd untagged push would
        // silently overwrite the 1st instead of adding a new row.
        if ($dedupeKey === null) {
            return Notification::create($payload);
        }

        return Notification::updateOrCreate(
            ['user_id' => $userId, 'dedupe_key' => $dedupeKey],
            $payload
        );
    }

    /**
     * Broadcasts the same $data to several users. Returns how many rows were
     * actually written (push() returning null — table unavailable — does not
     * count, but for that case every iteration returns null so the count is
     * simply 0).
     *
     * @param  iterable<int, int|string>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function pushMany(iterable $userIds, array $data): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $count = 0;

        foreach ($userIds as $userId) {
            if ($this->push((int) $userId, $data) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function toUser(int $userId, array $data): int
    {
        return $this->push($userId, $data) !== null ? 1 : 0;
    }

    /**
     * Notifies every user with an ACTIVE assignment of an active role named
     * $roleName (tolerant match via PermissionService::hasRole — same
     * case/trim tolerance host code already relies on), optionally scoped to
     * a company (see UserRole::scopeForCompany semantics). Returns 0 without
     * querying when the permissions module's tables are unavailable.
     *
     * @param  array<string, mixed>  $data
     */
    public function toRole(string $roleName, array $data, ?int $companyId = null): int
    {
        if (! $this->tableExists() || ! $this->userRolesTableExists()) {
            return 0;
        }

        $userIds = UserRole::query()
            ->active()
            ->forCompany($companyId)
            ->whereHas('role', fn ($q) => $q->where('is_active', true))
            ->distinct()
            ->pluck('user_id')
            ->filter(fn ($userId) => $this->permissions->hasRole((int) $userId, $roleName, $companyId))
            ->values();

        return $this->pushMany($userIds, $this->withCompany($data, $companyId));
    }

    /**
     * Notifies every "staff" user (onlyStaff = true — anyone holding an
     * active role, optionally scoped to a company) or, when onlyStaff is
     * false, every user of the host application's user model
     * (`ptah.permissions.user_model`), regardless of role.
     *
     * @param  array<string, mixed>  $data
     */
    public function toAll(array $data, ?int $companyId = null, bool $onlyStaff = true): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $payload = $this->withCompany($data, $companyId);

        if ($onlyStaff) {
            if (! $this->userRolesTableExists()) {
                return 0;
            }

            $userIds = UserRole::query()
                ->active()
                ->forCompany($companyId)
                ->whereHas('role', fn ($q) => $q->where('is_active', true))
                ->distinct()
                ->pluck('user_id');

            return $this->pushMany($userIds, $payload);
        }

        /** @var class-string<Model>|mixed $modelClass */
        $modelClass = config('ptah.permissions.user_model', 'App\Models\User');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return 0;
        }

        $count = 0;

        $modelClass::query()->chunkById(500, function (Collection $users) use (&$count, $payload) {
            foreach ($users as $user) {
                $userId = $this->resolveUserId($user);

                if ($userId !== null && $this->push($userId, $payload) !== null) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function unreadCount(?int $userId = null, ?int $companyId = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $userId ??= $this->resolveUserId(null);

        if ($userId === null) {
            return 0;
        }

        return Notification::query()
            ->forUser($userId)
            ->forCompany($companyId)
            ->unread()
            ->active()
            ->count();
    }

    /**
     * Active (non-dismissed), most recent notifications for the dropdown.
     */
    public function list(?int $userId = null, ?int $companyId = null, int $limit = 20): Collection
    {
        if (! $this->tableExists()) {
            return new Collection;
        }

        $userId ??= $this->resolveUserId(null);

        if ($userId === null) {
            return new Collection;
        }

        return Notification::query()
            ->forUser($userId)
            ->forCompany($companyId)
            ->active()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Paginated history for the "all notifications" screen.
     *
     * @param  array{unread_only?: bool, category?: string, search?: string, include_dismissed?: bool}  $filters
     */
    public function paginate(?int $userId, ?int $companyId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);

        if (! $this->tableExists()) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $userId ??= $this->resolveUserId(null);

        if ($userId === null) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $query = Notification::query()->forUser($userId)->forCompany($companyId);

        if (empty($filters['include_dismissed'])) {
            $query->active();
        }

        if (! empty($filters['unread_only'])) {
            $query->unread();
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Marks one notification as read. The owner is always resolved
     * server-side and put in the WHERE clause — never trust the caller's
     * $id alone (the IDOR shape fixed for revokeSession in v1.16.0).
     */
    public function markRead(int $id, ?int $userId = null): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $userId ??= $this->resolveUserId(null);

        if ($userId === null) {
            return false;
        }

        return Notification::query()
            ->whereKey($id)
            ->where('user_id', $userId)
            ->update(['read_at' => now()]) > 0;
    }

    public function markAllRead(?int $userId = null, ?int $companyId = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $userId ??= $this->resolveUserId(null);

        if ($userId === null) {
            return 0;
        }

        return Notification::query()
            ->forUser($userId)
            ->forCompany($companyId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Soft-hides one notification. Same owner-in-WHERE guard as markRead().
     */
    public function dismiss(int $id, ?int $userId = null): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $userId ??= $this->resolveUserId(null);

        if ($userId === null) {
            return false;
        }

        return Notification::query()
            ->whereKey($id)
            ->where('user_id', $userId)
            ->update(['dismissed_at' => now()]) > 0;
    }

    /**
     * Deletes READ notifications older than $days (measured from `read_at`).
     * Unread notifications are never pruned by this method, regardless of
     * age. Portable, chunked delete-by-id — same idiom as AuditPruneCommand.
     */
    public function purgeRead(int $days = 30, int $chunk = 1000, bool $dryRun = false): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $cutoff = now()->subDays(max(0, $days));
        $query = fn () => Notification::query()->whereNotNull('read_at')->where('read_at', '<', $cutoff);

        if ($dryRun) {
            return $query()->count();
        }

        $chunk = max(1, $chunk);
        $deleted = 0;

        while (true) {
            $ids = $query()->orderBy('id')->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            Notification::query()->whereKey($ids)->delete();
            $deleted += $ids->count();

            if ($ids->count() < $chunk) {
                break;
            }
        }

        return $deleted;
    }

    /**
     * Whether the opt-in `ptah_notifications` table can be queried: the
     * module must be enabled AND the consumer must have published + migrated
     * the table. Checked with Schema::hasTable() (not a query wrapped in a
     * QueryException catch): a raw "table missing" SQL error would pollute
     * logs on every request and, on PostgreSQL, aborts the whole request's
     * transaction — hasTable() never touches the failing table itself.
     */
    public function tableExists(): bool
    {
        if (! config('ptah.notifications.enabled')) {
            return false;
        }

        if (self::$tableExistsMemo !== null) {
            return self::$tableExistsMemo;
        }

        try {
            self::$tableExistsMemo = Schema::hasTable('ptah_notifications');
        } catch (Throwable) {
            self::$tableExistsMemo = false;
        }

        return self::$tableExistsMemo;
    }

    /**
     * Neutralises dangerous URL schemes (javascript:/data:/vbscript:) at
     * RENDER/REDIRECT time — not at write time — so a row already written by
     * a careless consumer is still neutralised wherever it is displayed.
     * Same regex as HasCrudRenderers::renderLink() (colsRendererLinkTemplate).
     */
    public static function safeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url)) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterData(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ALLOWED_DATA_KEYS));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCompany(array $data, ?int $companyId): array
    {
        if (! array_key_exists('company_id', $data)) {
            $data['company_id'] = $companyId;
        }

        return $data;
    }

    private function userRolesTableExists(): bool
    {
        try {
            return Schema::hasTable('ptah_user_roles');
        } catch (Throwable) {
            return false;
        }
    }
}

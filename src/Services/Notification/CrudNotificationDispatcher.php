<?php

declare(strict_types=1);

namespace Ptah\Services\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Ptah\Jobs\SendCrudNotificationJob;
use Ptah\Models\CrudConfig;
use Ptah\Services\Permission\ColumnPermissionService;
use Ptah\Support\ModelKey;
use Ptah\Traits\SendsCrudNotifications;

/**
 * Turns a model's created/updated/deleted event into queued notifications,
 * driven entirely by the `notifications.rules` section a consumer configured
 * through the visual CrudConfig editor (Notifications tab) — no code, no new
 * artisan flags. Bound as a singleton (see PtahServiceProvider); called from
 * {@see SendsCrudNotifications::bootSendsCrudNotifications()}.
 *
 * Gated by `ptah.notifications.enabled` — same switch NotificationService
 * itself uses, so a consumer who never opts into the notification module
 * never pays for this either (zero query).
 */
class CrudNotificationDispatcher
{
    /**
     * Reentrancy latch: a cascading save triggered WHILE this dispatcher is
     * already processing an outer event (e.g. a lifecycle hook that saves a
     * related model sharing the trait) must not fire its own notifications —
     * only the outermost event does. Shared across every model, not
     * per-class, since the risk is any nested save during the same call
     * stack, not just the same model re-entering itself.
     */
    private static bool $dispatching = false;

    /**
     * Memoizes the winning CrudConfig row per model FQCN for the lifetime of
     * the process — both rulesFor() and the placeholder allowlist read from
     * the SAME row, so this doubles as the allowlist's cache too.
     *
     * @var array<class-string, CrudConfig|null>
     */
    private static array $configMemo = [];

    /**
     * Resets the memoized CrudConfig lookup. Needed by the test suite (a
     * Testbench app can toggle config/DB rows between tests that still share
     * this PHP process).
     */
    public static function forgetMemo(): void
    {
        self::$configMemo = [];
    }

    public function dispatch(Model $model, string $event): void
    {
        if (self::$dispatching) {
            return;
        }

        if (! config('ptah.notifications.enabled')) {
            return;
        }

        $rules = $this->rulesFor($model::class);

        if ($rules === []) {
            return;
        }

        self::$dispatching = true;

        try {
            foreach ($rules as $rule) {
                if (! is_array($rule) || ($rule['event'] ?? '') !== $event) {
                    continue;
                }

                $this->dispatchRule($model, $rule);
            }
        } finally {
            self::$dispatching = false;
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function dispatchRule(Model $model, array $rule): void
    {
        $allowlist = $this->allowlistFor($model);

        // Title is truncated to the exact column width (string(180)) with an
        // empty truncation marker — a "…" suffix would push an already-180
        // char title past the limit, which a strict MySQL mode would reject.
        $title = Str::limit($this->resolveTemplate((string) ($rule['title'] ?? ''), $model, $allowlist), 180, '');

        if ($title === '') {
            return;
        }

        $body = $this->resolveTemplate((string) ($rule['body'] ?? ''), $model, $allowlist);
        $url = $this->resolveTemplate((string) ($rule['url'] ?? ''), $model, $allowlist);

        $payload = [
            'type' => $rule['type'] ?? 'info',
            'category' => ($rule['category'] ?? '') !== '' ? $rule['category'] : null,
            'title' => $title,
            'body' => $body !== '' ? $body : null,
            'icon' => ($rule['icon'] ?? '') !== '' ? $rule['icon'] : null,
            'url' => ($url !== '') ? $url : null,
            'action_label' => ($rule['actionLabel'] ?? '') !== '' ? $rule['actionLabel'] : null,
            'company_id' => $this->companyId($model),
        ];

        $notifySelf = (bool) ($rule['notifySelf'] ?? false);
        $exceptUserId = $notifySelf ? null : Auth::id();

        $job = SendCrudNotificationJob::dispatch(
            (string) ($rule['audience'] ?? 'user'),
            (string) ($rule['audienceValue'] ?? ''),
            $payload,
            $payload['company_id'],
            $exceptUserId,
        );

        // A host that runs no worker can force delivery inline for
        // notifications ONLY, without moving the whole application to `sync`.
        // Left null (the default) the application's own connection is used.
        // Note this is applied to the PendingDispatch, not inside the job:
        // `onConnection` there would be too late for `sync`, which resolves the
        // connection at dispatch time.
        $connection = config('ptah.notifications.queue_connection');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }
    }

    /**
     * Entries are NOT guaranteed to be arrays — this reads straight from the
     * `config` JSON column, which a human could hand-edit in the database.
     *
     * @return array<int, mixed>
     */
    private function rulesFor(string $modelClass): array
    {
        $row = $this->configRowFor($modelClass);

        if ($row === null) {
            return [];
        }

        $rules = $row->config['notifications']['rules'] ?? [];

        return is_array($rules) ? $rules : [];
    }

    /**
     * Placeholder allowlist: savable columns without a `colsPermission`
     * restriction, plus the model's own primary key — exactly what
     * CrudConfig::notificationPlaceholderOptions() offers in the editor.
     *
     * @return array<int, string>
     */
    private function allowlistFor(Model $model): array
    {
        $row = $this->configRowFor($model::class);
        $cols = $row?->config['cols'];

        $allowed = [];

        foreach ((is_array($cols) ? $cols : []) as $col) {
            if (! is_array($col)) {
                continue;
            }

            $name = $col['colsNomeFisico'] ?? '';

            if ($name === '' || ! empty($col[ColumnPermissionService::TAG])) {
                continue;
            }

            $allowed[] = $name;
        }

        $primaryKey = $model->getKeyName();

        // The primary key is allowed so `/orders/%id%` works even when the key
        // is not a configured column — but NOT when the config explicitly
        // restricts it. Forcing it in unconditionally was a real bypass: an
        // admin who tags the `id` column with colsPermission would still see
        // %id% resolved for every recipient, defeating the very gate this
        // allowlist exists to enforce (review finding). The restriction wins.
        if (! in_array($primaryKey, $allowed, true) && ! $this->columnIsRestricted($cols, $primaryKey)) {
            $allowed[] = $primaryKey;
        }

        return $allowed;
    }

    /**
     * Whether a configured column carries the restriction tag. A key with no
     * column entry at all is not restricted — there is nothing to gate.
     */
    private function columnIsRestricted(mixed $cols, string $field): bool
    {
        foreach ((is_array($cols) ? $cols : []) as $col) {
            if (is_array($col)
                && ($col['colsNomeFisico'] ?? '') === $field
                && ! empty($col[ColumnPermissionService::TAG])) {
                return true;
            }
        }

        return false;
    }

    private function resolveTemplate(string $template, Model $model, array $allowlist): string
    {
        if ($template === '') {
            return '';
        }

        return (string) preg_replace_callback('/%([a-zA-Z0-9_]+)%/', function (array $matches) use ($model, $allowlist) {
            $column = $matches[1];

            if (! in_array($column, $allowlist, true)) {
                return '';
            }

            $value = $model->getAttribute($column);

            return $value === null ? '' : (string) $value;
        }, $template);
    }

    /**
     * Same idiom as NotificationBell::companyId(): the record's own
     * company_id wins when the attribute exists (even a deleted model
     * instance still holds it in memory) — the session is only consulted
     * when the model has no such column at all.
     */
    private function companyId(Model $model): ?int
    {
        if (array_key_exists('company_id', $model->getAttributes())) {
            $value = $model->getAttribute('company_id');

            return $value !== null ? (int) $value : null;
        }

        if (! config('ptah.permissions.multi_company', true)) {
            return null;
        }

        $key = config('ptah.permissions.company_session_key', 'ptah_company_id');

        return $key && session()->has($key) ? (int) session($key) : null;
    }

    /**
     * Deterministic winner among every CrudConfig row for this model: the
     * first one — ordered by `route`, so the global config (route === '')
     * always sorts first — whose `notifications.rules` is non-empty.
     */
    private function configRowFor(string $modelClass): ?CrudConfig
    {
        if (array_key_exists($modelClass, self::$configMemo)) {
            return self::$configMemo[$modelClass];
        }

        $canonical = ModelKey::canonical($modelClass);

        $row = CrudConfig::query()
            ->where('model', $canonical)
            ->orderBy('route')
            ->get()
            ->first(fn (CrudConfig $candidate) => ! empty($candidate->config['notifications']['rules'] ?? []));

        return self::$configMemo[$modelClass] = $row;
    }
}

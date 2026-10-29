<?php

declare(strict_types=1);

namespace Ptah\Services\Auth;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Ptah\Support\PtahGuard;

/**
 * The signed-in user's sessions, in the `sessions` table.
 *
 * That table has no guard column: `user_id` is whatever the request's guard
 * answered. On a host with two identities (staff on one guard, customers on
 * another) user 7 of each shared every row — the profile listed the other
 * person's sessions (IP, browser) and could revoke them (laudo 1.53.0). A row
 * belongs to the user only when its payload carries the ptah guard's login key
 * with the user's id; a payload that cannot be read belongs to no one, so it
 * is neither shown nor deleted.
 */
class SessionService
{
    /**
     * Returns all active sessions for the user, ordered from most recent.
     */
    public function getActiveSessions(Authenticatable $user): Collection
    {
        if (! $this->sessionsTableExists()) {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->filter(fn ($session) => $this->belongsTo($session->payload ?? null, $user))
            ->values()
            ->map(function ($session) {
                $agent = $this->parseAgent($session->user_agent ?? '');
                $current = Request::hasSession() && $session->id === Request::session()->getId();

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address ?? trans('ptah::ui.unknown'),
                    'user_agent' => $session->user_agent ?? '',
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'last_activity_human' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'is_current' => $current,
                ];
            });
    }

    /**
     * Revokes a specific session belonging to $user.
     *
     * The `user_id` constraint is the IDOR guard: without it, any
     * authenticated user could pass another user's session id (sessions are
     * plain string ids, easy to enumerate/guess from a shared device) and
     * delete a session they do not own.
     */
    public function revokeSession(string $sessionId, Authenticatable $user): void
    {
        if (! $this->sessionsTableExists()) {
            return;
        }

        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($session !== null && $this->belongsTo($session->payload ?? null, $user)) {
            DB::table('sessions')->where('id', $sessionId)->delete();
        }
    }

    /**
     * Revokes all sessions except the current one.
     */
    public function revokeOtherSessions(Authenticatable $user, string $currentSessionId): int
    {
        if (! $this->sessionsTableExists()) {
            return 0;
        }

        $ids = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->get(['id', 'payload'])
            ->filter(fn ($session) => $this->belongsTo($session->payload ?? null, $user))
            ->pluck('id')
            ->all();

        return $ids === [] ? 0 : DB::table('sessions')->whereIn('id', $ids)->delete();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Does this stored session hold $user signed in on the ptah guard? */
    private function belongsTo(?string $payload, Authenticatable $user): bool
    {
        $data = $this->decodePayload($payload);
        $key = PtahGuard::sessionKey();

        return $data !== null
            && isset($data[$key])
            && (string) $data[$key] === (string) $user->getAuthIdentifier();
    }

    /**
     * The session array of a `sessions` row, read the way Laravel's Store
     * wrote it: base64, encrypted when `session.encrypt`, serialized with
     * `session.serialization`. Null when it cannot be read.
     *
     * @return array<string, mixed>|null
     */
    private function decodePayload(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }

        try {
            if (config('session.encrypt')) {
                $raw = app('encrypter')->decrypt($raw);
            }
            $data = config('session.serialization', 'php') === 'json'
                ? json_decode((string) $raw, true)
                : unserialize((string) $raw, ['allowed_classes' => false]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function sessionsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('sessions');
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseAgent(string $userAgent): array
    {
        $browser = trans('ptah::ui.unknown');
        $platform = trans('ptah::ui.unknown');

        $browsers = [
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'MSIE' => 'Internet Explorer',
            'Trident' => 'Internet Explorer',
        ];

        foreach ($browsers as $key => $name) {
            if (str_contains($userAgent, $key)) {
                $browser = $name;
                break;
            }
        }

        $platforms = [
            'Windows NT' => 'Windows',
            'Macintosh' => 'macOS',
            'Linux' => 'Linux',
            'Android' => 'Android',
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
        ];

        foreach ($platforms as $key => $name) {
            if (str_contains($userAgent, $key)) {
                $platform = $name;
                break;
            }
        }

        return compact('browser', 'platform');
    }
}

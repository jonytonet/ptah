<?php

declare(strict_types=1);

namespace Ptah\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

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
            ->map(function ($session) {
                $agent   = $this->parseAgent($session->user_agent ?? '');
                $current = ($session->id === Request::session()->getId());

                return [
                    'id'                 => $session->id,
                    'ip_address'         => $session->ip_address ?? trans('ptah::ui.unknown'),
                    'user_agent'         => $session->user_agent ?? '',
                    'browser'            => $agent['browser'],
                    'platform'           => $agent['platform'],
                    'last_activity_human'=> \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'is_current'         => $current,
                ];
            });
    }

    /**
     * Revokes a specific session.
     */
    public function revokeSession(string $sessionId): void
    {
        if ($this->sessionsTableExists()) {
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

        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

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
        $browser  = trans('ptah::ui.unknown');
        $platform = trans('ptah::ui.unknown');

        $browsers = [
            'Edg'     => 'Edge',
            'OPR'     => 'Opera',
            'Chrome'  => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari'  => 'Safari',
            'MSIE'    => 'Internet Explorer',
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
            'Macintosh'  => 'macOS',
            'Linux'      => 'Linux',
            'Android'    => 'Android',
            'iPhone'     => 'iPhone',
            'iPad'       => 'iPad',
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

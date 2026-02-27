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
     * Retorna todas as sessões ativas do usuário, ordenadas da mais recente.
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

                return (object) [
                    'id'            => $session->id,
                    'ip_address'    => $session->ip_address ?? 'desconhecido',
                    'user_agent'    => $session->user_agent ?? '',
                    'browser'       => $agent['browser'],
                    'platform'      => $agent['platform'],
                    'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'is_current'    => $current,
                ];
            });
    }

    /**
     * Revoga uma sessão específica.
     */
    public function revokeSession(string $sessionId): void
    {
        if ($this->sessionsTableExists()) {
            DB::table('sessions')->where('id', $sessionId)->delete();
        }
    }

    /**
     * Revoga todas as sessões exceto a atual.
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
        $browser  = 'Desconhecido';
        $platform = 'Desconhecido';

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

<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Who owns an export: a user id AND the guard it belongs to.
 *
 * Exports were owned by `user_id` alone. On a host with two identities (staff
 * on `web`, customers on another guard) user 7 of one guard listed and
 * downloaded the exports of user 7 of the other — and a guest-owned export
 * (`user_id` null) was shared by every guest (audit of 28/09/2026, 1.41.10).
 * The guard travels in the export's payload; there is no schema change.
 */
final class ExportOwner
{
    /** The guard of the current request — what `Auth::id()` answers for. */
    public static function guard(): string
    {
        return (string) Auth::getDefaultDriver();
    }

    /**
     * Is the signed-in user, on the export's own guard, its owner? A guest
     * never owns an export. An export written before 1.41.10 carries no
     * guard and belongs to the application's default one.
     */
    public static function owns(mixed $userId, ?string $guard): bool
    {
        if ($userId === null || $userId === '') {
            return false;
        }

        $guard = ($guard ?? '') !== '' ? $guard : (string) config('auth.defaults.guard');

        try {
            $id = Auth::guard($guard)->id();
        } catch (\Throwable) {
            return false;
        }

        return $id !== null && (string) $id === (string) $userId;
    }
}

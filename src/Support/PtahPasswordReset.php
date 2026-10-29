<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

/**
 * Password reset for the auth module (1.53.0).
 *
 * The reset page was named `password.reset`, the name Laravel's own
 * ResetPassword notification links to — and the name Fortify (Jetstream)
 * registers too, so both packages fought over it. The page is
 * `ptah.auth.password.reset` now:
 *
 *  - the links ptah sends ("forgot password", the users screen) point at it,
 *    through a ResetPassword URL callback that lives only for that send;
 *  - a reset link sent by anyone else keeps Laravel's behaviour —
 *    `password.reset` when the host (or Fortify) has it — and falls back to the
 *    ptah page when nothing else does (see PtahServiceProvider), so a host that
 *    relied on ptah answering `password.reset` keeps working.
 */
final class PtahPasswordReset
{
    public const ROUTE = 'ptah.auth.password.reset';

    public static function broker(): PasswordBroker
    {
        return Password::broker(PtahGuard::broker());
    }

    /** Sends the reset link for the ptah page; the broker's status string. */
    public static function sendLink(string $email): string
    {
        $previous = ResetPassword::$createUrlCallback;
        ResetPassword::createUrlUsing(fn ($user, string $token): string => self::url($user, $token));

        try {
            return self::broker()->sendResetLink(['email' => $email]);
        } finally {
            ResetPassword::$createUrlCallback = $previous;
        }
    }

    /** Laravel's default target when it exists, the ptah page otherwise. */
    public static function fallbackUrl(mixed $user, string $token): string
    {
        if (Route::has('password.reset')) {
            return url(route('password.reset', ['token' => $token, 'email' => self::email($user)], false));
        }

        return self::url($user, $token);
    }

    private static function url(mixed $user, string $token): string
    {
        return url(route(self::ROUTE, ['token' => $token, 'email' => self::email($user)], false));
    }

    private static function email(mixed $user): string
    {
        return $user instanceof CanResetPassword ? $user->getEmailForPasswordReset() : (string) ($user->email ?? '');
    }
}

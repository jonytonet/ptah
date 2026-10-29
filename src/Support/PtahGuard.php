<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

/**
 * The guard the auth module signs people into: `ptah.auth.guard`.
 *
 * Only the logout read that key. Login, the 2FA challenge and the profile used
 * the application's default guard, and the challenge looked the user up in
 * `auth.providers.users.model` — so on a host whose admins sign in on their
 * own guard (or on `web` backed by a provider other than `users`), the 2FA
 * challenge loaded the wrong model and signed into the wrong guard (laudo
 * 1.53.0). Everything in the auth module goes through here now.
 */
final class PtahGuard
{
    public static function name(): string
    {
        $guard = (string) config('ptah.auth.guard', '');

        return $guard !== '' ? $guard : (string) config('auth.defaults.guard', 'web');
    }

    public static function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return Auth::guard(self::name());
    }

    /**
     * Typed as the usual base of an Eloquent user, for static analysis.
     *
     * @return User|null
     */
    public static function user(): ?Authenticatable
    {
        /** @var User|null */
        return self::guard()->user();
    }

    public static function id(): int|string|null
    {
        return self::guard()->id();
    }

    /**
     * The user with this id, from the ptah guard's OWN provider.
     *
     * @return User|null see user()
     */
    public static function findUser(int|string $id): ?Authenticatable
    {
        /** @var User|null */
        return Auth::createUserProvider(self::provider())?->retrieveById($id);
    }

    /**
     * The session key the guard stores the signed-in id under — what tells
     * one guard's session from another's in the `sessions` table.
     */
    public static function sessionKey(): string
    {
        return 'login_'.self::name().'_'.sha1(SessionGuard::class);
    }

    /**
     * The password broker of the ptah guard's provider. The default broker
     * resets passwords in `users` — the wrong table for a guard backed by
     * another provider (1.53.0).
     */
    public static function broker(): ?string
    {
        $provider = self::provider();

        foreach ((array) config('auth.passwords', []) as $name => $broker) {
            if (is_array($broker) && ($broker['provider'] ?? null) === $provider) {
                return (string) $name;
            }
        }

        return null;
    }

    private static function provider(): ?string
    {
        $provider = config('auth.guards.'.self::name().'.provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the numeric user ID from different input types.
 *
 * Used by CompanyService and PermissionService to avoid duplication.
 */
trait ResolvesUser
{
    /**
     * Resolves the numeric user ID from different inputs.
     *
     * Accepts:
     *  - null          → uses auth()->id() or custom session key
     *  - int|string    → converts to int
     *  - Authenticatable or Model → reads the field defined in ptah.permissions.user_id_field
     *
     * @param  mixed $user
     * @return int|null
     */
    protected function resolveUserId(mixed $user): ?int
    {
        if ($user === null) {
            // Try Laravel's default auth()
            if (auth()->check()) {
                return (int) auth()->id();
            }

            // Fallback: custom session key (legacy apps)
            $sessionKey = config('ptah.permissions.user_session_key');
            if ($sessionKey && \Illuminate\Support\Facades\Session::has($sessionKey)) {
                return (int) \Illuminate\Support\Facades\Session::get($sessionKey);
            }

            return null;
        }

        if (is_int($user) || (is_string($user) && ctype_digit($user))) {
            return (int) $user;
        }

        if ($user instanceof Authenticatable || $user instanceof Model) {
            $field = config('ptah.permissions.user_id_field', 'id');
            return (int) $user->{$field};
        }

        return null;
    }
}

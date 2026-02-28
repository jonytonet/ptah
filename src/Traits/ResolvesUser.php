<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolve o ID numérico do usuário a partir de diferentes tipos de input.
 *
 * Utilizado por CompanyService e PermissionService para evitar duplicação.
 */
trait ResolvesUser
{
    /**
     * Resolve o ID numérico do usuário a partir de diferentes inputs.
     *
     * Aceita:
     *  - null          → usa auth()->id() ou chave de session personalizada
     *  - int|string    → converte para int
     *  - Authenticatable ou Model → lê o campo definido em ptah.permissions.user_id_field
     *
     * @param  mixed $user
     * @return int|null
     */
    protected function resolveUserId(mixed $user): ?int
    {
        if ($user === null) {
            // Tenta auth() padrão do Laravel
            if (auth()->check()) {
                return (int) auth()->id();
            }

            // Fallback: chave de session personalizada (apps legados)
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

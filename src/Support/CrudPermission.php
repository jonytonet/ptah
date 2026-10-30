<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Where a screen's permissions come from: ptah's ACL or Laravel's Gate (1.56.0).
 *
 * The screen permission (`permissions.permissionIdentifier`) and the column
 * permission (`colsPermission`) only worked with ptah's own ACL module on —
 * a host on Spatie, Bouncer or plain policies had no way to use them.
 * `ptah.permissions.driver`:
 *
 *  - `ptah` (default): unchanged — enforced only while `ptah.modules.permissions`
 *    is on, through ptah_can();
 *  - `gate`: always enforced, through Gate — whatever registers abilities
 *    answers (Spatie registers every permission as one). The screen's
 *    abilities are named by `ptah.permissions.gate_ability`, default
 *    `{identifier}.{action}` (`products.read`, `products.update`…; Spatie style:
 *    `{action} {identifier}`); a column's `colsPermission` is the ability itself.
 *
 * Without a signed-in user both drivers deny, as before.
 */
final class CrudPermission
{
    public const PTAH = 'ptah';

    public const GATE = 'gate';

    public static function driver(): string
    {
        return config('ptah.permissions.driver', self::PTAH) === self::GATE ? self::GATE : self::PTAH;
    }

    /** Whether screen and column permissions are enforced at all. */
    public static function active(): bool
    {
        return self::driver() === self::GATE || (bool) config('ptah.modules.permissions');
    }

    /** May the user do $action (create/read/update/delete…) on the screen key? */
    public static function allows(string $key, string $action, mixed $user = null, ?int $companyId = null): bool
    {
        if (self::driver() === self::GATE) {
            return self::gate(self::ability($key, $action), $user);
        }

        return function_exists('ptah_can') && ptah_can($key, $action, $user, $companyId);
    }

    /** May the user see a column gated by $key? (gate driver only — the ptah driver resolves keys in bulk.) */
    public static function allowsColumn(string $key, mixed $user = null): bool
    {
        return self::gate($key, $user);
    }

    public static function ability(string $key, string $action): string
    {
        $pattern = (string) config('ptah.permissions.gate_ability', '{identifier}.{action}');

        return str_replace(['{identifier}', '{action}'], [$key, $action], $pattern !== '' ? $pattern : '{identifier}.{action}');
    }

    private static function gate(string $ability, mixed $user): bool
    {
        $user = $user instanceof Authenticatable ? $user : Auth::user();

        // Gate::forUser and not ->can(): an Authenticatable without the
        // Authorizable trait (GenericUser, custom guards) has no can().
        return $user !== null && Gate::forUser($user)->allows($ability);
    }
}

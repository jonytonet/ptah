<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Where an unauthenticated visitor goes (1.46.5).
 *
 * The auth module used to send EVERY guest to `ptah.auth.login` — including
 * a host's second guard (Engepecas: the customer `portal`), whose visitors
 * landed on the panel login. Now the decision follows the guard the route
 * requires:
 *
 *  - a guard Ptah authenticates (`ptah.auth.guard`) → the Ptah login;
 *  - any other guard → `ptah.auth.guest_redirect` (a route name or a URL,
 *    or a map guard => route/URL), else null — Laravel's own fallback
 *    (the `login` route) decides;
 *  - no `auth` middleware found on the route → the Ptah login, as before.
 */
final class GuestRedirect
{
    public static function for(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $guards = self::requiredGuards($request);
        $ptahGuard = (string) config('ptah.auth.guard', 'web');

        if ($guards === null || in_array($ptahGuard, $guards, true)) {
            return route('ptah.auth.login');
        }

        $target = config('ptah.auth.guest_redirect');
        if (is_array($target)) {
            $target = collect($guards)->map(fn (string $g) => $target[$g] ?? null)->filter()->first();
        }

        return is_string($target) && $target !== ''
            ? (Route::has($target) ? route($target) : url($target))
            : null;
    }

    /**
     * The guards the route's `auth` middleware names — `auth` alone means the
     * default guard. Null when the route carries no auth middleware at all
     * (a custom middleware that throws AuthenticationException itself).
     *
     * @return list<string>|null
     */
    private static function requiredGuards(Request $request): ?array
    {
        $route = $request->route();
        if (! $route instanceof \Illuminate\Routing\Route) {
            return null;
        }

        $guards = null;
        foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            [$class, $params] = array_pad(explode(':', $middleware, 2), 2, '');
            if (! class_exists($class) || ! is_a($class, Authenticate::class, true)) {
                continue;
            }
            $named = array_values(array_filter(array_map('trim', explode(',', $params))));
            $guards = array_merge($guards ?? [], $named !== [] ? $named : [(string) config('auth.defaults.guard')]);
        }

        return $guards;
    }
}

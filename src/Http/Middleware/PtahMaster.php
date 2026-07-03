<?php

declare(strict_types=1);

namespace Ptah\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Restricts a route to master users. Registered as 'ptah.master'.
 *
 * The permission-management screens (roles, page objects, users-ACL, audit,
 * guide) administer the access-control system itself, so they must not be
 * reachable by every authenticated user — only masters.
 *
 * Usage:
 *   Route::middleware(['web', 'auth', 'ptah.master'])
 */
class PtahMaster
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! function_exists('ptah_is_master') || ! ptah_is_master()) {
            $message = trans('ptah::ui.permission_denied');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'error' => 'permission_denied',
                ], Response::HTTP_FORBIDDEN);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}

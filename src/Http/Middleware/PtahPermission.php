<?php

declare(strict_types=1);

namespace Ptah\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ptah\Services\Permission\PermissionService;

/**
 * Ptah permission-check middleware.
 *
 * Registered as 'ptah.can' in the ServiceProvider.
 *
 * Usage in routes:
 *   Route::middleware('ptah.can:users.store,create')
 *   Route::middleware('ptah.can:reports,read,optional_company_id')
 *
 * Parameters:
 *   1. objectKey  — object key (e.g. 'users.store')
 *   2. action     — action: create|read|update|delete
 *   3. companyId  — optional; uses session/auth if omitted
 */
class PtahPermission
{
    public function __construct(
        protected PermissionService $permission
    ) {}

    public function handle(Request $request, Closure $next, string $objectKey, string $action = 'read', ?string $companyId = null): mixed
    {
        $resolvedCompanyId = $companyId !== null ? (int) $companyId : null;

        if (!$this->permission->check(null, $objectKey, $action, $resolvedCompanyId)) {
            $message = trans('ptah::ui.permission_denied');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'error'   => 'permission_denied',
                    'key'     => $objectKey,
                    'action'  => $action,
                ], Response::HTTP_FORBIDDEN);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ptah\Services\Permission\PermissionService;

/**
 * Middleware de verificação de permissões Ptah.
 *
 * Registrado como 'ptah.can' no ServiceProvider.
 *
 * Uso nas rotas:
 *   Route::middleware('ptah.can:users.store,create')
 *   Route::middleware('ptah.can:reports,read,optional_company_id')
 *
 * Parâmetros:
 *   1. objectKey  — chave do objeto (ex: 'users.store')
 *   2. action     — ação: create|read|update|delete
 *   3. companyId  — opcional; se omitido usa session/auth
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
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Você não tem permissão para realizar esta ação.',
                    'error'   => 'permission_denied',
                    'key'     => $objectKey,
                    'action'  => $action,
                ], Response::HTTP_FORBIDDEN);
            }

            abort(403, 'Você não tem permissão para realizar esta ação.');
        }

        return $next($request);
    }
}

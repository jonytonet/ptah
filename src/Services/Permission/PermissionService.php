<?php

declare(strict_types=1);

namespace Ptah\Services\Permission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Ptah\Contracts\PermissionServiceContract;
use Ptah\Models\PageObject;
use Ptah\Models\PermissionAudit;
use Ptah\Models\UserRole;
use Ptah\Traits\ResolvesUser;

/**
 * Serviço central de verificação de permissões do Ptah.
 *
 * Hierarquia: Empresa → Role (is_master=bypass) → Página → Objeto → CRUD
 *
 * Adaptável a diferentes cenários:
 *  - app com Sanctum/Passport   → $user = Auth::user()
 *  - app legacy com session ID  → $user = null (lê PTAH_USER_SESSION_KEY)
 *  - single-tenant              → $companyId = null, multi_company = false
 *  - multi-company              → $companyId = id da empresa ativa
 */
class PermissionService implements PermissionServiceContract
{
    use ResolvesUser;

    // ─────────────────────────────────────────
    // Resolução de empresa
    // ─────────────────────────────────────────

    /**
     * Resolve o ID da empresa ativa.
     */
    protected function resolveCompanyId(?int $companyId): ?int
    {
        if ($companyId !== null) {
            return $companyId;
        }

        if (!config('ptah.permissions.multi_company', true)) {
            return null;
        }

        $sessionKey = config('ptah.permissions.company_session_key', 'ptah_company_id');
        if ($sessionKey && Session::has($sessionKey)) {
            return (int) Session::get($sessionKey);
        }

        return null;
    }

    // ─────────────────────────────────────────
    // Cache helpers
    // ─────────────────────────────────────────

    protected function cacheKey(string $type, int $userId, ?int $companyId, string $extra = ''): string
    {
        return "ptah_{$type}:{$userId}:{$companyId}:{$extra}";
    }

    protected function ttl(): int
    {
        return (int) config('ptah.permissions.cache_ttl', 3600);
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('ptah.permissions.cache', true);
    }

    // ─────────────────────────────────────────
    // Implementação do contrato
    // ─────────────────────────────────────────

    /**
     * {@inheritdoc}
     *
     * Implementação unificada: delega ao mapa completo cacheado por getPermissions().
     * Elimina o cache duplo (individual + mapa) que causava dados stale após revogação.
     */
    public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
    {
        $userId = $this->resolveUserId($user);

        // Guests sem permissão (a menos que allow_guest = true)
        if ($userId === null) {
            return (bool) config('ptah.permissions.allow_guest', false);
        }

        // 1. Short-circuit: roles MASTER passam em tudo
        if ($this->isMasterById($userId)) {
            if (config('ptah.permissions.audit') && config('ptah.permissions.audit_master')) {
                $this->writeAudit($userId, $companyId, $objectKey, strtolower($action), 'granted');
            }
            return true;
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);
        $action            = strtolower($action);

        // 2. Busca no mapa completo (única fonte de verdade, já cacheada)
        //    Garante consistência: clearCache() invalida o mapa e esta leitura reflete
        //    imediatamente qualquer mudança de role/permissão.
        $map    = $this->getPermissions($user, $resolvedCompanyId);
        $result = (bool) ($map[$objectKey][$action] ?? false);

        // 3. Auditoria
        if (config('ptah.permissions.audit')) {
            if (!$result || config('ptah.permissions.audit_denied')) {
                $this->writeAudit($userId, $resolvedCompanyId, $objectKey, $action, $result ? 'granted' : 'denied');
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isMaster(mixed $user = null): bool
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return false;
        }

        return $this->isMasterById($userId);
    }

    /**
     * Verificação interna de MASTER por ID (cacheada).
     */
    protected function isMasterById(int $userId): bool
    {
        if ($this->cacheEnabled()) {
            return (bool) Cache::remember(
                "ptah_is_master:{$userId}",
                $this->ttl(),
                fn () => $this->queryIsMaster($userId)
            );
        }

        return $this->queryIsMaster($userId);
    }

    protected function queryIsMaster(int $userId): bool
    {
        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('is_master', true)->where('is_active', true))
            ->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions(mixed $user = null, ?int $companyId = null): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        if ($this->isMasterById($userId)) {
            // MASTER: devolve mapa "tudo liberado" dos objetos cadastrados
            return $this->buildMasterPermissionMap();
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);

        if ($this->cacheEnabled()) {
            $key = $this->cacheKey('perms_map', $userId, $resolvedCompanyId);
            return Cache::remember($key, $this->ttl(), fn () => $this->buildPermissionMap($userId, $resolvedCompanyId));
        }

        return $this->buildPermissionMap($userId, $resolvedCompanyId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCompaniesForResource(mixed $user, string $objectKey, string $action): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        $actionColumn = "can_{$action}";

        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('permissions', fn ($q2) => $q2
                    ->where($actionColumn, true)
                    ->whereHas('pageObject', fn ($q3) => $q3->where('obj_key', $objectKey))
                )
            )
            ->pluck('company_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function syncRole(mixed $user, int $roleId, array $companyIds = []): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return;
        }

        if (empty($companyIds)) {
            UserRole::withTrashed()->updateOrCreate(
                ['user_id' => $userId, 'role_id' => $roleId, 'company_id' => null],
                ['is_active' => true, 'deleted_at' => null]
            );
        } else {
            foreach ($companyIds as $companyId) {
                UserRole::withTrashed()->updateOrCreate(
                    ['user_id' => $userId, 'role_id' => $roleId, 'company_id' => $companyId],
                    ['is_active' => true, 'deleted_at' => null]
                );
            }
        }

        $this->clearCache($user);
    }

    /**
     * {@inheritdoc}
     */
    public function detachRole(mixed $user, int $roleId, ?int $companyId = null): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return;
        }

        $query = UserRole::where('user_id', $userId)->where('role_id', $roleId);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $query->delete(); // SoftDelete

        $this->clearCache($user, $companyId);
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(mixed $user = null, ?int $companyId = null): void
    {
        if ($user === null) {
            // Caso extremo: limpa todo o cache ptah (use com cautela)
            // Compatível com tags se o driver suportar
            try {
                Cache::tags(['ptah_permissions'])->flush();
            } catch (\Throwable) {
                // Driver sem suporte a tags — não faz nada aqui
            }
            return;
        }

        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return;
        }

        Cache::forget("ptah_is_master:{$userId}");
        Cache::forget($this->cacheKey('perms_map', $userId, $companyId));

        // Limpa cache de checks individuais (não há pattern delete básico no file driver)
        // Em Redis, o ideal é usar Cache::tags. Aqui fazemos o possível.
    }

    // ─────────────────────────────────────────
    // DB queries internas
    // ─────────────────────────────────────────

    /**
     * Consulta direta ao DB se um usuário tem permissão para uma ação em um objeto.
     *
     * @internal Mantido para uso em subclasses que precisem de consulta pontual sem o mapa.
     *           O método check() usa getPermissions() (mapa cacheado) como fonte de verdade.
     */
    protected function queryPermission(int $userId, ?int $companyId, string $objectKey, string $actionColumn): bool
    {
        return UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->forCompany($companyId)
            ->whereHas('role', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('permissions', fn ($q2) => $q2
                    ->where($actionColumn, true)
                    ->whereNull('deleted_at')
                    ->whereHas('pageObject', fn ($q3) => $q3
                        ->where('obj_key', $objectKey)
                        ->where('is_active', true)
                    )
                )
            )
            ->exists();
    }

    /**
     * Monta o mapa completo de permissões: [ 'obj_key' => ['create'=>bool, ...] ]
     */
    protected function buildPermissionMap(int $userId, ?int $companyId): array
    {
        $rows = UserRole::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->forCompany($companyId)
            ->with([
                'role.permissions' => fn ($q) => $q->whereNull('deleted_at'),
                'role.permissions.pageObject',
            ])
            ->get()
            ->flatMap(fn (UserRole $ur) => $ur->role->permissions ?? collect());

        $map = [];

        foreach ($rows as $perm) {
            $key = $perm->pageObject?->obj_key ?? null;
            if (!$key) {
                continue;
            }

            if (!isset($map[$key])) {
                $map[$key] = ['create' => false, 'read' => false, 'update' => false, 'delete' => false];
            }

            // OR lógico: se qualquer role concede, considera concedido
            $map[$key]['create'] = $map[$key]['create'] || $perm->can_create;
            $map[$key]['read']   = $map[$key]['read']   || $perm->can_read;
            $map[$key]['update'] = $map[$key]['update'] || $perm->can_update;
            $map[$key]['delete'] = $map[$key]['delete'] || $perm->can_delete;
        }

        return $map;
    }

    /**
     * Mapa de MASTER: todos os objetos cadastrados com todos os flags true.
     */
    protected function buildMasterPermissionMap(): array
    {
        return PageObject::query()
            ->active()
            ->pluck('obj_key')
            ->unique()
            ->mapWithKeys(fn ($key) => [
                $key => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────
    // Auditoria
    // ─────────────────────────────────────────

    protected function writeAudit(int $userId, ?int $companyId, string $resourceKey, string $action, string $result): void
    {
        try {
            PermissionAudit::create([
                'user_id'      => $userId,
                'company_id'   => $companyId,
                'resource_key' => $resourceKey,
                'action'       => $action,
                'result'       => $result,
                'ip_address'   => Request::ip(),
                'user_agent'   => Request::userAgent(),
                'context'      => [
                    'uri'    => Request::getRequestUri(),
                    'method' => Request::method(),
                ],
            ]);
        } catch (\Throwable) {
            // Nunca derruba a aplicação por falha no log de auditoria
        }
    }
}

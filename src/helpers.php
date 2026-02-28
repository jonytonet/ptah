<?php

declare(strict_types=1);

use Ptah\Services\Permission\PermissionService;

if (!function_exists('ptah_can')) {
    /**
     * Verifica se o usuário possui permissão para executar uma ação em um recurso.
     *
     * O parâmetro $user aceita:
     *  - null          → utiliza auth()->user() ou session (config ptah.permissions.user_session_key)
     *  - int|string    → user ID, resolvido via config ptah.permissions.user_model
     *  - Authenticatable → model de usuário diretamente
     *
     * O parâmetro $companyId aceita:
     *  - null          → utiliza session (config ptah.permissions.company_session_key)
     *  - int           → ID da empresa
     *
     * @param  string          $objectKey  Chave do object (ex: 'users.store', 'reports.export')
     * @param  string          $action     Ação desejada: 'create', 'read', 'update', 'delete'
     * @param  mixed           $user       Usuário (null = auth atual)
     * @param  int|null        $companyId  ID da empresa (null = session atual)
     * @return bool
     */
    function ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->check($user, $objectKey, $action, $companyId);
    }
}

if (!function_exists('ptah_is_master')) {
    /**
     * Verifica se o usuário possui role MASTER (bypass total de permissões).
     *
     * @param  mixed $user  Usuário (null = auth atual)
     * @return bool
     */
    function ptah_is_master(mixed $user = null): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->isMaster($user);
    }
}

if (!function_exists('ptah_permissions')) {
    /**
     * Retorna o mapa completo de permissões do usuário.
     *
     * @param  mixed    $user       Usuário (null = auth atual)
     * @param  int|null $companyId  ID da empresa (null = session atual)
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    function ptah_permissions(mixed $user = null, ?int $companyId = null): array
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->getPermissions($user, $companyId);
    }
}

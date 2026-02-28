<?php

declare(strict_types=1);

namespace Ptah\Contracts;

interface PermissionServiceContract
{
    /**
     * Verifica se o usuário tem permissão para executar a ação no objeto.
     *
     * @param  mixed       $user       Usuário, user ID ou null (usa auth atual)
     * @param  string      $objectKey  Chave do objeto (ex: 'users.store')
     * @param  string      $action     create|read|update|delete
     * @param  int|null    $companyId  ID da empresa (null = session/auth context)
     */
    public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool;

    /**
     * Verifica se o usuário possui role MASTER (bypass total).
     */
    public function isMaster(mixed $user = null): bool;

    /**
     * Retorna o mapa completo de permissões do usuário.
     *
     * @return array<string, array{create: bool, read: bool, update: bool, delete: bool}>
     */
    public function getPermissions(mixed $user = null, ?int $companyId = null): array;

    /**
     * Retorna os IDs de empresa onde o usuário tem acesso ao recurso/ação.
     *
     * @return int[]
     */
    public function getCompaniesForResource(mixed $user, string $objectKey, string $action): array;

    /**
     * Associa um role ao usuário (cria UserRole para cada empresa).
     *
     * @param  int[] $companyIds  IDs de empresa; [] = sem empresa (single-company)
     */
    public function syncRole(mixed $user, int $roleId, array $companyIds = []): void;

    /**
     * Remove a associação role-usuário (soft delete de UserRole).
     */
    public function detachRole(mixed $user, int $roleId, ?int $companyId = null): void;

    /**
     * Invalida o cache de permissões do usuário.
     * Sem parâmetros = invalida tudo.
     */
    public function clearCache(mixed $user = null, ?int $companyId = null): void;
}

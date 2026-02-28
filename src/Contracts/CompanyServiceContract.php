<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Ptah\Models\Company;

interface CompanyServiceContract
{
    /**
     * Retorna a empresa marcada como padrão (is_default = true).
     * Cria automaticamente se não existir e createIfMissing = true.
     */
    public function getDefault(bool $createIfMissing = false): ?Company;

    /**
     * Busca uma empresa pelo ID.
     */
    public function getById(int $id): ?Company;

    /**
     * Retorna todas as empresas ativas em que o usuário tem algum role.
     *
     * @return Collection<int, Company>
     */
    public function getUserCompanies(mixed $user): Collection;

    /**
     * Retorna o ID da empresa ativa da sessão/contexto atual.
     */
    public function getCurrentCompanyId(): ?int;

    /**
     * Define a empresa ativa na sessão.
     */
    public function setCurrentCompany(int $companyId): void;

    /**
     * Invalida o cache de empresas.
     */
    public function clearCache(mixed $user = null): void;
}

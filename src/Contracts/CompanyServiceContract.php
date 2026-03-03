<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Ptah\Models\Company;

interface CompanyServiceContract
{
    /**
     * Returns the company marked as default (is_default = true).
     * Automatically creates one if it does not exist and createIfMissing = true.
     */
    public function getDefault(bool $createIfMissing = false): ?Company;

    /**
     * Finds a company by ID.
     */
    public function getById(int $id): ?Company;

    /**
     * Returns all active companies where the user has any role.
     *
     * @return Collection<int, Company>
     */
    public function getUserCompanies(mixed $user): Collection;

    /**
     * Returns the active company ID for the current session/context.
     */
    public function getCurrentCompanyId(): ?int;

    /**
     * Sets the active company in the session.
     */
    public function setCurrentCompany(int $companyId): void;

    /**
     * Invalidates the company cache.
     */
    public function clearCache(mixed $user = null): void;
}

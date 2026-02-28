<?php

declare(strict_types=1);

namespace Ptah\Services\Company;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Ptah\Contracts\CompanyServiceContract;
use Ptah\Models\Company;
use Ptah\Models\UserRole;
use Ptah\Traits\ResolvesUser;

/**
 * Gerencia empresas e contexto de empresa ativa.
 *
 * Adaptável a cenários single-tenant e multi-tenant.
 */
class CompanyService implements CompanyServiceContract
{
    use ResolvesUser;

    protected string $sessionKey;

    public function __construct()
    {
        $this->sessionKey = config('ptah.permissions.company_session_key', 'ptah_company_id');
    }

    // ─────────────────────────────────────────
    // Consultas
    // ─────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function getDefault(bool $createIfMissing = false): ?Company
    {
        $company = Cache::remember('ptah_company_default', 3600, fn () =>
            Company::active()->default()->first()
        );

        if (!$company && $createIfMissing) {
            $company = $this->createDefaultCompany();
            Cache::forget('ptah_company_default');
        }

        return $company;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $id): ?Company
    {
        return Cache::remember("ptah_company:{$id}", 3600, fn () =>
            Company::active()->find($id)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getUserCompanies(mixed $user): Collection
    {
        $userId = $this->resolveUserId($user);

        if ($userId === null) {
            return new Collection();
        }

        return Cache::remember("ptah_user_companies:{$userId}", 3600, fn () =>
            Company::query()
                ->whereIn('id', function ($query) use ($userId) {
                    $query->select('company_id')
                          ->from('ptah_user_roles')
                          ->where('user_id', $userId)
                          ->where('is_active', true)
                          ->whereNotNull('company_id')
                          ->whereNull('deleted_at');
                })
                ->active()
                ->get()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentCompanyId(): ?int
    {
        if (Session::has($this->sessionKey)) {
            return (int) Session::get($this->sessionKey);
        }

        // Fallback: empresa padrão
        $default = $this->getDefault();
        return $default?->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentCompany(int $companyId): void
    {
        Session::put($this->sessionKey, $companyId);
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(mixed $user = null): void
    {
        Cache::forget('ptah_company_default');

        if ($user !== null) {
            $userId = $this->resolveUserId($user);
            if ($userId) {
                Cache::forget("ptah_user_companies:{$userId}");
            }
        }
    }

    // ─────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────


    /**
     * Cria a empresa padrão inicial usando config da aplicação.
     */
    protected function createDefaultCompany(): Company
    {
        $name = config('app.name', 'Company');
        return Company::create([
            'name'       => $name,
            'slug'       => Str::slug($name),
            'is_default' => true,
            'is_active'  => true,
        ]);
    }
}

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
    // Company Switcher — novos helpers
    // ─────────────────────────────────────────

    /**
     * Retorna todas as empresas ativas ordenadas (padrão primeiro, depois por nome).
     * Resultado é cacheado por 5 minutos.
     */
    public function getAll(): \Illuminate\Support\Collection
    {
        return Cache::remember('ptah_companies_all', 300, function () {
            return Company::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Retorna o model Company ativo da sessão atual, ou null.
     */
    public function getActive(): ?Company
    {
        $id = $this->activeId();
        if (! $id) {
            return null;
        }
        return $this->getAll()->firstWhere('id', $id);
    }

    /**
     * Retorna o ID da empresa ativa da sessão (0 se não definida).
     */
    public function activeId(): int
    {
        return (int) Session::get($this->sessionKey, 0);
    }

    /**
     * Define a empresa ativa na sessão, validando que existe e está ativa.
     * Invalida cache de permissões do usuário atual.
     *
     * @param int $id  ID da empresa
     */
    public function setActive(int $id): void
    {
        if (! $this->getAll()->contains('id', $id)) {
            return;
        }

        Session::put($this->sessionKey, $id);

        // Invalida cache de permissões do usuário logado
        $userId = auth()->id();
        if ($userId) {
            Cache::forget("ptah_permissions:{$userId}:{$id}:");
            Cache::forget("ptah_is_master:{$userId}");
        }
    }

    /**
     * Inicializa a sessão da empresa se ainda não estiver definida.
     * Prioridade: is_default = true → primeira empresa ativa.
     * Chamado pelo CompanySwitcher no mount() para garantir contexto válido.
     */
    public function initSession(): void
    {
        if ($this->activeId() > 0) {
            return;
        }

        $all = $this->getAll();
        if ($all->isEmpty()) {
            return;
        }

        $default = $all->firstWhere('is_default', true) ?? $all->first();
        Session::put($this->sessionKey, $default->id);
    }

    /**
     * Invalida o cache da lista de empresas.
     * Chamar após criar/editar/excluir uma empresa.
     */
    public function forgetListCache(): void
    {
        Cache::forget('ptah_companies_all');
        Cache::forget('ptah_company_default');
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

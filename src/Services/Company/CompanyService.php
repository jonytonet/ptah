<?php

declare(strict_types=1);

namespace Ptah\Services\Company;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Ptah\Contracts\CompanyServiceContract;
use Ptah\Models\Company;
use Ptah\Services\Permission\PermissionService;
use Ptah\Traits\ResolvesUser;

/**
 * Manages companies and active company context.
 *
 * Adaptable to single-tenant and multi-tenant scenarios.
 */
class CompanyService implements CompanyServiceContract
{
    use ResolvesUser;

    protected string $sessionKey;

    public function __construct(
        protected PermissionService $permission = new PermissionService,
    ) {
        $this->sessionKey = config('ptah.permissions.company_session_key', 'ptah_company_id');
    }

    // ─────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function getDefault(bool $createIfMissing = false): ?Company
    {
        $company = Cache::remember('ptah_company_default', 3600, fn () => Company::active()->default()->first()
        );

        if (! $company && $createIfMissing) {
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
        return Cache::remember("ptah_company:{$id}", 3600, fn () => Company::active()->find($id)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getUserCompanies(mixed $user): Collection
    {
        $userId = $this->resolveUserId($user);

        if ($userId === null) {
            return new Collection;
        }

        return Cache::remember("ptah_user_companies:{$userId}", 3600, fn () => Company::query()
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

        // Fallback: default company
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
    // Company Switcher — helpers
    // ─────────────────────────────────────────

    /**
     * Returns all active companies sorted (default first, then by name).
     * Result is cached for 5 minutes.
     */
    public function getAll(): \Illuminate\Support\Collection
    {
        $result = Cache::remember('ptah_companies_all', 300, function () {
            return Company::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        });

        // Guard against stale/corrupt deserialized cache (e.g. __PHP_Incomplete_Class).
        if (! ($result instanceof \Illuminate\Support\Collection)) {
            Cache::forget('ptah_companies_all');

            return Company::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        return $result;
    }

    /**
     * Returns the active Company model from the current session, or null.
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
     * Returns the active company ID from the session (0 if not set).
     */
    public function activeId(): int
    {
        return (int) Session::get($this->sessionKey, 0);
    }

    /**
     * Sets the active company in the session, validating that it exists and is active.
     * Invalidates the current user's permission cache.
     *
     * @param  int  $id  Company ID
     */
    public function setActive(int $id): void
    {
        // Antes conferia so se a empresa existia e estava ativa — nunca se o
        // usuario PERTENCIA a ela. `switchTo(7)` punha qualquer usuario na
        // empresa 7, e o BaseCrud usa `ptah_company_id()` como escopo de tenant:
        // listagem e exportacao de outra empresa.
        if (! $this->canSwitchTo($id)) {
            return;
        }

        Session::put($this->sessionKey, $id);

        // Invalidate permissions cache for the logged-in user. The old scheme's
        // literal Cache::forget() keys ("ptah_permissions:{u}:{c}:", "ptah_is_master:{u}")
        // predate the versioned-generation cache and match nothing today — that was
        // a silent no-op, exactly what clearPermissionCache() did in the legacy system.
        $userId = auth()->id();
        if ($userId) {
            $this->permission->clearCache((int) $userId);
        }
    }

    /**
     * Initialises the company session if not yet set.
     * Priority: is_default = true → first active company.
     * Called by CompanySwitcher on mount() to ensure a valid context.
     */
    public function initSession(): void
    {
        $active = $this->activeId();
        $switchable = $this->switchableCompanies();

        if ($active > 0) {
            // Sessao valida, ou nada melhor a oferecer: fica como esta.
            if ($switchable->contains('id', $active) || $switchable->isEmpty()) {
                return;
            }

            // A sessao aponta para uma empresa de que o usuario nao participa —
            // gravada antes desta correcao, ou por um papel revogado. Move para
            // uma dele, em vez de manter o acesso ao tenant errado.
        }

        // O fallback para TODAS as empresas e deliberado, e so vale para quem
        // nao pertence a nenhuma. No BaseCrud `companyFilter = 0` significa SEM
        // escopo de tenant, entao deixar a sessao vazia para esse usuario o faria
        // ver os dados de todas as empresas — pior do que ver a padrao, que e o
        // que acontecia antes. Nao alargamos.
        $pool = $switchable->isNotEmpty() ? $switchable : $this->getAll();

        if ($pool->isEmpty()) {
            return;
        }

        $default = $pool->firstWhere('is_default', true) ?? $pool->first();
        Session::put($this->sessionKey, $default->id);
    }

    /**
     * The companies the given (or current) user may switch to.
     *
     * With the permissions module OFF there is no membership data at all, so
     * every active company — the behaviour before this existed. With it ON:
     * a master sees every company; so does a user holding an active GLOBAL role
     * (`company_id` NULL), because `UserRole::scopeForCompany()` applies a
     * global role in every company; everybody else sees the companies their
     * own active roles are in.
     *
     * Deliberately NOT built on `getUserCompanies()` alone: that returns only
     * roles with a non-null company, so a user whose only role is global would
     * have been locked out of every company.
     */
    public function switchableCompanies(mixed $user = null): Collection
    {
        $all = $this->getAll();

        if (! config('ptah.modules.permissions')) {
            return $all;
        }

        $user ??= auth()->user();
        $userId = $this->resolveUserId($user);

        if ($userId === null) {
            return new Collection;
        }

        if ($this->permission->isMaster($user) || $this->hasGlobalRole($userId)) {
            return $all;
        }

        $mine = $this->getUserCompanies($userId)->pluck('id')->all();

        return $all->whereIn('id', $mine)->values();
    }

    public function canSwitchTo(int $companyId, mixed $user = null): bool
    {
        return $this->switchableCompanies($user)->contains('id', $companyId);
    }

    private function hasGlobalRole(int $userId): bool
    {
        return DB::table('ptah_user_roles')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNull('company_id')
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Invalidates the company list cache.
     * Call after creating/editing/deleting a company.
     */
    public function forgetListCache(): void
    {
        Cache::forget('ptah_companies_all');
        Cache::forget('ptah_company_default');
    }

    // ─────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────

    /**
     * Creates the initial default company using the application config.
     */
    protected function createDefaultCompany(): Company
    {
        $name = config('app.name', 'Company');

        return Company::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}

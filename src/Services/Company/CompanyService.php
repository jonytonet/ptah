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
 * Manages companies and active company context.
 *
 * Adaptable to single-tenant and multi-tenant scenarios.
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
    // Queries
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
     * @param int $id  Company ID
     */
    public function setActive(int $id): void
    {
        if (! $this->getAll()->contains('id', $id)) {
            return;
        }

        Session::put($this->sessionKey, $id);

        // Invalidate permissions cache for the logged-in user
        $userId = auth()->id();
        if ($userId) {
            Cache::forget("ptah_permissions:{$userId}:{$id}:");
            Cache::forget("ptah_is_master:{$userId}");
        }
    }

    /**
     * Initialises the company session if not yet set.
     * Priority: is_default = true → first active company.
     * Called by CompanySwitcher on mount() to ensure a valid context.
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
            'name'       => $name,
            'slug'       => Str::slug($name),
            'is_default' => true,
            'is_active'  => true,
        ]);
    }
}

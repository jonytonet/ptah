<?php

declare(strict_types=1);

namespace Ptah\Services\Export;

use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;

/**
 * Shared allowlist + permission gate for exported data.
 *
 * Used by BOTH the synchronous download controller (ExportController) and the
 * queued export job (GenerateCrudExportJob) so a stale/forged model reference
 * is rejected the exact same way in either path — one is a live HTTP request,
 * the other a background worker with no request/response to abort().
 *
 * Accepts either the canonical BaseCrud key ("Product", "Purchase/Order/…", as
 * stored in crud_configs) or a fully-qualified class name (the queued job
 * always stores the resolved FQCN, never a client-writable raw value) — tries
 * an exact match against crud_configs first, then the canonicalised form.
 */
class ExportAuthorizer
{
    /**
     * Returns null when the export is allowed; otherwise a short, log-safe
     * reason it was denied (safe to store in Export::$error).
     *
     * $user / $companyId let a caller with no request/session context (e.g. a
     * queued job) authorize explicitly against the export's OWNER and company
     * instead of the ambient auth()/session — which, in a worker, are always
     * empty and would make every queued export fail regardless of who owns it
     * or what they're allowed to do. Both default to null, preserving the
     * synchronous HTTP behaviour (resolved from auth()/session).
     */
    public function reasonDenied(string $model, mixed $user = null, ?int $companyId = null): ?string
    {
        if ($model === '') {
            return 'Export not allowed.';
        }

        $config = $this->findConfig($model);

        if (! $config) {
            return 'Export not allowed for this model.';
        }

        // Enforce the CRUD's read permission only when the module is active
        // (default install has it off — no behavioural change there).
        if (config('ptah.modules.permissions')) {
            $permKey = $config->config['permissions']['permissionIdentifier'] ?? null;

            if ($permKey && function_exists('ptah_can') && ! ptah_can($permKey, 'read', $user, $companyId)) {
                return 'You are not allowed to export this data.';
            }
        }

        return null;
    }

    /**
     * HTTP variant — aborts the request (403) when denied. For controller use.
     */
    public function authorizeOrAbort(string $model, mixed $user = null, ?int $companyId = null): void
    {
        $reason = $this->reasonDenied($model, $user, $companyId);

        if ($reason !== null) {
            abort(403, $reason);
        }
    }

    /**
     * Looks up the allowlisted CrudConfig row for $model — trying an exact
     * match first (covers the synchronous flow's canonical key, and any FQCN
     * stored verbatim), then the canonicalised form (covers the queued flow,
     * which stores the resolved FQCN while crud_configs stores the canonical
     * sub-folder key — see ModelKey).
     */
    protected function findConfig(string $model): ?CrudConfig
    {
        $config = CrudConfig::query()->where('model', $model)->first();

        if ($config) {
            return $config;
        }

        $canonical = ModelKey::canonical($model);

        if ($canonical === $model) {
            return null;
        }

        return CrudConfig::query()->where('model', $canonical)->first();
    }
}

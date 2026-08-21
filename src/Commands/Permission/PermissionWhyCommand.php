<?php

declare(strict_types=1);

namespace Ptah\Commands\Permission;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;

/**
 * Diagnoses WHY `ptah_can($objKey, $action, $user)` grants or denies access,
 * without reimplementing the permission engine — the granted/denied result
 * itself always comes from `PermissionService::check()`.
 *
 * Prints the full chain that feeds that result: the user's `UserRole`
 * bindings (with the reason each one does or doesn't count), every
 * `PageObject` registered under the requested `obj_key` (an `obj_key`
 * collision across pages prints all of them — see `ConfigDoctorCommand`),
 * and the `RolePermission` rows crossing the two. When the requested
 * `--action` is denied, prints the single most specific missing piece, in
 * this precedence order: nonexistent object → inactive object → inactive
 * page → no active role → no bind at all → `can_{action}=false` → bind
 * trashed → grant scoped to a different company → grant only on a
 * different page (suggesting the qualified key to use instead).
 *
 * Diagnostic tool: forces `ptah.permissions.audit` off for the duration of
 * the command so running it never pollutes `ptah_permission_audits`.
 */
class PermissionWhyCommand extends Command
{
    protected $signature = 'ptah:permission:why
        {user : User ID or e-mail (looked up via config(ptah.permissions.user_model))}
        {objKey : The obj_key to inspect (bare, or qualified as page::obj_key / page::section::obj_key)}
        {--action=read : Action to evaluate — create|read|update|delete}
        {--company= : Company ID context. Omit to use the console context (no session — global grants only)}';

    protected $description = 'Explain why ptah_can() grants or denies a user access to an obj_key';

    public function handle(PermissionService $service): int
    {
        // Diagnostic run — must never write to ptah_permission_audits, no
        // matter how the host app has audit configured.
        config(['ptah.permissions.audit' => false]);

        $userArg = (string) $this->argument('user');
        $userId = $this->resolveUser($userArg);

        if ($userId === null) {
            $this->components->error("Usuário '{$userArg}' não encontrado (nem como ID numérico, nem como e-mail em ".config('ptah.permissions.user_model', 'App\Models\User').').');

            return self::FAILURE;
        }

        $action = strtolower((string) $this->option('action'));
        if (! in_array($action, PermissionService::ACTIONS, true)) {
            $this->components->error("Ação inválida: '{$action}'. Válidas: ".implode(', ', PermissionService::ACTIONS).'.');

            return self::FAILURE;
        }

        $companyOption = $this->option('company');
        $companyId = $companyOption !== null ? (int) $companyOption : null;
        $companyOption = $companyOption !== null ? (string) $companyOption : null;

        $objKey = (string) $this->argument('objKey');
        [$bareObjKey, $pageSlug, $section] = $this->decomposeKey($objKey);

        $this->printCompanyContext($companyOption, $companyId);
        $this->printUserRoles($userId, $companyId);
        $allObjects = $this->printPageObjects($bareObjKey);
        $this->printBinds($userId, $allObjects);

        $this->newLine();
        $this->line('Resultado por ação (via PermissionService::check()):');
        foreach (PermissionService::ACTIONS as $a) {
            $result = $service->check($userId, $objKey, $a, $companyId);
            $marker = $a === $action ? '  ← ação solicitada (--action)' : '';
            $this->line(($result ? '  <fg=green>✔ concedido</> ' : '  <fg=red>✘ negado</>    ').$a.$marker);
        }

        $granted = $service->check($userId, $objKey, $action, $companyId);

        $this->newLine();

        if ($granted) {
            $this->components->info("CONCEDIDO: o usuário pode '{$action}' em '{$objKey}'.");

            return self::SUCCESS;
        }

        [, $message] = $this->diagnose($bareObjKey, $pageSlug, $section, $action, $companyId, $userId, $allObjects);
        $this->components->error("NEGADO: o usuário NÃO pode '{$action}' em '{$objKey}'.");
        $this->line("Peça faltante: {$message}");

        return self::FAILURE;
    }

    /**
     * Resolves the `{user}` argument: a numeric ID directly, or an e-mail
     * looked up against `config('ptah.permissions.user_model')`.
     */
    protected function resolveUser(string $userArg): ?int
    {
        if (ctype_digit($userArg)) {
            return (int) $userArg;
        }

        /** @var class-string<Model> $userModel */
        $userModel = config('ptah.permissions.user_model', 'App\Models\User');

        if (! class_exists($userModel)) {
            return null;
        }

        $user = $userModel::query()->where('email', $userArg)->first();

        return $user ? (int) $user->getKey() : null;
    }

    /**
     * Decomposes a possibly-qualified object key into
     * [bareObjKey, pageSlug|null, section|null] — mirrors
     * `PermissionService::decomposeQualifiedKey()`, duplicated here (a few
     * lines) because it's pure string parsing for display purposes, not
     * authorization logic; the actual grant/deny decision below always
     * comes from `PermissionService::check()`.
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    protected function decomposeKey(string $objectKey): array
    {
        // Mesmo literal-primeiro do motor: um obj_key real contendo '::' nao e
        // decomposto, entao o diagnostico nunca descreve uma pagina fantasma.
        if (! str_contains($objectKey, PermissionService::KEY_QUALIFIER)
            || PageObject::query()->where('obj_key', $objectKey)->exists()) {
            return [$objectKey, null, null];
        }

        $parts = explode(PermissionService::KEY_QUALIFIER, $objectKey);

        if (count($parts) >= 3) {
            return [implode(PermissionService::KEY_QUALIFIER, array_slice($parts, 2)), $parts[0], $parts[1]];
        }

        return [$parts[1], $parts[0], null];
    }

    /**
     * (a) Prints the company context that will be used and, when --company
     * was omitted, warns that a console run has no session — only global
     * (company_id IS NULL) grants count, per `PermissionService::resolveCompanyId()`.
     */
    protected function printCompanyContext(?string $companyOption, ?int $companyId): void
    {
        $this->line('<fg=cyan>Contexto de empresa:</>');

        if ($companyOption !== null) {
            $this->line("  --company informado: {$companyId}");
        } else {
            $this->line('  --company NÃO informado. Em console não há sessão HTTP: PermissionService::resolveCompanyId() resulta em null.');
            $this->line('  ⚠ Apenas grants GLOBAIS (UserRole.company_id IS NULL) serão considerados. Use --company=<id> para simular uma empresa específica.');
        }

        $this->newLine();
    }

    /**
     * (b) Prints every UserRole (including trashed) for the user, with the
     * reason each one does or doesn't count towards a grant.
     */
    protected function printUserRoles(int $userId, ?int $companyId): void
    {
        $this->line('<fg=cyan>Papéis (UserRole) do usuário:</>');

        $userRoles = UserRole::withTrashed()->where('user_id', $userId)->with('role')->get();

        if ($userRoles->isEmpty()) {
            $this->line('  (nenhum UserRole encontrado para este usuário)');
            $this->newLine();

            return;
        }

        foreach ($userRoles as $ur) {
            // The `role()` relation has no PHPDoc generics (matching the rest
            // of this codebase), so static analysis only knows it as a plain
            // Model — narrow it explicitly rather than chaining `?->`.
            $role = $ur->role;
            $roleIsRole = $role instanceof Role;

            $reasons = [];

            if ($ur->trashed()) {
                $reasons[] = 'removido (soft-deleted)';
            }
            if (! $ur->is_active) {
                $reasons[] = 'UserRole.is_active=false';
            }
            if (! $roleIsRole) {
                $reasons[] = 'role inexistente';
            } elseif (! $role->is_active) {
                $reasons[] = 'Role.is_active=false';
            }
            if ($companyId !== null && $ur->company_id !== null && $ur->company_id !== $companyId) {
                $reasons[] = "empresa diferente (bind={$ur->company_id}, contexto={$companyId})";
            }

            $status = $reasons === [] ? '<fg=green>CONTA</>' : '<fg=red>NÃO CONTA</> — '.implode('; ', $reasons);

            $this->line(sprintf(
                "  role='%s' is_master=%s is_active=%s company_id=%s trashed=%s → %s",
                $roleIsRole ? $role->name : '(role ausente)',
                $roleIsRole && $role->is_master ? 'true' : 'false',
                $ur->is_active ? 'true' : 'false',
                $ur->company_id === null ? 'null (global)' : (string) $ur->company_id,
                $ur->trashed() ? 'true' : 'false',
                $status,
            ));
        }

        $this->newLine();
    }

    /**
     * (c) Prints every PageObject registered under the requested obj_key —
     * a collision across pages prints all of them.
     *
     * @return Collection<int, PageObject>
     */
    protected function printPageObjects(string $bareObjKey): Collection
    {
        $this->line("<fg=cyan>PageObjects com obj_key='{$bareObjKey}':</>");

        $objects = PageObject::where('obj_key', $bareObjKey)->with('page')->get();

        if ($objects->isEmpty()) {
            $this->line('  (nenhum PageObject encontrado)');
            $this->newLine();

            return $objects;
        }

        foreach ($objects as $obj) {
            $page = $obj->page;
            $pageIsPage = $page instanceof PtahPage;

            $this->line(sprintf(
                "  page='%s' (is_active=%s) section='%s' is_active=%s",
                $pageIsPage ? $page->slug : '(página ausente)',
                $pageIsPage && $page->is_active ? 'true' : 'false',
                $obj->section,
                $obj->is_active ? 'true' : 'false',
            ));
        }

        if ($objects->pluck('page.slug')->filter()->unique()->count() > 1) {
            $this->line("  ⚠ obj_key COLIDE entre páginas — use a chave qualificada 'page::obj_key' para desambiguar.");
        }

        $this->newLine();

        return $objects;
    }

    /**
     * (d) Prints every RolePermission crossing this user's UserRoles with
     * the PageObjects found above, including trashed ones and the 4 can_*.
     *
     * @param  Collection<int, PageObject>  $allObjects
     */
    protected function printBinds(int $userId, Collection $allObjects): void
    {
        $this->line('<fg=cyan>RolePermissions (papel × objeto):</>');

        if ($allObjects->isEmpty()) {
            $this->line('  (sem PageObject para cruzar — nada a mostrar)');
            $this->newLine();

            return;
        }

        $roleIds = UserRole::withTrashed()->where('user_id', $userId)->pluck('role_id')->unique()->values();
        $objIds = $allObjects->pluck('id')->values();

        $binds = RolePermission::withTrashed()
            ->whereIn('role_id', $roleIds)
            ->whereIn('page_object_id', $objIds)
            ->with(['role', 'pageObject.page'])
            ->get();

        if ($binds->isEmpty()) {
            $this->line('  (nenhum RolePermission vincula os papéis do usuário a este obj_key)');
            $this->newLine();

            return;
        }

        foreach ($binds as $bind) {
            $role = $bind->role;
            $roleIsRole = $role instanceof Role;

            $pageObject = $bind->pageObject;
            $pageObjectIsPageObject = $pageObject instanceof PageObject;

            $page = $pageObjectIsPageObject ? $pageObject->page : null;
            $pageIsPage = $page instanceof PtahPage;

            $this->line(sprintf(
                "  role='%s' page='%s' section='%s' can_create=%s can_read=%s can_update=%s can_delete=%s trashed=%s",
                $roleIsRole ? $role->name : '(role ausente)',
                $pageIsPage ? $page->slug : '(página ausente)',
                $pageObjectIsPageObject ? $pageObject->section : '',
                $bind->can_create ? 'true' : 'false',
                $bind->can_read ? 'true' : 'false',
                $bind->can_update ? 'true' : 'false',
                $bind->can_delete ? 'true' : 'false',
                $bind->trashed() ? 'true' : 'false',
            ));
        }

        $this->newLine();
    }

    /**
     * (f) Determines the single most specific missing piece explaining a
     * denial, in the documented precedence order.
     *
     * @param  Collection<int, PageObject>  $allObjects  Every PageObject sharing $bareObjKey (any page)
     * @return array{0: string, 1: string} [reasonKey, humanMessage]
     */
    protected function diagnose(
        string $bareObjKey,
        ?string $pageSlug,
        ?string $section,
        string $action,
        ?int $companyId,
        int $userId,
        Collection $allObjects
    ): array {
        $filtered = $allObjects;
        if ($pageSlug !== null) {
            $filtered = $filtered->filter(function (PageObject $o) use ($pageSlug) {
                $page = $o->page;

                return $page instanceof PtahPage && $page->slug === $pageSlug;
            });
            if ($section !== null) {
                $filtered = $filtered->filter(fn (PageObject $o) => $o->section === $section);
            }
        }

        // 1. Nonexistent object.
        if ($filtered->isEmpty()) {
            $where = $pageSlug !== null
                ? " na página '{$pageSlug}'".($section !== null ? " / seção '{$section}'" : '')
                : '';

            return ['objeto_inexistente', "Nenhum PageObject encontrado para obj_key='{$bareObjKey}'{$where}."];
        }

        // 2. Inactive object.
        $activeOnly = $filtered->filter(fn (PageObject $o) => $o->is_active);
        if ($activeOnly->isEmpty()) {
            return ['objeto_inativo', "O objeto '{$bareObjKey}' existe mas está inativo (PageObject.is_active=false)."];
        }

        // 3. Inactive page.
        $activeWithActivePage = $activeOnly->filter(function (PageObject $o) {
            $page = $o->page;

            return $page instanceof PtahPage && $page->is_active;
        });
        if ($activeWithActivePage->isEmpty()) {
            $firstPage = $activeOnly->first()->page;
            $slug = $firstPage instanceof PtahPage ? $firstPage->slug : '(desconhecida)';

            return ['pagina_inativa', "O objeto '{$bareObjKey}' está ativo, mas a página '{$slug}' está inativa (PtahPage.is_active=false)."];
        }

        // 4. No active role at all.
        $userRoles = UserRole::withTrashed()->where('user_id', $userId)->with('role')->get();
        $activeUserRoles = $userRoles->filter(function (UserRole $ur) {
            $role = $ur->role;

            return ! $ur->trashed() && $ur->is_active && $role instanceof Role && $role->is_active;
        });

        if ($activeUserRoles->isEmpty()) {
            return ['nenhum_papel_ativo', 'O usuário não possui nenhum papel (role) ativo — nenhum UserRole ativo aponta para um Role ativo.'];
        }

        $roleIds = $activeUserRoles->pluck('role_id')->unique()->values();
        $objIds = $activeWithActivePage->pluck('id')->unique()->values();

        $binds = RolePermission::withTrashed()->whereIn('role_id', $roleIds)->whereIn('page_object_id', $objIds)->get();

        // 5. No bind at all (with a peek at other pages for the same obj_key).
        if ($binds->isEmpty()) {
            if ($pageSlug !== null) {
                $otherObjIds = $allObjects->pluck('id')->diff($objIds)->values();
                $otherPageBinds = RolePermission::withTrashed()
                    ->whereIn('role_id', $roleIds)
                    ->whereIn('page_object_id', $otherObjIds)
                    ->whereNull('deleted_at')
                    ->where("can_{$action}", true)
                    ->with('pageObject.page')
                    ->get();

                if ($otherPageBinds->isNotEmpty()) {
                    $suggestions = $otherPageBinds
                        ->map(function (RolePermission $b) {
                            $pageObject = $b->pageObject;
                            $page = $pageObject instanceof PageObject ? $pageObject->page : null;

                            return $page instanceof PtahPage ? $page->slug : null;
                        })
                        ->filter()
                        ->map(fn (string $slug) => "{$slug}::{$bareObjKey}")
                        ->unique()
                        ->values()
                        ->implode(', ');

                    return ['grant_em_outra_pagina', "Existe um grant válido para obj_key='{$bareObjKey}' em outra página — use a chave qualificada: {$suggestions}"];
                }
            }

            $roleNames = $activeUserRoles->pluck('role.name')->filter()->implode(', ');

            return ['nenhum_bind', "Nenhum RolePermission vincula os papéis ativos do usuário ({$roleNames}) a este objeto."];
        }

        // 6. can_{action}=false on every bind found (trashed or not).
        $anyFlagTrue = $binds->contains(fn (RolePermission $b) => (bool) $b->{"can_{$action}"});
        if (! $anyFlagTrue) {
            return ['can_action_false', "Existe vínculo (RolePermission) mas can_{$action}=false para todos os papéis ativos do usuário neste objeto."];
        }

        // 7. Bind trashed — the only flag=true binds are soft-deleted.
        $activeGrantingBinds = $binds->filter(fn (RolePermission $b) => ! $b->trashed() && (bool) $b->{"can_{$action}"});
        if ($activeGrantingBinds->isEmpty()) {
            return ['bind_trashed', "O vínculo que concede can_{$action}=true foi removido (RolePermission soft-deleted)."];
        }

        // 8. Grant scoped to a different company.
        $grantingRoleIds = $activeGrantingBinds->pluck('role_id')->unique();
        $matchingUserRoles = $activeUserRoles->filter(fn (UserRole $ur) => $grantingRoleIds->contains($ur->role_id));
        $companyOk = $matchingUserRoles->contains(fn (UserRole $ur) => $ur->company_id === null || $ur->company_id === $companyId);

        if (! $companyOk) {
            $companies = $matchingUserRoles->pluck('company_id')->unique()
                ->map(fn ($c) => $c === null ? 'global' : (string) $c)->implode(', ');
            $current = $companyId === null ? 'nenhuma empresa (null)' : "empresa {$companyId}";

            return ['grant_em_outra_empresa', "Existe um grant válido, mas apenas para a(s) empresa(s) [{$companies}] — o contexto atual é {$current}."];
        }

        // Every layer checked out yet check() still denied — should not
        // happen; surfaced rather than silently reporting a wrong reason.
        return ['indeterminado', 'Não foi possível determinar a peça exata faltante — possível inconsistência entre este diagnóstico e o motor (PermissionService::check()). Revise manualmente.'];
    }
}

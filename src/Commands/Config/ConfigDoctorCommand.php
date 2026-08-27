<?php

declare(strict_types=1);

namespace Ptah\Commands\Config;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Support\ModelKey;
use Ptah\Support\SearchDropdownMask;
use Ptah\Support\SqlIdentifier;
use Ptah\Support\StyleRule;
use Ptah\Traits\SendsCrudNotifications;
use Throwable;

/**
 * Audits every row in `crud_configs` and surfaces the silent-failure classes the
 * per-model tooling can't see:
 *
 *   - orphan keys      → a model stored under a non-canonical key (e.g. the FQCN
 *                        "App\Models\X") that the runtime (which reads "X") never
 *                        finds. `--fix` rewrites them to the canonical key.
 *   - unresolved model → the key maps to no Eloquent class.
 *   - malformed config → fails ConfigSchemaValidator.
 *   - empty screen     → no columns (the listing would render blank).
 *   - legacy RBAC key  → permissions.identifier is set but permissions.permissionIdentifier
 *                        (the key the runtime actually reads) is empty — the screen is
 *                        silently ungated. `--fix` migrates the value.
 *   - legacy styles key → the flat 'styles' key some now-corrected callers used to
 *                        write; the runtime only reads 'contitionStyles'/'conditionStyles',
 *                        so any rule stored there silently never applies. `--fix`
 *                        normalises each item and folds it into 'contitionStyles'.
 *   - unusable row style → a 'contitionStyles' item that StyleRule::normalize()
 *                        rejects (empty field/style, unrecognised condition) — it
 *                        will never apply at render time. Warning only.
 *   - route ambiguity  → a model with both a global and a route-specific config
 *                        (the fallback is active — easy to mistake for a dup).
 *   - permissionIdentifier collision → two crud_configs for DIFFERENT models
 *                        share the same permissions.permissionIdentifier. Permission
 *                        resolution is global by that identifier, so granting access
 *                        to one model's screen grants it to the other's too.
 *   - obj_key collision → two ptah_page_objects on DIFFERENT pages share the same
 *                        obj_key. Resolution is global by obj_key too
 *                        (PermissionService::buildPermissionMap), same cross-grant risk.
 *   - unknown column permission key → a `colsPermission` tag (see
 *                        ColumnPermissionService::TAG) names no registered
 *                        PageObject (bare, or decomposed when qualified) — the
 *                        column silently becomes invisible to everyone but
 *                        MASTER. Warning only, never an error.
 *   - searchdropdown surface → per `searchdropdown` column, all warning-only:
 *                        (a) non-numeric/`< 1` colsSDLimit; (b) colsSDMask*
 *                        outside the built-ins (SearchDropdownMask::builtins());
 *                        (c) legacy dialect key present without its canonical
 *                        counterpart (colsSDMode/colsSDValueField/
 *                        colsSDLabelField/colsSDOrderBy) — `--fix` copies the
 *                        legacy value into the canonical key (idempotent,
 *                        never deletes the legacy one); (d) colsSDFilters that
 *                        isn't valid JSON/array; (e) a colsSDArraySearch
 *                        column SqlIdentifier::isSafe() rejects; (f) colsSDMode
 *                        outside model|service; (g) a service-mode
 *                        colsSDService class outside
 *                        config('ptah.crud.sd_service_namespaces') — purely
 *                        diagnostic, not enforced at request time.
 *   - notification delivery → per config with `notifications.rules`, all
 *                        warning-only: (a) the model lacks the
 *                        SendsCrudNotifications trait, so no rule can ever
 *                        fire; (b) an audience naming a role/user that does
 *                        not exist, or left empty; (c) the queue connection is
 *                        not `sync`, which means delivery only happens while a
 *                        worker is running — the silent-nothing this check
 *                        exists to make visible.
 *   - MASTER scoped by company_id → a `ptah_user_roles` binding for a MASTER
 *                        role that still carries a `company_id`. Security
 *                        alert (not a config problem): `PermissionService::
 *                        queryIsMaster()` resolves MASTER globally regardless
 *                        of `company_id`, so the binding is not actually
 *                        scoped to that company — whoever created it believes
 *                        it is. Warning-only (does not change how MASTER
 *                        resolves); guarded against the permissions module
 *                        being uninstalled.
 *
 * Both collision checks are diagnostic only — they do not change how permissions
 * resolve, they only surface the pre-existing global-key sharing risk.
 *
 * Exit code is non-zero when any ERROR is found (CI-friendly).
 */
class ConfigDoctorCommand extends Command
{
    protected $signature = 'ptah:config:doctor {--fix : Rewrite non-canonical model keys (FQCN → the runtime key)}';

    protected $description = 'Audit crud_configs for orphan keys, malformed configs and route ambiguity';

    public function handle(ConfigSchemaValidator $validator, ModelIntrospector $introspector): int
    {
        $rows = CrudConfig::query()->get();

        if ($rows->isEmpty()) {
            $this->info('No crud_configs found — nothing to check.');

            return self::SUCCESS;
        }

        $errors = 0;
        $warnings = 0;
        $fixed = 0;

        /** @var array<string, string[]> $routesByModel */
        $routesByModel = [];

        // Collected during the main loop below, checked once at the end.
        /** @var array<string, array<string, true>> $modelsByPermissionIdentifier */
        $modelsByPermissionIdentifier = [];

        // Set by check 9 when ANY config carries notification rules — the queue
        // note at the end is emitted once, not once per screen.
        $anyNotificationRule = false;

        foreach ($rows as $row) {
            $model = (string) $row->model;
            $canonical = ModelKey::canonical($model);
            $route = (string) ($row->route ?? '');
            $config = $row->config ?? [];
            $label = $canonical.($route !== '' ? " @{$route}" : '');

            $routesByModel[$canonical][] = $route;

            // 1. Orphan key (non-canonical) — the runtime never reads this row.
            if ($model !== $canonical) {
                if ($this->option('fix')) {
                    $conflict = CrudConfig::query()
                        ->where('model', $canonical)
                        ->where('route', $route)
                        ->where('id', '!=', $row->id)
                        ->exists();

                    if ($conflict) {
                        $this->line("🔴 <fg=red>conflict</> [{$label}]: canonical key already exists — not rewritten");
                        $errors++;
                    } else {
                        $row->update(['model' => $canonical]);
                        $this->line("🔧 <fg=green>fixed</> key: '{$model}' → '{$canonical}'");
                        $fixed++;
                        $model = $canonical;
                    }
                } else {
                    $this->line("🔴 <fg=red>orphan key</> [{$label}]: stored as '{$model}' but the runtime reads '{$canonical}' — run with --fix");
                    $errors++;
                }
            }

            // 2. Unresolved model.
            if ($introspector->resolveClass($canonical) === null) {
                $this->line("🟡 <fg=yellow>unresolved model</> [{$label}]: no Eloquent class resolves from '{$canonical}'");
                $warnings++;
            }

            // 3. Malformed config.
            try {
                $validator->validate($config, $canonical);
            } catch (ConfigValidationException $e) {
                $this->line("🔴 <fg=red>malformed</> [{$label}]: {$e->getMessage()}");
                $errors++;
            }

            // 4. Empty screen.
            if (empty($config['cols'] ?? [])) {
                $this->line("🟡 <fg=yellow>no columns</> [{$label}]: the listing would render empty");
                $warnings++;
            }

            // 5. Legacy RBAC key — written to 'identifier', but the runtime reads
            //    'permissionIdentifier' (HasCrudForm::authorizeCrudAction / BaseCrud::
            //    getEffectivePermissions / ExportController). Fail-open: the screen
            //    silently runs without a gate.
            $perms = $config['permissions'] ?? [];
            if (! empty($perms['identifier']) && empty($perms['permissionIdentifier'])) {
                if ($this->option('fix')) {
                    $config['permissions']['permissionIdentifier'] = $perms['identifier'];
                    unset($config['permissions']['identifier']);
                    $row->update(['config' => $config]);
                    $this->line("🔧 <fg=green>fixed</> RBAC key [{$label}]: 'identifier' → 'permissionIdentifier'");
                    $fixed++;
                } else {
                    $this->line("🔴 <fg=red>legacy RBAC key</> [{$label}]: chave RBAC gravada em 'identifier' (legado); o runtime lê 'permissionIdentifier' — esta tela NÃO é gateada. Rode --fix");
                    $errors++;
                }
            }

            // 5b. Legacy 'styles' key — the flat key some now-corrected callers
            //     used to write to. The runtime only ever reads 'contitionStyles'
            //     (or its correctly-spelled read alias 'conditionStyles'), so any
            //     rule stored under 'styles' silently never applies. --fix
            //     normalises each item (StyleRule::normalize()) and folds the
            //     usable ones into 'contitionStyles', then drops 'styles'.
            $legacyStyles = $config['styles'] ?? [];
            if (! empty($legacyStyles)) {
                if ($this->option('fix')) {
                    $migrated = array_values(array_filter(array_map(
                        static fn (array $style): ?array => StyleRule::normalize($style),
                        $legacyStyles
                    )));

                    $config['contitionStyles'] = array_merge($config['contitionStyles'] ?? [], $migrated);
                    unset($config['styles']);
                    $row->update(['config' => $config]);
                    $this->line("🔧 <fg=green>fixed</> legacy styles key [{$label}]: 'styles' → 'contitionStyles' (".count($migrated).' rule(s) migrated)');
                    $fixed++;
                } else {
                    $this->line("🔴 <fg=red>legacy styles key</> [{$label}]: chave 'styles' gravada (legado); o runtime lê 'contitionStyles' — estas regras de estilo NÃO são aplicadas. Rode --fix");
                    $errors++;
                }
            }

            // 5c. Unusable row style — a 'contitionStyles' item that
            //     StyleRule::normalize() rejects (empty field/style, or an
            //     unrecognised condition) never applies at render time.
            //     Diagnostic only — not an error, since it does not make the
            //     screen unusable, only that one styling rule.
            foreach (($config['contitionStyles'] ?? []) as $styleIndex => $style) {
                if (StyleRule::normalize($style) === null) {
                    $this->line("🟡 <fg=yellow>unusable row style</> [{$label}] index {$styleIndex}: regra em 'contitionStyles' não normaliza (campo/estilo vazio ou condição desconhecida) — nunca será aplicada");
                    $warnings++;
                }
            }

            // 5d. Unknown column permission key — a `colsPermission` tag (see
            //     ColumnPermissionService::TAG) that names no registered
            //     PageObject at all (bare, or — when qualified — its
            //     decomposed page/section) can never be granted to anyone but
            //     MASTER: the column is invisible for every other user, and
            //     nothing else surfaces this typo (the gate fails closed
            //     silently, by design). Diagnostic only — a column simply not
            //     yet wired to a real object is a normal work-in-progress
            //     state, not a broken config.
            foreach (($config['cols'] ?? []) as $colIndex => $col) {
                $permissionKey = trim((string) ($col['colsPermission'] ?? ''));
                if ($permissionKey === '') {
                    continue;
                }

                if (! $this->permissionKeyExists($permissionKey)) {
                    $field = (string) ($col['colsNomeFisico'] ?? $colIndex);
                    $this->line("🟡 <fg=yellow>unknown column permission key</> [{$label}] column '{$field}': '{$permissionKey}' não corresponde a nenhum ptah_page_objects registrado — a coluna fica invisível para todos, exceto MASTER, até que a chave seja cadastrada");
                    $warnings++;
                }
            }

            // 5e. SearchDropdown surface — every check here is a warning, never
            //     an error: none of these make the column unusable (sdSettings()
            //     already has safe defaults/fallbacks for all of them), they
            //     only surface a config that likely doesn't do what its author
            //     intended.
            $sdConfigChanged = false;
            foreach (($config['cols'] ?? []) as $colIndex => $col) {
                if (($col['colsTipo'] ?? '') !== 'searchdropdown') {
                    continue;
                }

                $field = (string) ($col['colsNomeFisico'] ?? $colIndex);

                // (a) colsSDLimit non-numeric or < 1 — sdSettings() casts it to
                //     int regardless, silently turning a typo into 0 or worse.
                if (array_key_exists('colsSDLimit', $col) && (! is_numeric($col['colsSDLimit']) || (int) $col['colsSDLimit'] < 1)) {
                    $this->line("🟡 <fg=yellow>searchdropdown: invalid colsSDLimit</> [{$label}] column '{$field}': '{$col['colsSDLimit']}' não é um inteiro >= 1 — sdSettings() usará o resultado do cast (int)");
                    $warnings++;
                }

                // (b) colsSDMask* outside the built-ins — silently rendered raw.
                foreach (['colsSDMaskOne', 'colsSDMaskTwo', 'colsSDMaskThree'] as $maskKey) {
                    $mask = $col[$maskKey] ?? null;
                    if ($mask !== null && $mask !== 'defaultMask' && ! in_array($mask, SearchDropdownMask::builtins(), true)) {
                        $this->line("🟡 <fg=yellow>searchdropdown: unknown mask</> [{$label}] column '{$field}': {$maskKey}='{$mask}' não é um mask nativo (".implode(', ', SearchDropdownMask::builtins()).') — o valor será exibido cru');
                        $warnings++;
                    }
                }

                // (c) Legacy dialect key present without its canonical
                //     counterpart. sdSettings() already reads the legacy key as
                //     a fallback, so this never breaks the column — --fix just
                //     normalises it (idempotent: a second run finds nothing
                //     left to do, since the canonical key now exists).
                $legacyToCanonical = [
                    'colsSDMode' => 'colsSDTipo',
                    'colsSDValueField' => 'colsSDValor',
                    'colsSDLabelField' => 'colsSDLabel',
                    'colsSDOrderBy' => 'colsSDOrder',
                ];
                foreach ($legacyToCanonical as $legacyKey => $canonicalKey) {
                    if (! array_key_exists($legacyKey, $col) || array_key_exists($canonicalKey, $col)) {
                        continue;
                    }

                    // colsSDMode only aliases colsSDTipo when it holds a
                    // recognised value — 'both' (an earlier doc's mistake)
                    // must never be promoted to colsSDTipo.
                    if ($legacyKey === 'colsSDMode' && ! in_array($col[$legacyKey], ['model', 'service'], true)) {
                        continue;
                    }

                    if ($this->option('fix')) {
                        $config['cols'][$colIndex][$canonicalKey] = $col[$legacyKey];
                        $sdConfigChanged = true;
                        $this->line("🔧 <fg=green>fixed</> searchdropdown legacy key [{$label}] column '{$field}': '{$legacyKey}' → '{$canonicalKey}'");
                        $fixed++;
                    } else {
                        $this->line("🟡 <fg=yellow>searchdropdown: legacy dialect key</> [{$label}] column '{$field}': '{$legacyKey}' gravado sem a chave canônica '{$canonicalKey}' — sdSettings() já lê o legado como fallback, mas rode --fix para normalizar");
                        $warnings++;
                    }
                }

                // (d) Malformed colsSDFilters — not valid JSON/array, so
                //     sdNormalizeFilters() silently discards it (empty filter set).
                if (array_key_exists('colsSDFilters', $col) && ! $this->sdFiltersLookValid($col['colsSDFilters'])) {
                    $this->line("🟡 <fg=yellow>searchdropdown: malformed colsSDFilters</> [{$label}] column '{$field}': não é um JSON válido nem um array de filtros — será ignorado em runtime");
                    $warnings++;
                }

                // (e) arraySearch column rejected by SqlIdentifier — never
                //     reaches the query, silently.
                $arraySearchRaw = $col['colsSDArraySearch'] ?? null;
                if ($arraySearchRaw !== null) {
                    $arrayCols = is_array($arraySearchRaw) ? $arraySearchRaw : explode(',', (string) $arraySearchRaw);
                    foreach ($arrayCols as $arrayCol) {
                        $arrayCol = trim((string) $arrayCol);
                        if ($arrayCol !== '' && ! SqlIdentifier::isSafe($arrayCol)) {
                            $this->line("🟡 <fg=yellow>searchdropdown: unsafe arraySearch column</> [{$label}] column '{$field}': '{$arrayCol}' rejeitado por SqlIdentifier — nunca entrará na busca");
                            $warnings++;
                        }
                    }
                }

                // (f) colsSDMode outside model|service — never aliases colsSDTipo.
                if (array_key_exists('colsSDMode', $col) && ! in_array($col['colsSDMode'], ['model', 'service'], true)) {
                    $this->line("🟡 <fg=yellow>searchdropdown: invalid colsSDMode</> [{$label}] column '{$field}': '{$col['colsSDMode']}' não é 'model' nem 'service' — não é usado como alias de colsSDTipo (default 'model' aplicado)");
                    $warnings++;
                }

                // (g) Service-mode class outside the configured namespaces.
                //     Diagnostic only — see config('ptah.crud.sd_service_namespaces').
                $sdTipo = $col['colsSDTipo']
                    ?? (in_array($col['colsSDMode'] ?? null, ['model', 'service'], true) ? $col['colsSDMode'] : 'model');

                if ($sdTipo === 'service' && ! empty($col['colsSDService'])) {
                    $allowedNamespaces = (array) config('ptah.crud.sd_service_namespaces', []);
                    $serviceClass = (string) $col['colsSDService'];
                    $inNamespace = false;

                    foreach ($allowedNamespaces as $namespace) {
                        $prefix = rtrim((string) $namespace, '\\').'\\';
                        if ($serviceClass === $namespace || str_starts_with($serviceClass, $prefix)) {
                            $inNamespace = true;
                            break;
                        }
                    }

                    if (! $inNamespace && $allowedNamespaces !== []) {
                        $this->line("🟡 <fg=yellow>searchdropdown: service outside sd_service_namespaces</> [{$label}] column '{$field}': '{$serviceClass}' fora de ".implode(', ', $allowedNamespaces)." — hoje é apenas diagnóstico (config('ptah.crud.sd_service_namespaces'))");
                        $warnings++;
                    }
                }
            }

            if ($sdConfigChanged) {
                $row->update(['config' => $config]);
            }

            // 9. Notification delivery. Rules configured here are inert unless
            //    the model carries the trait, and the audience has to resolve to
            //    somebody. Every branch is warning-only: a rule that delivers to
            //    nobody is a misconfiguration, not a broken config file.
            $notificationRules = $config['notifications']['rules'] ?? [];

            if (is_array($notificationRules) && $notificationRules !== []) {
                $anyNotificationRule = true;

                $modelClass = $introspector->resolveClass($canonical);

                // The trait is what hooks the Eloquent events. Without it the
                // rules sit in the JSON looking correct and nothing ever fires —
                // the exact silent-nothing this check exists for.
                if ($modelClass !== null && ! in_array(SendsCrudNotifications::class, class_uses_recursive($modelClass), true)) {
                    $this->line("🟡 <fg=yellow>notifications: model without the trait</> [{$label}]: ".count($notificationRules)." rule(s) configured but {$modelClass} does not use SendsCrudNotifications — no rule can ever fire");
                    $warnings++;
                }

                foreach ($notificationRules as $index => $rule) {
                    if (! is_array($rule)) {
                        $this->line("🟡 <fg=yellow>notifications: malformed rule</> [{$label}] #{$index}: not an object — the runtime skips it");
                        $warnings++;

                        continue;
                    }

                    $audience = (string) ($rule['audience'] ?? '');
                    $audienceValue = trim((string) ($rule['audienceValue'] ?? ''));

                    if (in_array($audience, ['user', 'role'], true) && $audienceValue === '') {
                        $this->line("🟡 <fg=yellow>notifications: audience without a value</> [{$label}] #{$index}: audience '{$audience}' needs a ".($audience === 'user' ? 'user id' : 'role name').' — the rule delivers to nobody');
                        $warnings++;

                        continue;
                    }

                    // Past the guard above, $audienceValue is non-empty for role/user.
                    if ($audience === 'role' && $this->rolesAreQueryable()) {
                        // Same identity rule as ptah_has_role: case-insensitive,
                        // trimmed, NO slug — separators are not an equivalence
                        // class here (see the wave-5 decision).
                        $exists = Role::query()
                            ->get(['name'])
                            ->contains(fn (Role $role) => mb_strtolower(trim((string) $role->name)) === mb_strtolower($audienceValue));

                        if (! $exists) {
                            $this->line("🟡 <fg=yellow>notifications: unknown role</> [{$label}] #{$index}: no role named '{$audienceValue}' — the rule delivers to nobody");
                            $warnings++;
                        }
                    }

                    if ($audience === 'user' && ! $this->userExists($audienceValue)) {
                        $this->line("🟡 <fg=yellow>notifications: unknown user</> [{$label}] #{$index}: no user with id '{$audienceValue}' — the rule delivers to nobody");
                        $warnings++;
                    }

                    if (trim((string) ($rule['title'] ?? '')) === '') {
                        $this->line("🟡 <fg=yellow>notifications: empty title</> [{$label}] #{$index}: the runtime drops a rule whose resolved title is empty");
                        $warnings++;
                    }
                }
            }

            // Collected for check 6b below (after --fix, $config already reflects
            // the migrated key). Same identifier on a DIFFERENT model is a
            // cross-grant risk — resolution is global by permissionIdentifier.
            $permissionIdentifier = (string) ($config['permissions']['permissionIdentifier'] ?? '');
            if ($permissionIdentifier !== '') {
                $modelsByPermissionIdentifier[$permissionIdentifier][$canonical] = true;
            }
        }

        // 6. Route ambiguity (global + route-specific for the same model).
        foreach ($routesByModel as $canonical => $routes) {
            $hasGlobal = in_array('', $routes, true);
            $specific = array_values(array_filter($routes, fn (string $r) => $r !== ''));

            if ($hasGlobal && $specific !== []) {
                $this->line("ℹ️  <fg=cyan>route fallback</> [{$canonical}]: global config aplica-se às rotas sem config própria; ".count($specific).' route-specific ('.implode(', ', $specific).') sobrepõe(m) a global apenas na(s) sua(s) rota(s)');
            }
        }

        // 7. permissionIdentifier collision across DIFFERENT models. Resolution
        //    (HasCrudForm::authorizeCrudAction / BaseCrud::getEffectivePermissions /
        //    ExportController) is global by this identifier — sharing it grants
        //    cross-model access without anyone intending it. Diagnostic only: does
        //    not change how permissions resolve.
        foreach ($modelsByPermissionIdentifier as $identifier => $models) {
            $modelNames = array_keys($models);

            if (count($modelNames) > 1) {
                $this->line("🟡 <fg=yellow>permissionIdentifier collision</> '{$identifier}': compartilhado pelos models ".implode(', ', $modelNames).' — a resolução de permissão é global por esse identificador (crossgrant entre telas)');
                $warnings++;
            }
        }

        // 8. obj_key collision across DIFFERENT pages. The BARE map
        //    (PermissionService::buildPermissionMap) is still global by
        //    obj_key — granting access to one object grants it to the other
        //    too — but each grant can now be disambiguated by calling
        //    ptah_can()/check() with the QUALIFIED key ("{page.slug}::{obj_key}",
        //    or "{page.slug}::{section}::{obj_key}" if the same page also
        //    repeats the key across sections) instead of the bare obj_key.
        //    Diagnostic only — does not change how the bare map resolves.
        $objectsByKey = PageObject::query()->with('page:id,slug')->get()->groupBy('obj_key');

        foreach ($objectsByKey as $key => $objects) {
            $pageSlugs = $objects->pluck('page.slug')->filter()->unique()->values();

            if ($pageSlugs->count() > 1) {
                $qualifiedForms = $pageSlugs->map(fn ($slug) => "{$slug}::{$key}")->implode(', ');
                $this->line("🟡 <fg=yellow>obj_key collision</> '{$key}': compartilhado pelas páginas ".implode(', ', $pageSlugs->all())." — a resolução do mapa bare é global por obj_key (crossgrant entre páginas); use a chave qualificada para desambiguar: {$qualifiedForms}");
                $warnings++;
            }
        }

        // 10. MASTER role scoped by company_id — PermissionService::queryIsMaster()
        //     resolves MASTER globally and never looks at the binding's
        //     company_id (see that method's docblock), so a company_id here
        //     scopes nothing; it only misleads whoever created the binding
        //     into believing access is limited to that company. Security
        //     alert, not a config defect — does not change how MASTER
        //     resolves. Guarded against the permissions module being
        //     uninstalled (same pattern as rolesAreQueryable()).
        if ($this->rolesAreQueryable()) {
            try {
                $scopedMasterBindings = UserRole::query()
                    ->whereNotNull('company_id')
                    ->whereHas('role', fn ($q) => $q->where('is_master', true))
                    ->with('role:id,name')
                    ->get();
            } catch (Throwable) {
                $scopedMasterBindings = collect();
            }

            foreach ($scopedMasterBindings as $binding) {
                $this->line("🔴 <fg=red>SECURITY ALERT — MASTER scoped by company_id</> user_id={$binding->user_id} role='{$binding->role?->name}' company_id={$binding->company_id}: MASTER é global (PermissionService::queryIsMaster() ignora company_id) — este vínculo NÃO restringe o acesso à empresa indicada, embora pareça que sim");
                $warnings++;
            }
        }

        // 9b. The queue note, emitted once. Delivery is a queued job on purpose
        //     (a notification must never slow down or break the save), but on any
        //     driver other than `sync` that means NOTHING is delivered while no
        //     worker runs — the job just sits in the queue looking like the
        //     feature is broken. We cannot detect a running worker reliably, so
        //     the honest move is to state the condition.
        if ($anyNotificationRule) {
            $connection = (string) config('queue.default');

            if ($connection !== 'sync') {
                $this->line("ℹ️  <fg=cyan>notifications: queue</> connection '{$connection}' — CRUD notifications are queued jobs, so they are only delivered while a worker is running (`php artisan queue:work`). Nothing is lost meanwhile: the job waits in the queue.");
            }
        }

        $this->newLine();
        $summary = "Checked {$rows->count()} config(s): {$errors} error(s), {$warnings} warning(s)";
        if ($this->option('fix')) {
            $summary .= ", {$fixed} fixed";
        }
        $errors > 0 ? $this->error($summary) : $this->info($summary);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Whether the roles table can be queried at all. The permissions module is
     * optional, so a host can have notification rules naming a role while the
     * table does not exist — that is not a finding, it is an uninstalled
     * module, and querying it would throw.
     */
    protected function rolesAreQueryable(): bool
    {
        try {
            return Schema::hasTable((new Role)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether $id names an existing user, via the configured user model. Returns
     * true (i.e. "no finding") whenever the question cannot be answered — an
     * unresolvable user model or an unreadable table must not be reported as a
     * broken rule.
     */
    protected function userExists(string $id): bool
    {
        if (! ctype_digit($id)) {
            return false;
        }

        /** @var class-string<Model>|mixed $userModel */
        $userModel = config('ptah.permissions.user_model', 'App\Models\User');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return true;
        }

        try {
            return $userModel::query()->whereKey((int) $id)->exists();
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Resolves whether $key names a registered `PageObject` — bare, or, when
     * it contains `PermissionService::KEY_QUALIFIER`, decomposed into
     * page/section. Mirrors `PermissionService::decomposeQualifiedKey()`'s
     * literal-first order (duplicated here — pure string parsing for a
     * diagnostic, not an authorization decision): a bare `obj_key` that
     * happens to contain `::` is checked as-is FIRST, so it is never wrongly
     * decomposed into a page/section that doesn't exist.
     */
    protected function permissionKeyExists(string $key): bool
    {
        if (PageObject::query()->where('obj_key', $key)->exists()) {
            return true;
        }

        if (! str_contains($key, PermissionService::KEY_QUALIFIER)) {
            return false;
        }

        $parts = explode(PermissionService::KEY_QUALIFIER, $key);

        if (count($parts) >= 3) {
            $bareObjKey = implode(PermissionService::KEY_QUALIFIER, array_slice($parts, 2));
            $pageSlug = $parts[0];
            $section = $parts[1];
        } else {
            $bareObjKey = $parts[1];
            $pageSlug = $parts[0];
            $section = null;
        }

        return PageObject::query()
            ->where('obj_key', $bareObjKey)
            ->when($section !== null, fn ($q) => $q->where('section', $section))
            ->whereHas('page', fn ($q) => $q->where('slug', $pageSlug))
            ->exists();
    }

    /**
     * Structural-only validity check for colsSDFilters (check 5e-d) — a
     * string must decode as valid JSON into an array; anything already an
     * array is accepted as-is. Does NOT validate individual filter items
     * (unsafe columns/operators are silently dropped at runtime by
     * HasCrudSearchDropdown::sdNormalizeFilters() — that degrades safely, so
     * it is not itself an error condition worth flagging here).
     */
    protected function sdFiltersLookValid(mixed $raw): bool
    {
        if ($raw === null || $raw === '') {
            return true;
        }

        if (is_array($raw)) {
            return true;
        }

        if (! is_string($raw)) {
            return false;
        }

        json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

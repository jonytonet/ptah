<?php

declare(strict_types=1);

namespace Ptah\Commands\Config;

use Illuminate\Console\Command;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Support\ModelKey;
use Ptah\Support\StyleRule;

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

        // 8. obj_key collision across DIFFERENT pages. Resolution
        //    (PermissionService::buildPermissionMap) is global by obj_key — the
        //    same key on two pages means granting access to one object grants it
        //    to the other too. Diagnostic only.
        $objectsByKey = PageObject::query()->with('page:id,slug')->get()->groupBy('obj_key');

        foreach ($objectsByKey as $key => $objects) {
            $pageSlugs = $objects->pluck('page.slug')->filter()->unique()->values();

            if ($pageSlugs->count() > 1) {
                $this->line("🟡 <fg=yellow>obj_key collision</> '{$key}': compartilhado pelas páginas ".implode(', ', $pageSlugs->all()).' — a resolução de permissão é global por obj_key (crossgrant entre páginas)');
                $warnings++;
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
}

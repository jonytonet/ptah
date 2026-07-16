<?php

declare(strict_types=1);

namespace Ptah\Commands\Config;

use Illuminate\Console\Command;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Models\CrudConfig;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Support\ModelKey;

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
 *   - route ambiguity  → a model with both a global and a route-specific config
 *                        (the fallback is active — easy to mistake for a dup).
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
        }

        // 5. Route ambiguity (global + route-specific for the same model).
        foreach ($routesByModel as $canonical => $routes) {
            $hasGlobal = in_array('', $routes, true);
            $specific = array_values(array_filter($routes, fn (string $r) => $r !== ''));

            if ($hasGlobal && $specific !== []) {
                $this->line("ℹ️  <fg=cyan>route fallback</> [{$canonical}]: global config + ".count($specific).' route-specific ('.implode(', ', $specific).') — global is the fallback');
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

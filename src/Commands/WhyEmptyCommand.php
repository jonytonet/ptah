<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Ptah\Support\WhyEmptyInspector;

/**
 * `ptah:why-empty Product --as=5` — why a screen shows no rows for a user.
 *
 * Mounts the screen as that user (their preferences, company, config) and
 * prints the row count after each layer the listing applies, so the layer
 * that emptied it is the one where the count drops. Also prints the final
 * SQL with bindings, and says when the query itself fails (BaseCrud turns
 * that into an empty page). Read-only.
 *
 * Uso:
 *   php artisan ptah:why-empty Catalog/Product --as=5
 *   php artisan ptah:why-empty Catalog/Product --as=5 --guard=portal --route=admin/products
 *   php artisan ptah:why-empty Catalog/Product --as=5 --json
 */
class WhyEmptyCommand extends Command
{
    protected $signature = 'ptah:why-empty
        {model : The screen\'s model key (as in crud_configs), e.g. Catalog/Product}
        {--as= : User id to inspect as (preferences and company are per user)}
        {--guard= : Guard for --as (default: the default guard)}
        {--route= : Screen path, for a route-specific config}
        {--json : Machine-readable output}';

    protected $description = 'Explain why a BaseCrud screen lists no rows for a user: row count after each layer, final SQL';

    public function handle(): int
    {
        if ($this->option('as') !== null) {
            $guard = $this->option('guard') ?: config('auth.defaults.guard');

            if (! Auth::guard($guard)->onceUsingId($this->option('as'))) {
                $this->components->error("No user with id {$this->option('as')} on guard \"{$guard}\".");

                return self::FAILURE;
            }

            Auth::shouldUse($guard);
        }

        try {
            $report = WhyEmptyInspector::inspect((string) $this->argument('model'), (string) ($this->option('route') ?? ''));
        } catch (\Throwable $e) {
            $this->components->error('Mounting the screen failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $who = Auth::check() ? 'user '.Auth::id() : 'anonymous (pass --as=<id>: preferences and company are per user)';
        $this->line("{$report['model']} as {$who}");

        if (! $report['can_read']) {
            $this->line('  <fg=red>✖</> this user cannot READ the screen — the listing is hidden, not empty. See: php artisan ptah:permission:why');
        }

        $previous = null;
        foreach ($report['layers'] as $layer) {
            $rows = $layer['rows'] === null ? 'error' : (string) $layer['rows'];
            $drop = $previous !== null && $layer['rows'] !== null && $layer['rows'] < $previous;
            $emptied = $drop && $layer['rows'] === 0;

            $this->line(sprintf(
                '  %s %-40s %8s  %s',
                $emptied ? '<fg=red>←</>' : ($drop ? '<fg=yellow>↓</>' : ' '),
                $layer['layer'],
                $rows,
                "<fg=gray>{$layer['note']}</>"
            ));

            if ($layer['rows'] !== null) {
                $previous = $layer['rows'];
            }
        }

        if ($report['final'] !== null) {
            $this->line("  rows on screen: {$report['final']}");
        }
        if ($report['sql']) {
            $this->line("  SQL: {$report['sql']}");
        }
        if ($report['error']) {
            $this->line("  <fg=red>✖</> {$report['error']}");
        }

        return self::SUCCESS;
    }
}

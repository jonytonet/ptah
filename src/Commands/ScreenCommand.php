<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Support\ScreenSummary;

/**
 * `ptah:screen Product` — a BaseCrud screen in about twenty lines.
 *
 * Columns (type, label, where shown, relation, renderer, rules), filters,
 * actions, styles, joins, hooks, permission and the settings that matter —
 * instead of `ptah:config --list` or the raw JSON, which run to thousands of
 * tokens of defaults. The read an agent does before touching a screen.
 *
 * Uso:
 *   php artisan ptah:screen Product
 *   php artisan ptah:screen Catalog/Product --route=admin/products
 *   php artisan ptah:screen Product --json
 */
class ScreenCommand extends Command
{
    protected $signature = 'ptah:screen
        {model : Model key, FQCN or short name (Product, Catalog/Product, App\Models\Catalog\Product)}
        {--route= : A route-specific config (default: every config of the model)}
        {--json : Machine-readable output}';

    protected $description = 'Summarize a BaseCrud screen: columns, filters, actions, styles, joins, hooks, permission';

    public function handle(): int
    {
        $rows = self::find((string) $this->argument('model'));

        if ($this->option('route') !== null) {
            $route = trim((string) $this->option('route'), '/');
            $rows = array_values(array_filter($rows, fn (CrudConfig $r) => trim((string) $r->route, '/') === $route));
        }

        if ($rows === []) {
            $this->components->error("No screen configured for \"{$this->argument('model')}\". See: php artisan ptah:map");

            return self::FAILURE;
        }

        $summaries = array_map(fn (CrudConfig $r) => ScreenSummary::build((string) $r->model, (string) ($r->route ?? ''), $r->config), $rows);

        if ($this->option('json')) {
            $this->line((string) json_encode(count($summaries) === 1 ? $summaries[0] : $summaries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->output->write(implode("\n", array_map(ScreenSummary::toText(...), $summaries)));

        return self::SUCCESS;
    }

    /**
     * Every config of the model: exact key first, then the canonical key of an
     * FQCN, then a unique short-name match.
     *
     * @return list<CrudConfig>
     */
    public static function find(string $input): array
    {
        $normalized = str_replace('\\', '/', trim($input));

        foreach (array_unique([$input, ModelKey::canonical($input), $normalized]) as $key) {
            $rows = CrudConfig::query()->where('model', $key)->orderBy('route')->get()->all();

            if ($rows !== []) {
                return $rows;
            }
        }

        $short = strtolower(basename($normalized));
        $matches = CrudConfig::query()->orderBy('model')->orderBy('route')->get()
            ->filter(fn (CrudConfig $r) => strtolower(basename(str_replace('\\', '/', (string) $r->model))) === $short);

        return $matches->pluck('model')->unique()->count() === 1 ? $matches->values()->all() : [];
    }
}

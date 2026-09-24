<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Ptah\Support\ProjectMap;

/**
 * `ptah:map` — the whole project, compact, for the start of a session.
 *
 * Entities (table, fields with types, FK targets, relations), configured
 * screens (route, permission, columns, filters), the database menu and the
 * TODOs the generators left. One read instead of opening every model,
 * migration and config.
 *
 * Uso:
 *   php artisan ptah:map
 *   php artisan ptah:map --json
 *   php artisan ptah:map --write          # .ptah/map.md, for an agent to read later
 */
class MapCommand extends Command
{
    protected $signature = 'ptah:map
        {--models= : Models directory (default: app/Models)}
        {--json : Machine-readable output}
        {--write : Also write the map to .ptah/map.md}';

    protected $description = 'Print a compact map of the project: entities, fields, relations, screens, menu and pending TODOs';

    public function handle(): int
    {
        $dir = $this->option('models') ?: app_path('Models');
        $models = ProjectMap::discoverModels($dir);
        $map = ProjectMap::build($models, app_path(), base_path());

        if ($this->option('json')) {
            $this->line((string) json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $text = ProjectMap::toText($map, app()->getNamespace().'Models\\');
        $this->output->write($text);

        if ($this->option('write')) {
            $path = base_path('.ptah/map.md');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $text);
            $this->components->info('Written to .ptah/map.md');
        }

        return self::SUCCESS;
    }
}

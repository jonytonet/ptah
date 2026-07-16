<?php

declare(strict_types=1);

namespace Ptah\Commands\Config;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;

/**
 * Exports EVERY BaseCrud configuration to a versionable directory — one
 * pretty-printed JSON file per (model, route). This is the git-friendly snapshot
 * of the whole config set that per-model `--export` never gave: commit the folder
 * and the configs become reviewable/diffable, and `ptah:config:import-all` rebuilds
 * them on a fresh database.
 */
class ConfigExportAllCommand extends Command
{
    protected $signature = 'ptah:config:export-all {path? : Target directory (default: database/ptah/crud-configs)}';

    protected $description = 'Export all crud_configs to a versionable directory (one JSON per model/route)';

    public function handle(): int
    {
        $dir = $this->targetDir();
        File::ensureDirectoryExists($dir);

        $rows = CrudConfig::query()->orderBy('model')->orderBy('route')->get();

        if ($rows->isEmpty()) {
            $this->info('No crud_configs to export.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $model = ModelKey::canonical((string) $row->model);
            $route = (string) ($row->route ?? '');

            $payload = [
                'model' => $model,
                'route' => $route,
                'config' => $row->config ?? [],
            ];

            $file = $dir.DIRECTORY_SEPARATOR.$this->fileName($model, $route);
            File::put($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $this->line("  <fg=green>exported</> {$model}".($route !== '' ? " @{$route}" : ''));
        }

        $this->newLine();
        $this->info("Exported {$rows->count()} config(s) to {$dir}");

        return self::SUCCESS;
    }

    protected function targetDir(): string
    {
        $path = (string) ($this->argument('path') ?? 'database/ptah/crud-configs');

        return $this->isAbsolute($path) ? $path : base_path($path);
    }

    /** Git-friendly, collision-free filename. model+route also live inside the JSON. */
    protected function fileName(string $model, string $route): string
    {
        $name = str_replace('/', '.', $model);
        if ($route !== '') {
            $name .= '__'.str_replace('/', '.', $route);
        }

        return $name.'.json';
    }

    protected function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}

<?php

declare(strict_types=1);

namespace Ptah\Commands\Config;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Support\ModelKey;

/**
 * Rebuilds every BaseCrud configuration from a directory produced by
 * `ptah:config:export-all`. Idempotent (upserts by model+route), so it doubles as
 * the seeding step for a fresh database. `model`/`route` are read from each file's
 * CONTENT (the filename is only for humans/git), and keys are canonicalised on the
 * way in so an old export can't reintroduce an orphan key.
 */
class ConfigImportAllCommand extends Command
{
    protected $signature = 'ptah:config:import-all {path? : Source directory (default: database/ptah/crud-configs)}';

    protected $description = 'Import all crud_configs from a directory produced by ptah:config:export-all';

    public function handle(CrudConfigService $service): int
    {
        $dir = $this->sourceDir();

        if (! File::isDirectory($dir)) {
            $this->error("Directory not found: {$dir}");

            return self::FAILURE;
        }

        $files = File::glob($dir.DIRECTORY_SEPARATOR.'*.json');

        if (empty($files)) {
            $this->info("No .json config files found in {$dir}");

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $payload = json_decode((string) File::get($file), true);

            if (! is_array($payload) || ! isset($payload['model'], $payload['config']) || ! is_array($payload['config'])) {
                $this->line('  🟡 <fg=yellow>skipped</> '.basename($file).': not a valid config export');
                $skipped++;

                continue;
            }

            $model = ModelKey::canonical((string) $payload['model']);
            $route = (string) ($payload['route'] ?? '');

            $service->save($model, $payload['config'], $route);
            $this->line("  <fg=green>imported</> {$model}".($route !== '' ? " @{$route}" : ''));
            $imported++;
        }

        $this->newLine();
        $this->info("Imported {$imported} config(s)".($skipped > 0 ? ", skipped {$skipped}" : '')." from {$dir}");

        return self::SUCCESS;
    }

    protected function sourceDir(): string
    {
        $path = (string) ($this->argument('path') ?? 'database/ptah/crud-configs');

        return $this->isAbsolute($path) ? $path : base_path($path);
    }

    protected function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}

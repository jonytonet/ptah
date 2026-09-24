<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Ptah\Commands\PreferencesRealignCommand;

/**
 * What a `composer update` of ptah leaves for THIS host to do.
 *
 * The CHANGELOG says what changed in the package; it cannot say which of
 * those changes needs a hand in a given project — that depends on what the
 * host published and configured. Each check here reads the host's own state:
 *
 *  - config/ptah.php published → `mergeConfigFrom` is shallow, so a nested key
 *    added by a newer version never reaches the host; and keys the package no
 *    longer reads sit there looking meaningful;
 *  - views published to resources/views/vendor/ptah → they shadow the
 *    package's, fixes included (1.34.8's URL sanitizing in the sidebar is
 *    invisible to a host with an old copy);
 *  - stubs published to stubs/ptah → ptah:forge keeps generating the old code;
 *  - the user_preferences FK vs the configured identity (1.34.6);
 *  - migrations of the package not yet run;
 *  - the structure editor, closed by default since 1.34.8.
 *
 * Findings are `level` warn (needs an action) or info, with the action.
 */
final class UpgradeInspector
{
    private const MAX_LISTED = 15;

    /**
     * @return list<array{check: string, level: 'warn'|'info', message: string, action: string}>
     */
    public static function inspect(string $packageRoot): array
    {
        return [
            ...self::config($packageRoot),
            ...self::views($packageRoot),
            ...self::stubs($packageRoot),
            ...self::preferencesForeignKey(),
            ...self::pendingMigrations(),
            ...self::structureEditor(),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function config(string $packageRoot): array
    {
        $published = config_path('ptah.php');

        if (! is_file($published)) {
            return [];
        }

        $defaults = require $packageRoot.'/config/ptah.php';
        $host = require $published;

        if (! is_array($defaults) || ! is_array($host)) {
            return [];
        }

        $out = [];
        $missing = array_values(array_filter(
            self::leafPaths($defaults),
            // Chave de primeiro nivel ausente o merge raso ainda preenche; o
            // problema e so o que fica ANINHADO.
            fn (string $path) => str_contains($path, '.') && ! Arr::has($host, $path) && self::parentExists($host, $path)
        ));

        if ($missing !== []) {
            $out[] = [
                'check' => 'config',
                'level' => 'warn',
                'message' => count($missing).' key(s) of the current version are missing from your config/ptah.php (mergeConfigFrom is shallow — they are NOT defaulted): '.self::list($missing),
                'action' => 'copy them from vendor/jonytonet/ptah/config/ptah.php into config/ptah.php',
            ];
        }

        $dead = array_values(array_filter(
            self::leafPaths($host),
            fn (string $path) => ! Arr::has($defaults, $path) && self::isClosedSection($defaults, $path)
        ));

        if ($dead !== []) {
            $out[] = [
                'check' => 'config',
                'level' => 'info',
                'message' => count($dead).' key(s) in config/ptah.php are not read by this version: '.self::list($dead),
                'action' => 'remove them — they look meaningful and do nothing',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function views(string $packageRoot): array
    {
        $dir = resource_path('views/vendor/ptah');

        if (! is_dir($dir)) {
            return [];
        }

        $identical = $differs = $dead = [];

        foreach (self::files($dir, '.blade.php') as $relative) {
            $package = $packageRoot.'/resources/views/'.$relative;

            if (! is_file($package)) {
                $dead[] = $relative;
            } elseif (self::normalized($dir.'/'.$relative) === self::normalized($package)) {
                $identical[] = $relative;
            } else {
                $differs[] = $relative;
            }
        }

        $out = [];

        if ($differs !== []) {
            $out[] = ['check' => 'views', 'level' => 'warn', 'message' => count($differs).' published view(s) differ from this version and shadow its changes (fixes included): '.self::list($differs), 'action' => 'diff each against vendor/jonytonet/ptah/resources/views and re-apply your edit on the new version'];
        }
        if ($identical !== []) {
            $out[] = ['check' => 'views', 'level' => 'info', 'message' => count($identical).' published view(s) are identical to the package — they only freeze future updates: '.self::list($identical), 'action' => 'delete them from resources/views/vendor/ptah'];
        }
        if ($dead !== []) {
            $out[] = ['check' => 'views', 'level' => 'info', 'message' => count($dead).' published view(s) no longer exist in the package: '.self::list($dead), 'action' => 'delete them'];
        }

        return $out;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function stubs(string $packageRoot): array
    {
        $dir = base_path('stubs/ptah');

        if (! is_dir($dir)) {
            return [];
        }

        $differs = [];
        foreach (self::files($dir, '.stub') as $relative) {
            $package = $packageRoot.'/src/Stubs/'.$relative;
            if (is_file($package) && self::normalized($dir.'/'.$relative) !== self::normalized($package)) {
                $differs[] = $relative;
            }
        }

        return $differs === [] ? [] : [[
            'check' => 'stubs',
            'level' => 'warn',
            'message' => count($differs).' published stub(s) differ from this version — ptah:forge keeps generating the old code: '.self::list($differs),
            'action' => 'diff against vendor/jonytonet/ptah/src/Stubs, or delete stubs/ptah/<file> to use the package\'s',
        ]];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function preferencesForeignKey(): array
    {
        try {
            if (! Schema::hasTable('user_preferences')) {
                return [];
            }

            $identity = UserIdentity::resolve();
            $wanted = $identity->shouldConstrain() ? $identity->table : null;
            $current = PreferencesRealignCommand::currentTarget();
        } catch (\Throwable) {
            return [];
        }

        if ($current === $wanted) {
            return [];
        }

        return [[
            'check' => 'preferences',
            'level' => 'warn',
            'message' => 'user_preferences.user_id points at '.($current ?? 'nothing').', the configured identity is '.($wanted ?? 'unconstrained').' — saving preferences can fail on the FK',
            'action' => 'php artisan ptah:preferences:realign --dry-run, then without --dry-run',
        ]];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function pendingMigrations(): array
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return [];
            }

            $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
            $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
        } catch (\Throwable) {
            return [];
        }

        return $pending === [] ? [] : [[
            'check' => 'migrations',
            'level' => 'warn',
            'message' => count($pending).' migration(s) not run: '.self::list($pending),
            'action' => 'php artisan migrate (back up production first)',
        ]];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function structureEditor(): array
    {
        $modules = (array) config('ptah.modules', []);

        if (config('ptah.structure_editor') || (empty($modules['menu']) && empty($modules['company']))) {
            return [];
        }

        return [[
            'check' => 'structure_editor',
            'level' => 'info',
            'message' => 'the menu/company screens (/ptah-menu, /ptah-companies) are closed: PTAH_STRUCTURE_EDITOR is off (default since 1.34.8)',
            'action' => 'set PTAH_STRUCTURE_EDITOR=true only where administrators edit the menu or companies from the UI',
        ]];
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * Dotted paths of the leaves of an ASSOCIATIVE tree. A list, or an empty
     * array, is a value (a user-filled map), not a section to descend into.
     *
     * @return list<string>
     */
    private static function leafPaths(array $tree, string $prefix = ''): array
    {
        $out = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                array_push($out, ...self::leafPaths($value, $path));
            } else {
                $out[] = $path;
            }
        }

        return $out;
    }

    /**
     * A key missing under a section the host deleted entirely is ONE finding
     * (the section), not twenty; report only where the parent survives.
     */
    private static function parentExists(array $host, string $path): bool
    {
        $parent = substr($path, 0, (int) strrpos($path, '.'));

        return ! str_contains($path, '.') || Arr::has($host, $parent);
    }

    /**
     * A host key is "dead" only under a section the package fully defines —
     * never inside a map the host is meant to fill.
     */
    private static function isClosedSection(array $defaults, string $path): bool
    {
        if (! str_contains($path, '.')) {
            return true;
        }

        $parent = Arr::get($defaults, substr($path, 0, (int) strrpos($path, '.')));

        return is_array($parent) && $parent !== [] && ! array_is_list($parent);
    }

    /**
     * @return list<string>
     */
    private static function files(string $dir, string $suffix): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $base = rtrim(str_replace('\\', '/', $dir), '/').'/';

        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->isFile() && str_ends_with($path, $suffix)) {
                $out[] = substr($path, strlen($base));
            }
        }

        sort($out);

        return $out;
    }

    private static function normalized(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /**
     * @param  list<string>  $items
     */
    private static function list(array $items): string
    {
        $shown = array_slice($items, 0, self::MAX_LISTED);

        return implode(', ', $shown).(count($items) > self::MAX_LISTED ? ' … (+'.(count($items) - self::MAX_LISTED).')' : '');
    }
}

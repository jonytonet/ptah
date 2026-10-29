<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * ptah in Laravel Boost's `boost.json` `packages` list (1.53.0).
 *
 * Boost installs a third-party package's guidelines and skills only when it
 * is in that list. Interactively, `boost:install` asks; with
 * --no-interaction (and when ptah:install runs it) it takes the list as it is
 * — empty on a first install, so ptah's guidelines never landed. ptah adds
 * itself before calling `boost:install`, and `ptah:upgrade-check` reports a
 * `boost.json` that lacks it.
 */
final class BoostPackages
{
    public const PACKAGE = 'jonytonet/ptah';

    public static function path(): string
    {
        return base_path('boost.json');
    }

    /** Whether boost.json exists and lists ptah. Null when there is no (readable) boost.json. */
    public static function includesPtah(): ?bool
    {
        $config = self::read();

        return $config === null ? null : in_array(self::PACKAGE, (array) ($config['packages'] ?? []), true);
    }

    /**
     * Adds ptah to `packages`, keeping everything else. Creates boost.json when
     * missing. False when the file exists but is not valid JSON (left alone).
     */
    public static function includePtah(): bool
    {
        $path = self::path();
        $config = is_file($path) ? self::read() : [];

        if ($config === null) {
            return false;
        }

        $packages = array_values(array_unique([...((array) ($config['packages'] ?? [])), self::PACKAGE]));
        $config['packages'] = $packages;
        ksort($config);

        return file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL) !== false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(): ?array
    {
        $path = self::path();
        if (! is_file($path)) {
            return null;
        }

        $config = json_decode((string) file_get_contents($path), true);

        return is_array($config) ? $config : null;
    }
}

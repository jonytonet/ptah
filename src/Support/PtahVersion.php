<?php

declare(strict_types=1);

namespace Ptah\Support;

use Composer\InstalledVersions;
use Throwable;

/**
 * The installed version of the package, for display.
 *
 * There is deliberately no version constant and no `version` field in
 * composer.json: Packagist derives the version from the git tag, and a constant
 * would be a second source of truth that goes stale exactly when it matters —
 * right after a release. So the answer comes from Composer's own lock data,
 * which is what the host actually installed.
 *
 * `getPrettyVersion()` returns the tag as written (`v1.31.1`) for a released
 * install and a branch name (`dev-main`) inside this repo, where ptah is the
 * root package. Both are useful answers to "which ptah is this?", so neither is
 * hidden — only the leading `v` is trimmed, since the label already says ptah.
 */
final class PtahVersion
{
    public const PACKAGE = 'jonytonet/ptah';

    /** Resolved once per request; the lock data cannot change mid-request. */
    private static ?string $cached = null;

    public static function current(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        return self::$cached = self::for(self::PACKAGE);
    }

    /**
     * The same resolution for any package name.
     *
     * Exists so the fallback is reachable from a test: the interesting branch is
     * "Composer cannot answer", and the only honest way to reach it without
     * unloading a class mid-suite is to ask about a package that is not there.
     */
    public static function for(string $package): string
    {
        return self::resolve($package);
    }

    /**
     * Only for tests that need to observe a specific value or the fallback.
     */
    public static function flush(): void
    {
        self::$cached = null;
    }

    private static function resolve(string $package): string
    {
        // Every branch here is a real possibility rather than defensive noise:
        // the class is absent under Composer 1, the package is absent from the
        // lock data when it was required by path without an install, and
        // getPrettyVersion() is documented to return null for a metapackage.
        // A version label is decoration — it must never be the thing that
        // breaks a page.
        try {
            if (! class_exists(InstalledVersions::class)) {
                return 'unknown';
            }

            if (! InstalledVersions::isInstalled($package)) {
                return 'unknown';
            }

            $version = InstalledVersions::getPrettyVersion($package);
        } catch (Throwable) {
            return 'unknown';
        }

        if (! is_string($version) || $version === '') {
            return 'unknown';
        }

        return ltrim($version, 'v');
    }
}

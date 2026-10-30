<?php

declare(strict_types=1);

namespace Ptah\Mcp;

use Composer\InstalledVersions;
use Laravel\Mcp\Server;

/**
 * ptah's own MCP server, for hosts without Laravel Boost (1.52.0).
 *
 * With Boost, the same tools already ride on Boost's server (see
 * PtahServiceProvider::registerBoostTools). Without it, the host — or the
 * ptah VS Code extension — starts this one:
 *
 *     php artisan mcp:start ptah
 *
 * Extends a laravel/mcp class, so it is never referenced by `::class` outside
 * a class_exists() guard; the package does not require laravel/mcp.
 */
class PtahServer extends Server
{
    protected string $name = 'ptah';

    protected string $instructions = <<<'MARKDOWN'
        Tools for a Laravel app built with the ptah package (config-driven BaseCrud
        screens, stored as JSON in crud_configs). Prefer them to reading files:
        ptah-map for what exists, ptah-screen before editing a screen, ptah-docs for
        options and documentation, ptah-validate-config before saving a config you
        wrote, ptah-check and ptah-doctor after changes, ptah-why-empty and
        ptah-last-error when something does not show up or breaks. All are read-only.
        Screen and column permissions follow ptah.permissions.driver: "ptah" (this
        package's ACL) or "gate" (Laravel's Gate — Spatie, policies); ptah-screen says
        which one checks a screen.
    MARKDOWN;

    protected function boot(): void
    {
        $this->tools = PtahMcpTools::CLASSES;

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('jonytonet/ptah')) {
            $this->version = (string) (InstalledVersions::getPrettyVersion('jonytonet/ptah') ?? $this->version);
        }
    }
}

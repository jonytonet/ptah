<?php

declare(strict_types=1);

namespace Ptah\Support;

use Livewire\Livewire;

/**
 * Which screen a BaseCrud (or its config editor) belongs to — the key of the
 * screen-specific config (1.58.0).
 *
 * It was `request()->path()`. A BaseCrud that a parent renders AFTER the page
 * loaded (a `key()` that changes with a filter, an `@if`, a tab) mounts inside
 * the Livewire update request, whose path is the endpoint, not the page — and
 * the config lookup fell back to the global config without a word, losing the
 * screen's columns, buttons, actions and `permissionIdentifier`.
 *
 * In an update request the page path comes from the snapshot memo, which
 * Livewire checksums, so the client cannot pick another screen with it.
 */
final class CrudScreenRoute
{
    public static function current(): string
    {
        if (Livewire::isLivewireRequest()) {
            $path = Livewire::originalPath();

            // No memo in the snapshot: Livewire answers its 'POST' placeholder.
            // Not a page — the global config, as before.
            return $path === 'POST' ? '' : ltrim((string) $path, '/');
        }

        return ltrim(request()->path(), '/');
    }

    /** A route given by the host (mount param), normalised like the path. */
    public static function given(?string $route): ?string
    {
        if ($route === null) {
            return null;
        }

        return trim($route, '/');
    }
}

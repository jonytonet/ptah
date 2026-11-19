<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Ptah\Http\Middleware\PtahFullLoad;
use Throwable;

/**
 * The single place that decides whether a link of the Livewire layout swaps the
 * page in place (Livewire's navigate, see ATTRIBUTE) or does a full load.
 *
 * Off by default (`ptah.forge.spa_navigation`). When it is off every method
 * answers "full load" and the layout renders exactly the HTML it rendered
 * before the mode existed — a production host with its own pages and scripts
 * that assume a document load sees no difference until it opts in.
 *
 * When it is on, a link navigates in place only if all of these hold:
 *  - it stays in the same window: no target, or `_self`;
 *  - it is same-origin: relative, or http(s) with this request's scheme, host
 *    and port — `javascript:`, `mailto:`, `tel:`, `data:`, another host, a
 *    bare `#fragment` all keep the browser's own behaviour;
 *  - its path is not in `ptah.forge.spa_navigation_except`;
 *  - its route does not carry the `ptah.full-load` middleware;
 *  - the caller did not opt it out (a menu item with `'spa' => false`).
 *
 * Downloads, exports, print views, logout and anything that changes the
 * session (company switch, login) are never given the attribute by the
 * package: they are forms, `download` links, `_blank` links or server
 * redirects, and must stay full loads.
 */
final class SpaNavigation
{
    /**
     * The marker the layout's frame script reads: a click on such a link goes
     * through `Livewire.navigate(href)` — Livewire's own SPA swap.
     *
     * NOT `wire:navigate` / `wire:navigate.hover`, deliberately. Those prefetch
     * (on hover, and on mousedown for every `wire:navigate` link) into a cache
     * whose 30s expiry timer is keyed by URI, not by entry (Livewire 4.3.1,
     * js/plugins/navigate/prefetch.js): a NEWER prefetch of the same URI that
     * is in flight when an older entry's timer fires is deleted, and its
     * response then throws "Cannot set properties of undefined (setting
     * 'html')" — and a click waiting on that prefetch never navigates. Moving
     * between the same few screens of an admin (the whole point of the mode)
     * hits it. `Livewire.navigate()` fetches directly and never touches that
     * cache.
     */
    public const ATTRIBUTE = 'data-ptah-navigate';

    /**
     * The package's own pages that are NOT the dashboard layout: the Forge
     * component demo is a standalone document with its own Tailwind and Alpine
     * from a CDN — swapped in place it would start a second Alpine. (PetPlace
     * has it in its menu.)
     */
    public const OWN_FULL_LOAD_PAGES = ['ptah-forge-demo'];

    public static function enabled(): bool
    {
        // Read with a default, like the other forge.* keys: a host that
        // published config/ptah.php before this key existed does not have it,
        // and mergeConfigFrom() is shallow.
        return (bool) config('ptah.forge.spa_navigation', false)
            && class_exists(Livewire::class);
    }

    /**
     * Whether this link may navigate in place.
     */
    public static function allows(?string $url, ?string $target = null, bool $optIn = true): bool
    {
        if (! $optIn || ! self::enabled()) {
            return false;
        }

        $target = trim((string) $target);
        if ($target !== '' && strtolower($target) !== '_self') {
            return false;
        }

        $path = self::sameOriginPath($url);
        if ($path === null) {
            return false;
        }

        return ! self::excepted($path) && ! self::routeAsksForFullLoad($path);
    }

    /**
     * The attribute to print inside the `<a>` — with its leading space — or an
     * empty string. Empty when the mode is off, so the tag is byte-identical
     * to what it was before the mode existed.
     */
    public static function attr(?string $url, ?string $target = null, bool $optIn = true): string
    {
        return self::allows($url, $target, $optIn) ? ' '.self::ATTRIBUTE : '';
    }

    /**
     * Whether a menu node (config or database driver) may navigate in place.
     *
     * @param  array<string, mixed>  $node
     */
    public static function allowsMenuItem(array $node): bool
    {
        return self::allows(
            is_string($node['url'] ?? null) ? $node['url'] : null,
            is_string($node['target'] ?? null) ? $node['target'] : null,
            ! self::optsOut($node),
        );
    }

    /**
     * `'spa' => false` (or "0", "false", "off") on the node.
     *
     * @param  array<string, mixed>  $node
     */
    public static function optsOut(array $node): bool
    {
        if (! array_key_exists('spa', $node) || $node['spa'] === null) {
            return false;
        }

        return filter_var($node['spa'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false;
    }

    /**
     * The URLs of the menu tree whose node opted out with `'spa' => false`.
     *
     * @param  array<array-key, mixed>  $tree
     * @return list<string>
     */
    public static function optedOutUrls(array $tree): array
    {
        $out = [];

        foreach ($tree as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (is_string($node['url'] ?? null) && self::optsOut($node)) {
                $out[] = trim($node['url']);
            }
            if (is_array($node['children'] ?? null)) {
                $out = [...$out, ...self::optedOutUrls($node['children'])];
            }
        }

        return $out;
    }

    /**
     * A fingerprint of the host's Vite build ('' without one), printed in a
     * `<meta name="ptah-build">`. After an in-place navigation the layout
     * compares it with the one the document was loaded with and reloads when
     * a deploy changed it — Livewire's `data-navigate-track` (which it adds to
     * every `@vite` tag) only notices a changed QUERY STRING, and Vite's
     * hashed file names change the path.
     */
    public static function buildId(): string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return '';
        }

        // The manifest is a few KB; hashing it is cheaper than being wrong
        // after a deploy that kept the file's mtime.
        return substr((string) md5_file($manifest), 0, 12);
    }

    /** @var array<string, bool> */
    private static array $fullLoadRoutes = [];

    /**
     * Whether the GET route that answers this path carries the
     * `ptah.full-load` middleware (PtahFullLoad): a screen with a layout of
     * its own, marked where it is declared instead of listed in the config.
     * A path no route answers is not one.
     */
    public static function routeAsksForFullLoad(string $path): bool
    {
        try {
            $router = app('router');
            $routes = $router->getRoutes();
            $request = app('request');
            // Per route collection: another app instance (a test, an Octane
            // worker reload) has other routes.
            $key = spl_object_id($routes).'|'.$request->getSchemeAndHttpHost().'|'.$path;

            if (isset(self::$fullLoadRoutes[$key])) {
                return self::$fullLoadRoutes[$key];
            }
            if (count(self::$fullLoadRoutes) > 2000) {
                self::$fullLoadRoutes = [];
            }

            $route = $routes->match(Request::create($request->getSchemeAndHttpHost().'/'.ltrim($path, '/'), 'GET'));
            $middleware = $route->gatherMiddleware();

            return self::$fullLoadRoutes[$key] = in_array(PtahFullLoad::ALIAS, $middleware, true)
                || in_array(PtahFullLoad::class, $middleware, true);
        } catch (Throwable) {
            // No route (404), another verb, a route that cannot be matched here.
            return isset($key) ? self::$fullLoadRoutes[$key] = false : false;
        }
    }

    /**
     * Is this path (no leading slash) in the host's exception list?
     */
    public static function excepted(string $path): bool
    {
        $path = trim($path, '/');

        foreach ([...self::OWN_FULL_LOAD_PAGES, ...(array) config('ptah.forge.spa_navigation_except', [])] as $pattern) {
            if (! is_string($pattern) || trim($pattern) === '') {
                continue;
            }
            $pattern = trim($pattern, '/');
            if (Str::is($pattern, $path) || ($pattern === '' && $path === '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The path of a same-origin URL, or null when the URL leaves this origin,
     * is a fragment, or has a scheme other than http(s).
     */
    private static function sameOriginPath(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        // The browser's reading of the URL (see SafeUrl): tab/LF/CR removed
        // from anywhere, C0 controls and spaces trimmed from the ends.
        $url = trim(str_replace(["\t", "\n", "\r"], '', $url), "\x00..\x20");

        if ($url === '' || str_starts_with($url, '#')) {
            return null;
        }

        $request = app('request');

        if (str_starts_with($url, '//') || preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:/', $url) === 1) {
            $parts = parse_url(str_starts_with($url, '//') ? $request->getScheme().':'.$url : $url);
            if ($parts === false) {
                return null;
            }

            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if (! in_array($scheme, ['http', 'https'], true) || $scheme !== strtolower($request->getScheme())) {
                return null;
            }

            if (strtolower((string) ($parts['host'] ?? '')) !== strtolower($request->getHost())) {
                return null;
            }

            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
            if ((int) $port !== (int) $request->getPort()) {
                return null;
            }

            return (string) ($parts['path'] ?? '/');
        }

        // Relative: `/x`, `x`, `?q=1`. A relative URL cannot leave the origin.
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : $request->path();
    }
}

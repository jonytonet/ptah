<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Ptah\Services\Menu\MenuService;

/**
 * The one place that decides what the navigation menu IS.
 *
 * This logic used to live in a `@php` block inside forge-sidebar.blade.php: the
 * priority chain (an explicit `items` prop, then MenuService when the driver is
 * `database`, then `ptah.forge.sidebar_items`), the fixed Dashboard entry
 * prepended for the database driver, and the demo fallback for an empty menu.
 *
 * It moved here because it gained a second and a third consumer, and they must
 * not be able to disagree:
 *
 *   the sidebar        renders the tree;
 *   the jump box       filters the flattened links (same sidebar, same request);
 *   FindMenuTool       tells the AI where a screen lives.
 *
 * That last one is the reason this is a class and not a helper. An assistant
 * that answers "Cotação is under Compras" while the sidebar says something else
 * is worse than an assistant that cannot answer at all — the person follows the
 * instruction, does not find the screen, and stops trusting both. A second
 * implementation of "what is the menu" is exactly how that happens.
 */
final class MenuResolver
{
    /**
     * The menu tree, resolved the way the sidebar has always resolved it.
     *
     * @param  array<int, array<string, mixed>>|null  $items  An explicit tree, which wins over everything.
     * @return array<int, array<string, mixed>>
     */
    public static function tree(?array $items = null): array
    {
        $usingDatabase = (bool) config('ptah.modules.menu') && config('ptah.menu.driver') === 'database';

        if ($items !== null) {
            $tree = $items;
        } elseif ($usingDatabase) {
            $tree = app(MenuService::class)->getTree();
        } else {
            $raw = (array) config('ptah.forge.sidebar_items', []);
            $tree = array_map(
                static fn (array $i): array => array_merge(['children' => [], 'type' => 'menuLink'], $i),
                $raw
            );
        }

        // The database driver gets a fixed Dashboard at the top: it is a screen
        // the package owns, so it is not in the host's `menus` table.
        if ($usingDatabase && $items === null) {
            array_unshift($tree, [
                'id' => null,
                'label' => 'Dashboard',
                'text' => 'Dashboard',
                'url' => Route::has('ptah.dashboard') ? route('ptah.dashboard') : '/dashboard',
                'icon' => 'bx bx-home-alt',
                'type' => 'menuLink',
                'target' => '_self',
                'is_active' => true,
                'match' => 'dashboard',
                'children' => [],
            ]);
        }

        if ($tree === []) {
            return self::demoTree();
        }

        return $tree;
    }

    /**
     * Every reachable LINK in the menu, with the trail that leads to it.
     *
     * Groups are not returned: a `menuGroup` has no url in ptah, so it is not
     * somewhere you can be sent. It appears only inside `breadcrumb`, which is
     * the trail from the root down to the link — the order you would read out
     * loud to someone who has to click their way there.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @return list<array{label: string, url: string, icon: string, target: string, breadcrumb: list<string>, path: string, search: string}>
     */
    public static function flatLinks(?array $items = null): array
    {
        return self::walk(self::tree($items), []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  list<string>  $trail
     * @return list<array{label: string, url: string, icon: string, target: string, breadcrumb: list<string>, path: string, search: string}>
     */
    private static function walk(array $nodes, array $trail): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            // An item switched off in the menu admin is not somewhere to send
            // anyone, and the sidebar does not render it either.
            if (array_key_exists('is_active', $node) && ! $node['is_active']) {
                continue;
            }

            $label = trim((string) ($node['label'] ?? $node['text'] ?? ''));
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $url = trim((string) ($node['url'] ?? ''));
            $isGroup = ($node['type'] ?? 'menuLink') === 'menuGroup';

            if ($label === '') {
                continue;
            }

            if (! $isGroup && $url !== '' && $url !== '#') {
                $breadcrumb = [...$trail, $label];

                $out[] = [
                    'label' => $label,
                    'url' => $url,
                    'icon' => (string) ($node['icon'] ?? 'bx bx-circle'),
                    'target' => (string) ($node['target'] ?? '_self'),
                    'breadcrumb' => $breadcrumb,
                    // Root to leaf: how you tell someone where to click.
                    'path' => implode(' > ', $breadcrumb),
                    // What a search matches against. Accents folded here rather
                    // than at every call site, so "cotacao" finds "Cotação" —
                    // and so the PHP side and the JS side fold the same way.
                    'search' => self::fold(implode(' ', $breadcrumb)),
                ];
            }

            if ($children !== []) {
                $out = [...$out, ...self::walk($children, [...$trail, $label])];
            }
        }

        return $out;
    }

    /**
     * Lowercase, accent-free. Matches what the jump box does in the browser.
     */
    public static function fold(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }

    /**
     * Links whose trail matches every word of the query, in any order.
     *
     * Word-by-word rather than a substring of the whole: "cotacao compras"
     * should find "Compras > Cotação" even though those words never appear in
     * that order in the trail.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @return list<array{label: string, url: string, icon: string, target: string, breadcrumb: list<string>, path: string, search: string}>
     */
    public static function search(string $query, int $limit = 10, ?array $items = null): array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', self::fold(trim($query))) ?: [],
            static fn (string $w): bool => $w !== ''
        ));

        $links = self::flatLinks($items);

        if ($words === []) {
            return array_slice($links, 0, max(1, $limit));
        }

        $matches = array_values(array_filter($links, static function (array $link) use ($words): bool {
            foreach ($words as $word) {
                if (! str_contains($link['search'], $word)) {
                    return false;
                }
            }

            return true;
        }));

        return array_slice($matches, 0, max(1, $limit));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function demoTree(): array
    {
        return [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bx bx-home-alt', 'type' => 'menuLink', 'match' => 'dashboard', 'children' => []],
            ['label' => 'Users', 'url' => '/users', 'icon' => 'bx bx-user', 'type' => 'menuLink', 'match' => 'users*', 'children' => []],
            ['label' => 'Products', 'url' => '/products', 'icon' => 'bx bx-cube', 'type' => 'menuLink', 'match' => 'products*', 'children' => []],
            ['label' => 'Reports', 'url' => '/reports', 'icon' => 'bx bx-bar-chart', 'type' => 'menuLink', 'match' => 'reports*', 'children' => []],
            ['label' => 'Settings', 'url' => '/settings', 'icon' => 'bx bx-cog', 'type' => 'menuLink', 'match' => 'settings*', 'children' => []],
        ];
    }
}

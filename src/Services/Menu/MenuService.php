<?php

declare(strict_types=1);

namespace Ptah\Services\Menu;

use Illuminate\Support\Collection;
use Ptah\Models\Menu;

class MenuService
{
    /**
     * Returns the hierarchical menu structure for the sidebar.
     * Uses driver 'database' or 'config' as per ptah.menu.driver.
     */
    public function getTree(): array
    {
        $driver = config('ptah.menu.driver', 'config');

        if ($driver === 'database' && config('ptah.modules.menu', false)) {
            return Menu::getTreeForSidebar();
        }

        return $this->getFromConfig();
    }

    /**
     * Converts sidebar_items from the config to the same format as the DB tree.
     */
    private function getFromConfig(): array
    {
        $items = config('ptah.forge.sidebar_items', []);

        return array_map(function (array $item) {
            return [
                'id'         => null,
                'parent_id'  => null,
                'text'       => $item['label'] ?? '',
                'label'      => $item['label'] ?? '',
                'url'        => $item['url'] ?? '#',
                'icon'       => $item['icon'] ?? 'bx bx-circle',
                'type'       => 'menuLink',
                'target'     => '_self',
                'link_order' => 0,
                'is_active'  => true,
                'match'      => $item['match'] ?? null,
                'children'   => [],
            ];
        }, $items);
    }

    /**
     * Invalidates the menu cache (call after creating/editing/deleting items).
     */
    public function clearCache(): void
    {
        Menu::clearCache();
    }

    /**
     * Returns all root menus with children for the admin CRUD.
     */
    public function allForAdmin(): Collection
    {
        return Menu::withoutTrashed()->ordered()->with('children')->get();
    }

    /**
     * Flat list of menus for the parent_id select in BaseCrud.
     */
    public function listForSelect(): array
    {
        return Menu::withoutTrashed()
            ->active()
            ->where('type', 'menuGroup')
            ->ordered()
            ->pluck('text', 'id')
            ->toArray();
    }
}

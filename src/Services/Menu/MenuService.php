<?php

declare(strict_types=1);

namespace Ptah\Services\Menu;

use Illuminate\Support\Collection;
use Ptah\Models\Menu;

class MenuService
{
    /**
     * Retorna a estrutura hierárquica de menus para a sidebar.
     * Usa driver 'database' ou 'config' conforme ptah.menu.driver.
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
     * Converte sidebar_items do config no mesmo formato da tree do banco.
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
     * Invalida o cache do menu (chamar após criar/editar/deletar itens).
     */
    public function clearCache(): void
    {
        Menu::clearCache();
    }

    /**
     * Retorna todos os menus raiz com filhos para o CRUD de gestão.
     */
    public function allForAdmin(): Collection
    {
        return Menu::withoutTrashed()->ordered()->with('children')->get();
    }

    /**
     * Lista plana de menus para o select de parent_id no BaseCrud.
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

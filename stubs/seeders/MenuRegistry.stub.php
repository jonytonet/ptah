<?php

/**
 * MenuRegistry — Estrutura de menu gerada automaticamente.
 *
 * Este arquivo é atualizado pelo comando ptah:forge sempre que uma nova
 * entidade é criada. Você pode editar manualmente para customizar:
 *   - Ícones (classes Boxicons ou FontAwesome)
 *   - Labels (textos exibidos no menu)
 *   - Ordem de exibição (order)
 *
 * Após editar, sincronize com o banco de dados:
 *   php artisan ptah:menu-sync --fresh
 *
 * Estrutura:
 *   - dashboard: link único no topo
 *   - groups: agrupamentos expandíveis (ex: Catálogo, Agendamentos)
 *     - links: itens dentro de cada grupo
 *
 * @generated {{ now }}
 * @see https://boxicons.com/ (ícones disponíveis)
 */

return [
    /**
     * Dashboard — Link único no topo do menu.
     */
    'dashboard' => [
        'text' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'bx bx-home-alt',
        'order' => 0,
    ],

    /**
     * Grupos — Agrupamentos hierárquicos de links.
     * Cada grupo pode ter múltiplos links (entidades).
     *
     * Ordem de exibição: order crescente (10, 20, 30...).
     * O ptah:forge adiciona novos grupos automaticamente.
     */
    'groups' => [
        /**
         * Administração — Módulos do ptah (empresas, perfis, menu).
         * Este grupo é criado automaticamente pelo ptah:install.
         */
        'admin' => [
            'text' => 'Administração',
            'icon' => 'bx bx-cog',
            'order' => 90,
            'links' => [
                [
                    'text' => 'Empresas',
                    'url' => '/ptah-companies',
                    'icon' => 'bx bx-buildings',
                    'order' => 1,
                ],
                [
                    'text' => 'Perfis de Acesso',
                    'url' => '/ptah-roles',
                    'icon' => 'bx bx-shield',
                    'order' => 2,
                ],
                [
                    'text' => 'Controle de Acesso',
                    'url' => '/ptah-users-acl',
                    'icon' => 'bx bx-lock',
                    'order' => 3,
                ],
                [
                    'text' => 'Menu',
                    'url' => '/ptah-menu',
                    'icon' => 'bx bx-menu',
                    'order' => 4,
                ],
            ],
        ],

        /**
         * Novos grupos serão adicionados automaticamente aqui pelo ptah:forge.
         *
         * Exemplo de estrutura após rodar:
         *   php artisan ptah:forge Health/VaccinationType
         *
         * 'health' => [
         *     'text' => 'Saúde',
         *     'icon' => 'bx bx-plus-medical',
         *     'order' => 40,
         *     'links' => [
         *         [
         *             'text' => 'Tipos de Vacina',
         *             'url' => '/vaccination_type',
         *             'icon' => 'bx bx-shield-plus',
         *             'order' => 1,
         *         ],
         *     ],
         * ],
         */
    ],
];

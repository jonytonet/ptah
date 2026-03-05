<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * MenuIconMapper — Mapeia módulos e entidades para ícones e labels PT-BR.
 *
 * Sistema de menu automático: resolve ícones Boxicons e traduções
 * baseado no caminho do módulo (ex: Health/VaccinationType).
 *
 * Uso:
 *   MenuIconMapper::getGroupIcon('Health')          → 'bx bx-plus-medical'
 *   MenuIconMapper::getLinkIcon('VaccinationType')  → 'bx bx-shield-plus'
 *   MenuIconMapper::translateEntity('VaccinationType') → 'Tipos de Vacina'
 *
 * @author Ptah Team
 * @since 1.0.0
 */
class MenuIconMapper
{
    /**
     * Mapeamento de módulos (grupos) para ícones Boxicons.
     * Usado quando ptah:forge recebe caminho como Health/VaccinationType.
     *
     * @var array<string, string>
     */
    private const GROUP_ICONS = [
        'catalog'    => 'bx bx-store',
        'scheduling' => 'bx bx-calendar',
        'clients'    => 'bx bx-group',
        'health'     => 'bx bx-plus-medical',
        'orders'     => 'bx bx-cart',
        'inventory'  => 'bx bx-transfer',
        'financial'  => 'bx bx-dollar-circle',
        'reference'  => 'bx bx-data',
        'settings'   => 'bx bx-cog',
        'reports'    => 'bx bx-bar-chart-alt-2',
        'marketing'  => 'bx bx-bullhorn',
        'crm'        => 'bx bx-user-circle',
    ];

    /**
     * Mapeamento de labels PT-BR para grupos (módulos).
     * Ex: 'catalog' → 'Catálogo'
     *
     * @var array<string, string>
     */
    private const GROUP_LABELS = [
        'catalog'    => 'Catálogo',
        'scheduling' => 'Agendamentos',
        'clients'    => 'Clientes & Pets',
        'health'     => 'Saúde',
        'orders'     => 'Pedidos',
        'inventory'  => 'Estoque',
        'financial'  => 'Financeiro',
        'reference'  => 'Referências',
        'settings'   => 'Configurações',
        'reports'    => 'Relatórios',
        'marketing'  => 'Marketing',
        'crm'        => 'CRM',
    ];

    /**
     * Mapeamento de palavras-chave (case-insensitive) para ícones de links.
     * Ordem importa: patterns mais específicos primeiro.
     * Ex: 'VaccinationType' → busca '*vaccination*' → 'bx bx-injection'
     *
     * @var array<string, string>
     */
    private const LINK_ICON_PATTERNS = [
        // Catálogo
        'category'     => 'bx bx-category',
        'product'      => 'bx bx-package',
        'brand'        => 'bx bx-bookmark',
        'supplier'     => 'bx bx-store-alt',

        // Agendamentos & Serviços
        'appointment'  => 'bx bx-calendar-check',
        'service'      => 'bx bx-briefcase',
        'employee'     => 'bx bx-user-check',
        'schedule'     => 'bx bx-time-five',

        // Clientes
        'client'       => 'bx bx-user',
        'customer'     => 'bx bx-user',
        'contact'      => 'bx bx-phone',
        'address'      => 'bx bx-map',

        // Pets
        'pet'          => 'bx bx-happy-heart',
        'animal'       => 'bx bx-paw',

        // Saúde
        'vaccination'  => 'bx bx-injection',
        'vaccine'      => 'bx bx-shield-plus',
        'medical'      => 'bx bx-file',
        'health'       => 'bx bx-plus-medical',
        'history'      => 'bx bx-history',

        // Referências Veterinárias
        'species'      => 'bx bx-dna',
        'breed'        => 'bx bx-tag',

        // Pedidos
        'order'        => 'bx bx-shopping-bag',
        'item'         => 'bx bx-list-check',
        'cart'         => 'bx bx-cart',

        // Estoque
        'stock'        => 'bx bx-transfer-alt',
        'movement'     => 'bx bx-transfer-alt',
        'inventory'    => 'bx bx-package',

        // Financeiro
        'receivable'   => 'bx bx-money-withdraw',
        'payable'      => 'bx bx-credit-card',
        'payment'      => 'bx bx-dollar',
        'invoice'      => 'bx bx-receipt',

        // Genérico
        'report'       => 'bx bx-bar-chart-alt',
        'dashboard'    => 'bx bx-home-alt',
        'user'         => 'bx bx-user',
        'role'         => 'bx bx-shield',
        'permission'   => 'bx bx-lock',
        'company'      => 'bx bx-buildings',
        'menu'         => 'bx bx-menu',
        'setting'      => 'bx bx-cog',
        'log'          => 'bx bx-list-ul',
    ];

    /**
     * Traduções específicas PT-BR para entidades comuns.
     * Fallback: Str::headline() se não estiver mapeado.
     *
     * @var array<string, string>
     */
    private const ENTITY_TRANSLATIONS = [
        // Catálogo
        'Category'           => 'Categorias',
        'Product'            => 'Produtos',
        'Brand'              => 'Marcas',

        // Referência Veterinária
        'AnimalSpecies'      => 'Espécies Animais',
        'AnimalBreed'        => 'Raças',

        // Serviços
        'ServiceCategory'    => 'Categorias de Serviço',
        'Service'            => 'Serviços',
        'Employee'           => 'Profissionais',

        // Agendamentos
        'Appointment'        => 'Agendamentos',

        // Clientes
        'Client'             => 'Clientes',
        'ClientAddress'      => 'Endereços',
        'ClientContact'      => 'Contatos Alternativos',
        'Pet'                => 'Pets',

        // Saúde
        'VaccinationType'    => 'Tipos de Vacina',
        'PetVaccination'     => 'Vacinações',
        'PetMedicalRecord'   => 'Prontuários',
        'PetServiceHistory'  => 'Histórico de Serviços',

        // Pedidos
        'Order'              => 'Pedidos',
        'OrderItem'          => 'Itens de Pedido',

        // Estoque
        'StockMovement'      => 'Movimentações',

        // Financeiro
        'Receivable'         => 'Contas a Receber',
        'Payable'            => 'Contas a Pagar',

        // Admin (módulos ptah)
        'Company'            => 'Empresas',
        'Role'               => 'Perfis de Acesso',
        'User'               => 'Usuários',
        'Menu'               => 'Menu',
    ];

    /**
     * Retorna o ícone Boxicon para um grupo (módulo).
     * Ex: 'Health' → 'bx bx-plus-medical'
     *
     * @param string $module Nome do módulo (ex: Health, Catalog, Scheduling)
     * @return string Classe CSS do ícone Boxicon
     */
    public static function getGroupIcon(string $module): string
    {
        $key = Str::lower($module);
        return self::GROUP_ICONS[$key] ?? 'bx bx-folder';
    }

    /**
     * Retorna o label PT-BR para um grupo (módulo).
     * Ex: 'health' → 'Saúde'
     *
     * @param string $module Nome do módulo (ex: Health, Catalog)
     * @return string Label traduzido ou fallback (Str::headline)
     */
    public static function getGroupLabel(string $module): string
    {
        $key = Str::lower($module);
        return self::GROUP_LABELS[$key] ?? Str::headline($module);
    }

    /**
     * Retorna o ícone Boxicon para um link (entidade).
     * Usa pattern matching case-insensitive.
     * Ex: 'VaccinationType' → 'bx bx-shield-plus' (match em 'vaccine')
     *
     * @param string $entity Nome da entidade (ex: VaccinationType, Product)
     * @return string Classe CSS do ícone Boxicon
     */
    public static function getLinkIcon(string $entity): string
    {
        $entityLower = Str::lower($entity);

        foreach (self::LINK_ICON_PATTERNS as $pattern => $icon) {
            if (str_contains($entityLower, $pattern)) {
                return $icon;
            }
        }

        // Fallback: ícone genérico
        return 'bx bx-file';
    }

    /**
     * Traduz o nome técnico da entidade para PT-BR.
     * Ex: 'VaccinationType' → 'Tipos de Vacina'
     * Fallback: Str::headline($entity) → 'Vaccination Type'
     *
     * @param string $entity Nome da entidade em PascalCase
     * @return string Label traduzido
     */
    public static function translateEntity(string $entity): string
    {
        return self::ENTITY_TRANSLATIONS[$entity] ?? Str::headline($entity);
    }

    /**
     * Gera ordem automática para links dentro de um grupo.
     * Lê o MenuRegistry.php, conta quantos links já existem no grupo e retorna próximo.
     * Ex: Grupo 'health' tem 2 links → retorna 3
     *
     * @param string $module Nome do módulo (ex: Health)
     * @param string $registryPath Caminho completo para MenuRegistry.php
     * @return int Próxima ordem disponível (1-based)
     */
    public static function getNextLinkOrder(string $module, string $registryPath): int
    {
        if (! file_exists($registryPath)) {
            return 1;
        }

        $registry = require $registryPath;
        $groupKey = Str::lower($module);

        if (! isset($registry['groups'][$groupKey]['links'])) {
            return 1;
        }

        return count($registry['groups'][$groupKey]['links']) + 1;
    }

    /**
     * Gera ordem automática para grupos.
     * Lê MenuRegistry.php, pega maior order existente e adiciona 10.
     * Ex: Último grupo tem order=40 → retorna 50
     *
     * @param string $registryPath Caminho completo para MenuRegistry.php
     * @return int Próxima ordem disponível (múltiplo de 10)
     */
    public static function getNextGroupOrder(string $registryPath): int
    {
        if (! file_exists($registryPath)) {
            return 10;
        }

        $registry = require $registryPath;

        if (empty($registry['groups'])) {
            return 10;
        }

        $maxOrder = 0;
        foreach ($registry['groups'] as $group) {
            if (isset($group['order']) && $group['order'] > $maxOrder) {
                $maxOrder = (int) $group['order'];
            }
        }

        return $maxOrder + 10;
    }
}

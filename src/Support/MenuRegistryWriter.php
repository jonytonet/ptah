<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * MenuRegistryWriter — Manipula o arquivo MenuRegistry.php preservando formatação.
 *
 * Adiciona entradas de menu automaticamente quando ptah:forge é executado.
 * Mantém estrutura hierárquica (dashboard → groups → links) e evita duplicatas.
 *
 * Uso:
 *   MenuRegistryWriter::addEntry(
 *       module: 'Health',
 *       entity: 'VaccinationType',
 *       url: '/vaccination_type',
 *       registryPath: database_path('seeders/MenuRegistry.php')
 *   );
 *
 * @author Ptah Team
 * @since 1.0.0
 */
class MenuRegistryWriter
{
    public function __construct(
        private Filesystem $files
    ) {}

    /**
     * Adiciona uma entrada de menu ao MenuRegistry.php.
     * Cria o grupo (módulo) se não existir, adiciona o link com ordem automática.
     *
     * @param string $module Nome do módulo (ex: Health, Catalog)
     * @param string $entity Nome da entidade (ex: VaccinationType)
     * @param string $url URL da entidade (ex: /vaccination_type)
     * @param string $registryPath Caminho completo para MenuRegistry.php
     * @param string|null $icon Ícone customizado (opcional, senão usa mapper)
     * @param string|null $label Label customizado (opcional, senão usa mapper)
     * @return bool True se adicionado, false se já existia
     * @throws \RuntimeException Se o arquivo não existir ou for inválido
     */
    /**
     * Adds a flat (ungrouped) link directly at the root level of MenuRegistry.php.
     * Use this when the entity has no module prefix.
     *
     * @param string $entity  Entity name (e.g. Category)
     * @param string $url     URL (e.g. /category)
     * @param string $registryPath
     * @param string|null $icon
     * @param string|null $label
     * @return bool True if added, false if already exists
     */
    public function addFlatEntry(
        string $entity,
        string $url,
        string $registryPath,
        ?string $icon = null,
        ?string $label = null
    ): bool {
        if (! $this->files->exists($registryPath)) {
            throw new \RuntimeException("MenuRegistry not found: {$registryPath}");
        }

        $registry = require $registryPath;

        if (! is_array($registry)) {
            throw new \RuntimeException('MenuRegistry.php must return an array');
        }

        $urlNormalized = Str::start($url, '/');

        // Duplicate check in flat links
        foreach ($registry['links'] ?? [] as $link) {
            if (($link['url'] ?? '') === $urlNormalized) {
                return false;
            }
        }

        $linkIcon  = $icon  ?? MenuIconMapper::getLinkIcon($entity);
        $linkLabel = $label ?? MenuIconMapper::translateEntity($entity);
        $linkOrder = count($registry['links'] ?? []) + 1;

        $registry['links'][] = [
            'text'  => $linkLabel,
            'url'   => $urlNormalized,
            'icon'  => $linkIcon,
            'order' => $linkOrder,
        ];

        $this->writeRegistry($registryPath, $registry);

        return true;
    }

    public function addEntry(
        string $module,
        string $entity,
        string $url,
        string $registryPath,
        ?string $icon = null,
        ?string $label = null
    ): bool {
        if (! $this->files->exists($registryPath)) {
            throw new \RuntimeException("MenuRegistry not found: {$registryPath}");
        }

        // Carregar registry atual
        $registry = require $registryPath;

        if (! is_array($registry)) {
            throw new \RuntimeException('MenuRegistry.php must return an array');
        }

        // Normalizar chaves
        $groupKey = Str::lower($module);
        $urlNormalized = Str::start($url, '/');

        // Verificar duplicata
        if ($this->entryExists($registry, $groupKey, $urlNormalized)) {
            return false;
        }

        // Resolver ícone e label via mapper
        $linkIcon = $icon ?? MenuIconMapper::getLinkIcon($entity);
        $linkLabel = $label ?? MenuIconMapper::translateEntity($entity);

        // Criar grupo se não existir
        if (! isset($registry['groups'][$groupKey])) {
            $registry = $this->createGroup($registry, $groupKey, $registryPath);
        }

        // Adicionar link ao grupo
        $registry = $this->addLink($registry, $groupKey, $linkLabel, $urlNormalized, $linkIcon, $registryPath);

        // Persistir mudanças
        $this->writeRegistry($registryPath, $registry);

        return true;
    }

    /**
     * Verifica se a entrada já existe no registry.
     *
     * @param array $registry Array do registry
     * @param string $groupKey Chave do grupo (ex: 'health')
     * @param string $url URL do link (ex: '/vaccination_type')
     * @return bool True se já existe
     */
    private function entryExists(array $registry, string $groupKey, string $url): bool
    {
        // Check flat links first
        foreach ($registry['links'] ?? [] as $link) {
            if (($link['url'] ?? '') === $url) {
                return true;
            }
        }

        if (! isset($registry['groups'][$groupKey]['links'])) {
            return false;
        }

        foreach ($registry['groups'][$groupKey]['links'] as $link) {
            if (($link['url'] ?? '') === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cria um novo grupo no registry.
     *
     * @param array $registry Array do registry
     * @param string $groupKey Chave do grupo (ex: 'health')
     * @param string $registryPath Caminho do registry (para calcular ordem)
     * @return array Registry atualizado
     */
    private function createGroup(array $registry, string $groupKey, string $registryPath): array
    {
        $groupIcon = MenuIconMapper::getGroupIcon($groupKey);
        $groupLabel = MenuIconMapper::getGroupLabel($groupKey);
        $groupOrder = MenuIconMapper::getNextGroupOrder($registryPath);

        $registry['groups'][$groupKey] = [
            'text' => $groupLabel,
            'icon' => $groupIcon,
            'order' => $groupOrder,
            'links' => [],
        ];

        return $registry;
    }

    /**
     * Adiciona um link ao grupo existente.
     *
     * @param array $registry Array do registry
     * @param string $groupKey Chave do grupo
     * @param string $label Label do link
     * @param string $url URL do link
     * @param string $icon Ícone do link
     * @param string $registryPath Caminho do registry (para calcular ordem)
     * @return array Registry atualizado
     */
    private function addLink(
        array $registry,
        string $groupKey,
        string $label,
        string $url,
        string $icon,
        string $registryPath
    ): array {
        $linkOrder = MenuIconMapper::getNextLinkOrder($groupKey, $registryPath);

        $registry['groups'][$groupKey]['links'][] = [
            'text' => $label,
            'url' => $url,
            'icon' => $icon,
            'order' => $linkOrder,
        ];

        return $registry;
    }

    /**
     * Escreve o registry atualizado no arquivo PHP.
     * Mantém formatação legível com indentação de 4 espaços.
     *
     * @param string $registryPath Caminho do arquivo
     * @param array $registry Array do registry
     * @return void
     */
    private function writeRegistry(string $registryPath, array $registry): void
    {
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * MenuRegistry — Auto-generated menu structure.\n";
        $content .= " * \n";
        $content .= " * Este arquivo é atualizado automaticamente pelo comando ptah:forge.\n";
        $content .= " * Você pode editá-lo manualmente para customizar ícones, labels ou ordem.\n";
        $content .= " * \n";
        $content .= " * Sincronize com o banco usando: php artisan ptah:menu-sync --fresh\n";
        $content .= " * \n";
        $content .= " * @generated " . now()->toDateTimeString() . "\n";
        $content .= " */\n\n";
        $content .= "return [\n";

        // Dashboard
        if (isset($registry['dashboard'])) {
            $content .= "    'dashboard' => [\n";
            $content .= "        'text' => " . $this->exportValue($registry['dashboard']['text']) . ",\n";
            $content .= "        'url' => " . $this->exportValue($registry['dashboard']['url']) . ",\n";
            $content .= "        'icon' => " . $this->exportValue($registry['dashboard']['icon']) . ",\n";
            $content .= "        'order' => " . $registry['dashboard']['order'] . ",\n";
            $content .= "    ],\n\n";
        }

        // Flat root links (no group)
        if (! empty($registry['links'])) {
            $content .= "    'links' => [\n";
            $flatLinks = $registry['links'];
            usort($flatLinks, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
            foreach ($flatLinks as $link) {
                $content .= "        [\n";
                $content .= "            'text' => " . $this->exportValue($link['text']) . ",\n";
                $content .= "            'url' => " . $this->exportValue($link['url']) . ",\n";
                $content .= "            'icon' => " . $this->exportValue($link['icon']) . ",\n";
                $content .= "            'order' => " . $link['order'] . ",\n";
                $content .= "        ],\n";
            }
            $content .= "    ],\n\n";
        }

        // Groups
        $content .= "    'groups' => [\n";

        // Ordenar grupos por 'order'
        $groups = $registry['groups'] ?? [];
        uasort($groups, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        foreach ($groups as $groupKey => $group) {
            $content .= "        '{$groupKey}' => [\n";
            $content .= "            'text' => " . $this->exportValue($group['text']) . ",\n";
            $content .= "            'icon' => " . $this->exportValue($group['icon']) . ",\n";
            $content .= "            'order' => " . $group['order'] . ",\n";
            $content .= "            'links' => [\n";

            // Ordenar links por 'order'
            $links = $group['links'] ?? [];
            usort($links, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

            foreach ($links as $link) {
                $content .= "                [\n";
                $content .= "                    'text' => " . $this->exportValue($link['text']) . ",\n";
                $content .= "                    'url' => " . $this->exportValue($link['url']) . ",\n";
                $content .= "                    'icon' => " . $this->exportValue($link['icon']) . ",\n";
                $content .= "                    'order' => " . $link['order'] . ",\n";
                $content .= "                ],\n";
            }

            $content .= "            ],\n";
            $content .= "        ],\n";
        }

        $content .= "    ],\n";
        $content .= "];\n";

        $this->files->put($registryPath, $content);
    }

    /**
     * Exporta um valor para string PHP formatada.
     * Ex: 'Dashboard' → "'Dashboard'"
     *
     * @param mixed $value Valor a exportar
     * @return string Representação PHP
     */
    private function exportValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Remove uma entrada do registry (útil para rollback/testes).
     *
     * @param string $url URL do link a remover
     * @param string $registryPath Caminho do registry
     * @return bool True se removido, false se não encontrado
     */
    public function removeEntry(string $url, string $registryPath): bool
    {
        if (! $this->files->exists($registryPath)) {
            return false;
        }

        $registry = require $registryPath;
        $urlNormalized = Str::start($url, '/');
        $found = false;

        // Remove from flat links
        $filteredFlat = array_filter($registry['links'] ?? [], function ($link) use ($urlNormalized, &$found) {
            if (($link['url'] ?? '') === $urlNormalized) {
                $found = true;
                return false;
            }
            return true;
        });
        $registry['links'] = array_values($filteredFlat);

        foreach ($registry['groups'] ?? [] as $groupKey => &$group) {
            $links = $group['links'] ?? [];
            $filteredLinks = array_filter($links, function($link) use ($urlNormalized, &$found) {
                if (($link['url'] ?? '') === $urlNormalized) {
                    $found = true;
                    return false;
                }
                return true;
            });

            $group['links'] = array_values($filteredLinks);

            // Remover grupo se ficou vazio
            if (empty($group['links'])) {
                unset($registry['groups'][$groupKey]);
            }
        }

        if ($found) {
            $this->writeRegistry($registryPath, $registry);
        }

        return $found;
    }
}

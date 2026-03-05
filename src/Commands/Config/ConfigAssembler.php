<?php

namespace Ptah\Commands\Config;

class ConfigAssembler
{
    /**
     * Assemble complete configuration from all parts
     */
    public function assemble(
        array $columns,
        array $actions,
        array $filters,
        array $styles,
        array $joins,
        array $general,
        array $permissions
    ): array {
        $config = array_merge(
            $this->getDefaults(),
            $general,
            $permissions,
            [
                'formEditFields' => array_merge($columns, $actions),
                'customFilters' => $filters,
                'conditionStyles' => $styles,
                'joins' => $joins,
            ]
        );

        return $config;
    }

    /**
     * Get default configuration values
     */
    protected function getDefaults(): array
    {
        return [
            'displayName' => '',
            'configLinkLinha' => '',
            'tableClass' => '',
            'theadClass' => '',
            'uiCompactMode' => false,
            'uiStickyHeader' => true,
            'showTotalizador' => true,
            'cacheEnabled' => false,
            'cacheTtl' => 300,
            'exportAsyncThreshold' => 1000,
            'exportMaxRows' => 10000,
            'exportOrientation' => 'landscape',
            'broadcastEnabled' => false,
            'broadcastChannel' => '',
            'broadcastEvent' => '',
            'groupBy' => '',
            'theme' => 'light',
            'permissionCreate' => '',
            'permissionEdit' => '',
            'permissionDelete' => '',
            'permissionExport' => '',
            'permissionRestore' => '',
            'permissionIdentifier' => '',
            'showCreateButton' => true,
            'showEditButton' => true,
            'showDeleteButton' => true,
            'showTrashButton' => true,
        ];
    }

    /**
     * Merge with existing configuration
     */
    public function mergeWithExisting(array $newConfig, array $existingConfig): array
    {
        return array_merge($existingConfig, $newConfig);
    }
}

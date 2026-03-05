<?php

namespace Ptah\Commands\Config\Validators;

use Ptah\Enums\CrudConfigEnums;

class ConfigValidator
{
    /**
     * Validate column configuration
     */
    public function validateColumn(array $config): void
    {
        // 1. colsNomeFisico is required
        if (empty($config['colsNomeFisico'])) {
            throw new \InvalidArgumentException('colsNomeFisico is required');
        }

        // 2. Valid column type
        if (!in_array($config['colsTipo'], CrudConfigEnums::COLUMN_TYPES)) {
            throw new \InvalidArgumentException("Invalid colsTipo: {$config['colsTipo']}. Valid types: " . implode(', ', CrudConfigEnums::COLUMN_TYPES));
        }

        // 3. If select, needs options
        if ($config['colsTipo'] === 'select' && empty($config['colsSelect'])) {
            throw new \InvalidArgumentException('Select type requires colsSelect (options)');
        }

        // 4. If searchdropdown, needs SD config
        if ($config['colsTipo'] === 'searchdropdown') {
            if (empty($config['colsSDModel']) && empty($config['colsSDService'])) {
                throw new \InvalidArgumentException('SearchDropdown requires colsSDModel or colsSDService');
            }
        }

        // 5. If renderer badge/pill, needs badges
        if (in_array($config['colsRenderer'] ?? '', ['badge', 'pill'])) {
            if (empty($config['colsRendererBadges'])) {
                throw new \InvalidArgumentException('Badge/pill renderer requires colsRendererBadges configuration');
            }
        }

        // 6. Validate renderer if present
        if (!empty($config['colsRenderer']) && !in_array($config['colsRenderer'], CrudConfigEnums::RENDERERS)) {
            throw new \InvalidArgumentException("Invalid renderer: {$config['colsRenderer']}. Valid renderers: " . implode(', ', CrudConfigEnums::RENDERERS));
        }

        // 7. Validate mask if present
        if (!empty($config['colsMask']) && !in_array($config['colsMask'], CrudConfigEnums::MASKS)) {
            throw new \InvalidArgumentException("Invalid mask: {$config['colsMask']}. Valid masks: " . implode(', ', CrudConfigEnums::MASKS));
        }
    }

    /**
     * Validate JOIN configuration
     */
    public function validateJoin(array $config, array $existingJoins = []): void
    {
        // 1. Required fields
        $required = ['type', 'table', 'first', 'second'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \InvalidArgumentException("JOIN: field '{$field}' is required");
            }
        }

        // 2. Valid type
        if (!in_array($config['type'], CrudConfigEnums::JOIN_TYPES)) {
            throw new \InvalidArgumentException("Invalid JOIN type: {$config['type']}. Valid types: " . implode(', ', CrudConfigEnums::JOIN_TYPES));
        }

        // 3. Check for duplicate table
        foreach ($existingJoins as $join) {
            if ($join['table'] === $config['table']) {
                throw new \InvalidArgumentException("Table '{$config['table']}' is already used in another JOIN");
            }
        }
    }

    /**
     * Validate action configuration
     */
    public function validateAction(array $config): void
    {
        if (empty($config['colsNomeLogico'])) {
            throw new \InvalidArgumentException('Action name (colsNomeLogico) is required');
        }

        if (empty($config['actionType'])) {
            throw new \InvalidArgumentException('Action type is required');
        }

        if (!in_array($config['actionType'], CrudConfigEnums::ACTION_TYPES)) {
            throw new \InvalidArgumentException("Invalid action type: {$config['actionType']}. Valid types: " . implode(', ', CrudConfigEnums::ACTION_TYPES));
        }

        if (empty($config['actionValue'])) {
            throw new \InvalidArgumentException('Action value is required');
        }

        if (!empty($config['actionColor']) && !in_array($config['actionColor'], CrudConfigEnums::ACTION_COLORS)) {
            throw new \InvalidArgumentException("Invalid action color: {$config['actionColor']}. Valid colors: " . implode(', ', CrudConfigEnums::ACTION_COLORS));
        }
    }

    /**
     * Validate filter configuration
     */
    public function validateFilter(array $config): void
    {
        if (empty($config['field'])) {
            throw new \InvalidArgumentException('Filter field is required');
        }

        if (!empty($config['colsFilterType']) && !in_array($config['colsFilterType'], CrudConfigEnums::FILTER_TYPES)) {
            throw new \InvalidArgumentException("Invalid filter type: {$config['colsFilterType']}. Valid types: " . implode(', ', CrudConfigEnums::FILTER_TYPES));
        }

        if (!empty($config['defaultOperator']) && !in_array($config['defaultOperator'], CrudConfigEnums::OPERATORS)) {
            throw new \InvalidArgumentException("Invalid operator: {$config['defaultOperator']}. Valid operators: " . implode(', ', CrudConfigEnums::OPERATORS));
        }

        if (!empty($config['aggregate']) && !in_array($config['aggregate'], CrudConfigEnums::AGGREGATES)) {
            throw new \InvalidArgumentException("Invalid aggregate: {$config['aggregate']}. Valid aggregates: " . implode(', ', CrudConfigEnums::AGGREGATES));
        }
    }

    /**
     * Validate style configuration
     */
    public function validateStyle(array $config): void
    {
        $required = ['field', 'condition', 'value', 'style'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new \InvalidArgumentException("Style: field '{$field}' is required");
            }
        }

        if (!in_array($config['condition'], CrudConfigEnums::OPERATORS)) {
            throw new \InvalidArgumentException("Invalid style condition: {$config['condition']}. Valid operators: " . implode(', ', CrudConfigEnums::OPERATORS));
        }
    }

    /**
     * Validate complete configuration structure
     */
    public function validateStructure(array $config): void
    {
        $required = ['formEditFields'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new \InvalidArgumentException("Configuration must have '{$field}' key");
            }
        }

        if (!is_array($config['formEditFields'])) {
            throw new \InvalidArgumentException("'formEditFields' must be an array");
        }
    }
}

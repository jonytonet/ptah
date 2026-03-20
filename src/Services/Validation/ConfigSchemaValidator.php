<?php

declare(strict_types=1);

namespace Ptah\Services\Validation;

use Ptah\Exceptions\ConfigValidationException;
use Ptah\Enums\CrudConfigEnums;
use Illuminate\Support\Facades\Schema;

/**
 * Validator for CRUD configuration arrays.
 *
 * Validates BaseCrud JSON configurations before they are persisted to the database.
 * Ensures that all required fields are present, types are correct, and dependencies are met.
 *
 * @package Ptah\Services\Validation
 */
class ConfigSchemaValidator
{
    /**
     * Validate a complete CRUD configuration.
     *
     * @param array<string, mixed> $config
     * @param string $model
     * @return void
     * @throws ConfigValidationException
     */
    public function validate(array $config, string $model): void
    {
        // Validate columns section
        if (isset($config['cols']) && is_array($config['cols'])) {
            $this->validateColumns($config['cols'], $model);
        }

        // Validate actions section
        if (isset($config['actions']) && is_array($config['actions'])) {
            $this->validateActions($config['actions']);
        }

        // Validate filters section
        if (isset($config['filters']) && is_array($config['filters'])) {
            $this->validateFilters($config['filters']);
        }

        // Validate styles section
        if (isset($config['styles']) && is_array($config['styles'])) {
            $this->validateStyles($config['styles']);
        }

        // Validate joins section
        if (isset($config['joins']) && is_array($config['joins'])) {
            $this->validateJoins($config['joins']);
        }

        // Validate general settings
        if (isset($config['general']) && is_array($config['general'])) {
            $this->validateGeneralSettings($config['general']);
        }
    }

    /**
     * Validate columns configuration.
     *
     * @param array<int, array<string, mixed>> $columns
     * @param string $model
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateColumns(array $columns, string $model): void
    {
        foreach ($columns as $index => $column) {
            $this->validateColumn($column, $index, $model);
        }
    }

    /**
     * Validate a single column configuration.
     *
     * @param array<string, mixed> $column
     * @param int $index
     * @param string $model
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateColumn(array $column, int $index, string $model): void
    {
        // Validate required field: colsNomeFisico
        if (empty($column['colsNomeFisico'])) {
            throw ConfigValidationException::missingRequiredField('colsNomeFisico', 'cols')
                ->withJsonPath("$.cols[{$index}].colsNomeFisico")
                ->withModel($model);
        }

        // Validate colsTipo if present
        if (isset($column['colsTipo'])) {
            if (!in_array($column['colsTipo'], CrudConfigEnums::COLUMN_TYPES, true)) {
                throw ConfigValidationException::invalidColumnType(
                    $column['colsNomeFisico'],
                    $column['colsTipo'],
                    CrudConfigEnums::COLUMN_TYPES,
                    'cols'
                )->withJsonPath("$.cols[{$index}].colsTipo")
                    ->withModel($model);
            }

            // Validate type-specific requirements
            $this->validateColumnTypeRequirements($column, $index, $model);
        }

        // Validate renderer if present
        if (isset($column['colsRenderer'])) {
            $this->validateRenderer($column, $index, $model);
        }

        // Validate mask if present
        if (isset($column['colsMask'])) {
            if (!is_string($column['colsMask'])) {
                throw ConfigValidationException::invalidType(
                    'colsMask',
                    $column['colsMask'],
                    'string',
                    'cols'
                )->withJsonPath("$.cols[{$index}].colsMask")
                    ->withModel($model)
                    ->withSuggestion('Use a single mask value like "money" or "cpf", not an array');
            }
        }
    }

    /**
     * Validate column type-specific requirements.
     *
     * @param array<string, mixed> $column
     * @param int $index
     * @param string $model
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateColumnTypeRequirements(array $column, int $index, string $model): void
    {
        $type = $column['colsTipo'];
        $field = $column['colsNomeFisico'];

        // Select type requires colsSelect
        if ($type === 'select' && empty($column['colsSelect'])) {
            throw ConfigValidationException::missingDependency(
                $field,
                'colsSelect',
                'cols'
            )->withJsonPath("$.cols[{$index}].colsSelect")
                ->withModel($model);
        }

        // SearchDropdown requires colsSDModel or colsSDService
        if ($type === 'searchdropdown') {
            if (empty($column['colsSDModel']) && empty($column['colsSDService'])) {
                throw ConfigValidationException::missingDependency(
                    $field,
                    'colsSDModel or colsSDService',
                    'cols'
                )->withJsonPath("$.cols[{$index}]")
                    ->withModel($model);
            }
        }

        // Relation type requires colsRelation
        if ($type === 'relation' && empty($column['colsRelation'])) {
            throw ConfigValidationException::missingDependency(
                $field,
                'colsRelation',
                'cols'
            )->withJsonPath("$.cols[{$index}].colsRelation")
                ->withModel($model);
        }
    }

    /**
     * Validate renderer configuration.
     *
     * @param array<string, mixed> $column
     * @param int $index
     * @param string $model
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateRenderer(array $column, int $index, string $model): void
    {
        $renderer = $column['colsRenderer'];

        if (!in_array($renderer, CrudConfigEnums::RENDERERS, true)) {
            throw ConfigValidationException::invalidColumnType(
                $column['colsNomeFisico'],
                $renderer,
                CrudConfigEnums::RENDERERS,
                'cols'
            )->withJsonPath("$.cols[{$index}].colsRenderer")
                ->withModel($model);
        }

        // Badge/pill requires colsRendererBadges
        if (in_array($renderer, ['badge', 'pill'], true) && empty($column['colsRendererBadges'])) {
            throw ConfigValidationException::invalidRendererConfig(
                $renderer,
                'colsRendererBadges'
            )->withJsonPath("$.cols[{$index}].colsRendererBadges")
                ->withModel($model);
        }
    }

    /**
     * Validate actions configuration.
     *
     * @param array<int, array<string, mixed>> $actions
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateActions(array $actions): void
    {
        foreach ($actions as $index => $action) {
            // Validate required field: colsNomeLogico
            if (empty($action['colsNomeLogico'])) {
                throw ConfigValidationException::missingRequiredField('colsNomeLogico', 'actions')
                    ->withJsonPath("$.actions[{$index}].colsNomeLogico");
            }

            // Validate action type
            if (isset($action['actionType'])) {
                $validTypes = ['wire', 'route', 'url', 'modal'];
                if (!in_array($action['actionType'], $validTypes, true)) {
                    throw ConfigValidationException::invalidColumnType(
                        $action['colsNomeLogico'],
                        $action['actionType'],
                        $validTypes,
                        'actions'
                    )->withJsonPath("$.actions[{$index}].actionType");
                }
            }
        }
    }

    /**
     * Validate filters configuration.
     *
     * @param array<int, array<string, mixed>> $filters
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateFilters(array $filters): void
    {
        foreach ($filters as $index => $filter) {
            // Validate required field: colsNomeFisico
            if (empty($filter['colsNomeFisico'])) {
                throw ConfigValidationException::missingRequiredField('colsNomeFisico', 'filters')
                    ->withJsonPath("$.filters[{$index}].colsNomeFisico");
            }

            // Validate filter type
            if (isset($filter['colsTipo'])) {
                $validTypes = ['boolean', 'select', 'numeric', 'date', 'text'];
                if (!in_array($filter['colsTipo'], $validTypes, true)) {
                    throw ConfigValidationException::invalidColumnType(
                        $filter['colsNomeFisico'],
                        $filter['colsTipo'],
                        $validTypes,
                        'filters'
                    )->withJsonPath("$.filters[{$index}].colsTipo");
                }
            }

            // Validate operator
            if (isset($filter['colsOperator'])) {
                $validOperators = ['eq', 'ne', 'lt', 'gt', 'lte', 'gte', 'like', 'in'];
                if (!in_array($filter['colsOperator'], $validOperators, true)) {
                    throw ConfigValidationException::invalidColumnType(
                        $filter['colsNomeFisico'],
                        $filter['colsOperator'],
                        $validOperators,
                        'filters'
                    )->withJsonPath("$.filters[{$index}].colsOperator");
                }
            }
        }
    }

    /**
     * Validate styles configuration.
     *
     * @param array<int, array<string, mixed>> $styles
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateStyles(array $styles): void
    {
        foreach ($styles as $index => $style) {
            // Validate required fields
            if (empty($style['colsNomeFisico'])) {
                throw ConfigValidationException::missingRequiredField('colsNomeFisico', 'styles')
                    ->withJsonPath("$.styles[{$index}].colsNomeFisico");
            }

            if (empty($style['colsOperator'])) {
                throw ConfigValidationException::missingRequiredField('colsOperator', 'styles')
                    ->withJsonPath("$.styles[{$index}].colsOperator");
            }

            if (!isset($style['colsValue'])) {
                throw ConfigValidationException::missingRequiredField('colsValue', 'styles')
                    ->withJsonPath("$.styles[{$index}].colsValue");
            }

            if (empty($style['colsCss'])) {
                throw ConfigValidationException::missingRequiredField('colsCss', 'styles')
                    ->withJsonPath("$.styles[{$index}].colsCss");
            }

            // Validate operator
            $validOperators = ['eq', 'ne', 'lt', 'gt', 'lte', 'gte'];
            if (!in_array($style['colsOperator'], $validOperators, true)) {
                throw ConfigValidationException::invalidColumnType(
                    $style['colsNomeFisico'],
                    $style['colsOperator'],
                    $validOperators,
                    'styles'
                )->withJsonPath("$.styles[{$index}].colsOperator");
            }
        }
    }

    /**
     * Validate joins configuration.
     *
     * @param array<int, array<string, mixed>> $joins
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateJoins(array $joins): void
    {
        $usedTables = [];

        foreach ($joins as $index => $join) {
            // Validate required fields
            if (empty($join['colsTipo'])) {
                throw ConfigValidationException::missingRequiredField('colsTipo', 'joins')
                    ->withJsonPath("$.joins[{$index}].colsTipo");
            }

            if (empty($join['colsTable'])) {
                throw ConfigValidationException::missingRequiredField('colsTable', 'joins')
                    ->withJsonPath("$.joins[{$index}].colsTable");
            }

            if (empty($join['colsOn'])) {
                throw ConfigValidationException::missingRequiredField('colsOn', 'joins')
                    ->withJsonPath("$.joins[{$index}].colsOn");
            }

            // Validate JOIN type
            $validTypes = ['inner', 'left', 'right'];
            if (!in_array($join['colsTipo'], $validTypes, true)) {
                throw ConfigValidationException::invalidColumnType(
                    'JOIN',
                    $join['colsTipo'],
                    $validTypes,
                    'joins'
                )->withJsonPath("$.joins[{$index}].colsTipo");
            }

            // Check for duplicate tables
            $table = $join['colsTable'];
            if (in_array($table, $usedTables, true)) {
                throw ConfigValidationException::duplicateConfiguration(
                    'table',
                    $table,
                    'joins'
                )->withJsonPath("$.joins[{$index}].colsTable")
                    ->withSuggestion("Table '{$table}' is already used in another JOIN");
            }

            $usedTables[] = $table;

            // Validate that table exists
            if (!Schema::hasTable($table)) {
                throw ConfigValidationException::invalidJoin(
                    $table,
                    "Table does not exist in database"
                )->withJsonPath("$.joins[{$index}].colsTable");
            }
        }
    }

    /**
     * Validate general settings.
     *
     * @param array<string, mixed> $settings
     * @return void
     * @throws ConfigValidationException
     */
    protected function validateGeneralSettings(array $settings): void
    {
        // Validate itemsPerPage
        if (isset($settings['itemsPerPage'])) {
            if (!is_int($settings['itemsPerPage']) || $settings['itemsPerPage'] < 1) {
                throw ConfigValidationException::invalidType(
                    'itemsPerPage',
                    $settings['itemsPerPage'],
                    'positive integer',
                    'general'
                )->withSuggestion('Use a value between 5 and 100');
            }
        }

        // Validate cacheTime
        if (isset($settings['cacheTime'])) {
            if (!is_int($settings['cacheTime']) || $settings['cacheTime'] < 0) {
                throw ConfigValidationException::invalidType(
                    'cacheTime',
                    $settings['cacheTime'],
                    'non-negative integer',
                    'general'
                )->withSuggestion('Use seconds (e.g., 300 for 5 minutes)');
            }
        }

        // Validate boolean settings
        $booleanSettings = ['cacheEnabled', 'paginationEnabled', 'exportEnabled', 'broadcastEnabled'];
        foreach ($booleanSettings as $setting) {
            if (isset($settings[$setting]) && !is_bool($settings[$setting])) {
                throw ConfigValidationException::invalidType(
                    $setting,
                    $settings[$setting],
                    'boolean',
                    'general'
                );
            }
        }
    }
}

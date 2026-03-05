<?php

declare(strict_types=1);

namespace Ptah\Services\Validation;

use Ptah\Enums\CrudConfigEnums;

/**
 * Builder for JSON Schema definitions.
 *
 * Generates JSON Schema documents for validating CRUD configurations.
 * This can be used for client-side validation or documentation generation.
 *
 * @package Ptah\Services\Validation
 */
class JsonSchemaBuilder
{
    /**
     * Build a complete JSON Schema for CRUD configuration.
     *
     * @return array<string, mixed>
     */
    public function buildCrudConfigSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'CRUD Configuration',
            'description' => 'Schema for BaseCrud configuration',
            'type' => 'object',
            'properties' => [
                'cols' => $this->buildColumnsSchema(),
                'actions' => $this->buildActionsSchema(),
                'filters' => $this->buildFiltersSchema(),
                'styles' => $this->buildStylesSchema(),
                'joins' => $this->buildJoinsSchema(),
                'general' => $this->buildGeneralSchema(),
                'permissions' => $this->buildPermissionsSchema(),
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * Build schema for columns section.
     *
     * @return array<string, mixed>
     */
    protected function buildColumnsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['colsNomeFisico'],
                'properties' => [
                    'colsNomeFisico' => [
                        'type' => 'string',
                        'description' => 'Physical column name in database',
                    ],
                    'colsNomeLogico' => [
                        'type' => 'string',
                        'description' => 'Display label for the column',
                    ],
                    'colsTipo' => [
                        'type' => 'string',
                        'enum' => CrudConfigEnums::COLUMN_TYPES,
                        'description' => 'Column display type',
                    ],
                    'colsMask' => [
                        'type' => 'string',
                        'description' => 'Input mask (e.g., money, cpf, date)',
                    ],
                    'colsRenderer' => [
                        'type' => 'string',
                        'enum' => ['badge', 'pill', 'icon', 'html', 'custom'],
                        'description' => 'Special renderer for the column',
                    ],
                    'colsRendererBadges' => [
                        'type' => 'array',
                        'description' => 'Badge/pill configurations',
                    ],
                    'colsSortable' => [
                        'type' => 'boolean',
                        'description' => 'Whether column is sortable',
                    ],
                    'colsSearchable' => [
                        'type' => 'boolean',
                        'description' => 'Whether column is searchable',
                    ],
                    'colsRequired' => [
                        'type' => 'boolean',
                        'description' => 'Whether field is required in forms',
                    ],
                    'colsRelation' => [
                        'type' => 'string',
                        'description' => 'Eloquent relation name',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build schema for actions section.
     *
     * @return array<string, mixed>
     */
    protected function buildActionsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['colsNomeLogico', 'colsTipo'],
                'properties' => [
                    'colsNomeLogico' => [
                        'type' => 'string',
                        'description' => 'Action name/identifier',
                    ],
                    'colsTipo' => [
                        'type' => 'string',
                        'enum' => ['wire', 'route', 'url', 'modal'],
                        'description' => 'Action type',
                    ],
                    'colsValue' => [
                        'type' => 'string',
                        'description' => 'Method name, route name, or URL',
                    ],
                    'colsIcon' => [
                        'type' => 'string',
                        'description' => 'Icon class (e.g., bx bx-edit)',
                    ],
                    'colsColor' => [
                        'type' => 'string',
                        'enum' => ['primary', 'secondary', 'success', 'danger', 'warning', 'info'],
                        'description' => 'Button color',
                    ],
                    'colsConfirmMessage' => [
                        'type' => 'string',
                        'description' => 'Confirmation message before action',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build schema for filters section.
     *
     * @return array<string, mixed>
     */
    protected function buildFiltersSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['colsNomeFisico', 'colsTipo', 'colsOperator'],
                'properties' => [
                    'colsNomeFisico' => [
                        'type' => 'string',
                        'description' => 'Database column to filter',
                    ],
                    'colsNomeLogico' => [
                        'type' => 'string',
                        'description' => 'Filter label',
                    ],
                    'colsTipo' => [
                        'type' => 'string',
                        'enum' => ['boolean', 'select', 'numeric', 'date', 'text'],
                        'description' => 'Filter input type',
                    ],
                    'colsOperator' => [
                        'type' => 'string',
                        'enum' => ['eq', 'ne', 'lt', 'gt', 'lte', 'gte', 'like', 'in'],
                        'description' => 'Comparison operator',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build schema for styles section.
     *
     * @return array<string, mixed>
     */
    protected function buildStylesSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['colsNomeFisico', 'colsOperator', 'colsValue', 'colsCss'],
                'properties' => [
                    'colsNomeFisico' => [
                        'type' => 'string',
                        'description' => 'Column to apply conditional style',
                    ],
                    'colsOperator' => [
                        'type' => 'string',
                        'enum' => ['eq', 'ne', 'lt', 'gt', 'lte', 'gte'],
                        'description' => 'Comparison operator',
                    ],
                    'colsValue' => [
                        'description' => 'Value to compare against',
                    ],
                    'colsCss' => [
                        'type' => 'string',
                        'description' => 'CSS classes to apply',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build schema for joins section.
     *
     * @return array<string, mixed>
     */
    protected function buildJoinsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['colsTipo', 'colsTable', 'colsOn'],
                'properties' => [
                    'colsTipo' => [
                        'type' => 'string',
                        'enum' => ['inner', 'left', 'right'],
                        'description' => 'JOIN type',
                    ],
                    'colsTable' => [
                        'type' => 'string',
                        'description' => 'Table to join',
                    ],
                    'colsOn' => [
                        'type' => 'string',
                        'description' => 'JOIN condition (e.g., table1.id=table2.foreign_id)',
                    ],
                    'colsSelect' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Columns to select from joined table',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build schema for general settings.
     *
     * @return array<string, mixed>
     */
    protected function buildGeneralSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'itemsPerPage' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'Number of items per page',
                ],
                'cacheEnabled' => [
                    'type' => 'boolean',
                    'description' => 'Enable query caching',
                ],
                'cacheTime' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Cache duration in seconds',
                ],
                'paginationEnabled' => [
                    'type' => 'boolean',
                    'description' => 'Enable pagination',
                ],
                'exportEnabled' => [
                    'type' => 'boolean',
                    'description' => 'Enable export functionality',
                ],
                'broadcastEnabled' => [
                    'type' => 'boolean',
                    'description' => 'Enable real-time broadcasting',
                ],
            ],
        ];
    }

    /**
     * Build schema for permissions section.
     *
     * @return array<string, mixed>
     */
    protected function buildPermissionsSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'canCreate' => ['type' => 'string'],
                'canEdit' => ['type' => 'string'],
                'canDelete' => ['type' => 'string'],
                'canView' => ['type' => 'string'],
                'canExport' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Export schema as JSON string.
     *
     * @return string
     */
    public function exportAsJson(): string
    {
        return json_encode(
            $this->buildCrudConfigSchema(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Save schema to file.
     *
     * @param string $path
     * @return bool
     */
    public function saveToFile(string $path): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($path, $this->exportAsJson()) !== false;
    }
}

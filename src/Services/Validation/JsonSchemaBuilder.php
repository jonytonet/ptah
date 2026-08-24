<?php

declare(strict_types=1);

namespace Ptah\Services\Validation;

use Ptah\Enums\CrudConfigEnums;
use Ptah\Support\StyleRule;

/**
 * Builder for JSON Schema definitions.
 *
 * Generates JSON Schema documents for validating CRUD configurations.
 * This can be used for client-side validation or documentation generation.
 *
 * AUTHORITY NOTE: the schema this class emits is DOCUMENTATION, not the gate.
 * The gate is {@see ConfigSchemaValidator}, which is what ConfigCommand
 * actually runs. Two consequences are deliberate:
 *
 *   - The row-style section is keyed `contitionStyles` — the typo is the
 *     runtime's real persisted contract (HasCrudRenderers::getRowStyle() reads
 *     it, every writer writes it), ratified as canonical rather than renamed,
 *     because renaming would touch every exported config in every installation
 *     to buy nothing but spelling.
 *   - The root is NOT `additionalProperties: false`. It used to be, while
 *     declaring 7 keys — and real configurations carry 24+ (cacheStrategy,
 *     configLinkLinha, crud, customFilters, uiPreferences, notifications…), so
 *     that flag made this schema reject every working config in existence. A
 *     documentation schema that enumerates a growing key set becomes a lie the
 *     moment a key is added; the honest shape is to describe the sections it
 *     knows and stay open about the rest.
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
                'contitionStyles' => $this->buildStylesSchema(),
                'joins' => $this->buildJoinsSchema(),
                'general' => $this->buildGeneralSchema(),
                'permissions' => $this->buildPermissionsSchema(),
            ],
            // Intentionally open — see the AUTHORITY NOTE on the class.
            'additionalProperties' => true,
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
                        'enum' => CrudConfigEnums::ACTION_TYPES,
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
            'description' => 'Conditional row styles, stored under the `contitionStyles` key',
            'items' => [
                'type' => 'object',
                // Both shapes StyleRule::normalize() accepts, in the same order
                // it tries them. The canonical one is what --style= and the
                // visual editor produce; the legacy one predates the single
                // normaliser and still renders correctly, so a schema that
                // rejected it would be reporting a problem that does not exist.
                'anyOf' => [
                    $this->buildCanonicalStyleItem(),
                    $this->buildLegacyStyleItem(),
                ],
            ],
        ];
    }

    /**
     * The canonical row-style shape: what StyleParser (`--style=`), the wizard
     * and CrudConfig's editor all persist, and what HasCrudRenderers reads.
     *
     * @return array<string, mixed>
     */
    protected function buildCanonicalStyleItem(): array
    {
        return [
            'title' => 'Canonical row style',
            'required' => ['field', 'style'],
            'properties' => [
                'field' => [
                    'type' => 'string',
                    'description' => 'Column to compare',
                ],
                'condition' => [
                    'type' => 'string',
                    // Straight from the normaliser, so this list cannot drift
                    // from the operators getRowStyle()'s match() evaluates.
                    'enum' => StyleRule::CONDITIONS,
                    'description' => 'Comparison operator; defaults to == when absent',
                ],
                'value' => [
                    'description' => 'Value to compare against',
                ],
                'style' => [
                    'type' => 'string',
                    'description' => 'Inline CSS declarations or CSS classes to apply to the row',
                ],
            ],
        ];
    }

    /**
     * The legacy row-style shape. Still normalised and still rendered — kept in
     * the schema so an older exported config does not read as invalid.
     *
     * @return array<string, mixed>
     */
    protected function buildLegacyStyleItem(): array
    {
        return [
            'title' => 'Legacy row style',
            'required' => ['colsNomeFisico', 'colsCss'],
            'properties' => [
                'colsNomeFisico' => [
                    'type' => 'string',
                    'description' => 'Column to apply conditional style',
                ],
                'colsOperator' => [
                    'type' => 'string',
                    'enum' => ['eq', 'ne', 'lt', 'gt', 'lte', 'gte'],
                    'description' => 'Comparison operator alias',
                ],
                'colsValue' => [
                    'description' => 'Value to compare against',
                ],
                'colsCss' => [
                    'type' => 'string',
                    'description' => 'CSS classes to apply',
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
     */
    public function saveToFile(string $path): bool
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($path, $this->exportAsJson()) !== false;
    }
}

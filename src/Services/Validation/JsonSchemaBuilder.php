<?php

declare(strict_types=1);

namespace Ptah\Services\Validation;

use Ptah\Commands\Config\Parsers\GeneralParser;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Support\ColumnKeys;
use Ptah\Support\FilterRule;
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
 *   - 1.52.0: this schema feeds the ptah VS Code extension's autocomplete, so
 *     it now describes what the runtime READS: every `cols*` key comes from
 *     ColumnKeys::KNOWN (and an unknown `cols*` key is flagged), joins and
 *     permissions use the runtime's shapes, `customFilters`, `uiPreferences`,
 *     `exportConfig` and `bulkActions` are described, and the sections nothing
 *     reads (`actions`, `filters`, `general`, `styles`) carry a
 *     deprecationMessage that says where their content belongs.
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
                'customFilters' => $this->buildCustomFiltersSchema(),
                'contitionStyles' => $this->buildStylesSchema(),
                'joins' => $this->buildJoinsSchema(),
                'permissions' => $this->buildPermissionsSchema(),
                'uiPreferences' => $this->buildUiPreferencesSchema(),
                'exportConfig' => $this->buildExportConfigSchema(),
                'bulkActions' => $this->buildBulkActionsSchema(),
                'displayName' => ['type' => 'string', 'description' => 'Screen title'],
                'actions' => $this->buildActionsSchema(),
                'filters' => $this->buildFiltersSchema(),
                'general' => $this->buildGeneralSchema(),
                'styles' => [
                    'deprecationMessage' => 'Never read: row styles live in "contitionStyles". `php artisan ptah:config:doctor --fix` migrates them.',
                ],
            ],
            // Intentionally open — see the AUTHORITY NOTE on the class.
            'additionalProperties' => true,
        ];
    }

    /**
     * The file `ptah:config:export-all` writes: the config inside a
     * {model, route, config} envelope (1.52.0, for the VS Code extension).
     *
     * @return array<string, mixed>
     */
    public function buildExportFileSchema(): array
    {
        $config = $this->buildCrudConfigSchema();
        unset($config['$schema']);

        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'ptah crud_configs export',
            'type' => 'object',
            'required' => ['model', 'config'],
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Runtime model key, e.g. "Product" or "Catalog/Product"'],
                'route' => ['type' => 'string', 'description' => 'Route of a route-specific config; "" for the global one'],
                'config' => $config,
            ],
        ];
    }

    /**
     * Build schema for columns section.
     *
     * Every key the runtime reads (ColumnKeys::KNOWN) is listed, so an editor
     * completes it; the common ones carry a type. A `cols*` key outside that
     * list is flagged — the runtime ignores it without a word. Keys without
     * the `cols` prefix stay allowed: action columns carry `action*` keys, and
     * a host may keep its own and read them in a hook.
     *
     * @return array<string, mixed>
     */
    protected function buildColumnsSchema(): array
    {
        $typed = [
            'colsNomeFisico' => ['type' => 'string', 'description' => 'Database column (or relation path) this column shows and saves'],
            'colsNomeLogico' => ['type' => 'string', 'description' => 'Label in the table header and the form'],
            'colsTipo' => ['type' => 'string', 'enum' => array_values(array_unique(array_merge(CrudConfigEnums::COLUMN_TYPES, ['action']))), 'description' => 'Form input type ("action" for a row-action column)'],
            'colsRenderer' => ['type' => 'string', 'enum' => CrudConfigEnums::RENDERERS, 'description' => 'How the cell is drawn in the listing'],
            'colsRendererBadges' => ['type' => 'array', 'description' => 'Badges: [{"value", "color", "label"}]'],
            'colsRequired' => ['type' => 'boolean', 'description' => 'Required in the form'],
            'colsGravar' => ['type' => 'boolean', 'description' => 'false: the field is not shown in the form nor saved'],
            'colsReadonly' => ['type' => 'boolean', 'description' => 'Shown in the form, not editable, not saved'],
            'colsVisibleList' => ['type' => 'boolean', 'description' => 'false: hidden in the listing'],
            'colsIsFilterable' => ['type' => 'boolean', 'description' => 'Offered in the filter panel'],
            'colsSearchable' => ['type' => 'boolean', 'description' => 'Included in the text search'],
            'colsValidations' => ['type' => ['array', 'string'], 'description' => 'Extra form rules, e.g. ["email", "maxLength:255"] (required is colsRequired)'],
            'colsSelect' => ['type' => 'object', 'description' => 'Select options as a label => value map'],
            'colsRelacaoBusca' => ['type' => ['string', 'array'], 'description' => 'Real column(s) of the relation to search/filter by text, when colsRelacaoExibe is an accessor. Dotted paths go deeper: "user.name"'],
            'colsSDTipo' => ['type' => 'string', 'enum' => ['model', 'service'], 'description' => 'Searchdropdown source'],
            'colsSDService' => ['type' => 'string', 'description' => 'Class implementing Ptah\\Contracts\\SearchDropdownSource'],
            'colsPermission' => ['type' => 'string', 'description' => 'Page-object key that gates this column'],
            'actionType' => ['type' => 'string', 'enum' => CrudConfigEnums::ACTION_TYPES, 'description' => 'Row action: what it does (colsTipo "action")'],
            'actionValue' => ['type' => 'string', 'description' => 'Livewire call, URL or JS; %field% is replaced by the row value'],
            'actionIcon' => ['type' => 'string', 'description' => 'Boxicons name, e.g. bx-check'],
            'actionColor' => ['type' => 'string', 'enum' => CrudConfigEnums::ACTION_COLORS],
            'actionConfirm' => ['type' => 'boolean'],
        ];

        $properties = $typed;
        foreach (ColumnKeys::KNOWN as $key) {
            $properties[$key] ??= ['description' => 'Read by the runtime — see docs/Configuration.md'];
        }
        ksort($properties);

        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['colsNomeFisico'],
                'properties' => $properties,
                'propertyNames' => [
                    'anyOf' => [
                        ['enum' => array_keys($properties)],
                        ['not' => ['pattern' => '^cols']],
                    ],
                ],
            ],
        ];
    }

    /**
     * The custom filters the filter panel reads (FilterRule::SECTION).
     *
     * @return array<string, mixed>
     */
    protected function buildCustomFiltersSchema(): array
    {
        return [
            'type' => 'array',
            'description' => 'Custom filters of the filter panel',
            'items' => [
                'type' => 'object',
                'required' => ['field'],
                'properties' => [
                    'field' => ['type' => 'string', 'description' => 'Column to filter'],
                    'label' => ['type' => 'string'],
                    'colsFilterType' => ['type' => 'string', 'enum' => CrudConfigEnums::FILTER_TYPES],
                    'operator' => ['type' => 'string', 'enum' => CrudConfigEnums::OPERATORS],
                    'colsSelect' => ['type' => 'object', 'description' => 'Select options as a label => value map'],
                    'whereHas' => ['type' => 'string', 'description' => 'Relation to filter through'],
                    'field_relation' => ['type' => 'string'],
                    'aggregate' => ['type' => 'string', 'enum' => CrudConfigEnums::AGGREGATES],
                ],
            ],
        ];
    }

    /**
     * The legacy actions section — read by nothing. Kept so the action types
     * stay documented next to the validator's.
     *
     * @return array<string, mixed>
     */
    protected function buildActionsSchema(): array
    {
        return [
            'type' => 'array',
            'deprecationMessage' => 'Never read: a row action is a column in "cols" with colsTipo "action" (actionType, actionValue, actionIcon…). `php artisan ptah:config:doctor --fix` moves these.',
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
                ],
            ],
        ];
    }

    /**
     * The legacy filters section — read by nothing since 1.28.0.
     *
     * @return array<string, mixed>
     */
    protected function buildFiltersSchema(): array
    {
        return [
            'type' => 'array',
            'deprecationMessage' => 'Never read: custom filters live in "'.FilterRule::SECTION.'". `php artisan ptah:config:doctor --fix` migrates them.',
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
     * Build schema for joins section — the shape CrudJoins reads.
     *
     * @return array<string, mixed>
     */
    protected function buildJoinsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['table', 'first', 'second'],
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => CrudConfigEnums::JOIN_TYPES, 'description' => 'JOIN type (default left)'],
                    'table' => ['type' => 'string', 'description' => 'Table to join'],
                    'first' => ['type' => 'string', 'description' => 'Left side of ON, e.g. products.supplier_id'],
                    'second' => ['type' => 'string', 'description' => 'Right side of ON, e.g. suppliers.id'],
                    'distinct' => ['type' => 'boolean'],
                    'select' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['column'],
                            'properties' => [
                                'column' => ['type' => 'string', 'description' => 'e.g. suppliers.name'],
                                'alias' => ['type' => 'string', 'description' => 'e.g. supplier_name'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The `general` section — read by nothing. Its keys belong elsewhere
     * (GeneralParser::ALIASES) or do not exist (GeneralParser::UNREAD).
     *
     * @return array<string, mixed>
     */
    protected function buildGeneralSchema(): array
    {
        return [
            'type' => 'object',
            'deprecationMessage' => 'Never read. itemsPerPage → uiPreferences.perPage, exportEnabled → exportConfig.enabled; '
                .implode(', ', array_slice(GeneralParser::UNREAD, 0, 4)).'… do not exist.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildUiPreferencesSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perPage' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Rows per page'],
                'compactMode' => ['type' => 'boolean'],
                'stickyHeader' => ['type' => 'boolean'],
                'highlightOnHover' => ['type' => 'boolean'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildExportConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'asyncThreshold' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Above this many rows the export is queued'],
                'maxRows' => ['type' => 'integer', 'minimum' => 1],
                'orientation' => ['type' => 'string', 'enum' => CrudConfigEnums::ORIENTATIONS],
                'formats' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['excel', 'pdf']]],
                'chunkSize' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildBulkActionsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['label', 'action'],
                'properties' => [
                    'label' => ['type' => 'string'],
                    'action' => ['type' => 'string'],
                    'method' => ['type' => 'string', 'description' => '"Class@key": the class implements Ptah\\Contracts\\BulkActionHandler; the key is handed to it'],
                ],
            ],
        ];
    }

    /**
     * Build schema for permissions section — the keys HasCrudForm reads.
     *
     * @return array<string, mixed>
     */
    protected function buildPermissionsSchema(): array
    {
        $gate = fn (string $what): array => ['type' => ['string', 'null'], 'description' => "Gate name checked with can() to {$what}"];

        return [
            'type' => 'object',
            'properties' => [
                'create' => $gate('create'),
                'edit' => $gate('edit'),
                'delete' => $gate('delete'),
                'restore' => $gate('restore'),
                'export' => $gate('export'),
                'import' => $gate('import'),
                'history' => $gate('see the history'),
                'attachments' => $gate('manage attachments'),
                'showCreateButton' => ['type' => 'boolean'],
                'showEditButton' => ['type' => 'boolean'],
                'showDeleteButton' => ['type' => 'boolean'],
                'showTrashButton' => ['type' => 'boolean'],
                'permissionIdentifier' => ['type' => 'string', 'description' => 'ACL key of the Permissions module (gates nothing while it is off)'],
                'identifier' => ['deprecationMessage' => 'Never read: the runtime reads "permissionIdentifier" — this screen is NOT gated. `php artisan ptah:config:doctor --fix` migrates it.'],
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

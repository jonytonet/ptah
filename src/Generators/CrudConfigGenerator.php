<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Support\EntityContext;
use Ptah\Support\FieldDefinition;

/**
 * CrudConfig generator.
 *
 * Creates (or updates) the row in the `crud_configs` table with the full
 * default BaseCrud configuration for the scaffolded entity.
 *
 * Does not use stubs — persists directly to the database via CrudConfigService.
 */
class CrudConfigGenerator extends AbstractGenerator
{
    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    protected function label(): string
    {
        return 'CrudConfig';
    }

    /**
     * Only runs when generating views (web mode).
     * In --api mode there is no list screen.
     */
    public function shouldRun(EntityContext $context): bool
    {
        return $context->withViews;
    }

    /**
     * Generates and persists the CRUD configuration to the database.
     */
    public function generate(EntityContext $context): GeneratorResult
    {
        // Include subfolder in identifier: Product/ProductStock
        $crudIdentifier = $context->subFolder
            ? $context->subFolder . '/' . $context->entity
            : $context->entity;

        $label = "CrudConfig [{$crudIdentifier}]";

        try {
            $service = app(CrudConfigService::class);

            // If it already exists and --force is not set, skip
            $existing = $service->find($crudIdentifier);
            if ($existing && ! $context->force) {
                return GeneratorResult::skipped($label, 'crud_configs');
            }

            $config = $this->buildConfig($context);
            $service->save($crudIdentifier, $config);

            return GeneratorResult::done($label, 'crud_configs');
        } catch (\Throwable $e) {
            return GeneratorResult::error($label, 'crud_configs', $e->getMessage());
        }
    }

    // ── Construção do config ────────────────────────────────────────────────

    private function buildConfig(EntityContext $context): array
    {
        // Include subfolder in identifier for BaseCrud to resolve correctly (e.g. Product/ProductStock)
        $crudIdentifier = $context->subFolder
            ? $context->subFolder . '/' . $context->entity
            : $context->entity;

        return [
            'crud'              => $crudIdentifier,
            'totalizador'       => false,
            'configEsconderId'  => false,
            'configLinkLinha'   => '/' . $context->entityPlural . '/%id%',
            'tableClass'        => 'table table-hover table-condensed table-sm table-bordered table-nowrap align-middle',
            'theadClass'        => '',
            'cols'              => $this->buildCols($context),
            'customFilters'     => [],
            'contitionStyles'   => [],
            'totalizadores'     => ['enabled' => false, 'columns' => []],
            'permissions'       => $this->defaultPermissions($context),
            'cacheStrategy'     => ['enabled' => true, 'ttl' => 300, 'tags' => []],
            'exportConfig'      => $this->defaultExportConfig(),
            'dateRangeFilters'  => $this->buildDateRangeFilters($context),
            'uiPreferences'     => [
                'theme'            => 'light',
                'compactMode'      => false,
                'stickyHeader'     => true,
                'showTotalizador'  => false,
                'highlightOnHover' => true,
            ],
        ];
    }

    /**
     * Builds the columns from the EntityContext FieldDefinitions.
     *
     * Order: id (read-only) → entity fields → created_at (read-only)
     */
    private function buildCols(EntityContext $context): array
    {
        $cols = [];

        // ID column always present
        $cols[] = [
            'colsNomeFisico'  => 'id',
            'colsNomeLogico'  => trans('ptah::ui.col_id'),
            'colsTipo'        => 'number',
            'colsGravar'      => false,
            'colsRequired'    => false,
            'colsAlign'       => 'text-center',
            'colsIsFilterable'=> true,
        ];

        // Entity fields
        foreach ($context->fields as $field) {
            $cols[] = $this->buildColFromField($field);
        }

        // created_at
        $cols[] = [
            'colsNomeFisico'  => 'created_at',
            'colsNomeLogico'  => trans('ptah::ui.col_created_at'),
            'colsTipo'        => 'date',
            'colsGravar'      => false,
            'colsRequired'    => false,
            'colsAlign'       => 'text-center',
            'colsHelper'      => 'dateFormat',
            'colsIsFilterable'=> true,
        ];

        return $cols;
    }

    /**
     * Converts a FieldDefinition into a CrudConfig col entry.
     */
    private function buildColFromField(FieldDefinition $field): array
    {
        $tipo    = $this->mapTipo($field);
        $gravar  = true;
        $align   = $this->mapAlign($field);
        $col = [
            'colsNomeFisico'  => $field->name,
            'colsNomeLogico'  => $field->label !== '' ? $field->label : $this->humanLabel($field->name),
            'colsTipo'        => $tipo,
            'colsGravar'      => $gravar,
            'colsRequired'    => false,
            'colsAlign'       => $align,
            'colsIsFilterable'=> true,
        ];

        // FK fields → relation
        if (str_ends_with($field->name, '_id')) {
            $col['colsRelacao']      = '';
            $col['colsRelacaoExibe'] = '';
        }

        // Enum → select with options
        if ($field->type === 'enum' && ! empty($field->enumValues)) {
            $col['colsSelect'] = $this->buildEnumSelect($field->enumValues);
        }

        // Boolean → select Yes/No (respects PTAH_LOCALE)
        if ($field->type === 'boolean') {
            $col['colsSelect'] = [trans('ptah::ui.bool_yes') => '1', trans('ptah::ui.bool_no') => '0'];
            $col['colsHelper'] = 'yesOrNot';
        }

        // Decimal/float → currency helper (for price/value/amount fields)
        if (in_array($field->type, ['decimal', 'float', 'double'])) {
            if ($this->isCurrencyField($field->name)) {
                $col['colsHelper'] = 'currencyFormat';
                $col['colsAlign']  = 'text-end';
                $col['colsReverse'] = true;
            }
        }

        // Date/datetime
        if (in_array($field->type, ['date', 'datetime', 'timestamp'])) {
            $col['colsHelper'] = 'dateFormat';
        }

        return $col;
    }

    /**
     * Maps the FieldDefinition type to BaseCrud's colsTipo.
     */
    private function mapTipo(FieldDefinition $field): string
    {
        return match (true) {
            $field->type === 'boolean'                                         => 'select',
            $field->type === 'enum'                                            => 'select',
            in_array($field->type, ['integer', 'bigInteger', 'unsignedBigInteger',
                                    'unsignedInteger', 'tinyInteger', 'smallInteger',
                                    'decimal', 'float', 'double'])             => 'number',
            in_array($field->type, ['date', 'datetime', 'timestamp'])          => 'date',
            default                                                             => 'text',
        };
    }

    private function mapAlign(FieldDefinition $field): string
    {
        return match (true) {
            in_array($field->type, ['integer', 'bigInteger', 'unsignedBigInteger',
                                    'decimal', 'float', 'double'])             => 'text-end',
            $field->type === 'boolean'                                         => 'text-center',
            in_array($field->type, ['date', 'datetime', 'timestamp'])          => 'text-center',
            default                                                             => 'text-start',
        };
    }

    private function buildEnumSelect(array $values): array
    {
        $select = [];
        foreach ($values as $v) {
            $select[ucfirst((string) $v)] = (string) $v;
        }
        return $select;
    }

    private function isCurrencyField(string $name): bool
    {
        foreach (['price', 'value', 'amount', 'cost', 'total', 'salary', 'wage', 'fee'] as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function humanLabel(string $name): string
    {
        return ucwords(str_replace('_', ' ', preg_replace('/_id$/', '', $name) ?? $name));
    }

    private function buildDateRangeFilters(EntityContext $context): array
    {
        $filters = [];

        foreach ($context->fields as $field) {
            if (in_array($field->type, ['date', 'datetime', 'timestamp'])) {
                $filters[$field->name] = [
                    'enableRange'              => true,
                    'defaultOperator'          => 'BETWEEN',
                    'quickFilters'             => [],
                    'disableEndDateWhenEquals' => true,
                ];
            }
        }

        // created_at sempre
        $filters['created_at'] = [
            'enableRange'              => true,
            'defaultOperator'          => 'BETWEEN',
            'quickFilters'             => [],
            'disableEndDateWhenEquals' => true,
        ];

        return $filters;
    }

    private function defaultPermissions(EntityContext $context): array
    {
        return [
            'create'           => null,
            'edit'             => null,
            'delete'           => null,
            'export'           => null,
            'restore'          => null,
            'showCreateButton' => true,
            'showEditButton'   => true,
            'showDeleteButton' => true,
            'showTrashButton'  => true,
            'identifier'       => 'page' . $context->entity,
        ];
    }

    private function defaultExportConfig(): array
    {
        return [
            'enabled'             => true,
            'asyncThreshold'      => 1000,
            'maxRows'             => 10000,
            'orientation'         => 'landscape',
            'formats'             => ['excel', 'pdf'],
            'chunkSize'           => 500,
            'notificationChannel' => 'database',
        ];
    }
}

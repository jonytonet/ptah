<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Support\EntityContext;
use Ptah\Support\FieldDefinition;

/**
 * Gerador de CrudConfig.
 *
 * Cria (ou atualiza) a linha na tabela `crud_configs` com toda a configuração
 * padrão do BaseCrud para a entidade scaffolded.
 *
 * Não usa stubs — persiste diretamente no banco via CrudConfigService.
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
     * Só roda quando gera views (modo web).
     * No modo --api não existe tela de listagem.
     */
    public function shouldRun(EntityContext $context): bool
    {
        return $context->withViews;
    }

    /**
     * Gera e salva a configuração do CRUD no banco.
     */
    public function generate(EntityContext $context): GeneratorResult
    {
        $label = "CrudConfig [{$context->entity}]";

        try {
            $service = app(CrudConfigService::class);

            // Se já existe e não é --force, pula
            $existing = $service->find($context->entity);
            if ($existing && ! $context->force) {
                return GeneratorResult::skipped($label, 'crud_configs');
            }

            $config = $this->buildConfig($context);
            $service->save($context->entity, $config);

            return GeneratorResult::done($label, 'crud_configs');
        } catch (\Throwable $e) {
            return GeneratorResult::error($label, 'crud_configs', $e->getMessage());
        }
    }

    // ── Construção do config ────────────────────────────────────────────────

    private function buildConfig(EntityContext $context): array
    {
        return [
            'crud'              => $context->entity,
            'totalizador'       => false,
            'configEsconderId'  => 'N',
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
     * Constrói as colunas a partir dos FieldDefinitions da EntityContext.
     *
     * Ordem: id (read-only) → campos da entidade → created_at (read-only)
     */
    private function buildCols(EntityContext $context): array
    {
        $cols = [];

        // Coluna ID sempre presente
        $cols[] = [
            'colsNomeFisico'  => 'id',
            'colsNomeLogico'  => 'ID',
            'colsTipo'        => 'number',
            'colsGravar'      => 'N',
            'colsRequired'    => 'N',
            'colsAlign'       => 'text-center',
            'colsIsFilterable'=> 'N',
        ];

        // Campos da entidade
        foreach ($context->fields as $field) {
            $cols[] = $this->buildColFromField($field);
        }

        // created_at
        $cols[] = [
            'colsNomeFisico'  => 'created_at',
            'colsNomeLogico'  => 'Criado em',
            'colsTipo'        => 'date',
            'colsGravar'      => 'N',
            'colsRequired'    => 'N',
            'colsAlign'       => 'text-center',
            'colsHelper'      => 'dateFormat',
            'colsIsFilterable'=> 'S',
        ];

        return $cols;
    }

    /**
     * Converte um FieldDefinition em um col do CrudConfig.
     */
    private function buildColFromField(FieldDefinition $field): array
    {
        $tipo    = $this->mapTipo($field);
        $gravar  = 'S';
        $align   = $this->mapAlign($field);
        $col = [
            'colsNomeFisico'  => $field->name,
            'colsNomeLogico'  => $this->humanLabel($field->name),
            'colsTipo'        => $tipo,
            'colsGravar'      => $gravar,
            'colsRequired'    => 'N',
            'colsAlign'       => $align,
            'colsIsFilterable'=> 'S',
        ];

        // Campos FK → relação
        if (str_ends_with($field->name, '_id')) {
            $col['colsRelacao']      = '';
            $col['colsRelacaoExibe'] = '';
        }

        // Enum → select com opções
        if ($field->type === 'enum' && ! empty($field->enumValues)) {
            $col['colsSelect'] = $this->buildEnumSelect($field->enumValues);
        }

        // Boolean → select Sim/Não
        if ($field->type === 'boolean') {
            $col['colsSelect'] = ['Sim' => '1', 'Não' => '0'];
            $col['colsHelper'] = 'yesOrNot';
        }

        // Decimal/float → helper de moeda (para campos price/value/amount)
        if (in_array($field->type, ['decimal', 'float', 'double'])) {
            if ($this->isCurrencyField($field->name)) {
                $col['colsHelper'] = 'currencyFormat';
                $col['colsAlign']  = 'text-end';
                $col['colsReverse'] = 'S';
            }
        }

        // Date/datetime
        if (in_array($field->type, ['date', 'datetime', 'timestamp'])) {
            $col['colsHelper'] = 'dateFormat';
        }

        return $col;
    }

    /**
     * Mapeia o tipo do FieldDefinition para o colsTipo do BaseCrud.
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

<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;
use Ptah\Support\FieldDefinition;

/**
 * Gera o Model Eloquent da entidade.
 *
 * Stub: model.stub
 * Placeholders: namespace, entity, table, fillable, casts,
 *               soft_deletes_use, soft_deletes_trait, swagger_schema
 */
class ModelGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        // Subpasta: app/Models/Product/ProductStock.php
        $subDir = $context->subFolder ? '/' . str_replace('\\', '/', $context->subFolder) : '';
        $path   = config('ptah.paths.models') . "{$subDir}/{$context->entity}.php";

        $softDeletesUse   = '';
        $softDeletesTrait = '';

        if ($context->withSoftDeletes) {
            $softDeletesUse   = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $softDeletesTrait = "    use SoftDeletes;\n";
        }

        // Gera @OA\Schema apenas no modo --api
        $swaggerSchema = $context->withViews ? '' : $this->buildSwaggerSchema($context);

        return $this->writeFile(
            path: $path,
            stub: 'model',
            replacements: [
                'namespace'          => $context->modelNamespace,
                'entity'             => $context->entity,
                'table'              => $context->table,
                'fillable'           => $context->fillableList(),
                'casts'              => $context->castsList(),
                'soft_deletes_use'   => $softDeletesUse,
                'soft_deletes_trait' => $softDeletesTrait,
                'relationships_use'  => $context->relationshipsUse(),
                'relationships'      => $context->relationships(),
                'swagger_schema'     => $swaggerSchema,
            ],
            force: $context->force,
            labelOverride: "Model [{$context->entity}]",
        );
    }

    protected function label(): string
    {
        return 'Model';
    }

    /**
     * Gera o bloco de anotação @OA\Schema para o modelo.
     * Mapeia os tipos dos campos para tipos OpenAPI.
     */
    private function buildSwaggerSchema(EntityContext $context): string
    {
        $props = [];

        foreach ($context->fields as $field) {
            /** @var FieldDefinition $field */
            if ($field->isForeignKey()) {
                $propType   = 'integer';
                $propFormat = '';
            } else {
                [$propType, $propFormat] = $this->mapToOpenApiType($field->type);
            }

            $formatStr = $propFormat ? ", format=\"{$propFormat}\"" : '';
            $props[]   = " *     @OA\\Property(property=\"{$field->name}\", type=\"{$propType}\"{$formatStr}),";
        }

        $propsStr = implode("\n", $props);

        return <<<SCHEMA

/**
 * @OA\\Schema(
 *     schema="{$context->entity}",
 *     title="{$context->entity}",
 *     description="Model {$context->entity}",
 *     @OA\\Xml(name="{$context->entity}"),
{$propsStr}
 * )
 */
SCHEMA;
    }

    /**
     * Mapeia tipo de campo para [tipo OpenAPI, formato].
     *
     * @return array{string, string}
     */
    private function mapToOpenApiType(string $type): array
    {
        return match (strtolower($type)) {
            'integer', 'int', 'biginteger', 'smallinteger', 'tinyinteger' => ['integer', ''],
            'decimal', 'float', 'double'                                   => ['number', 'float'],
            'boolean', 'bool'                                              => ['boolean', ''],
            'date'                                                         => ['string', 'date'],
            'datetime', 'timestamp'                                        => ['string', 'date-time'],
            'json', 'array', 'object'                                      => ['object', ''],
            'text', 'longtext', 'mediumtext'                               => ['string', ''],
            default                                                        => ['string', ''],
        };
    }
}

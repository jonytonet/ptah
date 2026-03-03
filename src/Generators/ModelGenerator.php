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

        // ── Modo "adicionar API" (--api sem --api-only) + arquivo já existe ──────
        // Nesse fluxo o model já tem $fillable, $casts e relacionamentos corretos.
        // Sobrescrever perderia customizações do desenvolvedor.
        // Em vez disso, apenas injetamos o @OA\Schema block se ainda não estiver lá.
        if ($context->withApi && $context->withViews && file_exists($path)) {
            return $this->injectSwaggerSchema($path, $context);
        }

        $softDeletesUse   = '';
        $softDeletesTrait = '';

        if ($context->withSoftDeletes) {
            $softDeletesUse   = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $softDeletesTrait = "    use SoftDeletes;\n";
        }

        // Gera @OA\Schema quando o modo API estiver ativo
        $swaggerSchema = $context->withApi ? $this->buildSwaggerSchema($context) : '';

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

    /**
     * Injeta o bloco @OA\Schema no model existente sem sobrescrever o arquivo.
     * Usado no modo "adicionar API" (--api) quando o model já foi criado (modo web).
     * Se o @OA\Schema já existir no arquivo, retorna skipped.
     */
    private function injectSwaggerSchema(string $path, EntityContext $context): GeneratorResult
    {
        $label   = "Model [{$context->entity}] (swagger injected)";
        $content = $this->files->get($path);

        // Já tem @OA\Schema — nada a fazer
        if (str_contains($content, '@OA\\Schema') || str_contains($content, '@OA\Schema')) {
            return GeneratorResult::skipped($label, $path);
        }

        $schema = $this->buildSwaggerSchema($context);

        // Insere o bloco imediatamente antes da declaração `class XyzAbc`
        $patched = preg_replace(
            '/^(class\s+' . preg_quote($context->entity, '/') . '\s+)/m',
            $schema . "\n$1",
            $content,
        );

        if ($patched === null || $patched === $content) {
            // Regex não encontrou o padrão esperado — salva aviso e pula
            return GeneratorResult::skipped(
                "Model [{$context->entity}] (swagger — class declaration not found)",
                $path,
            );
        }

        $this->files->put($path, $patched);

        return GeneratorResult::done($label, $path);
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

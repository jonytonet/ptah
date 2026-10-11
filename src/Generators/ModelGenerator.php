<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;
use Ptah\Support\FieldDefinition;

/**
 * Generates the Eloquent Model for the entity.
 *
 * Stub: model.stub
 * Placeholders: namespace, entity, table, fillable, casts,
 *               soft_deletes_use, soft_deletes_trait, swagger_schema
 */
class ModelGenerator extends AbstractGenerator
{
    private const SWAGGER_USE = 'use OpenApi\Attributes as OA;';

    public function generate(EntityContext $context): GeneratorResult
    {
        // Subfolder: app/Models/Product/ProductStock.php
        $subDir = $context->subFolder ? '/'.str_replace('\\', '/', $context->subFolder) : '';
        $path = config('ptah.paths.models')."{$subDir}/{$context->entity}.php";

        // ── "Add API" mode (--api without --api-only) + file already exists ──────
        // In this flow the model already has the correct $fillable, $casts and relationships.
        // Overwriting it would lose developer customisations.
        // Instead, inject the OA\Schema attribute only if it is not already there.
        if ($context->withApi && $context->withViews && file_exists($path)) {
            return $this->injectSwaggerSchema($path, $context);
        }

        $softDeletesUse = '';
        $softDeletesTrait = '';

        if ($context->withSoftDeletes) {
            $softDeletesUse = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $softDeletesTrait = "    use SoftDeletes;\n";
        }

        // OA\Schema attribute (and its `use`) when API mode is active
        $swaggerSchema = $context->withApi ? self::SWAGGER_USE."\n\n".rtrim($this->buildSwaggerSchema($context)) : '';

        return $this->writeFile(
            path: $path,
            stub: 'model',
            replacements: [
                'namespace' => $context->modelNamespace,
                'entity' => $context->entity,
                'table' => $context->table,
                'fillable' => $context->fillableList(),
                'casts' => $context->castsList(),
                'soft_deletes_use' => $softDeletesUse,
                'soft_deletes_trait' => $softDeletesTrait,
                'relationships_use' => $context->relationshipsUse(),
                'relationships' => $context->relationships(),
                'swagger_schema' => $swaggerSchema,
            ],
            force: $context->force,
            labelOverride: "Model [{$context->entity}]",
        );
    }

    /**
     * Injects the OA\Schema attribute (and its `use`) into an existing model
     * without overwriting the file. Used in "add API" mode (--api) when the
     * model was already created in web mode. Skipped when the model already
     * has a schema — as an attribute or as an old `@OA\Schema` docblock.
     */
    private function injectSwaggerSchema(string $path, EntityContext $context): GeneratorResult
    {
        $label = "Model [{$context->entity}] (swagger injected)";
        $content = $this->files->get($path);

        if (str_contains($content, '#[OA\\Schema') || str_contains($content, '@OA\\Schema')) {
            return GeneratorResult::skipped($label, $path);
        }

        // Insert the attribute immediately before the `class XyzAbc` declaration
        // Callback, nao string de substituicao: o atributo tem barras
        // invertidas (OA\Schema) que o preg_replace poderia interpretar.
        $schema = $this->buildSwaggerSchema($context);
        $patched = preg_replace_callback(
            '/^(class\s+'.preg_quote($context->entity, '/').'\s+)/m',
            fn (array $m) => $schema.$m[1],
            $content,
            1,
        );

        if ($patched === null || $patched === $content) {
            // Regex did not find the expected pattern — saves warning and skips
            return GeneratorResult::skipped(
                "Model [{$context->entity}] (swagger — class declaration not found)",
                $path,
            );
        }

        // The `use` after the file's last top-level import (or after the
        // namespace line when there is none), unless it is already there.
        if (! str_contains($patched, self::SWAGGER_USE)) {
            $head = substr($patched, 0, (int) strpos($patched, '#[OA\Schema'));
            if (preg_match_all('/^use\s+[^;]+;\R/m', $head, $m, PREG_OFFSET_CAPTURE) > 0) {
                $last = end($m[0]);
                $at = $last[1] + strlen($last[0]);
                $patched = substr($patched, 0, $at).self::SWAGGER_USE."\n".substr($patched, $at);
            } else {
                $patched = (string) preg_replace_callback('/^(namespace\s+[^;]+;\R)/m', fn (array $m) => $m[1]."\n".self::SWAGGER_USE."\n", $patched, 1);
            }
        }

        $this->files->put($path, $patched);

        return GeneratorResult::done($label, $path);
    }

    protected function label(): string
    {
        return 'Model';
    }

    /**
     * Builds the OA\Schema attribute for the model (1.44.0). It was an
     * `@OA\Schema` docblock, which swagger-php 6 (l5-swagger 11) does not read
     * without doctrine/annotations. Field names are identifiers — they go
     * into the single-quoted strings as they are.
     */
    private function buildSwaggerSchema(EntityContext $context): string
    {
        $props = [];

        foreach ($context->fields as $field) {
            /** @var FieldDefinition $field */
            if ($field->isForeignKey()) {
                $propType = 'integer';
                $propFormat = '';
            } else {
                [$propType, $propFormat] = $this->mapToOpenApiType($field->type);
            }

            $formatStr = $propFormat ? ", format: '{$propFormat}'" : '';
            $props[] = "        new OA\\Property(property: '{$field->name}', type: '{$propType}'{$formatStr}),";
        }

        $propsStr = implode("\n", $props);

        return <<<SCHEMA
#[OA\\Schema(
    schema: '{$context->entity}',
    title: '{$context->entity}',
    description: 'Model {$context->entity}',
    xml: new OA\\Xml(name: '{$context->entity}'),
    properties: [
{$propsStr}
    ],
)]

SCHEMA;
    }

    /**
     * Maps a field type to [OpenAPI type, format].
     *
     * @return array{string, string}
     */
    private function mapToOpenApiType(string $type): array
    {
        return match (strtolower($type)) {
            'integer', 'int', 'biginteger', 'smallinteger', 'tinyinteger' => ['integer', ''],
            'decimal', 'float', 'double' => ['number', 'float'],
            'boolean', 'bool' => ['boolean', ''],
            'date' => ['string', 'date'],
            'datetime', 'timestamp' => ['string', 'date-time'],
            'json', 'array', 'object' => ['object', ''],
            'text', 'longtext', 'mediumtext' => ['string', ''],
            default => ['string', ''],
        };
    }
}

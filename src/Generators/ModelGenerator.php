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
    public function generate(EntityContext $context): GeneratorResult
    {
        // Subfolder: app/Models/Product/ProductStock.php
        $subDir = $context->subFolder ? '/' . str_replace('\\', '/', $context->subFolder) : '';
        $path   = config('ptah.paths.models') . "{$subDir}/{$context->entity}.php";

        // ── "Add API" mode (--api without --api-only) + file already exists ──────
        // In this flow the model already has the correct $fillable, $casts and relationships.
        // Overwriting it would lose developer customisations.
        // Instead, inject the @OA\Schema block only if it is not already there.
        if ($context->withApi && $context->withViews && file_exists($path)) {
            return $this->injectSwaggerSchema($path, $context);
        }

        $softDeletesUse   = '';
        $softDeletesTrait = '';

        if ($context->withSoftDeletes) {
            $softDeletesUse   = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $softDeletesTrait = "    use SoftDeletes;\n";
        }

        // Generate @OA\Schema when API mode is active
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
     * Injects the @OA\Schema block into an existing model without overwriting the file.
     * Used in "add API" mode (--api) when the model was already created in web mode.
     * Returns skipped if @OA\Schema is already present in the file.
     */
    private function injectSwaggerSchema(string $path, EntityContext $context): GeneratorResult
    {
        $label   = "Model [{$context->entity}] (swagger injected)";
        $content = $this->files->get($path);

        // Already has @OA\Schema — nothing to do
        if (str_contains($content, '@OA\\Schema') || str_contains($content, '@OA\Schema')) {
            return GeneratorResult::skipped($label, $path);
        }

        $schema = $this->buildSwaggerSchema($context);

        // Insert the block immediately before the `class XyzAbc` declaration
        $patched = preg_replace(
            '/^(class\s+' . preg_quote($context->entity, '/') . '\s+)/m',
            $schema . "\n$1",
            $content,
        );

        if ($patched === null || $patched === $content) {
            // Regex did not find the expected pattern — saves warning and skips
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
     * Builds the @OA\Schema annotation block for the model.
     * Maps field types to OpenAPI types.
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
     * Maps a field type to [OpenAPI type, format].
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

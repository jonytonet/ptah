<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Value object (immutable DTO) that carries all pre-computed data
 * of an entity during the scaffold execution.
 *
 * Created once by ScaffoldCommand and passed to all Generators.
 *
 * Sub-folder support:
 *   - subFolder   : subfolder relative to the models directory, e.g. 'Product'
 *                   Can be multi-level: 'Catalog/Product'
 *                   Empty when the entity is at the root.
 *   - modelNamespace : full PHP namespace of the Model, e.g. App\Models\Product
 *   - modelFqn       : full Model FQN,  e.g. App\Models\Product\ProductStock
 */
readonly class EntityContext
{
    /**
     * PHP namespace of the Model (e.g. App\Models\Product or App\Models).
     * Automatically computed from rootNamespace + subFolder.
     */
    public string $modelNamespace;

    /**
     * Full Model FQN (e.g. App\Models\Product\ProductStock).
     */
    public string $modelFqn;

    /**
     * @param FieldDefinition[] $fields
     * @param string $subFolder  Subfolder relative to Models/, e.g. 'Product'. Empty if root.
     */
    public function __construct(
        public string $entity,             // ProductStock
        public string $entityLower,        // product_stock
        public string $entityPlural,       // product_stocks
        public string $entityPluralStudly, // ProductStocks
        public string $table,              // product_stocks (ou valor de --table)
        public string $rootNamespace,      // App\
        public string $timestamp,          // 2026_02_25_120000
        public bool   $withViews,          // false quando --api-only
        public bool   $withSoftDeletes,    // true by default
        public bool   $force,              // --force
        public array  $fields,
        public string $subFolder = '',     // ex: 'Product' ou 'Catalog/Product'
        public bool   $withApi   = false,  // true quando --api ou --api-only
    ) {
        $nsBase = rtrim($rootNamespace, '\\') . '\\Models';
        $this->modelNamespace = $subFolder
            ? $nsBase . '\\' . str_replace('/', '\\', $subFolder)
            : $nsBase;
        $this->modelFqn = $this->modelNamespace . '\\' . $entity;
    }

    /**
     * Returns the namespace with the subfolder applied.
     * E.g. subNs('App\\Services') with subFolder='Product' → 'App\\Services\\Product'
     */
    public function subNs(string $baseNamespace): string
    {
        if ($this->subFolder === '') {
            return rtrim($baseNamespace, '\\');
        }

        return rtrim($baseNamespace, '\\') . '\\' . str_replace('/', '\\', $this->subFolder);
    }

    /**
     * Returns the file path with the subfolder applied.
     * E.g. subPath('app/Services') with subFolder='Product' → 'app/Services/Product'
     */
    public function subPath(string $basePath): string
    {
        if ($this->subFolder === '') {
            return rtrim($basePath, '/');
        }

        return rtrim($basePath, '/') . '/' . $this->subFolder;
    }

    /**
     * Generates the $fillable list as a string for the Model stub.
     * Result: 'name', 'price', 'status'
     */
    public function fillableList(): string
    {
        $base = empty($this->fields)
            ? ['// Add fields here']
            : array_map(fn(FieldDefinition $f) => "'{$f->name}'", $this->fields);

        // Audit fields — automatically populated by the HasAuditFields trait
        $base[] = "'created_by'";
        $base[] = "'updated_by'";

        if ($this->withSoftDeletes) {
            $base[] = "'deleted_by'";
        }

        return implode(",\n        ", $base);
    }

    /**
     * Generates the $casts block as a string for the Model stub.
     * Result: 'price' => 'decimal:2', 'is_active' => 'boolean',
     */
    public function castsList(): string
    {
        $base = empty($this->fields)
            ? ['// \'field\' => \'type\',']
            : array_map(fn(FieldDefinition $f) => "'{$f->name}' => '{$f->castType()}',", $this->fields);

        // Casts for the audit fields
        $base[] = "'created_by' => 'integer',";
        $base[] = "'updated_by' => 'integer',";

        if ($this->withSoftDeletes) {
            $base[] = "'deleted_by' => 'integer',";
        }

        return implode("\n        ", $base);
    }

    /**
     * Generates the Blueprint lines for the migration.
     */
    public function migrationColumns(): string
    {
        if (empty($this->fields)) {
            return "            // Add columns here";
        }

        return implode("\n", array_map(
            fn(FieldDefinition $f) => $f->migrationLine(),
            $this->fields
        ));
    }

    /**
     * Generates validation rules for Store.
     */
    public function validationRulesStore(): string
    {
        if (empty($this->fields)) {
            return "            // 'field' => 'required|string',";
        }

        return implode("\n            ", array_map(
            fn(FieldDefinition $f) => $f->validationRuleStore(),
            $this->fields
        ));
    }

    /**
     * Generates validation rules for Update.
     */
    public function validationRulesUpdate(): string
    {
        if (empty($this->fields)) {
            return "            // 'field' => 'sometimes|required|string',";
        }

        return implode("\n            ", array_map(
            fn(FieldDefinition $f) => $f->validationRuleUpdate(),
            $this->fields
        ));
    }

    /**
     * Generates DTO properties with PHP types.
     */
    public function dtoProperties(): string
    {
        if (empty($this->fields)) {
            return "        // public readonly string \$name,";
        }

        return implode("\n", array_map(
            fn(FieldDefinition $f) => "        public readonly {$f->phpType()} \${$f->name}" .
                ($f->nullable ? " = null," : ","),
            $this->fields
        ));
    }

    /**
     * Generates the DTO fromArray mapping.
     */
    public function dtoFromArray(): string
    {
        if (empty($this->fields)) {
            return "            // name: \$data['name'],";
        }

        return implode("\n", array_map(
            fn(FieldDefinition $f) => "            {$f->name}: \$data['{$f->name}']" .
                ($f->nullable ? " ?? null," : ","),
            $this->fields
        ));
    }

    /**
     * Generates belongsTo methods for FK fields (_id with large integer type).
     * Returns an empty string if there are no FKs.
     */
    public function relationships(): string
    {
        $fkFields = array_values(array_filter(
            $this->fields,
            fn(FieldDefinition $f) => $f->isForeignKey()
        ));

        if (empty($fkFields)) {
            return '';
        }

        $methods = array_map(function (FieldDefinition $f) {
            $methodName   = Str::camel($f->relatedName());
            $relatedModel = $f->relatedModel();

            return
                "    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo\n" .
                "    {\n" .
                "        return \$this->belongsTo({$relatedModel}::class, '{$f->name}');\n" .
                "    }";
        }, $fkFields);

        return "\n" . implode("\n\n", $methods) . "\n";
    }

    /**
     * Generates `use` declarations for models related via FK.
     *
     * Generates TODO comments instead of automatic imports to avoid
     * incorrect namespaces when related models are in different sub-folders
     * from the current entity.
     *
     * The developer must adjust the namespace according to the real model location.
     * Returns an empty string if there are no FKs.
     */
    public function relationshipsUse(): string
    {
        $fkFields = array_values(array_filter(
            $this->fields,
            fn(FieldDefinition $f) => $f->isForeignKey()
        ));

        if (empty($fkFields)) {
            return '';
        }

        $rootNs = rtrim($this->rootNamespace, '\\') . '\\Models';

        $lines = array_unique(array_map(
            function (FieldDefinition $f) use ($rootNs): string {
                // company_id → Ptah\Models\Company (well-known package model)
                if ($f->relatedModel() === 'Company') {
                    return 'use Ptah\\Models\\Company;';
                }

                return "// TODO: use {$rootNs}\\{$f->relatedModel()};" .
                    " // check the real namespace — adjust if {$f->relatedModel()} is in a sub-folder";
            },
            $fkFields
        ));

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generates the toArray() fields for the Resource.
     */
    public function resourceFields(): string
    {
        $lines = ["'id' => \$this->id,"];

        foreach ($this->fields as $field) {
            $lines[] = "            '{$field->name}' => \$this->{$field->name},";
        }

        $lines[] = "            'created_by' => \$this->created_by,";
        $lines[] = "            'updated_by' => \$this->updated_by,";

        if ($this->withSoftDeletes) {
            $lines[] = "            'deleted_by' => \$this->deleted_by,";
        }

        $lines[] = "            'created_at' => \$this->created_at,";
        $lines[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $lines);
    }
}

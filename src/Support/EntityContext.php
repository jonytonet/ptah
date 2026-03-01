<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Value object (DTO imutável) que carrega todos os dados
 * calculados de uma entidade durante a execução do scaffold.
 *
 * Criado uma vez pelo ScaffoldCommand e passado a todos os Generators.
 *
 * Suporte a subpastas:
 *   - subFolder   : subfolder relativo ao diretório de models, ex: 'Product'
 *                   Pode ser multi-nível: 'Catalog/Product'
 *                   Vazio quando a entidade está na raiz.
 *   - modelNamespace : namespace PHP completo do Model, ex: App\Models\Product
 *   - modelFqn       : FQN completo do Model,  ex: App\Models\Product\ProductStock
 */
readonly class EntityContext
{
    /**
     * Namespace PHP do Model (ex: App\Models\Product ou App\Models).
     * Calculado automaticamente a partir de rootNamespace + subFolder.
     */
    public string $modelNamespace;

    /**
     * FQN completo do Model (ex: App\Models\Product\ProductStock).
     */
    public string $modelFqn;

    /**
     * @param FieldDefinition[] $fields
     * @param string $subFolder  Subfolder relativo a Models/, ex: 'Product'. Vazio se raiz.
     */
    public function __construct(
        public string $entity,             // ProductStock
        public string $entityLower,        // product_stock
        public string $entityPlural,       // product_stocks
        public string $entityPluralStudly, // ProductStocks
        public string $table,              // product_stocks (ou valor de --table)
        public string $rootNamespace,      // App\
        public string $timestamp,          // 2026_02_25_120000
        public bool   $withViews,          // false se --api
        public bool   $withSoftDeletes,    // true por padrão
        public bool   $force,              // --force
        public array  $fields,
        public string $subFolder = '',     // ex: 'Product' ou 'Catalog/Product'
    ) {
        $nsBase = rtrim($rootNamespace, '\\') . '\\Models';
        $this->modelNamespace = $subFolder
            ? $nsBase . '\\' . str_replace('/', '\\', $subFolder)
            : $nsBase;
        $this->modelFqn = $this->modelNamespace . '\\' . $entity;
    }

    /**
     * Retorna o namespace com subfolder aplicado.
     * Ex: subNs('App\\Services') com subFolder='Product' → 'App\\Services\\Product'
     */
    public function subNs(string $baseNamespace): string
    {
        if ($this->subFolder === '') {
            return rtrim($baseNamespace, '\\');
        }

        return rtrim($baseNamespace, '\\') . '\\' . str_replace('/', '\\', $this->subFolder);
    }

    /**
     * Retorna o caminho de arquivo com subfolder aplicado.
     * Ex: subPath('app/Services') com subFolder='Product' → 'app/Services/Product'
     */
    public function subPath(string $basePath): string
    {
        if ($this->subFolder === '') {
            return rtrim($basePath, '/');
        }

        return rtrim($basePath, '/') . '/' . $this->subFolder;
    }

    /**
     * Gera a lista $fillable como string para o stub do Model.
     * Resultado: 'name', 'price', 'status'
     */
    public function fillableList(): string
    {
        if (empty($this->fields)) {
            return "// Adicione os campos aqui";
        }

        return implode(",\n        ", array_map(
            fn(FieldDefinition $f) => "'{$f->name}'",
            $this->fields
        ));
    }

    /**
     * Gera o bloco $casts como string para o stub do Model.
     * Resultado: 'price' => 'decimal:2', 'is_active' => 'boolean',
     */
    public function castsList(): string
    {
        if (empty($this->fields)) {
            return "// 'campo' => 'tipo',";
        }

        return implode("\n        ", array_map(
            fn(FieldDefinition $f) => "'{$f->name}' => '{$f->castType()}',",
            $this->fields
        ));
    }

    /**
     * Gera as linhas Blueprint para a migration.
     */
    public function migrationColumns(): string
    {
        if (empty($this->fields)) {
            return "            // Adicione as colunas aqui";
        }

        return implode("\n", array_map(
            fn(FieldDefinition $f) => $f->migrationLine(),
            $this->fields
        ));
    }

    /**
     * Gera as regras de validação para Store.
     */
    public function validationRulesStore(): string
    {
        if (empty($this->fields)) {
            return "            // 'campo' => 'required|string',";
        }

        return implode("\n            ", array_map(
            fn(FieldDefinition $f) => $f->validationRuleStore(),
            $this->fields
        ));
    }

    /**
     * Gera as regras de validação para Update.
     */
    public function validationRulesUpdate(): string
    {
        if (empty($this->fields)) {
            return "            // 'campo' => 'sometimes|required|string',";
        }

        return implode("\n            ", array_map(
            fn(FieldDefinition $f) => $f->validationRuleUpdate(),
            $this->fields
        ));
    }

    /**
     * Gera as propriedades do DTO com tipos PHP.
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
     * Gera o mapeamento fromArray do DTO.
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
     * Gera os métodos belongsTo para campos FK (_id com tipo inteiro grande).
     * Retorna string vazia se não houver FKs.
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
     * Gera declarações `use` para os models relacionados via FK.
     * Retorna string vazia se não houver FKs.
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

        $lines = array_unique(array_map(
            fn(FieldDefinition $f) => "use {$this->rootNamespace}Models\\{$f->relatedModel()};",
            $fkFields
        ));

        return implode("\n", $lines) . "\n";
    }

    /**
     * Gera os campos do toArray() do Resource.
     */
    public function resourceFields(): string
    {
        $lines = ["'id' => \$this->id,"];

        foreach ($this->fields as $field) {
            $lines[] = "            '{$field->name}' => \$this->{$field->name},";
        }

        $lines[] = "            'created_at' => \$this->created_at,";
        $lines[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $lines);
    }
}

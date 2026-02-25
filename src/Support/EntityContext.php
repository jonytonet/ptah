<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Value object (DTO imutável) que carrega todos os dados
 * calculados de uma entidade durante a execução do scaffold.
 *
 * Criado uma vez pelo ScaffoldCommand e passado a todos os Generators.
 */
readonly class EntityContext
{
    /**
     * @param FieldDefinition[] $fields
     */
    public function __construct(
        public string $entity,             // Product
        public string $entityLower,        // product
        public string $entityPlural,       // products
        public string $entityPluralStudly, // Products
        public string $table,              // products (ou valor de --table)
        public string $rootNamespace,      // App\
        public string $timestamp,          // 2026_02_25_120000
        public bool   $withViews,          // false se --api
        public bool   $withSoftDeletes,    // true por padrão
        public bool   $force,              // --force
        public array  $fields,
    ) {}

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

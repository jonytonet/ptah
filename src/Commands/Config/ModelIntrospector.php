<?php

namespace Ptah\Commands\Config;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ModelIntrospector
{
    /**
     * Get fillable fields from model
     */
    public function getFillable(string $modelClass): array
    {
        try {
            $model = new $modelClass;
            return $model->getFillable();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get casts from model
     */
    public function getCasts(string $modelClass): array
    {
        try {
            $model = new $modelClass;
            return $model->getCasts();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get table columns from database
     */
    public function getTableColumns(string $table): array
    {
        try {
            return Schema::getColumns($table);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get table name from model
     */
    public function getTable(string $modelClass): string
    {
        try {
            $model = new $modelClass;
            return $model->getTable();
        } catch (\Exception $e) {
            return Str::snake(Str::pluralStudly(class_basename($modelClass)));
        }
    }

    /**
     * Infer column type from casts and database schema
     */
    public function inferColumnType(string $field, array $casts, array $schemaInfo): string
    {
        // 1. Priority: $casts
        if (isset($casts[$field])) {
            return $this->mapCastToColumnType($casts[$field]);
        }

        // 2. Schema from database
        $column = collect($schemaInfo)->firstWhere('name', $field);
        if ($column) {
            return $this->mapDbTypeToColumnType($column['type_name']);
        }

        // 3. Default
        return 'text';
    }

    /**
     * Map Laravel cast to column type
     */
    protected function mapCastToColumnType(string $cast): string
    {
        return match (true) {
            $cast === 'int' || $cast === 'integer' => 'number',
            $cast === 'float' || $cast === 'double' || str_starts_with($cast, 'decimal') => 'number',
            $cast === 'bool' || $cast === 'boolean' => 'boolean',
            $cast === 'date' => 'date',
            $cast === 'datetime' || $cast === 'timestamp' => 'datetime',
            default => 'text',
        };
    }

    /**
     * Map database type to column type
     */
    protected function mapDbTypeToColumnType(string $dbType): string
    {
        return match (true) {
            str_contains($dbType, 'int') => 'number',
            str_contains($dbType, 'decimal') || str_contains($dbType, 'float') || str_contains($dbType, 'double') => 'number',
            str_contains($dbType, 'bool') => 'boolean',
            str_contains($dbType, 'date') && !str_contains($dbType, 'time') => 'date',
            str_contains($dbType, 'timestamp') || str_contains($dbType, 'datetime') => 'datetime',
            str_contains($dbType, 'text') => 'textarea',
            default => 'text',
        };
    }

    /**
     * Suggest renderer based on field name and type
     */
    public function suggestRenderer(string $type, string $field): string
    {
        // Smart suggestions based on field name
        if (in_array($field, ['status', 'situation', 'state'])) {
            return 'badge';
        }

        if (str_ends_with($field, '_at')) {
            return 'datetime';
        }

        if (str_contains($field, 'price') || str_contains($field, 'value') || str_contains($field, 'total') || str_contains($field, 'amount')) {
            return 'money';
        }

        if (str_contains($field, 'percent') || str_contains($field, 'rate')) {
            return 'number';
        }

        if ($field === 'email') {
            return 'text';
        }

        if (str_contains($field, 'url') || str_contains($field, 'link')) {
            return 'link';
        }

        if (str_contains($field, 'image') || str_contains($field, 'photo') || str_contains($field, 'avatar')) {
            return 'image';
        }

        return match ($type) {
            'boolean' => 'boolean',
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'datetime',
            default => 'text',
        };
    }

    /**
     * Suggest mask based on field name
     */
    public function suggestMask(string $field): ?string
    {
        return match (true) {
            $field === 'cpf' => 'cpf',
            $field === 'cnpj' => 'cnpj',
            in_array($field, ['phone', 'telefone', 'celular']) => 'phone',
            $field === 'cep' => 'cep',
            in_array($field, ['placa', 'plate']) => 'plate',
            str_contains($field, 'price') || str_contains($field, 'valor') => 'money_brl',
            str_contains($field, 'percent') => 'percent',
            $field === 'rg' => 'rg',
            $field === 'pis' => 'pis',
            $field === 'ncm' => 'ncm',
            default => null,
        };
    }

    /**
     * Get relations from model using reflection
     */
    public function getRelations(string $modelClass): array
    {
        try {
            $reflection = new ReflectionClass($modelClass);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            $relations = [];
            foreach ($methods as $method) {
                $returnType = $method->getReturnType();
                if ($returnType) {
                    $returnTypeName = $returnType instanceof \ReflectionNamedType 
                        ? $returnType->getName() 
                        : (string) $returnType;

                    if (in_array($returnTypeName, [
                        'Illuminate\Database\Eloquent\Relations\HasOne',
                        'Illuminate\Database\Eloquent\Relations\HasMany',
                        'Illuminate\Database\Eloquent\Relations\BelongsTo',
                        'Illuminate\Database\Eloquent\Relations\BelongsToMany',
                    ])) {
                        $relations[] = $method->getName();
                    }
                }
            }

            return $relations;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if field is likely a foreign key
     */
    public function isForeignKey(string $field): bool
    {
        return str_ends_with($field, '_id');
    }

    /**
     * Suggest relation name from foreign key
     */
    public function suggestRelationName(string $foreignKey): string
    {
        return Str::camel(str_replace('_id', '', $foreignKey));
    }

    /**
     * Check if model class exists and is valid Eloquent model
     */
    public function validateModelClass(string $modelClass): bool
    {
        return class_exists($modelClass) 
            && is_subclass_of($modelClass, 'Illuminate\Database\Eloquent\Model');
    }
}

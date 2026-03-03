<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Responsável por inspecionar campos de uma entidade.
 *
 * Duas estratégias:
 *  1. Banco de dados existente: Schema::getColumns() — portável (MySQL/PostgreSQL/SQLite)
 *  2. String de --fields: "name:string,price:decimal(10,2):nullable"
 */
class SchemaInspector
{
    /** Campos ignorados automaticamente na inspeção do BD */
    private const IGNORED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Lê as colunas de uma tabela existente no banco de dados.
     *
     * Usa Schema::getColumns() — disponível no Laravel 10.23+ e portável
     * entre MySQL, PostgreSQL e SQLite. Não usa SQL específico de driver.
     *
     * @return FieldDefinition[]
     */
    public function fromDatabase(string $table): array
    {
        try {
            $columns = Schema::getColumns($table);
        } catch (\Throwable) {
            return [];
        }

        $fields = [];

        foreach ($columns as $col) {
            if (in_array($col['name'], self::IGNORED_COLUMNS, true)) {
                continue;
            }

            $fields[] = $this->parseDbColumn($col);
        }

        return $fields;
    }

    /**
     * Parseia a string do argumento --fields.
     *
     * Formato:  campo:tipo[(params)][:nullable][:unique]
     * Exemplos:
     *   name:string
     *   price:decimal(10,2):nullable
     *   status:enum(active|inactive|pending)
     *   is_active:boolean
     *   email:string:unique
     *   user_id:unsignedBigInteger
     *
     * @return FieldDefinition[]
     */
    public function fromString(string $input): array
    {
        $fields = [];

        // Divide por vírgulas que NÃO estejam dentro de parênteses
        // Ex: "price:decimal(10,2),name:string" → ["price:decimal(10,2)", "name:string"]
        $parts = preg_split('/,(?![^(]*\))/', $input);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $fields[] = $this->parseFieldString($part);
        }

        return $fields;
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Parseia "name:type[(params)][:modifier...]" em FieldDefinition.
     */
    private function parseFieldString(string $fieldStr): FieldDefinition
    {
        $precision  = 10;
        $scale      = 2;
        $enumValues = [];

        // Extrai parâmetros entre parênteses antes de explodir por ':'
        $params = null;
        $fieldStr = preg_replace_callback(
            '/\(([^)]+)\)/',
            function (array $m) use (&$params): string {
                $params = $m[1];
                return '';
            },
            $fieldStr
        );

        $segments = explode(':', $fieldStr);
        $name     = $segments[0];
        $type     = isset($segments[1]) ? $this->normalizeType($segments[1]) : 'string';
        $nullable = in_array('nullable', $segments, true);
        $unique   = in_array('unique',   $segments, true);

        // Detecta surname=X ou label=X como rótulo de exibição no BaseCrud
        $label = '';
        foreach ($segments as $segment) {
            if (str_starts_with($segment, 'surname=')) {
                $label = substr($segment, 8);
                break;
            }
            if (str_starts_with($segment, 'label=')) {
                $label = substr($segment, 6);
                break;
            }
        }

        if ($params !== null) {
            if ($type === 'enum') {
                $enumValues = array_filter(array_map('trim', explode('|', $params)));
            } elseif ($type === 'decimal') {
                [$precision, $scale] = array_pad(
                    array_map('intval', explode(',', $params)),
                    2,
                    2
                );
            }
        }

        return new FieldDefinition(
            name:       $name,
            type:       $type,
            nullable:   $nullable,
            unique:     $unique,
            precision:  $precision,
            scale:      $scale,
            enumValues: array_values($enumValues),
            label:      $label,
        );
    }

    /**
     * Converte uma coluna retornada pelo Schema::getColumns() em FieldDefinition.
     *
     * Formato do array (Laravel 10.23+):
     *   'name'      => string  — nome da coluna
     *   'type_name' => string  — tipo base sem modificadores (ex: 'int', 'varchar')
     *   'type'      => string  — tipo completo com precisão/escala (ex: 'decimal(10,2)')
     *   'nullable'  => bool
     *   'comment'   => string|null
     *
     * @param  array<string, mixed>  $col
     */
    private function parseDbColumn(array $col): FieldDefinition
    {
        // Usa 'type' (completo) para detecção; fallback para 'type_name'
        $raw      = strtolower((string) ($col['type'] ?? $col['type_name'] ?? ''));
        $typeName = strtolower((string) ($col['type_name'] ?? ''));
        $nullable   = (bool) ($col['nullable'] ?? false);
        $unique     = false;
        $precision  = 10;
        $scale      = 2;
        $enumValues = [];

        $type = match (true) {
            str_contains($raw, 'tinyint(1)')                          => 'boolean',
            str_contains($raw, 'bigint unsigned')
                || ($typeName === 'bigint' && str_contains($raw, 'unsigned'))
                                                                      => 'unsignedBigInteger',
            $typeName === 'bigint'                                    => 'bigInteger',
            str_contains($typeName, 'int')                            => 'integer',
            str_contains($typeName, 'decimal')                        => 'decimal',
            in_array($typeName, ['float', 'double', 'real'], true)    => 'float',
            in_array($typeName, ['bool', 'boolean'], true)            => 'boolean',
            $typeName === 'enum'                                      => 'enum',
            in_array($typeName, ['datetime', 'timestamp', 'timestamptz'], true) => 'datetime',
            $typeName === 'date'                                      => 'date',
            str_contains($typeName, 'longtext')                       => 'longText',
            str_contains($typeName, 'text')                           => 'text',
            in_array($typeName, ['json', 'jsonb'], true)              => 'json',
            default                                                   => 'string',
        };

        if ($type === 'decimal') {
            preg_match('/decimal\((\d+),\s*(\d+)\)/', $raw, $m);
            $precision = (int) ($m[1] ?? 10);
            $scale     = (int) ($m[2] ?? 2);
        }

        if ($type === 'enum') {
            preg_match_all("/'([^']+)'/", $raw, $m);
            $enumValues = $m[1];
        }

        $label = isset($col['comment']) ? (string) $col['comment'] : '';

        return new FieldDefinition(
            name:       (string) ($col['name'] ?? ''),
            type:       $type,
            nullable:   $nullable,
            unique:     $unique,
            precision:  $precision,
            scale:      $scale,
            enumValues: $enumValues,
            label:      $label,
        );
    }

    /**
     * Normaliza aliases de tipo (int → integer, bool → boolean, etc.).
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'int'                => 'integer',
            'bigint'             => 'bigInteger',
            'uint', 'uint4'      => 'unsignedInteger',
            'ubigint', 'ubigint8'=> 'unsignedBigInteger',
            'bool'               => 'boolean',
            'float', 'double'    => 'float',
            'datetime'           => 'datetime',
            'timestamp'          => 'timestamp',
            'longtext'           => 'longText',
            'foreign'            => 'foreignId',
            default              => $type,
        };
    }
}

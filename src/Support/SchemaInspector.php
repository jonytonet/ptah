<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Responsible for inspecting entity fields.
 *
 * Two strategies:
 *  1. Existing database: Schema::getColumns() — portable (MySQL/PostgreSQL/SQLite)
 *  2. --fields string: "name:string,price:decimal(10,2):nullable"
 */
class SchemaInspector
{
    /** Fields automatically ignored when inspecting the DB */
    private const IGNORED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Reads columns from an existing database table.
     *
     * Uses Schema::getColumns() — available in Laravel 10.23+ and portable
     * across MySQL, PostgreSQL and SQLite. Does not use driver-specific SQL.
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

        // Splits by commas that are NOT inside parentheses
        // E.g. "price:decimal(10,2),name:string" → ["price:decimal(10,2)", "name:string"]
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

        // Extracts parameters within parentheses before exploding by ':'
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

        // Detects surname=X or label=X as the display label in BaseCrud
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
     *   'type'      => string  — full type with precision/scale (e.g. 'decimal(10,2)')
     *   'nullable'  => bool
     *   'comment'   => string|null
     *
     * @param  array<string, mixed>  $col
     */
    private function parseDbColumn(array $col): FieldDefinition
    {
        // Uses 'type' (full) for detection; fallback to 'type_name'
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

<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Responsável por inspecionar campos de uma entidade.
 *
 * Duas estratégias:
 *  1. Banco de dados existente: SHOW FULL COLUMNS FROM `table`
 *  2. String de --fields: "name:string,price:decimal(10,2):nullable"
 */
class SchemaInspector
{
    /** Campos ignorados automaticamente na inspeção do BD */
    private const IGNORED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Lê as colunas de uma tabela existente no banco de dados.
     *
     * @return FieldDefinition[]
     */
    public function fromDatabase(string $table): array
    {
        try {
            $columns = DB::select("SHOW FULL COLUMNS FROM `{$table}`");
        } catch (\Throwable) {
            return [];
        }

        $fields = [];

        foreach ($columns as $col) {
            if (in_array($col->Field, self::IGNORED_COLUMNS, true)) {
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
        );
    }

    /**
     * Converte uma coluna retornada pelo `SHOW FULL COLUMNS FROM` em FieldDefinition.
     */
    private function parseDbColumn(object $col): FieldDefinition
    {
        $raw        = strtolower((string) $col->Type);
        $nullable   = $col->Null === 'YES';
        $unique     = false;
        $precision  = 10;
        $scale      = 2;
        $enumValues = [];

        $type = match (true) {
            str_contains($raw, 'tinyint(1)')        => 'boolean',
            str_contains($raw, 'bigint unsigned')
                || str_contains($raw, 'bigint') && str_contains((string)($col->Extra ?? ''), 'unsigned')
                                                    => 'unsignedBigInteger',
            str_contains($raw, 'bigint')            => 'bigInteger',
            str_contains($raw, 'int')               => 'integer',
            str_contains($raw, 'decimal')           => 'decimal',
            str_contains($raw, 'float')
                || str_contains($raw, 'double')     => 'float',
            str_contains($raw, 'bool')              => 'boolean',
            str_starts_with($raw, 'enum')           => 'enum',
            str_contains($raw, 'datetime')
                || str_contains($raw, 'timestamp')  => 'datetime',
            $raw === 'date'                         => 'date',
            str_contains($raw, 'longtext')          => 'longText',
            str_contains($raw, 'text')              => 'text',
            str_contains($raw, 'json')              => 'json',
            default                                 => 'string',
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

        return new FieldDefinition(
            name:       $col->Field,
            type:       $type,
            nullable:   $nullable,
            unique:     $unique,
            precision:  $precision,
            scale:      $scale,
            enumValues: $enumValues,
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
            default              => $type,
        };
    }
}

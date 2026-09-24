<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * Reads a spreadsheet into rows and lines its columns up with a screen's form.
 *
 * The half of CRUD import that knows nothing about Livewire: parsing, header
 * matching and turning a cell into the value the form would have sent. The
 * other half (HasCrudImport) validates each row with the screen's own rules
 * and persists through the screen's own scope, hooks and audit stamps.
 *
 * Real files, not ideal ones. A CSV saved by Excel in Brazil is `;`-separated
 * and Windows-1252; a header says "Preço" where the field is `price`; a
 * select column holds "Ativo" where the form sends `1`; a relation column
 * holds "Parafusos" where the form sends `category_id = 7`. Each of those is
 * handled here instead of becoming a row error the user cannot fix.
 */
final class CrudImportReader
{
    public const EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls'];

    /**
     * @return array{headers: list<string>, rows: list<list<mixed>>}
     */
    public static function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new RuntimeException("Unsupported file type .{$extension} — use ".implode(', ', self::EXTENSIONS).'.');
        }

        $table = in_array($extension, ['csv', 'txt'], true) ? self::readCsv($path) : self::readSpreadsheet($path);

        // Linhas totalmente vazias (o fim de uma planilha do Excel) nao sao dados.
        $table = array_values(array_filter($table, fn (array $row) => array_filter($row, fn ($c) => $c !== null && trim((string) $c) !== '') !== []));

        if ($table === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($table));

        return ['headers' => $headers, 'rows' => $table];
    }

    /**
     * header index => form field, by name or label, accents and case ignored.
     *
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $formCols
     * @return array<int, string>
     */
    public static function autoMap(array $headers, array $formCols): array
    {
        $byKey = [];
        foreach ($formCols as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            if ($field === '') {
                continue;
            }
            $byKey[self::key($field)] ??= $field;
            if (! empty($col['colsNomeLogico'])) {
                $byKey[self::key((string) $col['colsNomeLogico'])] ??= $field;
            }
        }

        $map = [];
        $taken = [];
        foreach ($headers as $i => $header) {
            $field = $byKey[self::key($header)] ?? null;
            if ($field !== null && ! isset($taken[$field])) {
                $map[$i] = $field;
                $taken[$field] = true;
            }
        }

        return $map;
    }

    /**
     * A cell as the form would have sent it, or an error message.
     *
     * @param  array<string, mixed>  $col
     * @param  (\Closure(Builder): void)|null  $scopeRelated  narrows a
     *                                                        relation lookup (the active company) — a name must never resolve to
     *                                                        another tenant's record
     * @return array{0: mixed, 1: string|null} [value, error]
     */
    public static function coerce(mixed $cell, array $col, ?\Closure $scopeRelated = null): array
    {
        if ($cell === null || (is_string($cell) && trim($cell) === '')) {
            return [null, null];
        }

        $value = is_string($cell) ? trim($cell) : $cell;
        $type = (string) ($col['colsTipo'] ?? 'text');
        $label = (string) ($col['colsNomeLogico'] ?? $col['colsNomeFisico'] ?? '');

        // select: a planilha traz o rotulo ("Ativo"); o form envia a chave.
        if (! empty($col['colsSelect']) && is_array($col['colsSelect'])) {
            $options = $col['colsSelect'];

            foreach ($options as $optLabel => $optValue) {
                if ((string) $optValue === (string) $value) {
                    return [$optValue, null];
                }
            }
            foreach ($options as $optLabel => $optValue) {
                if (self::key((string) $optLabel) === self::key((string) $value)) {
                    return [$optValue, null];
                }
            }

            return [null, "\"{$value}\" is not an option of {$label}"];
        }

        if ($type === 'boolean') {
            $k = self::key((string) $value);

            return match (true) {
                in_array($k, ['1', 'sim', 's', 'yes', 'y', 'true', 'verdadeiro', 'x'], true) => [1, null],
                in_array($k, ['0', 'nao', 'n', 'no', 'false', 'falso'], true) => [0, null],
                default => [null, "\"{$value}\" is not yes/no for {$label}"],
            };
        }

        // searchdropdown com model: a planilha traz o nome ("Parafusos"); o
        // form envia o id. Um id numerico passa direto.
        if ($type === 'searchdropdown' && ! empty($col['colsSDModel']) && ! is_numeric($value)) {
            $model = str_replace('/', '\\', (string) $col['colsSDModel']);
            $labelField = (string) ($col['colsSDLabel'] ?? 'name');
            $valueField = (string) ($col['colsSDValor'] ?? $col['colsSDValueField'] ?? 'id');

            if (! class_exists($model) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $labelField.$valueField) !== 1) {
                return [null, "{$label}: cannot resolve \"{$value}\" (relation not configured)"];
            }

            $query = $model::query()->where($labelField, $value);
            if ($scopeRelated !== null) {
                $scopeRelated($query);
            }
            $ids = $query->limit(2)->pluck($valueField)->all();

            return match (count($ids)) {
                1 => [$ids[0], null],
                0 => [null, "{$label} \"{$value}\" not found"],
                default => [null, "{$label} \"{$value}\" matches more than one record — use its id"],
            };
        }

        // "1.234,56" numa coluna numerica sem mascara: o validador `numeric`
        // recusaria, e e assim que o Excel brasileiro exporta.
        if ($type === 'number' && is_string($value) && empty($col['colsMask']) && preg_match('/^-?[\d.]*,\d+$/', $value) === 1) {
            return [(float) str_replace(['.', ','], ['', '.'], $value), null];
        }

        // Excel guarda data como serial numerico.
        if (in_array($type, ['date', 'datetime', 'datetime-local'], true) && is_numeric($value) && (float) $value > 20000) {
            $date = Date::excelToDateTimeObject((float) $value);

            return [$type === 'date' ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s'), null];
        }

        return [$value, null];
    }

    /**
     * @return list<list<mixed>>
     */
    private static function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);

        // BOM do Excel e encoding Windows-1252 dos CSV salvos no Brasil.
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($raw, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : (substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',');

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<mixed>>
     */
    private static function readSpreadsheet(string $path): array
    {
        $sheets = Excel::toArray(new class {}, $path);

        return array_values($sheets[0] ?? []);
    }

    private static function key(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($s)));
    }
}

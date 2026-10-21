<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A screen's `joins` applied to a query — one place for the listing and the
 * export (1.50.0).
 *
 * The export rebuilt its rows from `whereIn(ids)` without the JOINs, so every
 * column that came from a joined table (`supplier_name`) left the file
 * empty. The listing's own applyJoins() now delegates here, and the sync and
 * queued exports call it with the joins carried in their payload.
 */
final class CrudJoins
{
    /**
     * @param  array<int, array<string, mixed>>  $joins  the screen config's `joins`
     * @return string[] names of the joined tables
     */
    public static function apply(Builder $query, Model $modelInstance, array $joins): array
    {
        if ($joins === []) {
            return [];
        }

        $mainTable = $modelInstance->getTable();
        $joinedTables = [];
        $selectCols = ["{$mainTable}.*"];
        $useDistinct = false;

        foreach ($joins as $join) {
            $table = trim((string) ($join['table'] ?? ''));
            $first = trim((string) ($join['first'] ?? ''));
            $second = trim((string) ($join['second'] ?? ''));
            $type = strtolower((string) ($join['type'] ?? 'left'));

            if (! $table || ! $first || ! $second) {
                continue;
            }

            // Avoid duplicate JOIN on the same query
            if (in_array($table, $joinedTables, true)) {
                continue;
            }

            if ($type === 'inner') {
                $query->join($table, $first, '=', $second);
            } else {
                $query->leftJoin($table, $first, '=', $second);
            }

            $joinedTables[] = $table;

            foreach ($join['select'] ?? [] as $sel) {
                $col = trim((string) ($sel['column'] ?? ''));
                $alias = trim((string) ($sel['alias'] ?? ''));
                // Both are interpolated into a raw SELECT expression — guard as
                // identifiers (config-driven, so never trust them). Skip if unsafe.
                if ($col && $alias && SqlIdentifier::isSafe($col) && SqlIdentifier::isSafe($alias)) {
                    $selectCols[] = DB::raw("{$col} AS {$alias}");
                }
            }

            if (! empty($join['distinct'])) {
                $useDistinct = true;
            }
        }

        if ($joinedTables !== []) {
            $query->select($selectCols);

            if ($useDistinct) {
                $query->distinct();
            }
        }

        return $joinedTables;
    }
}

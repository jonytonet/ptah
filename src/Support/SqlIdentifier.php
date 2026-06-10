<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Validates SQL identifiers (column / table names) before they are interpolated
 * into raw SQL fragments (whereRaw, havingRaw, etc.).
 *
 * Filter strategies receive the column name from the CRUD configuration. Even
 * though that configuration is admin-controlled, concatenating it directly into
 * raw SQL is an injection vector if the config is ever tampered with. This guard
 * accepts only plain identifiers and optionally table-qualified ones
 * (e.g. `name`, `created_at`, `users.name`), rejecting anything with quotes,
 * parentheses, spaces, comments or operators.
 */
final class SqlIdentifier
{
    /**
     * Optionally table-qualified identifier: `column` or `table.column`.
     */
    private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    public static function isSafe(?string $identifier): bool
    {
        if ($identifier === null || $identifier === '') {
            return false;
        }

        return (bool) preg_match(self::PATTERN, $identifier);
    }
}
